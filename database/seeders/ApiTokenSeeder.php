<?php

namespace Database\Seeders;

use App\Enums\Api\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A token for the Rekordbox CLI tool (#168), so the now-playing API (#124) can be tried straight
 * after a reset. Its value is fixed, like the role users' passwords, so the CLI's config keeps
 * working across resets. It's a local test token only.
 *
 * Run within a tenant, after the role users, e.g. `php artisan tenants:seed --class=ApiTokenSeeder`.
 * Safe to re-run: the token is only made when the user doesn't have it.
 */
class ApiTokenSeeder extends Seeder
{
    public const string NAME = 'Rekordbox CLI';

    /** The plain token, as the CLI sends it */
    public const string TOKEN = 'lxst_LocalDemoCliToken0000000000000000000000';

    /** @var list<ApiTokenAbility> */
    public const array ABILITIES = [ApiTokenAbility::DjNowPlaying];

    public function run(?User $user = null): void
    {
        if ($user === null || $user->tokens()->where('name', self::NAME)->exists()) {
            return;
        }

        $user->tokens()->create([
            'name' => self::NAME,
            'token' => hash('sha256', self::TOKEN),
            'abilities' => array_map(fn (ApiTokenAbility $ability) => $ability->value, self::ABILITIES),
        ]);

        $this->command?->info(self::NAME.' token ('.$user->email.'): '.self::TOKEN);
    }

    /**
     * The seeded token, if it's there
     */
    public static function token(): ?ApiToken
    {
        return ApiToken::query()->where('token', hash('sha256', self::TOKEN))->first();
    }
}
