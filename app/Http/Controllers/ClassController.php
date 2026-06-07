<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClassController extends Controller
{
    // get all classes
    public function index()
    {
        $classes = ClassModel::all();
        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    // detail satu class
    public function show($id)
    {
        $class = ClassModel::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $class,
        ]);
    }

    // create class (admin)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_name' => 'required|string|min:3',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
        ], [
            'class_name.required' => 'Nama Kelas harus diisi',
            'price.required' => 'Harga Kelas harus diisi',
            'capacity.required' => 'Kapasitas Kelas harus diisi',
            'image.mimes' => 'Format foto wajib berupa jpg, jpeg, png, svg, webp',
            'image.max' => 'Ukuran foto maksimal 2MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $namaFile = rand(1, 999) . '-' . time() . '-image.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('classes', $namaFile, 'public');
        }

        $class = ClassModel::create([
            'class_name' => $request->class_name,
            'description' => $request->description,
            'price' => $request->price,
            'capacity' => $request->capacity,
            'image' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil ditambahkan',
            'data' => $class,
        ], 201);
    }

    // update class (admin)
    public function update(Request $request, $id)
    {
        $class = ClassModel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'class_name' => 'sometimes|string|min:3',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'capacity' => 'sometimes|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['class_name', 'description', 'price', 'capacity']);

        // image upload
        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($class->image && Storage::disk('public')->exists($class->image)) {
                Storage::disk('public')->delete($class->image);
            }

            $image = $request->file('image');
            $namaFile = rand(1, 999) . '-' . time() . '-image.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('classes', $namaFile, 'public');
            $data['image'] = $imagePath;
        }

        $class->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil diupdate',
            'data' => $class,
        ]);
    }

    // delete class (admin)
    public function destroy($id)
    {
        $class = ClassModel::findOrFail($id);

        if ($class->image && Storage::disk('public')->exists($class->image)) {
            Storage::disk('public')->delete($class->image);
        }

        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil dihapus',
        ]);
    }
}
