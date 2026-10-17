<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasApiTokens<ApiToken> */
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * A deleted user's API tokens go with them, so nothing keeps acting as someone who's gone.
     * Only tenants have API tokens; central users share this model but not the table.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if (tenant() !== null) {
                $user->tokens()->delete();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Helper to return first + last name.
     */
    public function getNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * The user's IANA timezone, falling back to the app timezone when unset.
     */
    public function localTimezone(): string
    {
        $timezone = $this->getAttribute('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : (string) config('app.timezone');
    }

    /**
     * The current instant on the user's wall clock.
     */
    public function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now($this->localTimezone());
    }

    /**
     * The user's current calendar day.
     *
     * @see self::localDate()
     */
    public function localToday(): CarbonImmutable
    {
        return $this->localDate(CarbonImmutable::now());
    }

    /**
     * The calendar day an instant falls on for this user, as midnight in the
     * app timezone. Calendar days (period_start, active_from, ...) are stored
     * as timezone-less dates, so this keeps comparisons against them exact.
     */
    public function localDate(CarbonInterface $instant): CarbonImmutable
    {
        $day = CarbonImmutable::instance($instant)->setTimezone($this->localTimezone())->toDateString();

        return CarbonImmutable::parse($day, (string) config('app.timezone'))->startOfDay();
    }

    /**
     * Interpret a wall-clock date/time the user entered as local time,
     * returning the instant in the app timezone (UTC) for storage.
     */
    public function parseLocal(string $wallTime): CarbonImmutable
    {
        return CarbonImmutable::parse($wallTime, $this->localTimezone())
            ->setTimezone((string) config('app.timezone'));
    }
}
