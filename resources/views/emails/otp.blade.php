<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mã OTP</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .otp { background: #FF6B6B; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0; }
        .warning { background: #FFF3CD; border: 1px solid #FFEAA7; padding: 10px; border-radius: 5px; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>TechStore</h1>
        </div>
        <div class="content">
            <p>Xin chào,</p>
            
            <p>Bạn đã yêu cầu mã OTP để xác thực tài khoản. Mã OTP của bạn là:</p>
            
            <div class="otp">{{ $otp }}</div>
            
            <div class="warning">
                <strong>Lưu ý:</strong>
                <ul>
                    <li>Mã OTP sẽ hết hạn sau <strong>5 phút</strong></li>
                    <li>Không chia sẻ mã này với bất kỳ ai</li>
                    <li>Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email</li>
                </ul>
            </div>
            
            <p>Trân trọng,<br>Đội ngũ TechStore</p>
        </div>
    </div>
</body>
</html>