<?php

namespace App\Models\Dj;

use App\Enums\Dj\CanonicalRecord;
use Carbon\CarbonImmutable;
use Database\Factories\Dj\MatchDismissalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A suggested duplicate someone said isn't the same, so it isn't suggested again
 *
 * @property int $id
 * @property CanonicalRecord $record
 * @property int $first_id The smaller ID of the pair
 * @property int $second_id
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class MatchDismissal extends Model
{
    /** @use HasFactory<MatchDismissalFactory> */
    use HasFactory;

    protected $table = 'dj_match_dismissals';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'record',
        'first_id',
        'second_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record' => CanonicalRecord::class,
        ];
    }
}
