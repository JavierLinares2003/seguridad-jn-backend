<?php

namespace App\Services;

use App\Models\BodegaEntrega;
use App\Models\BodegaEntregaItem;
use App\Models\BodegaFacturaCompra;
use App\Models\BodegaFacturaCompraItem;
use App\Models\BodegaMovimiento;
use App\Models\BodegaProducto;
use App\Models\BodegaVariante;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class BodegaService
{
    public function asegurarCodigoProducto(BodegaProducto $producto): string
    {
        if ($producto->codigo) {
            return $producto->codigo;
        }

        $producto->loadMissing('categoria');
        $prefijo = strtoupper((string) ($producto->categoria?->prefijo_correlativo ?: 'BOD'));
        $producto->codigo = $this->siguienteCorrelativo('bodega_productos', 'codigo', $prefijo);
        $producto->save();

        return $producto->codigo;
    }

    public function asegurarCodigoProveedor(\App\Models\BodegaProveedor $proveedor): string
    {
        if ($proveedor->codigo) {
            return $proveedor->codigo;
        }
        $proveedor->codigo = $this->siguienteCorrelativo('bodega_proveedores', 'codigo', 'PRV');
        $proveedor->save();

        return $proveedor->codigo;
    }

    public function asegurarCodigoArma(\App\Models\BodegaArma $arma): string
    {
        if ($arma->codigo) {
            return $arma->codigo;
        }
        $arma->codigo = $this->siguienteCorrelativo('bodega_armas', 'codigo', 'ARM');
        $arma->save();

        return $arma->codigo;
    }

    public function asegurarCodigoKit(\App\Models\BodegaKit $kit): string
    {
        if ($kit->codigo) {
            return $kit->codigo;
        }
        $kit->codigo = $this->siguienteCorrelativo('bodega_kits', 'codigo', 'KIT');
        $kit->save();

        return $kit->codigo;
    }

    public function asegurarSku(BodegaVariante $variante): ?string
    {
        $variante->loadMissing('producto');
        $producto = $variante->producto;
        if (!$producto) {
            return $variante->sku;
        }
        $this->asegurarCodigoProducto($producto);
        $sku = $this->generarSku($producto, $variante->talla, $variante->condicion, $variante->genero);
        if (!$sku) {
            return $variante->sku;
        }
        if ($variante->sku === $sku) {
            return $sku;
        }
        $base = $sku;
        $i = 2;
        while (
            BodegaVariante::where('sku', $sku)
                ->when($variante->exists, fn ($q) => $q->where('id', '!=', $variante->id))
                ->exists()
        ) {
            $sku = $base . '-' . $i;
            $i++;
        }
        $variante->sku = $sku;
        $variante->save();

        return $sku;
    }

    private function siguienteCorrelativo(string $tabla, string $columna, string $prefijo): string
    {
        $max = (int) DB::table($tabla)
            ->where($columna, 'like', $prefijo . '-%')
            ->selectRaw(
                "COALESCE(MAX(NULLIF(regexp_replace(SPLIT_PART({$columna}, '-', 2), '[^0-9]', '', 'g'), '')::INTEGER), 0) as max"
            )
            ->value('max');

        $n = $max + 1;
        do {
            $codigo = $prefijo . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (DB::table($tabla)->where($columna, $codigo)->exists());

        return $codigo;
    }

    private function generarSku(BodegaProducto $producto, ?string $talla, ?string $condicion, ?string $genero = null): ?string
    {
        if (!$producto->codigo) {
            $producto->refresh();
        }
        if (!$producto->codigo) {
            return null;
        }

        $parts = [$producto->codigo];
        if ($talla) {
            $parts[] = strtoupper($talla);
        }
        if ($genero) {
            $parts[] = strtoupper(substr($genero, 0, 1));
        }
        if ($condicion) {
            $parts[] = $condicion === 'usado' ? 'U' : 'N';
        }

        return implode('-', $parts);
    }

    /**
     * Crea o actualiza una variante normalizando atributos vacíos.
     */
    public function upsertVariante(BodegaProducto $producto, array $attrs): BodegaVariante
    {
        $this->asegurarCodigoProducto($producto);

        $talla = $this->norm($attrs['talla'] ?? null);
        $condicion = $this->norm($attrs['condicion'] ?? null);
        $genero = $this->norm($attrs['genero'] ?? null);

        $variante = BodegaVariante::firstOrNew([
            'producto_id' => $producto->id,
            'talla' => $talla,
            'condicion' => $condicion,
            'genero' => $genero,
        ]);

        if (!$variante->exists) {
            $variante->existencia = 0;
            $variante->stock_minimo = (int) ($attrs['stock_minimo'] ?? 0);
            $variante->sku = $attrs['sku'] ?? $this->generarSku($producto, $talla, $condicion, $genero);
            $variante->activo = true;
            $variante->save();
        } else {
            if (array_key_exists('stock_minimo', $attrs)) {
                $variante->stock_minimo = (int) $attrs['stock_minimo'];
            }
            if (!empty($attrs['sku'])) {
                $variante->sku = $attrs['sku'];
            } elseif (empty($variante->sku)) {
                $variante->sku = $this->generarSku($producto, $talla, $condicion, $genero);
            }
            $variante->activo = true;
            $variante->save();
        }

        return $variante;
    }

    /**
     * Registra un movimiento y actualiza existencia.
     *
     * @param  array{
     *   variante_id:int,
     *   tipo:string,
     *   cantidad:int,
     *   fecha_movimiento?:string,
     *   personal_id?:int|null,
     *   proyecto_id?:int|null,
     *   proveedor_id?:int|null,
     *   registrado_por_user_id?:int|null,
     *   referencia?:string|null,
     *   observaciones?:string|null
     * }  $data
     */
    public function registrarMovimiento(array $data): BodegaMovimiento
    {
        $tipo = $data['tipo'];
        $cantidad = (int) $data['cantidad'];

        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }

        if (!in_array($tipo, ['ingreso', 'egreso', 'ajuste', 'ajuste_inicial', 'merma'], true)) {
            throw new InvalidArgumentException('Tipo de movimiento no válido.');
        }

        return DB::transaction(function () use ($data, $tipo, $cantidad) {
            /** @var BodegaVariante $variante */
            $variante = BodegaVariante::lockForUpdate()->findOrFail($data['variante_id']);
            $anterior = (int) $variante->existencia;

            $nueva = match ($tipo) {
                'ingreso', 'ajuste_inicial' => $anterior + $cantidad,
                'egreso' => $anterior - $cantidad,
                'merma' => $anterior, // prenda dañada: no vuelve a stock usable
                'ajuste' => $cantidad,
                default => $anterior,
            };

            if ($tipo === 'ajuste') {
                // Para ajuste, "cantidad" enviada es el delta absoluto hacia el stock objetivo
                // Recalcular: si mandan existencia_nueva preferida
                if (isset($data['existencia_nueva'])) {
                    $nueva = (int) $data['existencia_nueva'];
                }
                $cantidadMov = abs($nueva - $anterior);
            } else {
                $cantidadMov = $cantidad;
            }

            if ($nueva < 0) {
                throw new InvalidArgumentException(
                    "Stock insuficiente. Existencia actual: {$anterior}, solicitado: {$cantidad}."
                );
            }

            if ($tipo === 'ajuste') {
                $cantidadMov = max(1, abs($nueva - $anterior));
                // Si no hay cambio, no crear movimiento vacío
                if ($nueva === $anterior) {
                    throw new InvalidArgumentException('El ajuste no cambia la existencia.');
                }
            }

            $variante->update(['existencia' => $nueva]);

            return BodegaMovimiento::create([
                'variante_id' => $variante->id,
                'tipo' => $tipo,
                'cantidad' => $tipo === 'ajuste' ? $cantidadMov : $cantidad,
                'existencia_anterior' => $anterior,
                'existencia_nueva' => $nueva,
                'fecha_movimiento' => $data['fecha_movimiento'] ?? now()->toDateString(),
                'personal_id' => $data['personal_id'] ?? null,
                'proyecto_id' => $data['proyecto_id'] ?? null,
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'entrega_id' => $data['entrega_id'] ?? null,
                'factura_compra_id' => $data['factura_compra_id'] ?? null,
            ]);
        });
    }

    /**
     * Entrega a personal = egreso con personal_id.
     */
    public function entregarAPersonal(array $data): BodegaMovimiento
    {
        if (empty($data['personal_id'])) {
            throw new InvalidArgumentException('Debe indicar el personal que recibe.');
        }

        $data['tipo'] = 'egreso';

        return $this->registrarMovimiento($data);
    }

    /**
     * Graba una factura de compra y carga inventario (un ingreso por línea).
     *
     * @param  array{
     *   proveedor_id:int,
     *   fecha_factura:string,
     *   serie?:string|null,
     *   numero_factura:string,
     *   observaciones?:string|null,
     *   registrado_por_user_id?:int|null,
     *   items: array<int, array{variante_id:int, cantidad:int, precio_unitario?:float|int|string}>
     * }  $data
     */
    public function registrarFacturaCompra(array $data): BodegaFacturaCompra
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new InvalidArgumentException('Agregue al menos un producto a la factura.');
        }

        $serie = $this->norm($data['serie'] ?? null);
        $numero = trim((string) ($data['numero_factura'] ?? ''));
        if ($numero === '') {
            throw new InvalidArgumentException('Indique el número de factura.');
        }

        $duplicada = BodegaFacturaCompra::query()
            ->where('proveedor_id', $data['proveedor_id'])
            ->where('numero_factura', $numero)
            ->where(function ($q) use ($serie) {
                if ($serie === null) {
                    $q->whereNull('serie')->orWhere('serie', '');
                } else {
                    $q->where('serie', $serie);
                }
            })
            ->exists();
        if ($duplicada) {
            throw new InvalidArgumentException('Ya existe esa serie/factura para el proveedor.');
        }

        try {
            return DB::transaction(function () use ($data, $items, $serie, $numero) {
            $fecha = $data['fecha_factura'] ?? now()->toDateString();
            $lineas = [];
            $total = 0.0;

            foreach ($items as $idx => $item) {
                $varianteId = (int) ($item['variante_id'] ?? 0);
                $cantidad = (int) ($item['cantidad'] ?? 0);
                $precio = round((float) ($item['precio_unitario'] ?? 0), 2);
                if ($varianteId <= 0 || $cantidad <= 0) {
                    throw new InvalidArgumentException('Ítem #' . ($idx + 1) . ': producto y cantidad son obligatorios.');
                }
                if ($precio < 0) {
                    throw new InvalidArgumentException('Ítem #' . ($idx + 1) . ': el precio no puede ser negativo.');
                }
                $subtotal = round($precio * $cantidad, 2);
                $total = round($total + $subtotal, 2);
                $lineas[] = [
                    'variante_id' => $varianteId,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $subtotal,
                ];
            }

            $factura = BodegaFacturaCompra::create([
                'proveedor_id' => $data['proveedor_id'],
                'fecha_factura' => $fecha,
                'serie' => $serie,
                'numero_factura' => $numero,
                'total' => $total,
                'observaciones' => $data['observaciones'] ?? null,
                'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
            ]);

            $ref = 'FAC-' . ($serie ? $serie . '-' : '') . $numero;

            foreach ($lineas as $linea) {
                $mov = $this->registrarMovimiento([
                    'variante_id' => $linea['variante_id'],
                    'tipo' => 'ingreso',
                    'cantidad' => $linea['cantidad'],
                    'fecha_movimiento' => $fecha,
                    'proveedor_id' => $data['proveedor_id'],
                    'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
                    'referencia' => $ref,
                    'observaciones' => 'Factura de compra ' . $ref,
                    'factura_compra_id' => $factura->id,
                ]);

                BodegaFacturaCompraItem::create([
                    'factura_id' => $factura->id,
                    'variante_id' => $linea['variante_id'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                    'movimiento_id' => $mov->id,
                ]);
            }

            return $factura->fresh([
                'proveedor',
                'items.variante.producto.categoria',
                'registradoPor:id,name',
            ]);
        });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'bodega_facturas_compra_doc_unique')) {
                throw new InvalidArgumentException('Ya existe esa serie/factura para el proveedor.');
            }
            throw $e;
        }
    }

    /**
     * Entrega completa (simple / kit / reposición): N egresos + desglose de precios + cobro opcional.
     *
     * @param  array{
     *   personal_id:int,
     *   tipo:string,
     *   cobrar:bool,
     *   motivo_reposicion?:string|null,
     *   observaciones?:string|null,
     *   fecha_entrega?:string,
     *   registrado_por_user_id?:int|null,
     *   items: array<int, array{variante_id:int, cantidad:int, precio_unitario?:float|int|string}>,
     *   descuento?: array{cuotas_totales?:int, fecha_inicio?:string, descripcion?:string}|null
     * }  $data
     * @return array{entrega: BodegaEntrega, grupo_uniforme:?string, sugerir_descuento_uniforme:bool}
     */
    public function crearEntregaCompleta(array $data, ?UniformeService $uniformeService = null): array
    {
        $personalId = (int) ($data['personal_id'] ?? 0);
        $tipo = $data['tipo'] ?? 'simple';
        $items = $data['items'] ?? [];
        $cobrar = (bool) ($data['cobrar'] ?? false);

        if ($personalId <= 0) {
            throw new InvalidArgumentException('Debe indicar el personal que recibe.');
        }
        if (!in_array($tipo, ['simple', 'kit', 'reposicion'], true)) {
            throw new InvalidArgumentException('Tipo de entrega no válido.');
        }
        $viaOps = !empty($data['personal_operaciones_id']);
        $cambioDano = (bool) ($data['cambio_por_dano'] ?? false);
        if ($viaOps && (int) $data['personal_operaciones_id'] === $personalId) {
            throw new InvalidArgumentException('Quien lleva (operaciones) y el usuario final deben ser personas distintas.');
        }
        if ($cambioDano && empty($data['variante_entrada_dano_id'])) {
            throw new InvalidArgumentException('En cambio por daño indique la prenda dañada que entra.');
        }
        if (!is_array($items) || count($items) === 0) {
            throw new InvalidArgumentException('Debe agregar al menos un ítem a la entrega.');
        }
        if ($tipo === 'reposicion' && empty(trim((string) ($data['motivo_reposicion'] ?? '')))) {
            throw new InvalidArgumentException('Indique el motivo de la reposición.');
        }

        $uniformeService = $uniformeService ?? app(UniformeService::class);

        return DB::transaction(function () use ($data, $personalId, $tipo, $items, $cobrar, $uniformeService, $viaOps, $cambioDano) {
            $fecha = $data['fecha_entrega'] ?? now()->toDateString();
            $lineas = [];
            $montoTotal = 0.0;

            foreach ($items as $idx => $item) {
                $varianteId = (int) ($item['variante_id'] ?? 0);
                $cantidad = (int) ($item['cantidad'] ?? 0);
                $variante = BodegaVariante::with('producto')->find($varianteId);
                $precio = round((float) ($item['precio_unitario'] ?? 0), 2);
                if ($cobrar && $precio <= 0 && $variante?->producto) {
                    $precio = $variante->producto->precioParaCondicion($variante->condicion);
                }

                if ($varianteId <= 0 || $cantidad <= 0) {
                    throw new InvalidArgumentException('Ítem #' . ($idx + 1) . ': variante y cantidad son obligatorios.');
                }
                if ($cobrar && $precio < 0) {
                    throw new InvalidArgumentException('Ítem #' . ($idx + 1) . ': el precio no puede ser negativo.');
                }
                if ($cobrar && $precio <= 0) {
                    throw new InvalidArgumentException('Si se va a cobrar, cada ítem debe tener precio mayor a 0.');
                }

                $subtotal = round($precio * $cantidad, 2);
                $montoTotal = round($montoTotal + $subtotal, 2);
                $lineas[] = [
                    'variante_id' => $varianteId,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $subtotal,
                ];
            }

            if ($cobrar && $montoTotal <= 0) {
                throw new InvalidArgumentException('El monto total a cobrar debe ser mayor a 0.');
            }

            $entrega = BodegaEntrega::create([
                'personal_id' => $personalId,
                'modo_entrega' => $viaOps ? 'via_operaciones' : 'directa',
                'personal_operaciones_id' => $viaOps ? (int) $data['personal_operaciones_id'] : null,
                'tipo' => $tipo,
                'cobrar' => $cobrar,
                'monto_total' => $cobrar ? $montoTotal : 0,
                'motivo_reposicion' => $tipo === 'reposicion' ? ($data['motivo_reposicion'] ?? null) : null,
                'cambio_por_dano' => $cambioDano,
                'variante_entrada_dano_id' => $cambioDano ? ($data['variante_entrada_dano_id'] ?? null) : null,
                'cantidad_entrada_dano' => $cambioDano ? (int) ($data['cantidad_entrada_dano'] ?? 1) : null,
                'observaciones' => $data['observaciones'] ?? null,
                'fecha_entrega' => $fecha,
                'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
            ]);
            $entrega->refresh();

            $ref = match ($tipo) {
                'kit' => 'KIT-' . $entrega->id,
                'reposicion' => 'REP-' . $entrega->id,
                default => 'ENT-' . $entrega->id,
            };

            foreach ($lineas as $linea) {
                $obsParts = [];
                if ($tipo === 'reposicion') {
                    $obsParts[] = 'Reposición: ' . ($data['motivo_reposicion'] ?? '');
                }
                if (!empty($data['observaciones'])) {
                    $obsParts[] = $data['observaciones'];
                }

                $mov = $this->registrarMovimiento([
                    'variante_id' => $linea['variante_id'],
                    'tipo' => 'egreso',
                    'cantidad' => $linea['cantidad'],
                    'fecha_movimiento' => $fecha,
                    'personal_id' => $personalId,
                    'proyecto_id' => $data['proyecto_id'] ?? null,
                    'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
                    'referencia' => $ref,
                    'observaciones' => $obsParts ? implode(' | ', $obsParts) : null,
                    'entrega_id' => $entrega->id,
                ]);

                BodegaEntregaItem::create([
                    'entrega_id' => $entrega->id,
                    'variante_id' => $linea['variante_id'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'subtotal' => $linea['subtotal'],
                    'movimiento_id' => $mov->id,
                ]);
            }

            if ($cambioDano) {
                $this->registrarMovimiento([
                    'variante_id' => (int) $data['variante_entrada_dano_id'],
                    'tipo' => 'merma',
                    'cantidad' => max(1, (int) ($data['cantidad_entrada_dano'] ?? 1)),
                    'fecha_movimiento' => $fecha,
                    'personal_id' => $personalId,
                    'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
                    'referencia' => 'DAN-' . $entrega->id,
                    'observaciones' => 'Prenda dañada/quemada: no reingresa a stock usable. Boleta ' . ($entrega->numero_boleta ?: $entrega->id),
                    'entrega_id' => $entrega->id,
                ]);
            }

            $grupoUniforme = null;
            $sugerir = false;

            if ($cobrar) {
                $descuento = $data['descuento'] ?? null;
                $cuotas = (int) ($descuento['cuotas_totales'] ?? 0);

                if ($cuotas >= 1) {
                    $descripcion = trim((string) ($descuento['descripcion'] ?? ''));
                    if ($descripcion === '') {
                        $descripcion = $tipo === 'reposicion'
                            ? 'Reposición de equipo / uniforme'
                            : 'Kit / uniforme entregado';
                        if (!empty($data['motivo_reposicion'])) {
                            $descripcion .= ' (' . $data['motivo_reposicion'] . ')';
                        }
                    }

                    $resultado = $uniformeService->crearDescuentoUniforme([
                        'personal_id' => $personalId,
                        'monto' => $montoTotal,
                        'cuotas_totales' => $cuotas,
                        'fecha_inicio' => $descuento['fecha_inicio'] ?? $fecha,
                        'descripcion' => $descripcion,
                        'registrado_por_user_id' => $data['registrado_por_user_id'] ?? null,
                    ]);

                    $grupoUniforme = $resultado['grupo_uniforme'];
                    $entrega->update(['grupo_uniforme' => $grupoUniforme]);
                } else {
                    $sugerir = true;
                }
            }

            $entrega->load([
                'personal:id,nombres,apellidos,dpi,puesto,estado',
                'personalOperaciones:id,nombres,apellidos,dpi,puesto',
                'items.variante.producto.categoria',
                'movimientos',
            ]);

            return [
                'entrega' => $entrega,
                'grupo_uniforme' => $grupoUniforme,
                'sugerir_descuento_uniforme' => $sugerir,
            ];
        });
    }

    /**
     * Devuelve a bodega lo que salió en una boleta (misma guía Nº).
     * Sin ítems: devuelve todo lo pendiente.
     *
     * @param  array<int, array{item_id?:int, cantidad?:int}>  $items
     */
    public function registrarDevolucion(BodegaEntrega $entrega, array $items = [], ?int $userId = null): BodegaEntrega
    {
        return DB::transaction(function () use ($entrega, $items, $userId) {
            /** @var BodegaEntrega $entrega */
            $entrega = BodegaEntrega::with('items')->lockForUpdate()->findOrFail($entrega->id);
            if ($entrega->devuelta_at) {
                throw new InvalidArgumentException('Esta boleta ya está marcada como devuelta.');
            }

            $solicitados = [];
            foreach ($items as $row) {
                $itemId = (int) ($row['item_id'] ?? $row['id'] ?? 0);
                $cant = (int) ($row['cantidad'] ?? 0);
                if ($itemId > 0 && $cant > 0) {
                    $solicitados[$itemId] = $cant;
                }
            }

            $devolvioAlgo = false;
            foreach ($entrega->items as $item) {
                $pendiente = max(0, (int) $item->cantidad - (int) $item->cantidad_devuelta);
                if ($pendiente <= 0) {
                    continue;
                }
                $devolver = $solicitados === []
                    ? $pendiente
                    : min($pendiente, $solicitados[$item->id] ?? 0);
                if ($devolver <= 0) {
                    continue;
                }

                $this->registrarMovimiento([
                    'variante_id' => $item->variante_id,
                    'tipo' => 'ingreso',
                    'cantidad' => $devolver,
                    'personal_id' => $entrega->personal_id,
                    'registrado_por_user_id' => $userId,
                    'referencia' => 'BOL-' . ($entrega->numero_boleta ?: $entrega->id),
                    'observaciones' => 'Devolución boleta ' . ($entrega->numero_boleta ?: $entrega->id),
                    'entrega_id' => $entrega->id,
                ]);

                $item->update(['cantidad_devuelta' => (int) $item->cantidad_devuelta + $devolver]);
                $devolvioAlgo = true;
            }

            if (!$devolvioAlgo) {
                throw new InvalidArgumentException('No hay cantidades pendientes de devolver en esta boleta.');
            }

            $entrega->unsetRelation('items');
            $entrega->load('items');
            $queda = $entrega->items->contains(
                fn ($it) => (int) $it->cantidad > (int) $it->cantidad_devuelta
            );
            if (!$queda) {
                $entrega->update(['devuelta_at' => now()]);
            }

            return $entrega->fresh([
                'personal:id,nombres,apellidos,dpi,puesto,estado',
                'items.variante.producto.categoria',
                'movimientos',
                'registradoPor:id,name',
            ]);
        });
    }

    private function norm(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
