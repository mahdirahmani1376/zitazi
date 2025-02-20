<?php

if (! function_exists('read_csv_string')) {
    function read_csv_string($csvFile, $delimiter = ',')
        {
            if (($handle = fopen($csvFile, "r")) !== FALSE) {
                $headers = fgetcsv($handle); // Read the first line as headers
                $data = [];

                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (! empty($row[0]))
                    {
                        $data[] = array_combine($headers, $row); // Combine headers with row values
                    }
                }

                fclose($handle);

                return $data;
            }
        }
};