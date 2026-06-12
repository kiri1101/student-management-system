<?php

namespace App\Mail;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name').' — set your password',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.user-invitation',
            with: [
                'user' => $this->user,
                'roleLabel' => $this->roleLabel(),
                'setupUrl' => route('password.reset', [
                    'token' => $this->token,
                    'email' => $this->user->email,
                ]),
                'expireHours' => (int) ceil(config('auth.passwords.users.expire') / 60),
            ],
        );
    }

    private function roleLabel(): ?string
    {
        $value = $this->user->roles()->value('name');

        if ($value === null) {
            return null;
        }

        $role = $value instanceof RoleName ? $value : RoleName::tryFrom((string) $value);

        return $role?->label() ?? ucfirst((string) $value);
    }
}
