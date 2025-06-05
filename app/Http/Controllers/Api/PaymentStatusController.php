<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentStatus;

class PaymentStatusController extends Controller
{
    public function index()
    {
        return response()->json(PaymentStatus::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $paymentStatus = PaymentStatus::create($validated);

        return response()->json($paymentStatus, 201);
    }

    public function show($id)
    {
        $paymentStatus = PaymentStatus::findOrFail($id);
        return response()->json($paymentStatus);
    }

    public function update(Request $request, $id)
    {
        $paymentStatus = PaymentStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $paymentStatus->update($validated);

        return response()->json($paymentStatus);
    }

    public function destroy($id)
{
    $paymentStatus = PaymentStatus::findOrFail($id);
    $paymentStatus->delete();

    return response()->json([
        'message' => 'Trạng thái thanh toán đã được xóa thành công.'
    ], 200);
}

}
