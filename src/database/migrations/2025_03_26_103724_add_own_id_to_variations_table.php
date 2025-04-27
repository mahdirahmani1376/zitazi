<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->unsignedBigInteger('own_id')->unique()->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->dropColumn('own_id');
        });
    }
};
