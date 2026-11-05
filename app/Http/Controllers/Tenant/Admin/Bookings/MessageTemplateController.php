<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\MessageEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\MessageTemplateUpdateRequest;
use App\Models\Bookings\MessageTemplate;
use App\Models\Settings\MessagingSettings;
use App\Services\Authorisation\Enums\BookingsPermission;
use App\Services\Bookings\BookingMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The wording of the messages customers get about their bookings, by email, text and WhatsApp
 */
class MessageTemplateController extends Controller
{
    public function index(Request $request, BookingMessages $messages, MessagingSettings $settings): Response
    {
        Gate::authorize(BookingsPermission::ManageMessages);

        return Inertia::render('admin/bookings/message-template/Index', [
            'events' => array_map(fn (MessageEvent $event) => [
                'value' => $event->value,
                'label' => $event->label(),
                'description' => __("bookings.messages.events.{$event->value}"),
                'is_enabled' => $messages->wordingFor($event)['is_enabled'],
                'has_whatsapp_template' => filled($messages->wordingFor($event)['whatsapp_template_sid']),
            ], MessageEvent::cases()),
            'canSendSms' => $settings->canSendSms(),
            'canSendWhatsApp' => $settings->canSendWhatsApp(),
        ]);
    }

    public function edit(string $event, BookingMessages $messages): Response
    {
        Gate::authorize(BookingsPermission::ManageMessages);

        $messageEvent = MessageEvent::tryFrom($event) ?? abort(404);
        $template = MessageTemplate::query()->firstWhere('event', $messageEvent->value);

        return Inertia::render('admin/bookings/message-template/Edit', [
            'event' => ['value' => $messageEvent->value, 'label' => $messageEvent->label()],
            // Blank uses the default, which is shown as the placeholder
            'template' => [
                'is_enabled' => $template->is_enabled ?? true,
                'email_subject' => $template?->email_subject,
                'email_body' => $template?->email_body,
                'sms_body' => $template?->sms_body,
                'whatsapp_template_sid' => $template?->whatsapp_template_sid,
            ],
            'defaults' => $messages->defaultsFor($messageEvent),
            'placeholders' => BookingMessages::PLACEHOLDERS,
        ]);
    }

    public function update(MessageTemplateUpdateRequest $request, string $event): RedirectResponse
    {
        $messageEvent = MessageEvent::tryFrom($event) ?? abort(404);

        MessageTemplate::query()->updateOrCreate(['event' => $messageEvent->value], $request->validated());

        return redirect()->route('admin.bookings.messages.index')
            ->with('success', __('bookings.messages.flash.updated'));
    }
}
