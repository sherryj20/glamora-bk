<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    // GET /api/bookings/me  (sin paginación)
public function getMyBookings(Request $request)
{
    $rows = DB::table('bookings as b')
        ->leftJoin('stylists as s', 's.id', '=', 'b.stylist_id')
        ->leftJoin('booking_items as bi', 'bi.booking_id', '=', 'b.id')
        ->leftJoin('services as sv', 'sv.id', '=', 'bi.service_id')
        ->where('b.user_id', Auth::id())
        ->orderByDesc('b.service_date')->orderByDesc('b.service_time')
        ->select([
            'b.id','b.user_id','b.stylist_id','b.service_date','b.service_time',
            'b.notes','b.subtotal','b.tax','b.total_price','b.status',
            DB::raw('s.name as stylist_name'),
            'bi.id as item_id','bi.service_id','bi.quantity','bi.unit_price',
            DB::raw('sv.name as service_name'),'sv.duration_minutes as service_duration','sv.price as service_price',
        ])->get();

    $result = [];
    foreach ($rows as $r) {
        $bid = (string)$r->id;

        if (!isset($result[$bid])) {
            $statusInt = (int)$r->status;
            $status = match ($statusInt) {
                \App\Models\Booking::STATUS_PENDING   => 'pending',
                \App\Models\Booking::STATUS_CONFIRMED => 'confirmed',
                \App\Models\Booking::STATUS_COMPLETED => 'completed',
                \App\Models\Booking::STATUS_CANCELED  => 'cancelled',
                default => 'pending',
            };

            // Si service_time es TIME en DB, evita parseos raros
            $dateOut = $r->service_date ? \Carbon\Carbon::parse($r->service_date)->format('D, M j, Y') : null;
            $timeOut = $r->service_time
                ? \Carbon\Carbon::createFromFormat('H:i:s', (string)$r->service_time)->format('h:i A')
                : null;

            $result[$bid] = [
                'id'          => $bid,
                'userId'      => (string)$r->user_id,
                'stylistId'   => (string)$r->stylist_id,
                'stylistName' => $r->stylist_name ?? null,
                'date'        => $dateOut,
                'time'        => $timeOut,
                'status'      => $status,      // <-- texto 4-estados
                'statusInt'   => $statusInt,   // <-- por si el front lo quiere
                'notes'       => $r->notes,
                'price'       => (float)$r->total_price,
                'raw' => [
                    'service_date' => $r->service_date ? (string)$r->service_date : null,
                    'service_time' => $r->service_time ? (string)$r->service_time : null,
                    'subtotal'     => (float)$r->subtotal,
                    'tax'          => (float)$r->tax,
                    'total_price'  => (float)$r->total_price,
                ],
                'items'       => [],
            ];
        }

        if ($r->item_id) {
            $result[$bid]['items'][] = [
                'id'        => (string)$r->item_id,
                'serviceId' => $r->service_id ? (string)$r->service_id : null,
                'name'      => $r->service_name ?? null,
                'quantity'  => (int)$r->quantity,
                'unitPrice' => (float)$r->unit_price,
                'lineTotal' => (float)$r->unit_price * (int)$r->quantity,
                'duration'  => $r->service_duration ? (int)$r->service_duration : null,
                'listPrice' => $r->service_price ? (float)$r->service_price : null,
            ];
        }
    }

    foreach ($result as &$b) {
        $n = count($b['items']);
        $b['serviceName'] = $n === 1 ? ($b['items'][0]['name'] ?? null) : ($n > 1 ? $n.' services' : null);
    }

    return response()->json(['data' => array_values($result)]);
}


    // POST /api/bookings/{booking}/cancel
    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ((int)$booking->status === Booking::STATUS_CANCELED) {
            return response()->json(['message' => 'Already cancelled'], 422);
        }
        $booking->status = Booking::STATUS_CANCELED;
        $booking->save();

        return response()->json(['message' => 'Booking cancelled']);
    }
}
