<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Schools/Index', [
            'schools' => School::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Schools/Form', [
            'school' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        School::create($data);

        return redirect()->route('admin.schools.index')->with('success', 'Colegio creado correctamente.');
    }

    public function edit(School $school)
    {
        return Inertia::render('Admin/Schools/Form', [
            'school' => $school,
        ]);
    }

    public function update(Request $request, School $school)
    {
        $data = $this->validated($request, $school->id);
        $school->update($data);

        return redirect()->route('admin.schools.index')->with('success', 'Colegio actualizado correctamente.');
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
            'is_active' => ['boolean'],
        ]);
    }
}
