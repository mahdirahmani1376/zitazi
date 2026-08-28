<?php

namespace App\Jobs;

use App\Events\ImportCompleted;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GenerateImportReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $importBatchId,
    )
    {
    }

    public function handle(): void
    {
        $batch = ImportBatch::findOrFail($this->importBatchId);

        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
        ]);

        $sourcePath = Storage::disk('local')->path(
            $batch->source_path
        );

        $spreadsheet = IOFactory::load($sourcePath);

        $sheet = $spreadsheet->getActiveSheet();

        /*
         * WithHeadingRow means row 1 is the header.
         * Add our two result columns.
         */
        $highestColumn = $sheet->getHighestColumn();

        $statusColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $highestColumn
            ) + 1;

        $errorColumn = $statusColumn + 1;

        $sheet->setCellValue(
            [
                $statusColumn,
                1,
            ],
            'import_status'
        );

        $sheet->setCellValue(
            [
                $errorColumn,
                1,
            ],
            'import_error'
        );

        $errors = $batch->errors()
            ->get()
            ->keyBy('row_number');

        $highestRow = $sheet->getHighestRow();

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $error = $errors->get($rowNumber);

            if ($error) {
                $sheet->setCellValue(
                    [$statusColumn, $rowNumber],
                    'failed'
                );

                $sheet->setCellValue(
                    [$errorColumn, $rowNumber],
                    $error->error
                );
            } else {
                $sheet->setCellValue(
                    [$statusColumn, $rowNumber],
                    'success'
                );

                $sheet->setCellValue(
                    [$errorColumn, $rowNumber],
                    ''
                );
            }
        }

        $reportFilename = "import_{$batch->id}_result.xlsx";
        $reportPath = "imports/reports/{$reportFilename}";

        $reportPathComplete = Storage::disk('local')->path(
            $reportPath
        );

        Storage::disk('local')->makeDirectory('imports/reports');

        IOFactory::createWriter(
            $spreadsheet,
            'Xlsx'
        )->save($reportPathComplete);

        $batch->update([
            'status' => 'completed',
            'report_path' => $reportPath,
            'report_filename' => $reportFilename,
            'finished_at' => now(),
        ]);

        ImportCompleted::dispatch($batch->fresh());
    }
}
