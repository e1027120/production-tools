<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingStep extends Model
{
    protected $fillable = [
        'training_id',
        'title',
        'content',
        'image_path',
        'audio_path',
        'video_url',
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
}
