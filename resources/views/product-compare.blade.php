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

        .tableFixHead thead th { position: sticky; top: 0; z-index: 1; }
    </style>
</head>
<body>

<div class="container tableFixHead">
    <h2 class="text-center mb-4">داده های فروشگاه‌ها</h2>

    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>شناسه محصول</th>
            <th>قیمت دیجی کالا</th>
            <th>پایین ترین قیمت دیجی کالا</th>
            <th>قیمت پیشنهادی دیجی کالا</th>
            <th>قیمت ترب</th>
            <th>پایین ترین قیمت ترب</th>
            <th>قیمت پیشنهادی ترب</th>
            <th>قیمت در سایت زیتازی</th>
            <th>زمان به روز رسانی</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($data as $productCompare)
        <tr>
            <td>{{ $productCompare->product->own_id }}</td>
            <td style="background-color: {{$productCompare->digi_class}}">
                @if ($productCompare->digikala_zitazi_price)
                {{ number_format($productCompare->digikala_zitazi_price) }} تومان
                @else
                {{ "ناموجود" }}
                @endif
            </td>
            <td>
                @if ($productCompare->digikala_min_price)
                {{ number_format($productCompare->digikala_min_price) }} تومان
                @else
                {{ "ناموجود" }}
                @endif
            </td>
            <td style="background-color : {{ $productCompare->digi_recommend }}">
                @if ($productCompare->digi_recommend)
                {{ number_format($productCompare->zitazi_digikala_price_recommend) }} تومان
                @endif
            </td>
            <td style="background-color : {{$productCompare->torob_class}}">
                @if ($productCompare->zitazi_torob_price)
                {{ number_format($productCompare->zitazi_torob_price) }} تومان
                @else
                {{ "ناموجود" }}
                @endif
            </td>
            <td>
                @if ($productCompare->torob_min_price)
                {{ number_format($productCompare->torob_min_price) }} تومان
                @else
                {{ "ناموجود" }}    
                @endif
            </td>
            <td style="background-color : {{ $productCompare->torob_recommend }}">
                @if ($productCompare->torob_recommend)
                {{ number_format($productCompare->zitazi_torob_price_recommend) }} تومان
                @endif
            </td>
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
