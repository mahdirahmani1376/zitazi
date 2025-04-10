<?php

namespace App\Exports;

use App\Models\SyncLog;
use Maatwebsite\Excel\Concerns\FromCollection;

class SyncLogExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return SyncLog::all();
    }
}
