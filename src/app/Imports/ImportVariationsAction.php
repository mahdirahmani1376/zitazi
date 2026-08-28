<?php

namespace App\Imports;

use App\Events\ImportProgressUpdated;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ImportVariationsAction implements
    OnEachRow,
    WithHeadingRow,
    WithChunkReading,
    ShouldQueue,
    SkipsEmptyRows
{
    use Importable;

    public function __construct(
        private readonly int $importBatchId,
    )
    {
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function onRow(Row $row): void
    {
        $batch = ImportBatch::findOrFail(
            $this->importBatchId
        );

        try {
            $this->updateVariationFromRow(
                $row->toArray()
            );

            $batch->increment('successful_rows');

        } catch (\Throwable $e) {

            $batch->increment('failed_rows');

            $batch->errors()->create([
                'row_number' => $row->getIndex(),
                'error' => $e->getMessage(),
            ]);

            Log::error('import-error', [
                'import_batch_id' => $batch->id,
                'row_number' => $row->getIndex(),
                'error' => $e->getMessage(),
            ]);
        }

//        /*
//         * Broadcast every 50 rows.
//         */
//        $processed =
//            $batch->successful_rows
//            + $batch->failed_rows;
//
//        if ($processed % 50 === 0) {
//            $batch->refresh();
//
//            ImportProgressUpdated::dispatch($batch);
//        }

        $batch->refresh();

        ImportProgressUpdated::dispatch($batch);
    }

    public function updateVariationFromRow(array $row): Variation
    {
        $variationId = $row['شناسه تنوع در وب سرویس'] ?? null;

        if (!$variationId) {
            throw new \RuntimeException(
                'شناسه تنوع در وب سرویس وارد نشده است.'
            );
        }

        $baseSource = $row['مرجع'] ?? null;

        if (!$baseSource) {
            throw new \RuntimeException(
                'ستون مرجع وجود ندارد'
            );
        }

        $result = Variation::where('id', $variationId)
            ->where('base_source', $row['مرجع'])
            ->first();

        if (!$result) {
            throw new \RuntimeException(
                "شناسه تنوع در وب سرویس اشتباه است - در ربات موجود نیست این تنوع"
            );
        }

        $itemType = Product::VARIATION_UPDATE;

        if (
            empty($row['شناسه تنوع زیتازی'])
            && empty($result->own_id)
        ) {
            $itemType = Product::PRODUCT_UPDATE;
        }

        $oldOwnId = $result->own_id;
        $oldIsDeleted = $result->is_deleted;
        $oldItemType = $result->item_type;

        $result->update([
            'own_id' => $row['شناسه تنوع زیتازی'],
            'item_type' => $itemType,
            'is_deleted' => $row['غیرفعال'] ?? false,
        ]);

        Log::info('product-import-update', [
            'own_id' => $row['شناسه تنوع زیتازی'],
            'old_own_id' => $oldOwnId,
            'item_type' => $itemType,
            'old_item_type' => $oldItemType,
            'is_deleted' => $row['غیرفعال'],
            'old_is_deleted' => $oldIsDeleted,
        ]);

        return $result;
    }

}
