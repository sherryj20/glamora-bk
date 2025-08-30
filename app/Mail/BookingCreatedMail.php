<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingCreatedMail extends Mailable /* implements ShouldQueue */ // <- si quieres en cola, descomenta la interfaz
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $actionUrl // link a la web para revisar/confirmar/rechazar
    ) {}

    public function build()
    {
        return $this->subject('Nueva cita creada #' . $this->booking->id)
            ->markdown('emails.bookings.created');
    }
}
