<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    private const SETTINGS_SCHEMA = [
        'max_upload_mb' => ['type' => 'int', 'min' => 1, 'max' => 100],
        'max_storage_mb' => ['type' => 'int', 'min' => 50, 'max' => 10000],
        'allowed_mime_types' => ['type' => 'array'],
        'maintenance_mode' => ['type' => 'bool'],
    ];

    private const DEFAULTS = [
        'max_upload_mb' => ['mb' => 10],
        'max_storage_mb' => ['mb' => 500],
        'allowed_mime_types' => [
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
        ],
        'maintenance_mode' => ['enabled' => false],
    ];

    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $record = AppSetting::find($key);
            $settings[$key] = $record ? $record->value : $default;
        }

        return response()->json($settings);
    }

    public function update(string $key, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        if (!isset(self::SETTINGS_SCHEMA[$key])) {
            return response()->json(['message' => 'Configuración inválida.'], 422);
        }

        $schema = self::SETTINGS_SCHEMA[$key];
        $value = $request->input('value');

        if ($schema['type'] === 'int') {
            if (!is_int($value) && !is_numeric($value)) {
                return response()->json(['message' => 'El valor debe ser un número entero.'], 422);
            }
            $value = (int) $value;
            if ($value < $schema['min'] || $value > $schema['max']) {
                return response()->json(['message' => "El valor debe estar entre {$schema['min']} y {$schema['max']}."], 422);
            }
            $stored = [$key === 'max_upload_mb' ? 'mb' : 'mb' => $value];
        } elseif ($schema['type'] === 'bool') {
            if (!is_bool($value)) {
                return response()->json(['message' => 'El valor debe ser verdadero o falso.'], 422);
            }
            $stored = ['enabled' => $value];
        } elseif ($schema['type'] === 'array') {
            if (!is_array($value)) {
                return response()->json(['message' => 'El valor debe ser una lista.'], 422);
            }
            $stored = ['types' => $value];
        } else {
            $stored = $value;
        }

        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'description' => self::DEFAULTS[$key]['description'] ?? '']
        );

        return response()->json(['key' => $key, 'value' => $stored]);
    }
}
