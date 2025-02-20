<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

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

    protected $headers;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3'
        ];

        $sheetUrl = "https://docs.google.com/spreadsheets/d/1acouaqx5INPMNMG8d9-IKEqfbkfQkqf-kAy04eJZeRU/gviz/tq?tqx=out:csv";
        $response = Http::get($sheetUrl);
        $csvData = $response->body();
        $data = $this->parseCsv($csvData);

        foreach($data as $item)
        {
            $this->reportDigikala($item);
            $this->reportTorob($item);
        }
        

        
        
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

    private function reportDigikala($data)
    {
        $url = $data['digi_kala_api_link'];
        $response = Http::acceptJson()->withHeaders($this->headers)->get($url.'&page=1')->collect();
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
    
            $response = $responses->prepend($response);
            $products = $response->pluck('data.products')->collapse()->keyBy('id');
        } else {
            $products = collect(data_get($response,'data.products'))->keyBy('id');
        }
        
        $prices = $products->pluck('default_variant.price.selling_price');

        $average = (int)$prices->average();
        
        $report = Report::query()->create([
            'url' => $url,
            'average' => $average,
            'total' => $products->count(),
            'source' => 'digikala'
        ]);

        dump($report->toArray());

    }

    private function reportTorob($data)
    {
        $url = $data['Torob PLP link'];

        $responses = Http::pool(function (Pool $pool) use ($url) {
            return collect()
                ->range(1, 4)
                ->map(fn ($page) => $pool->get($url . "&page={$page}"));
        });

        $responses = collect($responses)->map(function (Response $response){
            $crawler = new Crawler($response);
            $element = $crawler->filter("script#__NEXT_DATA__")->first();
            if ($element->count() > 0) {
                return collect(json_decode($element->text(),true));
            }
        });

        $products = $responses->pluck('props.pageProps.products')->collapse();

        $average = (int)$products->pluck('price')->average();

        $report = Report::query()->create([
            'url' => $url,
            'average' => $average,
            'total' => $products->count(),
            'source' => 'digikala'
        ]);

        dump($report->toArray());


    }
}
