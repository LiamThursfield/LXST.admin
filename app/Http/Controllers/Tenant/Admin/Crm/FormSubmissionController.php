<?php

namespace App\Http\Controllers\Tenant\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Crm\FormSubmissionIndexQuery;
use App\Http\Requests\Tenant\Admin\Crm\FormSubmissionIndexRequest;
use App\Http\Resources\Tenant\Admin\Crm\FormSubmissionResource;
use App\Models\Crm\Form;
use App\Models\Crm\FormSubmission;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Submissions are created by the public API (#39); the admin can only read them and mark them as spam
 */
class FormSubmissionController extends Controller
{
    public function index(FormSubmissionIndexRequest $request, FormSubmissionIndexQuery $query): Response
    {
        return Inertia::render('admin/crm/form-submission/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter(fn (mixed $value) => $value !== null && $value !== '')->toArray();
            },
            'columns' => [
                ['accessorKey' => 'submitted_at', 'header' => __('crm.submissions.columns.submitted_at')],
                ['accessorKey' => 'form', 'header' => __('crm.submissions.columns.form')],
                ['accessorKey' => 'contact', 'header' => __('crm.submissions.columns.contact')],
                ['accessorKey' => 'is_spam', 'header' => __('crm.submissions.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'submissions' => FormSubmissionResource::collection($query->handle($request)),
            'forms' => Form::withTrashed()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Form $form) => ['value' => (string) $form->id, 'label' => $form->name])
                ->values(),
        ]);
    }

    public function show(FormSubmission $formSubmission): Response
    {
        Gate::authorize('view', $formSubmission);

        return Inertia::render('admin/crm/form-submission/Show', [
            'submission' => new FormSubmissionResource($formSubmission->load('form', 'contact')),
        ]);
    }
}
