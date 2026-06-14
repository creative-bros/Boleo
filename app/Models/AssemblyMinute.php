<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssemblyMinute extends Model
{
    use HasFactory;

    protected $fillable = [
        'condominium_profile_id',
        'title',
        'assembly_date',
        'duration',
        'summary',
        'document_path',
        'convocation_path',
    ];

    protected function casts(): array
    {
        return [
            'assembly_date' => 'date',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CondominiumProfile::class, 'condominium_profile_id');
    }
}
