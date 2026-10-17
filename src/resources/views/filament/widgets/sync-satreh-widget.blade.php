<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4 py-2">
            <div class="text-sm text-gray-500">
                {{ $this->getSyncEnabled() ? 'آپدیت ساتره فعال است' : 'آپدیت ساتره غیر فعال است' }}
            </div>
            <div class="text-sm text-gray-500">
                {{ $this->toggleSyncAction() }}
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
