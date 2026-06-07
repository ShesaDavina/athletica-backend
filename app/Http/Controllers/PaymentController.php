<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\UserMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class PaymentController extends Controller
{
    // get all payments (admin)
    public function index(Request $request)
    {
        $query = Payment::with(['user', 'booking.schedule.class', 'userMembership.membership']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->search) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        $payments->getCollection()->transform(function ($payment) {
            return [
                'payment_id' => $payment->payment_id,
                'user_id' => $payment->user_id,
                'user_name' => $payment->user->name,
                'user_email' => $payment->user->email,
                'type' => $payment->booking_id ? 'booking' : 'membership',
                'item_name' => $payment->booking_id
                    ? ($payment->booking->schedule->class->class_name ?? '-')
                    : ($payment->userMembership->membership->name ?? '-'),
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'payment_date' => $payment->payment_date,
                'created_at' => $payment->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function __construct()
    {
        // setup midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    // create payment transaction for booking (regular class)
    public function createBookingPayment(Request $request, $bookingId)
    {
        Log::info('=== PAYMENT DEBUG START ===');
        Log::info('Booking ID: ' . $bookingId);

        $user = $request->user();
        Log::info('User: ' . ($user ? $user->email : 'null'));

        // Cari booking
        $booking = Booking::with(['schedule.class', 'user'])
            ->where('user_id', $user->user_id)
            ->where('booking_id', $bookingId)
            ->firstOrFail();

        Log::info('Booking found: ' . $booking->booking_id);
        Log::info('Class price: ' . $booking->schedule->class->price);

        // Cek Midtrans config
        Log::info('Midtrans Server Key: ' . (config('midtrans.server_key') ? 'exists' : 'MISSING'));
        Log::info('Midtrans Is Production: ' . (config('midtrans.is_production') ? 'true' : 'false'));

        try {
            // Setup Midtrans configuration
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            Log::info('Midtrans config set');

            // Generate order ID
            $orderId = 'BOOK-' . $bookingId . '-' . time();

            $transactionDetails = [
                'order_id' => $orderId,
                'gross_amount' => (int) $booking->schedule->class->price,
            ];

            $customerDetails = [
                'first_name' => $booking->user->name,
                'email' => $booking->user->email,
            ];

            $params = [
                'transaction_details' => $transactionDetails,
                'customer_details' => $customerDetails,
            ];

            Log::info('Transaction params: ', $params);

            // Get Snap payment page URL
            $snap = \Midtrans\Snap::createTransaction($params);

            Log::info('Midtrans response: ', (array) $snap);

            $paymentToken = $snap->token;
            $paymentUrl = $snap->redirect_url;

            // Update payment record
            $payment = Payment::where('booking_id', $bookingId)->first();
            $payment->update([
                'payment_token' => $paymentToken,
                'payment_url' => $paymentUrl,
                'order_id' => $orderId,
            ]);

            Log::info('Payment updated with token');
            Log::info('=== PAYMENT DEBUG END ===');

            return response()->json([
                'success' => true,
                'message' => 'Payment token generated',
                'data' => [
                    'payment_id' => $payment->payment_id,
                    'token' => $paymentToken,
                    'payment_url' => $paymentUrl,
                    'order_id' => $orderId,
                    'amount' => $payment->amount,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Midtrans Error: ' . $e->getMessage());
            Log::error('Midtrans Error Trace: ' . $e->getTraceAsString());
            Log::info('=== PAYMENT DEBUG END WITH ERROR ===');

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    // create payment for membership purchase
    public function createMembershipPayment(Request $request, $userMembershipId)
    {
        $user = $request->user();

        // cari user memberhsip milik user
        $userMembership = UserMembership::with(['membership', 'user'])
            ->where('user_id', $user->user_id)
            ->where('user_membership_id', $userMembershipId)
            ->firstOrFail();

        // sudah punya payment?
        $existingPayment = Payment::where('user_membership_id', $userMembershipId)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment && $existingPayment->payment_token) {
            return response()->json([
                'success' => true,
                'message' => 'Payment already exists',
                'data' => [
                    'payment_token' => $existingPayment->payment_token,
                    'payment_url' => $existingPayment->payment_url,
                ]
            ]);
        }

        $payment = Payment::where('user_membership_id', $userMembershipId)->first();
        if ($payment && $payment->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Membership sudah dibayar',
            ], 422);
        }

        // Generate order ID
        $orderId = 'MEM-' . $userMembershipId . '-' . time();

        $transactionDetails = [
            'order_id' => $orderId,
            'gross_amount' => (int) $userMembership->membership->price,
        ];

        $customerDetails = [
            'first_name' => $userMembership->user->name,
            'email' => $userMembership->user->email,
        ];

        $params = [
            'transaction_details' => $transactionDetails,
            'customer_details' => $customerDetails,
        ];

        try {
            $snap = Snap::createTransaction($params);
            $paymentToken = $snap->token;
            $paymentUrl = $snap->redirect_url;

            DB::beginTransaction();

            if ($payment) {
                $payment->update([
                    'amount' => $userMembership->membership->price,
                    'payment_method' => 'midtrans',
                    'status' => 'pending',
                    'payment_token' => $paymentToken,
                    'payment_url' => $paymentUrl,
                    'order_id' => $orderId,
                    'payment_date' => null,
                ]);
            } else {
                $payment = Payment::create([
                    'user_id' => $user->user_id,
                    'booking_id' => null,
                    'user_membership_id' => $userMembershipId,
                    'amount' => $userMembership->membership->price,
                    'payment_method' => 'midtrans',
                    'status' => 'pending',
                    'payment_token' => $paymentToken,
                    'payment_url' => $paymentUrl,
                    'order_id' => $orderId,
                    'payment_date' => null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment token generated',
                'data' => [
                    'payment_id' => $payment->payment_id,
                    'token' => $paymentToken,
                    'payment_url' => $paymentUrl,
                    'order_id' => $orderId,
                    'amount' => $payment->amount,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Midtrans Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    // handle payment notif
    public function handleNotification(Request $request)
    {
        try {
            $payload = $request->all();

            Log::info('Midtrans Notification received', ['payload' => $payload]);

            if (!isset($payload['order_id'])) {
                Log::warning('Midtrans Notification: missing order_id', ['payload' => $payload]);
                return response()->json(['status' => 'error', 'message' => 'Missing order_id'], 400);
            }

            $orderId = $payload['order_id'];
            $transactionStatus = $payload['transaction_status'] ?? null;
            $fraudStatus = $payload['fraud_status'] ?? null;

            Log::info('Processing notification', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus
            ]);

            $parts = explode('-', $orderId);

            if (count($parts) < 2) {
                Log::warning('Invalid order_id format', ['order_id' => $orderId]);
                return response()->json(['status' => 'error', 'message' => 'Invalid order_id format'], 400);
            }

            $type = $parts[0]; // BOOK or MEM
            $id = $parts[1];   // booking_id or user_membership_id

            DB::beginTransaction();

            if ($type === 'BOOK') {
                // Payment for booking
                $payment = Payment::where('booking_id', $id)->first();

                if (!$payment) {
                    Log::warning('Payment not found for booking', ['booking_id' => $id]);
                    DB::rollBack();
                    return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
                }

                $booking = Booking::find($id);

                if (!$booking) {
                    Log::warning('Booking not found', ['booking_id' => $id]);
                    DB::rollBack();
                    return response()->json(['status' => 'error', 'message' => 'Booking not found'], 404);
                }

                if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                    if ($fraudStatus == 'accept') {
                        // Payment success
                        $payment->update([
                            'status' => 'paid',
                            'payment_date' => now(),
                        ]);

                        Log::info('Booking payment success', ['booking_id' => $id]);
                    }
                } elseif ($transactionStatus == 'deny' || $transactionStatus == 'cancel' || $transactionStatus == 'expire') {
                    // Payment failed
                    $payment->update(['status' => 'failed']);
                    $booking->update(['status' => 'canceled']);

                    Log::info('Booking payment failed', ['booking_id' => $id]);
                } elseif ($transactionStatus == 'pending') {
                    $payment->update(['status' => 'pending']);
                    Log::info('Booking payment pending', ['booking_id' => $id]);
                }
            } elseif ($type === 'MEM') {
                // Payment for membership
                $payment = Payment::where('user_membership_id', $id)->first();

                if (!$payment) {
                    Log::warning('Payment not found for membership', ['user_membership_id' => $id]);
                    DB::rollBack();
                    return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
                }

                $userMembership = UserMembership::find($id);

                if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                    if ($fraudStatus == 'accept') {
                        // Payment success
                        $payment->update([
                            'status' => 'paid',
                            'payment_date' => now(),
                        ]);

                        Log::info('Membership payment success', ['user_membership_id' => $id]);
                    }
                } elseif ($transactionStatus == 'deny' || $transactionStatus == 'cancel' || $transactionStatus == 'expire') {
                    // Payment failed - hapus user membership
                    $payment->update(['status' => 'failed']);
                    if ($userMembership) {
                        $userMembership->delete();
                    }

                    Log::info('Membership payment failed, user_membership deleted', ['user_membership_id' => $id]);
                } elseif ($transactionStatus == 'pending') {
                    $payment->update(['status' => 'pending']);
                    Log::info('Membership payment pending', ['user_membership_id' => $id]);
                }
            }

            DB::commit();

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Midtrans Webhook Error: ' . $e->getMessage());
            Log::error('Midtrans Webhook Trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // cek payment status
    public function checkStatus($paymentId)
    {
        $payment = Payment::with(['booking', 'userMembership.membership'])
            ->findOrFail($paymentId);

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->payment_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
                'payment_url' => $payment->payment_url,
                'type' => $payment->booking_id ? 'booking' : 'membership',
                'booking' => $payment->booking,
                'membership' => $payment->userMembership,
            ]
        ]);
    }
}
