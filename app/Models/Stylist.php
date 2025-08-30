<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
// app/Models/Stylist.php

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stylist extends Model
{
    public $timestamps = false;

    protected $fillable = ['img','bio','user_id','specialty','active'];

    protected $casts = [
        'active'     => 'boolean',
        'created_at' => 'datetime',
    ];

    // 👇 añade esto
    protected $appends = ['name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    
}
