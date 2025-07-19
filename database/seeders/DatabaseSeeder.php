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
        // Crear usuarios de prueba
        $usuario1 = User::create([
            'nombre' => 'Ana García',
            'email' => 'ana@kompapay.com',
            'password' => Hash::make('password123'),
            'id_publico' => Str::uuid(),
            'ultima_sync' => Carbon::now(),
        ]);

        $usuario2 = User::create([
            'nombre' => 'Carlos López',
            'email' => 'carlos@kompapay.com',
            'password' => Hash::make('password123'),
            'id_publico' => Str::uuid(),
            'ultima_sync' => Carbon::now(),
        ]);

        $usuario3 = User::create([
            'nombre' => 'María Rodríguez',
            'email' => 'maria@kompapay.com',
            'password' => Hash::make('password123'),
            'id_publico' => Str::uuid(),
            'ultima_sync' => Carbon::now(),
        ]);

        // Crear grupos de prueba
        $grupo1 = Grupo::create([
            'nombre' => 'Vacaciones en Cancún',
            'creado_por' => $usuario1->id,
            'id_publico' => Str::uuid(),
            'fecha_creacion' => Carbon::now(),
        ]);

        $grupo2 = Grupo::create([
            'nombre' => 'Cena de Cumpleaños',
            'creado_por' => $usuario2->id,
            'id_publico' => Str::uuid(),
            'fecha_creacion' => Carbon::now(),
        ]);

        // Agregar miembros a los grupos
        $grupo1->miembros()->attach([$usuario1->id, $usuario2->id, $usuario3->id]);
        $grupo2->miembros()->attach([$usuario1->id, $usuario2->id]);

        // Crear gastos de prueba
        $gasto1 = Gasto::create([
            'grupo_id' => $grupo1->id,
            'descripcion' => 'Hotel Resort - 3 noches',
            'monto' => 1500.00,
            'pagado_por' => $usuario1->id,
            'modificado_por' => $usuario1->id,
            'id_publico' => Str::uuid(),
            'tipo_division' => 'equitativa',
            'fecha_creacion' => Carbon::now(),
            'ultima_modificacion' => Carbon::now(),
        ]);

        $gasto2 = Gasto::create([
            'grupo_id' => $grupo1->id,
            'descripcion' => 'Vuelos México-Cancún',
            'monto' => 900.00,
            'pagado_por' => $usuario2->id,
            'modificado_por' => $usuario2->id,
            'id_publico' => Str::uuid(),
            'tipo_division' => 'equitativa',
            'fecha_creacion' => Carbon::now(),
            'ultima_modificacion' => Carbon::now(),
        ]);

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

        $this->command->info('✅ Datos de prueba creados exitosamente:');
        $this->command->info("👤 Usuario 1: {$usuario1->email} (Ana García)");
        $this->command->info("👤 Usuario 2: {$usuario2->email} (Carlos López)");
        $this->command->info("👤 Usuario 3: {$usuario3->email} (María Rodríguez)");
        $this->command->info("🏖️ Grupo 1: {$grupo1->nombre} (ID público: {$grupo1->id_publico})");
        $this->command->info("🎂 Grupo 2: {$grupo2->nombre} (ID público: {$grupo2->id_publico})");
        $this->command->info("💰 3 gastos creados con participantes");
        $this->command->info("🔑 Contraseña para todos: password123");
    }
}
