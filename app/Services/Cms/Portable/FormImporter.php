<?php

namespace App\Services\Cms\Portable;

use App\Enums\TenantModule;
use App\Http\Controllers\Concerns\SyncsBuilderFields;
use App\Http\Requests\Tenant\Admin\Cms\ImportedFormData;
use App\Models\Crm\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Imports CRM forms, by their slug. A form's email recipients stay as they are here: only what's
 * expected is read from the paste, so a new form has none and an overwritten one keeps its own.
 *
 * @extends ItemImporter<Form>
 */
class FormImporter extends ItemImporter
{
    use ChecksImportedData;
    use SyncsBuilderFields;

    public function __construct(protected FormExporter $exporter) {}

    public function kind(): string
    {
        return 'form';
    }

    /**
     * @param  array<mixed>  $item
     */
    public function key(array $item): ?string
    {
        return is_string($item['slug'] ?? null) ? "{$this->kind()}/{$item['slug']}" : null;
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
        return __('cms.import.kinds.form');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function existing(array $item): ?Form
    {
        return Form::query()->where('slug', $item['slug'])->first();
    }

    /**
     * @param  Form  $existing
     * @return array<string, mixed>
     */
    protected function exported(Model $existing): array
    {
        return $this->exporter->item($existing);
    }

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('create', Form::class) ?? false;
    }

    protected function unavailableReason(): ?string
    {
        return (tenant()?->hasModule(TenantModule::Crm) ?? false) ? null : (string) __('cms.import.validation.crm_off');
    }

    /**
     * @param  Form  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function changes(Model $existing, array $item): array
    {
        $incoming = collect(is_array($item['fields'] ?? null) ? $item['fields'] : [])
            ->filter(fn (mixed $field) => is_array($field) && is_string($field['slug'] ?? null))
            ->pluck('slug');
        $current = $existing->fields()->pluck('slug');

        return array_values(array_filter([
            $this->countChange('added', $incoming->diff($current)->count()),
            $this->countChange('removed', $current->diff($incoming)->count()),
        ]));
    }

    protected function countChange(string $change, int $count): ?string
    {
        return $count === 0 ? null : trans_choice("cms.import.changes.{$change}", $count);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Form|null  $existing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function save(array $item, ?Model $existing): array
    {
        $data = ImportedFormData::for($this->builderData($item, $existing), $existing);
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            return ['errors' => $errors, 'warnings' => []];
        }

        $attributes = Arr::except($data->formAttributes(), 'email_recipients');

        $form = $existing ?? Form::create($attributes);
        $existing?->update($attributes);

        $this->syncBuilderFields($form->fields(), $data->fieldsAttributes());

        return ['errors' => [], 'warnings' => []];
    }

    /**
     * The item as the form builder would send it. Only what's expected is copied, so ids and email
     * recipients in the paste go no further.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function builderData(array $item, ?Form $existing): array
    {
        /** @var array<string, int> $fieldIds */
        $fieldIds = $existing?->fields()->pluck('id', 'slug')->all() ?? [];
        $fields = $item['fields'] ?? [];

        return [
            ...Arr::only($item, ['slug', 'name', 'submit_button_text', 'redirect_url', 'success_message']),
            ...array_map(fn (string $key) => $item[$key] ?? false, array_combine(Form::MARKETING_FIELDS, Form::MARKETING_FIELDS)),
            'fields' => is_array($fields)
                ? array_map(fn (mixed $field) => is_array($field) ? [
                    'id' => is_string($field['slug'] ?? null) ? ($fieldIds[$field['slug']] ?? null) : null,
                    ...Arr::only($field, ['slug', 'type', 'name', 'is_required', 'settings']),
                ] : $field, array_values($fields))
                : $fields,
        ];
    }
}
