<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\UserMembership;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipController extends Controller
{
    // list membership
    public function index()
    {
        $memberships = Membership::all();

        return response()->json([
            'success' => true,
            'data' => $memberships,
        ]);
    }

    // create membership (admin only)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:3',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'class_limit' => 'nullable|integer|min:0',
        ]);

        $membership = Membership::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Paket membership berhasil ditambahkan',
            'data' => $membership,
        ], 201);
    }

    // detail membership
    public function show($id)
    {
        $membership = Membership::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $membership,
        ]);
    }

    // update membership (admin only)
    public function update(Request $request, $id)
    {
        $membership = Membership::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|min:3',
            'price' => 'sometimes|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'class_limit' => 'sometimes|integer|min:0',
        ]);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }
        if ($request->has('price')) {
            $data['price'] = $request->price;
        }
        if ($request->has('duration_days')) {
            $data['duration_days'] = $request->duration_days;
        }
        if ($request->has('class_limit')) {
            $data['class_limit'] = $request->class_limit;
        }

        $membership->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Paket membership berhasil diupdate',
            'data' => $membership,
        ]);
    }

    // delete membership (admin only)
    public function destroy($id)
    {
        $membership = Membership::findOrFail($id);

        $hasActiveUsers = UserMembership::where('membership_id', $id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveUsers) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus paket membership karena masih ada user yang aktif',
            ], 422);
        }

        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paket membership berhasil dihapus',
        ]);
    }

    // PEMBELIAN USER
    // lihat membership user yg login
    public function myMembership(Request $request)
    {
        $userMembership = UserMembership::where('user_id', $request->user()->user_id)
            ->with('membership')
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->first();

        if (!$userMembership) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Anda belum memiliki membership aktif',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $userMembership,
        ]);
    }

    // beli membership (DENGAN PAYMENT)
    public function buy(Request $request)
    {
        $request->validate([
            'membership_id' => 'required|exists:memberships,membership_id'
        ]);

        $membership = Membership::findOrFail($request->membership_id);
        $user = $request->user();

        // Cek membership aktif
        $existingMembership = UserMembership::where('user_id', $user->user_id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki membership aktif. Harap tunggu hingga expired.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $startDate = now();
            $endDate = now()->addDays($membership->duration_days);
            $remainingClass = $membership->class_limit;

            $userMembership = UserMembership::create([
                'user_id' => $user->user_id,
                'membership_id' => $membership->membership_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'remaining_class' => $remainingClass,
                'status' => 'active',
            ]);

            $payment = Payment::create([
                'user_id' => $user->user_id,
                'booking_id' => null,
                'user_membership_id' => $userMembership->user_membership_id,
                'amount' => $membership->price,
                'payment_method' => 'midtrans',
                'status' => 'pending',
                'payment_date' => null,
            ]);

            // Generate payment token langsung (tanpa panggil controller lain)
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $orderId = 'MEM-' . $userMembership->user_membership_id . '-' . time();
            $transactionDetails = [
                'order_id' => $orderId,
                'gross_amount' => (int) $membership->price,
            ];
            $customerDetails = [
                'first_name' => $user->name,
                'email' => $user->email,
            ];
            $params = [
                'transaction_details' => $transactionDetails,
                'customer_details' => $customerDetails,
            ];

            $snap = \Midtrans\Snap::createTransaction($params);
            $paymentToken = $snap->token;
            $paymentUrl = $snap->redirect_url;

            // Update payment dengan token
            $payment->update([
                'payment_token' => $paymentToken,
                'payment_url' => $paymentUrl,
                'order_id' => $orderId,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Silakan lakukan pembayaran untuk mengaktifkan membership',
                'data' => [
                    'user_membership' => $userMembership->load('membership'),
                    'payment' => $payment,
                    'payment_token' => $paymentToken,
                    'payment_url' => $paymentUrl,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membeli membership: ' . $e->getMessage(),
            ], 500);
        }
    }
}
