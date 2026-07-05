<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Portfolio;
use App\Models\Media;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\AdminStudentMail;

class AdminController extends Controller
{
    /**
     * Get platform statistics for the admin dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        // 1. Authorization check
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Acceso denegado. Se requieren privilegios de administrador.'
            ], 403);
        }

        // 2. General Metrics
        $totalStudents = User::where('role', 'student')->count();
        $publishedPortfolios = Portfolio::where('is_published', true)->count();
        $draftPortfolios = Portfolio::where('is_published', false)->count();
        $totalFiles = Media::count();

        // 3. Storage bytes calculation (fallback to 0)
        $storageCloudinary = (int) Media::where('disk', 'cloudinary')->sum('size');
        $storageR2 = (int) Media::where('disk', 'r2')->sum('size');

        // 4. Categories breakdown
        $categoriesModel = Category::withCount('portfolios')
            ->orderBy('portfolios_count', 'desc')
            ->get();

        $categoriesBreakdown = [];
        foreach ($categoriesModel as $cat) {
            $categoriesBreakdown[] = [
                'name' => $cat->name,
                'slug' => $cat->slug,
                'count' => $cat->portfolios_count,
            ];
        }

        return response()->json([
            'status' => 'success',
            'total_students' => $totalStudents,
            'published_portfolios' => $publishedPortfolios,
            'draft_portfolios' => $draftPortfolios,
            'total_files' => $totalFiles,
            'categories_breakdown' => $categoriesBreakdown,
            'storage_cloudinary' => $storageCloudinary,
            'storage_r2' => $storageR2,
        ]);
    }

    /**
     * Force unpublish a student's portfolio.
     */
    public function unpublishStudentPortfolio(string $studentId, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $student = User::where('role', 'student')->findOrFail($studentId);
        $portfolio = $student->portfolio;

        if (!$portfolio) {
            return response()->json(['message' => 'El estudiante no ha inicializado su portafolio.'], 404);
        }

        $portfolio->update(['is_published' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Portafolio despublicado exitosamente.',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Retrieve all uploaded files for a specific student.
     */
    public function getStudentMedia(string $studentId, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $student = User::where('role', 'student')->findOrFail($studentId);
        $media = Media::where('user_id', $student->id)->orderBy('created_at', 'desc')->get();

        // Append public URL to each media item for easier rendering in React
        $media->transform(function ($item) {
            $item->url = \Illuminate\Support\Facades\Storage::disk($item->disk)->url($item->file_path);
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'media' => $media,
        ]);
    }

    /**
     * Reset a student's password and email them the new credentials.
     */
    public function resetStudentPassword(string $studentId, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $student = User::where('role', 'student')->findOrFail($studentId);
        $newPassword = \Illuminate\Support\Str::password(10);

        $student->update([
            'password' => \Illuminate\Support\Facades\Hash::make($newPassword),
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($student->email)->send(new \App\Mail\PasswordResetMail($student, $newPassword));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fallo al enviar correo de restablecimiento: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Contraseña restablecida y enviada por correo.',
            'generated_password' => $newPassword,
        ]);
    }

    /**
     * Send a custom email from admin to a student.
     */
    public function sendEmail(string $studentId, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
        ]);

        $student = User::where('role', 'student')->findOrFail($studentId);

        try {
            Mail::to($student->email)->send(new AdminStudentMail(
                $student,
                $validated['subject'],
                $validated['message']
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Correo enviado exitosamente a ' . $student->name . '.',
            ]);
        } catch (\Exception $e) {
            Log::error('Fallo al enviar correo administrador-estudiante: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al enviar el correo. Intenta de nuevo.',
            ], 500);
        }
    }

    /**
     * Deep delete a student user, removing all their media from Cloudinary/R2 first.
     */
    public function destroyStudent(string $studentId, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $student = User::where('role', 'student')->findOrFail($studentId);

        // Delete all files from Cloudinary and Cloudflare R2
        $mediaItems = Media::where('user_id', $student->id)->get();
        foreach ($mediaItems as $media) {
            try {
                if (\Illuminate\Support\Facades\Storage::disk($media->disk)->exists($media->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk($media->disk)->delete($media->file_path);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Fallo al eliminar archivo en la nube para el estudiante: ' . $e->getMessage());
            }
        }

        // Delete cover thumbnail if exists
        $portfolio = $student->portfolio;
        if ($portfolio && $portfolio->thumbnail_path) {
            try {
                if (str_starts_with($portfolio->thumbnail_path, 'http')) {
                    $filename = basename($portfolio->thumbnail_path);
                    $disk = env('FILESYSTEM_DISK_IMAGES', 'cloudinary');
                    if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($filename)) {
                        \Illuminate\Support\Facades\Storage::disk($disk)->delete($filename);
                    }
                } else {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($portfolio->thumbnail_path)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($portfolio->thumbnail_path);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Fallo al eliminar miniatura del estudiante: ' . $e->getMessage());
            }
        }

        // Delete user (cascade DB records delete)
        $student->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Estudiante y todos sus archivos eliminados permanentemente del sistema.',
        ]);
    }
}
