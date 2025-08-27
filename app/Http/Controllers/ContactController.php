<?php

// app/Http/Controllers/ContactController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessageMailable;
use App\Mail\ContactAutoReplyMailable;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required','string','max:120'],
            'email'   => ['required','email','max:150'],
            'phone'   => ['nullable','string','max:50'],
            'message' => ['required','string','max:5000'],
            // honeypot anti-spam (debe venir vacío)
            'company' => ['nullable','string','max:50']
        ]);

        // Honeypot: si viene con algo, ignoramos (spam)
        if (!empty($data['company'])) {
            return response()->json(['ok' => true]); // no revelar al bot
        }

        // A quién enviar (tu correo). Puedes usar .env para configurarlo:
        $to = config('mail.contact_to', env('MAIL_CONTACT_TO', 'info@glamorastudiohn.com'));

        // 1) Te envías el correo con los datos del formulario
        Mail::to($to)->send(new ContactMessageMailable($data));

        // 2) Auto-respuesta opcional al usuario
        Mail::to($data['email'])->send(new ContactAutoReplyMailable($data));

        return response()->json(['ok' => true, 'message' => 'Message sent']);
    }
}
