<?php

namespace App\Models;

use Database\Factories\HomeStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One number on the home page, in either of two placements.
 *
 * See the create_home_stats_table migration for why the hero mini-stats and
 * the stat bar share a table, and why `value` is a string.
 *
 * `accent` ships as a bare token ("green"), not a CSS class — the client maps
 * it to its own styling.
 */
class HomeStat extends Model
{
    /** @use HasFactory<HomeStatFactory> */
    use HasFactory;

    /**
     * The mini-stats sitting under the hero's search box.
     */
    public const PLACEMENT_HERO = 'hero';

    /**
     * The four icon cards in the band below the hero.
     */
    public const PLACEMENT_STAT_BAR = 'stat_bar';

    /**
     * @var list<string>
     */
    public const PLACEMENTS = [self::PLACEMENT_HERO, self::PLACEMENT_STAT_BAR];

    /**
     * @var list<string>
     */
    public const ACCENTS = ['green', 'orange'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'placement',
        'label',
        'value',
        'icon_class',
        'accent',
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
