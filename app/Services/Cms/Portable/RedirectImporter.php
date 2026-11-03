<?php

namespace App\Services\Cms\Portable;

use App\Http\Requests\Tenant\Admin\Cms\ImportedRedirectData;
use App\Models\Cms\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Imports redirects, by the URL they start from. Their publishing dates stay behind: a new one has
 * none, and an overwritten one keeps its own.
 *
 * @extends ItemImporter<Redirect>
 */
class RedirectImporter extends ItemImporter
{
    use ChecksImportedData;

    public function __construct(protected SiteExporter $exporter) {}

    public function kind(): string
    {
        return 'redirect';
    }

    /**
     * @param  array<mixed>  $item
     */
    public function key(array $item): ?string
    {
        $from = $item['url_from'] ?? null;

        return is_string($from) && $from !== '' && strlen($from) <= 255 ? $this->kind().'/'.Redirect::normalisePath($from) : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function identifier(array $item): string
    {
        return Redirect::normalisePath($item['url_from']);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function name(array $item): string
    {
        return is_string($item['url_to'] ?? null) ? "{$this->identifier($item)} → {$item['url_to']}" : $this->identifier($item);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function dependencies(array $item): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function typeLabel(array $item): string
    {
        return __('cms.import.kinds.redirect');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?Redirect
    {
        return Redirect::query()->where('url_from', $this->identifier($item))->first();
    }

    /**
     * @param  Redirect  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->redirectItem($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', Redirect::class) ?? false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Redirect|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        $data = ImportedRedirectData::for(Arr::only($item, ['url_from', 'url_to', 'is_enabled', 'is_permanent']), $existing);
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => []];
        }

        $attributes = $data->portableAttributes();

        if ($existing === null) {
            Redirect::create($attributes);
        } else {
            $existing->update($attributes);
        }

        return ['errors' => [], 'warnings' => $attributes['is_enabled'] ? [(string) __('cms.import.warnings.redirect_live')] : []];
    }
}
