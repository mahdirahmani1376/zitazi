<?php

namespace App\Console\Commands;

use App\Models\ProductCompare;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ComparePricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:compare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare Products';

    /**
     * Execute the console command.
     */
    public function handle()
    {



    }

    private function compareDigikala()
    {
        $url = 'https://api.digikala.com/v2/product/13287099/';

        $headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3'
        ];
        
        $response = Http::withHeaders($headers)->acceptJson()->get($url)->collect();

        $variants = collect(data_get($response,'data.product.variants'));

        $priceAverage = $variants->pluck('price')->pluck('selling_price')->average();

        return $priceAverage;
    }
}
