<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\LibrarySource;
use App\Models\Dj\Library;
use App\Services\Dj\Import\RekordboxDatabase;
use App\Services\Dj\Import\RekordboxXml;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Uploads a Rekordbox file (a decrypted master.db or an XML export) into an existing library of
 * that source, or a new one
 */
class LibraryImportStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $library = $this->library();

        return $library === null
            ? ($this->user()?->can('create', Library::class) ?? false)
            : ($this->user()?->can('import', $library) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $source = $this->source();

        return [
            'source' => ['required', Rule::enum(LibrarySource::class)->only(LibrarySource::uploadable())],
            // Only libraries of the same source, as artist IDs differ between sources
            'library_id' => [
                'nullable',
                Rule::exists(Library::class, 'id')->where('source', $source?->value),
            ],
            'library_name' => ['required_without:library_id', 'nullable', Rule::string()->max(255)],
            'file' => [
                'required',
                'file',
                'max:'.(int) config('dj.imports.max_size_kb'),
                'extensions:'.($source?->fileExtension() ?? 'db,xml'),
            ],
        ];
    }

    /**
     * An encrypted master.db, or an XML file that isn't a Rekordbox export, can't be read, so
     * they're caught here rather than in the queued import
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($validator->errors()->has('file') || ! $file instanceof UploadedFile) {
                    return;
                }

                $path = (string) $file->getRealPath();

                $error = match ($this->source()) {
                    LibrarySource::RekordboxDb => RekordboxDatabase::isUnencryptedSqlite($path) ? null : 'dj.imports.errors.not_decrypted',
                    LibrarySource::RekordboxXml => RekordboxXml::isRekordboxXml($path) ? null : 'dj.imports.errors.not_rekordbox_xml',
                    default => null,
                };

                if ($error !== null) {
                    $validator->errors()->add('file', __($error));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source' => __('dj.imports.fields.source'),
            'library_id' => __('dj.imports.fields.library'),
            'library_name' => __('dj.imports.fields.library_name'),
            'file' => __('dj.imports.fields.file'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => __('dj.imports.errors.too_large', ['size' => (int) round((int) config('dj.imports.max_size_kb') / 1024)]),
            'file.extensions' => $this->source() === LibrarySource::RekordboxXml
                ? __('dj.imports.errors.not_xml')
                : __('dj.imports.errors.not_db'),
        ];
    }

    /**
     * The kind of file being uploaded, if it's one that can be
     */
    public function source(): ?LibrarySource
    {
        $source = LibrarySource::tryFrom((string) $this->input('source'));

        return in_array($source, LibrarySource::uploadable(), true) ? $source : null;
    }

    /**
     * The existing library to import into, if one was chosen
     */
    public function library(): ?Library
    {
        $id = $this->input('library_id');

        return filled($id) ? Library::query()->whereKey($id)->first() : null;
    }
}
