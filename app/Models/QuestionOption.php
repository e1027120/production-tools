<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'image_path',
        'is_correct',
    ];

    protected $casts = [
        'question_id' => 'integer',
        'is_correct' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class, 'question_id');
    }
}
