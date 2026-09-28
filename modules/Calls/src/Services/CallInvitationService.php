<?php

namespace Deally\Calls\Services;

use Deally\Calls\Mail\CallInvitationMail;
use Deally\Calls\Models\Call;
use Deally\Calls\Models\CallInvitation;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Composes and sends the call invitation.
 *
 * The exact body is stored on the invitation row, so what was actually
 * delivered to a customer stays answerable.
 */
class CallInvitationService
{
    /**
     * Build the invitation without sending it, so an agent can read the exact
     * copy before it goes to a customer.
     *
     * @return array{subject: string, body: string}
     */
    public function compose(Call $call, array $platform = []): array
    {
        $customer = $call->contact_name ?: $call->company;
        $host = auth()->user()?->name ?: 'your DeAlly account';
        $when = $call->date?->format('l j F \a\t g:ia') ?? 'the agreed time';

        $subject = "DeAlly call with {$host} — ".($call->name ?: $call->company);

        $lines = [
            "Hi {$customer},",
            '',
            "{$host} has booked a call with you about {$call->company} on {$when}.",
            '',
        ];

        if (filled($platform['join_url'] ?? null)) {
            $lines[] = "Join here: {$platform['join_url']}";
            $lines[] = '';
        } elseif (filled($platform['name'] ?? null)) {
            /* Saying nothing about a link is the honest option. Telling the
               customer to expect one that was never created is not. */
            $lines[] = "The meeting will be on {$platform['name']}. You will get the joining details shortly.";
            $lines[] = '';
        }

        $lines[] = 'Best,';
        $lines[] = $host;

        return [
            'subject' => $subject,
            'body' => implode("\n", $lines),
        ];
    }

    /**
     * Store the invitation and attempt delivery.
     *
     * Delivery failure is recorded on the row rather than thrown, because a
     * rep who is about to join a call needs to know the invitation did not go
     * out — not a 500 page.
     */
    public function send(Call $call, string $email, ?string $name = null, array $platform = []): CallInvitation
    {
        $copy = $this->compose($call, $platform);

        $invitation = CallInvitation::query()->create([
            'call_id' => $call->id,
            'channel' => 'email',
            'recipient_name' => $name ?: $call->contact_name,
            'recipient_email' => $email,
            'subject' => $copy['subject'],
            'body' => $copy['body'],
            'status' => CallInvitation::STATUS_PENDING,
        ]);

        try {
            Mail::to($email)->send(new CallInvitationMail($invitation));
        } catch (Throwable $exception) {
            $invitation->update([
                'status' => CallInvitation::STATUS_FAILED,
                'delivery_error' => $exception->getMessage(),
            ]);

            /* The call carries the outcome too, so a rep reading the call sees
               that the customer was never reached — an invitation row that only
               the invitation screen can show is not enough. */
            $call->forceFill([
                'invitation_status' => Call::INVITATION_FAILED,
            ])->save();

            return $invitation;
        }

        $invitation->update([
            'status' => CallInvitation::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $call->forceFill([
            'invitation_status' => Call::INVITATION_SENT,
            'invited_at' => now(),
        ])->save();

        return $invitation->refresh();
    }
}
