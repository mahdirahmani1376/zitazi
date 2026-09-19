<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4 py-2">
            <div>
                {{ $this->refreshAction }}
            </div>
        </div>
    </x-filament::section>

    <x-filament-actions::modals/>
</x-filament-widgets::widget>
