@vite(['resources/css/app.css', 'resources/js/app.js'])
<x-filament-panels::page>
    <div class="max-w-md mx-auto bg-gray-200 p-6 rounded-lg shadow-md">
        <h4 class="text-center text-lg text-black font-semibold mb-4">آپلود فایل اکسل</h4>

        <form action="{{ route('variations.import') }}" method="post" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="flex flex-col">
                <label for="file" class="mb-2 font-medium text-gray-700">انتخاب فایل:</label>
                <input
                    type="file"
                    name="file"
                    id="file"
                    required
                    class="block w-full text-gray-700 border border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:outline-none"
                >
            </div>

            <button type="submit"
                    class="w-full py-2 px-4 bg-green-600 text-white font-semibold rounded-md hover:bg-green-700 transition-colors">
                آپلود
            </button>
        </form>
    </div>

</x-filament-panels::page>
