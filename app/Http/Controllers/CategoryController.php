<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
    }


    public function store(Request $request)
    {
        // Validate dữ liệu gửi lên
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'thumbnail' => 'nullable|image|max:2048', // Nếu bạn upload ảnh
        ]);

        // Xử lý upload ảnh nếu có
        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('thumbnails', 'public');
            $validated['thumbnail'] = $path;
        }

        // Tạo sản phẩm mới
        $product = Product::create($validated);

        // Redirect hoặc trả về JSON
        return redirect()->route('products.index')->with('success', 'Tạo sản phẩm thành công!');
    }
}


