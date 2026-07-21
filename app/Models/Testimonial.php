<?php

namespace App\Models;

use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One customer review in the home-page carousel.
 *
 * No `avatarSrc` or `starIcons` accessor. Resolving `avatar` against
 * `avatar_url` is response shaping for the API Resource; the star row is
 * rendered by the client from the `rating` decimal.
 *
 * One rule the client must preserve when rendering stars: no empty stars are
 * drawn. A 4.5 rating shows five icons (four full plus a half), a 3.0 rating
 * shows three. That is the appearance every existing review already has.
 */
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * Directory the image service stores avatars under. Kept as the
     * storage-layout contract even though no upload endpoint exists yet.
     */
    public const IMAGE_COLLECTION = 'testimonials';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quote',
        'author_name',
        'author_role',
        'avatar',
        'avatar_url',
        'rating',
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
}
