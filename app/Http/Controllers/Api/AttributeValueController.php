<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use Illuminate\Http\Request;

class AttributeValueController extends Controller
{
    public function index()
    {
        $values = AttributeValue::with('attribute')->get();
        return response()->json(['data' => $values]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|exists:attributes,id',
            'value' => 'required|string|max:255'
        ]);

        $value = AttributeValue::create($validated);
        return response()->json(['data' => $value->load('attribute')], 201);
    }

    public function show($id)
    {
        $value = AttributeValue::with('attribute')->findOrFail($id);
        return response()->json(['data' => $value]);
    }

    public function update(Request $request, $id)
    {
        $value = AttributeValue::findOrFail($id);
        
        $validated = $request->validate([
            'attribute_id' => 'required|exists:attributes,id',
            'value' => 'required|string|max:255'
        ]);

        $value->update($validated);
        return response()->json(['data' => $value->load('attribute')]);
    }

    public function destroy($id)
    {
        $value = AttributeValue::findOrFail($id);
        $value->delete();
        return response()->json(['message' => 'Xóa giá trị thuộc tính thành công']);
    }
}
