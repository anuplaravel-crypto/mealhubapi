<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "meal types" browse cards on the home page — Healthy Bowls,
     * Breakfast, High Protein, Vegan & Veggie.
     *
     * Deliberately its own table rather than a row in `section_features` under
     * a `meal_types` home_sections key, even though every other home section's
     * repeatable content lives there. The other sections' items (how-it-works
     * steps, about's feature list, rider perks) are pure presentation with no
     * life outside the home page. These categories are not: `menu_items` — a
     * table this project's domain already anticipates — will need to hang off a
     * category, and a customer order already spans "multiple meal types" per
     * the core domain description. Modelling that FK against a row shaped for
     * CMS display would mean unpicking it the moment the ordering domain
     * exists. This table carries no FK to home_sections at all, in either
     * direction — the home page's envelope (eyebrow/heading/body) still comes
     * from a home_sections row keyed `meal_types` for consistency with the
     * other sections, but the two are queried independently.
     *
     * Unlike home_sections' fixed, seeder-owned rows, this is a normal
     * full-CRUD lookup table: a fifth category is an ordinary thing an admin
     * might add.
     *
     * No `slug` column yet — nothing today needs one, and it is the obvious
     * next addition once the ordering domain actually needs to route or filter
     * by category rather than just display one.
     *
     * `tagline` holds the "120+ options" style copy the cards ship with today.
     * It is free text, not a computed count, because there are no `menu_items`
     * yet to count — expect it to be replaced by a real aggregate once that
     * table exists.
     */
    public function up(): void
    {
        Schema::create('meal_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('tagline', 60)->nullable();
            $table->string('image', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_categories');
    }
};
