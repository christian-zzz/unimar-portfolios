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

        $maintenance = \App\Models\AppSetting::find('maintenance_mode')?->value['enabled'] ?? false;
        if ($maintenance && $user->role !== 'admin') {
            Auth::logout();
            return response()->json([
                'message' => 'La plataforma está en mantenimiento. Intenta más tarde.'
            ], 503);
        }

        $user->update(['last_login_at' => now()]);
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'regex:/^[^\s@]+@unimar\.edu\.ve$/i'],
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

    /**
     * Send password reset request email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Ese correo no está registrado en el sistema.',
            ], 422);
        }

        // Generate a secure token and store it
        $token = Str::random(60);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        try {
            Mail::to($user->email)->send(new \App\Mail\ForgotPasswordMail($user, $token));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al enviar correo de recuperación: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Si el correo existe en nuestro sistema, se ha enviado un enlace de recuperación.',
        ], 200);
    }

    /**
     * Reset the user password using a valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'El enlace de recuperación es inválido o ha expirado.',
            ], 422);
        }

        // Expire token after 60 minutes
        if (now()->parse($record->created_at)->addMinutes(60)->isPast()) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'message' => 'El enlace de recuperación ha expirado.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'message' => 'Tu contraseña ha sido restablecida exitosamente. Ya puedes iniciar sesión.',
            ], 200);
        }

        return response()->json([
            'message' => 'Error al restablecer la contraseña. Usuario no encontrado.',
        ], 404);
    }
}
