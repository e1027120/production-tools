<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingAssignment extends Model
{
    protected $fillable = [
        'training_id',
        'user_id',
        'assigned_by',
        'due_at',
        'status',
    ];

    protected $casts = [
        'training_id' => 'integer',
        'user_id' => 'integer',
        'assigned_by' => 'integer',
        'due_at' => 'datetime',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class, 'assignment_id');
    }
}
