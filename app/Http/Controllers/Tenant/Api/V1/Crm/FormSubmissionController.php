<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Crm\FormSubmissionStoreRequest;
use App\Models\Crm\Form;
use App\Services\Crm\FormSpamCheck;
use App\Services\Crm\FormSubmissionRecorder;
use Illuminate\Http\JsonResponse;

/**
 * Lets a website submit a CRM form
 */
class FormSubmissionController extends Controller
{
    /**
     * Spam is saved but the response is the same as for a genuine submission, so bots learn nothing
     */
    public function store(
        FormSubmissionStoreRequest $request,
        Form $form,
        FormSubmissionRecorder $recorder,
        FormSpamCheck $spamCheck,
    ): JsonResponse {
        /** @var array<string, mixed> $values */
        $values = $request->validated();

        $recorder->record($form, $values, $spamCheck->looksAutomated($request));

        return response()->json([
            'data' => [
                'message' => $form->success_message ?: __('crm.submissions.api.submitted'),
                'redirect_url' => $form->redirect_url,
            ],
        ], 201);
    }
}
