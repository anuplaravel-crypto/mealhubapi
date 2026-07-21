<?php

namespace App\Models;

use Database\Factories\RiderVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rider's one vehicle record, submitted during onboarding and verified by an
 * admin. See the create_rider_vehicles_table migration for how `is_active`
 * tracks the rider's `users.status`.
 */
class RiderVehicle extends Model
{
    /** @use HasFactory<RiderVehicleFactory> */
    use HasFactory;

    /**
     * The vehicle types a rider may register. Lowercase on the wire and in the
     * column — any client-facing labels ("Bike", "Car") map to these exact
     * values.
     *
     * @var list<string>
     */
    public const VEHICLE_TYPES = ['bike', 'car', 'scooter', 'bicycle'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rider_id',
        'image',
        'vehicle_type',
        'registration_number',
        'vehicle_color',
        'vehicle_brand',
        'vehicle_model',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The rider (user) this vehicle belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
