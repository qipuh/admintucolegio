<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\SchoolSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Schools/Index', [
            'schools' => School::orderBy('name')->get()->makeHidden(['stats', 'health']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Schools/Form', [
            'school' => null,
        ]);
    }

    public function store(Request $request, SchoolSyncService $sync)
    {
        $data = $this->validated($request);
        $school = School::create($data);

        if ($school->has_api_key) {
            $sync->sync($school);
        }

        return redirect()->route('admin.schools.show', $school)->with('success', 'Colegio creado correctamente.');
    }

    public function show(School $school)
    {
        return Inertia::render('Admin/Schools/Show', [
            'school' => $school,
            'tickets' => $school->tickets()->latest()->limit(10)
                ->get(['id', 'school_id', 'title', 'priority', 'status', 'created_at']),
            'openTickets' => $school->tickets()->open()->count(),
        ]);
    }

    public function edit(School $school)
    {
        return Inertia::render('Admin/Schools/Form', [
            'school' => $school->makeHidden(['stats', 'health']),
        ]);
    }

    public function update(Request $request, School $school)
    {
        $data = $this->validated($request, $school->id);

        // Clave vacía = conservar la actual; "remove_api_key" la borra.
        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }
        if ($request->boolean('remove_api_key')) {
            $data['api_key'] = null;
        }

        $school->update($data);

        return redirect()->route('admin.schools.show', $school)->with('success', 'Colegio actualizado correctamente.');
    }

    public function destroy(School $school)
    {
        $school->delete();

        return redirect()->route('admin.schools.index')->with('success', 'Colegio eliminado.');
    }

    public function toggleActive(School $school)
    {
        $school->update(['is_active' => !$school->is_active]);

        return back();
    }

    public function sync(School $school, SchoolSyncService $sync)
    {
        $ok = $sync->sync($school);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Datos actualizados.' : 'No se pudo sincronizar: '.$school->sync_error,
        );
    }

    public function syncAll(SchoolSyncService $sync)
    {
        $schools = School::where('is_active', true)->get();
        $ok = $schools->filter(fn ($s) => $sync->sync($s))->count();

        return back()->with('success', "Sincronizados {$ok} de {$schools->count()} colegios.");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required', 'string', 'max:255',
                'unique:schools,domain' . ($ignoreId ? ",$ignoreId" : ''),
            ],
            'api_base_url' => ['required', 'string', 'max:255', 'url'],
            'logo_url' => ['nullable', 'string', 'max:255', 'url'],
            'api_key' => ['nullable', 'string', 'min:16', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
