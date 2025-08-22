<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name','description','duration_minutes','price',
        'requires_deposit','deposit_amount','active','category_id',
    ];

    public $timestamps = false;

    protected $casts = [
        'price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'requires_deposit' => 'boolean',
        'active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
