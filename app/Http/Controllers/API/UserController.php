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

        // Fetch students with eager loaded portfolio and media count/size aggregates
        $students = User::where('role', 'student')
            ->select(['id', 'name', 'email', 'created_at'])
            ->with(['portfolio:id,user_id,slug,is_published'])
            ->withCount('media')
            ->withSum('media as media_size', 'size')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($students, 200);
    }
}
