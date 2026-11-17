<?php

namespace App\Mcp\Tools;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\ContentScope;
use App\Enums\Api\McpOutcome;
use App\Enums\TenantModule;
use App\Mcp\Support\AuditTrail;
use App\Mcp\Support\ToolCall;
use App\Mcp\Support\ToolRefused;
use App\Models\ApiToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cms\Api\ContentVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * A tool on the tenant's MCP server. A tool is only listed, and can only be called, when the
 * request's API token has the tool's ability (granted explicitly, not by a `*` token), the tenant
 * has the tool's module, and the token's user has the permissions the admin would need. Each call is
 * checked again when it runs, and saved to the audit log however it ends. Tools that change
 * something are rate limited per token and refresh the headless API's cached content.
 */
abstract class McpTool extends Tool
{
    /**
     * The ability the token needs to use this tool
     */
    abstract public function ability(): ApiTokenAbility;

    /**
     * The tenant module the tool belongs to
     */
    abstract protected function module(): TenantModule;

    /**
     * Whether the token's user may use the tool, e.g. their CMS permissions. Each record is also
     * checked by the admin's policies as the tool runs.
     */
    abstract protected function userCan(User $user): bool;

    /**
     * Does the tool's work, recording what it acted on and changed in the call
     */
    abstract protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory;

    /**
     * Whether the tool changes anything (rate limited per token, and refreshes the API's cache)
     */
    protected function changesContent(): bool
    {
        return false;
    }

    public function shouldRegister(): bool
    {
        $user = $this->user();
        $tenant = tenant();

        return $user instanceof User
            && $this->tokenHas($this->ability())
            && $tenant instanceof Tenant
            && $tenant->hasModule($this->module())
            && $this->userCan($user);
    }

    public function handle(Request $request, AuditTrail $audit): Response|ResponseFactory
    {
        $user = $this->user();
        $call = new ToolCall($this->name(), $this->ability(), $request->all());

        try {
            if (! $user instanceof User || ! $this->shouldRegister()) {
                throw new AuthorizationException(__('mcp.errors.not_allowed'));
            }

            if ($this->changesContent() && ! $this->withinWriteLimit()) {
                $call->outcome = McpOutcome::RateLimited;
                $call->message = __('mcp.errors.rate_limited');

                return Response::error($call->message);
            }

            $response = $this->run($request, $user, $call);

            if ($this->changesContent()) {
                ContentVersion::for(ContentScope::Cms)->bump();
            }

            $call->outcome = McpOutcome::Success;

            return $response;
        } catch (ValidationException $exception) {
            $call->outcome = McpOutcome::Invalid;
            $call->message = collect($exception->errors())->flatten()->implode(' ');

            throw $exception;
        } catch (AuthorizationException $exception) {
            $call->outcome = McpOutcome::Denied;
            $call->message = $exception->getMessage();

            throw $exception;
        } catch (ToolRefused $refused) {
            $call->outcome = McpOutcome::Denied;
            $call->message = $refused->getMessage();

            return Response::error($refused->getMessage());
        } catch (Throwable $exception) {
            $call->outcome = McpOutcome::Error;
            $call->message = $exception->getMessage();

            throw $exception;
        } finally {
            $audit->record($call, $user, $this->token());
        }
    }

    /**
     * The token's user, who the tool acts as
     */
    protected function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function token(): ?ApiToken
    {
        $token = $this->user()?->currentAccessToken();

        return $token instanceof ApiToken ? $token : null;
    }

    /**
     * Whether the token was given the ability. MCP abilities must be listed on the token: a token
     * made in code with every ability (`*`) doesn't get them.
     */
    protected function tokenHas(ApiTokenAbility $ability): bool
    {
        return in_array($ability->value, $this->token()->abilities ?? [], true);
    }

    /**
     * Counts a change against the token's limit, if it's not already reached
     */
    protected function withinWriteLimit(): bool
    {
        $key = 'mcp-writes|'.tenant()?->getTenantKey().'|'.$this->token()?->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('api.mcp.writes_per_minute'))) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    /**
     * The record as structured content (with a JSON text copy, for clients that only read text)
     *
     * @param  array<string, mixed>  $data
     */
    protected function result(array $data): ResponseFactory
    {
        return Response::structured($data);
    }
}
