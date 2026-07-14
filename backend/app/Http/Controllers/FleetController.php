<?php

namespace App\Http\Controllers;

use App\Models\{Assignment, Audit, Driver, Mileage, Vehicle, VehicleRequest};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FleetController extends Controller
{
    private function audit(Request $request, string $action, string $entity, ?int $id = null, array $details = []): void
    {
        Audit::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $id,
            'details' => $details,
            'ip' => $request->ip(),
        ]);
    }

    public function requests(Request $request)
    {
        $query = VehicleRequest::with('employee.area')->latest();
        if ($request->user()->role->name === 'SOLICITANTE') {
            $query->where('employee_id', $request->user()->employee_id);
        }
        return $query->paginate();
    }

    public function pending()
    {
        return VehicleRequest::with('employee.area')
            ->where('status', 'Pendiente')
            ->orderBy('service_date')
            ->orderBy('required_time')
            ->get();
    }

    public function requestShow(Request $request, int $id)
    {
        $vehicleRequest = VehicleRequest::with('employee.area')->findOrFail($id);
        if ($request->user()->role->name === 'SOLICITANTE' && $vehicleRequest->employee_id !== $request->user()->employee_id) {
            abort(403, 'No puede consultar solicitudes de otro empleado.');
        }
        return $vehicleRequest;
    }

    public function requestStore(Request $request)
    {
        abort_unless($request->user()->employee_id, 422, 'El usuario no está vinculado a un empleado.');
        $data = $request->validate([
            'origin' => 'required|string|max:200|different:destination',
            'destination' => 'required|string|max:200|different:origin',
            'service_date' => 'required|date|after_or_equal:today',
            'required_time' => 'required|date_format:H:i',
            'passenger_count' => 'required|integer|min:1|max:100',
            'reason' => 'required|string|min:10|max:2000',
        ]);

        $now = now();
        $insideBusinessHours = $now->isWeekday() && $now->between(
            $now->copy()->setTime(8, 0),
            $now->copy()->setTime(16, 0),
            true
        );

        $vehicleRequest = DB::transaction(function () use ($request, $data, $now, $insideBusinessHours) {
            $vehicleRequest = VehicleRequest::create($data + [
                'employee_id' => $request->user()->employee_id,
                'code' => 'SOL-'.$now->format('Ymd-His').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                'registered_at' => $now,
                'status' => 'Pendiente',
                'subject_to_availability' => ! $insideBusinessHours,
            ]);
            $this->audit($request, 'CREAR', 'solicitud', $vehicleRequest->id, [
                'subject_to_availability' => ! $insideBusinessHours,
            ]);
            return $vehicleRequest;
        });

        return response()->json([
            'data' => $vehicleRequest,
            'message' => $insideBusinessHours
                ? 'Solicitud registrada correctamente.'
                : 'Solicitud registrada fuera del horario regular y sujeta a disponibilidad.',
        ], 201);
    }

    public function requestStatus(Request $request, int $id, string $status)
    {
        $data = $request->validate([
            'reason' => [Rule::requiredIf($status === 'Cancelada'), 'nullable', 'string', 'min:5', 'max:500'],
        ]);

        return DB::transaction(function () use ($request, $id, $status, $data) {
            $vehicleRequest = VehicleRequest::lockForUpdate()->findOrFail($id);
            abort_unless($vehicleRequest->status === 'Pendiente', 422, 'Solo se pueden evaluar solicitudes pendientes.');
            $vehicleRequest->update([
                'status' => $status,
                'rejection_reason' => $status === 'Cancelada' ? $data['reason'] : null,
            ]);
            $this->audit($request, strtoupper($status), 'solicitud', $vehicleRequest->id, $data);
            return $vehicleRequest;
        });
    }

    public function vehicles(Request $request)
    {
        return Vehicle::when($request->boolean('available'), fn ($query) => $query->where('status', 'Disponible'))->get();
    }

    public function vehicleSave(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'plate' => ['required', 'string', 'max:10', Rule::unique('vehicles')->ignore($id)],
            'brand' => 'required|string|max:80',
            'model' => 'required|string|max:80',
            'year' => 'required|integer|min:1980|max:'.(now()->year + 1),
            'passenger_capacity' => 'required|integer|min:1|max:100',
            'vehicle_type' => 'required|string|max:50',
            'status' => 'sometimes|in:Disponible,Asignado,En ruta,Mantenimiento,Inoperativo',
        ]);
        $vehicle = Vehicle::updateOrCreate(['id' => $id], $data);
        $this->audit($request, $id ? 'ACTUALIZAR' : 'CREAR', 'vehiculo', $vehicle->id);
        return $vehicle;
    }

    public function vehicleStatus(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:Disponible,Asignado,En ruta,Mantenimiento,Inoperativo']);
        $vehicle = Vehicle::findOrFail($id);
        if (in_array($data['status'], ['Disponible', 'Mantenimiento', 'Inoperativo'], true)) {
            $active = Assignment::where('vehicle_id', $id)->whereIn('status', ['Programada', 'En ruta'])->exists();
            abort_if($active, 422, 'No puede cambiar el estado de un vehículo con un servicio activo.');
        }
        $vehicle->update($data);
        $this->audit($request, 'CAMBIAR_ESTADO', 'vehiculo', $id, $data);
        return $vehicle;
    }

    public function drivers(Request $request)
    {
        return Driver::when($request->boolean('available'), fn ($query) => $query->where('status', 'Disponible'))->get();
    }

    public function driverSave(Request $request, ?int $id = null)
    {
        $data = $request->validate([
            'dni' => ['required', 'digits:8', Rule::unique('drivers')->ignore($id)],
            'full_name' => 'required|string|max:150',
            'license' => ['required', 'string', 'max:30', Rule::unique('drivers')->ignore($id)],
            'category' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'status' => 'sometimes|in:Disponible,Ocupado,Descanso',
        ]);
        $driver = Driver::updateOrCreate(['id' => $id], $data);
        $this->audit($request, $id ? 'ACTUALIZAR' : 'CREAR', 'conductor', $driver->id);
        return $driver;
    }

    public function driverStatus(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:Disponible,Ocupado,Descanso']);
        if (in_array($data['status'], ['Disponible', 'Descanso'], true)) {
            $active = Assignment::where('driver_id', $id)->whereIn('status', ['Programada', 'En ruta'])->exists();
            abort_if($active, 422, 'No puede cambiar el estado de un conductor con un servicio activo.');
        }
        $driver = Driver::findOrFail($id);
        $driver->update($data);
        $this->audit($request, 'CAMBIAR_ESTADO', 'conductor', $id, $data);
        return $driver;
    }

    public function schedule(Request $request)
    {
        $data = $request->validate([
            'vehicle_request_id' => 'required|exists:vehicle_requests,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:drivers,id',
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
            'instructions' => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $vehicleRequest = VehicleRequest::lockForUpdate()->findOrFail($data['vehicle_request_id']);
            $vehicle = Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);
            $driver = Driver::lockForUpdate()->findOrFail($data['driver_id']);

            abort_unless($vehicleRequest->status === 'Evaluada', 422, 'La solicitud debe estar aprobada.');
            abort_unless($vehicle->status === 'Disponible', 422, 'El vehículo debe estar disponible.');
            abort_unless($driver->status === 'Disponible', 422, 'El conductor debe estar disponible.');
            abort_if($vehicleRequest->passenger_count > $vehicle->passenger_capacity, 422, 'La cantidad de pasajeros supera la capacidad del vehículo.');
            abort_unless(Carbon::parse($data['starts_at'])->toDateString() === $vehicleRequest->service_date->toDateString(), 422, 'La programación debe corresponder a la fecha solicitada.');

            $overlaps = fn ($query) => $query
                ->where('starts_at', '<', $data['ends_at'])
                ->where('ends_at', '>', $data['starts_at'])
                ->whereIn('status', ['Programada', 'En ruta'])
                ->exists();
            abort_if($overlaps(Assignment::where('vehicle_id', $vehicle->id)), 422, 'El vehículo tiene un horario cruzado.');
            abort_if($overlaps(Assignment::where('driver_id', $driver->id)), 422, 'El conductor tiene un servicio simultáneo.');

            $assignment = Assignment::create($data + ['status' => 'Programada']);
            $assignment->route()->create([
                'origin' => $vehicleRequest->origin,
                'destination' => $vehicleRequest->destination,
                'instructions' => $data['instructions'] ?? null,
            ]);
            $vehicleRequest->update(['status' => 'Programada']);
            $vehicle->update(['status' => 'Asignado']);
            $driver->update(['status' => 'Ocupado']);
            $this->audit($request, 'ASIGNAR', 'asignacion', $assignment->id, $data);
            return $assignment->load('request', 'vehicle', 'driver', 'route', 'mileage');
        });
    }

    public function assignments()
    {
        return Assignment::with('request.employee', 'vehicle', 'driver', 'route', 'mileage')->latest()->get();
    }

    public function assignment(Request $request, int $id)
    {
        $assignment = Assignment::with('request.employee.area', 'vehicle', 'driver', 'route', 'mileage')->findOrFail($id);
        if ($request->user()->role->name === 'CONDUCTOR') {
            $driverId = Driver::where('user_id', $request->user()->id)->value('id');
            abort_unless($assignment->driver_id === $driverId, 403, 'La ruta no pertenece al conductor autenticado.');
        }
        return $assignment;
    }

    public function driverRoutes(Request $request)
    {
        $driver = Driver::where('user_id', $request->user()->id)->firstOrFail();
        return Assignment::with('request', 'vehicle', 'route', 'mileage')
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['Programada', 'En ruta'])
            ->orderBy('starts_at')
            ->get();
    }

    public function mileage(Request $request)
    {
        $data = $request->validate([
            'assignment_id' => 'required|exists:assignments,id',
            'initial' => 'nullable|required_without:final|numeric|min:0',
            'final' => 'nullable|required_without:initial|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $data) {
            $assignment = Assignment::lockForUpdate()->with('vehicle', 'driver')->findOrFail($data['assignment_id']);
            $authenticatedDriver = Driver::where('user_id', $request->user()->id)->firstOrFail();
            abort_unless($assignment->driver_id === $authenticatedDriver->id, 403, 'El servicio no pertenece al conductor autenticado.');
            abort_if(in_array($assignment->status, ['Atendida', 'Cancelada'], true), 422, 'El servicio ya está cerrado.');

            $mileage = Mileage::firstOrNew(['assignment_id' => $assignment->id]);
            if (array_key_exists('initial', $data)) {
                abort_if($mileage->initial !== null, 422, 'El kilometraje inicial ya fue registrado.');
                abort_unless($assignment->status === 'Programada', 422, 'Solo una ruta programada puede iniciarse.');

                $lastFinal = Mileage::query()
                    ->join('assignments', 'assignments.id', '=', 'mileages.assignment_id')
                    ->where('assignments.vehicle_id', $assignment->vehicle_id)
                    ->whereNotNull('mileages.final')
                    ->max('mileages.final');
                abort_if($lastFinal !== null && (float) $data['initial'] < (float) $lastFinal, 422, "El odómetro inicial no puede ser menor al último registro ({$lastFinal} km).");

                $mileage->initial = $data['initial'];
                $mileage->save();
                DB::table('vehicle_departures')->insert([
                    'assignment_id' => $assignment->id,
                    'departed_at' => now(),
                    'initial_mileage' => $data['initial'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $assignment->update(['status' => 'En ruta']);
                $assignment->vehicle->update(['status' => 'En ruta']);
                $this->audit($request, 'INICIAR_RUTA', 'asignacion', $assignment->id, ['initial' => $data['initial']]);
            }

            if (array_key_exists('final', $data)) {
                abort_unless($assignment->status === 'En ruta', 422, 'Debe iniciar la ruta antes de registrar el kilometraje final.');
                abort_if($mileage->initial === null, 422, 'Primero registre el kilometraje inicial.');
                abort_if($mileage->final !== null, 422, 'El kilometraje final ya fue registrado.');
                abort_if((float) $data['final'] < (float) $mileage->initial, 422, 'El kilometraje final no puede ser menor al inicial.');

                $mileage->final = $data['final'];
                $mileage->save();
                DB::table('vehicle_returns')->insert([
                    'assignment_id' => $assignment->id,
                    'returned_at' => now(),
                    'final_mileage' => $data['final'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->audit($request, 'REGISTRAR_RETORNO', 'asignacion', $assignment->id, [
                    'final' => $data['final'],
                    'kilometers_traveled' => $mileage->kilometers_traveled,
                ]);
            }

            return response()->json([
                'data' => $mileage->fresh(),
                'message' => $mileage->final === null
                    ? 'Ruta iniciada correctamente.'
                    : 'Retorno registrado. El kilometraje fue calculado automáticamente.',
            ]);
        });
    }

    public function finish(Request $request, int $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $assignment = Assignment::lockForUpdate()->with('vehicle', 'driver', 'request', 'mileage')->findOrFail($id);
            $authenticatedDriver = Driver::where('user_id', $request->user()->id)->firstOrFail();
            abort_unless($assignment->driver_id === $authenticatedDriver->id, 403, 'El servicio no pertenece al conductor autenticado.');
            abort_unless($assignment->status === 'En ruta', 422, 'Solo se puede finalizar un servicio en ruta.');
            abort_unless($assignment->mileage?->initial !== null && $assignment->mileage?->final !== null, 422, 'Registre el odómetro inicial y final.');

            $assignment->update(['status' => 'Atendida']);
            $assignment->vehicle->update(['status' => 'Disponible']);
            $assignment->driver->update(['status' => 'Disponible']);
            $assignment->request->update(['status' => 'Atendida']);
            $this->audit($request, 'FINALIZAR', 'asignacion', $id, [
                'kilometers_traveled' => $assignment->mileage->kilometers_traveled,
            ]);
            return $assignment->fresh(['vehicle', 'driver', 'request', 'mileage']);
        });
    }

    public function dashboard()
    {
        $completed = Assignment::where('status', 'Atendida');
        $monthStart = now()->startOfMonth();
        $monthlyServices = (clone $completed)->where('updated_at', '>=', $monthStart)->count();
        $elapsedDays = max(1, $monthStart->diffInDays(now()) + 1);

        $vehicleStats = Vehicle::query()
            ->leftJoin('assignments', function ($join) {
                $join->on('assignments.vehicle_id', '=', 'vehicles.id')->where('assignments.status', '=', 'Atendida');
            })
            ->leftJoin('mileages', 'mileages.assignment_id', '=', 'assignments.id')
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.brand', 'vehicles.model')
            ->selectRaw('vehicles.id, vehicles.plate, vehicles.brand, vehicles.model, COUNT(assignments.id) as services, COALESCE(SUM(mileages.final - mileages.initial), 0) as kilometers')
            ->orderByDesc('services')->get();

        $driverStats = Driver::query()
            ->leftJoin('assignments', function ($join) {
                $join->on('assignments.driver_id', '=', 'drivers.id')->where('assignments.status', '=', 'Atendida');
            })
            ->leftJoin('mileages', 'mileages.assignment_id', '=', 'assignments.id')
            ->groupBy('drivers.id', 'drivers.full_name')
            ->selectRaw('drivers.id, drivers.full_name, COUNT(assignments.id) as services, COALESCE(SUM(mileages.final - mileages.initial), 0) as kilometers')
            ->orderByDesc('kilometers')->get();

        return [
            'requests' => [
                'pending' => VehicleRequest::where('status', 'Pendiente')->count(),
                'attended' => VehicleRequest::where('status', 'Atendida')->count(),
            ],
            'vehicles' => [
                'available' => Vehicle::where('status', 'Disponible')->count(),
                'maintenance' => Vehicle::where('status', 'Mantenimiento')->count(),
            ],
            'drivers_available' => Driver::where('status', 'Disponible')->count(),
            'monthly_services' => $monthlyServices,
            'daily_average' => round($monthlyServices / $elapsedDays, 2),
            'kilometers' => (float) Mileage::selectRaw('COALESCE(SUM(final-initial),0) total')->value('total'),
            'vehicle_stats' => $vehicleStats,
            'driver_stats' => $driverStats,
        ];
    }

    public function audits()
    {
        return Audit::with('user')->latest()->paginate();
    }
}
