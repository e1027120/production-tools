<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shopping_list_id
 * @property string $name
 * @property float $unit_price
 * @property int $quantity
 * @property string|null $link
 * @property string|null $comments
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $total_price
 */
class ShoppingListItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopping_list_id',
        'name',
        'unit_price',
        'quantity',
        'link',
        'comments',
    ];

    protected $casts = [
        'shopping_list_id' => 'integer',
        'unit_price' => 'float',
        'quantity' => 'integer',
    ];

    protected $appends = [
        'total_price',
    ];

    /**
     * Get the shopping list that owns the item.
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * Get the total price for this item (unit price * quantity).
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }
}
