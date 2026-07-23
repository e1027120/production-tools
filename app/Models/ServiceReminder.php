<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'asset_id',
        'title',
        'frequency',
        'next_due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'next_due_date' => 'date',
        'church_id' => 'integer',
        'asset_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
