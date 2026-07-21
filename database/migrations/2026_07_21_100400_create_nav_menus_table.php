<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every navigation link on the public site, in one table split by `location`.
     *
     * Four locations cover six visual groups, because two pairs collapse:
     *   - `navbar` holds both the plain nav links and the CTA buttons; the
     *     `variant` column is what tells them apart (null is a plain link,
     *     `outline`/`solid` are buttons).
     *   - `footer_menu` holds both footer columns; `group_label` carries the
     *     column heading ("Company", "Partners"), so headings are editable data
     *     rather than markup and a third column needs no migration.
     *   - `social` and `legal` are one group each.
     *
     * A link's target is either a `route_key` or a literal `url`, never both.
     *
     * `route_key` is deliberately NOT a Laravel route name. This API has no
     * named web routes and the SPA owns its own routing, so the column holds an
     * opaque token the client maps to its own path. MealHub's equivalent column
     * was `route_name` and its model resolved it through route(); porting that
     * here would resolve every CTA to nothing. Do not reintroduce route()
     * against this column.
     */
    public function up(): void
    {
        Schema::create('nav_menus', function (Blueprint $table) {
            $table->id();
            $table->enum('location', ['navbar', 'footer_menu', 'social', 'legal']);
            $table->string('group_label', 40)->nullable();
            $table->string('label', 60);
            $table->string('icon_class', 60)->nullable();
            $table->string('variant', 20)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('route_key', 100)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The only public query is "everything published, in order" — the
            // composite covers it. group_label is grouped in PHP, never filtered
            // on in SQL, so it earns no index of its own.
            $table->index(['location', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menus');
    }
};
