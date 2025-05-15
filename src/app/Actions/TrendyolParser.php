<?php

namespace App\Actions;

use App\Models\Product;
use Symfony\Component\DomCrawler\Crawler;

class TrendyolParser
{
    public function parseResponse($response): array
    {
        $variantsArray = [];
        $crawler = new Crawler($response);
        foreach (range(2, 5) as $i) {
            $allVariantsElement = $crawler->filter("body > script:nth-child($i)")->first();
            if ($allVariantsElement->count() > 0) {
                $pattern = '/"allVariants"\s*:\s*(\[\s*\{.*?\}\s*\])/s';
                preg_match($pattern, $allVariantsElement->text(), $matches);

                if ($matches) {
                    $json = json_decode($matches[1], true);
                    if (!empty($json)) {
                        foreach ($json as $variant) {
                            $variantsArray[] = [
                                'size' => $variant['value'],
                                'item_number' => $variant['itemNumber'],
                                'barcode' => $variant['barcode'] ?? null,
                                'price' => $variant['price'],
                                'stock' => $variant['inStock'] ? 88 : 0,
                                'item_type' => Product::VARIATION_UPDATE
                            ];
                        }
                    }
                    break;
                }
            }
        }

        return $variantsArray;
    }

    public function parseSingleProductResponse($response): array
    {
        $variantsArray = [];
        $crawler = new Crawler($response);

        $schema = $crawler->filter('script[type="application/ld+json"]')->first();
        $schemaJson = json_decode($schema->text(), true);

        foreach (range(2, 5) as $i) {
            $allVariantsElement = $crawler->filter("body > script:nth-child($i)")->first();
            if ($allVariantsElement->count() > 0) {
                $pattern = '/"allVariants"\s*:\s*(\[\s*\{.*?\}\s*\])/s';
                preg_match($pattern, $allVariantsElement->text(), $matches);

                preg_match('/price"\s*:\s*(\{.*?\})/', $allVariantsElement->text(), $priceMatches);
                $defaultPrice = null;
                if (!empty($priceMatches[1])) {
                    $priceJson = json_decode($priceMatches[1] . '}', true);
                    $defaultPrice = data_get($priceJson, 'discountedPrice.value');
                    $defaultPrice = (int)($defaultPrice);
                }

                if ($matches) {
                    $json = json_decode($matches[1], true);
                    if (!empty($json)) {
                        $itemType = count($json) > 1 ? Product::VARIATION_UPDATE : Product::PRODUCT_UPDATE;
                        foreach ($json as $variant) {
                            $variantsArray[] = [
                                'size' => $variant['value'],
                                'item_number' => $variant['itemNumber'],
                                'barcode' => null,
                                'price' => $defaultPrice,
                                'stock' => $variant['inStock'] ? 88 : 0,
                                'item_type' => $itemType
                            ];
                        }
                    }
                    break;
                }
            }
        }

        return [
            'data' => $variantsArray,
            'sku' => $schemaJson['sku'] ?? null,
            'url' => data_get($schemaJson, 'offers.url'),
            'color' => $schemaJson['color'] ?? null,
        ];
    }
}
