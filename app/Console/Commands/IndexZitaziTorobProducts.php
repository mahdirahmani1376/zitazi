<?php

namespace App\Console\Commands;

use App\Models\TorobProduct;
use Http;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class IndexZitaziTorobProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:index-zitazi-torob-products {--just-click}';

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
        $startTime = microtime(true);

        if (empty($this->option('just-click'))) {
            $params = [
                'shop_id' => '12259',
                'page' => 0,
                'source' => 'next_desktop',
                'rank_offset' => '24',
                '_bt__experiment' => '',
                'suid' => '67d579ef2e39358f2ee17c9e',
            ];

            $response = Http::get('https://api.torob.com/v4/internet-shop/base-product/list/', $params)->json();

            $totalCount = $response['count'];

            $pages = (int) $totalCount / 25;

            $bar = $this->output->createProgressBar($totalCount);

            for ($i = 0; $i <= $pages; $i++) {
                $params = [
                    'shop_id' => '12259',
                    'page' => $i,
                    'source' => 'next_desktop',
                    'rank_offset' => '24',
                    '_bt__experiment' => '',
                    'suid' => '67d579ef2e39358f2ee17c9e',
                ];

                $response = Http::get('https://api.torob.com/v4/internet-shop/base-product/list/', $params)->json();

                $results = $response['results'];

                foreach ($results as $result) {
                    TorobProduct::query()->updateOrCreate([
                        'random_key' => $result['random_key'],
                    ], [
                        'web_client_absolute_url' => 'https://torob.com'.urldecode($result['web_client_absolute_url']),
                        'name1' => $result['name1'],
                        'price' => $result['price'],
                        'stock_status' => $result['stock_status'],
                        'more_info_url' => urldecode($result['more_info_url']),
                    ]);

                    $bar->advance();
                }

            }

            $bar->finish();

            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            Log::info('Finished app:index-zitazi-torob-products at '.Carbon::now()->toDateTimeString().
                '. Duration: '.number_format($duration, 2).' seconds.');

        }

        $torobProducts = TorobProduct::query()->get();

        $bar = $this->output->createProgressBar($torobProducts->count());

        foreach ($torobProducts->chunk(16) as $torobChunk) {
            $responses = Http::pool(function (Pool $pool) use ($torobChunk, $bar) {
                return $torobChunk->map(function (TorobProduct $torobProduct) use ($pool, $bar) {
                    $promise = $pool->get($torobProduct->more_info_url);
                    $response = $promise->wait();
                    $responseData = $response->json();
                    $sellers = data_get($responseData, 'products_info.result');
                    // $clickable = count($sellers) > 1 ? false : true;
                    $clickable = count($sellers) < 3 ? true : false;

                    $rank = 1;
                    foreach ($sellers as $seller) {
                        if ($seller['shop_id'] == 12259) {
                            break;
                        }

                        $rank++;
                    }

                    $torobProduct->update([
                        'clickable' => $clickable,
                        'rank' => $rank,
                    ]);

                    $bar->advance();

                    return $torobProduct;
                });

            });

        }

        $bar->finish();

    }
}
