<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Mail\ForgotPasswordOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $otp = rand(100000, 999999);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'last_otp_sent_at' => now(),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp));

        Cart::create(['user_id' => $user->id]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng xác thực email của bạn.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'client',
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }

        // Nếu chưa xác minh → yêu cầu OTP
        if (!$user->is_verified) {
            if (!$request->otp) {
                return response()->json(['message' => 'Tài khoản chưa xác thực. Nhập mã OTP đã gửi về email.'], 403);
            }

            // ✅ Kiểm tra mã OTP trước
            if ($user->email_otp !== $request->otp) {
                return response()->json(['message' => 'Mã OTP không đúng'], 400);
            }

            // ✅ Kiểm tra hết hạn
            if (!$user->otp_expires_at || $user->otp_expires_at < now()) {
                return response()->json(['message' => 'Mã OTP đã hết hạn'], 400);
            }
            Log::info('✅ OTP verified for user: ' . $user->email);
            // ✅ Xác minh thành công
            $user->update([
                'is_verified' => 1,
                'email_otp' => null,
                 'email_verified_at' => now(),
                'otp_expires_at' => null,
            ]);
        }
        // Tạo token
    $token = $user->createToken('auth_token')->plainTextToken;


        // Danh sách admin
        $adminEmails = ['admin@test.com', 'admin@admin.com'];
        $isAdmin = in_array($user->email, $adminEmails);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $isAdmin ? 'admin' : 'client',
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
    // gửi lại mã OTP
    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) return response()->json(['message' => 'Email không tồn tại'], 404);
        if ($user->is_verified) return response()->json(['message' => 'Email đã xác minh'], 400);

        if ($user->last_otp_sent_at && $user->last_otp_sent_at->diffInSeconds(now()) < 60) {
            $seconds = 60 - $user->last_otp_sent_at->diffInSeconds(now());
            return response()->json(['message' => "Vui lòng chờ $seconds giây trước khi gửi lại OTP"], 429);
        }

        $otp = rand(100000, 999999);
        $user->update([
            'email_otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'last_otp_sent_at' => now(),
        ]);

        Mail::to($user->email)->send(new OtpMail($otp));

        return response()->json(['message' => 'Mã OTP mới đã được gửi']);
    }
        // Gửi OTP về email
    public function requestResetOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email không tồn tại'], 404);
        }

        $otp = rand(100000, 999999);

        $user->update([
            'reset_password_otp' => $otp,
            'reset_password_expires_at' => now()->addMinutes(10),
            'reset_password_verified' => false,
        ]);

        Mail::to($user->email)->send(new ForgotPasswordOtpMail($otp));

        return response()->json(['message' => 'Mã OTP đã được gửi về email']);
    }

    // Xác thực OTP
    public function verifyResetOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !$user->reset_password_otp) {
        return response()->json(['message' => 'Không tìm thấy yêu cầu OTP'], 404);
    }

    if ($user->reset_password_expires_at < now()) {
        return response()->json(['message' => 'Mã OTP đã hết hạn'], 400);
    }

    if ($user->reset_password_otp !== $request->otp) {
        return response()->json(['message' => 'Mã OTP không đúng'], 400);
    }

    // Đánh dấu đã xác thực và reset OTP
    $user->update([
        'reset_password_verified' => true,
        'reset_password_otp' => null,
        'reset_password_expires_at' => null,
    ]);

    return response()->json(['message' => 'Xác thực thành công. Bạn có thể đổi mật khẩu.']);
}

    // Reset mật khẩu
    public function resetPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|min:6|confirmed'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'Tài khoản không tồn tại'], 404);
    }

    if (!$user->reset_password_verified) {
        return response()->json(['message' => 'Bạn chưa xác thực OTP'], 403);
    }

    $user->update([
        'password' => Hash::make($request->password),
        'reset_password_verified' => false,
    ]);

    return response()->json(['message' => 'Đặt lại mật khẩu thành công']);
}


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $adminEmails = ['admin@test.com', 'admin@admin.com'];
        $isAdmin = in_array($user->email, $adminEmails);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $isAdmin ? 'admin' : 'client',
            ]
        ]);
    }
}
