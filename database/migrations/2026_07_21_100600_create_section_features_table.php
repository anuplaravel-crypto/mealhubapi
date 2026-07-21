<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The repeatable items belonging to a home section — the how-it-works
     * steps, the about section's feature list, and the rider perks.
     *
     * Those three render very differently (a numbered badge, an icon tile, a
     * bare icon) but carry the same content: a title, a line of body copy, and
     * an accent colour. That shared shape is why they get one typed child table
     * rather than one per section.
     *
     * `icon_class` is nullable because how-it-works numbers its steps instead
     * of icon-ing them, and the number is derived from render position rather
     * than stored — storing it would let an admin save two "3"s, and hiding a
     * step would leave a gap in the sequence.
     *
     * Note that not every repeatable home-page collection belongs here: the
     * meal-type browse cards are their own `meal_categories` table with no
     * relationship to this one, because they have a life beyond the home page
     * once the ordering domain exists.
     */
    public function up(): void
    {
        Schema::create('section_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->string('icon_class', 60)->nullable();
            $table->enum('accent', ['green', 'orange'])->default('green');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['home_section_id', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_features');
    }
};
