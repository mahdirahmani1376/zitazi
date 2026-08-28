<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_batch_id')
                ->references('id')
                ->on('import_batches')
                ->cascadeOnDelete();

            $table->unsignedInteger('row_number');

            $table->text('error');

            $table->index([
                'import_batch_id',
                'row_number',
            ]);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
