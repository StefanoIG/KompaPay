<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #374151;
            background: #f3f4f6;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 0 auto;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .content {
            padding: 25px 30px;
            flex-grow: 1;
        }

        /* --- Encabezado (CSS CORREGIDO) --- */
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white !important;
            padding: 25px 30px;
            position: relative;
            overflow: auto; /* Contiene los floats */
        }

        .header * {
            color: white !important;
        }

        .header-left {
            float: left;
            width: 80%; /* Ancho para el texto, dejando espacio al logo */
        }
        
        .header-logo {
            float: right;
            width: 70px;
            height: 70px;
            background: #f39c12;
            border-radius: 50%;
            text-align: center;
            line-height: 70px; /* Centrado vertical simple */
            font-size: 32px;
            font-weight: 800;
            color: white !important;
        }

        .header-left h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 2px;
            color: white !important; /* Color explícito */
        }

        .header-subtitle {
            font-size: 14px;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 20px;
            color: white !important; /* Color explícito */
        }

        .header-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .header-info-item h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
            opacity: 0.8;
            margin-bottom: 4px;
            color: white !important; /* Color explícito */
        }

        .header-info-item p {
            font-size: 13px;
            font-weight: 600;
            color: white !important; /* Color explícito */
        }
        
        /* --- Secciones y Títulos --- */
        .section {
            margin-bottom: 25px;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        .section-icon {
            font-size: 20px;
            margin-right: 12px;
            color: #3498db;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        /* --- Rejilla de Resumen Ejecutivo --- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .kpi-label {
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
        }

        /* --- Tarjeta de Balance Grande --- */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .balance-label {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 8px;
            color: white;
        }

        .balance-amount {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 5px;
            color: white;
        }
        
        .balance-status {
            color: white;
            font-size: 14px;
        }
        
        /* --- Tarjetas de Grupos --- */
        .group-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        
        .group-header {
            background: #374151;
            color: white;
            padding: 12px 20px;
        }

        .group-title {
            font-size: 16px;
            font-weight: 600;
        }

        .group-content {
            padding: 20px;
        }
        
        .group-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 15px;
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .group-stat {
            text-align: center;
        }
        
        .group-stat-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .group-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }

        /* --- Tabla Moderna --- */
        .table-container {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: #f9fafb;
            color: #4b5563;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            border-bottom: 2px solid #e5e7eb;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
            font-size: 11px;
            color: #374151;
        }
        
        tr:last-child td {
            border-bottom: none;
        }

        tr:nth-child(even) {
            background: #fcfcfd;
        }

        .amount {
            text-align: right;
            font-weight: 600;
        }
        
        /* --- Elementos Utilitarios --- */
        .currency {
            font-family: 'Courier New', Courier, monospace;
        }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-paid { background-color: #d1fae5; color: #065f46; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-na { background-color: #e5e7eb; color: #4b5563; }

        .you-paid-badge {
            background: #667eea;
            color: white;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 8px;
            font-weight: 700;
            margin-left: 5px;
            display: inline-block;
            vertical-align: middle;
        }
        
        .page-break { page-break-before: always; }
        
        /* --- Footer --- */
        .footer {
            background: #1f2937;
            color: #d1d5db;
            padding: 20px 30px;
            text-align: center;
            font-size: 10px;
            margin-top: auto;
        }
        .footer strong {
            color: #f39c12;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-logo">K</div>
            <div class="header-left">
                <h1 style="color: black !important;">{{ $titulo }}</h1>
                <p class="header-subtitle" style="color: black !important;">Reporte financiero detallado de gastos compartidos</p>
                
                <div class="header-info-grid">
                    <div class="header-info-item">
                        <h3 style="color: black !important;">Usuario</h3>
                        <p style="color: black !important; font-weight: 600;">{{ $usuario->nombre }}</p>
                    </div>
                    <div class="header-info-item">
                        <h3 style="color: black !important;">Período</h3>
                        <p style="color: black !important; font-weight: 600;">{{ $fechaInicio->format('d/m/Y') }} - {{ $fechaFin->format('d/m/Y') }}</p>
                    </div>
                    <div class="header-info-item">
                        <h3 style="color: black !important;">Generado</h3>
                        <p style="color: black !important; font-weight: 600;">{{ $fechaGeneracion->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="section">
                <div class="section-header">
                    <div class="section-icon">■</div>
                    <div class="section-title">Resumen Ejecutivo</div>
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Total Gastado</div>
                        <div class="kpi-value currency">${{ number_format($resumenGeneral['total_gastos_periodo'], 2) }}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Pagado por Ti</div>
                        <div class="kpi-value currency">${{ number_format($resumenGeneral['total_pagado'], 2) }}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Tu Parte de Gastos</div>
                        <div class="kpi-value currency">${{ number_format($resumenGeneral['total_pagado'] + $resumenGeneral['balance_general'], 2) }}</div>
                    </div>
                </div>

                <div class="balance-card">
                    <div class="balance-label">Tu Balance Final</div>
                    <div class="balance-amount currency">
                        {{ $resumenGeneral['balance_general'] < 0 ? '-' : '' }}${{ number_format(abs($resumenGeneral['balance_general']), 2) }}
                    </div>
                    <div class="balance-status">
                        <strong>{{ $resumenGeneral['balance_general'] >= 0 ? 'Te deben' : 'Debes' }}</strong> esta cantidad en total.
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <div class="section-icon">●</div>
                    <div class="section-title">Análisis por Grupos</div>
                </div>
                
                @forelse($grupos as $index => $grupo)
                    @if($index > 0)
                        <div class="page-break"></div>
                    @endif
                    
                    <div class="group-card">
                        <div class="group-header">
                            <h3 class="group-title">{{ $grupo['nombre'] }}</h3>
                        </div>
                        <div class="group-stats">
                            <div class="group-stat">
                                <div class="group-stat-label">Total Gastos</div>
                                <div class="group-stat-value currency">${{ number_format($grupo['total_gastos'], 2) }}</div>
                            </div>
                            <div class="group-stat">
                                <div class="group-stat-label">Pagado por Ti</div>
                                <div class="group-stat-value currency">${{ number_format($grupo['pagado_por_usuario'], 2) }}</div>
                            </div>
                            <div class="group-stat">
                                <div class="group-stat-label">Tu Balance</div>
                                <div class="group-stat-value currency {{ $grupo['balance_usuario'] >= 0 ? 'positive' : 'negative' }}">
                                    ${{ number_format($grupo['balance_usuario'], 2) }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="group-content">
                            @if(count($grupo['gastos']) === 0)
                                <p>No hay gastos registrados en este grupo para el período seleccionado.</p>
                            @else
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th style="width: 35%;">Descripción</th>
                                                <th>Monto</th>
                                                <th>Pagador</th>
                                                <th>Tu Parte</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($grupo['gastos'] as $gasto)
                                                <tr>
                                                    <td>{{ \Carbon\Carbon::parse($gasto['fecha'])->format('d/m/Y') }}</td>
                                                    <td>{{ $gasto['descripcion'] }}</td>
                                                    <td class="amount currency">${{ number_format($gasto['monto'], 2) }}</td>
                                                    <td>
                                                        {{ $gasto['pagador'] }}
                                                        @if($gasto['usuario_pago'])
                                                            <span class="you-paid-badge">TÚ</span>
                                                        @endif
                                                    </td>
                                                    <td class="amount currency">
                                                        @if($gasto['participacion_usuario'] > 0)
                                                            ${{ number_format($gasto['participacion_usuario'], 2) }}
                                                        @else
                                                            <span style="color: #9ca3af;">—</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($gasto['participacion_usuario'] > 0)
                                                            @if($gasto['usuario_pagado'])
                                                                <span class="status-badge status-paid">Pagado</span>
                                                            @else
                                                                <span class="status-badge status-pending">Pendiente</span>
                                                            @endif
                                                        @else
                                                            <span class="status-badge status-na">N/A</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="kpi-card">
                        <p>No se encontraron grupos con gastos en el período seleccionado.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="footer">
            <strong>KompaPay - Sistema de Gestión de Gastos Compartidos</strong><br>
            Reporte generado automáticamente el {{ $fechaGeneracion->format('d/m/Y \a \l\a\s H:i:s') }}.
        </div>
    </div>
</body>
</html>