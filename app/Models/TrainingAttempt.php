<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingAttempt extends Model
{
    protected $fillable = [
        'training_id',
        'user_id',
        'assignment_id',
        'score',
        'passed',
        'answers_json',
        'completed_at',
    ];

    protected $casts = [
        'training_id' => 'integer',
        'user_id' => 'integer',
        'assignment_id' => 'integer',
        'score' => 'integer',
        'passed' => 'boolean',
        'answers_json' => 'array',
        'completed_at' => 'datetime',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssignment::class);
    }
}
