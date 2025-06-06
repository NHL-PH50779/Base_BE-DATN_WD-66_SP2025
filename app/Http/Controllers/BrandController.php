<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        return response()->json(Brand::all());
    }

    public function store(Request $request)
    {
        $brand = Brand::create($request->validate(['name' => 'required']));
        return response()->json($brand, 201);
    }

    public function show($id)
    {
        $brand = Brand::find($id);
        return $brand ? response()->json($brand) : response()->json(['message' => 'Not Found'], 404);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::find($id);
        if (!$brand) return response()->json(['message' => 'Not Found'], 404);

        $brand->update($request->validate(['name' => 'required']));
        return response()->json($brand);
    }

    public function destroy($id)
    {
        $brand = Brand::find($id);
        if (!$brand) return response()->json(['message' => 'Not Found'], 404);

        $brand->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
