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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id('booking_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('schedules', 'schedule_id')->onDelete('cascade');
            $table->enum('booking_type', ['membership', 'regular']);
            $table->enum('status', ['booked', 'canceled', 'attended'])->default('booked');
            $table->unique(['user_id', 'schedule_id'], 'unique_booking');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
