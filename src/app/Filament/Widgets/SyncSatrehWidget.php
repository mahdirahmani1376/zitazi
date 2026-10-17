<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class SyncSatrehWidget extends Widget
{
    use InteractsWithActions;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.sync-satreh-widget';

    public function toggleSync(): void
    {
        $enabled = ! $this->getSyncEnabled();

        Cache::put('sync_satreh_enabled', $enabled);

        Notification::make()
            ->title($enabled ? 'آپدیت ساتره فعال است' : 'آپدیت ساتره غیر فعال است')
            ->success()
            ->send();
    }

    public function getSyncEnabled(): bool
    {
        return filter_var(
            Cache::get('sync_satreh_enabled', config('services.satreh_sync_enabled')),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function toggleSyncAction(): Action
    {
        return Action::make('toggleSync')
            ->label(fn () => $this->getSyncEnabled()
                ? 'غیر فعال کردن آپدیت ساتره'
                : 'فعال کردن آپدیت ساتره'
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
