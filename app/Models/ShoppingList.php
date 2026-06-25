<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $church_id
 * @property string $name
 * @property string|null $description
 * @property string|null $share_token
 * @property array|null $shared_emails
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShoppingList extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'name',
        'description',
        'share_token',
        'shared_emails',
        'created_by',
    ];

    protected $casts = [
        'church_id' => 'integer',
        'shared_emails' => 'array',
        'created_by' => 'integer',
    ];

    /**
     * Get the church that owns the shopping list.
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Get the user who created the shopping list.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items for the shopping list.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class);
    }
}
