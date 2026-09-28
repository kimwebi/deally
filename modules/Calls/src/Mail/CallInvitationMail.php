<?php

namespace Deally\Calls\Mail;

use Deally\Calls\Models\CallInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invitation the customer receives.
 *
 * Built from the stored invitation row rather than re-rendered at send time, so
 * what is delivered is exactly what is kept on the record. If the copy is ever
 * changed after the fact, the promise the customer actually received is not.
 */
class CallInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CallInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->invitation->subject,
            replyTo: config('mail.from.address') ? [config('mail.from.address')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'calls::mail.call-invitation',
            with: [
                'invitation' => $this->invitation,
                'body' => $this->invitation->body,
            ],
        );
    }
}
