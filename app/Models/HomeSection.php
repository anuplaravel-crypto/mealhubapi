<?php

namespace App\Models;

use Database\Factories\HomeSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One home-page section's shared envelope.
 *
 * See the create_home_sections_table migration for why rows are a fixed set
 * and why `extras` is capped.
 *
 * There is no `imageSrc` accessor here: resolving `image` (an uploaded
 * filename) against `image_url` (an external link) into one absolute URL is
 * response shaping and belongs in the API Resource, which is also the only
 * layer that knows the request's host.
 */
class HomeSection extends Model
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory;

    /**
     * Directory the image service stores section imagery under. Kept as the
     * storage-layout contract even though no upload endpoint exists yet.
     */
    public const IMAGE_COLLECTION = 'sections';

    /**
     * The "who we are" block with its floating badge.
     */
    public const KEY_ABOUT = 'about';

    /**
     * The how-it-works step cards.
     */
    public const KEY_HOW_IT_WORKS = 'how_it_works';

    /**
     * The meal-type browse cards' heading/body chrome. The cards themselves
     * are `meal_categories` rows, queried independently — see that table's
     * migration for why they are not `section_features`.
     */
    public const KEY_MEAL_TYPES = 'meal_types';

    /**
     * The partner carousel's heading/body chrome and its "View all" button.
     * The cards themselves are `featured_restaurants` rows, queried
     * independently — see that table's migration.
     */
    public const KEY_FEATURED_RESTAURANTS = 'featured_restaurants';

    /**
     * The dark rider-recruitment panel.
     */
    public const KEY_DELIVERY = 'delivery';

    /**
     * The closing app-download call to action.
     */
    public const KEY_CTA = 'cta';

    /**
     * Every section key a client is expected to render.
     *
     * @var list<string>
     */
    public const KEYS = [
        self::KEY_ABOUT,
        self::KEY_HOW_IT_WORKS,
        self::KEY_MEAL_TYPES,
        self::KEY_FEATURED_RESTAURANTS,
        self::KEY_DELIVERY,
        self::KEY_CTA,
    ];

    /**
     * The most properties `extras` may hold. A section needing more has earned
     * a typed child table instead.
     */
    public const MAX_EXTRAS = 3;

    /**
     * The extras each section understands, as name => admin-facing label.
     *
     * Declared here so the write path can whitelist what it accepts — without
     * it an admin could invent a property no client reads, which looks saved
     * but does nothing.
     *
     * @var array<string, array<string, string>>
     */
    public const EXTRA_FIELDS = [
        self::KEY_ABOUT => [
            'badge_icon' => 'Badge icon class',
            'badge_text' => 'Badge text (one line per row)',
        ],
        self::KEY_FEATURED_RESTAURANTS => [
            'cta_label' => 'Button label',
            'cta_url' => 'Button URL',
        ],
        self::KEY_DELIVERY => [
            'cta_label' => 'Button label',
            'cta_icon' => 'Button icon class',
            'cta_url' => 'Button URL',
        ],
        self::KEY_CTA => [
            'app_store_url' => 'App Store URL',
            'google_play_url' => 'Google Play URL',
        ],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'eyebrow',
        'heading',
        'heading_accent',
        'body',
        'image',
        'image_url',
        'extras',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extras' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SectionFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(SectionFeature::class);
    }

    /**
     * Read one section-specific field without tripping over a null `extras`.
     *
     * Note for clients: the About section's `badge_text` may contain a real
     * newline, and that break is meaningful — render it, don't collapse it.
     */
    public function extra(string $name, ?string $default = null): ?string
    {
        return $this->extras[$name] ?? $default;
    }
}
