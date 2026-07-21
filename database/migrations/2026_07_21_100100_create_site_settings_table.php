<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site-wide branding and meta, held as a single row.
     *
     * There is no column enforcing the single-row rule — it is enforced by the
     * read path being a firstOrCreate on SiteSetting::SINGLETON_ID, and by the
     * API exposing no store or destroy endpoint for this resource.
     *
     * The wordmark is split across `brand_primary_text` and
     * `brand_accent_text` rather than being one field containing markup, so an
     * admin never types HTML and the client decides how to style each half.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 100);
            $table->string('brand_primary_text', 60);
            $table->string('brand_accent_text', 60)->nullable();
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('logo', 255)->nullable();
            $table->text('footer_blurb')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
