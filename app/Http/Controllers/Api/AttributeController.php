<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Attribute;
use Illuminate\Http\Request;

class AttributeController extends Controller
{
    public function index()
{
    return Attribute::all();
}

public function store(Request $request)
{
    $data = $request->validate(['name' => 'required|string|max:255']);
    $attribute = Attribute::create($data);
    return response()->json($attribute, 201);
}

public function update(Request $request, $id)
{
    $attribute = Attribute::findOrFail($id);
    $data = $request->validate(['name' => 'required|string|max:255']);
    $attribute->update($data);
    return response()->json($attribute);
}

public function destroy($id)
{
    $attribute = Attribute::findOrFail($id);
    $attribute->delete();
    return response()->json(['message' => 'Attribute deleted']);
}

}
