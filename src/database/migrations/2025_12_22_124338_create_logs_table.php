<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('log_models', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('product_id')
                ->index();

            $table
                ->foreignId('variation_id')
                ->nullable()
                ->index();

            $table->string('message');
            $table->json('data');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_models');
    }
};
