<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\MessageEvent;
use App\Enums\Bookings\MessageStatus;
use Carbon\CarbonInterface;
use Database\Factories\Bookings\MessageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One booking message: who it went to, how, and whether it got there
 *
 * @property int $id
 * @property int|null $booking_id
 * @property MessageEvent $event
 * @property MessageChannel $channel
 * @property string|null $recipient
 * @property string|null $body
 * @property MessageStatus $status
 * @property string|null $note
 * @property string|null $provider_message_id
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $created_at
 */
class MessageLog extends Model
{
    /** @use HasFactory<MessageLogFactory> */
    use HasFactory;

    protected $table = 'bookings_message_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'event',
        'channel',
        'recipient',
        'body',
        'status',
        'note',
        'provider_message_id',
        'sent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => MessageEvent::class,
            'channel' => MessageChannel::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
