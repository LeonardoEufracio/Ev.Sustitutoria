<?php

namespace App\Console\Commands;

use App\Models\{Driver, Employee, Role, User};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    protected $signature = 'user:create';
    protected $description = 'Crea un usuario guardando únicamente el hash de su contraseña en la base de datos';

    public function handle(): int
    {
        $roles = Role::orderBy('name')->pluck('name')->all();
        if ($roles === []) {
            $this->error('Primero ejecute php artisan migrate --seed.');
            return self::FAILURE;
        }

        $name = $this->ask('Nombre completo');
        $email = $this->ask('Correo electrónico');
        $roleName = $this->choice('Rol', $roles);
        $password = $this->secret('Contraseña (mínimo 12 caracteres)');
        $confirmation = $this->secret('Confirme la contraseña');

        $validation = Validator::make(
            compact('name', 'email', 'password'),
            ['name' => 'required|max:150', 'email' => 'required|email|unique:users,email', 'password' => 'required|min:12']
        );
        if ($validation->fails() || $password !== $confirmation) {
            foreach ($validation->errors()->all() as $error) {
                $this->error($error);
            }
            if ($password !== $confirmation) {
                $this->error('Las contraseñas no coinciden.');
            }
            return self::FAILURE;
        }

        $employeeId = null;
        if ($roleName === 'SOLICITANTE') {
            $employee = Employee::where('dni', $this->ask('DNI del empleado existente'))->first();
            if (! $employee) {
                $this->error('No existe un empleado con ese DNI.');
                return self::FAILURE;
            }
            $employeeId = $employee->id;
        }

        $user = User::create([
            'role_id' => Role::where('name', $roleName)->value('id'),
            'employee_id' => $employeeId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'active' => true,
        ]);

        if ($roleName === 'CONDUCTOR') {
            Driver::create([
                'user_id' => $user->id,
                'dni' => $this->ask('DNI del conductor'),
                'full_name' => $name,
                'license' => $this->ask('Número de licencia'),
                'category' => $this->ask('Categoría de licencia'),
                'phone' => $this->ask('Teléfono'),
                'status' => 'Disponible',
            ]);
        }

        $this->info("Usuario {$email} creado. La base de datos conserva únicamente el hash bcrypt.");
        return self::SUCCESS;
    }
}
