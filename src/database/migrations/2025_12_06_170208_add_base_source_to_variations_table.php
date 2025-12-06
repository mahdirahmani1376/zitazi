<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('variations', function (Blueprint $table) {
            $table
                ->string('base_source')
                ->default(\App\Models\Product::ZITAZI)
                ->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('variations', function (Blueprint $table) {
            $table->dropColumn('base_source');
        });
    }
};
