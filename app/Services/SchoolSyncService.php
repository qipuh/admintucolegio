<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cliente de la API /api/v1/integrador/* de cada colegio (tucolegio, clave
 * X-Integrador-Key). sync() guarda la última foto de stats/health en schools;
 * si un colegio falla, se conserva la foto anterior y se marca el error.
 */
class SchoolSyncService
{
    public function sync(School $school): bool
    {
        if (! $school->has_api_key) {
            $school->forceFill([
                'sync_status' => 'error',
                'sync_error' => 'El colegio no tiene clave de integrador configurada.',
            ])->save();

            return false;
        }

        try {
            $stats = $this->json($school, 'stats');
            $health = $this->json($school, 'health');
        } catch (\Throwable $e) {
            $school->forceFill([
                'sync_status' => 'error',
                'sync_error' => Str::limit($e->getMessage(), 500),
            ])->save();

            return false;
        }

        $school->forceFill([
            'stats' => $stats,
            'health' => $health,
            'synced_at' => now(),
            'sync_status' => 'ok',
            'sync_error' => null,
        ])->save();

        return true;
    }

    /** GET a /integrador/{endpoint} con errores legibles. */
    public function get(School $school, string $endpoint, array $query = [], int $timeout = 20): Response
    {
        if (! $school->has_api_key) {
            throw new \RuntimeException('El colegio no tiene clave de integrador configurada.');
        }

        $response = Http::withHeaders(['X-Integrador-Key' => $school->api_key])
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions(['verify' => (bool) config('services.integrador.verify_ssl')])
            ->get(rtrim($school->api_base_url, '/').'/integrador/'.$endpoint, $query);

        if ($response->status() === 401) {
            throw new \RuntimeException('Clave de integrador rechazada (401). Revisa que coincida con INTEGRADOR_API_KEY del colegio.');
        }

        if ($response->status() === 404) {
            throw new \RuntimeException("API de integrador no disponible (404) en /integrador/{$endpoint}: el colegio no tiene esta versión o falta INTEGRADOR_API_KEY.");
        }

        $response->throw();

        return $response;
    }

    public function json(School $school, string $endpoint, array $query = []): array
    {
        $json = $this->get($school, $endpoint, $query)->json();

        if (! is_array($json)) {
            throw new \RuntimeException("Respuesta inválida de /integrador/{$endpoint}.");
        }

        return $json;
    }
}
