<?php

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('cancel-batch {batch}', function ($batch) {
    \Illuminate\Container\Container::getInstance()->make(\Illuminate\Bus\BatchRepository::class)?->find($batch)?->cancel();
});

Artisan::command('test-arzdigital', function (\App\Actions\HttpService $httpService) {
    Currency::syncTryRate();

    $url = 'https://lake.arzdigital.com/web/api/v1/pub/coins?type=fiat';
    $response = $httpService->sendWithCache('get', $url);
    $response = json_decode($response, true);

    $lyre = collect($response['data'])->keyBy('symbol')->get('TRY')['toman'];
    $dirham = collect($response['data'])->keyBy('symbol')->get('AED')['toman'];
    dump($lyre, $dirham);
});

Artisan::command('batch-update-test', function () {
    $variationsData = [];
    $productSData = [
        'update' => [

        ]
    ];
    $woo = WoocommerceService::getClient();

    $products = Product::query()
        ->withWhereHas('variations', function (Builder|HasMany $query) {
            $query->where(function (Builder $query) {
                $query
                    ->whereNot('own_id', '')
                    ->orWhere('item_type', Product::PRODUCT_UPDATE);
            });
        })
        ->whereNot('promotion', '=', 1)
        ->whereIn('id', [6523, 6525])
        ->get()
        ->each(function (Product $product) use (&$variationsData, &$productSData) {
            $massVariationUpdateData = [
                'product_id' => $product->own_id,
                'data' => [
                    'update' => [

                    ]
                ]
            ];
            foreach ($product->variations as $variation) {
                if ($variation->status == Variation::AVAILABLE) {
                    $dto = ZitaziUpdateDTO::createFromArray([
                        'stock_quantity' => $variation->stock,
                        'price' => $variation->rial_price,
                    ]);
                } else {
                    $dto = ZitaziUpdateDTO::createFromArray([
                        'stock_quantity' => 0,
                    ]);
                }

                $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
                if ($dto->stock_quantity > 0) {
                    $stockStatus = ZitaziUpdateDTO::IN_STOCK;
                }

                $data = $dto->getUpdateBody();

                $data['stock_status'] = $stockStatus;

                if (!empty($dto->price)) {
                    $data['sale_price'] = null;
                    $data['regular_price'] = '' . $dto->price;
                }

                if ($variation->item_type == Product::PRODUCT_UPDATE and empty($variation->own_id)) {
                    $data['id'] = $variation->product->own_id;
                    $productSData['update'][] = $data;
                } elseif (!empty($variation->own_id)) {
                    $data['id'] = $variation->own_id;
                    $massVariationUpdateData['data']['update'][] = $data;
                } else {
                    return;
                }
                $variationsData[] = $massVariationUpdateData;
            }
        });

//    foreach (collect($productSData)->chunk(10) as $chunk) {
//        $response = $woo->post("products/batch",$productSData);
//        Log::info('response batch product',[
//            'response' => $response,
//        ]);
//    }
// 6520

    dd(json_encode($variationsData));
    $unavailableVariations = [];
    foreach ($variationsData as $variation) {
        $url = "products/{$variation['product_id']}/variations/batch";
        $data = $variation['data'];
        dd($data);
        $response = $woo->post($url, $data);
        foreach ($response->update as $responseData) {
            if (!empty($responseData->error)) {
                Log::error('error in zitazi sync', [
                    'response' => json_encode($responseData),
                    'data' => $variation['data'],
                    'url' => $url,
                ]);
//                $unavailableVariations[] = $variation
            } else {
                Log::info(
                    "batch-variation-update",
                    [
                        'body' => $data,
                        'product' => $variation['product_id'],
                        'response' => json_encode($response),
                    ]
                );
            }
        }
//        if ($response->sy)
    }

//    Log::error('WooCommerce error variation', [
//        'code' => $json['code'] ?? 'unknown',
//        'message' => $json['message'] ?? 'No message',
//        'variation_id' => $variation->id,
//        'json' => $json,
//        'body' => $body
//    ]);
//
//    $variation->update([
//        'status' => Variation::UNAVAILABLE_ON_ZITAZI,
//    ]);

});

Artisan::command('sync-zitazi-all {id}', function ($id) {
    $product = Product::firstWhere('own_id', $id);

    foreach ($product->variations as $variation) {
        $updateData = ZitaziUpdateDTO::createFromArray([
            'stock_quantity' => $variation->stock,
            'price' => $variation->rial_price
        ]);

        SyncZitaziJob::dispatchSync($variation, $updateData);
    }


});

