<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\SchoolSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Asistencia docente de un colegio, de solo lectura: consulta en vivo la
 * misma matriz y el mismo Excel que /admin/asistencia-docente de tucolegio.
 */
class TeacherAttendanceController extends Controller
{
    private const FILTERS = ['mode', 'date', 'month', 'date_from', 'date_to', 'level', 'teacher_id', 'attendance'];

    public function index(Request $request, School $school, SchoolSyncService $api)
    {
        $filters = array_filter($request->only(self::FILTERS), fn ($v) => $v !== null && $v !== '');

        $data = null;
        $error = null;
        try {
            $data = $api->json($school, 'teacher-attendance', $filters);
        } catch (\Throwable $e) {
            $error = Str::limit($e->getMessage(), 400);
        }

        return Inertia::render('Admin/Schools/TeacherAttendance', [
            'school' => $school->only(['id', 'name', 'domain', 'logo_url']),
            'filters' => $filters,
            'data' => $data,
            'error' => $error,
        ]);
    }

    public function export(Request $request, School $school, SchoolSyncService $api)
    {
        $filters = array_filter($request->only(self::FILTERS), fn ($v) => $v !== null && $v !== '');

        try {
            $response = $api->get($school, 'teacher-attendance/export', $filters, 120);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo descargar el Excel: '.Str::limit($e->getMessage(), 300));
        }

        $filename = Str::slug($school->name).'-asistencia-docentes-'.now()->format('Y-m-d').'.xlsx';

        return response($response->body(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
