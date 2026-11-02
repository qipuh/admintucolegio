<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Clave X-Integrador-Key del colegio (cast encrypted en el modelo).
            $table->text('api_key')->nullable()->after('logo_url');
            // Última foto de /integrador/stats y /integrador/health.
            $table->json('stats')->nullable()->after('is_active');
            $table->json('health')->nullable()->after('stats');
            $table->timestamp('synced_at')->nullable()->after('health');
            $table->string('sync_status', 20)->nullable()->after('synced_at');
            $table->text('sync_error')->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['api_key', 'stats', 'health', 'synced_at', 'sync_status', 'sync_error']);
        });
    }
};
