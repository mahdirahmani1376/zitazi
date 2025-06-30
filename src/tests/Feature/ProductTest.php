<?php

namespace Tests\Feature;

use App\Actions\SyncProductsAction;
use App\Models\Product;
use Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::set('try_rate', 2400);
        Cache::forget(Product::TOROB_LOCK_FOR_UPDATE);

        Http::fake([
            'https://torob.com/p/420ddacd-40dd-400a-8bde-3e5c555b5614/%D9%84%DA%AF%D9%88-%D8%B3%D8%B1%DB%8C-cherry-blossoms-%DA%A9%D8%AF-40725/'
            => Http::response(File::get(base_path('tests/Data/torob.txt')), 200),
            'https://www.trendyol.com/lego/shpwave-kiraz-cicekleri-insa-edilebilen-cicek-buketi-40725-8-yas-icin-yapim-seti-430-p-796161952?boutiqueId=61&merchantId=234396'
            => Http::response(File::get(base_path('tests/Data/trendyol.txt')), 200),
            'https://api.digikala.com/v2/product/14243079/'
            => Http::response(File::get(base_path('tests/Data/digikala.json')), 200),
        ]);
    }

    public function test_trendyol(): void
    {
        $product = Product::factory()->create([
            'own_id' => 555750,
            'trendyol_source' => 'https://www.trendyol.com/lego/shpwave-kiraz-cicekleri-insa-edilebilen-cicek-buketi-40725-8-yas-icin-yapim-seti-430-p-796161952?boutiqueId=61&merchantId=234396',
            'stock' => null,
            'price' => null,
            'rial_price' => null
        ]);

        app(SyncProductsAction::class)->execute($product);

        $this->assertDatabaseHas('products', [
            "id" => 1,
            'rial_price' => 2950000,
            'stock' => 0
        ]);
    }

    public function test_trendyol_torob_digi(): void
    {
        $product = Product::factory()->create([
            'own_id' => 555750,
            'trendyol_source' => 'https://www.trendyol.com/lego/shpwave-kiraz-cicekleri-insa-edilebilen-cicek-buketi-40725-8-yas-icin-yapim-seti-430-p-796161952?boutiqueId=61&merchantId=234396',
            'torob_source' => 'https://torob.com/p/420ddacd-40dd-400a-8bde-3e5c555b5614/%D9%84%DA%AF%D9%88-%D8%B3%D8%B1%DB%8C-cherry-blossoms-%DA%A9%D8%AF-40725/',
            'digikala_source' => 14243079,
            'stock' => null,
            'price' => null,
            'rial_price' => null
        ]);

        app(SyncProductsAction::class)->execute($product);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'rial_price' => 2950000,
            'stock' => 0
        ]);

        $this->assertDatabaseHas('product_compares', [
            'product_id' => $product->id,
            "zitazi_torob_price_recommend" => 2210000,
            "zitazi_digi_ratio" => 1,
            "zitazi_digikala_price_recommend" => 2338000,
            "digikala_zitazi_price" => 2350000,
            "digikala_min_price" => 2350000,
        ]);
    }

    public function test_elele()
    {
        $product = Product::factory()->create([
            'own_id' => 555750,
            'elele_source' => 'https://www.elelebaby.com/elele-eronafix-360-donebilen-isofixli-oto-koltugu-0-36-kg-siyah-gri'
        ]);

        app(SyncProductsAction::class)->execute($product);

        dump($product->toArray());
    }

    public function test_trendyol_torob_lock()
    {
        Cache::set(Product::TOROB_LOCK_FOR_UPDATE, true);

        $product = Product::factory()->create([
            'own_id' => 555750,
            'trendyol_source' => 'https://www.trendyol.com/lego/shpwave-kiraz-cicekleri-insa-edilebilen-cicek-buketi-40725-8-yas-icin-yapim-seti-430-p-796161952?boutiqueId=61&merchantId=234396',
            'torob_source' => 'https://torob.com/p/420ddacd-40dd-400a-8bde-3e5c555b5614/%D9%84%DA%AF%D9%88-%D8%B3%D8%B1%DB%8C-cherry-blossoms-%DA%A9%D8%AF-40725/',
            'digikala_source' => 14243079,
            'stock' => null,
            'price' => null,
            'rial_price' => null
        ]);

        app(SyncProductsAction::class)->execute($product);

        dump($product->toArray());
    }

    public function test_navasan(): void
    {
        $response = \Illuminate\Support\Facades\Http::acceptJson()->withQueryParameters([
            'api_key' => env('NAVASAN_KEY'),
        ])->get('http://api.navasan.tech/latest')->json();

        dd($response);
    }

}
