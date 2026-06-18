<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alertas (Fase 5). Dos tablas, ambas aisladas por tenant:
     *  - alert_rules:  la regla configurada ("ventas cayeron X% vs. mes anterior").
     *  - alert_events: cada disparo de una regla, idempotente por (regla, periodo).
     */
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // Medida observada: 'monto' (ventas) | 'ordenes'.
            $table->string('measure', 20)->default('monto');
            // Dirección que dispara: 'drop' (cae) | 'rise' (sube).
            $table->string('direction', 10)->default('drop');
            // Umbral en % de variación intermensual.
            $table->decimal('threshold_pct', 6, 2);

            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'enabled']);
        });

        Schema::create('alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();

            // Periodo (YYYY-MM) cuya variación rompió el umbral.
            $table->string('period', 7);
            $table->string('measure', 20);
            $table->decimal('observed', 18, 2);
            $table->decimal('previous', 18, 2);
            $table->decimal('change_pct', 7, 2);
            $table->string('message');
            $table->timestamps();

            // Un disparo por regla y periodo: la evaluación es idempotente.
            $table->unique(['alert_rule_id', 'period']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rules');
    }
};
