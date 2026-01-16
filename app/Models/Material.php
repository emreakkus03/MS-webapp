<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'sap_number',
        'description',
        'unit',
        'packaging'
    ];

    /**
     * Relatie: Materialen zitten in veel bestellingen.
     * We voegen ->withPivot('quantity', 'ready') toe zodat je die velden kunt uitlezen.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'material_order')
                    ->withPivot('quantity', 'ready')
                    ->withTimestamps();
    }
}
