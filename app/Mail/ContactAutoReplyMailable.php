<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactAutoReplyMailable extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data){ $this->data = $data; }

    public function build()
    {
        return $this->subject('Hemos recibido tu mensaje')
            ->markdown('emails.contact.autoreply', ['data' => $this->data]);
    }
}