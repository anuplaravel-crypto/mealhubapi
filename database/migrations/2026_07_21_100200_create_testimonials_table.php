<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer reviews shown in the home-page testimonial carousel.
     *
     * An avatar can come from either of two columns: `avatar` holds an
     * uploaded filename, `avatar_url` an externally hosted image. Both exist
     * because the seeded reviews use hot-linked photos a seeder cannot
     * download. The write path clears `avatar_url` whenever an upload is
     * saved, so at most one is populated after any edit.
     *
     * Resolving the two into a single URL is response shaping and belongs in
     * the API Resource, not on the model.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote');
            $table->string('author_name', 100);
            $table->string('author_role', 100)->nullable();
            $table->string('avatar', 255)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
