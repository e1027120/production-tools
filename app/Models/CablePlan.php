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
 * @property string|null $floor_plan_path
 * @property float $scale_pixels
 * @property float $scale_distance
 * @property string $scale_unit
 * @property float $slack_percent
 * @property float $room_height
 * @property array|null $cables
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read array $totals_by_type
 */
class CablePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'name',
        'description',
        'floor_plan_path',
        'scale_pixels',
        'scale_distance',
        'scale_unit',
        'slack_percent',
        'room_height',
        'cables',
        'created_by',
    ];

    protected $casts = [
        'church_id' => 'integer',
        'scale_pixels' => 'float',
        'scale_distance' => 'float',
        'slack_percent' => 'float',
        'room_height' => 'float',
        'cables' => 'array',
        'created_by' => 'integer',
    ];

    protected $appends = [
        'totals_by_type',
    ];

    /**
     * Get the church that owns this cable plan.
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * Get the user who created this cable plan.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Calculate physical length for a single cable run in scale units.
     * Incorporates horizontal distance, 2x room height vertical runs, slack, and path quantity.
     */
    public function calculateCableLength(array $cable): float
    {
        $points = $cable['points'] ?? [];
        if (count($points) < 2) {
            return 0.0;
        }

        $scaleRatio = 0.0;
        if ($this->scale_pixels > 0) {
            $scaleRatio = $this->scale_distance / $this->scale_pixels;
        }

        $horizontalLength = 0.0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];

            $dx = ($p2['x'] - $p1['x']) * $scaleRatio;
            $dy = ($p2['y'] - $p1['y']) * $scaleRatio;

            $segmentLength = sqrt($dx * $dx + $dy * $dy);
            $horizontalLength += $segmentLength;
        }

        // Add 2 * room_height to the horizontal length
        $totalSingleLength = $horizontalLength + (2 * ($this->room_height ?? 3.5));

        // Apply slack percent
        $slackMultiplier = 1.0 + ($this->slack_percent / 100.0);
        $singleCableLengthWithSlack = $totalSingleLength * $slackMultiplier;

        // Multiply by quantity (qty) of cables on this path
        $qty = $cable['qty'] ?? 1;
        if ($qty < 1) {
            $qty = 1;
        }

        return $singleCableLengthWithSlack * $qty;
    }

    /**
     * Get summary totals grouped by cable type.
     */
    public function getTotalsByTypeAttribute(): array
    {
        $cablesList = $this->cables ?? [];
        $totals = [
            'Audio Cat6' => 0.0,
            'Audio XLR' => 0.0,
            'Audio Speaker' => 0.0,
            'Video Cat6' => 0.0,
            'HDMI' => 0.0,
            'SDI' => 0.0,
            'Network Cat6' => 0.0,
        ];

        foreach ($cablesList as $cable) {
            $type = $cable['type'] ?? '';
            if (array_key_exists($type, $totals)) {
                $totals[$type] += $this->calculateCableLength($cable);
            }
        }

        return $totals;
    }
}
