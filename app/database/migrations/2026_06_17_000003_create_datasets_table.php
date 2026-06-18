<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entidad mínima de datos pertenecientes a un tenant. Sirve en la Fase 1
     * para demostrar el aislamiento; la Fase 2 (Ingesta) la extiende con el
     * detalle real del procesamiento de CSV.
     */
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('ready');
            $table->unsignedBigInteger('rows')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
