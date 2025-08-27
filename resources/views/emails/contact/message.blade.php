@'
@component('mail::message')
# Nuevo mensaje de contacto

@component('mail::panel')
**Nombre:** {{ $data['name'] }}  
**Email:** {{ $data['email'] }}  
**Teléfono:** {{ $data['phone'] ?? 'N/D' }}
@endcomponent

**Mensaje:**

> {{ $data['message'] }}

@isset($data['email'])
@component('mail::button', ['url' => 'mailto:'.$data['email']])
Responder a {{ $data['name'] }}
@endcomponent
@endisset

Gracias,  
**{{ config('app.name', 'Glamora Studio') }}**

<small>Este correo fue generado automáticamente desde el formulario de contacto del sitio {{ config('app.url') }}.</small>
@endcomponent
'@ | Set-Content -Encoding UTF8 resources/views/emails/contact/message.blade.php
