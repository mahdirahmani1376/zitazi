<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Reports Decathlon ScrapeOps fallback usage to Telegram.
 *
 * The scraper increments a daily Redis counter for every request it sends
 * through ScrapeOps and attaches the running total to the scrape response.
 * This reporter turns that into a few meaningful messages instead of one per
 * product: the switch to the fallback, a periodic progress line, and the
 * previous day's total.
 *
 * There is deliberately no request cap — the counter is visibility only.
 * Milestones are de-duplicated in the cache so a burst of products cannot
 * flood the channel.
 */
class ScrapeOpsFallbackReporter
{
    /**
     * Emit a progress message every this many fallback requests.
     */
    public const PROGRESS_EVERY = 500;

    public function __construct(
        private readonly TelegramService $telegram,
    ) {}

    /**
     * Report on a scrape response that used the ScrapeOps fallback.
     *
     * @param  array<string, mixed>  $messageArray
     */
    public function report(array $messageArray): void
    {
        if (data_get($messageArray, 'scrapeops_fallback') !== true) {
            return;
        }

        $count = (int) data_get($messageArray, 'scrapeops_requests_today', 0);

        if ($count <= 0) {
            return;
        }

        $productId = (string) data_get($messageArray, 'product_id', '');
        $today = now()->toDateString();

        $this->reportOnce($today, 'first', implode("\n", [
            '<b>Decathlon switched to ScrapeOps fallback</b>',
            'The native browser was blocked, so product pages are being fetched through the proxy.',
            'Product: <code>'.TelegramService::escape($productId).'</code>',
            "Requests today: <b>{$count}</b>",
        ]));

        if ($count % self::PROGRESS_EVERY === 0) {
            $this->reportOnce($today, "progress:{$count}", implode("\n", [
                '<b>ScrapeOps fallback</b>',
                "Requests today: <b>{$count}</b>",
                'Last product: <code>'.TelegramService::escape($productId).'</code>',
            ]));
        }
    }

    /**
     * Send at most one message per milestone per day.
     */
    private function reportOnce(string $today, string $milestone, string $message): void
    {
        $key = "scrapeops:fallback:reported:{$today}:{$milestone}";

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        $this->telegram->send($message);
    }
}
