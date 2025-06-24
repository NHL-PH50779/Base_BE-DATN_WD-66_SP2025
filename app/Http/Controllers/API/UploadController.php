<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UploadController extends Controller
{
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'thumbnail' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'File không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('thumbnail');
            $path = $file->store('products', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'message' => 'Upload thành công',
                'data' => [
                    'url' => $url,
                    'path' => $path
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Upload thất bại',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}