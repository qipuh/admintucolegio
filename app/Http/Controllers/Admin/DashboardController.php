<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SupportTicket;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $schools = School::orderBy('name')->get();

        $rows = $schools->map(function (School $s) {
            $st = $s->stats ?? [];
            $adoption = Arr::get($st, 'usage.adoption.families', ['total' => 0, 'with_app' => 0]);

            return [
                'id' => $s->id,
                'name' => $s->name,
                'domain' => $s->domain,
                'logo_url' => $s->logo_url,
                'is_active' => $s->is_active,
                'has_api_key' => $s->has_api_key,
                'sync_status' => $s->sync_status,
                'synced_at' => $s->synced_at,
                'users' => Arr::get($st, 'community.users'),
                'enrolled' => Arr::get($st, 'community.enrolled'),
                'teachers' => Arr::get($st, 'community.teachers_active'),
                'users_with_app' => Arr::get($st, 'usage.users_with_app'),
                'app_active_7d' => Arr::get($st, 'usage.app_active_7d'),
                'web_active_7d' => Arr::get($st, 'usage.web_active_7d'),
                'family_adoption' => $adoption['total'] > 0 ? round($adoption['with_app'] * 100 / $adoption['total'], 1) : null,
            ];
        });

        $synced = $schools->filter(fn ($s) => $s->stats);
        $sum = fn (string $path) => $synced->sum(fn ($s) => (int) Arr::get($s->stats, $path, 0));

        $families = $synced->reduce(function ($carry, $s) {
            $a = Arr::get($s->stats, 'usage.adoption.families', []);

            return [$carry[0] + ($a['total'] ?? 0), $carry[1] + ($a['with_app'] ?? 0)];
        }, [0, 0]);

        $platforms = [];
        foreach ($synced as $s) {
            foreach (Arr::get($s->stats, 'usage.devices_by_platform', []) as $platform => $n) {
                $platforms[$platform] = ($platforms[$platform] ?? 0) + $n;
            }
        }

        return Inertia::render('Dashboard', [
            'totals' => [
                'schools' => $schools->count(),
                'schools_active' => $schools->where('is_active', true)->count(),
                'schools_synced' => $synced->count(),
                'users' => $sum('community.users'),
                'students' => $sum('community.students'),
                'enrolled' => $sum('community.enrolled'),
                'teachers' => $sum('community.teachers_active'),
                'users_with_app' => $sum('usage.users_with_app'),
                'app_active_7d' => $sum('usage.app_active_7d'),
                'app_active_30d' => $sum('usage.app_active_30d'),
                'web_active_7d' => $sum('usage.web_active_7d'),
                'push_30d' => $sum('activity.push_30d'),
                'families' => $families[0],
                'families_with_app' => $families[1],
                'devices_by_platform' => $platforms,
            ],
            'schools' => $rows,
            'charts' => $synced->values()->map(fn (School $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'roles' => Arr::get($s->stats, 'community.users_by_role', []),
                'levels' => Arr::get($s->stats, 'community.enrolled_by_level', []),
                'adoption' => Arr::get($s->stats, 'usage.adoption', []),
                'attendance_daily' => Arr::get($s->stats, 'series.student_attendance_daily', []),
                'teacher_daily' => Arr::get($s->stats, 'series.teacher_attendance_daily', []),
                'installs_weekly' => Arr::get($s->stats, 'series.installs_weekly', []),
            ]),
            'alerts' => $this->alerts($schools),
            'tickets' => [
                'open' => SupportTicket::open()->count(),
                'urgent' => SupportTicket::open()->whereIn('priority', ['alta', 'urgente'])->count(),
                'recent' => SupportTicket::open()->with('school:id,name')
                    ->orderByRaw("FIELD(priority, 'urgente', 'alta', 'media', 'baja')")
                    ->latest()->limit(5)
                    ->get(['id', 'school_id', 'title', 'priority', 'status', 'created_at']),
            ],
        ]);
    }

    /** Señales que requieren atención de soporte, a partir de la última foto. */
    private function alerts($schools): array
    {
        $alerts = [];

        foreach ($schools->where('is_active', true) as $s) {
            $h = $s->health ?? [];
            $push = function (string $level, string $message) use (&$alerts, $s) {
                $alerts[] = [
                    'school_id' => $s->id,
                    'school' => $s->name,
                    'level' => $level,
                    'message' => $message,
                ];
            };

            if (! $s->has_api_key) {
                $push('info', 'Sin clave de integrador: no se reciben estadísticas.');
                continue;
            }
            if ($s->sync_status === 'error') {
                $push('error', 'No se pudo sincronizar: '.$s->sync_error);
            } elseif ($s->synced_at && $s->synced_at->lt(now()->subHours(3))) {
                $push('warning', 'Datos desactualizados (última sincronización '.$s->synced_at->diffForHumans().').');
            }
            if (! $h) {
                continue;
            }
            if (! Arr::get($h, 'services.database', true)) {
                $push('error', 'Base de datos no responde.');
            }
            if (Arr::get($h, 'app.debug') && Arr::get($h, 'app.env') === 'production') {
                $push('error', 'APP_DEBUG activo en producción.');
            }
            if (($n = Arr::get($h, 'queue.failed_24h', 0)) > 0) {
                $push('warning', "{$n} trabajo(s) en cola fallaron en las últimas 24 h.");
            }
            if (($free = Arr::get($h, 'disk.free_bytes')) && ($total = Arr::get($h, 'disk.total_bytes')) && $free / $total < 0.1) {
                $push('error', 'Disco casi lleno ('.round($free * 100 / $total).'% libre).');
            }
            $wa = Arr::get($s->stats, 'activity.whatsapp_30d', []);
            $waTotal = array_sum($wa);
            if ($waTotal >= 20 && ($wa['failed'] ?? 0) / $waTotal > 0.3) {
                $push('warning', 'WhatsApp: '.round($wa['failed'] * 100 / $waTotal).'% de envíos fallidos en 30 días.');
            }
        }

        $order = ['error' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

        return $alerts;
    }
}
