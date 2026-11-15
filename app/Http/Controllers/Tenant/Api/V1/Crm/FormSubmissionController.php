<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Enums\Crm\SpamReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Crm\FormSubmissionStoreRequest;
use App\Models\Crm\Form;
use App\Services\Captcha\Captcha;
use App\Services\Captcha\CaptchaResult;
use App\Services\Crm\FormSpamCheck;
use App\Services\Crm\FormSubmissionRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Lets a website submit a CRM form
 */
class FormSubmissionController extends Controller
{
    /**
     * Spam is saved but the response is the same as for a genuine submission, so bots learn nothing.
     *
     * When the form asks for a captcha (#74), it's checked once the answers are valid, so a visitor
     * fixing a mistake can send the same token again. A missing or failed captcha is refused, so a
     * real visitor whose token ran out can try again; if the provider can't be reached, the
     * submission is kept but marked as spam, so it isn't lost.
     *
     * @throws ValidationException when the form asks for a captcha and it fails
     */
    public function store(
        FormSubmissionStoreRequest $request,
        Form $form,
        FormSubmissionRecorder $recorder,
        FormSpamCheck $spamCheck,
        Captcha $captcha,
    ): JsonResponse {
        /** @var array<string, mixed> $values */
        $values = $request->validated();

        $captchaResult = $form->captcha_enabled && $captcha->isConfigured()
            ? $captcha->check($request)
            : CaptchaResult::Passed;

        if ($captchaResult === CaptchaResult::Failed) {
            throw ValidationException::withMessages([
                Captcha::tokenField() => __('crm.submissions.api.captcha_failed'),
            ]);
        }

        $spamReason = $spamCheck->reason($request)
            ?? ($captchaResult === CaptchaResult::Unavailable ? SpamReason::CaptchaUnavailable : null);

        $recorder->record($form, $values, $spamReason);

        return response()->json([
            'data' => [
                'message' => $form->success_message ?: __('crm.submissions.api.submitted'),
                'redirect_url' => $form->redirect_url,
            ],
        ], 201);
    }
}
