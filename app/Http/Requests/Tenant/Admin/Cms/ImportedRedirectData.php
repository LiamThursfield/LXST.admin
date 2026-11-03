<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Models\Cms\Redirect;

/**
 * One imported redirect, put through the same rules as its admin form
 */
class ImportedRedirectData extends RedirectStoreRequest
{
    /** @use IsImportedData<Redirect> */
    use IsImportedData;

    public function authorize(): bool
    {
        return true;
    }

    protected function currentRedirect(): ?Redirect
    {
        return $this->existing;
    }

    /**
     * What an import can change, which leaves the publishing dates as they are
     *
     * @return array{url_from: mixed, url_to: mixed, is_enabled: bool, is_permanent: bool}
     */
    public function portableAttributes(): array
    {
        return [
            'url_from' => $this->validated('url_from'),
            'url_to' => $this->validated('url_to'),
            'is_enabled' => $this->boolean('is_enabled'),
            'is_permanent' => $this->boolean('is_permanent'),
        ];
    }
}
