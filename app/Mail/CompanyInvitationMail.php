<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CompanyInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You\'ve been invited to join :company', [
                'company' => $this->invitation->company->name,
            ]),
        );
    }

    public function content(): Content
    {
        $token = $this->invitation->token;
        $hasAccount = $this->invitation->user_id !== null;

        $acceptUrl = $hasAccount
            ? url("/login?invitation={$token}")
            : url("/register?invitation={$token}");

        return new Content(
            markdown: 'emails.company-invitation',
            with: [
                'invitation' => $this->invitation,
                'companyName' => $this->invitation->company->name,
                'roleName' => $this->invitation->role->title,
                'inviterName' => $this->invitation->invitedBy->name,
                'acceptUrl' => $acceptUrl,
                'declineUrl' => url("/invitations/{$token}/decline"),
                'hasAccount' => $hasAccount,
            ],
        );
    }
}
