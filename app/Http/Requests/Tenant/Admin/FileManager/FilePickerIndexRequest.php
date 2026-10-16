<?php

namespace App\Http\Requests\Tenant\Admin\FileManager;

use App\Models\FileManager\Folder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilePickerIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Folder::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The folder being viewed; the top level when missing
            'folder' => ['nullable', 'integer', Rule::exists('file_manager_folders', 'id')],
            // Only list images, e.g. when choosing a logo
            'images_only' => ['nullable', 'boolean'],
        ];
    }

    public function folder(): ?Folder
    {
        $id = $this->validated('folder');

        return is_numeric($id) ? Folder::query()->find((int) $id) : null;
    }
}
