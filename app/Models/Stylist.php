<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stylist extends Model
{
    protected $fillable = [
        'name','specialty','active',
    ];

    public $timestamps = false; // solo created_at en migración (si lo dejas, puedes cambiarlo)

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
