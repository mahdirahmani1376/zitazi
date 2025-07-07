<?php

namespace App\Actions;

use App\Exceptions\UnProcessableResponseException;
use App\Models\Product;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SendHttpRequestAction
{
    public function __invoke($method, $url, $headers = []): Response
    {
        if (empty($headers)) {
            $headers = [
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
            ];
        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)->$method($url);

        return $response;

    }

    public function sendWithCache($method, $url)
    {
        dump($url);
        if (empty($headers)) {
            $headers = [
//                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
//                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:139.0) Gecko/20100101 Firefox/139.0',
//                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
//                'Accept-Language' => 'en-US,en;q=0.5',
//                'Accept-Encoding' => 'gzip, deflate, br, zstd',
//                'Connection' => 'keep-alive',
//                'Cookie' => 'hvtb=1; VisitCount=28; platform=web; OptanonConsent=isGpcEnabled=0^&datestamp=Mon+Jul+07+2025+13%3A51%3A41+GMT%2B0330+(Iran+Standard+Time)^&version=202402.1.0^&browserGpcFlag=0^&isIABGlobal=false^&hosts=^&genVendors=V77%3A0%2CV67%3A0%2CV79%3A0%2CV71%3A0%2CV69%3A0%2CV7%3A0%2CV5%3A0%2CV9%3A0%2CV1%3A0%2CV70%3A0%2CV3%3A0%2CV68%3A0%2CV78%3A0%2CV17%3A0%2CV76%3A0%2CV80%3A0%2CV16%3A0%2CV72%3A0%2CV10%3A0%2CV40%3A0%2C^&consentId=e7dea41d-db05-41f5-a9bd-2f336cd698d3^&interactionCount=1^&isAnonUser=1^&landingPath=NotLandingPage^&groups=C0002%3A1%2CC0004%3A1%2CC0003%3A1%2CC0001%3A1%2CC0007%3A1%2CC0009%3A1%2CC0005%3A0^&geolocation=TR%3B06^&AwaitingReconsent=false; OptanonAlertBoxClosed=2025-03-29T08:11:39.455Z; pid=b3926927-860d-4024-9e93-a3aa6e327ffc; WebAbTesting=A_58-B_95-C_16-D_83-E_31-F_98-G_56-H_24-I_20-J_45-K_42-L_3-M_69-N_42-O_29-P_40-Q_24-R_51-S_77-T_28-U_32-V_71-W_57-X_61-Y_39-Z_42; utmSourceLT30d=direct; utmMediumLT30d=not set; utmCampaignLT30d=not set; _ga=GA1.2.1199406640.1743235904; _ym_uid=1743236097984427198; _ym_d=1743236097; _ga_1=GS2.1.s1749378281$o16$g0$t1749378281$j60$l0$h1777960384; _fbp=fb.1.1743271407484.358562532741639766; _ga_NMNGDGYKS4=GS2.2.s1751826219$o24$g1$t1751828129$j60$l0$h0; _hjSessionUser_3408726=eyJpZCI6ImVkOWRhODNiLTg4YjAtNTk1My1iZGI2LTZiMGRkYjNlZWMwMCIsImNyZWF0ZWQiOjE3NDMyNzE0MTI3NzUsImV4aXN0aW5nIjp0cnVlfQ==; _hjSessionUser_3408421=eyJpZCI6IjJhZGJhMDQwLWU5ODAtNTViZS04NzNmLTAzMTljOTBlNmY1MiIsImNyZWF0ZWQiOjE3NDY3Nzg2OTUzMTcsImV4aXN0aW5nIjpmYWxzZX0=; storefrontId=1; language=tr; countryCode=TR; anonUserId=4b013550-5a96-11f0-8be6-e7a2ed909cc4; _gid=GA1.2.1847693712.1751826219; sid=JZuGuYCMGP; WebAbDecider=ABres_B-ABBMSA_B-ABRRIn_B-ABSCB_B-ABSuggestionHighlight_B-ABBP_B-ABCatTR_B-ABSuggestionTermActive_A-ABAZSmartlisting_62-ABBH2_B-ABMB_B-ABMRF_1-ABARR_B-ABMA_B-ABSP_B-ABPastSearches_B-ABSuggestionJFYProducts_B-ABSuggestionQF_B-ABBadgeBoost_A-ABFilterRelevancy_1-ABSuggestionBadges_B-ABProductGroupTopPerformer_B-ABOpenFilterToggle_2-ABRR_2-ABBS_2-ABSuggestionPopularCTR_B; forceUpdateAbDecider=forced; WebRecoAbDecider=ABcrossRecoVersion_1-ABcrossRecoAdsVersion_1-ABsimilarRecoVersion_1-ABcrossSameBrandVersion_1-ABcompleteTheLookVersion_1-ABattributeRecoVersion_1-ABbasketRecoVersion_1-ABcollectionRecoVersion_1-ABsimilarRecoAdsVersion_1-ABsimilarSameBrandVersion_1-ABpdpGatewayVersion_1-ABallInOneRecoVersion_1-ABshopTheLookVersion_1; __cf_bm=r0.w82uRMAsSwrUmKHCBjdH8qVmOQ_pio9JLMdBsKFI-1751883057-1.0.1.1-F6cv8mymWazYD4lX4CkiOODBLBwaC73mcqwZBojKPLpSxawAZob9YbmxGpBsfxCY4xb1aSb57qlti73fyapvLbsiE4m1VgLLoFe_niJ.h4I; __cflb=0H28vSBxxmVRpbspyL7N4hbiBY4yBgWd35dx6MA1atD; _cfuvid=PjzPM43s1gtKe6y527jyICSiGumYUFIqech4E9NsiIs-1751883057209-0.0.1.1-604800000; UserInfo=%7B%22Gender%22%3Anull%2C%22UserTypeStatus%22%3Anull%2C%22ForceSet%22%3Afalse%7D; AbTesting=SFWBFP_B-SFWDBSR_A-SFWDQL_B-SFWDRS_A-SFWDSAOFv2_B-SFWDSFAG_B-SFWDTKV2_A-SFWPSCB_B-SSTPRFL_B-STSBuynow_B-STSCouponV2_A-STSImageSocialProof_A-STSRecoRR_B-STSRecoSocialProof_A-WCOnePageCheckoutv3_A-WebAATestPidStRndGulf_B%7C1751885470%7Cb3926927-860d-4024-9e93-a3aa6e327ffc; msearchAb=ABAdvertSlotPeriod_1-ABAD_B-ABQR_B-ABqrw_b-ABSimD_B-ABBSA_D-ABSuggestionLC_B; homepageAb=homepage%3AadWidgetSorting_V1_1-componentSMHPLiveWidgetFix_V3_2-firstComponent_V3_2-sorter_V4_b-performanceSorting_V1_3-topWidgets_V1_1%2CnavigationSection%3Asection_V1_1%2CnavigationSideMenu%3AsideMenu_V1_1',
//                'Upgrade-Insecure-Requests' => '1',
//                'Sec-Fetch-Dest' => 'document',
//                'Sec-Fetch-Mode' => 'navigate',
//                'Sec-Fetch-Site' => 'none',
//                'Sec-Fetch-User' => '?1',
//                'Priority' => 'u=0, i',
//                'Pragma' => 'no-cache',
//                'Cache-Control' => 'no-cache'
            ];
        }

        $urlMd5 = md5($url);

        if ($response = Cache::get($urlMd5)) {
//            return $response;
        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)->$method($url);
        Cache::put($urlMd5, $response->body(), now()->addDay());

        return $response->body();
    }

    public function sendTorobRequest(Product $product)
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15',
            'Mozilla/5.0 (Linux; Android 11; SM-A505F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36',
        ];

        $headers = [
            'User-Agent' => $agents[array_rand($agents)],
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Connection' => 'keep-alive',
            'Referer' => 'https://torob.com/', // if you're scraping internal links,
            'Accept-Encoding' => 'gzip, deflate, br',
        ];

        $cacheKey = $product->torob_id;

        if ($response = Cache::get($cacheKey)) {
            return $response;
        }

        /** @var Response $response */
        $url = env('CRAWLER_BASE_URL');
        $body = [
            'url' => $product->torob_id
        ];

        $response = Http::withHeaders($headers)->post($url, $body);
        if ($response->status() === \Symfony\Component\HttpFoundation\Response::HTTP_OK) {
            Cache::put($cacheKey, $response->json(), now()->addDay());
        } else {
            throw UnProcessableResponseException::make("torob-ban");
        }

        return $response->json();
    }

    public function sendAmazonRequest($url)
    {
        if (empty($headers)) {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:138.0) Gecko/20100101 Firefox/138.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'DNT' => '1',
                'Sec-GPC' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Priority' => 'u=0, i',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ];
        }

        $urlMd5 = md5($url);

//        if ($response = Cache::get($urlMd5)) {
//            return $response;
//        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)->get($url);
        Cache::put($urlMd5, $response->body(), now()->addDay());

        return $response->body();
    }
}
