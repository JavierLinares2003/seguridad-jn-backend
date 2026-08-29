<?php

namespace App\Services;

use App\Models\BodegaProducto;
use App\Models\BodegaProveedor;
use App\Models\BodegaVariante;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;

class BodegaFacturaPdfService
{
    /** @var list<string> */
    private array $nombresPropios = [
        'seguridad jn',
        'seguridadjn',
        'receptor',
        'consumidor final',
    ];

    public function extraer(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('No se pudo leer el archivo PDF.');
        }

        try {
            $pdf = (new Parser())->parseFile($path);
            $texto = trim((string) $pdf->getText());
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('El PDF no se pudo abrir. Prueba con el archivo original de la factura.');
        }

        $texto = $this->limpiarTexto($texto);
        if (mb_strlen($texto) < 40) {
            throw new InvalidArgumentException(
                'Este PDF parece una imagen escaneada y no trae texto. Usa el PDF digital de la factura (FEL) o captura los datos a mano.'
            );
        }

        $encabezado = $this->extraerEncabezado($texto);
        $lineas = $this->extraerLineas($texto);
        $proveedor = $this->emparejarProveedor($encabezado['proveedor_nombre'] ?? null, $texto);
        [$items, $sinMatch] = $this->emparejarProductos($lineas);

        return [
            'proveedor_id' => $proveedor['id'] ?? null,
            'proveedor_nombre' => $proveedor['nombre'] ?? ($encabezado['proveedor_nombre'] ?? null),
            'proveedor_codigo' => $proveedor['codigo'] ?? null,
            'proveedor_confianza' => $proveedor['confianza'] ?? null,
            'fecha_factura' => $encabezado['fecha_factura'] ?? null,
            'serie' => $encabezado['serie'] ?? null,
            'numero_factura' => $encabezado['numero_factura'] ?? null,
            'total_pdf' => $encabezado['total'] ?? null,
            'items' => $items,
            'items_sin_match' => $sinMatch,
            'advertencias' => $this->advertencias($encabezado, $proveedor, $items, $sinMatch),
        ];
    }

    private function limpiarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace("/[ \t]+/", ' ', $texto) ?? $texto;
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @return array{serie:?string, numero_factura:?string, fecha_factura:?string, total:?float, proveedor_nombre:?string}
     */
    private function extraerEncabezado(string $texto): array
    {
        $serie = $this->primerMatch($texto, [
            '/\bserie\s*(?:del\s+documento)?\s*[:.]?\s*([A-Z0-9\-]{1,12})\b/iu',
        ]);
        $numero = $this->primerMatch($texto, [
            '/\b(?:n[úu]mero\s*(?:de\s*(?:documento|factura))?|no\.?\s*(?:de\s*factura)?|factura\s*n[úu]?m?\.?)\s*[:.]?\s*([A-Z0-9\-]{3,20})\b/iu',
            '/\bdocumento\s*[:.]?\s*([A-Z0-9\-]{3,20})\b/iu',
        ]);
        if ($numero && preg_match('/^(nit|iva|fel)$/i', $numero)) {
            $numero = null;
        }

        $fecha = $this->extraerFecha($texto);
        $total = $this->extraerTotal($texto);
        $proveedorNombre = $this->extraerNombreEmisor($texto);

        return [
            'serie' => $serie,
            'numero_factura' => $numero,
            'fecha_factura' => $fecha,
            'total' => $total,
            'proveedor_nombre' => $proveedorNombre,
        ];
    }

    private function extraerFecha(string $texto): ?string
    {
        if (preg_match('/fecha(?:\s+de\s+emisi[oó]n)?\s*[:.]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/iu', $texto, $m)) {
            return $this->normalizarFecha($m[1]);
        }
        if (preg_match('/fecha(?:\s+de\s+emisi[oó]n)?\s*[:.]?\s*(\d{4}-\d{2}-\d{2})/iu', $texto, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\b/', $texto, $m)) {
            return $this->normalizarFecha($m[1]);
        }

        return null;
    }

    private function normalizarFecha(string $raw): ?string
    {
        $raw = str_replace('-', '/', $raw);
        $parts = explode('/', $raw);
        if (count($parts) !== 3) {
            return null;
        }
        [$a, $b, $c] = $parts;
        if (strlen($c) === 2) {
            $c = '20' . $c;
        }
        $d = (int) $a;
        $m = (int) $b;
        $y = (int) $c;
        if ($d > 12 && $m <= 12) {
            // dd/mm/yyyy
        } elseif ($a > 12) {
            // already day
        } elseif ($d <= 12 && $m > 12) {
            [$d, $m] = [$m, $d];
        }
        if (!checkdate($m, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private function extraerTotal(string $texto): ?float
    {
        $candidatos = [];
        if (preg_match_all('/(?:gran\s+total|total\s+a\s+pagar|total\s+factura|total\s+general|total)\s*[:.]?\s*(?:q|gtq)?\s*([\d.,]+)/iu', $texto, $m)) {
            foreach ($m[1] as $raw) {
                $n = $this->parseNumero($raw);
                if ($n !== null && $n > 0) {
                    $candidatos[] = $n;
                }
            }
        }

        return $candidatos ? max($candidatos) : null;
    }

    private function extraerNombreEmisor(string $texto): ?string
    {
        $patrones = [
            '/nombre(?:\s+del)?\s+emisor\s*[:.]?\s*(.+)/iu',
            '/nombre\s+comercial\s*[:.]?\s*(.+)/iu',
            '/raz[oó]n\s+social\s*[:.]?\s*(.+)/iu',
            '/\bemisor\s*[:.]?\s*(.+)/iu',
            '/proveedor\s*[:.]?\s*(.+)/iu',
        ];
        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $m)) {
                $nombre = $this->limpiarNombre(trim(explode("\n", $m[1])[0]));
                if ($nombre && !$this->esNombrePropio($nombre)) {
                    return $nombre;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{descripcion:string, cantidad:int, precio_unitario:float, subtotal:?float}>
     */
    private function extraerLineas(string $texto): array
    {
        $lineas = [];
        $rows = preg_split('/\n+/', $texto) ?: [];
        $skip = '/(descripcion|descripción|cantidad|precio|subtotal|nit|iva|total|emisor|receptor|serie|factura|direcci[oó]n|tel[eé]fono|autorizaci[oó]n|fel|certificador)/iu';

        foreach ($rows as $row) {
            $row = trim($row);
            if ($row === '' || preg_match($skip, $row) && mb_strlen($row) < 40) {
                continue;
            }

            $parsed = $this->parseFilaItem($row);
            if ($parsed) {
                $lineas[] = $parsed;
            }
        }

        if (count($lineas) === 0) {
            $lineas = $this->extraerLineasBloque($texto);
        }

        $unicas = [];
        foreach ($lineas as $linea) {
            $key = $this->norm($linea['descripcion']) . '|' . $linea['cantidad'] . '|' . $linea['precio_unitario'];
            $unicas[$key] = $linea;
        }

        return array_values($unicas);
    }

    /**
     * @return array{descripcion:string, cantidad:int, precio_unitario:float, subtotal:?float}|null
     */
    private function parseFilaItem(string $row): ?array
    {
        $money = '(\d{1,3}(?:[.,]\d{3})*[.,]\d{2}|\d+[.,]\d{2})';
        $qty = '(\d{1,5}(?:[.,]\d{1,3})?)';

        // cantidad  descripcion  precio  subtotal
        if (preg_match('/^' . $qty . '\s+(.+?)\s+' . $money . '\s+' . $money . '$/u', $row, $m)) {
            return $this->armarLinea($m[2], $m[1], $m[3], $m[4]);
        }
        // descripcion  cantidad  precio  subtotal
        if (preg_match('/^(.{4,120}?)\s+' . $qty . '\s+' . $money . '\s+' . $money . '$/u', $row, $m)) {
            return $this->armarLinea($m[1], $m[2], $m[3], $m[4]);
        }
        // descripcion  cantidad  precio
        if (preg_match('/^(.{4,120}?)\s+' . $qty . '\s+' . $money . '$/u', $row, $m)) {
            $desc = $this->norm($m[1]);
            if (str_contains($desc, 'total') || str_contains($desc, 'iva')) {
                return null;
            }

            return $this->armarLinea($m[1], $m[2], $m[3], null);
        }

        return null;
    }

    /**
     * @return list<array{descripcion:string, cantidad:int, precio_unitario:float, subtotal:?float}>
     */
    private function extraerLineasBloque(string $texto): array
    {
        $lineas = [];
        $money = '(\d{1,3}(?:[.,]\d{3})*[.,]\d{2}|\d+[.,]\d{2})';
        $qty = '(\d{1,5}(?:[.,]\d{1,3})?)';
        if (preg_match_all('/' . $qty . '\s+([A-Za-zÁÉÍÓÚáéíóúÑñ0-9\/\-\.\s]{4,80}?)\s+' . $money . '\s+' . $money . '/u', $texto, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $linea = $this->armarLinea($hit[2], $hit[1], $hit[3], $hit[4]);
                if ($linea) {
                    $lineas[] = $linea;
                }
            }
        }

        return $lineas;
    }

    /**
     * @return array{descripcion:string, cantidad:int, precio_unitario:float, subtotal:?float}|null
     */
    private function armarLinea(string $descripcion, string $cantidadRaw, string $precioRaw, ?string $subtotalRaw): ?array
    {
        $descripcion = $this->limpiarNombre($descripcion);
        if ($descripcion === '' || mb_strlen($descripcion) < 3) {
            return null;
        }
        $n = $this->norm($descripcion);
        if (preg_match('/^(total|iva|nit|serie|factura|emisor|receptor)$/', $n)) {
            return null;
        }

        $cantidad = (int) round($this->parseNumero($cantidadRaw) ?? 0);
        $precio = $this->parseNumero($precioRaw);
        $subtotal = $subtotalRaw ? $this->parseNumero($subtotalRaw) : null;
        if ($cantidad <= 0 || $precio === null || $precio < 0) {
            return null;
        }
        if ($cantidad > 5000) {
            return null;
        }

        return [
            'descripcion' => $descripcion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * @return array{id:?int, nombre:?string, codigo:?string, confianza:?int}
     */
    private function emparejarProveedor(?string $nombrePdf, string $texto): array
    {
        $proveedores = BodegaProveedor::query()->where('activo', true)->get(['id', 'codigo', 'nombre']);
        $textoNorm = $this->norm($texto);
        $mejor = ['id' => null, 'nombre' => $nombrePdf, 'codigo' => null, 'confianza' => null];
        $mejorScore = 0;

        foreach ($proveedores as $prov) {
            $nombre = $this->norm((string) $prov->nombre);
            if ($nombre === '') {
                continue;
            }
            $score = 0;
            if (mb_strlen($nombre) >= 6 && str_contains($textoNorm, $nombre)) {
                $score = 95;
            }
            if ($nombrePdf) {
                similar_text($this->norm($nombrePdf), $nombre, $pct);
                $score = max($score, (int) round($pct));
            }
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = [
                    'id' => $prov->id,
                    'nombre' => $prov->nombre,
                    'codigo' => $prov->codigo,
                    'confianza' => $score,
                ];
            }
        }

        if ($mejorScore < 55) {
            return ['id' => null, 'nombre' => $nombrePdf, 'codigo' => null, 'confianza' => $mejorScore ?: null];
        }

        return $mejor;
    }

    /**
     * @param  list<array{descripcion:string, cantidad:int, precio_unitario:float, subtotal:?float}>  $lineas
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function emparejarProductos(array $lineas): array
    {
        $productos = BodegaProducto::query()
            ->where('activo', true)
            ->with(['variantes' => fn ($q) => $q->where('activo', true)])
            ->get();

        $items = [];
        $sinMatch = [];

        foreach ($lineas as $linea) {
            $match = $this->mejorProducto($linea['descripcion'], $productos);
            if (!$match) {
                $sinMatch[] = $linea;
                continue;
            }
            $variante = $this->mejorVariante($linea['descripcion'], $match);
            if (!$variante) {
                $sinMatch[] = array_merge($linea, ['motivo' => 'El producto no tiene variante activa.']);
                continue;
            }
            $items[] = [
                'variante_id' => $variante->id,
                'producto_id' => $match->id,
                'nombre' => trim(($match->codigo ? $match->codigo . ' · ' : '') . $match->nombre),
                'etiqueta' => $variante->etiqueta,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'descripcion_pdf' => $linea['descripcion'],
            ];
        }

        return [$items, $sinMatch];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BodegaProducto>  $productos
     */
    private function mejorProducto(string $descripcion, $productos): ?BodegaProducto
    {
        $desc = $this->norm($descripcion);
        $mejor = null;
        $mejorScore = 0;

        foreach ($productos as $producto) {
            $nombre = $this->norm((string) $producto->nombre);
            $codigo = $this->norm((string) $producto->codigo);
            $score = 0;
            if ($codigo !== '' && str_contains($desc, $codigo)) {
                $score = 98;
            }
            if ($nombre !== '' && str_contains($desc, $nombre)) {
                $score = max($score, 92);
            }
            similar_text($desc, $nombre, $pct);
            $score = max($score, (int) round($pct));

            $palabras = array_filter(explode(' ', $nombre), fn ($w) => mb_strlen($w) >= 4);
            $hits = 0;
            foreach ($palabras as $palabra) {
                if (str_contains($desc, $palabra)) {
                    $hits++;
                }
            }
            if ($palabras) {
                $score = max($score, (int) round(100 * $hits / count($palabras)));
            }

            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $producto;
            }
        }

        return $mejorScore >= 58 ? $mejor : null;
    }

    private function mejorVariante(string $descripcion, BodegaProducto $producto): ?BodegaVariante
    {
        $variantes = $producto->variantes;
        if ($variantes->isEmpty()) {
            return null;
        }
        if ($variantes->count() === 1) {
            return $variantes->first();
        }

        $desc = $this->norm($descripcion);
        $talla = null;
        if (preg_match('/\b(?:talla|t)\s*[:.]?\s*(xxl|xl|xs|s|m|l|\d{2})\b/i', $descripcion, $m)) {
            $talla = strtoupper($m[1]);
        } elseif (preg_match('/\b(xxl|xl|xs)\b/i', $descripcion, $m)) {
            $talla = strtoupper($m[1]);
        }

        $condicion = null;
        if (str_contains($desc, 'usado')) {
            $condicion = 'usado';
        } elseif (str_contains($desc, 'nuevo')) {
            $condicion = 'nuevo';
        }

        $genero = null;
        if (preg_match('/\b(mujer|dama)\b/', $desc)) {
            $genero = 'mujer';
        } elseif (preg_match('/\b(hombre|caballero)\b/', $desc)) {
            $genero = 'hombre';
        }

        $filtradas = $variantes;
        if ($talla) {
            $porTalla = $filtradas->filter(fn (BodegaVariante $v) => strtoupper((string) $v->talla) === $talla);
            if ($porTalla->isNotEmpty()) {
                $filtradas = $porTalla;
            }
        }
        if ($condicion) {
            $porCond = $filtradas->filter(fn (BodegaVariante $v) => $v->condicion === $condicion);
            if ($porCond->isNotEmpty()) {
                $filtradas = $porCond;
            }
        }
        if ($genero) {
            $porGen = $filtradas->filter(fn (BodegaVariante $v) => $v->genero === $genero);
            if ($porGen->isNotEmpty()) {
                $filtradas = $porGen;
            }
        }

        $nuevo = $filtradas->first(fn (BodegaVariante $v) => $v->condicion === 'nuevo');

        return $nuevo ?: $filtradas->first();
    }

    /**
     * @param  array<string, mixed>  $encabezado
     * @param  array<string, mixed>  $proveedor
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    private function advertencias(array $encabezado, array $proveedor, array $items, array $sinMatch): array
    {
        $avisos = [];
        if (empty($encabezado['numero_factura'])) {
            $avisos[] = 'No se encontró el número de factura. Complétalo a mano.';
        }
        if (empty($proveedor['id'])) {
            $avisos[] = 'No se identificó el proveedor en el catálogo. Elígelo en la lista.';
        }
        if (count($items) === 0 && count($sinMatch) === 0) {
            $avisos[] = 'No se pudieron leer las líneas de productos. Agrégalas a mano.';
        }
        if ($sinMatch) {
            $avisos[] = 'Algunos productos del PDF no coinciden con bodega. Revísalos abajo.';
        }

        return $avisos;
    }

    private function primerMatch(string $texto, array $patrones): ?string
    {
        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $m)) {
                $valor = trim($m[1]);
                if ($valor !== '') {
                    return strtoupper($valor);
                }
            }
        }

        return null;
    }

    private function parseNumero(string $raw): ?float
    {
        $raw = trim($raw);
        $raw = str_replace(['Q', 'q', ' '], '', $raw);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (substr_count($raw, ',') === 1 && preg_match('/,\d{1,2}$/', $raw)) {
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '', $raw);
        }
        if (!is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }

    private function limpiarNombre(string $nombre): string
    {
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;
        $nombre = trim($nombre, " \t.:;-");

        return mb_substr($nombre, 0, 160);
    }

    private function esNombrePropio(string $nombre): bool
    {
        $n = $this->norm($nombre);
        foreach ($this->nombresPropios as $propio) {
            if (str_contains($n, $propio)) {
                return true;
            }
        }

        return false;
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($trans) && $trans !== '') {
            $value = $trans;
        }
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
