<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class SyncZitaziWidget extends Widget
{
    use InteractsWithActions;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.sync-zitazi-widget';

    public function toggleSync(): void
    {
        $enabled = ! $this->getSyncEnabled();

        Cache::put('sync_zitazi_enabled', $enabled);

        Notification::make()
            ->title($enabled ? 'آپدیت زیتازی فعال است' : 'آپدیت زیتازی غیر فعال است')
            ->success()
            ->send();
    }

    public function getSyncEnabled(): bool
    {
        return filter_var(
            Cache::get('sync_zitazi_enabled', config('services.zitazi_sync_enabled')),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function toggleSyncAction(): Action
    {
        return Action::make('toggleSync')
            ->label(fn () => $this->getSyncEnabled()
                ? 'غیر فعال کردن آپدیت زیتازی'
                : 'فعال کردن آپدیت زیتازی'
            )
            ->icon(fn () => $this->getSyncEnabled()
                ? 'heroicon-o-pause'
                : 'heroicon-o-play'
            )
            ->color(fn () => $this->getSyncEnabled()
                ? 'gray'
                : 'success'
            )
            ->action('toggleSync');
    }
}
