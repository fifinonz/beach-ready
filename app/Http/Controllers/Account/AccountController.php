<?php

namespace App\Http\Controllers\Account;

use App\Account\AccountDeletion;
use App\Account\DataExport;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /**
     * "Download my data", as a JSON file.
     */
    public function export(Request $request): JsonResponse
    {
        $filename = 'beach-ready-data-'.CarbonImmutable::now()->toDateString().'.json';

        return response()
            ->json(DataExport::for($request->user()), options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * "Delete my account": removes the user and everything that belongs to
     * them. Fasts and reminders go with the user through the database's
     * cascading foreign keys. A reminder job already queued finds its
     * reminder gone and does nothing.
     */
    public function destroy(Request $request): Response
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ]);

        $user = $request->user();

        // Log out first: logging out saves the user's remember token, which
        // would put a deleted user back.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AccountDeletion::delete($user);

        return response()->noContent();
    }
}
