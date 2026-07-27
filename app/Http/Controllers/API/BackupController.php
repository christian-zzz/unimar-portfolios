<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BackupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        try {
            $files = Storage::disk('r2')->files('backups/');
        } catch (\Exception $e) {
            Log::error('Error al listar backups en R2: ' . $e->getMessage());
            return response()->json(['backups' => [], 'message' => 'Error al conectar con el almacenamiento.'], 500);
        }

        $backups = [];
        foreach ($files as $file) {
            try {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::disk('r2')->size($file),
                    'last_modified' => Storage::disk('r2')->lastModified($file),
                ];
            } catch (\Exception $e) {
                Log::warning('Error al obtener metadatos de backup: ' . $file . ' - ' . $e->getMessage());
            }
        }

        usort($backups, fn($a, $b) => $b['last_modified'] - $a['last_modified']);

        return response()->json(['backups' => $backups]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $dbUrl = config('database.connections.pgsql.url');
        if (!$dbUrl) {
            return response()->json(['message' => 'No se encontró la configuración de la base de datos.'], 500);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupName = "backup_{$timestamp}.sql.gz";
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = "{$tempDir}/{$backupName}";

        $command = sprintf(
            'pg_dump --dbname=%s --no-owner --no-acl 2>/dev/null | gzip > %s',
            escapeshellArg($dbUrl),
            escapeshellArg($tempPath)
        );

        Log::info('Iniciando creación de respaldo: ' . $backupName);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            $errorOutput = $process->getErrorOutput();
            Log::error('Error al ejecutar pg_dump: ' . $errorOutput);
            return response()->json([
                'message' => 'Error al crear el respaldo.',
            ], 500);
        }

        if (!file_exists($tempPath) || filesize($tempPath) === 0) {
            Log::error('Respaldo generado vacío.');
            return response()->json(['message' => 'El respaldo generado está vacío.'], 500);
        }

        $r2Path = 'backups/' . $backupName;

        try {
            $stream = fopen($tempPath, 'r');
            Storage::disk('r2')->writeStream($r2Path, $stream);
            fclose($stream);
        } catch (\Exception $e) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
            Log::error('Error al subir backup a R2: ' . $e->getMessage());
            return response()->json(['message' => 'Error al almacenar el respaldo en R2.'], 500);
        }

        unlink($tempPath);
        Log::info('Respaldo creado exitosamente: ' . $backupName);

        try {
            $size = Storage::disk('r2')->size($r2Path);
            $lastModified = Storage::disk('r2')->lastModified($r2Path);
        } catch (\Exception $e) {
            $size = 0;
            $lastModified = now()->timestamp;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Respaldo creado exitosamente.',
            'backup' => [
                'name' => $backupName,
                'path' => $r2Path,
                'size' => $size,
                'last_modified' => $lastModified,
            ],
        ]);
    }

    public function download(Request $request, string $filename)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $path = 'backups/' . $filename;

        try {
            if (!Storage::disk('r2')->exists($path)) {
                return response()->json(['message' => 'Respaldo no encontrado.'], 404);
            }
            return Storage::disk('r2')->download($path, $filename);
        } catch (\Exception $e) {
            Log::error('Error al descargar backup: ' . $filename . ' - ' . $e->getMessage());
            return response()->json(['message' => 'Error al descargar el respaldo.'], 500);
        }
    }

    public function destroy(Request $request, string $filename): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $path = 'backups/' . $filename;

        try {
            if (!Storage::disk('r2')->exists($path)) {
                return response()->json(['message' => 'Respaldo no encontrado.'], 404);
            }
            Storage::disk('r2')->delete($path);
            Log::info('Respaldo eliminado: ' . $filename);
            return response()->json([
                'status' => 'success',
                'message' => 'Respaldo eliminado exitosamente.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar backup: ' . $filename . ' - ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar el respaldo.'], 500);
        }
    }
}
