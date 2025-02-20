<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class SheetReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sheet-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $sheetUrl = "https://docs.google.com/spreadsheets/d/1acouaqx5INPMNMG8d9-IKEqfbkfQkqf-kAy04eJZeRU/gviz/tq?tqx=out:csv";
        // $response = Http::get($sheetUrl);
        // $csvData = $response->body();
        // $data = $this->parseCsv($csvData);

        $url = 'https://api.digikala.com/v1/categories/stroller-and-carrier/search/?has_selling_stock=1&q=کالسکه&sort=7';
        
        $headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3'
        ];
        
        $response = Http::acceptJson()->withHeaders($headers)->get($url.'&page=1')->collect();
        $totalPages = data_get($response,'data.pager.total_pages');
        $totalPages = $totalPages >= 5 ? 5 : $totalPages;
        $totalItems = data_get($response,'data.pager.total_items');

        if ($totalPages > 1)
        {
            $responses = Http::pool(function (Pool $pool) use ($url, $totalPages) {
                return collect()
                    ->range(2, $totalPages)
                    ->map(fn ($page) => $pool->get($url . "&page={$page}"));
            });
    
            $responses = collect($responses)->map(function (Response $response){
                return $response->collect();
            });
    
            $responses = $responses->prepend($response);
        }

        $products = $responses->pluck('data.products')->collapse()->keyBy('id');
        
        $prices = $products->pluck('default_variant.price.selling_price');

        $average = $prices->average();
        
        dd($average,$totalItems);
        

    }

    private function parseCsv($csvData)
    {
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        $header = array_shift($rows);
        $csv    = array();
        foreach($rows as $row) {
            $csv[] = array_combine($header, $row);
        }

        return $csv;
    }
}
