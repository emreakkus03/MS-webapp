<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'pickup_date',
        'license_plate',
        'status'
    ];

    // Zorgt dat pickup_date een Carbon datum-object wordt (makkelijk voor format)
    protected $casts = [
        'pickup_date' => 'date',
    ];

    /**
     * Relatie: Een bestelling hoort bij een Team (Ploeg).
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Relatie: Een bestelling bevat veel materialen.
     */
    public function materials()
    {
        return $this->belongsToMany(Material::class, 'material_order')
                    ->withPivot('quantity', 'ready')
                    ->withTimestamps();
    }
}
