<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * Campos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'name',
        'price',
        'category',
        'img',
        'description',
        'in_stock',
    ];

    public $timestamps = false;

    /**
     * Casts de atributos.
     */
    protected $casts = [
        'price'    => 'decimal:2',
        'category' => 'integer',
        'in_stock' => 'boolean',
    ];

    /**
     * Scopes útiles (opcionales).
     */
    public function scopeAvailable($query)
    {
        return $query->where('in_stock', true);
    }

    public function scopeCategory($query, int $category)
    {
        return $query->where('category', $category);
    }
}
