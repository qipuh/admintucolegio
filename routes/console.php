<?php

use Illuminate\Foundation\Inspiring;
use App\Models\School;
use App\Services\SchoolSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('schools:sync {school? : ID del colegio (vacío = todos los activos)}', function (SchoolSyncService $sync) {
    $schools = $this->argument('school')
        ? School::whereKey($this->argument('school'))->get()
        : School::where('is_active', true)->get();

    foreach ($schools as $school) {
        $ok = $sync->sync($school);
        $ok
            ? $this->info("✓ {$school->name}")
            : $this->error("✗ {$school->name}: {$school->fresh()->sync_error}");
    }
})->purpose('Trae estadísticas y estado técnico de los colegios');

Schedule::command('schools:sync')->hourly()->withoutOverlapping();
