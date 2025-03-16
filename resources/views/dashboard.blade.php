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

        .url-link {
            text-decoration: none;
            color: #007bff;
        }

        .url-link:hover {
            text-decoration: underline;
        }


    </style>
</head>
<body>

<div class="container">
<h2 class="text-center mb-4">داشبرد</h2>

<a href="{{ route('products.download') }}" class="btn btn-primary">دانلود تمامی محصولات</a>


<a href="{{ route('variations.download') }}" class="btn btn-primary">دانلود تنوع دکلتون</a>

<a href="{{ route('products.update') }}" class="btn btn-primary">
    آپدیت محصولات
</a>

<a href="{{ route('products.compare') }}" class="btn btn-primary">صفحه مقایسه محصولات ترب و دیجی کالا</a>
<a href="{{ route('products.report') }}" class="btn btn-primary">گزارش کلی میانگین محصولات</a>
<a href="{{ route('top-100') }}" class="btn btn-primary">گزارش پرفروش ترین محصولات ترب و دیجی کالا</a>
<a href="{{ route('torob-products.index') }}" class="btn btn-primary">گزارش محصولات موجود در ترب</a>
{{-- <a href="{{ route('torob-products.download') }}" class="btn btn-primary">
    دانلود گزارش محصولات موجود در ترب
</a> --}}

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
