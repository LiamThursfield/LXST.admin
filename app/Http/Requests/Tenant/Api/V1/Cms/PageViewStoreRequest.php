<?php

namespace App\Http\Requests\Tenant\Api\V1\Cms;

use App\Models\Cms\Redirect;
use Illuminate\Foundation\Http\FormRequest;

class PageViewStoreRequest extends FormRequest
{
    /**
     * The UTM parameters kept as the view's source
     *
     * @var list<string>
     */
    public const array SOURCE_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The path viewed, e.g. /about/team (a full URL is cut down to its path)
            'url' => ['required', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            // The visitor's, which the site passes on when it calls from its server. Left out,
            // the request's own are used (right when the visitor's browser calls the API).
            'user_agent' => ['nullable', 'string', 'max:512'],
            'ip_address' => ['nullable', 'ip'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'array:'.implode(',', self::SOURCE_KEYS)],
            'source.*' => ['nullable', 'string', 'max:255'],
            'additional_data' => ['nullable', 'array', 'max:20'],
            'additional_data.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The viewed path, tidied like stored URLs (leading slash, no trailing slash, no query)
     */
    public function sitePath(): string
    {
        $path = Redirect::normalisePath($this->string('url')->toString());

        return (string) strtok($path, '?') ?: '/';
    }

    public function visitorUserAgent(): ?string
    {
        return $this->filled('user_agent') ? $this->string('user_agent')->toString() : $this->userAgent();
    }

    public function visitorIp(): ?string
    {
        return $this->filled('ip_address') ? $this->string('ip_address')->toString() : $this->ip();
    }
}
