<?php

namespace App\Http\Resources\Tenant\Admin\Crm;

use App\Http\Resources\Traits\HasActions;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Form $form */
        $form = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $form)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.crm.forms.show', $form))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $form)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.crm.forms.edit', $form))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $form)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.crm.forms.destroy', $form))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('crm.forms.confirm_delete', ['name' => $form->name]));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Form $form */
        $form = $this->resource;

        return $this->appendActions($request, [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'email_recipients' => $form->email_recipients ?? [],
            'marketing_email' => $form->marketing_email,
            'marketing_sms' => $form->marketing_sms,
            'marketing_telephone' => $form->marketing_telephone,
            'submit_button_text' => $form->submit_button_text,
            'redirect_url' => $form->redirect_url,
            'success_message' => $form->success_message,
            'fields_count' => $form->fields_count ?? $this->whenLoaded('fields', fn () => $form->fields->count()),
            'fields' => $this->whenLoaded('fields', fn () => $form->fields->map(fn (FormField $field) => [
                'id' => $field->id,
                'type' => $field->type->value,
                'type_label' => $field->type->label(),
                'name' => $field->name,
                'slug' => $field->slug,
                'is_required' => $field->is_required,
                'settings' => (object) array_filter($field->settings?->toArray() ?? [], fn (mixed $value) => $value !== null),
            ])->values()),
        ]);
    }
}
