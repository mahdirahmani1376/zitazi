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

        .table {
            width: 100%;
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background-color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        .table td {
            text-align: center;
        }

        .table td, .table th {
            padding: 10px;
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
    <h2 class="text-center mb-4">داده های فروشگاه‌ها</h2>

    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>شناسه محصول</th>
            <th>قیمت دیجی کالا</th>
            <th>قیمت ترب</th>
            <th>قیمت زیتازی</th>
            <th>زمان به روز رسانی</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($data as $productCompare)
        <tr>
            <td>{{ $productCompare->product->own_id }}</td>
            <td class="{{$productCompare->digi_class}}">{{ number_format($productCompare->price_digi) }} تومان</td>
            <td class="{{$productCompare->torob_class}}">{{ number_format($productCompare->price_torob) }} تومان</td>
            <td>{{ number_format($productCompare->product->rial_price) }} تومان</td>
            <td>{{ $productCompare->updated_at }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
