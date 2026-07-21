<?php

namespace App\Models;

use Database\Factories\SectionFeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One repeatable item inside a home section — a how-it-works step, an
 * about-page feature, or a rider perk.
 *
 * `accent` ships as a bare token ("green"), not a CSS variable or rgba tint —
 * those were Blade concerns and the client owns them now.
 *
 * A how-it-works step's number is not stored: it is derived from render
 * position, so hiding a step renumbers the rest rather than leaving a gap.
 */
class SectionFeature extends Model
{
    /** @use HasFactory<SectionFeatureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    public const ACCENTS = ['green', 'orange'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'home_section_id',
        'title',
        'body',
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

    /**
     * @return BelongsTo<HomeSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }
}
