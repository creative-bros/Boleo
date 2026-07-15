<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResidentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'condominium_profile_id',
        'unit_id',
        'period_year',
        'period_month',
        'amount_due',
        'amount_paid',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ResidentReceipt $receipt): void {
            $receipt->amount_paid = min(
                max((float) $receipt->amount_paid, 0),
                max((float) $receipt->amount_due, 0)
            );
            $receipt->status = self::statusFromAmounts((float) $receipt->amount_due, (float) $receipt->amount_paid);
        });
    }

    public static function statusFromAmounts(float $amountDue, float $amountPaid): string
    {
        if ($amountPaid <= 0) {
            return 'pendiente';
        }

        if ($amountPaid >= $amountDue) {
            return 'pagado';
        }

        return 'parcial';
    }

    public function condominiumProfile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
