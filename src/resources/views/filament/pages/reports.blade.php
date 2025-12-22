@vite(['resources/css/app.css', 'resources/js/app.js'])

<x-filament-panels::page>
    <div class="flex flex-col items-start space-y-3 py-6">

        <x-filament::button
            tag="a"
            href="{{ route('products.download') }}"
            color="success"
            size="md">
            دانلود تمامی محصولات
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('variations.download') }}"
            color="success"
            size="md">
            دانلود تمام تنوع‌های زیتازی
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('satre-variations.download') }}"
            color="success"
            size="md">
            دانلود تمام تنوع‌های ساتره
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('null-variations.download') }}"
            color="success"
            size="md">
            دانلود تنوع‌های لینک نشده
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('sync-logs.download') }}"
            color="success"
            size="md">
            دانلود لاگ‌های برنامه
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('out-of-stock-logs.download') }}"
            color="success"
            size="md">
            گزارش محصولات ناموجود شده
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('products.compare') }}"
            color="success"
            size="md">
            صفحه مقایسه محصولات
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('products.report') }}"
            color="success"
            size="md">
            گزارش کلی میانگین محصولات
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('top-100') }}"
            color="success"
            size="md">
            گزارش پرفروش‌ترین محصولات
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('torob-products.index') }}"
            color="success"
            size="md">
            گزارش محصولات موجود در ترب
        </x-filament::button>

        <x-filament::button
            tag="a"
            href="{{ route('unavailable-variations.download') }}"
            color="success"
            size="md">
            گزارش تنوع‌های وصل نشده
        </x-filament::button>

    </div>
</x-filament-panels::page>
