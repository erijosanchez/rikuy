@php
    /** @var array $report */
    $money = fn ($v) => 'S/ ' . number_format((float) $v, 0, '.', ',');
    $num = fn ($v) => number_format((float) $v, 0, '.', ',');

    $kpis = $report['kpis'];
    $cmp = $report['comparison'] ?? null;
    $trend = $report['trend'] ?? [];
    $top = $report['top_products'] ?? [];
    $regions = $report['by_region'] ?? [];
    $latest = $report['latest_period'] ?? null;

    // Geometría de la mini-gráfica de barras (SVG server-side).
    $chartW = 720; $chartH = 150; $pad = 4;
    $maxMonto = 0;
    foreach ($trend as $t) { $maxMonto = max($maxMonto, (float) $t['monto']); }
    $maxMonto = $maxMonto ?: 1;
    $n = max(count($trend), 1);
    $bw = ($chartW - $pad * ($n + 1)) / $n;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte ejecutivo — {{ $report['organization'] }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, 'Segoe UI', system-ui, sans-serif;
            color: #1c2430;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .sheet { padding: 28px 32px; }

        .head {
            display: flex; align-items: flex-start; justify-content: space-between;
            border-bottom: 2px solid #0f1722; padding-bottom: 14px; margin-bottom: 22px;
        }
        .brand { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 18px; color: #0f1722; }
        .brand .dot { width: 11px; height: 11px; border-radius: 50%; background: #2ec4b6; display: inline-block; }
        .head__meta { text-align: right; color: #5b6b80; font-size: 11px; }
        .head__title { font-size: 13px; font-weight: 700; color: #0f1722; text-transform: uppercase; letter-spacing: 0.08em; }
        .org { font-size: 15px; font-weight: 700; color: #0f1722; margin-top: 2px; }

        .eyebrow { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; color: #8a97a8; margin: 0 0 8px; font-weight: 700; }

        .kpis { display: flex; gap: 12px; margin-bottom: 22px; }
        .kpi { flex: 1; border: 1px solid #e3e8ef; border-radius: 12px; padding: 12px 14px; background: #f8fafc; }
        .kpi__label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #8a97a8; }
        .kpi__value { font-size: 20px; font-weight: 800; color: #0f1722; margin-top: 4px; }
        .kpi__delta { font-size: 10px; font-weight: 700; margin-top: 4px; }
        .up { color: #2f9e44; } .down { color: #e03131; }

        .section { margin-bottom: 22px; }
        .grid2 { display: flex; gap: 20px; }
        .col { flex: 1; }

        .chart-wrap { border: 1px solid #e3e8ef; border-radius: 12px; padding: 14px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #8a97a8; padding: 6px 8px; border-bottom: 1px solid #e3e8ef; }
        td { padding: 6px 8px; border-bottom: 1px solid #eef2f6; vertical-align: middle; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .rank { display: inline-block; width: 18px; height: 18px; line-height: 18px; text-align: center; border-radius: 50%; background: #eef2f6; color: #5b6b80; font-size: 10px; font-weight: 700; }
        .bar { height: 6px; border-radius: 3px; background: #2ec4b6; }
        .bar-track { background: #eef2f6; border-radius: 3px; }

        .foot { margin-top: 24px; border-top: 1px solid #e3e8ef; padding-top: 10px; color: #8a97a8; font-size: 10px; display: flex; justify-content: space-between; }
        .muted { color: #8a97a8; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="head">
        <div>
            <div class="brand"><span class="dot"></span> Rikuy</div>
            <div class="org">{{ $report['organization'] }}</div>
        </div>
        <div class="head__meta">
            <div class="head__title">Reporte ejecutivo</div>
            <div>Generado {{ $report['generated_at']->format('d/m/Y H:i') }}</div>
            @if ($latest)
                <div class="muted">Datos hasta {{ $latest['period'] }}</div>
            @endif
        </div>
    </div>

    {{-- KPIs --}}
    <p class="eyebrow">Indicadores clave</p>
    <div class="kpis">
        <div class="kpi">
            <div class="kpi__label">Total facturado</div>
            <div class="kpi__value">{{ $money($kpis['monto']) }}</div>
            @if ($cmp && $cmp['tiene_previo'] && $cmp['variacion_pct'] !== null)
                <div class="kpi__delta {{ $cmp['variacion_pct'] >= 0 ? 'up' : 'down' }}">
                    {{ $cmp['variacion_pct'] >= 0 ? '▲' : '▼' }} {{ abs($cmp['variacion_pct']) }}% vs {{ $cmp['year_previo'] }}
                </div>
            @endif
        </div>
        <div class="kpi">
            <div class="kpi__label">Órdenes</div>
            <div class="kpi__value">{{ $num($kpis['ordenes']) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">Ticket promedio</div>
            <div class="kpi__value">{{ $money($kpis['ticket_promedio']) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi__label">Unidades</div>
            <div class="kpi__value">{{ $num($kpis['unidades']) }}</div>
        </div>
    </div>

    {{-- Tendencia mensual (SVG) --}}
    @if (count($trend))
        <div class="section">
            <p class="eyebrow">Tendencia mensual (facturación)</p>
            <div class="chart-wrap">
                <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="100%" height="{{ $chartH }}" preserveAspectRatio="none">
                    @foreach ($trend as $i => $t)
                        @php
                            $hgt = max(2, ((float) $t['monto'] / $maxMonto) * ($chartH - 28));
                            $x = $pad + $i * ($bw + $pad);
                            $y = $chartH - 18 - $hgt;
                        @endphp
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $bw }}" height="{{ $hgt }}" rx="2" fill="#2ec4b6"></rect>
                        <text x="{{ $x + $bw / 2 }}" y="{{ $chartH - 5 }}" font-size="9" fill="#8a97a8" text-anchor="middle">{{ substr($t['period'], 2) }}</text>
                    @endforeach
                </svg>
            </div>
        </div>
    @endif

    <div class="grid2">
        {{-- Top productos --}}
        <div class="col section">
            <p class="eyebrow">Top productos</p>
            <table>
                <thead>
                    <tr><th>#</th><th>Producto</th><th class="num">Facturado</th><th class="num">Part.</th></tr>
                </thead>
                <tbody>
                    @forelse ($top as $p)
                        <tr>
                            <td><span class="rank">{{ $p['ranking'] }}</span></td>
                            <td>
                                {{ $p['producto'] }}
                                <div class="bar-track" style="margin-top:4px;">
                                    <div class="bar" style="width: {{ max(3, (float) $p['participacion_pct']) }}%;"></div>
                                </div>
                            </td>
                            <td class="num">{{ $money($p['monto']) }}</td>
                            <td class="num">{{ $p['participacion_pct'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Por región --}}
        <div class="col section">
            <p class="eyebrow">Por región</p>
            <table>
                <thead>
                    <tr><th>Región</th><th class="num">Facturado</th><th class="num">Part.</th></tr>
                </thead>
                <tbody>
                    @forelse (array_slice($regions, 0, 8) as $r)
                        <tr>
                            <td>{{ $r['region'] }}</td>
                            <td class="num">{{ $money($r['monto']) }}</td>
                            <td class="num">{{ $r['participacion_pct'] }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="foot">
        <span>Rikuy · Business Intelligence as a Product</span>
        <span>Cifras validadas contra la fuente · esquema estrella en PostgreSQL</span>
    </div>
</div>
</body>
</html>
