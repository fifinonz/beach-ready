<?php

namespace App\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removes an account and everything that belongs to it. Fasts and reminders
 * go through the database's cascading foreign keys; sessions and reset
 * tokens have no foreign key, so they're removed here. Used by "Delete my
 * account" and by the clean-up of demo accounts.
 */
class AccountDeletion
{
    public static function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Signs them out everywhere, not only in one browser.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });
    }
}
