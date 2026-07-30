<?php

namespace App\Enums;
enum SyncStatusEnum: string
{
    case ENQUEUED = 'enqueued';
    case NOT_ENQUEUED = 'not_enqueued';
    case PROCESSING = 'processing';
    case SUCCESSFUL_DATA_FETCH = 'successful_data_fetch';
    case FAILED_DATA_FETCH = 'failed_data_fetch';
    case SUCCESSFUL_UPDATE = 'successful_update';
    case SKIPPED_UPDATE = 'skipped_update';
    case FAILED_UPDATE = 'failed_update';

    public function getBadgeColorForState(): string
    {
        return match ($this) {
            self::PROCESSING, self::SUCCESSFUL_DATA_FETCH => 'info',
            self::SKIPPED_UPDATE => 'warning',
            self::SUCCESSFUL_UPDATE => 'success',
            self::FAILED_DATA_FETCH, self::FAILED_UPDATE => 'danger',
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
        };
    }
}
