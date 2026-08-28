<?php

use App\Enums\ImportStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('source_path');
            $table->string('report_filename')->nullable();
            $table->string('report_path')->nullable();

            $table->foreignId('user_id');

            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('total_rows')->default(0);

            $table->enum('status', ImportStatusEnum::getValues())->default(ImportStatusEnum::PENDING);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
