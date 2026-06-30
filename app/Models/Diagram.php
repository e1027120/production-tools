<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagram extends Model
{
    protected $fillable = [
        'church_id',
        'name',
        'type',
        'description',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'church_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
