<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'resident_receipt_id',
        'concept',
        'amount',
        'status',
        'payment_method',
        'payment_type',
        'payment_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function residentReceipt(): BelongsTo
    {
        return $this->belongsTo(ResidentReceipt::class);
    }
}
