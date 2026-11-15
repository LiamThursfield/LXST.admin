<?php

namespace App\Services\Dj\Requests;

use App\Http\Requests\Tenant\Api\V1\Dj\SongRequestStoreRequest;
use App\Models\Dj\Gig;
use App\Services\Captcha\Captcha;
use App\Services\Captcha\CaptchaResult;
use Illuminate\Support\Facades\Cache;

/**
 * The captcha on a gig's requests (#74). When the gig asks for one, only a visitor's first request
 * needs it: once they've passed, it's remembered for the rest of the gig, so later requests don't.
 */
class RequestCaptcha
{
    public function __construct(protected Captcha $captcha) {}

    /**
     * Whether the gig asks for a captcha, and the tenant has one set up
     */
    public function isOn(Gig $gig): bool
    {
        return $gig->requestSettings()->require_captcha && $this->captcha->isConfigured();
    }

    /**
     * What a site needs to render the widget, or null when the gig doesn't ask for a captcha
     *
     * @return array{provider: string, site_key: string, token_field: string}|null
     */
    public function widget(Gig $gig): ?array
    {
        return $this->isOn($gig) ? $this->captcha->widget() : null;
    }

    /**
     * Checks the visitor's captcha, unless the gig doesn't ask for one or they've already passed one
     * for it. A pass is remembered for the rest of the gig. When the provider can't be reached, the
     * request goes through (the DJ sees every request anyway), but nothing is remembered.
     */
    public function check(SongRequestStoreRequest $request, Gig $gig): CaptchaResult
    {
        $visitor = $request->visitorHash($gig->starts_at);

        if (! $this->isOn($gig) || ($visitor !== null && Cache::has($this->passKey($gig, $visitor)))) {
            return CaptchaResult::Passed;
        }

        $result = $this->captcha->check($request, $request->visitorIp());

        if ($result === CaptchaResult::Passed && $visitor !== null) {
            Cache::put($this->passKey($gig, $visitor), true, $gig->ends_at->copy()->addDay());
        }

        return $result;
    }

    protected function passKey(Gig $gig, string $visitor): string
    {
        return "dj:captcha-passed:{$gig->id}:{$visitor}";
    }
}
