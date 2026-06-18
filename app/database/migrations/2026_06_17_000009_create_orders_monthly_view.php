<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregación mensual de órdenes por tenant. En Postgres es una VISTA
     * MATERIALIZADA (se refresca tras cada build de la estrella); en sqlite
     * (tests) es una vista normal con el mismo nombre y SELECT, para que la
     * capa de métricas consulte `mv_orders_monthly` de forma uniforme.
     */
    protected string $select = <<<'SQL'
        SELECT
            f.organization_id        AS organization_id,
            d.year                   AS year,
            d.month                  AS month,
            MIN(d.month_name)        AS month_name,
            SUM(f.monto)             AS monto,
            COUNT(*)                 AS ordenes,
            COALESCE(SUM(f.cantidad), 0) AS unidades
        FROM fact_orders f
        JOIN dim_date d ON d.id = f.date_id
        GROUP BY f.organization_id, d.year, d.month
    SQL;

    public function up(): void
    {
        if ($this->isPostgres()) {
            DB::statement("CREATE MATERIALIZED VIEW mv_orders_monthly AS {$this->select} WITH DATA");
            DB::statement('CREATE UNIQUE INDEX mv_orders_monthly_pk ON mv_orders_monthly (organization_id, year, month)');
        } else {
            DB::statement("CREATE VIEW mv_orders_monthly AS {$this->select}");
        }
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_orders_monthly');
        } else {
            DB::statement('DROP VIEW IF EXISTS mv_orders_monthly');
        }
    }

    protected function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
