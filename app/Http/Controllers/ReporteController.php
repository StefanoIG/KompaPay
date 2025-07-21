<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Grupo;
use App\Models\Gasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    /**
     * Genera un reporte PDF de balance de gastos
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function balancePdf(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'sometimes|uuid|exists:grupos,id',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Establecer fechas por defecto si no se proporcionan
            $fechaInicio = $request->fecha_inicio ? 
                Carbon::parse($request->fecha_inicio)->startOfDay() : 
                Carbon::now()->startOfMonth();
            
            $fechaFin = $request->fecha_fin ? 
                Carbon::parse($request->fecha_fin)->endOfDay() : 
                Carbon::now()->endOfMonth();

            // Determinar si es para un grupo específico o todos los grupos
            $grupos = collect();
            $tituloReporte = '';
            
            if ($request->grupo_id) {
                // Reporte para un grupo específico
                $grupo = Grupo::find($request->grupo_id);
                
                // Verificar que el usuario pertenezca al grupo
                if (!$grupo || (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes acceso a este grupo.'
                    ], 403);
                }
                
                $grupos->push($grupo);
                $tituloReporte = "Balance de Gastos - Grupo: {$grupo->nombre}";
            } else {
                // Reporte para todos los grupos del usuario
                $grupos = $user->grupos()->with(['gastos' => function($query) use ($fechaInicio, $fechaFin) {
                    $query->whereBetween('fecha_creacion', [$fechaInicio, $fechaFin]);
                }])->get();
                
                // También incluir grupos creados por el usuario
                $gruposCreados = $user->gruposCreados()->with(['gastos' => function($query) use ($fechaInicio, $fechaFin) {
                    $query->whereBetween('fecha_creacion', [$fechaInicio, $fechaFin]);
                }])->get();
                
                $grupos = $grupos->merge($gruposCreados)->unique('id');
                $tituloReporte = "Balance General de Gastos - Todos los Grupos";
            }

            // Recopilar datos del reporte
            $datosReporte = $this->procesarDatosReporte($user, $grupos, $fechaInicio, $fechaFin);
            
            // Datos para la vista
            $data = [
                'titulo' => $tituloReporte,
                'usuario' => $user,
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'grupos' => $datosReporte['grupos'],
                'resumenGeneral' => $datosReporte['resumen'],
                'fechaGeneracion' => Carbon::now()
            ];

            // Generar PDF
            $pdf = Pdf::loadView('reportes.balance', $data);
            $pdf->setPaper('a4', 'portrait');
            
            // Configurar opciones para mejor manejo de caracteres UTF-8
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'isFontSubsettingEnabled' => true
            ]);
            
            $nombreArchivo = 'balance_' . $fechaInicio->format('Y-m-d') . '_' . $fechaFin->format('Y-m-d') . '.pdf';
            
            return $pdf->download($nombreArchivo);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa los datos para el reporte
     * 
     * @param User $user
     * @param \Illuminate\Support\Collection $grupos
     * @param Carbon $fechaInicio
     * @param Carbon $fechaFin
     * @return array
     */
    private function procesarDatosReporte($user, $grupos, $fechaInicio, $fechaFin)
    {
        $gruposData = [];
        $totalPagado = 0;
        $totalAdeudado = 0;
        $totalAcreedor = 0;

        foreach ($grupos as $grupo) {
            // Obtener gastos del grupo en el período
            $gastos = $grupo->gastos()
                ->with(['pagador', 'participantes'])
                ->whereBetween('fecha_creacion', [$fechaInicio, $fechaFin])
                ->get();

            $gastosDelGrupo = [];
            $totalGrupo = 0;
            $pagadoPorUsuario = 0;
            $deudaUsuario = 0;
            $acreenciaUsuario = 0;

            foreach ($gastos as $gasto) {
                $totalGrupo += $gasto->monto;
                
                // Verificar si el usuario pagó este gasto
                $usuarioPago = $gasto->pagado_por === $user->id;
                if ($usuarioPago) {
                    $pagadoPorUsuario += $gasto->monto;
                }

                // Calcular participación del usuario
                $participacionUsuario = $gasto->participantes->where('id', $user->id)->first();
                $montoParticipacion = $participacionUsuario ? $participacionUsuario->pivot->monto_proporcional : 0;
                $pagadoParticipacion = $participacionUsuario ? $participacionUsuario->pivot->pagado : false;

                // Calcular deudas y acreencias
                if ($participacionUsuario && !$pagadoParticipacion) {
                    $deudaUsuario += $montoParticipacion;
                }

                if ($usuarioPago) {
                    $participantesNoPagados = $gasto->participantes->where('pivot.pagado', false);
                    $acreenciaUsuario += $participantesNoPagados->sum('pivot.monto_proporcional');
                }

                $gastosDelGrupo[] = [
                    'descripcion' => $gasto->descripcion,
                    'monto' => $gasto->monto,
                    'fecha' => $gasto->fecha_creacion,
                    'pagador' => $gasto->pagador->nombre,
                    'usuario_pago' => $usuarioPago,
                    'participacion_usuario' => $montoParticipacion,
                    'usuario_pagado' => $pagadoParticipacion,
                    'id_publico' => $gasto->id_publico
                ];
            }

            $gruposData[] = [
                'nombre' => $grupo->nombre,
                'total_gastos' => $totalGrupo,
                'gastos' => $gastosDelGrupo,
                'pagado_por_usuario' => $pagadoPorUsuario,
                'deuda_usuario' => $deudaUsuario,
                'acreencia_usuario' => $acreenciaUsuario,
                'balance_usuario' => $acreenciaUsuario - $deudaUsuario
            ];

            $totalPagado += $pagadoPorUsuario;
            $totalAdeudado += $deudaUsuario;
            $totalAcreedor += $acreenciaUsuario;
        }

        return [
            'grupos' => $gruposData,
            'resumen' => [
                'total_pagado' => $totalPagado,
                'total_adeudado' => $totalAdeudado,
                'total_acreedor' => $totalAcreedor,
                'balance_general' => $totalAcreedor - $totalAdeudado,
                'cantidad_grupos' => count($gruposData),
                'total_gastos_periodo' => array_sum(array_column($gruposData, 'total_gastos'))
            ]
        ];
    }

    /**
     * Obtiene un resumen de balance sin generar PDF (para vista previa)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumenBalance(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'sometimes|uuid|exists:grupos,id',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Establecer fechas por defecto si no se proporcionan
            $fechaInicio = $request->fecha_inicio ? 
                Carbon::parse($request->fecha_inicio)->startOfDay() : 
                Carbon::now()->startOfMonth();
            
            $fechaFin = $request->fecha_fin ? 
                Carbon::parse($request->fecha_fin)->endOfDay() : 
                Carbon::now()->endOfMonth();

            // Determinar grupos
            $grupos = collect();
            
            if ($request->grupo_id) {
                $grupo = Grupo::find($request->grupo_id);
                if (!$grupo || (!$grupo->miembros->contains($user->id) && $grupo->creado_por !== $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes acceso a este grupo.'
                    ], 403);
                }
                $grupos->push($grupo);
            } else {
                $grupos = $user->grupos()->get();
                $gruposCreados = $user->gruposCreados()->get();
                $grupos = $grupos->merge($gruposCreados)->unique('id');
            }

            // Procesar datos
            $datosReporte = $this->procesarDatosReporte($user, $grupos, $fechaInicio, $fechaFin);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => [
                        'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                        'fecha_fin' => $fechaFin->format('Y-m-d')
                    ],
                    'grupos' => $datosReporte['grupos'],
                    'resumen' => $datosReporte['resumen']
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el resumen: ' . $e->getMessage()
            ], 500);
        }
    }
}
