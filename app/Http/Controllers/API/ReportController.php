<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Portfolio;
use App\Models\Media;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

class ReportController extends Controller
{
    public function generate(Request $request): Response|JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:students,portfolios,storage,analytics',
            'format' => 'required|in:pdf,excel',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $type = $validated['type'];
        $format = $validated['format'];
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $methodName = "generate{$type}Report";
        if (!method_exists($this, $methodName)) {
            return response()->json(['message' => 'Tipo de reporte no soportado.'], 400);
        }

        $data = $this->$methodName($dateFrom, $dateTo);

        if ($format === 'pdf') {
            return $this->renderPdf($type, $data, $dateFrom, $dateTo);
        }

        return $this->renderExcel($type, $data, $dateFrom, $dateTo);
    }

    private function generateStudentsReport(?string $dateFrom, ?string $dateTo): array
    {
        $students = User::where('role', 'student')
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('created_at', '<=', $dateTo . ' 23:59:59'))
            ->with('portfolio')
            ->orderBy('created_at', 'desc')
            ->get();

        $total = $students->count();
        $active = $students->filter(fn($s) => $s->last_login_at && $s->last_login_at->gt(now()->subDays(30)))->count();
        $published = $students->filter(fn($s) => $s->portfolio?->is_published)->count();
        $draft = $students->filter(fn($s) => $s->portfolio && !$s->portfolio->is_published)->count();
        $nostarted = $students->filter(fn($s) => !$s->portfolio)->count();

        $registrationsByMonth = $students
            ->groupBy(fn($s) => $s->created_at->format('Y-m'))
            ->map(fn($group, $month) => ['month' => $month, 'count' => $group->count()])
            ->values()
            ->toArray();

        return [
            'title' => 'Reporte de Estudiantes',
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'published' => $published,
            'draft' => $draft,
            'nostarted' => $nostarted,
            'registrations_by_month' => $registrationsByMonth,
            'students' => $students->map(fn($s) => [
                'name' => $s->name,
                'email' => $s->email,
                'portfolio_status' => $s->portfolio
                    ? ($s->portfolio->is_published ? 'Publicado' : 'Borrador')
                    : 'Sin iniciar',
                'last_login' => $s->last_login_at?->format('d/m/Y H:i') ?? 'Nunca',
                'created_at' => $s->created_at->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    private function generatePortfoliosReport(?string $dateFrom, ?string $dateTo): array
    {
        $portfolios = Portfolio::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('created_at', '<=', $dateTo . ' 23:59:59'))
            ->with('user', 'categories')
            ->orderBy('created_at', 'desc')
            ->get();

        $total = $portfolios->count();
        $published = $portfolios->filter(fn($p) => $p->is_published)->count();

        $categories = Category::withCount('portfolios')
            ->orderBy('portfolios_count', 'desc')
            ->get()
            ->map(fn($c) => ['name' => $c->name, 'count' => $c->portfolios_count])
            ->toArray();

        $lighthouseScores = $portfolios
            ->filter(fn($p) => !empty($p->lighthouse_scores))
            ->map(fn($p) => $p->lighthouse_scores);

        $avgPerformance = $this->average($lighthouseScores->pluck('performance')->filter());
        $avgAccessibility = $this->average($lighthouseScores->pluck('accessibility')->filter());
        $avgSeo = $this->average($lighthouseScores->pluck('seo')->filter());
        $avgBestPractices = $this->average($lighthouseScores->pluck('best_practices')->filter());

        return [
            'title' => 'Reporte de Portafolios',
            'total' => $total,
            'published' => $published,
            'unpublished' => $total - $published,
            'categories' => $categories,
            'avg_performance' => $avgPerformance,
            'avg_accessibility' => $avgAccessibility,
            'avg_seo' => $avgSeo,
            'avg_best_practices' => $avgBestPractices,
            'portfolios' => $portfolios->map(fn($p) => [
                'student' => $p->user?->name ?? '—',
                'title' => $p->title ?? 'Sin título',
                'slug' => $p->slug,
                'status' => $p->is_published ? 'Publicado' : 'Borrador',
                'categories' => $p->categories->pluck('name')->implode(', ') ?: '—',
                'created_at' => $p->created_at->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    private function generateStorageReport(?string $dateFrom, ?string $dateTo): array
    {
        $media = Media::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('created_at', '<=', $dateTo . ' 23:59:59'))
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalFiles = $media->count();
        $totalSize = (int) $media->sum('size');

        $byType = $media->groupBy(function ($m) {
            $type = explode('/', $m->mime_type ?? 'unknown')[0];
            return match ($type) {
                'image' => 'Imágenes',
                'video' => 'Videos',
                'application' => match (true) {
                    str_contains($m->mime_type ?? '', 'json') => 'Animaciones (Lottie)',
                    str_contains($m->mime_type ?? '', 'pdf') => 'Documentos (PDF)',
                    str_contains($m->mime_type ?? '', 'font') => 'Fuentes',
                    default => 'Otros',
                },
                'font' => 'Fuentes',
                'model' => 'Modelos 3D',
                default => 'Otros',
            };
        })->map(fn($group, $type) => [
            'type' => $type,
            'count' => $group->count(),
            'size' => (int) $group->sum('size'),
        ])->values()->toArray();

        $byStudent = $media->groupBy('user_id')->map(function ($group, $userId) {
            $user = $group->first()->user;
            return [
                'name' => $user?->name ?? 'Desconocido',
                'email' => $user?->email ?? '—',
                'count' => $group->count(),
                'size' => (int) $group->sum('size'),
            ];
        })->sortByDesc('size')->values()->toArray();

        return [
            'title' => 'Reporte de Almacenamiento',
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'by_type' => $byType,
            'by_student' => $byStudent,
        ];
    }

    private function generateAnalyticsReport(?string $dateFrom, ?string $dateTo): array
    {
        $period = '15d';
        if ($dateFrom && $dateTo) {
            $from = now()->parse($dateFrom);
            $to = now()->parse($dateTo);
            $days = $from->diffInDays($to) + 1;
            $period = $days <= 15 ? '15d' : '30d';
        }

        $controller = app(AnalyticsController::class);
        $request = request();
        $result = $controller->getGlobalReport($request);

        $data = $result->getData(true);
        $historical = $data['historical'] ?? [];

        $totals = $historical['totals'] ?? [];
        $devices = $historical['devices'] ?? [];
        $countries = $historical['countries'] ?? [];
        $topPages = $historical['topPages'] ?? [];
        $events = $historical['events'] ?? [];
        $engagementRate = $historical['engagementRate'] ?? '0%';

        return [
            'title' => 'Reporte de Analíticas',
            'period' => $period,
            'totals' => $totals,
            'engagement_rate' => $engagementRate,
            'devices' => $devices,
            'countries' => $countries,
            'top_pages' => $topPages,
            'events' => $events,
        ];
    }

    private function renderPdf(string $type, array $data, ?string $dateFrom, ?string $dateTo): Response
    {
        $data['generated_at'] = now()->format('d/m/Y H:i');
        $data['date_from'] = $dateFrom ? now()->parse($dateFrom)->format('d/m/Y') : '—';
        $data['date_to'] = $dateTo ? now()->parse($dateTo)->format('d/m/Y') : '—';

        $pdf = Pdf::loadView("reports.{$type}", $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("reporte_{$type}_" . now()->format('Y-m-d') . '.pdf');
    }

    private function renderExcel(string $type, array $data, ?string $dateFrom, ?string $dateTo): Response
    {
        $writer = new Writer();
        $filename = storage_path("app/temp/reporte_{$type}_" . now()->format('Y-m-d') . '.xlsx');

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer->openToFile($filename);

        $headerStyle = (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setFontSize(11);

        $rows = $this->getExcelRows($type, $data);

        foreach ($rows as $index => $rowData) {
            $row = Row::fromValues($rowData);
            if ($index === 0) {
                $row->setStyle($headerStyle);
            }
            $writer->addRow($row);
        }

        $writer->close();

        $content = file_get_contents($filename);
        unlink($filename);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte_' . $type . '_' . now()->format('Y-m-d') . '.xlsx"',
        ]);
    }

    private function getExcelRows(string $type, array $data): array
    {
        return match ($type) {
            'students' => $this->getStudentsExcelRows($data),
            'portfolios' => $this->getPortfoliosExcelRows($data),
            'storage' => $this->getStorageExcelRows($data),
            'analytics' => $this->getAnalyticsExcelRows($data),
            default => [],
        };
    }

    private function getStudentsExcelRows(array $data): array
    {
        $rows = [
            ['Reporte de Estudiantes - Folium UNIMAR'],
            ['Generado: ' . $data['generated_at'] ?? now()->format('d/m/Y H:i')],
            ['Período:', $data['date_from'] ?? '—', 'al', $data['date_to'] ?? '—'],
            [],
            ['Resumen'],
            ['Total', 'Activos', 'Inactivos', 'Publicados', 'Borradores', 'Sin iniciar'],
            [
                $data['total'], $data['active'], $data['inactive'],
                $data['published'], $data['draft'], $data['nostarted'],
            ],
            [],
            ['Detalle de Estudiantes'],
            ['Nombre', 'Email', 'Portafolio', 'Último Acceso', 'Registro'],
        ];

        foreach ($data['students'] as $student) {
            $rows[] = [
                $student['name'],
                $student['email'],
                $student['portfolio_status'],
                $student['last_login'],
                $student['created_at'],
            ];
        }

        return $rows;
    }

    private function getPortfoliosExcelRows(array $data): array
    {
        $rows = [
            ['Reporte de Portafolios - Folium UNIMAR'],
            ['Generado: ' . ($data['generated_at'] ?? now()->format('d/m/Y H:i'))],
            ['Período:', $data['date_from'] ?? '—', 'al', $data['date_to'] ?? '—'],
            [],
            ['Resumen'],
            ['Total', 'Publicados', 'No publicados'],
            [$data['total'], $data['published'], $data['unpublished']],
            [],
            ['Puntuaciones Lighthouse Promedio'],
            ['Performance', 'Accesibilidad', 'SEO', 'Mejores Prácticas'],
            [
                $data['avg_performance'] . '%',
                $data['avg_accessibility'] . '%',
                $data['avg_seo'] . '%',
                $data['avg_best_practices'] . '%',
            ],
            [],
            ['Distribución por Categorías'],
            ['Categoría', 'Portafolios'],
        ];

        foreach ($data['categories'] as $cat) {
            $rows[] = [$cat['name'], $cat['count']];
        }

        $rows[] = [];
        $rows[] = ['Detalle de Portafolios'];
        $rows[] = ['Estudiante', 'Título', 'Slug', 'Estado', 'Categorías', 'Creado'];

        foreach ($data['portfolios'] as $portfolio) {
            $rows[] = [
                $portfolio['student'],
                $portfolio['title'],
                $portfolio['slug'],
                $portfolio['status'],
                $portfolio['categories'],
                $portfolio['created_at'],
            ];
        }

        return $rows;
    }

    private function getStorageExcelRows(array $data): array
    {
        $rows = [
            ['Reporte de Almacenamiento - Folium UNIMAR'],
            ['Generado: ' . ($data['generated_at'] ?? now()->format('d/m/Y H:i'))],
            ['Período:', $data['date_from'] ?? '—', 'al', $data['date_to'] ?? '—'],
            [],
            ['Resumen'],
            ['Archivos Totales', 'Espacio Total'],
            [$data['total_files'], $this->formatBytes($data['total_size'])],
            [],
            ['Distribución por Tipo'],
            ['Tipo', 'Archivos', 'Espacio'],
        ];

        foreach ($data['by_type'] as $type) {
            $rows[] = [$type['type'], $type['count'], $this->formatBytes($type['size'])];
        }

        $rows[] = [];
        $rows[] = ['Uso por Estudiante'];
        $rows[] = ['Nombre', 'Email', 'Archivos', 'Espacio'];

        foreach ($data['by_student'] as $student) {
            $rows[] = [$student['name'], $student['email'], $student['count'], $this->formatBytes($student['size'])];
        }

        return $rows;
    }

    private function getAnalyticsExcelRows(array $data): array
    {
        $rows = [
            ['Reporte de Analíticas - Folium UNIMAR'],
            ['Generado: ' . ($data['generated_at'] ?? now()->format('d/m/Y H:i'))],
            ['Período:', $data['date_from'] ?? '—', 'al', $data['date_to'] ?? '—'],
            [],
            ['Métricas Generales'],
            ['Usuarios Activos', 'Vistas', 'Usuarios Nuevos', 'Duración Promedio', 'Rebote', 'Engagement'],
            [
                $data['totals']['activeUsers'] ?? 0,
                $data['totals']['screenPageViews'] ?? 0,
                $data['totals']['newUsers'] ?? 0,
                ($data['totals']['averageSessionDuration'] ?? 0) . 's',
                $data['totals']['bounceRate'] ?? '0%',
                $data['engagement_rate'] ?? '0%',
            ],
            [],
            ['Dispositivos'],
            ['Dispositivo', 'Usuarios'],
        ];

        foreach ($data['devices'] as $device) {
            $rows[] = [$device['name'], $device['value']];
        }

        $rows[] = [];
        $rows[] = ['Países'];
        $rows[] = ['País', 'Usuarios'];

        foreach ($data['countries'] as $country) {
            $rows[] = [$country['code'], $country['users']];
        }

        $rows[] = [];
        $rows[] = ['Páginas Más Visitadas'];
        $rows[] = ['Ruta', 'Vistas'];

        foreach ($data['top_pages'] as $page) {
            $rows[] = [$page['path'], $page['views']];
        }

        $rows[] = [];
        $rows[] = ['Eventos'];
        $rows[] = ['Evento', 'Conteo'];

        foreach ($data['events'] as $event) {
            $rows[] = [$event['name'], $event['count']];
        }

        return $rows;
    }

    private function average($values): string
    {
        $values = $values->filter(fn($v) => is_numeric($v));
        if ($values->isEmpty()) return '—';
        if ($values->first() > 1) {
            return number_format($values->average(), 1);
        }
        return number_format($values->average() * 100, 1);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes) / log($k));
        return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}
