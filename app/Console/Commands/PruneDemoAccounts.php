<?php

namespace App\Console\Commands;

use App\Account\AccountDeletion;
use App\Demo\DemoAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:prune')]
#[Description('Delete demo accounts more than 24 hours old')]
class PruneDemoAccounts extends Command
{
    public function handle(): int
    {
        $count = 0;
        $cutoff = CarbonImmutable::now()->subHours(DemoAccount::LIFETIME_HOURS);

        User::query()
            ->where('is_demo', true)
            ->where('created_at', '<', $cutoff)
            ->eachById(function (User $user) use (&$count) {
                AccountDeletion::delete($user);
                $count++;
            });

        $this->info("Deleted {$count} demo accounts.");

        return self::SUCCESS;
    }
}
