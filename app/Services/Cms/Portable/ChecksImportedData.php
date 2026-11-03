<?php

namespace App\Services\Cms\Portable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

trait ChecksImportedData
{
    /**
     * Runs an imported item's rules, giving back any problems rather than stopping the import
     *
     * @return list<string>
     */
    protected function validationErrors(FormRequest $data): array
    {
        try {
            $data->validateResolved();
        } catch (ValidationException $exception) {
            return array_values(array_unique(array_merge(...array_values($exception->errors()))));
        }

        return [];
    }
}
