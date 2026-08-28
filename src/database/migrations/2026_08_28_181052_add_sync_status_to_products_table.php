<?php

use App\Enums\SyncStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table
                ->enum('sync_status', SyncStatusEnum::getValues())
                ->default(SyncStatusEnum::NOT_ENQUEUED->value);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sync_status');
        });
    }
};
