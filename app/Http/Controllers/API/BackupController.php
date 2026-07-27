<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $files = Storage::disk('r2')->files('backups/');

        $backups = array_map(function ($file) {
            return [
                'name' => basename($file),
                'path' => $file,
                'size' => Storage::disk('r2')->size($file),
                'last_modified' => Storage::disk('r2')->lastModified($file),
            ];
        }, $files);

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

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            return response()->json([
                'message' => 'Error al crear el respaldo: ' . $process->getErrorOutput(),
            ], 500);
        }

        if (!file_exists($tempPath) || filesize($tempPath) === 0) {
            return response()->json(['message' => 'El respaldo generado está vacío.'], 500);
        }

        $r2Path = 'backups/' . $backupName;
        Storage::disk('r2')->put($r2Path, file_get_contents($tempPath));

        unlink($tempPath);

        return response()->json([
            'status' => 'success',
            'message' => 'Respaldo creado exitosamente.',
            'backup' => [
                'name' => $backupName,
                'path' => $r2Path,
                'size' => Storage::disk('r2')->size($r2Path),
                'last_modified' => Storage::disk('r2')->lastModified($r2Path),
            ],
        ]);
    }

    public function download(Request $request, string $filename)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $path = 'backups/' . $filename;

        if (!Storage::disk('r2')->exists($path)) {
            return response()->json(['message' => 'Respaldo no encontrado.'], 404);
        }

        return Storage::disk('r2')->download($path, $filename);
    }

    public function destroy(Request $request, string $filename): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $path = 'backups/' . $filename;

        if (!Storage::disk('r2')->exists($path)) {
            return response()->json(['message' => 'Respaldo no encontrado.'], 404);
        }

        Storage::disk('r2')->delete($path);

        return response()->json([
            'status' => 'success',
            'message' => 'Respaldo eliminado exitosamente.',
        ]);
    }
}
