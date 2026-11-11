<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Minimal Telegram Bot API client used by operational alerts.
 *
 * Mirrors the channel already used by Alertmanager/Grafana so health alerts
 * land in the same place. Failures are logged and swallowed: a broken alert
 * channel must never break the command that is reporting the problem.
 */
class TelegramService
{
    public function send(string $message): bool
    {
        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.chat_id');

        if ($botToken === '' || $chatId === '') {
            Log::warning('telegram alert skipped: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is not configured');

            return false;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (Throwable $e) {
            Log::error('telegram alert failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::error('telegram alert failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Escape user supplied text for Telegram's HTML parse mode.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
