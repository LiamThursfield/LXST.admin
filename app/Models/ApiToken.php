<?php

namespace App\Models;

use App\Enums\Api\ApiTokenAbility;
use Carbon\CarbonImmutable;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A token that lets a trusted client, e.g. the Rekordbox CLI tool, call the tenant API.
 * Created by a user, and requests made with it act as them.
 *
 * @property int $id
 * @property string $name
 * @property string $token
 * @property list<string> $abilities
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $tokenable
 */
class ApiToken extends PersonalAccessToken
{
    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    protected $table = 'api_tokens';

    /**
     * The user who created the token
     *
     * @return MorphTo<User, $this>
     */
    public function tokenable(): MorphTo
    {
        /** @var MorphTo<User, $this> */
        return $this->morphTo('tokenable');
    }

    /**
     * The token's abilities, ignoring any this app no longer knows
     *
     * @return list<ApiTokenAbility>
     */
    public function abilityEnums(): array
    {
        return array_values(array_filter(array_map(
            fn (string $ability) => ApiTokenAbility::tryFrom($ability),
            $this->abilities ?? [],
        )));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
