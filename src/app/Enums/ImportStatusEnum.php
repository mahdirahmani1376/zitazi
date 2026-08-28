<?php

namespace App\Enums;

enum ImportStatusEnum: string
{
    use EnumMethods;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';


    public function getBadgeColorForState(): string
    {
        return match ($this) {
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            default => 'gray',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PROCESSING => 'در حال پردازش',
            self::PENDING => 'منتظر پردازش',
            self::COMPLETED => 'انجام شده',
            self::FAILED => 'خطا رخ داده',
        };
    }
}
