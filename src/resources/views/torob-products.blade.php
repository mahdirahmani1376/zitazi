<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Torob products index</title>
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
    <h2 class="text-center mb-4">لیست محصولات موجود در ترب</h2>

    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>شناسه محصول</th>
            <th>نام</th>
            <th>قیمت</th>
            <th>لینک</th>
            <th>شناسه ترب</th>
            <th>رتبه در لیست</th>
            <th>تک سلر</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($data as $torobProduct)
        <tr>
            <td>{{ $torobProduct?->id}}</td>
            <td>{{ $torobProduct->name1 }}</td>
            <td>{{ $torobProduct->price }}</td>
            <td>{{ $torobProduct->web_client_absolute_url }}</td>
            <td>{{ $torobProduct->random_key }}</td>
            <td>{{ $torobProduct->rank }}</td>
            <td>{{ $torobProduct->clickable }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
