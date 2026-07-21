<?php

namespace App\Models;

use Database\Factories\FeaturedRestaurantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One card in the home page's featured-restaurants carousel.
 *
 * See the create_featured_restaurants_table migration first — most of these
 * columns are restaurant-domain facts standing in for a domain that does not
 * exist yet, not editorial content, and `user_id` is the seam where the real
 * entity will eventually take over.
 *
 * No `imageSrc` or `perkClass` accessor, and no per-slide constant: image
 * resolution belongs in the API Resource, and how many cards make a carousel
 * slide is a DOM decision the client owns.
 */
class FeaturedRestaurant extends Model
{
    /** @use HasFactory<FeaturedRestaurantFactory> */
    use HasFactory;

    /**
     * Directory the image service stores card imagery under. Kept as the
     * storage-layout contract even though no upload endpoint exists yet.
     */
    public const IMAGE_COLLECTION = 'featured-restaurants';

    /**
     * @var list<string>
     */
    public const PERK_VARIANTS = ['success', 'warning'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'image',
        'image_url',
        'rating',
        'location',
        'cuisines',
        'delivery_time',
        'tag',
        'perk_label',
        'perk_variant',
        'is_published',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The `users` row this card stands in for, once one is linked.
     *
     * Null for every card today. Named `restaurant` rather than `user` because
     * the column is only ever meant to hold a role='restaurant' row, which the
     * write-path validation enforces — `users` itself is one table for all four
     * roles.
     *
     * @return BelongsTo<User, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
