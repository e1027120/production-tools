<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    /** @use HasFactory<\Database\Factories\RackFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'size',
        'description',
        'devices',
        'church_id',
    ];

    protected $casts = [
        'devices' => 'array',
        'size' => 'integer',
        'church_id' => 'integer',
    ];

    /**
     * Get the church that owns the rack layout.
     */
    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
