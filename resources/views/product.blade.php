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
    
    <input type="text" id="filename" placeholder="Enter filename (optional)">
    <button id="downloadBtn">Download Excel</button>
    
    <script>
        document.getElementById('downloadBtn').addEventListener('click', function () {
            let filename = document.getElementById('filename').value.trim();
            let url = '/products';
            
            // Initiate the file download
            window.location.href = url;
        });
    </script>
</body>
</html>
