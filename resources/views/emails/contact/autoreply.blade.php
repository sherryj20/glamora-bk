@'
@component('mail::message')
# ¡Hola {{ $data['name'] }}!

Gracias por escribirnos. Hemos recibido tu mensaje y nuestro equipo te responderá lo antes posible.

@component('mail::panel')
**Tu mensaje enviado:**
> {{ $data['message'] }}
@endcomponent

Mientras tanto, si necesitas actualizar tu consulta o agregar más detalles, puedes responder directamente a este correo.

@component('mail::button', ['url' => config('app.url')])
Visitar {{ config('app.name', 'Glamora Studio') }}
@endcomponent

Un saludo,  
**{{ config('app.name', 'Glamora Studio') }}**  
**Tel:** (504) 9524-8210  
**Email:** info@glamorastudiohn.com

<small>Este es un mensaje automático de confirmación. Si no enviaste esta solicitud, puedes ignorar este correo.</small>
@endcomponent
'@ | Set-Content -Encoding UTF8 resources/views/emails/contact/autoreply.blade.php
