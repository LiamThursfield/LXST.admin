<?php

namespace App\Services\Crm;

use App\Http\Controllers\Concerns\SyncsBuilderFields;
use App\Http\Requests\Tenant\Admin\Crm\FormStoreRequest;
use App\Http\Requests\Tenant\Admin\Crm\FormUpdateRequest;
use App\Models\Crm\Form;
use Illuminate\Support\Facades\DB;

/**
 * Saves a validated CRM form with its fields. Used by the admin and the MCP server, so both save
 * forms the same way.
 */
class FormWriter
{
    use SyncsBuilderFields;

    public function create(FormStoreRequest $request): Form
    {
        return DB::transaction(function () use ($request) {
            $form = Form::create($request->formAttributes());

            $this->syncBuilderFields($form->fields(), $request->fieldsAttributes());

            return $form;
        });
    }

    public function update(Form $form, FormUpdateRequest $request): Form
    {
        return DB::transaction(function () use ($request, $form) {
            $form->update($request->formAttributes());

            $this->syncBuilderFields($form->fields(), $request->fieldsAttributes());

            return $form;
        });
    }
}
