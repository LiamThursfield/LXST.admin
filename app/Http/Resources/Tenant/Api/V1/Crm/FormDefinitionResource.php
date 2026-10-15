<?php

namespace App\Http\Resources\Tenant\Api\V1\Crm;

use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Services\Crm\FormSpamCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a website needs to render a CRM form and submit it back
 */
class FormDefinitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Form $form */
        $form = $this->resource;

        return [
            'slug' => $form->slug,
            'name' => $form->name,
            'submit_button_text' => $form->submit_button_text,
            'success_message' => $form->success_message,
            'redirect_url' => $form->redirect_url,
            'fields' => $form->fields->map(fn (FormField $field) => [
                'key' => $field->slug,
                'label' => $field->name,
                'type' => $field->type->value,
                'required' => $field->is_required,
                'settings' => (object) array_filter($field->settings?->toArray() ?? [], fn (mixed $value) => $value !== null),
            ])->values(),
            // The marketing consent checkboxes to show, submitted as booleans under these keys
            'marketing' => collect(Form::MARKETING_FIELDS)
                ->filter(fn (string $key) => $form->getAttribute($key))
                ->values(),
            // Render the honeypot as a hidden field, and send the token back unchanged
            'spam_protection' => [
                'honeypot_field' => config('api.form_submissions.honeypot_field'),
                'token_field' => config('api.form_submissions.token_field'),
                'token' => app(FormSpamCheck::class)->issueToken(),
            ],
        ];
    }
}
