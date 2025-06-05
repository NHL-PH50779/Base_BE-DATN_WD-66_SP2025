<?php

namespace App\Http\Controllers\Api;

use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ReturnRequestController extends Controller
{
    
    public function index()
    {
        return ReturnRequest::all();
    }

    
    public function store(Request $request)
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'order_id' => 'required|exists:orders,id',
        'reason' => 'required|string',
        'status' => 'required|string',
    ]);

    $returnRequest = ReturnRequest::create($validated);

    return response()->json([
        'message' => 'Return request created successfully',
        'data' => $returnRequest
    ], 201);
}


    
    public function show($id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);
        return $returnRequest;
    }

    
    public function update(Request $request, $id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'order_id' => 'sometimes|exists:orders,id',
            'reason' => 'nullable|string',
            'status' => 'sometimes|string',
        ]);

        $returnRequest->update($data);

        return response()->json($returnRequest, 200);
    }

   
    public function destroy($id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);
        $returnRequest->delete();

        return response()->json(null, 204);
    }
}

