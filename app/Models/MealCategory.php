<?php

namespace App\Models;

use Database\Factories\MealCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A meal-type browse card on the home page — Healthy Bowls, Breakfast, and so
 * on. See the create_meal_categories_table migration for why this is an
 * independent table rather than a home_sections/section_features row.
 *
 * No `imageSrc` accessor: resolving `image` against `image_url` is response
 * shaping for the API Resource.
 */
class MealCategory extends Model
{
    /** @use HasFactory<MealCategoryFactory> */
    use HasFactory;

    /**
     * Directory the image service stores category imagery under. Kept as the
     * storage-layout contract even though no upload endpoint exists yet.
     */
    public const IMAGE_COLLECTION = 'meal-categories';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tagline',
        'image',
        'image_url',
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
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
