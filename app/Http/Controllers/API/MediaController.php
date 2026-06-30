<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\Media;

class MediaController extends Controller
{
    /**
     * List all media uploaded by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $media = Media::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Append public URL to each media item for easier rendering in React
            $media->transform(function ($item) {
                $item->url = Storage::disk($item->disk)->url($item->file_path);
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'media' => $media,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener la biblioteca de medios.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a media file and route it to the correct disk based on MIME type.
     */
    public function upload(Request $request): JsonResponse
    {
        // 1. Validate the incoming request
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'], // Max size 10MB (10240 KB)
        ], [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo debe ser un archivo válido.',
            'file.max' => 'El tamaño máximo permitido es de 10 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación fallida',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $mimeType = $file->getMimeType();

            // 2. Routing logic based on MIME type
            if (str_starts_with($mimeType, 'image/')) {
                $disk = env('FILESYSTEM_DISK_IMAGES', 'cloudinary');
            } else {
                $disk = env('FILESYSTEM_DISK_ASSETS', 'r2');
            }

            // 3. Store file and generate filename
            $path = $file->store('', $disk);

            if ($path === false) {
                throw new Exception('No se pudo guardar el archivo en el disco.');
            }

            // 4. Save to Database
            $media = new Media();
            $media->user_id = $request->user()->id;
            
            $portfolio = $request->user()->portfolio()->first();
            $media->portfolio_id = $portfolio ? $portfolio->id : null;
            
            $media->file_name = $file->getClientOriginalName();
            $media->file_path = $path;
            $media->mime_type = $mimeType;
            $media->size = $file->getSize();
            $media->disk = $disk;
            $media->save();

            // 5. Get the public URL
            $url = Storage::disk($disk)->url($path);

            return response()->json([
                'status' => 'success',
                'url' => $url,
                'type' => $mimeType,
                'disk' => $disk,
                'media' => $media,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al subir el archivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a media file from disk and database.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $media = Media::where('user_id', $request->user()->id)
                ->where('id', $id)
                ->first();

            if (!$media) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El archivo no existe o no tiene permisos para eliminarlo.',
                ], 404);
            }

            // Delete from disk
            if (Storage::disk($media->disk)->exists($media->file_path)) {
                Storage::disk($media->disk)->delete($media->file_path);
            }

            // Delete from database
            $media->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo eliminado con éxito.',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al eliminar el archivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
