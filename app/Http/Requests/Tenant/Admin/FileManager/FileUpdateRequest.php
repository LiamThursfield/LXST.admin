<?php

namespace App\Http\Requests\Tenant\Admin\FileManager;

use App\Models\FileManager\File;
use Illuminate\Foundation\Http\FormRequest;

class FileUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->managedFile()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt_text' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function managedFile(): File
    {
        /** @var File $file */
        $file = $this->route('file');

        return $file;
    }
}
