<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    public function index()
    {
        $attributes = Attribute::with('values')->get();
        return response()->json(['data' => $attributes]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100'
        ]);

        $attribute = Attribute::create($validated);
        return response()->json(['data' => $attribute], 201);
    }

    public function show($id)
    {
        $attribute = Attribute::with('values')->findOrFail($id);
        return response()->json(['data' => $attribute]);
    }

    public function update(Request $request, $id)
    {
        $attribute = Attribute::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:100'
        ]);

        $attribute->update($validated);
        return response()->json(['data' => $attribute]);
    }

    public function destroy($id)
    {
        $attribute = Attribute::findOrFail($id);
        $attribute->delete();
        return response()->json(['message' => 'Xóa thuộc tính thành công']);
    }
}
