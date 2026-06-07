<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserMembership;
use Illuminate\Http\Request;

class UserMembershipController extends Controller
{
    // lihat semua membership user (admin only)
    public function index()
    {
        $userMemberships = UserMembership::with(['user', 'membership'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $userMemberships,
        ]);
    }

    // detail membership user
    public function show($id)
    {
        $userMembership = UserMembership::with(['user', 'membership'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $userMembership,
        ]);
    }


    // semua membership aktif (admin only)
    public function active()
    {
        $activeMemberships = UserMembership::with(['user', 'membership'])
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->get();

        return response()->json([
            'success' => true,
            'count' => $activeMemberships->count(),
            'data' => $activeMemberships,
        ]);
    }

    // semua membership expired (admin only)
    public function expired()
    {
        $expiredMemberships = UserMembership::with(['user', 'membership'])
            ->where('status', 'expired')
            ->orWhere('end_date', '<', now())
            ->get();

        return response()->json([
            'success' => true,
            'count' => $expiredMemberships->count(),
            'data' => $expiredMemberships,
        ]);
    }
}
