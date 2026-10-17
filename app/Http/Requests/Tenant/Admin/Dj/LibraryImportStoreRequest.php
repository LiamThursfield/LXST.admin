<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\LibrarySource;
use App\Models\Dj\Library;
use App\Services\Dj\Import\RekordboxDatabase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Uploads a decrypted Rekordbox master.db into an existing library of that source, or a new one
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
        return [
            // Only libraries of the same source, as artist IDs differ between sources
            'library_id' => [
                'nullable',
                Rule::exists(Library::class, 'id')->where('source', LibrarySource::RekordboxDb->value),
            ],
            'library_name' => ['required_without:library_id', 'nullable', Rule::string()->max(255)],
            'file' => ['required', 'file', 'max:'.(int) config('dj.imports.max_size_kb'), 'extensions:db'],
        ];
    }

    /**
     * An encrypted master.db can't be read, so it's caught here rather than in the queued import
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

                if (! RekordboxDatabase::isUnencryptedSqlite((string) $file->getRealPath())) {
                    $validator->errors()->add('file', __('dj.imports.errors.not_decrypted'));
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
            'file.extensions' => __('dj.imports.errors.not_db'),
        ];
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
