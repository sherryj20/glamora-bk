<?php
// app/Mail/ContactMessageMailable.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMailable extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data){ $this->data = $data; }

    public function build()
    {
        return $this->subject('Nuevo mensaje de contacto')
            ->markdown('emails.contact.message', ['data' => $this->data]);
    }
}
