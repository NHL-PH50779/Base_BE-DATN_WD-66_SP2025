<?php

namespace App\Http\Controllers\Api;

use App\Models\Refund;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RefundController extends Controller
{
    public function index()
    {
        $refunds = Refund::all();
        return response()->json($refunds, 200);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount'   => 'required|numeric|min:0',
            'status'   => 'required|string|max:50',
        ]);

        $refund = Refund::create($data);

        return response()->json($refund, 201);
    }

    
    public function show($id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Refund not found'], 404);
        }

        return response()->json($refund, 200);
    }

    
    public function update(Request $request, $id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Refund not found'], 404);
        }

        $data = $request->validate([
            'order_id' => 'sometimes|exists:orders,id',
            'amount'   => 'sometimes|numeric|min:0',
            'status'   => 'sometimes|string|max:50',
        ]);

        $refund->update($data);

        return response()->json($refund, 200);
    }

    
    public function destroy($id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Refund not found'], 404);
        }

        $refund->delete();

        return response()->json(['message' => 'Refund deleted'], 200);
    }
}

