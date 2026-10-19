<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password?" Uses Laravel's password broker: a reset token that
 * expires after 60 minutes (config/auth.php) and is deleted once used.
 */
class PasswordResetController extends Controller
{
    public const LINK_SENT = "If there's an account for that email, we've sent a link to reset its password.";

    /**
     * Always the same answer, so it never reveals whether an email has an
     * account. The email is queued, so the response takes as long either way.
     */
    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => $request->validated('email')]);

        return response()->json(['message' => self::LINK_SENT], 202);
    }

    /**
     * Sets the new password, signs the account out everywhere, then signs
     * this device back in.
     */
    public function reset(ResetPasswordRequest $request): UserResource
    {
        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Every existing session ends: whoever knew the old password
                // is signed out on every device.
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'This reset link has expired or has already been used. Ask for a new one.',
            ]);
        }

        $user = User::where('email', $request->validated('email'))->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user);
    }
}
