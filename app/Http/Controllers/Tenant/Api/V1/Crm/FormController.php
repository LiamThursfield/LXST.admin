<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\FormDefinitionResource;
use App\Models\Crm\Form;

/**
 * Lets a website load a CRM form, to render it
 */
class FormController extends Controller
{
    public function show(Form $form): FormDefinitionResource
    {
        return new FormDefinitionResource($form->load('fields'));
    }
}
