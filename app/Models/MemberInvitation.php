<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberInvitation extends Model
{
    protected $fillable = [
        'email',
        'church_id',
        'role',
        'modules',
        'token',
    ];

    protected $casts = [
        'modules' => 'array',
        'church_id' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
