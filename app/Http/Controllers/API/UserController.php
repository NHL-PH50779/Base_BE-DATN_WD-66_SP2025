<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        
        // Thêm role cho mỗi user
        $users = $users->map(function ($user) {
            $adminEmails = ['admin@test.com', 'admin@admin.com'];
            $user->role = in_array($user->email, $adminEmails) ? 'admin' : 'client';
            return $user;
        });

        return response()->json([
            'message' => 'Danh sách người dùng',
            'data' => $users
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,client'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        // Thêm role
        $adminEmails = ['admin@test.com', 'admin@admin.com'];
        $user->role = in_array($user->email, $adminEmails) ? 'admin' : 'client';

        return response()->json([
            'message' => 'Thêm người dùng thành công',
            'data' => $user
        ], 201);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        }

        // Thêm role
        $adminEmails = ['admin@test.com', 'admin@admin.com'];
        $user->role = in_array($user->email, $adminEmails) ? 'admin' : 'client';

        return response()->json([
            'message' => 'Chi tiết người dùng',
            'data' => $user
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email
        ];

        if ($request->password) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Thêm role
        $adminEmails = ['admin@test.com', 'admin@admin.com'];
        $user->role = in_array($user->email, $adminEmails) ? 'admin' : 'client';

        return response()->json([
            'message' => 'Cập nhật người dùng thành công',
            'data' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        }

        // Không cho phép xóa admin
        $adminEmails = ['admin@test.com', 'admin@admin.com'];
        if (in_array($user->email, $adminEmails)) {
            return response()->json([
                'message' => 'Không thể xóa tài khoản admin'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'Xóa người dùng thành công'
        ]);
    }
}