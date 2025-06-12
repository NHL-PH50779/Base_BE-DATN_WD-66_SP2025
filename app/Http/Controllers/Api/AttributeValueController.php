<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use Illuminate\Http\Request;

class AttributeValueController extends Controller
{
    public function index()
{
    return AttributeValue::with('attribute')->get();
}

public function store(Request $request)
{
    $data = $request->validate([
        'attribute_id' => 'required|exists:attributes,id',
        'value' => 'required|string|max:255'
    ]);
    $value = AttributeValue::create($data);
    return response()->json($value, 201);
}

public function update(Request $request, $id)
{
    $value = AttributeValue::findOrFail($id);
    $data = $request->validate([
        'attribute_id' => 'required|exists:attributes,id',
        'value' => 'required|string|max:255'
    ]);
    $value->update($data);
    return response()->json($value);
}

public function destroy($id)
{
    $value = AttributeValue::findOrFail($id);
    $value->delete();
    return response()->json(['message' => 'Attribute value deleted']);
}

}
