<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_compares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->integer('digikala_zitazi_price')->nullable();
            $table->integer('digikala_min_price')->nullable();
            $table->integer('torob_min_price')->nullable();
            $table->integer('zitazi_torob_price')->nullable();
            $table->integer('zitazi_torob_price_recommend')->nullable();
            $table->integer('zitazi_digikala_price_recommend')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_compares');
    }
};
