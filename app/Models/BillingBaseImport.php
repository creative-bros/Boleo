<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingBaseImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'condominium_profile_id',
        'original_name',
        'stored_path',
        'imported_rows',
        'status',
        'notes',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }

    public function condominiumProfile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class);
    }

    public function residentAccounts(): HasMany
    {
        return $this->hasMany(ImportedResidentAccount::class);
    }
}
