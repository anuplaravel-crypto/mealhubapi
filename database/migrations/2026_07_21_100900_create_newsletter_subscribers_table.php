<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Newsletter signups from the public footer form.
     *
     * **Double opt-in.** A row exists as soon as someone types an address, but
     * it is not a subscriber until `confirmed_at` is set by clicking the link
     * mailed to that address. The reason is the one spam vector a signup box
     * cannot rate-limit away: anyone can type anyone else's email. Rate
     * limiting stops volume, not impersonation — only confirmation does, and
     * mailing an address that never asked for it is the actual harm.
     *
     * `token` is deliberately **persistent and never cleared**. It serves both
     * the confirmation link and, afterwards, the unsubscribe link that every
     * subsequent mailing must carry. Clearing it on confirm would leave
     * confirmed subscribers with no way out, which is exactly backwards.
     *
     * Status is derived rather than stored, so the two timestamps cannot
     * disagree with a status column:
     *   - pending      — confirmed_at null, unsubscribed_at null
     *   - confirmed    — confirmed_at set, unsubscribed_at null
     *   - unsubscribed — unsubscribed_at set (whatever confirmed_at says)
     *
     * No IP or user-agent column. Throttling happens per-IP in the rate limiter
     * without persisting anything, so storing it would be collecting personal
     * data this feature has no use for.
     *
     * `email` is unique and stored lowercase, following the project's casing
     * convention for categorical/lookup values — resubscribing reuses the row
     * rather than creating a second one.
     */
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            // The only list query is "who can we actually mail", i.e. confirmed
            // and not unsubscribed.
            $table->index(['confirmed_at', 'unsubscribed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
