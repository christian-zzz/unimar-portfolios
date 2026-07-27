<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        $tempSql = "{$tempDir}/backup_{$timestamp}.sql";

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $command = sprintf(
            'pg_dump --dbname=%s --no-owner --no-acl > %s 2>/dev/null',
            escapeshellArg($dbUrl),
            escapeshellArg($tempSql)
        );

        Log::info('Iniciando respaldo: ' . $backupName);

        exec($command, $execOutput, $exitCode);

        if ($exitCode !== 0) {
            if (file_exists($tempSql)) {
                unlink($tempSql);
            }
            Log::error('Error en pg_dump (exit code: ' . $exitCode . ')');
            return response()->json(['message' => 'Error al crear el respaldo.'], 500);
        }

        if (!file_exists($tempSql) || filesize($tempSql) === 0) {
            Log::error('Respaldo generado vacío.');
            return response()->json(['message' => 'El respaldo generado está vacío.'], 500);
        }

        $sql = file_get_contents($tempSql);
        unlink($tempSql);

        $compressed = gzencode($sql, 9);
        if ($compressed === false) {
            Log::error('Error al comprimir el respaldo.');
            return response()->json(['message' => 'Error al comprimir el respaldo.'], 500);
        }

        $r2Path = 'backups/' . $backupName;

        try {
            Storage::disk('r2')->put($r2Path, $compressed);
        } catch (\Exception $e) {
            Log::error('Error al subir backup a R2: ' . $e->getMessage());
            return response()->json(['message' => 'Error al almacenar el respaldo en R2.'], 500);
        }

        Log::info('Respaldo creado exitosamente: ' . $backupName);

        try {
            $size = Storage::disk('r2')->size($r2Path);
            $lastModified = Storage::disk('r2')->lastModified($r2Path);
        } catch (\Exception $e) {
            $size = strlen($compressed);
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
