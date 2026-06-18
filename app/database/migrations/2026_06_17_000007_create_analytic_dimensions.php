<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensiones por-tenant del hecho de órdenes de compra: producto,
     * proveedor, entidad y región. Clave natural única por (tenant, nombre);
     * la PK es una surrogate key referenciada por fact_orders.
     */
    public function up(): void
    {
        foreach (['dim_product', 'dim_supplier', 'dim_entity', 'dim_region'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['organization_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        foreach (['dim_product', 'dim_supplier', 'dim_entity', 'dim_region'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
