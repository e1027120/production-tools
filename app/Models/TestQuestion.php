<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestQuestion extends Model
{
    protected $fillable = [
        'training_id',
        'question_text',
        'image_path',
        'type',
        'sort_order',
    ];

    protected $casts = [
        'training_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id');
    }
}
