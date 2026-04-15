<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'area',
        'provider_id',
        'last_cost',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_cost' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
