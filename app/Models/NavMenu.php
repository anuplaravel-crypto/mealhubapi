<?php

namespace App\Models;

use Database\Factories\NavMenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One navigation link on the public site.
 *
 * See the create_nav_menus_table migration for how four locations cover the
 * six visual groups.
 *
 * There is deliberately no `href` accessor. A link's target is either a
 * `route_key` (an opaque token the SPA maps to its own path) or a literal
 * `url`, and choosing between them is the client's job — this API has no named
 * web routes to resolve against. Likewise there is no CSS-class helper:
 * `variant` is a semantic token the client styles.
 */
class NavMenu extends Model
{
    /** @use HasFactory<NavMenuFactory> */
    use HasFactory;

    /**
     * The top nav — plain links and the CTA buttons, split by `variant`.
     */
    public const LOCATION_NAVBAR = 'navbar';

    /**
     * Both footer link columns, split by `group_label`.
     */
    public const LOCATION_FOOTER_MENU = 'footer_menu';

    /**
     * The footer's social icon row.
     */
    public const LOCATION_SOCIAL = 'social';

    /**
     * The Privacy / Terms / Cookies row beneath the footer rule.
     */
    public const LOCATION_LEGAL = 'legal';

    /**
     * @var list<string>
     */
    public const LOCATIONS = [
        self::LOCATION_NAVBAR,
        self::LOCATION_FOOTER_MENU,
        self::LOCATION_SOCIAL,
        self::LOCATION_LEGAL,
    ];

    public const VARIANT_OUTLINE = 'outline';

    public const VARIANT_SOLID = 'solid';

    /**
     * @var list<string>
     */
    public const VARIANTS = [self::VARIANT_OUTLINE, self::VARIANT_SOLID];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'location',
        'group_label',
        'label',
        'icon_class',
        'variant',
        'url',
        'route_key',
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
