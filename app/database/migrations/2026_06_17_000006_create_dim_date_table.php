<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensión de fecha conformada (global a todos los tenants: una fecha es
     * una fecha). Atributos derivados para drill-down temporal.
     */
    public function up(): void
    {
        Schema::create('dim_date', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->smallInteger('year');
            $table->smallInteger('quarter');
            $table->smallInteger('month');
            $table->string('month_name', 20);
            $table->smallInteger('day');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_date');
    }
};