Artisan::command('app:clean-deleted-products', function () {
    $deletedProducts = \Illuminate\Support\Facades\Cache::get('deleted-products');
    foreach (Product::whereIn('id', $deletedProducts)->get() as $product) {
        foreach ($product->variations as $variation) {
            $variation->update([
                'stock' => 0
            ]);

            SyncZitaziJob::dispatch($variation, ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => 0
            ]));

            Log::info('variation deleted', [
                'variation_id' => $variation->id,
                'woocommerce_id' => $variation->product->own_id,
                'data' => $variation->toArray()
            ]);

            $variation->delete();
        }

        Log::info('product deleted', [
            'product_id' => $product->id,
            'woocommerce_id' => $product->own_id,
            'data' => $product->toArray()
        ]);

        $product->delete();

    }
});

Artisan::command('hhh', function () {

    $test = '{
  "badges": [],
  "discount_info": [],
  "random_key": "d0bd6d48-e42f-4629-af75-d207c52f6f25",
  "name1": "حلقه بسکتبال تاشو کیپستا مدل B900 NBA",
  "name1_max_lines": 3,
  "name2": "",
  "more_info_url": "https://api.torob.com/v4/base-product/details/?source=&discover_method=direct&_bt__experiment=&suid=&search_id=&cities=&province=&pdp_ordering_algorithm=minimum_extra_amount&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25",
  "web_client_absolute_url": "/p/d0bd6d48-e42f-4629-af75-d207c52f6f25/%D8%AD%D9%84%D9%82%D9%87-%D8%A8%D8%B3%DA%A9%D8%AA%D8%A8%D8%A7%D9%84-%D8%AA%D8%A7%D8%B4%D9%88-%DA%A9%DB%8C%D9%BE%D8%B3%D8%AA%D8%A7-%D9%85%D8%AF%D9%84-b900-nba/",
  "price": 833751000,
  "price_prefix": "cheapest",
  "price_text": "۸۳۳٫۷۵۱٫۰۰۰ تومان",
  "price_text_mode": "active",
  "shop_text": "در زیتازی",
  "stock_status": "",
  "delivery_city_name": "تهران",
  "delivery_city_flag": "offline",
  "is_adv": false,
  "similar_api": "https://api.torob.com/v4/base-product/similar-base-product/?source=&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&limit=24",
  "media_search": "https://api.torob.com/v4/base-product/search-by-image/?source=&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&discover_method=search_image_base_pdp&prev_uid=",
  "card_type": "cover-img",
  "estimated_sell": "",
  "media_urls": [
    {
      "type": "image",
      "url": "https://image.torob.com/base/images/8C/o1/8Co1swEw3lia468j.jpg"
    },
    {
      "type": "image",
      "url": "https://image.torob.com/base/images/qO/fx/qOfxX-VLi9Vd07Vl.jpg"
    },
    {
      "type": "image",
      "url": "https://image.torob.com/base/images/zK/Nf/zKNf9mj9bQZCssZZ.jpg"
    },
    {
      "type": "image",
      "url": "https://image.torob.com/base/images/Ca/jm/CajmfplcO-on-09o.jpg"
    },
    {
      "type": "image",
      "url": "https://image.torob.com/base/images/-P/E2/-PE2GR5uy_wBFkkK.jpg"
    },
    {
      "type": "image",
      "url": "https://image.torob.com/base/images/1u/Sk/1uSkNSLxn13PlE3F.jpg"
    }
  ],
  "image_url": "https://image.torob.com/base/images/8C/o1/8Co1swEw3lia468j.jpg",
  "image_count": 6,
  "direct_cta": null,
  "has_nearby_shop": false,
  "has_wiki": false,
  "is_authentic": false,
  "products_info": {
    "ordering": 1,
    "title": "فروشنده‌ها",
    "tab_title": "خرید اینترنتی",
    "result": [
      {
        "availability": true,
        "name1": "حلقه بسکتبال تاشو کیپستا مدل B900 NBA",
        "name2": "",
        "shop_name": "زیتازی",
        "shop_name2": "تهران",
        "shop_id": 12259,
        "has_public_torob_profile": true,
        "prk": "8b43a69e-260d-40eb-87ac-2a96acd681d4",
        "problem_report_type": "online",
        "page_url": "https://api.torob.com/v4/product-page/redirect/?source=&uid=&discover_method=direct&_bt__experiment=&search_id=&suid=&bvid=01a1308bb6e577f69c8f07cb9d744f78&session_id=awxyqimnckdsbjwkyoyvpkplimezyzuv&device_id=&algorithm=&_bt__pdp_ordering_algorithm=minimum_extra_amount&prk=8b43a69e-260d-40eb-87ac-2a96acd681d4&btn=buy_city&shop_rank=0&user_delivery_city=392&pdp_display=seller_card&view=&app_ver=",
        "is_filtered_by_city": true,
        "is_filtered_by_warranty": false,
        "is_filtered_by_bnpl": false,
        "is_filtered_by_torobpay": false,
        "shop_score_percentile": 0,
        "shop_votes_count": 0,
        "button_text": "خرید اینترنتی",
        "price": 833751000,
        "price_text": "۸۳۳٫۷۵۱٫۰۰۰ تومان",
        "price_text_striked": "",
        "price_text_mode": "active",
        "is_price_unreliable": false,
        "price_string": "۸۳۳٫۷۵۱٫۰۰۰ تومان",
        "shop_score": 5,
        "show_report_button": true,
        "more_info": {
          "payment_on_delivery": null,
          "free_shipping": "امکان ارسال رایگان برای خریدهای بالای ۵٫۰۰۰٫۰۰۰ تومان",
          "same_day_delivery": "امکان تحویل در همان روز برای تهران با هماهنگی",
          "shipping_types": [
            "باربری های درون شهری یا برون شهری",
            "خودروی فروشگاه",
            "شرکتهای پست خصوصی نظیر تیپاکس",
            "پست",
            "پیک"
          ],
          "heavy_items": "برای ارسال کالاهای سنگین تماس بگیرید",
          "same_day_free_shipping": "امکان ارسال رایگان در روش ارسال فوری فراهم نیست",
          "selected_shipping_info": [
            "same_day_delivery"
          ],
          "delivery_info": "پیک (تهران)\nارسال پست"
        },
        "score_info": {
          "score": 4.9,
          "score_text": "★۴.۹ (۵ سال در ترب)",
          "score_color": "#248212",
          "score_background_color": "#e7fae3",
          "complaints_info": {
            "title": "امتیاز فروشگاه ★۴.۹ (۵ سال در ترب)",
            "summary": [
              "حدود ۵۰۰ تا ۱۰۰۰ سفارش در ۹۰ روز اخیر فعالیت در ترب",
              "۲ کاربر از طریق ترب سفارش خود را پیگیری کرده اند."
            ]
          }
        },
        "is_adv": false,
        "last_price_change_date": "۱۷ ساعت پیش",
        "show_purchase_warning": false,
        "installment": null,
        "guarantee_info": {
          "status": "disabled"
        },
        "badges": [],
        "purchasing_terms": null,
        "postage_fee": ""
      }
    ],
    "seed": "1791815825",
    "count": 1,
    "is_visible": true,
    "sellers_url": "https://api.torob.com/v4/base-product/sellers/?source=&discover_method=direct&_bt__experiment=&search_id=&bvid=01a1308bb6e577f69c8f07cb9d744f78&suid=&cities=&province=&pdp_ordering_algorithm=minimum_extra_amount&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&list_type=products_info&seed=1791815825"
  },
  "products_in_store_info": {
    "ordering": 2,
    "title": "فروشگاه‌های حضوری",
    "tab_title": "خرید حضوری",
    "result": [
      {
        "prk": "8b43a69e-260d-40eb-87ac-2a96acd681d4",
        "shop_id": 12259,
        "name1": "خیابان جمهوری، بین ابوریحان...",
        "name2": "",
        "shop_name": "زیتازی",
        "shop_badge": null,
        "shop_name2": "تهران",
        "distance": "",
        "sorting_badge": [],
        "working_hours": {
          "type": "schedule",
          "title": {
            "status": "باز",
            "text": "تا ۱۹:۳۰"
          },
          "schedule": [
            {
              "title": "شنبه",
              "text": "۱۰:۰۰ - ۱۹:۳۰",
              "is_today": false
            },
            {
              "title": "یکشنبه",
              "text": "۱۰:۰۰ - ۱۹:۳۰",
              "is_today": false
            },
            {
              "title": "دوشنبه (امروز)",
              "text": "۱۰:۰۰ - ۱۹:۳۰",
              "is_today": true
            },
            {
              "title": "سه‌شنبه",
              "text": "۱۰:۰۰ - ۱۹:۳۰",
              "is_today": false
            },
            {
              "title": "چهارشنبه",
              "text": "۱۰:۰۰ - ۱۹:۳۰",
              "is_today": false
            },
            {
              "title": "پنج‌شنبه",
              "text": "۱۰:۰۰ - ۱۹:۳۰",
              "is_today": false
            },
            {
              "title": "جمعه",
              "text": "فروشگاه تعطیل است",
              "is_today": false
            }
          ],
          "working_hours_msg": null
        },
        "is_open": true,
        "price": 833751000,
        "price_string": "۸۳۳٫۷۵۱٫۰۰۰ تومان",
        "is_price_unreliable": false,
        "button_text": "اطلاعات تماس",
        "problem_report_type": "offline",
        "last_price_change_date": "۱۷ ساعت پیش",
        "badges": [],
        "purchasing_terms": null,
        "shop_more_info": null,
        "supports_fast_delivery": true,
        "image_info": {
          "thumbnail_image_url": "https://image.torob.com/shops/images/f538a8fa87d1.jpeg_/300x300.webp",
          "shop_images_api_url": "https://api.torob.com/v4/base-product/member/images/?prk=8b43a69e-260d-40eb-87ac-2a96acd681d4&base_rk=d0bd6d48-e42f-4629-af75-d207c52f6f25&shop_id=12259&member_type=product_page",
          "count": 9
        },
        "is_filtered_by_city": true,
        "is_filtered_by_bnpl": false,
        "page_url": "https://api.torob.com/v4/product-in-store/contact-info/?source=&uid=&discover_method=direct&_bt__experiment=&search_id=&suid=&bvid=01a1308bb6e577f69c8f07cb9d744f78&session_id=awxyqimnckdsbjwkyoyvpkplimezyzuv&device_id=&prk=8b43a69e-260d-40eb-87ac-2a96acd681d4&btn=buy&shop_rank=0&ct=1&pdp_display=seller_card&view=&app_ver=",
        "score_info": {
          "score": 5,
          "score_text": "خرید حضوری",
          "score_color": "#333333",
          "score_background_color": "#ecedef",
          "complaints_info": {
            "title": "خرید حضوری",
            "summary": [
              "توصیه ترب خرید حضوری از این فروشگاه است."
            ]
          }
        },
        "address": "خیابان جمهوری، بین ابوریحان...",
        "installment": null
      }
    ],
    "seed": "1791815825",
    "count": 1,
    "is_visible": true,
    "sellers_url": "https://api.torob.com/v4/base-product/sellers/?source=&discover_method=direct&_bt__experiment=&search_id=&cities=&province=&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&list_type=products_in_store_info&seed=1791815825",
    "map_sellers_api_url": "https://api.torob.com/v4/base-product/map/sellers/?prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&source=&discover_method=direct&search_id=&suid=&seed=1791815825&_bt__experiment=",
    "map_sellers_url": "https://torob.com/map-sellers/?prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&source=&discover_method=direct&search_id=&suid=&seed=1791815825&_bt__experiment="
  },
  "products_instore_info": {
    "ordering": 1,
    "title": "فروشندگان سطح شهر",
    "result": [],
    "is_visible": false
  },
  "seller_title": "فروشنده‌ها",
  "filters": {
    "items": [],
    "url": "https://api.torob.com/v4/base-product/details/?source=&discover_method=direct&_bt__experiment=&suid=&search_id=&cities=&province=&pdp_ordering_algorithm=minimum_extra_amount&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&bvid=01a1308bb6e577f69c8f07cb9d744f78&no_log=true&seed=1791815825",
    "interaction_log_url": "https://api.torob.com/v4/base-product/details/interaction-log/?prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&base_id=228055460&source=&bvid=01a1308bb6e577f69c8f07cb9d744f78"
  },
  "min_price": 833751000,
  "max_price": 833751000,
  "variants": [],
  "contents": [],
  "breadcrumbs": [
    {
      "id": 0,
      "title": "ترب",
      "url": "/",
      "cat_id": 0,
      "brand_id": null
    },
    {
      "id": 156,
      "title": "ورزش و تناسب اندام",
      "url": "/browse/156/%D9%88%D8%B1%D8%B2%D8%B4-%D9%88-%D8%AA%D9%86%D8%A7%D8%B3%D8%A8-%D8%A7%D9%86%D8%AF%D8%A7%D9%85-sport-and-body/",
      "cat_id": 156,
      "brand_id": null
    },
    {
      "id": 2622,
      "title": "ورزش‌‌های انفرادی و تیمی",
      "url": "/browse/2622/%D9%88%D8%B1%D8%B2%D8%B4-%D9%87%D8%A7%DB%8C-%D8%A7%D9%86%D9%81%D8%B1%D8%A7%D8%AF%DB%8C-%D9%88-%D8%AA%DB%8C%D9%85%DB%8C/",
      "cat_id": 2622,
      "brand_id": null
    },
    {
      "id": 6923,
      "title": "حلقه و تور بسکتبال",
      "url": "/browse/6923/%D8%AD%D9%84%D9%82%D9%87-%D9%88-%D8%AA%D9%88%D8%B1-%D8%A8%D8%B3%DA%A9%D8%AA%D8%A8%D8%A7%D9%84/",
      "cat_id": 6923,
      "brand_id": null
    }
  ],
  "structural_specs": {
    "source": "",
    "headers": [
      {
        "header": "مشخصات کلی",
        "specs": {
          "سایز": "تک سایز"
        }
      }
    ]
  },
  "key_specs": [],
  "slug_name": "حلقه-بسکتبال-تاشو-کیپستا-مدل-b900-nba",
  "is_confirmed": true,
  "is_accessible": true,
  "availability": true,
  "similar_listing": "https://api.torob.com/v4/base-product/similar-listings/?prk=d0bd6d48-e42f-4629-af75-d207c52f6f25",
  "similar_products": "https://api.torob.com/v4/base-product/similar-base-product/?source=&prk=d0bd6d48-e42f-4629-af75-d207c52f6f25&limit=24",
  "torob_category": 6923,
  "no_index": false,
  "buy_box_price_text": "۸۳۳٫۷۵۱٫۰۰۰ تومان",
  "buy_box_button_text": "خرید از ارزان‌ترین فروشنده",
  "buy_box_button_link": "https://api.torob.com/v4/product-page/redirect/?source=&uid=&discover_method=direct&_bt__experiment=&search_id=&suid=&bvid=01a1308bb6e577f69c8f07cb9d744f78&session_id=awxyqimnckdsbjwkyoyvpkplimezyzuv&device_id=&algorithm=&_bt__pdp_ordering_algorithm=minimum_extra_amount&prk=8b43a69e-260d-40eb-87ac-2a96acd681d4&btn=buy_box&shop_rank=0&user_delivery_city=392&pdp_display=seller_card&view=&app_ver=",
  "buy_box_show_purchase_warning": false,
  "new_buy_box_button_text": "خرید از زیتازی",
  "new_buy_box_button_text2": "۸۳۳٫۷۵۱٫۰۰۰ تومان",
  "new_buy_box_button_badge": "ارزان‌ترین",
  "new_buy_box_button_badge_style": "plain",
  "new_buy_box_button_link": "https://api.torob.com/v4/product-page/redirect/?source=&uid=&discover_method=direct&_bt__experiment=&search_id=&suid=&bvid=01a1308bb6e577f69c8f07cb9d744f78&session_id=awxyqimnckdsbjwkyoyvpkplimezyzuv&device_id=&algorithm=&_bt__pdp_ordering_algorithm=minimum_extra_amount&prk=8b43a69e-260d-40eb-87ac-2a96acd681d4&btn=buy_box&shop_rank=0&user_delivery_city=392&pdp_display=seller_card&view=&app_ver=&buy_box_reason=cheapest",
  "new_buy_box_button_scrol_to_prk": null,
  "new_buy_box_source_prk": "8b43a69e-260d-40eb-87ac-2a96acd681d4",
  "new_buy_box_source_type": "online",
  "new_buy_box_chevron_text": null,
  "new_buy_box_show_purchase_warning": false,
  "new_buy_box_contact_url": null,
  "interview_survey_data": null,
  "image_urls": [
    {
      "source": "",
      "urls": [
        "https://image.torob.com/base/images/qO/fx/qOfxX-VLi9Vd07Vl.jpg",
        "https://image.torob.com/base/images/zK/Nf/zKNf9mj9bQZCssZZ.jpg",
        "https://image.torob.com/base/images/Ca/jm/CajmfplcO-on-09o.jpg",
        "https://image.torob.com/base/images/-P/E2/-PE2GR5uy_wBFkkK.jpg",
        "https://image.torob.com/base/images/1u/Sk/1uSkNSLxn13PlE3F.jpg"
      ]
    }
  ]
}';

    $testC = json_decode($test, true);

    $minShop = collect(data_get($testC, 'products_info.result'))->sortBy('price')->first();

    $minPrice = $minShop['price'];
    $rivalShop = $minShop['shop_id'];

    dd(compact('minPrice', 'rivalShop'));

});
