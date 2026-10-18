<?php

namespace App\Http\Requests\Concerns;

use App\Services\Cms\Api\VisitorPrivacy;
use Carbon\CarbonInterface;

/**
 * The visitor behind a public API request. Sites calling from their server pass the visitor's
 * `ip_address` and `user_agent`; left out, the request's own are used (right when the visitor's
 * browser calls the API).
 */
trait IdentifiesVisitor
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function visitorRules(): array
    {
        return [
            'user_agent' => ['nullable', 'string', 'max:512'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }

    public function visitorUserAgent(): ?string
    {
        return $this->filled('user_agent') ? $this->string('user_agent')->toString() : $this->userAgent();
    }

    public function visitorIp(): ?string
    {
        return $this->filled('ip_address') ? $this->string('ip_address')->toString() : $this->ip();
    }

    /**
     * The hashed visitor for a day, e.g. a gig's, so it holds for the whole gig
     */
    public function visitorHash(CarbonInterface $day): ?string
    {
        return app(VisitorPrivacy::class)->visitorHash($this->visitorIp(), $this->visitorUserAgent(), $day);
    }
}
