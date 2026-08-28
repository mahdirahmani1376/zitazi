<?php

namespace App\Enums;
enum SyncStatusEnum: string
{
    case ENQUEUED = 'enqueued';
    case NOT_ENQUEUED = 'not_enqueued';
    case PROCESSING = 'processing';
    case SUCCESSFUL_DATA_FETCH = 'successful_data_fetch';
    case FAILED_DATA_FETCH = 'failed_data_fetch';
    case PRODUCT_GOT_DELETED = 'product_is_deleted';
    case SUCCESSFUL_UPDATE = 'successful_update';
    case SKIPPED_UPDATE = 'skipped_update';
    case ENV_DISABLED = 'update is disabled in .env';
    case VARIATION_HAS_PROMOTION_ACTIVE = 'variation on promotion';
    case VARIATION_IS_DELETED = 'variation_is_deleted';
    case NO_ROUTE_FOUND_FOR_VARIATION = 'no-route-found-for-variation';
    case FAILED_UPDATE = 'failed_update';
    case COOLDOWN = 'cooldown';

    public function getBadgeColorForState(): string
    {
        return match ($this) {
            self::PROCESSING, self::SUCCESSFUL_DATA_FETCH => 'info',
            self::SKIPPED_UPDATE, self::ENV_DISABLED, self::VARIATION_HAS_PROMOTION_ACTIVE, self::NO_ROUTE_FOUND_FOR_VARIATION => 'warning',
            self::SUCCESSFUL_UPDATE => 'success',
            self::FAILED_DATA_FETCH, self::FAILED_UPDATE, self::COOLDOWN, self::PRODUCT_GOT_DELETED => 'danger',
            default => 'gray',
        };
    }

    public function progress(): int
    {
        return match ($this) {
            self::ENQUEUED => 10,
            self::PROCESSING, self::FAILED_DATA_FETCH => 40,
            self::SUCCESSFUL_DATA_FETCH, self::FAILED_UPDATE, self::SKIPPED_UPDATE => 70,
            self::SUCCESSFUL_UPDATE => 100,
            self::NOT_ENQUEUED => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ENQUEUED => 'اضافه شده به صف',
            self::PROCESSING => 'در حال پردازش',
            self::SUCCESSFUL_DATA_FETCH => 'اطلاعات با موفقیت دریافت شده',
            self::FAILED_DATA_FETCH => 'دریافت اطلاعات با خطا مواجه شد لاگ ها چک شود',
            self::SUCCESSFUL_UPDATE => 'بروززسانی در سایت مقصد به درستی صورت گرفت',
            self::SKIPPED_UPDATE => 'بروززسانی در سایت مقصد صورت نگرفت',
            self::FAILED_UPDATE => 'بروززسانی در سایت مقصد با خطا مواجه شد',
            self::NOT_ENQUEUED => 'به صف اضافه نشده',
            self::ENV_DISABLED => 'آپدیت در برنامه غیر فعال است',
            self::VARIATION_HAS_PROMOTION_ACTIVE => 'پروموشن روشن است آپدیت صورت نگرفته',
            self::VARIATION_IS_DELETED => 'تنوع پاک شده از ربات',
            self::NO_ROUTE_FOUND_FOR_VARIATION => 'آپدیت انجام نشد! ستون own_id چک شود اگر این محصول تنوع دارد',
            self::COOLDOWN => 'ربات مسدوده شده لطفا صبر کنید',
            self::PRODUCT_GOT_DELETED => 'محصول از سایت اصلی پاک شده لینک چک شود',
        };
    }

    public static function getValues()
    {
        return array_map(function ($item) {
            return $item->value;
        }, self::cases());
    }
}
