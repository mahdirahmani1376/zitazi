<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class SyncWidget extends Widget
{
    use InteractsWithActions;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.sync-widget';

    public function toggleSync(): void
    {
        $enabled = !$this->getSyncEnabled();

        Cache::put('sync_enabled', $enabled);

        Notification::make()
            ->title($enabled ? 'ربات فعال است' : 'ربات غیر فعال است')
            ->success()
            ->send();
    }

    public function getSyncEnabled(): bool
    {
        return filter_var(
            Cache::get('sync_enabled', config('sync_enabled')),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function toggleSyncAction(): Action
    {
        return Action::make('toggleSync')
            ->label(fn() => $this->getSyncEnabled()
                ? 'غیر فعال کردن ربات'
                : 'فعال کردن ربات'
            )
            ->icon(fn() => $this->getSyncEnabled()
                ? 'heroicon-o-pause'
                : 'heroicon-o-play'
            )
            ->color(fn() => $this->getSyncEnabled()
                ? 'gray'
                : 'success'
            )
            ->action('toggleSync');
    }
}
