<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => (string) $this->user_id,
            'stylist_id'   => $this->stylist_id ? (string) $this->stylist_id : null,
            'service_date' => optional($this->service_date)->format('Y-m-d'),
            'service_time' => (string) $this->service_time,
            'notes'        => $this->notes,
            'subtotal'     => (string) $this->subtotal,
            'tax'          => (string) $this->tax,
            'total_price'  => (string) $this->total_price,
            'status'       => (int) $this->status,
            'created_at'   => optional($this->created_at)->toISOString(),

            'user'    => $this->whenLoaded('user', fn () => [
                'id' => (string) $this->user->id,
                'name' => $this->user->name,
            ]),
            'stylist' => $this->whenLoaded('stylist', fn () => [
                'id' => (string) $this->stylist->id,
                'name' => $this->stylist->name,
            ]),
            'items'   => $this->whenLoaded('items', fn () => $this->items->map(function ($it) {
                return [
                    'id'         => $it->id,
                    'service_id' => (string) $it->service_id,
                    'quantity'   => (int) $it->quantity,
                    'unit_price' => (string) $it->unit_price,
                    'total'      => (string) $it->total,
                ];
            })),
        ];
    }
}
