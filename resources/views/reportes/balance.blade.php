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
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #2d3748;
            background: #ffffff;
        }

        .page {
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 0;
            padding: 0;
            position: relative;
        }

        /* --- ENCABEZADO MEJORADO --- */
        .header {
            background: linear-gradient(135deg, #1a365d 0%, #2b77c7 50%, #4299e1 100%);
            color: black;
            padding: 20px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 120px;
        }

        .header-content {
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
        }

        .header-left {
            flex: 1;
            padding-right: 20px;
        }

        .header-logo {
            width: 100px;
            height: 70px;
            flex-shrink: 0;
        }

        .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: black;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .header-subtitle {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.95;
            margin-bottom: 15px;
            color: black;
        }

        .header-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        .header-info-item {
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 6px;
            backdrop-filter: blur(10px);
        }

        .header-info-item h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 4px;
            color: black;
        }

        .header-info-item p {
            font-size: 12px;
            font-weight: 600;
            color: black;
        }
        
        /* --- CONTENIDO PRINCIPAL --- */
        .content {
            padding: 25px 20px;
        }

        /* --- SECCIONES Y TÍTULOS --- */
        .section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-left: 4px solid #4299e1;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- TARJETAS KPI MEJORADAS --- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: transform 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4299e1, #63b3ed);
        }

        .kpi-label {
            font-size: 11px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 4px;
        }

        /* --- TARJETA DE BALANCE PRINCIPAL --- */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            color: black;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .balance-label {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 10px;
            color: black;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .balance-amount {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 8px;
            color: black;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .balance-status {
            color: black;
            font-size: 16px;
            font-weight: 600;
        }
        
        /* --- TARJETAS DE GRUPOS MEJORADAS --- */
        .group-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .group-header {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            color: black;
            padding: 16px 24px;
            position: relative;
        }

        .group-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4299e1, #63b3ed);
        }

        .group-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .group-content {
            padding: 24px;
        }
        
        .group-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border-bottom: 1px solid #e2e8f0;
            margin: -24px -24px 24px -24px;
        }

        .group-stat {
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .group-stat-label {
            font-size: 10px;
            color: #718096;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .group-stat-value {
            font-size: 16px;
            font-weight: 800;
            color: #2d3748;
        }

        /* --- TABLA MODERNA MEJORADA --- */
        .table-container {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: black;
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 12px;
            color: #2d3748;
        }
        
        tr:last-child td {
            border-bottom: none;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        tr:hover {
            background: #edf2f7;
        }

        .amount {
            text-align: right;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        /* --- ELEMENTOS UTILITARIOS MEJORADOS --- */
        .currency {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
        }
        
        .positive { 
            color: #38a169; 
            font-weight: 700;
        }
        
        .negative { 
            color: #e53e3e; 
            font-weight: 700;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-paid { 
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%); 
            color: #22543d; 
            border: 1px solid #9ae6b4;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%); 
            color: #9c4221; 
            border: 1px solid #fed7aa;
        }
        
        .status-na { 
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); 
            color: #4a5568; 
            border: 1px solid #cbd5e0;
        }

        .you-paid-badge {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: black;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 800;
            margin-left: 8px;
            display: inline-block;
            vertical-align: middle;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .page-break { 
            page-break-before: always; 
        }
        
        /* --- FOOTER MEJORADO --- */
        .footer {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            color: #e2e8f0;
            padding: 20px;
            text-align: center;
            font-size: 11px;
            margin-top: 30px;
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4299e1, #63b3ed);
        }
        
        .footer strong {
            color: #63b3ed;
            font-weight: 700;
        }

        /* --- EFECTOS Y ANIMACIONES --- */
        .shimmer {
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.5) 50%, transparent 70%);
            background-size: 200% 100%;
        }

        /* --- RESPONSIVO PARA PDF --- */
        @media print {
            .page {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>{{ $titulo }}</h1>
                    <p class="header-subtitle">Reporte financiero detallado de gastos compartidos</p>
                    
                    <div class="header-info">
                        <div class="header-info-item">
                            <h3>Usuario</h3>
                            <p>{{ $usuario->nombre }}</p>
                        </div>
                        <div class="header-info-item">
                            <h3>Período</h3>
                            <p>{{ $fechaInicio->format('d/m/Y') }} - {{ $fechaFin->format('d/m/Y') }}</p>
                        </div>
                        <div class="header-info-item">
                            <h3>Generado</h3>
                            <p>{{ $fechaGeneracion->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
                <div class="header-logo">
                    <img src="https://www.uleam.edu.ec/wp-content/uploads/2012/09/LOGO-ULEAM-300x216.png" alt="Logo">
                </div>
            </div>
        </div>

        <div class="content">
            <div class="section">
                <div class="section-header">
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
                                <div style="text-align: center; padding: 40px; color: #718096;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">📊</div>
                                    <p style="font-size: 16px; font-weight: 600;">No hay gastos registrados</p>
                                    <p style="font-size: 14px;">No se encontraron gastos en este grupo para el período seleccionado.</p>
                                </div>
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
                                                    <td style="font-weight: 600;">{{ \Carbon\Carbon::parse($gasto['fecha'])->format('d/m/Y') }}</td>
                                                    <td style="font-weight: 500;">{{ $gasto['descripcion'] }}</td>
                                                    <td class="amount currency">${{ number_format($gasto['monto'], 2) }}</td>
                                                    <td style="font-weight: 500;">
                                                        {{ $gasto['pagador'] }}
                                                        @if($gasto['usuario_pago'])
                                                            <span class="you-paid-badge">TÚ</span>
                                                        @endif
                                                    </td>
                                                    <td class="amount currency">
                                                        @if($gasto['participacion_usuario'] > 0)
                                                            ${{ number_format($gasto['participacion_usuario'], 2) }}
                                                        @else
                                                            <span style="color: #a0aec0; font-style: italic;">—</span>
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
                    <div class="kpi-card" style="grid-column: 1 / -1; padding: 40px;">
                        <div style="text-align: center; color: #718096;">
                            <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                            <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No hay grupos disponibles</p>
                            <p style="font-size: 14px;">No se encontraron grupos con gastos en el período seleccionado.</p>
                        </div>
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