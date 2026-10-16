<?php

namespace App\Http\Requests\Tenant\Admin\FileManager;

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class FileStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', File::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('file-manager.allowed_extensions');

        return [
            'folder_id' => ['nullable', 'integer', Rule::exists('file_manager_folders', 'id')],
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('file-manager.max_files_per_upload')],
            // Checks both the extension and that the contents match it
            'files.*' => ['file', 'max:'.(int) config('file-manager.max_size_kb'), 'extensions:'.implode(',', $extensions), 'mimes:'.implode(',', $extensions)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.extensions' => __('file_manager.files.validation.type', ['types' => implode(', ', (array) config('file-manager.allowed_extensions'))]),
            'files.*.mimes' => __('file_manager.files.validation.type', ['types' => implode(', ', (array) config('file-manager.allowed_extensions'))]),
            'files.*.max' => __('file_manager.files.validation.size', ['size' => (int) round((int) config('file-manager.max_size_kb') / 1024)]),
        ];
    }

    /**
     * Name each file in error messages, e.g. "brochure.exe"
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(Arr::wrap($this->file('files')))
            ->mapWithKeys(fn (UploadedFile $file, int|string $index) => [
                "files.{$index}" => $file->getClientOriginalName(),
            ])
            ->all();
    }

    public function folder(): ?Folder
    {
        $id = $this->validated('folder_id');

        return is_numeric($id) ? Folder::query()->find((int) $id) : null;
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        return array_values(Arr::wrap($this->file('files')));
    }
}
