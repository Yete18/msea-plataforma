<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodigoRecuperacionMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $codigo, public readonly int $minutos) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Código para restablecer tu contraseña MSEA');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.codigo-recuperacion');
    }
}
