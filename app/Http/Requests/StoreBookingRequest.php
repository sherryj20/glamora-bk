<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Si usas auth:sanctum, el usuario ya viene autenticado.
        // Puedes restringir más si hace falta.
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['required','integer','exists:users,id'],
            'stylist_id'   => ['nullable','integer','exists:stylists,id'],
            'service_date' => ['required','date_format:Y-m-d'],
            'service_time' => ['required','date_format:H:i:s'], // "14:30:00"
            'notes'        => ['nullable','string','max:2000'],

            // Totales (puedes recalcularlos en el controller igualmente)
            'subtotal'     => ['required','numeric','min:0'],
            'tax'          => ['required','numeric','min:0'],
            'total_price'  => ['required','numeric','min:0'],

            // 0=pending, 1=confirmed, 2=completed, 3=cancelled
            'status'       => ['required','integer', Rule::in([0,1,2,3])],

            // Ítems (opcional, si los envías embebidos)
            'items'                      => ['nullable','array','min:1'],
            'items.*.service_id'         => ['required_with:items','integer','exists:services,id'],
            'items.*.quantity'           => ['required_with:items','integer','min:1'],
            'items.*.unit_price'         => ['required_with:items','numeric','min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_date.date_format' => 'El formato de fecha debe ser Y-m-d (por ejemplo 2025-09-01).',
            'service_time.date_format' => 'La hora debe ser H:i:s (por ejemplo 14:30:00).',
        ];
    }
}
