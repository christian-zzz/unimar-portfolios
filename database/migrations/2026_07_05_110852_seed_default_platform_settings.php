<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->upsert([
            [
                'key' => 'max_upload_mb',
                'value' => json_encode(['mb' => 10]),
                'description' => 'Tamaño máximo por archivo subido (MB)',
            ],
            [
                'key' => 'max_storage_mb',
                'value' => json_encode(['mb' => 500]),
                'description' => 'Cuota máxima de almacenamiento por estudiante (MB)',
            ],
            [
                'key' => 'allowed_mime_types',
                'value' => json_encode([
                    'types' => [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                        'image/svg+xml',
                        'video/mp4',
                        'video/webm',
                        'video/quicktime',
                        'application/pdf',
                        'application/json',
                        'font/woff',
                        'font/woff2',
                        'application/font-woff',
                        'application/font-woff2',
                        'model/gltf-binary',
                    ],
                ]),
                'description' => 'Tipos MIME permitidos en el editor',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => json_encode(['enabled' => false]),
                'description' => 'Modo mantenimiento (bloquea acceso a no administradores)',
            ],
        ], ['key'], ['value', 'description']);
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', [
            'max_upload_mb',
            'max_storage_mb',
            'allowed_mime_types',
            'maintenance_mode',
        ])->delete();
    }
};
