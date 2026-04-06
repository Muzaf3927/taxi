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
        Schema::create('driver_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('from_address');
            $table->string('to_address');
            $table->boolean('postman')->default(false);
            $table->date('date');
            $table->time('time');
            $table->decimal('amount', 12, 2);
            $table->integer('seats');
            $table->enum('status', ['active', 'in_progress', 'completed'])->default('active');
            $table->text('comment')->nullable();
            $table->decimal('from_lat', 10, 7)->nullable();
            $table->decimal('from_lng', 10, 7)->nullable();
            $table->decimal('to_lat', 10, 7)->nullable();
            $table->decimal('to_lng', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_trips');
    }
};
