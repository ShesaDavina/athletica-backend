<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\UserMembershipController;
use Illuminate\Support\Facades\Route;

// public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// webhook
Route::post('/payment/notification', [PaymentController::class, 'handleNotification'])->name('payment.notification');

// pakai token
Route::middleware(['auth:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // classes
    Route::get('/classes', [ClassController::class, 'index']);
    Route::get('/classes/{id}', [ClassController::class, 'show']);

    // schedules
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{id}', [ScheduleController::class, 'show']);

    // memberships
    Route::get('/memberships', [MembershipController::class, 'index']);
    Route::get('/memberships/{id}', [MembershipController::class, 'show']);
    Route::post('/memberships/buy', [MembershipController::class, 'buy']);
    Route::get('/user/membership', [MembershipController::class, 'myMembership']);

    // bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::get('/bookings/schedule/{schedule_id}/available', [BookingController::class, 'checkAvailability']);

    // payments
    Route::post('/payments/booking/{bookingId}', [PaymentController::class, 'createBookingPayment']);
    Route::post('/payments/membership/{userMembershipId}', [PaymentController::class, 'createMembershipPayment']);
    Route::get('/payments/{paymentId}/status', [PaymentController::class, 'checkStatus']);

    // exports
    Route::get('/export/ticket/{bookingId}', [ExportController::class, 'exportTicketPdf']);
    Route::get('/export/ticket/{bookingId}/preview', [ExportController::class, 'previewTicket']);

    // admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard']);

        // classes
        Route::post('/classes', [ClassController::class, 'store']);
        Route::put('/classes/{id}', [ClassController::class, 'update']);
        Route::delete('/classes/{id}', [ClassController::class, 'destroy']);

        // trainers
        Route::get('/trainers', [TrainerController::class, 'index']);
        Route::post('/trainers', [TrainerController::class, 'store']);
        Route::put('/trainers/{id}', [TrainerController::class, 'update']);
        Route::delete('/trainers/{id}', [TrainerController::class, 'destroy']);

        // memberships
        Route::post('/memberships', [MembershipController::class, 'store']);
        Route::put('/memberships/{id}', [MembershipController::class, 'update']);
        Route::delete('/memberships/{id}', [MembershipController::class, 'destroy']);

        // user memberships
        Route::get('/admin/user-memberships', [UserMembershipController::class, 'index']);
        Route::get('/admin/user-memberships/active', [UserMembershipController::class, 'active']);
        Route::get('/admin/user-memberships/expired', [UserMembershipController::class, 'expired']);
        Route::get('/admin/user-memberships/{id}', [UserMembershipController::class, 'show']);

        // bookings
        Route::get('/admin/bookings', [BookingController::class, 'adminIndex']);
        Route::get('/admin/bookings/{id}', [BookingController::class, 'adminShow']);

        // payments
        Route::get('/admin/payments', [PaymentController::class, 'index']);

        // exports
        Route::get('/export/bookings', [ExportController::class, 'exportBookingsExcel']);
        Route::get('/export/payments', [ExportController::class, 'exportPaymentsExcel']);
    });

    // trainer only
    Route::middleware('role:trainer')->group(function () {
        Route::get('/dashboard/trainer', [DashboardController::class, 'trainerDashboard']);

        // schedules
        Route::get('/trainer/schedules', [ScheduleController::class, 'trainerSchedules']);
        Route::post('/trainer/schedules', [ScheduleController::class, 'store']);
        Route::put('/trainer/schedules/{id}', [ScheduleController::class, 'update']);
        Route::delete('/trainer/schedules/{id}', [ScheduleController::class, 'destroy']);
        Route::get('/trainer/schedules/{id}/participants', [ScheduleController::class, 'participants']);
        Route::get('/trainer/attendances', [ScheduleController::class, 'trainerAttendances']);
        Route::put('/trainer/attendance/{bookingId}', [ScheduleController::class, 'markAttendance']);

        // export
        Route::get('/trainer/export/schedules', [ExportController::class, 'exportTrainerSchedule']);
        Route::get('/trainer/export/attendances', [ExportController::class, 'exportTrainerAttendance']);
    });
});
