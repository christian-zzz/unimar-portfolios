<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\StudentWelcomeMail;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Handle authentication and issue token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * Register a new student account (Admin only).
     */
    public function registerStudent(Request $request): JsonResponse
    {
        // Strict Business Rule: Only admins can register new students
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        ]);

        // Generate a secure random password
        $generatedPassword = Str::password(10);

        $student = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($generatedPassword),
            'role' => 'student', // Hardcoded role to enforce rule
        ]);

        // Send the welcome email with credentials
        try {
            Mail::to($student->email)->send(new StudentWelcomeMail($student, $generatedPassword));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al enviar correo de bienvenida: ' . $e->getMessage());
        }

        return response()->json([
            'user' => $student,
            'generated_password' => $generatedPassword,
        ], 201);
    }

    /**
     * Revoke the current user's token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ], 200);
    }
}
