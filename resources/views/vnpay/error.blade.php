<!DOCTYPE html>
<html>
<head>
    <title>Lỗi thanh toán</title>
    <meta charset="utf-8">
</head>
<body>
    <h2>Lỗi thanh toán!</h2>
    <p>{{ $message }}</p>
    
    <script>
        setTimeout(() => {
            window.close();
        }, 3000);
    </script>
</body>
</html>