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
            <th>منبع</th>
            <th>میانگین قیمت</th>
            <th>مجموع تعداد</th>
            <th>زمان به روز رسانی</th>
        </tr>
        </thead>
        <tbody>
        <!-- Digikala Row -->
        <tr>
            <td>{{ $data['digikala']['source'] }}</td>
            <td>{{ number_format($data['digikala']['average']) }} تومان</td>
            <td>{{ $data['digikala']['total'] }}</td>
            <td>{{ \Carbon\Carbon::parse($data['digikala']['updated_at'])->format('Y-m-d H:i:s') }}</td>
        </tr>
        <!-- Torob Row -->
        <tr>
            <td>{{ $data['torob']['source'] }}</td>
            <td>{{ number_format($data['torob']['average']) }} تومان</td>
            <td>{{ $data['torob']['total'] }}</td>
            <td>{{ \Carbon\Carbon::parse($data['torob']['updated_at'])->format('Y-m-d H:i:s') }}</td>
        </tr>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
