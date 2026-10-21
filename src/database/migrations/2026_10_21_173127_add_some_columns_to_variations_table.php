<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->string('source_product_id')->nullable();
            $table->string('source_variation_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->dropColumn([
                'source_product_id',
                'source_variation_id'
            ]);
        });
    }
};
