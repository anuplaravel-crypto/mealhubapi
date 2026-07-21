<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Storage collection profile pictures are written to.
     *
     * Unlike the CMS models' constants this is only the leaf: profile pictures
     * are personal data, and the four roles share one `users` table, so the
     * owning role prefixes it — `customer/profile`, `rider/profile`. The
     * prefixing happens in `ProfileService`, which is the only writer.
     */
    public const IMAGE_COLLECTION = 'profile';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstName',
        'lastName',
        'email',
        'mobile',
        'preferred_language',
        'image',
        'role',
        'password',
        'accept_registration_tnc',
        'marketing_consent',
        'otp',
        'otp_expires_at',
        'status',
        'is_email_verified',
        'address1',
        'address2',
        'zip_code',
        'doc_image1',
        'doc_image2',
        'latitude',
        'longitude',
        'country_id',
        'county_id',
        'city_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'otp_expires_at' => 'datetime',
            'accept_registration_tnc' => 'boolean',
            'marketing_consent' => 'boolean',
            'status' => 'boolean',
            'is_email_verified' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Vehicles registered by this rider (rider role only).
     *
     * @return HasMany<RiderVehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(RiderVehicle::class, 'rider_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Terms & conditions documents this user has accepted (via term_condition_users pivot).
     */
    public function acceptedTerms(): BelongsToMany
    {
        return $this->belongsToMany(TermCondition::class, 'term_condition_users')
            ->withPivot('accepted_at', 'ip_address')
            ->withTimestamps();
    }

    /**
     * Terms & conditions documents this user authored (admin only).
     */
    public function authoredTerms(): HasMany
    {
        return $this->hasMany(TermCondition::class, 'created_by');
    }
}
