<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        // No tocar datos existentes: esta migracion queda como marca historica.
        // Los datos de Railway deben conservarse entre commits y despliegues.
    }

    public function down(): void
    {
        // No hay cambios de esquema ni datos que revertir.
    }
};
