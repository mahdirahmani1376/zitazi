<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('trendyol_source', 2048)->nullable()->change();
            $table->string('decathlon_url', 2048)->nullable()->change();
            $table->string('elele_source', 2048)->nullable()->change();
            $table->string('matilda_source', 2048)->nullable()->change();
            $table->string('amazon_source', 2048)->nullable()->change();
        });

        Schema::table('variations', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('trendyol_source')->nullable()->change();
            $table->string('decathlon_url')->nullable()->change();
            $table->string('elele_source')->nullable()->change();
            $table->string('matilda_source')->nullable()->change();
            $table->string('amazon_source')->nullable()->change();
        });

        Schema::table('variations', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
        });
    }
};
