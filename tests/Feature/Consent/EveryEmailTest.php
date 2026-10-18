<?php

use App\Consent\EmailLinks;
use App\Mail\BeachReadyMail;
use App\Mail\ConfirmEmailMail;
use App\Mail\ReminderMail;
use App\Models\Fast;
use App\Models\Reminder;
use App\Models\User;

/*
| CLAUDE.md: every email includes a working unsubscribe link and a link to
| the preference center. Each kind of email Beach Ready sends is listed
| here, so a new one can't skip the footer or the headers.
*/

dataset('every email', [
    'confirmation' => [fn () => new ConfirmEmailMail(User::factory()->create())],
    'reminder' => [fn () => new ReminderMail(Reminder::factory()->for(Fast::factory())->create())],
]);

function recipientOf(BeachReadyMail $mail): User
{
    return $mail instanceof ReminderMail ? $mail->reminder->fast->user : $mail->user;
}

it('links to the preference center and to unsubscribe', function (BeachReadyMail $mail) {
    $user = recipientOf($mail);

    $mail->assertSeeInHtml('Manage email preferences');
    $mail->assertSeeInHtml(e(EmailLinks::preferences($user)), false);
    $mail->assertSeeInHtml(e(EmailLinks::unsubscribe($user)), false);
})->with('every email');

it('has one-click unsubscribe headers', function (BeachReadyMail $mail) {
    $headers = $mail->headers()->text;

    expect($headers['List-Unsubscribe'])->toBe('<'.EmailLinks::unsubscribe(recipientOf($mail)).'>')
        ->and($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
})->with('every email');

it('shows the postal address when one is set', function (BeachReadyMail $mail) {
    config(['mail.postal_address' => 'PO Box 123, Nairobi']);

    $mail->assertSeeInHtml('Beach Ready · PO Box 123, Nairobi');
})->with('every email');

it('uses the Beach Ready colours', function (BeachReadyMail $mail) {
    // The lagoon button colour from CLAUDE.md, inlined from the mail theme.
    $mail->assertSeeInHtml('#12808A', false);
})->with('every email');
