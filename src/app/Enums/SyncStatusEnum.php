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
            self::PROCESSING => 40,
            self::SUCCESSFUL_DATA_FETCH => 70,
            self::SUCCESSFUL_UPDATE,
            self::SKIPPED_UPDATE => 100,
            self::FAILED_DATA_FETCH,
            self::FAILED_UPDATE => 100,
            self::NOT_ENQUEUED => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ENQUEUED => 'Enqueued',
            self::PROCESSING => 'Processing',
            self::SUCCESSFUL_DATA_FETCH => 'Data Fetched',
            self::FAILED_DATA_FETCH => 'Data Fetch Failed',
            self::SUCCESSFUL_UPDATE => 'Successfully Updated',
            self::SKIPPED_UPDATE => 'Update Skipped',
            self::FAILED_UPDATE => 'Update Failed',
            self::NOT_ENQUEUED => 'Not enqueued',
        };
    }
}
