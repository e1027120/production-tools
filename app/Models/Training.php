<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Training extends Model
{
    protected $fillable = [
        'church_id',
        'title',
        'description',
        'ministry',
        'has_test',
        'passing_score',
        'created_by',
    ];

    protected $casts = [
        'has_test' => 'boolean',
        'passing_score' => 'integer',
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

    public function steps(): HasMany
    {
        return $this->hasMany(TrainingStep::class)->orderBy('sort_order');
    }

    public function testQuestions(): HasMany
    {
        return $this->hasMany(TestQuestion::class)->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class);
    }
}
