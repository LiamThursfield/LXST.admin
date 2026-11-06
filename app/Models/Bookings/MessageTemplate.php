<?php

namespace App\Models\Bookings;

use App\Enums\Bookings\MessageEvent;
use Database\Factories\Bookings\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's own wording for one kind of booking message. Blank parts use the default wording.
 *
 * @property int $id
 * @property MessageEvent $event
 * @property bool $is_enabled
 * @property int|null $offset_hours
 * @property string|null $email_subject
 * @property string|null $email_body
 * @property string|null $sms_body
 * @property string|null $whatsapp_template_sid
 */
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory;

    protected $table = 'bookings_message_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event',
        'is_enabled',
        'offset_hours',
        'email_subject',
        'email_body',
        'sms_body',
        'whatsapp_template_sid',
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
            'is_enabled' => 'boolean',
            'offset_hours' => 'integer',
        ];
    }
}
