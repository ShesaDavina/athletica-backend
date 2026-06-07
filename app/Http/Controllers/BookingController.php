<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\UserMembership;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    // lihat semua booking dari semua user (admin only)
    public function adminIndex(Request $request)
    {
        $bookings = Booking::with(['user', 'schedule.class', 'schedule.trainer', 'payment'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    // detail booking tertentu (admin only)
    public function adminShow($id)
    {
        $booking = Booking::with(['user', 'schedule.class', 'schedule.trainer', 'payment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    // lihat booking user yang login
    public function index(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->user_id)
            ->with(['schedule.class', 'schedule.trainer', 'payment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    // detail booking
    public function show($id, Request $request)
    {
        $booking = Booking::with(['schedule.class', 'schedule.trainer', 'payment'])
            ->where('user_id', $request->user()->user_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    // booking kelas
    public function store(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,schedule_id',
            'booking_type' => 'required|in:regular,membership',
        ]);

        $user = $request->user();
        $schedule = Schedule::with('class')->findOrFail($request->schedule_id);

        $existingBooking = Booking::where('user_id', $user->user_id)
            ->where('schedule_id', $request->schedule_id)
            ->whereIn('status', ['booked', 'attended'])
            ->first();

        if ($existingBooking) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah booking jadwal ini',
            ], 422);
        }

        $bookedCount = Booking::where('schedule_id', $request->schedule_id)
            ->where('status', 'booked')
            ->count();

        if ($bookedCount >= $schedule->class->capacity) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas sudah penuh',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // booking pake membership
            if ($request->booking_type === 'membership') {
                $userMembership = UserMembership::where('user_id', $user->user_id)
                    ->where('status', 'active')
                    ->where('end_date', '>=', now())
                    ->first();

                if (!$userMembership) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki membership aktif',
                    ], 422);
                }

                if ($userMembership->remaining_class !== null && $userMembership->remaining_class <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sisa kuota membership Anda habis',
                    ], 422);
                }

                $booking = Booking::create([
                    'user_id' => $user->user_id,
                    'schedule_id' => $request->schedule_id,
                    'booking_type' => 'membership',
                    'status' => 'booked',
                ]);

                if ($userMembership->remaining_class !== null) {
                    $userMembership->decrement('remaining_class');
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Booking berhasil (menggunakan membership)',
                    'data' => $booking->load(['schedule.class', 'schedule.trainer']),
                ], 201);
            }

            $booking = Booking::create([
                'user_id' => $user->user_id,
                'schedule_id' => $request->schedule_id,
                'booking_type' => 'regular',
                'status' => 'booked',
            ]);

            $payment = Payment::create([
                'user_id' => $user->user_id,
                'booking_id' => $booking->booking_id,
                'user_membership_id' => null,
                'amount' => $schedule->class->price,
                'payment_method' => 'midtrans',
                'status' => 'pending',
                'payment_date' => null,
            ]);

            DB::commit();

            // integrasi midtrans
            try {
                $paymentController = new \App\Http\Controllers\PaymentController();
                $paymentResponse = $paymentController->createBookingPayment($request, $booking->booking_id);

                $responseData = $paymentResponse->getData();

                if ($responseData && $responseData->success) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Booking berhasil, silakan lakukan pembayaran',
                        'data' => [
                            'booking' => $booking->load(['schedule.class', 'schedule.trainer']),
                            'payment' => $payment,
                            'payment_token' => $responseData->data->token ?? null,
                            'payment_url' => $responseData->data->payment_url ?? null,
                        ],
                    ], 201);
                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'Booking berhasil, tapi gagal generate payment. Silakan coba lagi nanti.',
                        'data' => [
                            'booking' => $booking->load(['schedule.class', 'schedule.trainer']),
                            'payment' => $payment,
                        ],
                    ], 201);
                }
            } catch (\Exception $e) {
                Log::error('Payment generation error: ' . $e->getMessage());

                return response()->json([
                    'success' => true,
                    'message' => 'Booking berhasil, tapi gagal generate payment. Error: ' . $e->getMessage(),
                    'data' => [
                        'booking' => $booking->load(['schedule.class', 'schedule.trainer']),
                        'payment' => $payment,
                    ],
                ], 201);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat booking: ' . $e->getMessage(),
            ], 500);
        }
    }

    // batal booking
    public function cancel($id, Request $request)
    {
        $booking = Booking::where('user_id', $request->user()->user_id)
            ->findOrFail($id);

        if ($booking->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak dapat dibatalkan',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $booking->status = 'canceled';
            $booking->save();

            // Kalau booking pake membership, kembalikan remaining_class
            if ($booking->booking_type === 'membership') {
                $userMembership = UserMembership::where('user_id', $request->user()->user_id)
                    ->where('status', 'active')
                    ->first();

                if ($userMembership && $userMembership->remaining_class !== null) {
                    $userMembership->increment('remaining_class');
                }
            }

            // kalau booking reguler, update payment status jadi failed
            if ($booking->booking_type === 'regular') {
                $payment = Payment::where('booking_id', $booking->booking_id)->first();
                if ($payment && $payment->status !== 'paid') {
                    $payment->status = 'failed';
                    $payment->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dibatalkan',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan booking: ' . $e->getMessage(),
            ], 500);
        }
    }

    // cek ketersediaan jadwal
    public function checkAvailability($schedule_id)
    {
        $schedule = Schedule::with('class')->findOrFail($schedule_id);

        $bookedCount = Booking::where('schedule_id', $schedule_id)
            ->where('status', 'booked')
            ->count();

        $available = $bookedCount < $schedule->class->capacity;
        $remaining = $schedule->class->capacity - $bookedCount;

        return response()->json([
            'success' => true,
            'data' => [
                'schedule_id' => $schedule_id,
                'capacity' => $schedule->class->capacity,
                'booked_count' => $bookedCount,
                'remaining' => $remaining,
                'available' => $available,
            ],
        ]);
    }
}
