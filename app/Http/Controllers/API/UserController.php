<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of all registered students (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        // Security Check: Only admins can list students
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        // Fetch students selecting only safe columns, ordered by registration date (newest first)
        $students = User::where('role', 'student')
            ->select(['id', 'name', 'email', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($students, 200);
    }
}
