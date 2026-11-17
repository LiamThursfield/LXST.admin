<?php

/*
| Shared by the MCP server tests: calls the server the way an MCP client does, over HTTP
*/

use App\Enums\Api\ApiTokenAbility;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\CrmPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/**
 * Sends one JSON-RPC message to the MCP server with the token
 *
 * @param  array<string, mixed>  $params
 */
function mcpRequest(mixed $test, ?string $token, string $method, array $params = []): TestResponse
{
    // Each request should authenticate afresh, as a real one would
    Auth::forgetGuards();

    $test->flushHeaders();

    if ($token !== null) {
        $test->withToken($token);
    }

    return $test->postJson(route('api.mcp'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

/**
 * Calls a tool and gives back the JSON-RPC result (content, structuredContent and isError), or
 * `error` for a JSON-RPC error, e.g. an unknown tool
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function mcpCall(mixed $test, string $token, string $tool, array $arguments = []): array
{
    $response = mcpRequest($test, $token, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);

    return $response->json('result') ?? ['error' => $response->json('error')];
}

/**
 * The names of the tools the token sees
 *
 * @return list<string>
 */
function mcpToolNames(mixed $test, string $token): array
{
    return collect(mcpRequest($test, $token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();
}

/**
 * A user who can do everything the MCP tools can, and manage API tokens
 */
function mcpUser(mixed $test): User
{
    return $test->createUserWithPermissions([
        SettingsPermission::ManageApiTokens,
        CmsPermission::ViewContent, CmsPermission::ManageContent,
        CmsPermission::ViewStructure, CmsPermission::ManageStructure,
        CrmPermission::ViewForms, CrmPermission::ManageForms,
    ]);
}

/**
 * A plain token for the user with the abilities (every MCP ability by default)
 *
 * @param  list<ApiTokenAbility>|null  $abilities
 */
function mcpToken(User $user, ?array $abilities = null): string
{
    return $user->createToken('Claude Code', array_map(
        fn (ApiTokenAbility $ability) => $ability->value,
        $abilities ?? ApiTokenAbility::mcp(),
    ))->plainTextToken;
}
