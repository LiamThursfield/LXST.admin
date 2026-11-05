<?php

namespace App\Services\Messaging;

use App\Models\Settings\MessagingSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sends texts and WhatsApp messages through the tenant's Twilio account, with Laravel's HTTP client
 * rather than Twilio's package. WhatsApp messages started by a business have to use a template Meta
 * has approved, filled in with numbered variables.
 */
class TwilioClient
{
    protected const string API = 'https://api.twilio.com/2010-04-01';

    public function __construct(protected MessagingSettings $settings) {}

    /**
     * @return string Twilio's ID for the message
     *
     * @throws MessagingException
     */
    public function sendSms(string $to, string $body): string
    {
        return $this->send([
            'To' => $to,
            'From' => (string) $this->settings->sms_from,
            'Body' => $body,
        ]);
    }

    /**
     * @param  array<int, string>  $variables  By number, as the template has them: {{1}}, {{2}} and so on
     * @return string Twilio's ID for the message
     *
     * @throws MessagingException
     */
    public function sendWhatsApp(string $to, string $templateSid, array $variables): string
    {
        return $this->send([
            'To' => "whatsapp:{$to}",
            'From' => 'whatsapp:'.$this->settings->whatsapp_from,
            'ContentSid' => $templateSid,
            'ContentVariables' => (string) json_encode((object) array_map(strval(...), $variables)),
        ]);
    }

    /**
     * @param  array<string, string>  $message
     */
    protected function send(array $message): string
    {
        $accountSid = (string) $this->settings->twilio_account_sid;

        $response = Http::asForm()
            ->withBasicAuth($accountSid, (string) $this->settings->twilio_auth_token)
            ->timeout(15)
            ->post(self::API."/Accounts/{$accountSid}/Messages.json", $message);

        if ($response->failed()) {
            throw new MessagingException($this->errorFrom($response));
        }

        return (string) $response->json('sid');
    }

    protected function errorFrom(Response $response): string
    {
        $message = $response->json('message');

        return is_string($message) && $message !== '' ? $message : "Twilio returned {$response->status()}.";
    }
}
