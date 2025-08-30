@component('mail::message')
# Nueva cita creada

@php
    use Carbon\Carbon;

    // FECHA
    $dateText = null;
    if ($booking->service_date instanceof \Carbon\CarbonInterface) {
        $dateText = $booking->service_date->format('D, M j, Y');
    } elseif (!empty($booking->service_date)) {
        $dateText = Carbon::parse($booking->service_date)->format('D, M j, Y');
    }

    // HORA (acepta HH:mm, HH:mm:ss y HH:mm:ss.uuuuuu)
    $timeText = null;
    $timeRaw = $booking->service_time;

    if ($timeRaw instanceof \Carbon\CarbonInterface) {
        $timeText = $timeRaw->format('h:i A');
    } elseif (is_string($timeRaw) && $timeRaw !== '') {
        // Si trae microsegundos: "HH:MM:SS.uuuuuu" -> "HH:MM:SS"
        if (str_contains($timeRaw, '.')) {
            $timeRaw = explode('.', $timeRaw)[0];
        }
        $fmt = strlen($timeRaw) === 5 ? 'H:i' : 'H:i:s';
        try {
            $timeText = Carbon::createFromFormat($fmt, $timeRaw)->format('h:i A');
        } catch (\Exception $e) {
            // fallback: deja el valor tal cual
            $timeText = $timeRaw;
        }
    }
@endphp

**Cliente:** {{ $booking->user?->name ?? ('User #'.$booking->user_id) }}  
**Estilista:** {{ $booking->stylist?->user?->name ?? 'Sin preferencia' }}  
**Fecha:** {{ $dateText ?? '-' }}  
**Hora:** {{ $timeText ?? '-' }}  
**Total:** L {{ number_format((float)$booking->total_price, 2) }}

> Por favor ingresa a la web para **confirmar o rechazar** la cita.

@component('mail::button', ['url' => $actionUrl])
Revisar cita
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
