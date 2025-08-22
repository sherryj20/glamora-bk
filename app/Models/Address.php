<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id','line1','line2','city','state',
    ];

    public $timestamps = false; // según tu migración

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
