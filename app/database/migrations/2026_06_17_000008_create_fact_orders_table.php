<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hecho transaccional: una fila = una línea de orden de compra.
     * Apunta a las dimensiones por surrogate key. Aislado por tenant
     * (organization_id) y trazable a su dataset de origen.
     */
    public function up(): void
    {
        Schema::create('fact_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();

            $table->foreignId('date_id')->constrained('dim_date');
            $table->foreignId('product_id')->constrained('dim_product');
            $table->foreignId('supplier_id')->constrained('dim_supplier');
            $table->foreignId('entity_id')->nullable()->constrained('dim_entity');
            $table->foreignId('region_id')->nullable()->constrained('dim_region');

            // Medidas aditivas.
            $table->decimal('monto', 15, 2);
            $table->decimal('cantidad', 15, 2)->nullable();

            $table->index(['organization_id', 'date_id']);
            $table->index(['organization_id', 'product_id']);
            $table->index(['organization_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_orders');
    }
};
