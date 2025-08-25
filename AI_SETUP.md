# 🤖 Hướng dẫn tích hợp AI Chatbot với Gemini

## 📋 Tổng quan
Hệ thống đã tích hợp AI chatbot sử dụng Google Gemini API với các tính năng:
- Trả lời thông minh bằng AI
- Tìm kiếm sản phẩm theo ngữ cảnh
- Fallback logic khi AI không khả dụng
- Cache và tối ưu hiệu suất

## 🔧 Cài đặt

### 1. Lấy API Key từ Google AI Studio
1. Truy cập: https://makersuite.google.com/app/apikey
2. Đăng nhập Google account
3. Tạo API key mới
4. Copy API key

### 2. Cấu hình .env
```env
# Google AI Configuration
GOOGLE_AI_API_KEY=your_real_api_key_here
GOOGLE_AI_MODEL=gemini-pro
AI_CHAT_ENABLED=true
AI_FALLBACK_ENABLED=true
AI_TIMEOUT=15
AI_MAX_TOKENS=500
AI_TEMPERATURE=0.7
```

### 3. Test API
```bash
# Test AI service
POST /api/test/ai
{
  "message": "Tìm laptop gaming dưới 20 triệu"
}

# Test Gemini direct
POST /api/test/gemini-direct
{
  "message": "Hello, how are you?"
}
```

## 🚀 Sử dụng

### API Endpoint chính
```bash
POST /api/ai-chat
Authorization: Bearer {token}
{
  "message": "Tìm iPhone mới nhất"
}
```

### Response format
```json
{
  "message": "AI response text with emojis",
  "products": [...], // Nếu có sản phẩm
  "type": "product_search|general|greeting|error",
  "ai_powered": true|false
}
```

## 💡 Ví dụ câu hỏi

### Tìm kiếm sản phẩm
- "Tìm laptop gaming dưới 20 triệu"
- "iPhone mới nhất giá bao nhiêu?"
- "Laptop Dell cho văn phòng"
- "Điện thoại Samsung cao cấp"

### Câu hỏi chung
- "Xin chào"
- "Cửa hàng bán gì?"
- "Tư vấn mua laptop"

## 🔍 Kiểm tra hoạt động

### 1. Kiểm tra config
```php
// Trong controller hoặc tinker
dd(config('services.google_ai'));
```

### 2. Test fallback
- Để API key rỗng → sẽ dùng logic fallback
- API key sai → sẽ fallback sau timeout

### 3. Log errors
```bash
tail -f storage/logs/laravel.log | grep "AI"
```

## 📁 Cấu trúc code

```
app/
├── Http/Controllers/API/
│   ├── AIChatController.php      # Main controller
│   └── TestAIController.php      # Test endpoints
├── Services/
│   └── AIService.php             # Core AI logic
config/
└── services.php                  # AI configuration
```

## 🛠️ Tùy chỉnh

### Thay đổi prompt
Chỉnh sửa method `buildPrompt()` trong `AIService.php`

### Thêm logic tìm kiếm
Chỉnh sửa method `searchProducts()` và `isProductQuery()`

### Cấu hình AI parameters
Chỉnh sửa trong `.env` hoặc `config/services.php`

## 🚨 Troubleshooting

### Lỗi thường gặp
1. **API key không hợp lệ**: Kiểm tra key trong .env
2. **Timeout**: Tăng AI_TIMEOUT trong .env
3. **Rate limit**: Gemini có giới hạn requests/phút
4. **No products found**: Kiểm tra database có sản phẩm

### Debug
```bash
# Test AI service
curl -X POST http://localhost:8000/api/test/ai \
  -H "Content-Type: application/json" \
  -d '{"message": "xin chào"}'

# Check logs
tail -f storage/logs/laravel.log
```

## 📊 Monitoring

### Metrics cần theo dõi
- Response time AI API
- Fallback rate
- User satisfaction
- Popular queries

### Performance tips
- Cache store context (categories, brands)
- Limit product search results
- Use appropriate timeout values
- Monitor API quota usage

---

✅ **Hệ thống AI đã sẵn sàng!** 
Chỉ cần thay API key thật và test thôi! 🎉