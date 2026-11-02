<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;

class SchoolController extends Controller
{
    /**
     * Directorio público de colegios activos — lo consume la app Flutter
     * en el selector de colegio en vez de traer la lista hardcodeada.
     */
    public function index()
    {
        $schools = School::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'api_base_url', 'logo_url'])
            // has_api_key es interno del panel: la app solo necesita estos campos.
            ->each->setAppends([]);

        return response()->json(['schools' => $schools]);
    }
}
