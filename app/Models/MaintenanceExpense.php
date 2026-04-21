<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'spent_at',
        'expense_group',
        'category',
        'report_month',
        'concept',
        'provider_id',
        'amount',
        'document_path',
        'document_name',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'spent_at' => 'date',
            'report_month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
