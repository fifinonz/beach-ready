<?php

namespace App\Http\Controllers\Auth;

use App\Consent\EmailConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, EmailConfirmation $confirmation): JsonResponse
    {
        $user = User::create($request->safe()->except('email_reminders'));

        if ($request->boolean('email_reminders')) {
            $user->forceFill(['email_fasting_reminders' => true])->save();
            $confirmation->sendFirst($user);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201);
    }
}
