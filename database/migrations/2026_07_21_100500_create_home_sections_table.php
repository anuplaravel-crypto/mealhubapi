<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The common envelope every home-page section shares — one row per section
     * key, holding the eyebrow/heading/body/image/visibility that all of them
     * have in common. Repeatable content hangs off a typed child table instead
     * (see create_section_features_table).
     *
     * `key` identifies which section a row drives, so rows are a **fixed set**:
     * adding one no client renders would be inert data. That is why the API
     * exposes no store or destroy endpoint for this resource — the seeder
     * creates the rows and an admin only ever edits them, the same arrangement
     * site_settings uses for its singleton.
     *
     * `extras` carries genuinely section-specific fields and is capped at three
     * properties by the write-path validation. The cap is the whole point:
     * without it this becomes an untyped, unqueryable dumping ground, and a
     * section needing more than three has earned a child table of its own.
     *
     * `heading_accent` is the highlighted tail of a headline — About reads
     * "A smarter way to eat well, *every single day*" with the last phrase
     * emphasised. It is a column rather than markup inside `heading` for the
     * same reason site_settings splits its wordmark into brand_primary_text and
     * brand_accent_text: an admin never types HTML, and the client decides how
     * to style each half. It is envelope rather than section-specific because
     * the hero highlights its headline the same way.
     *
     * `image_url` is the external counterpart to `image`, mirroring
     * testimonials' avatar/avatar_url pair. It exists because the sections ship
     * with hot-linked photos a seeder cannot download into storage. The write
     * path clears it whenever an upload is saved, so at most one source
     * survives an edit.
     *
     * (In MealHub `heading_accent` and `image_url` arrived as a later ALTER;
     * they are folded into the create here since this schema has no history to
     * replay.)
     */
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('eyebrow', 60)->nullable();
            $table->string('heading', 160);
            $table->string('heading_accent', 120)->nullable();
            $table->text('body')->nullable();
            $table->string('image', 255)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->json('extras')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
