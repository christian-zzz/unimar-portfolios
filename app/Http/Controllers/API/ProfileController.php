<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's personal information.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Strict Business Rule: Students are not allowed to update their name or email
        if ($user->role === 'student') {
            return response()->json([
                'message' => 'Los estudiantes no tienen permitido modificar su nombre o correo electrónico.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        return response()->json($user, 200);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente.',
        ], 200);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:2048'],
        ]);

        $file = $request->file('avatar');
        $publicId = 'avatars/' . $user->id;

        $stored = $file->storeAs('avatars', $user->id, 'cloudinary');
        if (!$stored) {
            return response()->json(['message' => 'Error al subir la imagen.'], 500);
        }

        $url = Storage::disk('cloudinary')->url($publicId);
        $user->update(['avatar_url' => $url]);

        return response()->json(['avatar_url' => $url], 200);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url) {
            Storage::disk('cloudinary')->delete('avatars/' . $user->id);
            $user->update(['avatar_url' => null]);
        }

        return response()->json(['message' => 'Avatar eliminado.'], 200);
    }
}
