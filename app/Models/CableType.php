<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $church_id
 * @property string $name
 * @property string $color
 * @property float $price_per_m
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CableType extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'name',
        'color',
        'price_per_m',
    ];

    protected $casts = [
        'church_id' => 'integer',
        'price_per_m' => 'float',
    ];

    /**
     * Get the church that owns this cable type.
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Fetch cable types for a church. Seeds defaults automatically if none exist.
     *
     * @return Collection<int, CableType>
     */
    public static function getForChurch(int $churchId): Collection
    {
        $types = self::where('church_id', $churchId)->orderBy('name')->get();

        if ($types->isEmpty()) {
            self::seedDefaultTypes($churchId);
            $types = self::where('church_id', $churchId)->orderBy('name')->get();
        }

        return $types;
    }

    /**
     * Seed default cable types for a given church.
     */
    public static function seedDefaultTypes(int $churchId): void
    {
        $defaults = [
            ['name' => 'Audio Cat6', 'color' => '#3B82F6', 'price_per_m' => 1.50],
            ['name' => 'Audio XLR', 'color' => '#EF4444', 'price_per_m' => 2.50],
            ['name' => 'Audio Speaker', 'color' => '#10B981', 'price_per_m' => 3.00],
            ['name' => 'Video Cat6', 'color' => '#F59E0B', 'price_per_m' => 1.80],
            ['name' => 'HDMI', 'color' => '#8B5CF6', 'price_per_m' => 5.00],
            ['name' => 'SDI', 'color' => '#EC4899', 'price_per_m' => 2.20],
            ['name' => 'Network Cat6', 'color' => '#06B6D4', 'price_per_m' => 1.20],
        ];

        foreach ($defaults as $type) {
            self::create([
                'church_id' => $churchId,
                'name' => $type['name'],
                'color' => $type['color'],
                'price_per_m' => $type['price_per_m'],
            ]);
        }
    }
}
