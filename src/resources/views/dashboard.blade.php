<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Tahoma', sans-serif;
            direction: rtl;
            background-color: #f4f7fc;
            padding: 20px;
        }

        .sidebar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .menu a {
            display: block;
            padding: 10px;
            margin-bottom: 5px;
            text-align: right;
            background: #007bff;
            color: white;
            border-radius: 5px;
            text-decoration: none;
        }

        .menu a:hover {
            background: #0056b3;
        }

        .form-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .container {
            display: flex;
            flex-wrap: wrap;
        }

        .menu {
            width: 30%;
        }

        .form-container {
            width: 65%;
        }
    </style>
</head>
<body>

<div class="container">
    @if(session('success') || session('error'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3"
             style="z-index: 1050; max-height: 300px; overflow-y: auto; width: 350px;">
            {{-- Success Toast --}}
            @if(session('success'))
                <div class="toast align-items-center text-white bg-success border-0 mb-2" role="alert"
                     aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            {{ session('success') }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                aria-label="Close"></button>
                    </div>
                </div>
            @endif

            {{-- Error Toasts --}}
            @if(session('error'))
                @foreach(session('error') as $e)
                    <div class="toast align-items-center text-white bg-danger border-0 mb-2" role="alert"
                         aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                {{ $e['message'] }}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                    aria-label="Close"></button>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                document.querySelectorAll('.toast').forEach(function (toastEl) {
                    new bootstrap.Toast(toastEl).show();
                });
            });
        </script>
    @endif



    <!-- Sidebar (Right Menu) -->
    <div class="menu sidebar">
        <h4 class="text-center">داشبرد</h4>
        <a href="{{ route('products.download') }}">دانلود تمامی محصولات</a>
        <a href="{{ route('variations.download') }}">دانلود تمام تنوع ها</a>
        <a href="{{ route('null-variations.download') }}">دانلود تنوع های لینک نشده</a>
        <a href="{{ route('sync-logs.download') }}">دانلود لاگ های برنامه</a>
        <a href="{{ route('out-of-stock-logs.download') }}">گزارش محصولات ناموجود شده</a>
        <a href="{{ route('products.compare') }}">صفحه مقایسه محصولات ترب و دیجی کالا</a>
        <a href="{{ route('products.report') }}">گزارش کلی میانگین محصولات</a>
        <a href="{{ route('top-100') }}">گزارش پرفروش ترین محصولات ترب و دیجی کالا</a>
        <a href="{{ route('torob-products.index') }}">گزارش محصولات موجود در ترب</a>
        <a href="{{ route('unavailable-variations.download') }}">گزارش تنوع های وصل نشده</a>
    </div>

    <!-- Form (Left Side) -->
    <div class="form-container">
        <div>
            <h4 class="text-center mb-3">آپلود فایل اکسل</h4>
            <form action="{{ route('variations.import') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">انتخاب فایل:</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">آپلود</button>
            </form>
        </div>
        <div>
            <h4 class="text-center mb-3">بروز رسانی محصول</h4>
            <form action="{{ route('product.update') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">شناسه ووکامرس</label>
                    <input type="text" name="own_id" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">آپدیت</button>
            </form>
        </div>
        <div>
            <h4 class="text-center mb-3">باز خوانی محصولات از شیت</h4>
            <form action="{{ route('products.seed') }}" method="post" enctype="multipart/form-data">
                @csrf
                <button type="submit" class="btn btn-success">آپدیت</button>
            </form>
        </div>
        <div class="text-center mb-3">
            <h4> آخرین بروزرسانی نرخ ارز</h4>
            <p>{{  \App\Models\Currency::latest()->first()->updated_at ?? 'نا موجود' }}</p>
            <h4>نرخ لیر</h4>
            <p>{{  \App\Models\Currency::syncTryRate() ?? 'نا موجود' }}</p>
            <h4>نرخ درهم</h4>
            <p>{{  \App\Models\Currency::syncDirhamTryRate() ?? 'نا موجود' }}</p>
            <h4>منبع</h4>
            <a href="https://lake.arzdigital.com/web/api/v1/pub/coins?type=fiat">api ارز دیجیتال</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
