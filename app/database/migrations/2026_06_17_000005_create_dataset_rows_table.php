<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filas crudas de un dataset, ya normalizadas a los campos canónicos
     * (data JSON). La Fase 3 las transformará en hechos/dimensiones; aquí solo
     * las aterrizamos de forma genérica y aislada por tenant.
     */
    public function up(): void
    {
        Schema::create('dataset_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->timestamp('created_at')->nullable();

            $table->index(['dataset_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_rows');
    }
};
