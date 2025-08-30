<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    public const STATUS_PENDING   = 0; // pendiente
    public const STATUS_CONFIRMED = 1; // confirmada
    public const STATUS_COMPLETED = 2; // completada
    public const STATUS_CANCELED  = 3; // cancelada

    protected $fillable = [
        'user_id','stylist_id','service_date','service_time',
        'notes','subtotal','tax','total_price','status',
    ];

    public $timestamps = false; // tienes created_at manual; si quieres updated_at, cambia migración

    protected $casts = [
        'service_date' => 'date',
        'service_time' => 'string',          // ✅ deja la hora como texto
        'subtotal'     => 'decimal:2',
        'tax'          => 'decimal:2',
        'total_price'  => 'decimal:2',
        'status'       => 'integer',
        'created_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stylist(): BelongsTo
    {
        return $this->belongsTo(Stylist::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    
}
