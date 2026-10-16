<?php

namespace App\Http\Requests\Tenant\Api\V1\Cms;

use App\Models\Cms\Redirect;
use App\Services\Cms\Api\ContentPresenter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageShowRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The path on the site, e.g. /about/team (a full URL is cut down to its path)
            'url' => ['required', 'string', 'max:2048'],
            // How rich text comes back; both formats when left out
            'format' => ['nullable', Rule::in([ContentPresenter::FORMAT_HTML, ContentPresenter::FORMAT_JSON])],
        ];
    }

    /**
     * The requested path, tidied like stored URLs (leading slash, no trailing slash, no query)
     */
    public function sitePath(): string
    {
        $path = Redirect::normalisePath($this->string('url')->toString());

        return (string) strtok($path, '?') ?: '/';
    }

    public function richTextFormat(): string
    {
        return $this->string('format')->toString() ?: ContentPresenter::FORMAT_BOTH;
    }
}
