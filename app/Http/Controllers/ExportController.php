<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BookingsExport;
use App\Exports\PaymentsExport;
use App\Exports\TrainerAttendanceExport;
use App\Exports\TrainerScheduleExport;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExportController extends Controller
{
    // ADMIN EXPORTS
    // export bookings ke excel
    public function exportBookingsExcel(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $status = $request->get('status');
        $search = $request->get('search');

        $export = new BookingsExport($startDate, $endDate, $status, $search);

        $filename = 'laporan_booking_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    // export payments ke excel
    public function exportPaymentsExcel(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $status = $request->get('status');

        $export = new PaymentsExport($startDate, $endDate, $status);

        $filename = 'laporan_payment_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    // TRAINER EXPORTS
    // export schedules trainer ke excel
    public function exportTrainerSchedule(Request $request)
    {
        $trainerId = $request->user()->user_id;
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

        $schedules = Schedule::with(['class'])
            ->where('trainer_id', $trainerId)
            ->whereBetween('schedule_date', [$startDate, $endDate])
            ->orderBy('schedule_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($schedule) {
                $bookedCount = $schedule->bookings()
                    ->whereIn('status', ['booked', 'attended'])
                    ->count();

                return [
                    'schedule_date' => $schedule->schedule_date,
                    'class_name' => $schedule->class->class_name,
                    'start_time' => $schedule->formatted_start_time ?? Carbon::parse($schedule->start_time)->format('H:i'),
                    'end_time' => $schedule->formatted_end_time ?? Carbon::parse($schedule->end_time)->format('H:i'),
                    'capacity' => $schedule->class->capacity,
                    'booked_count' => $bookedCount,
                ];
            });

        $export = new TrainerScheduleExport($schedules);
        $filename = 'jadwal_trainer_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    // export kehadiran member
    public function exportTrainerAttendance(Request $request)
    {
        $trainerId = $request->user()->user_id;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = Booking::whereHas('schedule', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        })->with(['user', 'schedule.class']);

        if ($startDate) {
            $query->whereHas('schedule', function ($q) use ($startDate) {
                $q->whereDate('schedule_date', '>=', $startDate);
            });
        }
        if ($endDate) {
            $query->whereHas('schedule', function ($q) use ($endDate) {
                $q->whereDate('schedule_date', '<=', $endDate);
            });
        }

        $attendances = $query->get()->map(function ($booking) {
            return [
                'user_name' => $booking->user->name,
                'user_email' => $booking->user->email,
                'class_name' => $booking->schedule->class->class_name,
                'schedule_date' => $booking->schedule->schedule_date,
                'start_time' => $booking->schedule->formatted_start_time ?? Carbon::parse($booking->schedule->start_time)->format('H:i'),
                'end_time' => $booking->schedule->formatted_end_time ?? Carbon::parse($booking->schedule->end_time)->format('H:i'),
                'booking_type' => $booking->booking_type,
                'status' => $booking->status,
            ];
        });

        $export = new TrainerAttendanceExport($attendances);
        $filename = 'kehadiran_trainer_' . Carbon::now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    // USER EXPORTS (PDF TICKET)
    // export tiket booking ke PDF
    public function exportTicketPdf($bookingId, Request $request)
    {
        $booking = Booking::with(['user', 'schedule.class', 'schedule.trainer', 'payment'])
            ->where('user_id', $request->user()->user_id)
            ->where('booking_id', $bookingId)
            ->firstOrFail();

        // cek apakah booking sudah paid/booked
        if ($booking->booking_type === 'regular') {
            $payment = $booking->payment;
            if (!$payment || $payment->status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tiket hanya bisa didownload setelah pembayaran sukses',
                ], 422);
            }
        }

        $data = [
            'booking' => $booking,
            'class' => $booking->schedule->class,
            'trainer' => $booking->schedule->trainer,
            'user' => $booking->user,
            'schedule' => $booking->schedule,
            'payment' => $booking->payment,
            'date' => Carbon::now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.ticket', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'tiket_booking_' . $booking->booking_id . '.pdf';

        return $pdf->download($filename);
    }

    // preview tiket
    public function previewTicket($bookingId, Request $request)
    {
        $booking = Booking::with(['user', 'schedule.class', 'schedule.trainer', 'payment'])
            ->where('user_id', $request->user()->user_id)
            ->where('booking_id', $bookingId)
            ->firstOrFail();

        $data = [
            'booking' => $booking,
            'class' => $booking->schedule->class,
            'trainer' => $booking->schedule->trainer,
            'user' => $booking->user,
            'schedule' => $booking->schedule,
            'payment' => $booking->payment,
            'date' => Carbon::now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.ticket', $data);

        return $pdf->stream('tiket.pdf');
    }
}
