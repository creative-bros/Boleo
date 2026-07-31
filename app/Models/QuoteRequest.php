<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequest extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DENIED = 'denied';

    protected $fillable = [
        'quote_number',
        'condominium_profile_id',
        'condominium_name',
        'external_reference',
        'source_system',
        'client_name',
        'client_email',
        'client_phone',
        'property_location',
        'monthly_budget',
        'has_administration',
        'has_prosoc_certification',
        'apartment_count',
        'comment',
        'consultation_date',
        'source',
        'contact_name',
        'contact_email',
        'contact_phone',
        'service_type',
        'description',
        'desired_date',
        'budget_amount',
        'priority',
        'status',
        'decision_notes',
        'decided_at',
        'decided_by',
        'metadata',
        'origin_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'desired_date' => 'date',
            'budget_amount' => 'decimal:2',
            'has_administration' => 'boolean',
            'has_prosoc_certification' => 'boolean',
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function condominiumProfile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
