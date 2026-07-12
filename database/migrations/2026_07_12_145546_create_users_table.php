<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstName', 20);
            $table->string('lastName', 20)->nullable();
            $table->string('email', 50)->unique();
            $table->string('mobile', 20)->nullable();
            $table->string('preferred_language', 20)->nullable();
            $table->string('image', 255)->nullable();
            $table->enum('role', ['admin', 'restaurant', 'customer', 'rider'])->default('customer');
            $table->string('password', 255);
            $table->rememberToken();
            $table->boolean('accept_registration_tnc')->default(0);
            $table->boolean('marketing_consent')->default(0);
            $table->string('otp', 6);
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('status')->default(0);
            $table->boolean('is_email_verified')->default(0);
            $table->string('address1', 255)->nullable();
            $table->string('address2', 255)->nullable();
            $table->string('zip_code', 50)->nullable();
            $table->string('doc_image1', 255)->nullable();
            $table->string('doc_image2', 255)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('county_id')->nullable()->constrained('counties')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
