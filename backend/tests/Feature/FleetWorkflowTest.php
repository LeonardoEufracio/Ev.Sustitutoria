<?php

namespace Tests\Feature;

use App\Models\{Area, Assignment, Driver, Employee, Role, User, Vehicle, VehicleRequest};
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FleetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function driverScenario(int $capacity = 5): array
    {
        $driverRole = Role::create(['name' => 'CONDUCTOR']);
        $area = Area::create(['name' => 'Operaciones']);
        $employee = Employee::create(['area_id' => $area->id, 'dni' => '12345678', 'full_name' => 'Solicitante', 'position' => 'Analista', 'phone' => '999111222']);
        $user = User::create(['role_id' => $driverRole->id, 'name' => 'Conductor', 'email' => 'driver@test.local', 'password' => Hash::make('UnaClaveSegura123!'), 'active' => true]);
        $driver = Driver::create(['user_id' => $user->id, 'dni' => '87654321', 'full_name' => 'Conductor', 'license' => 'Q12345678', 'category' => 'A-IIb', 'phone' => '999222333', 'status' => 'Ocupado']);
        $vehicle = Vehicle::create(['plate' => 'ABC-123', 'brand' => 'Toyota', 'model' => 'Hilux', 'year' => 2024, 'passenger_capacity' => $capacity, 'vehicle_type' => 'Camioneta', 'status' => 'Asignado']);
        $request = VehicleRequest::create(['code' => 'SOL-TEST-001', 'employee_id' => $employee->id, 'origin' => 'Oficina', 'destination' => 'Aeropuerto', 'service_date' => now()->addDay()->toDateString(), 'required_time' => '10:00', 'passenger_count' => 3, 'reason' => 'Comisión institucional', 'registered_at' => now(), 'status' => 'Programada', 'subject_to_availability' => false]);
        $assignment = Assignment::create(['vehicle_request_id' => $request->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(12, 0), 'status' => 'Programada']);
        return compact('user', 'driver', 'vehicle', 'request', 'assignment');
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->issue($user)];
    }

    public function test_driver_mileage_is_calculated_and_resources_are_released(): void
    {
        $data = $this->driverScenario();

        $this->withHeaders($this->bearer($data['user']))->postJson('/api/kilometraje', [
            'assignment_id' => $data['assignment']->id,
            'initial' => 1250.25,
        ])->assertOk()->assertJsonPath('data.kilometers_traveled', null);

        $this->assertDatabaseHas('assignments', ['id' => $data['assignment']->id, 'status' => 'En ruta']);
        $this->assertDatabaseHas('vehicles', ['id' => $data['vehicle']->id, 'status' => 'En ruta']);
        $this->assertDatabaseHas('vehicle_departures', ['assignment_id' => $data['assignment']->id, 'initial_mileage' => 1250.25]);

        $this->withHeaders($this->bearer($data['user']))->postJson('/api/kilometraje', [
            'assignment_id' => $data['assignment']->id,
            'final' => 1298.75,
        ])->assertOk()->assertJsonPath('data.kilometers_traveled', 48.5);

        $this->withHeaders($this->bearer($data['user']))->patchJson('/api/conductor/finalizar-servicio/'.$data['assignment']->id)
            ->assertOk()->assertJsonPath('mileage.kilometers_traveled', 48.5);

        $this->assertDatabaseHas('assignments', ['id' => $data['assignment']->id, 'status' => 'Atendida']);
        $this->assertDatabaseHas('vehicles', ['id' => $data['vehicle']->id, 'status' => 'Disponible']);
        $this->assertDatabaseHas('drivers', ['id' => $data['driver']->id, 'status' => 'Disponible']);
        $this->assertDatabaseHas('vehicle_requests', ['id' => $data['request']->id, 'status' => 'Atendida']);
    }

    public function test_final_odometer_cannot_be_lower_than_initial(): void
    {
        $data = $this->driverScenario();
        $headers = $this->bearer($data['user']);
        $this->withHeaders($headers)->postJson('/api/kilometraje', ['assignment_id' => $data['assignment']->id, 'initial' => 500])->assertOk();
        $this->withHeaders($headers)->postJson('/api/kilometraje', ['assignment_id' => $data['assignment']->id, 'final' => 499.99])
            ->assertStatus(422)->assertJsonPath('message', 'El kilometraje final no puede ser menor al inicial.');
        $this->assertDatabaseMissing('vehicle_returns', ['assignment_id' => $data['assignment']->id]);
    }

    public function test_another_driver_cannot_modify_the_route(): void
    {
        $data = $this->driverScenario();
        $role = Role::where('name', 'CONDUCTOR')->first();
        $other = User::create(['role_id' => $role->id, 'name' => 'Otro', 'email' => 'other@test.local', 'password' => Hash::make('UnaClaveSegura456!'), 'active' => true]);
        Driver::create(['user_id' => $other->id, 'dni' => '11223344', 'full_name' => 'Otro', 'license' => 'Q87654321', 'category' => 'A-IIb', 'phone' => '999444555', 'status' => 'Disponible']);
        $this->withHeaders($this->bearer($other))->postJson('/api/kilometraje', ['assignment_id' => $data['assignment']->id, 'initial' => 100])
            ->assertForbidden();
    }
}
