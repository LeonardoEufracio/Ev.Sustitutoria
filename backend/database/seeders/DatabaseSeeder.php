<?php

namespace Database\Seeders;

use App\Models\{Area, Employee, Role, Vehicle};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ADMINISTRADOR', 'COORDINADOR', 'CONDUCTOR', 'SOLICITANTE'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $area = Area::firstOrCreate(['name' => 'Administración']);
        Employee::firstOrCreate(
            ['dni' => '12345678'],
            ['area_id' => $area->id, 'full_name' => 'Usuario Solicitante', 'position' => 'Analista', 'phone' => '999111222']
        );
        Vehicle::firstOrCreate(
            ['plate' => 'ABC-123'],
            ['brand' => 'Toyota', 'model' => 'Hilux', 'year' => 2024, 'passenger_capacity' => 5, 'vehicle_type' => 'Camioneta', 'status' => 'Disponible']
        );
    }
}
