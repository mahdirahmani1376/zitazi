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
    @if(session('success'))
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
            <div id="successToast" class="toast align-items-center text-white bg-success border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        {{ session('success') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let toast = new bootstrap.Toast(document.getElementById("successToast"));
                toast.show();
            });
        </script>
    @endif
        @if(session('error'))
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
                <div id="errorToast" class="toast align-items-center text-white bg-danger border-0 show" role="alert"
                     aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        @foreach(session('error') as $error)
                            <div class="toast-body">
                                {{ session('error') }}
                            </div>
                        @endforeach
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                aria-label="Close"></button>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    let toast = new bootstrap.Toast(document.getElementById("errorToast"));
                    toast.show();
                });
            </script>
        @endif

    <!-- Sidebar (Right Menu) -->
    <div class="menu sidebar">
        <h4 class="text-center">داشبرد</h4>
        <a href="{{ route('products.download') }}">دانلود تمامی محصولات</a>
        <a href="{{ route('variations.download') }}">دانلود تنوع دکلتون</a>
        <a href="{{ route('products.update') }}">آپدیت محصولات</a>
        <a href="{{ route('products.compare') }}">صفحه مقایسه محصولات ترب و دیجی کالا</a>
        <a href="{{ route('products.report') }}">گزارش کلی میانگین محصولات</a>
        <a href="{{ route('top-100') }}">گزارش پرفروش ترین محصولات ترب و دیجی کالا</a>
        <a href="{{ route('torob-products.index') }}">گزارش محصولات موجود در ترب</a>
    </div>

    <!-- Form (Left Side) -->
    <div class="form-container">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
