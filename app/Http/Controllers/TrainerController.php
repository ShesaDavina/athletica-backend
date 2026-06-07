<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TrainerController extends Controller
{
    public function index()
    {
        $trainers = User::where('role', 'trainer')->get();
        return response()->json(['success' => true, 'data' => $trainers]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ], [
            'name.required' => 'Nama trainer harus diisi',
            'name.min' => 'Nama minimal 3 karakter',
            'email.required' => 'Email harus diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password harus diisi',
            'password.min' => 'Password minimal 6 karakter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $trainer = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'trainer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trainer berhasil ditambahkan',
            'data' => $trainer,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $trainer = User::where('role', 'trainer')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|min:3',
            'email' => 'sometimes|email|unique:users,email,' . $id . ',user_id',
            'password' => 'sometimes|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $trainer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Trainer berhasil diupdate',
            'data' => $trainer,
        ]);
    }


    public function destroy($id)
    {
        $trainer = User::where('role', 'trainer')->findOrFail($id);

        if ($trainer->schedules()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Trainer masih memiliki jadwal'], 422);
        }

        $trainer->delete();
        return response()->json(['success' => true, 'message' => 'Trainer berhasil dihapus']);
    }
}
