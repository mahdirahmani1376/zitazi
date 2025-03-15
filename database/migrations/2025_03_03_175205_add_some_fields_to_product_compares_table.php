<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_compares', function (Blueprint $table) {
            $table->float('zitazi_digi_ratio')->nullable();
            $table->float('zitazi_torob_ratio')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_compares', function (Blueprint $table) {
            //
        });
    }
};
