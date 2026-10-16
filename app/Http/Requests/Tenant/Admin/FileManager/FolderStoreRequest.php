<?php

namespace App\Http\Requests\Tenant\Admin\FileManager;

use App\Models\FileManager\Folder;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FolderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Folder::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('file_manager_folders', 'id')],
            // Letters, numbers, spaces, dashes, underscores and dots, not starting with a dot (no slashes)
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9 _\-][A-Za-z0-9 _\-.]*$/', $this->uniqueInParentRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('file_manager.folders.validation.name_characters'),
        ];
    }

    /**
     * A folder can't share its name (ignoring case) with another folder in the same place
     */
    protected function uniqueInParentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $exists = Folder::query()
                ->where('parent_id', $this->parent()?->id)
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $value))])
                ->exists();

            if ($exists) {
                $fail(__('file_manager.folders.validation.name_taken'));
            }
        };
    }

    public function parent(): ?Folder
    {
        $id = $this->input('parent_id');

        return is_numeric($id) ? Folder::query()->find((int) $id) : null;
    }

    public function folderName(): string
    {
        return trim((string) $this->validated('name'));
    }
}
