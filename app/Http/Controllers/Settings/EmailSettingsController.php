<?php

namespace App\Http\Controllers\Settings;

use App\Consent\EmailConfirmation;
use App\Consent\EmailTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateEmailSettingsRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The email topics and the state of the address's confirmation, for the
 * Reminders and Settings screens.
 */
class EmailSettingsController extends Controller
{
    public function __construct(private EmailConfirmation $confirmation) {}

    public function show(Request $request): JsonResponse
    {
        return $this->settingsOf($request->user());
    }

    /**
     * Turning a topic on for an unconfirmed address sends the confirmation
     * email, the first time only.
     */
    public function update(UpdateEmailSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        foreach ($request->validated() as $topic => $on) {
            $user->{EmailTopic::from($topic)->column()} = $on;
        }

        $user->save();

        if (EmailTopic::anyOnFor($user)) {
            $this->confirmation->sendFirst($user);
        }

        return $this->settingsOf($user->fresh());
    }

    /**
     * "Resend": another confirmation email, at most one every 60 seconds.
     */
    public function resendConfirmation(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->hasConfirmedEmail(), 409, 'Your email address is already confirmed.');
        abort_if($user->is_demo, 409, "Demo accounts don't receive email.");

        $wait = $this->confirmation->resend($user);

        if ($wait !== null) {
            return response()->json([
                'message' => "You can ask for another email in {$wait} seconds.",
                'retry_after_seconds' => $wait,
            ], 429, ['Retry-After' => $wait]);
        }

        return response()->json(['message' => 'Confirmation email sent.'], 202);
    }

    private function settingsOf(User $user): JsonResponse
    {
        $topics = collect(EmailTopic::cases())
            ->mapWithKeys(fn (EmailTopic $topic) => [$topic->value => $user->{$topic->column()}])
            ->all();

        return response()->json(['data' => [
            ...$topics,
            'email_confirmed' => $user->hasConfirmedEmail(),
            'resend_available_in_seconds' => $user->hasConfirmedEmail()
                ? null
                : $this->confirmation->secondsUntilResend($user, CarbonImmutable::now()),
        ]]);
    }
}
