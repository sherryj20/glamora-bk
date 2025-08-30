<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Mail\BookingCreatedMail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    // GET /api/bookings/me
// GET /api/bookings/me
public function getMyBookings(Request $request)
{
    $rows = DB::table('bookings as b')
        ->leftJoin('stylists as s', 's.id', '=', 'b.stylist_id')
        ->leftJoin('users as su', 'su.id', '=', 's.user_id') // ← user dueño de la estilista
        ->leftJoin('booking_items as bi', 'bi.booking_id', '=', 'b.id')
        ->leftJoin('services as sv', 'sv.id', '=', 'bi.service_id')
        ->where('b.user_id', Auth::id())
        ->orderByDesc('b.service_date')
        ->orderByDesc('b.service_time')
        ->select([
            'b.id','b.user_id','b.stylist_id','b.service_date','b.service_time',
            'b.notes','b.subtotal','b.tax','b.total_price','b.status',
            DB::raw('su.name as stylist_name'),          // ← nombre desde users
            'bi.id as item_id','bi.service_id','bi.quantity','bi.unit_price',
            DB::raw('sv.name as service_name'),
            'sv.duration_minutes as service_duration',
            'sv.price as service_price',
        ])
        ->get();

    // ---- Mapear a la forma que espera el front
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

            $dateOut = $r->service_date ? \Carbon\Carbon::parse($r->service_date)->format('D, M j, Y') : null;
            $timeOut = $r->service_time
                ? \Carbon\Carbon::createFromFormat('H:i:s', (string)$r->service_time)->format('h:i A')
                : null;

            $result[$bid] = [
                'id'          => $bid,
                'userId'      => (string)$r->user_id,
                'stylistId'   => $r->stylist_id !== null ? (string)$r->stylist_id : null,
                'stylistName' => $r->stylist_name ?? null, // ← ya viene de users
                'date'        => $dateOut,
                'time'        => $timeOut,
                'status'      => $status,
                'notes'       => $r->notes,
                'price'       => (float)$r->total_price,
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

    // Atajo de nombre de servicio
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

    // POST /api/bookings
    public function store(StoreBookingRequest $request)
    {
        $payload = $request->validated();
        // $payload['user_id'] = $request->user()->id; // fuerza propietario si quieres

        $recomputeFromDb = true;

        return DB::transaction(function () use ($payload, $recomputeFromDb) {

            $subtotal = (float) ($payload['subtotal'] ?? 0);
            $tax      = (float) ($payload['tax'] ?? 0);
            $total    = (float) ($payload['total_price'] ?? 0);

            $itemsInput = $payload['items'] ?? null;

            if ($recomputeFromDb) {
                if (is_array($itemsInput) && count($itemsInput) > 0) {
                    $subtotal = 0.0;

                    $serviceIds = collect($itemsInput)->pluck('service_id')->unique()->values()->all();
                    $services   = Service::query()
                        ->whereIn('id', $serviceIds)
                        ->get(['id','price'])
                        ->keyBy('id');

                    foreach ($itemsInput as $it) {
                        $svc = $services[$it['service_id']] ?? null;
                        if (!$svc) {
                            throw ValidationException::withMessages([
                                'items' => ['Uno de los servicios no existe.']
                            ]);
                        }
                        $qty   = (int) $it['quantity'];
                        $price = (float) $svc->price;
                        $subtotal += $qty * $price;
                    }

                    $taxRate = config('app.booking_tax_rate', 0.15);
                    $tax   = round($subtotal * $taxRate, 2);
                    $total = round($subtotal + $tax, 2);
                }
            }

            $booking = new Booking();
            $booking->user_id      = $payload['user_id'];
            $booking->stylist_id   = $payload['stylist_id'] ?? null;
            $booking->service_date = $payload['service_date'];
            $booking->service_time = $payload['service_time']; // "HH:MM:SS"
            $booking->notes        = $payload['notes'] ?? null;
            $booking->subtotal     = $subtotal;
            $booking->tax          = $tax;
            $booking->total_price  = $total;
            $booking->status       = (int) $payload['status'];
            $booking->created_at   = now();
            $booking->save();

            // Crear items (sin columna 'total')
            if (is_array($itemsInput) && count($itemsInput) > 0) {
                foreach ($itemsInput as $it) {
                    $svc  = $recomputeFromDb ? Service::find($it['service_id'], ['id','price']) : null;
                    $unit = $svc ? (float) $svc->price : (float) ($it['unit_price'] ?? 0);
                    $qty  = (int) $it['quantity'];

                    BookingItem::create([
                        'booking_id' => $booking->id,
                        'service_id' => $it['service_id'],
                        'quantity'   => $qty,
                        'unit_price' => $unit,
                        'created_at' => now(),
                    ]);
                }
            }

            // Cargar relaciones para el correo y la respuesta
            $booking->loadMissing(['items','stylist.user','user']);

            // Enviar correo de notificación
            $this->notifyBookingCreated($booking);

            return (new BookingResource($booking))->response()->setStatusCode(201);
        });
    }

    // POST /api/bookings/{booking}/items
    public function storeItem(Booking $booking, Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required','integer','exists:services,id'],
            'quantity'   => ['required','integer','min:1'],
            'unit_price' => ['nullable','numeric','min:0'],
        ]);

        return DB::transaction(function () use ($booking, $data) {
            $svc  = Service::findOrFail($data['service_id'], ['id','price']);
            $unit = (float) ($data['unit_price'] ?? $svc->price);
            $qty  = (int) $data['quantity'];

            BookingItem::create([
                'booking_id' => $booking->id,
                'service_id' => $svc->id,
                'quantity'   => $qty,
                'unit_price' => $unit,
                'created_at' => now(),
            ]);

            // Recalcular totales con SUM(quantity * unit_price)
            $sum = DB::table('booking_items')
                ->where('booking_id', $booking->id)
                ->sum(DB::raw('quantity * unit_price'));

            $taxRate = config('app.booking_tax_rate', 0.15);
            $booking->subtotal    = round($sum, 2);
            $booking->tax         = round($sum * $taxRate, 2);
            $booking->total_price = round($booking->subtotal + $booking->tax, 2);
            $booking->save();

            return (new BookingResource($booking->fresh(['items'])))->response()->setStatusCode(201);
        });
    }

    /** -------------------- Helpers -------------------- */

    private function notifyBookingCreated(Booking $booking): void
    {
        // Email del usuario dueño de la estilista (stylists.user_id → users.email)
        $stylistEmail = $booking->stylist?->user?->email;

        // Lista adicional (coma-separado) desde .env → APP_BOOKING_NOTIFY o APP.BOOKING_NOTIFY
        $extra = collect(explode(',', (string)config('app.booking_notify', '')))
            ->map(fn($e) => trim($e))
            ->filter()
            ->values()
            ->all();

        // Construir destinatarios
        $to = [];
        if (!empty($stylistEmail)) $to[] = $stylistEmail;
        $to = array_values(array_unique(array_merge($to, $extra)));

        if (empty($to)) {
            $to = [config('mail.from.address')];
        }

        $url = $this->frontUrlForReview($booking);
        Mail::to($to)->send(new \App\Mail\BookingCreatedMail($booking, $url));
    }


    private function frontUrlForReview(Booking $booking): string
    {
        $base = rtrim((string)config('app.frontend_url', config('app.url')), '/');

        // Ajusta la ruta a la que deban ir para confirmar/rechazar:
        // Ejemplos:
        //   /account/bookings/{id}  (panel usuario)
        //   /staff/bookings/{id}    (panel estilistas)
        return $base . '/account/bookings/' . $booking->id;
    }
}
