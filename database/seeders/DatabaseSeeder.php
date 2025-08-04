<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Grupo;
use App\Models\Gasto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Iniciando seeders...');
        
        // Crear usuarios de prueba (evitar duplicados)
        $usuario1 = User::firstOrCreate(
            ['email' => 'ana@kompapay.com'],
            [
                'nombre' => 'Ana García',
                'password' => Hash::make('password123'),
                'id_publico' => Str::uuid(),
                'ultima_sync' => Carbon::now(),
            ]
        );

        $usuario2 = User::firstOrCreate(
            ['email' => 'carlos@kompapay.com'],
            [
                'nombre' => 'Carlos López',
                'password' => Hash::make('password123'),
                'id_publico' => Str::uuid(),
                'ultima_sync' => Carbon::now(),
            ]
        );

        $usuario3 = User::firstOrCreate(
            ['email' => 'maria@kompapay.com'],
            [
                'nombre' => 'María Rodríguez',
                'password' => Hash::make('password123'),
                'id_publico' => Str::uuid(),
                'ultima_sync' => Carbon::now(),
            ]
        );

        $this->command->info("Usuarios creados: {$usuario1->nombre}, {$usuario2->nombre}, {$usuario3->nombre}");

        // Crear grupos de prueba (evitar duplicados)
        $grupo1 = Grupo::firstOrCreate(
            ['nombre' => 'Vacaciones en Cancún', 'creado_por' => $usuario1->id],
            [
                'id_publico' => Str::uuid(),
                'fecha_creacion' => Carbon::now(),
            ]
        );

        $grupo2 = Grupo::firstOrCreate(
            ['nombre' => 'Cena de Cumpleaños', 'creado_por' => $usuario2->id],
            [
                'id_publico' => Str::uuid(),
                'fecha_creacion' => Carbon::now(),
            ]
        );

        // Agregar miembros a los grupos (solo si no están ya agregados)
        if (!$grupo1->miembros()->where('user_id', $usuario1->id)->exists()) {
            $grupo1->miembros()->attach([$usuario1->id, $usuario2->id, $usuario3->id]);
        }
        if (!$grupo2->miembros()->where('user_id', $usuario1->id)->exists()) {
            $grupo2->miembros()->attach([$usuario1->id, $usuario2->id]);
        }

        $this->command->info("Grupos creados: {$grupo1->nombre}, {$grupo2->nombre}");

        // Crear gastos de prueba (evitar duplicados)
        $gasto1 = Gasto::firstOrCreate(
            ['grupo_id' => $grupo1->id, 'descripcion' => 'Hotel Resort - 3 noches'],
            [
                'monto' => 1500.00,
                'pagado_por' => $usuario1->id,
                'modificado_por' => $usuario1->id,
                'id_publico' => Str::uuid(),
                'tipo_division' => 'equitativa',
                'fecha_creacion' => Carbon::now(),
                'ultima_modificacion' => Carbon::now(),
            ]
        );

        $gasto2 = Gasto::firstOrCreate(
            ['grupo_id' => $grupo1->id, 'descripcion' => 'Vuelos México-Cancún'],
            [
                'monto' => 900.00,
                'pagado_por' => $usuario2->id,
                'modificado_por' => $usuario2->id,
                'id_publico' => Str::uuid(),
                'tipo_division' => 'equitativa',
                'fecha_creacion' => Carbon::now(),
                'ultima_modificacion' => Carbon::now(),
            ]
        );

        $gasto3 = Gasto::create([
            'grupo_id' => $grupo2->id,
            'descripcion' => 'Restaurante La Terraza',
            'monto' => 250.00,
            'pagado_por' => $usuario2->id,
            'modificado_por' => $usuario2->id,
            'id_publico' => Str::uuid(),
            'tipo_division' => 'equitativa',
            'fecha_creacion' => Carbon::now(),
            'ultima_modificacion' => Carbon::now(),
        ]);

        // Agregar participantes a los gastos con división proporcional
        // Gasto 1: Hotel - División igual entre 3 personas
        $gasto1->participantes()->sync([
            $usuario1->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 500.00,
                'pagado' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            $usuario2->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 500.00,
                'pagado' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            $usuario3->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 500.00,
                'pagado' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        // Gasto 2: Vuelos - División igual entre 3 personas
        $gasto2->participantes()->sync([
            $usuario1->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 300.00,
                'pagado' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            $usuario2->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 300.00,
                'pagado' => true,
                'fecha_pago' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            $usuario3->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 300.00,
                'pagado' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        // Gasto 3: Restaurante - División igual entre 2 personas
        $gasto3->participantes()->sync([
            $usuario1->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 125.00,
                'pagado' => true,
                'fecha_pago' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            $usuario2->id => [
                'id' => Str::uuid(),
                'monto_proporcional' => 125.00,
                'pagado' => true,
                'fecha_pago' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $this->command->info('Datos de prueba creados exitosamente:');
        $this->command->info('Usuario 1: ' . $usuario1->email . ' (Ana García)');
        $this->command->info('Usuario 2: ' . $usuario2->email . ' (Carlos López)');
        $this->command->info('Usuario 3: ' . $usuario3->email . ' (María Rodríguez)');
        $this->command->info('Grupo 1: ' . $grupo1->nombre . ' (ID público: ' . $grupo1->id_publico . ')');
        $this->command->info('Grupo 2: ' . $grupo2->nombre . ' (ID público: ' . $grupo2->id_publico . ')');
        $this->command->info('3 gastos creados con participantes');
        $this->command->info('Contraseña para todos: password123');
        
        // Estadísticas finales
        $totalUsuarios = User::count();
        $totalGrupos = Grupo::count();
        $totalGastos = Gasto::count();
        
        $this->command->info('RESUMEN FINAL:');
        $this->command->info("   Total usuarios: {$totalUsuarios}");
        $this->command->info("   Total grupos: {$totalGrupos}");
        $this->command->info("   Total gastos: {$totalGastos}");
        $this->command->info('Seeders ejecutados exitosamente!');
    }
}
