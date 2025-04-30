<?php

if (!function_exists('parse_csv')) {
    function parse_csv($csvData): array
    {
        $rows = array_map('str_getcsv', explode("\n", $csvData));
        $header = array_shift($rows);
        $csv = [];
        foreach ($rows as $row) {
            $csv[] = array_combine($header, $row);
        }

        return $csv;
    }
}

if (!function_exists('parse_sheet_response')) {
    function parse_sheet_response($data): array
    {
        $header = array_shift($data);
        $length = count($header);
        $csv = [];

        foreach ($data as $row) {
            if (empty($row[0])) {
                continue;
            }

            try {
                $body = array_slice(array_pad($row, $length, ''), 0, $length);
                $data = array_combine($header, $body);

                $csv[] = $data;

            } catch (Throwable $e) {
                dump($e->getMessage(), $header, $body);
                Log::error('read_from_csv_error', [
                    'error' => $e->getMessage(),
                    'data' => [
                        'header' => $header,
                        'row' => $row,
                    ],
                ]);
            }
        }

        return $csv;
    }
}
