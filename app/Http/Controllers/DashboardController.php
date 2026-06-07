<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\UserMembership;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ADMIN DASHBOARD
    // semua chart data
    public function adminDashboard(Request $request)
    {
        $monthlyBookings = $this->getMonthlyBookings();

        $populerClasses = $this->getPopularClasses();

        $monthlyRevenue = $this->getMonthlyRevenue();

        $membershipStats = $this->getMembershipStats();

        $totals = $this->getTotals();

        return response()->json([
            'success' => true,
            'data' => [
                'monthly_bookings' => $monthlyBookings,
                'popular_classes' => $populerClasses,
                'monthly_revenue' => $monthlyRevenue,
                'membership_stats' => $membershipStats,
                'totals' => $totals,
            ]
        ]);
    }

    // TRAINER DASHBOARD
    public function trainerDashboard(Request $request)
    {
        $trainerId = $request->user()->user_id;

        $totalSchedules = Schedule::where('trainer_id', $trainerId)->count();

        $totalParticipants = Booking::whereHas('schedule', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        })->where('status', 'attended')->count();

        $totalClasses = Schedule::where('trainer_id', $trainerId)
            ->distinct('class_id')
            ->count('class_id');

        $totalBookings = Booking::whereHas('schedule', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        })->count();

        $attendedBookings = Booking::whereHas('schedule', function ($q) use ($trainerId) {
            $q->where('trainer_id', $trainerId);
        })->where('status', 'attended')->count();

        $attendanceRate = $totalBookings > 0 ? round(($attendedBookings / $totalBookings) * 100, 1) : 0;

        $upcomingSchedules = Schedule::where('trainer_id', $trainerId)
            ->where('schedule_date', '>=', Carbon::today())
            ->with('class')
            ->orderBy('schedule_date', 'asc')
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
            'data' => [
                'total_schedules' => $totalSchedules,
                'total_participants' => $totalParticipants,
                'total_classes' => $totalClasses,
                'attendance_rate' => $attendanceRate,
                'upcoming_schedules' => $upcomingSchedules,
            ]
        ]);
    }


    // PRIVATE METHODS
    // booking perbulan (6 bulan terakhir)
    private function getMonthlyBookings()
    {
        $months = collect();
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $months->push([
                'month' => $month->format('M Y'),
                'year' => $month->year,
                'month_num' => $month->month,
            ]);
        }

        $bookings = Booking::select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as total')
        )
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->keyBy(function ($item) {
                return $item->year . '-' . $item->month;
            });

        $result = [];
        foreach ($months as $month) {
            $key = $month['year'] . '-' . $month['month_num'];
            $result[] = [
                'month' => $month['month'],
                'total' => $bookings[$key]->total ?? 0,
            ];
        }

        return $result;
    }

    // popular classes (top 3 kelas paling banyak dibooking)
    private function getPopularClasses()
    {
        return Booking::select(
            'classes.class_name',
            DB::raw('COUNT(bookings.booking_id) as total_bookings')
        )
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.schedule_id')
            ->join('classes', 'schedules.class_id', '=', 'classes.class_id')
            ->where('bookings.status', '!=', 'canceled')
            ->groupBy('classes.class_id', 'classes.class_name')
            ->orderBy('total_bookings', 'desc')
            ->limit(3)
            ->get();
    }

    // pendapatan perbulan (6 bln terakhir)
    private function getMonthlyRevenue()
    {
        $months = collect();
        $now = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $months->push([
                'month' => $month->format('M Y'),
                'year' => $month->year,
                'month_num' => $month->month,
            ]);
        }

        $revenues = Payment::select(
            DB::raw('YEAR(payment_date) as year'),
            DB::raw('MONTH(payment_date) as month'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'paid')
            ->where('payment_date', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->keyBy(function ($item) {
                return $item->year . '-' . $item->month;
            });

        $result = [];
        foreach ($months as $month) {
            $key = $month['year'] . '-' . $month['month_num'];
            $result[] = [
                'month' => $month['month'],
                'total' => $revenues[$key]->total ?? 0,
            ];
        }

        return $result;
    }

    // membership active dan expired
    private function getMembershipStats()
    {
        $active = UserMembership::where('status', 'active')
            ->where('end_date', '>=', Carbon::today())
            ->count();

        $expired = UserMembership::where('status', 'expired')
            ->orWhere('end_date', '<', Carbon::today())
            ->count();

        return [
            'active' => $active,
            'expired' => $expired,
        ];
    }

    // total keseluruhan (cards)
    private function getTotals()
    {
        return [
            'total_users' => \App\Models\User::count(),
            'total_trainers' => \App\Models\User::where('role', 'trainer')->count(),
            'total_bookings' => Booking::where('status', 'booked')->count(),
            'total_attended' => Booking::where('status', 'attended')->count(),
            'total_revenue' => Payment::where('status', 'paid')->sum('amount'),
            'active_memberships' => UserMembership::where('status', 'active')
                ->where('end_date', '>=', Carbon::today())
                ->count(),
        ];
    }
}
