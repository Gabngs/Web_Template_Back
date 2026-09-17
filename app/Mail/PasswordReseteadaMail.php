<?php

namespace App\Mail;

use App\Models\dbsiaw\SiawUsuarios;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReseteadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SiawUsuarios $usuario,
        public readonly string $passwordTemporal,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu contraseña fue restablecida',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reseteada',
        );
    }
}
