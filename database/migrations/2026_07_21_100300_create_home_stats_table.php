<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The home page's two number rows, in one table split by `placement`.
     *
     * The hero's mini-stats and the stat bar below it are the same shape — a
     * label and a number — rendered two different ways, so they share a table
     * with a discriminator rather than getting one table each.
     *
     * `value` is a string because the two placements read it differently, and
     * the write-path validation must enforce the difference:
     *   - `hero` is rendered verbatim, so it carries the whole display string
     *     ("15k+", "4.9★") including any suffix.
     *   - `stat_bar` feeds a client-side counter animation, so it must be
     *     digits only. A value like "15k+" would animate to NaN.
     *
     * `icon_class` and `accent` only mean anything for `stat_bar`; the hero
     * mini-stats are plain text with no icon and no colour of their own.
     *
     * Note: `icon_class` currently carries Bootstrap Icons class names
     * inherited from the MealHub seed data. A client using a different icon
     * set should treat it as an opaque token to map, and the long-term fix is
     * to reseed bare semantic tokens ("shop") rather than framework classes.
     */
    public function up(): void
    {
        Schema::create('home_stats', function (Blueprint $table) {
            $table->id();
            $table->enum('placement', ['hero', 'stat_bar']);
            $table->string('label', 60);
            $table->string('value', 20);
            $table->string('icon_class', 60)->nullable();
            $table->enum('accent', ['green', 'orange'])->default('green');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['placement', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_stats');
    }
};
