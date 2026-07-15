<?php

use App\Http\Controllers\Api\SchoolController;
use Illuminate\Support\Facades\Route;

// Público, sin auth: la app Flutter lo consulta al abrir el selector de colegio.
Route::get('/schools', [SchoolController::class, 'index']);
