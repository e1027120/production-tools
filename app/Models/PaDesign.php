<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $church_id
 * @property string $name
 * @property string|null $description
 * @property array|null $data
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaDesign extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'name',
        'description',
        'data',
        'created_by',
    ];

    protected $casts = [
        'church_id' => 'integer',
        'data' => 'array',
        'created_by' => 'integer',
    ];

    /**
     * Get the church that owns this PA design.
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Get the user who created this PA design.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
