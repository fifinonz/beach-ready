<?php

namespace App\Http\Controllers\Demo;

use App\Demo\DemoAccount;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /**
     * "Try the demo": makes a fresh demo account and signs this browser in.
     */
    public function store(Request $request): JsonResponse
    {
        $timezone = $request->validate([
            'timezone' => ['required', 'string', 'timezone:all'],
        ])['timezone'];

        $user = DemoAccount::create($timezone, CarbonImmutable::now());

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201);
    }
}
