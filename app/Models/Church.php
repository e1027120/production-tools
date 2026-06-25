<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Users belonging to this church with role and module rights.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ChurchUser::class)
            ->withPivot('role', 'modules')
            ->withTimestamps();
    }

    /**
     * Racks associated with this church.
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    /**
     * Custom equipment catalog items associated with this church.
     */
    public function catalogDevices(): HasMany
    {
        return $this->hasMany(CatalogDevice::class);
    }

    /**
     * Shopping lists associated with this church.
     */
    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }
}
