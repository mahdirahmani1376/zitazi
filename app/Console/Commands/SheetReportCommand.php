<?php

namespace App\Console\Commands;

use App\Models\ExternalProduct;
use App\Models\Product;
use App\Models\Report;
use App\Models\SubCategory;
use App\Services\WoocommerceService;
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
    protected $signature = 'app:sheet-report';

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
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ];

        $sheetUrl = 'https://docs.google.com/spreadsheets/d/1acouaqx5INPMNMG8d9-IKEqfbkfQkqf-kAy04eJZeRU/gviz/tq?tqx=out:csv';
        $response = Http::get($sheetUrl);
        $csvData = $response->body();
        $data = parse_csv($csvData);

        foreach ($data as $item) {
            $this->info('report_digi');
            $this->reportDigikala($item);
            $this->info('report torob');
            $this->reportTorob($item);
        }

    }

    private function reportDigikala($data)
    {
        $url = $data['digi_kala_api_link'];
        $response = Http::acceptJson()->withHeaders($this->headers)->get($url.'&page=1')->collect();
        $totalPages = data_get($response, 'data.pager.total_pages');
        $totalPages = $totalPages >= 5 ? 5 : $totalPages;
        $totalItems = data_get($response, 'data.pager.total_items');

        if ($totalPages > 1) {
            $responses = Http::pool(function (Pool $pool) use ($url, $totalPages) {
                return collect()
                    ->range(2, $totalPages)
                    ->map(fn ($page) => $pool->get($url."&page={$page}"));
            });

            $responses = collect($responses)->map(function (Response $response) {
                return $response->collect();
            });

            $response = $responses->prepend($response);
            $products = $response->pluck('data.products')->collapse()->keyBy('id');
        } else {
            $products = collect(data_get($response, 'data.products'))->keyBy('id');
        }

        foreach ($products as $product) {
            $externalProduct = ExternalProduct::query()->updateOrCreate([
                'source_id' => data_get($product, 'id'),
            ],
                [
                    'title' => data_get($product, 'title_fa'),
                    'price' => data_get($product, 'default_variant.price.selling_price') / 10,
                    'category' => data_get($data, 'Category'),
                    'source' => 'digikala',
                ]);

            foreach (range(2,7) as $i)
            {
                $text = "item_category{$i}";
                if (! empty($category = data_get($product,"data_layer.{$text}")))
                {
                    SubCategory::updateOrCreate([
                        'external_product_id' => $externalProduct->id
                    ],[
                        'name' => $category,
                        'parent_category' => data_get($product,'data_layer.category'),
                        'external_product_id' => $externalProduct->id
                    ]);
                }
            }
        }

        $prices = $products->pluck('default_variant.price.selling_price');

        $average = (int) $prices->average();

        $report = Report::query()->create([
            'url' => $url,
            'average' => $average / 10,
            'total' => $products->count(),
            'source' => 'digikala',
            'zitazi_category' => $data['Category']
        ]);

    }

    private function reportTorob($data)
    {
        $url = $data['Torob PLP link'];
        $zitaziCategory = data_get($data, 'Category');

        $responses = Http::pool(function (Pool $pool) use ($url) {
            return collect()
                ->range(1, 4)
                ->map(fn ($page) => $pool->get($url."&page={$page}"));
        });

        $responses = collect($responses)->map(function (Response $response) {
            $crawler = new Crawler($response);
            $element = $crawler->filter('script#__NEXT_DATA__')->first();
            if ($element->count() > 0) {
                return collect(json_decode($element->text(), true));
            }
        });


        $products = $responses->pluck('props.pageProps.products')->collapse();
        $bar = $this->output->createProgressBar($products->count());

        $externalProducts = [];
        foreach ($products as $product) {
            $p = ExternalProduct::query()->updateOrCreate([
                // 'source_id' => data_get($product,'random_key')
                'source_id' => 'https://torob.com'.urldecode(data_get($product, 'web_client_absolute_url')),
            ],
                [
                    'title' => data_get($product, 'name1'),
                    'price' => data_get($product, 'price'),
                    'category' => $zitaziCategory,
                    'source' => 'torob',
                ]);

            $externalProducts[data_get($product,'random_key')]['model'] = $p;
            $externalProducts[data_get($product,'random_key')]['product'] = $product;

        }

        $responseProducts = Http::pool(function (Pool $pool) use ($products) {
            return $products->map(function ($product) use ($pool) {
                $url = data_get($product,'more_info_url');
                try {
                    $response = $pool->get($url);
                    return $response;
                } catch (\Exception $e)
                {
                    dump('error_response_'.$product['more_info_url']);
                }
            });
        });

        $responseProducts = collect($responseProducts)->each(function ($response) use ($bar, $zitaziCategory, $products,$externalProducts) {
            if (! $response instanceof Response)
            {
                $bar->advance();
                return;
            }
            $data = $response->json();
            $randomKey = data_get($data,'random_key');
            /** @var ExternalProduct $externalProduct */
            $externalProduct = $externalProducts[$randomKey]['model'] ?? null;
            if (! $externalProduct)
            {
                $bar->advance();
                return;
            }

            $breadCrumbs = array_slice($data['breadcrumbs'],2);
            $parentCategory = $data['breadcrumbs'][1]['title'];
            foreach ($breadCrumbs as $breadCrumb) {
                SubCategory::updateOrCreate([
                    'external_product_id' => $externalProduct->id
                ],[
                    'name' => $breadCrumb['title'],
                    'parent_category' => $parentCategory,
                ]);
            }

            $bar->advance();
        });

        $bar->finish();


        $average = (int) $products->pluck('price')->average();

        $report = Report::query()->create([
            'url' => $url,
            'average' => $average,
            'total' => $products->count(),
            'source' => 'torob',
            'zitazi_category' => $zitaziCategory
        ]);

    }

    private function reportZitazi($data)
    {
        $woocommerce = WoocommerceService::getClient();
        foreach (Product::all() as $product) {
            $response = $woocommerce->get($product->own_id);
        }
    }
}
