<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    // list schedules
    public function index(Request $request)
    {
        $query = Schedule::with(['trainer', 'class'])
            ->withCount(['bookings as bookings_count' => function ($query) {
                $query->whereIn('status', ['booked', 'attended']);
            }]);

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('date')) {
            $query->where('schedule_date', $request->date);
        }

        if ($request->user() && $request->user()->role === 'trainer') {
            $query->where('trainer_id', $request->user()->user_id);
        }

        $schedules = $query->orderBy('schedule_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    // detail schedule
    public function show($id)
    {
        $schedule = Schedule::with(['trainer', 'class', 'bookings.user'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $schedule]);
    }

    // create schedule (trainer)
    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,class_id',
            'schedule_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        // cek tdk ada double jadwal di kelas yg sama
        $exists = Schedule::where('class_id', $request->class_id)
            ->where('schedule_date', $request->schedule_date)
            ->where('start_time', $request->start_time)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal sudah ada untuk kelas ini di tanggal dan jam yang sama',
            ], 422);
        }

        $schedule = Schedule::create([
            'trainer_id' => $request->user()->user_id,
            'class_id' => $request->class_id,
            'schedule_date' => $request->schedule_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dibuat',
            'data' => $schedule->load('trainer', 'class'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);

            // permission check
            if ($request->user()->role !== 'admin' && $schedule->trainer_id !== $request->user()->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $request->validate([
                'schedule_date' => 'sometimes|date|after_or_equal:today',
                'start_time' => 'sometimes',
                'end_time' => 'sometimes|after:start_time',
            ]);

            $data = [];

            if ($request->has('schedule_date')) {
                $data['schedule_date'] = $request->schedule_date;
            }
            if ($request->has('start_time')) {
                $data['start_time'] = $request->start_time;
            }
            if ($request->has('end_time')) {
                $data['end_time'] = $request->end_time;
            }

            $schedule->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil diupdate',
                'data' => $schedule->fresh()->load('trainer', 'class'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Schedule $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        // Cek permission
        if ($request->user()->role !== 'admin' && $schedule->trainer_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Cek apakah ada booking di jadwal ini
        if ($schedule->bookings()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus jadwal karena sudah ada booking',
            ], 422);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dihapus',
        ]);
    }

    // get 5 schedules mendatang
    public function upcoming(Request $request)
    {
        $trainerId = $request->user()->user_id;

        $schedules = Schedule::with(['class'])
            ->where('trainer_id', $trainerId)
            ->where('schedule_date', '>=', now()->toDateString())
            ->orderBy('schedule_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($schedule) {
                $bookingsCount = Booking::where('schedule_id', $schedule->schedule_id)
                    ->whereIn('status', ['booked', 'attended'])
                    ->count();
                $schedule->bookings_count = $bookingsCount;
                return $schedule;
            });

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    // all schedules (trainer)
    public function trainerSchedules(Request $request)
    {
        $trainerId = $request->user()->user_id;

        $schedules = Schedule::with(['class'])
            ->where('trainer_id', $trainerId)
            ->orderBy('schedule_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(15);

        // Add bookings count to each schedule
        $schedules->getCollection()->transform(function ($schedule) {
            $schedule->bookings_count = Booking::where('schedule_id', $schedule->schedule_id)
                ->whereIn('status', ['booked', 'attended'])
                ->count();
            return $schedule;
        });

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    // get member
    public function participants($id, Request $request)
    {
        $trainerId = $request->user()->user_id;

        $schedule = Schedule::where('schedule_id', $id)
            ->where('trainer_id', $trainerId)
            ->firstOrFail();

        $participants = Booking::with(['user'])
            ->where('schedule_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($booking) {
                return [
                    'booking_id' => $booking->booking_id,
                    'user_id' => $booking->user_id,
                    'user_name' => $booking->user->name,
                    'user_email' => $booking->user->email,
                    'booking_type' => $booking->booking_type,
                    'status' => $booking->status,
                    'created_at' => $booking->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => $schedule->load('class'),
                'participants' => $participants,
                'total' => $participants->count(),
            ],
        ]);
    }

    // get all member
    public function trainerAttendances(Request $request)
    {
        $trainerId = $request->user()->user_id;
        $query = Booking::whereHas('schedule', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        })->with(['user', 'schedule.class']);

        if ($request->search) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->schedule_id) {
            $query->where('schedule_id', $request->schedule_id);
        }

        $attendances = $query->orderBy('created_at', 'desc')->paginate(15);

        $attendances->getCollection()->transform(function ($booking) {
            return [
                'booking_id' => $booking->booking_id,
                'user_name' => $booking->user->name,
                'user_email' => $booking->user->email,
                'booking_type' => $booking->booking_type,
                'status' => $booking->status,
                'class_name' => $booking->schedule->class?->class_name ?? 'Kelas tidak tersedia',
                'schedule_date' => $booking->schedule->schedule_date,
                'start_time' => \Carbon\Carbon::parse($booking->schedule->start_time)->format('H:i'),
                'end_time' => \Carbon\Carbon::parse($booking->schedule->end_time)->format('H:i'),
            ];
        });

        return response()->json(['success' => true, 'data' => $attendances]);
    }

    // absen attended
    public function markAttendance(Request $request, $bookingId)
    {
        $trainerId = $request->user()->user_id;

        $booking = Booking::whereHas('schedule', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        })->findOrFail($bookingId);

        $request->validate([
            'status' => 'required|in:attended,canceled',
        ]);

        $booking->status = $request->status;
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Status kehadiran berhasil diupdate',
            'data' => $booking,
        ]);
    }
}
