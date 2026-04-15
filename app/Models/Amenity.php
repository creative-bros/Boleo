<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amenity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area',
        'status',
        'capacity',
        'hours',
        'notes',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(AmenityReservation::class);
    }
}
