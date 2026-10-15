<?php

namespace App\Http\Resources\Tenant\Admin\Crm;

use App\Data\Crm\FormSubmissionAnswer;
use App\Http\Resources\Traits\HasActions;
use App\Models\Crm\FormSubmission;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormSubmissionResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var FormSubmission $submission */
        $submission = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $submission)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.crm.form-submissions.show', $submission))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $submission)) {
            $actions[] = $submission->is_spam
                ? Action::make(__('crm.submissions.actions.not_spam'), route('admin.crm.form-submissions.spam.destroy', $submission))
                    ->icon('i-lucide-shield-check')
                    ->method('DELETE')
                : Action::make(__('crm.submissions.actions.mark_spam'), route('admin.crm.form-submissions.spam.store', $submission))
                    ->icon('i-lucide-shield-alert')
                    ->method('POST');
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
        /** @var FormSubmission $submission */
        $submission = $this->resource;
        $form = $submission->form;
        $contact = $submission->contact;

        return $this->appendActions($request, [
            'id' => $submission->id,
            'is_spam' => $submission->is_spam,
            'submitted_at' => $submission->submitted_at->toIso8601String(),
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
                'deleted' => $form->trashed(),
            ],
            'contact' => $contact === null ? null : [
                'id' => $contact->id,
                'name' => $contact->name_with_title,
                'email' => $contact->email,
                'deleted' => $contact->trashed(),
            ],
            'answers' => collect($submission->data->answers)->map(fn (FormSubmissionAnswer $answer) => [
                'slug' => $answer->slug,
                'label' => $answer->label,
                'type' => $answer->type->value,
                'type_label' => $answer->type->label(),
                'value' => $answer->value,
            ])->values(),
            'marketing' => (object) $submission->data->marketing,
        ]);
    }
}
