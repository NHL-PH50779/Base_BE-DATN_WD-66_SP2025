<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Mã OTP đặt lại mật khẩu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: auto;
        }
        .otp {
            font-size: 32px;
            font-weight: bold;
            color: #2d89ef;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 13px;
            color: #888;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Xin chào,</h2>
    <p>Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản của mình.</p>
    <p>Mã xác thực (OTP) của bạn là:</p>
    <div class="otp">{{ $otp }}</div>
    <p>Mã này có hiệu lực trong <strong>10 phút</strong>. Vui lòng không chia sẻ mã này với bất kỳ ai.</p>
    <div class="footer">
        Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.
        <br>Trân trọng,<br><strong>Đội ngũ hỗ trợ</strong>
    </div>
</div>
</body>
</html>
