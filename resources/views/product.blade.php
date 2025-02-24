<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Excel</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body>
    <h1>Download Products Excel</h1>
    
    <button id="downloadBtn">Download Excel</button>
    
    <script>
        document.getElementById('downloadBtn').addEventListener('click', function () {
            let url = '/product-download';
            window.location.href = url;
        });
    </script>
</body>
</html>
