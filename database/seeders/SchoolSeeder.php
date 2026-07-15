<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $schools = [
            [
                'name' => 'I.E. Juan Clemente Vergel',
                'domain' => 'jcvex91.edu.pe',
                'api_base_url' => 'https://jcvex91.edu.pe/api/v1',
                'is_active' => true,
            ],
            [
                'name' => 'San Ramón - La Recoleta',
                'domain' => 'sanramonrecoleta.edu.pe',
                'api_base_url' => 'https://sanramonrecoleta.edu.pe/api/v1',
                'is_active' => true,
            ],
            [
                'name' => 'I.E. La Florida',
                'domain' => 'ielaflorida.edu.pe',
                'api_base_url' => 'https://ielaflorida.edu.pe/api/v1',
                'is_active' => true,
            ],
            [
                'name' => 'Desarrollo (tucolegio.test)',
                'domain' => 'tucolegio.test',
                'api_base_url' => 'https://tucolegio.test/api/v1',
                'is_active' => true,
            ],
        ];

        foreach ($schools as $school) {
            School::updateOrCreate(['domain' => $school['domain']], $school);
        }
    }
}
