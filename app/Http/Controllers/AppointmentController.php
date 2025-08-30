<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Stylist;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    // Si ya tienes estos helpers en el controlador, puedes omitirlos.
    private function normalize(Booking $b): array
    {
        // status a string para el front; ajusta si prefieres número
        $map = [
            Booking::STATUS_PENDING   => 'pending',
            Booking::STATUS_CONFIRMED => 'confirmed',
            Booking::STATUS_COMPLETED => 'completed',
            Booking::STATUS_CANCELED  => 'cancelled',
        ];

        return [
            'id'         => $b->id,
            'user_id'    => $b->user_id,
            'stylist_id' => $b->stylist_id,
            'date'       => optional($b->service_date)->toDateString(),
            'time'       => $b->service_time,
            'status'     => $map[(int)$b->status] ?? 'pending',
        ];
    }

    private function applyFutureFilter($q)
    {
        $today = Carbon::today()->toDateString();
        $now   = Carbon::now()->format('H:i'); // "HH:MM"
        return $q->where(function ($qq) use ($today, $now) {
            $qq->whereDate('service_date', '>', $today)
               ->orWhere(function ($q2) use ($today, $now) {
                   $q2->whereDate('service_date', '=', $today)
                      ->where('service_time', '>=', $now);
               });
        });
    }

    // GET /appointments/mine  -> TODAS las citas para el estilista del usuario logeado (con filtros opcionales)
    public function mine(Request $req)
    {
        $stylistId = Stylist::where('user_id', Auth::id())->value('id');
        if (!$stylistId) {
            return response()->json([]); // usuario no está ligado a un stylist
        }

        $q = Booking::query()
            ->select(['id','user_id','stylist_id','service_date','service_time','status'])
            ->where('stylist_id', $stylistId)
            ->orderBy('service_date')
            ->orderBy('service_time');

        // filtros opcionales
        if ($req->filled('status')) {
            // acepta "0,1" o "pending,confirmed"
            $statuses = collect(explode(',', $req->status))
                ->map(fn($s) => strtolower(trim($s)))
                ->map(function ($v) {
                    if (is_numeric($v)) return (int)$v;
                    return match ($v) {
                        'pending'   => Booking::STATUS_PENDING,
                        'confirmed' => Booking::STATUS_CONFIRMED,
                        'completed' => Booking::STATUS_COMPLETED,
                        'cancelled','canceled' => Booking::STATUS_CANCELED,
                        default => null,
                    };
                })
                ->filter(fn($x) => $x !== null)
                ->values()
                ->all();

            if (!empty($statuses)) {
                $q->whereIn('status', $statuses);
            }
        }
        if ($req->filled('from')) $q->whereDate('service_date', '>=', $req->input('from'));
        if ($req->filled('to'))   $q->whereDate('service_date', '<=', $req->input('to'));

        return response()->json($q->get()->map(fn($b) => $this->normalize($b)));
    }

    // GET /appointments/mine/pending  -> PENDIENTES del estilista del usuario logeado
    public function myPending(Request $req)
    {
        $stylistId = Stylist::where('user_id', Auth::id())->first();
        if (!$stylistId) {
            return response()->json([]);
        }

        $q = Booking::query()
            ->select(['id','user_id','stylist_id','service_date','service_time','status'])
            ->where('stylist_id', $stylistId->id)
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('service_date')
            ->orderBy('service_time');

        return response()->json($q->get()->map(fn($b) => $this->normalize($b)));
    }

    // GET /appointments/mine/upcoming  -> CONFIRMADAS a futuro del estilista del usuario logeado
    public function myUpcoming(Request $req)
    {
        $stylistId = Stylist::where('user_id', Auth::id())->first();
        
        Log::error($stylistId->id);
        
        if (!$stylistId) {
            return response()->json([]);
        }

        $q = Booking::query()
            ->select(['id','user_id','stylist_id','service_date','service_time','status'])
            ->where('stylist_id', $stylistId->id)
            ->where('status', Booking::STATUS_PENDING);

        $this->applyFutureFilter($q)
            ->orderBy('service_date')
            ->orderBy('service_time');

        return response()->json($q->get()->map(fn($b) => $this->normalize($b)));
    }

    // Acciones:
    public function accept(Booking $appointment, Request $req)
    {
        // (Opcional) valida que Auth::id() sea el dueño del stylist asignado a $appointment->stylist_id
        $appointment->update(['status' => Booking::STATUS_CONFIRMED]);
        return response()->json($this->normalize($appointment));
    }

    public function decline(Booking $appointment, Request $req)
    {
        $appointment->update(['status' => Booking::STATUS_CANCELED]);
        return response()->json($this->normalize($appointment));
    }

    public function cancel(Booking $appointment, Request $req)
    {
        $appointment->update(['status' => Booking::STATUS_CANCELED]);
        return response()->json($this->normalize($appointment));
    }

    public function rebook(Booking $appointment, Request $req)
    {
        $data = $req->validate([
            'date' => ['required','date'],
            'time' => ['required','string'], // "HH:MM"
        ]);

        $appointment->update([
            'service_date' => $data['date'],
            'service_time' => $data['time'],
        ]);

        return response()->json($this->normalize($appointment));
    }
}
