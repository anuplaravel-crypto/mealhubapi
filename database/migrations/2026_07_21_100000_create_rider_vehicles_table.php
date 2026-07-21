<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One vehicle record per rider, submitted during onboarding and verified
     * by an admin.
     *
     * `is_active` is kept in sync with the rider's `users.status` by the admin
     * approval action — the rider's dashboard stays gated until both agree.
     *
     * The unique index is on [rider_id, registration_number] rather than
     * registration_number alone: the number is nullable, and two riders must
     * not be able to claim the same plate.
     */
    public function up(): void
    {
        Schema::create('rider_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->string('image', 255)->nullable();
            $table->enum('vehicle_type', ['bike', 'car', 'scooter', 'bicycle']);
            $table->string('registration_number', 50)->nullable();
            $table->string('vehicle_color', 30)->nullable();
            $table->string('vehicle_brand', 50)->nullable();
            $table->string('vehicle_model', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['rider_id', 'registration_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_vehicles');
    }
};
