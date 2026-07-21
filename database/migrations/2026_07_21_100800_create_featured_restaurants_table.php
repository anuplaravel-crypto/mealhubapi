<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The home page's "Featured restaurants" carousel cards.
     *
     * READ THIS BEFORE EXTENDING THE TABLE. Unlike every other CMS table here,
     * most of these columns are not editorial content — they are
     * **restaurant-domain facts being faked** because the domain does not exist
     * yet. A restaurant today is a `users` row with `role='restaurant'`
     * carrying an owner's firstName/lastName and no business name, cuisine,
     * rating or delivery estimate. This table stands in until that changes.
     *
     * `user_id` is the seam. It is nullable and null for every seeded row: the
     * cards are pure placeholders today. Once a real restaurant entity exists,
     * an admin links a card to it, and these display columns become fallbacks
     * that the linked entity supersedes — not the source of truth they are now.
     * `users` is one table discriminated by `role`, so nothing but validation
     * stops a rider being linked here; the write-path validation must constrain
     * the exists rule to role='restaurant'. The FK is nullOnDelete rather than
     * cascade so a removed account degrades a card to its own placeholder copy
     * instead of deleting it.
     *
     * Which columns are which, when the domain lands:
     *   - name, image/image_url, cuisines, location, delivery_time — become the
     *     restaurant's own fields. Superseded.
     *   - rating — MUST become derived from real reviews. It is editable here
     *     only because dropping it would have broken the visual-no-op property
     *     this conversion is verified against. An admin-typed rating is
     *     fabricated social proof; treat shipping it as debt with a deadline,
     *     not as a feature.
     *   - tag ("Top rated", "New") — genuinely editorial. An admin choosing to
     *     badge a partner is curation and should survive the domain landing.
     *   - perk_label/perk_variant — mixed. "Free delivery" is a commercial fact
     *     the restaurant owns; "Popular" is derived. Expect to split.
     *
     * MealHub chunked these rows into fixed-size carousel slides server-side.
     * That is a DOM decision the SPA now owns, so no per-slide constant lives
     * on this side.
     */
    public function up(): void
    {
        Schema::create('featured_restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('image', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->string('location', 60)->nullable();
            $table->string('cuisines', 120)->nullable();
            $table->string('delivery_time', 40)->nullable();
            $table->string('tag', 30)->nullable();
            $table->string('perk_label', 30)->nullable();
            $table->enum('perk_variant', ['success', 'warning'])->default('success');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_restaurants');
    }
};
