<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

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
        $data = $request->validate([
            'name' => 'required|string|max:255'
        ]);
        $category = Category::create($data);
        return response()->json($category, 201);

    }
}


