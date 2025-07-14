<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtp;
use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use App\Mail\SendOtpResetPasswordMail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng'
            ], 401);
        }

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'client',
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|string|min:6|same:password',
            'otp' => 'required|string|size:6'
        ]);

        // Verify OTP first
        $otpRecord = UserOtp::where('email', $validated['email'])
            ->where('otp', $validated['otp'])
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$otpRecord) {
            return response()->json([
                'message' => 'Mã OTP không đúng hoặc đã hết hạn'
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'client',
        ]);

        // Delete OTP after successful registration
        $otpRecord->delete();

        // Create cart for new user
        Cart::create(['user_id' => $user->id]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
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

    public function logout(Request $request)
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }

    public function user(Request $request)
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'province' => $user->province,
                'district' => $user->district,
                'ward' => $user->ward,
                'address' => $user->address,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender,
                'role' => $user->role ?? 'client',
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:15',
            'province' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other'
        ]);

        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'province' => $request->province,
            'district' => $request->district,
            'ward' => $request->ward,
            'address' => $request->address,
            'birth_date' => $request->birth_date,
            'gender' => $request->gender
        ]);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'province' => $user->province,
                'district' => $user->district,
                'ward' => $user->ward,
                'address' => $user->address,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender,
                'role' => $user->role ?? 'client',
            ]
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng'
            ], 401);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công'
        ]);
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);
        
        $otp = rand(100000, 999999);
        
        UserOtp::updateOrCreate(
            ['email' => $validated['email']],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5)
            ]
        );
        
        try {
            Mail::to($validated['email'])->send(new OtpMail($otp));
            
            return response()->json([
                'message' => 'Đã gửi mã OTP đến email của bạn'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi gửi email: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);
        
        $otpRecord = UserOtp::where('email', $validated['email'])
            ->where('otp', $validated['otp'])
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$otpRecord) {
            return response()->json([
                'message' => 'Mã OTP không đúng hoặc đã hết hạn'
            ], 422);
        }
        
        // Xóa OTP sau khi xác minh thành công
        $otpRecord->delete();
        
        return response()->json([
            'message' => 'Xác minh OTP thành công'
        ]);
    }

    // Gửi OTP cho quên mật khẩu
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Email không tồn tại'], 404);
        }
        
        $otp = rand(100000, 999999);
        
        PasswordReset::updateOrCreate(
            ['email' => $validated['email']],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5)
            ]
        );
        
        try {
            Mail::to($validated['email'])->send(new SendOtpResetPasswordMail($otp));
            
            return response()->json([
                'message' => 'OTP đã được gửi tới email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi gửi email: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Đặt lại mật khẩu với OTP
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'new_password' => 'required|string|min:6',
            'new_password_confirmation' => 'required|string|min:6|same:new_password'
        ]);
        
        $record = PasswordReset::where('email', $validated['email'])
            ->where('otp', $validated['otp'])
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$record) {
            return response()->json([
                'message' => 'OTP không hợp lệ hoặc đã hết hạn'
            ], 400);
        }
        
        $user = User::where('email', $validated['email'])->first();
        $user->password = Hash::make($validated['new_password']);
        $user->save();
        
        // Xóa OTP
        PasswordReset::where('email', $validated['email'])->delete();
        
        return response()->json([
            'message' => 'Đặt lại mật khẩu thành công'
        ]);
    }
}