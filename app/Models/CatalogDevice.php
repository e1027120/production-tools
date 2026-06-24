<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand',
        'name',
        'type',
        'u_height',
        'power_consumption',
        'weight',
        'church_id',
    ];

    protected $casts = [
        'u_height' => 'integer',
        'power_consumption' => 'integer',
        'weight' => 'float',
        'church_id' => 'integer',
    ];

    /**
     * Get the church that owns this custom catalog equipment.
     */
    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
