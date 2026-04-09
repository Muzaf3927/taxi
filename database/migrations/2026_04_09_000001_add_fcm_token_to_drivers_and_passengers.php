<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('rating');
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });

        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
