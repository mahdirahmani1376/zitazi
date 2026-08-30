<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-4 py-2">
            {{ $this->executeTrendyolAction }}
            {{ $this->executeDecathlonAction }}
        </div>
    </x-filament::section>

    <x-filament-actions::modals/>
</x-filament-widgets::widget>
