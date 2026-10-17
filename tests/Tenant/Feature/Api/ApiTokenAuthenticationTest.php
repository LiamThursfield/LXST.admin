<?php

use App\Enums\Api\ApiTokenAbility;
use App\Models\User;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

beforeEach(function () {
    // Stands in for the first token endpoints (library sync and now playing come later)
    Route::middleware(['api', InitializeTenancyByDomain::class, 'auth.tenant-token:dj:sync', 'throttle:tenant-api-token'])
        ->post('/api/v1/token-test', fn (Request $request) => ['user' => $request->user()?->getKey()]);

    $this->owner = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens]);
});

/**
 * Sends a request to the test endpoint with the given bearer token
 */
function callWithToken(mixed $test, ?string $token): mixed
{
    // Each request should authenticate afresh, as a real one would
    Auth::forgetGuards();

    return $test->withToken($token ?? '')->postJson('/api/v1/token-test');
}

test('a token with the ability is let through, acting as the user who created it', function () {
    $token = $this->owner->createToken('CLI', [ApiTokenAbility::DjSync->value]);

    callWithToken($this, $token->plainTextToken)
        ->assertOk()
        ->assertExactJson(['user' => $this->owner->id]);

    expect($token->accessToken->fresh()->last_used_at)->not->toBeNull();
});

test('new tokens start with the lxst_ prefix so secret scanners can spot them', function () {
    $token = $this->owner->createToken('CLI', [ApiTokenAbility::DjSync->value]);

    expect(explode('|', $token->plainTextToken, 2)[1])->toStartWith('lxst_')
        ->and($token->accessToken->token)->not->toContain('lxst_');
});

test('requests without a valid token are unauthenticated', function (?string $token) {
    callWithToken($this, $token)
        ->assertUnauthorized()
        ->assertJson(['message' => __('site_settings.api_tokens.api.unauthenticated')]);
})->with([
    'no token' => null,
    'unknown token' => '999|lxst_not-a-real-token',
    'malformed token' => 'nonsense',
]);

test('a token without the ability is forbidden', function () {
    $token = $this->owner->createToken('Now playing', [ApiTokenAbility::DjNowPlaying->value]);

    callWithToken($this, $token->plainTextToken)
        ->assertForbidden()
        ->assertJson(['message' => __('site_settings.api_tokens.api.missing_ability', ['ability' => 'dj:sync'])]);
});

test('an expired token is unauthenticated', function () {
    $token = $this->owner->createToken('CLI', [ApiTokenAbility::DjSync->value], now()->subMinute());

    callWithToken($this, $token->plainTextToken)->assertUnauthorized();
});

test('a revoked token is unauthenticated', function () {
    $token = $this->owner->createToken('CLI', [ApiTokenAbility::DjSync->value]);
    $token->accessToken->delete();

    callWithToken($this, $token->plainTextToken)->assertUnauthorized();
});

test('a token stops working when its user can no longer manage API tokens', function () {
    $token = $this->owner->createToken('CLI', [ApiTokenAbility::DjSync->value]);
    $this->owner->revokePermissionTo(SettingsPermission::ManageApiTokens);

    callWithToken($this, $token->plainTextToken)->assertUnauthorized();
});

test('a logged-in admin session does not pass for a token', function () {
    $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));

    $this->postJson('/api/v1/token-test')->assertUnauthorized();
});

test('deleting a user deletes their tokens', function () {
    $token = $this->owner->createToken('CLI', [ApiTokenAbility::DjSync->value]);

    $this->owner->delete();

    expect($token->accessToken->fresh())->toBeNull();
});

test('token requests are rate limited per token', function () {
    config(['api.tokens.rate_limit_per_minute' => 2]);

    $first = $this->owner->createToken('First', [ApiTokenAbility::DjSync->value])->plainTextToken;
    $second = User::find($this->owner->id)->createToken('Second', [ApiTokenAbility::DjSync->value])->plainTextToken;

    callWithToken($this, $first)->assertOk();
    callWithToken($this, $first)->assertOk();
    callWithToken($this, $first)->assertTooManyRequests();

    // Another token has its own limit
    callWithToken($this, $second)->assertOk();
});
