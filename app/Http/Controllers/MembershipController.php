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

        // Cek user sudah punya membership aktif?
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
            // Hitung tanggal expired
            $startDate = now();
            $endDate = now()->addDays($membership->duration_days);

            // remaining class (null = unlimited)
            $remainingClass = $membership->class_limit;

            // Buat user membership dengan status active dulu
            // Tapi nanti di webhook kalau payment gagal, akan dihapus
            $userMembership = UserMembership::create([
                'user_id' => $user->user_id,
                'membership_id' => $membership->membership_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'remaining_class' => $remainingClass,
                'status' => 'active',
            ]);

            // Buat payment record (pending)
            $payment = Payment::create([
                'user_id' => $user->user_id,
                'booking_id' => null,
                'user_membership_id' => $userMembership->user_membership_id,
                'amount' => $membership->price,
                'payment_method' => 'midtrans',
                'status' => 'pending',
                'payment_date' => null,
            ]);

            DB::commit();

            // INTEGRASI MIDTRANS
            $paymentController = app(PaymentController::class);
            $paymentRequest = new Request();
            $paymentResponse = $paymentController->createMembershipPayment($paymentRequest, $userMembership->user_membership_id);

            $responseData = $paymentResponse->getData();

            if ($responseData->success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Silakan lakukan pembayaran untuk mengaktifkan membership',
                    'data' => [
                        'user_membership' => $userMembership->load('membership'),
                        'payment' => $payment,
                        'payment_token' => $responseData->data->token,
                        'payment_url' => $responseData->data->payment_url,
                    ],
                ], 201);
            } else {
                // Kalau gagal generate payment, hapus user membership
                DB::beginTransaction();
                $userMembership->delete();
                $payment->delete();
                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memproses pembayaran: ' . ($responseData->message ?? 'Unknown error'),
                ], 500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membeli membership: ' . $e->getMessage(),
            ], 500);
        }
    }
}
