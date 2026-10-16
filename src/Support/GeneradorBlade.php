<?php

namespace InformaticadosLago\GeneradorPlantillas\Support;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Pasa el XML de una plantilla (versión 1) a una vista Blade para Dompdf. La
 * vista recibe los datos en $datos, un array por grupos: {{grupo.campo}} en los
 * textos y, en las tablas, el campo de cada columna dentro de cada elemento de
 * su lista. Si recibe $marcaAgua (un texto), lo pone en grande y en diagonal en
 * cada hoja (un borrador: EN PREPARACIÓN). Todo va en mm desde la esquina superior izquierda de la página.
 * Cabecera y pie se repiten en cada hoja; el cuerpo (una fila por elemento de
 * su lista) corre entre los dos. Una tabla libre con la lista vacía no sale.
 *
 * Lo que escribe el usuario en la plantilla nunca llega a la Blade como código:
 * los textos van tal cual (solo < > & como entidades, para que el HTML no se
 * rompa), pero el que lleva algo que Blade o PHP ejecutarían no se admite
 * (codigoEn(); ValidadorPlantilla lo rechaza al guardar). Las rutas solo pueden
 * ser grupo.campo y los formatos, los de Formato. Los números y colores se validan.
 */
class GeneradorBlade
{
    /** Ancho y alto en vertical, en mm. */
    const FORMATOS = ['A3' => [297, 420], 'A4' => [210, 297], 'A5' => [148, 210]];

    /** Lo que el editor admite como imagen (logo y marca de agua). */
    const TIPOS_IMAGEN = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];

    /** {{ruta}} o {{ruta|formato}}, como en el editor. */
    const CAMPO = '/\{\{\s*([\w.]+)\s*(?:\|\s*(\w+)\s*)?\}\}/';

    /**
     * El primer texto de la plantilla (de un <texto> o el título de una
     * columna) que lleva algo que Blade o PHP tomarían por código: {{ o {!!
     * que no son un campo, una @ de directiva (@php, @if, @{{…) o <?. null si
     * no hay ninguno.
     */
    public static function codigoEn(DOMDocument $documento): ?string
    {
        $textos = [];
        foreach ($documento->getElementsByTagName('texto') as $texto) {
            $textos[] = $texto->textContent;
        }
        foreach ($documento->getElementsByTagName('columna') as $columna) {
            $textos[] = $columna->getAttribute('titulo');
        }

        foreach ($textos as $texto) {
            // Cada campo pasa a un espacio: en la Blade lo que va justo después es «!!}», que no es letra.
            $resto = preg_replace(self::CAMPO, ' ', $texto);
            // Blade toma por directiva la @ que no va detrás de una letra, número o _ (en ASCII).
            if (str_contains($resto, '{{') || str_contains($resto, '{!!') || str_contains($resto, '<?')
                || str_contains($texto, '@{{') || preg_match('/(?<![A-Za-z0-9_])@/', $resto)) {
                return $texto;
            }
        }

        return null;
    }

    public function blade(string $xml): string
    {
        $documento = new DOMDocument();
        if (trim($xml) === '' || ! @$documento->loadXML($xml)) {
            throw new InvalidArgumentException('El XML de la plantilla está mal formado.');
        }
        $raiz = $documento->documentElement;
        if ($raiz->nodeName !== 'plantilla') {
            throw new InvalidArgumentException('El elemento raíz de la plantilla debe ser <plantilla>.');
        }
        if (($texto = self::codigoEn($documento)) !== null) {
            throw new InvalidArgumentException("Un texto de la plantilla lleva código: «{$texto}».");
        }

        $pagina = $this->hijo($raiz, 'pagina');
        [$ancho, $alto] = self::FORMATOS[$pagina?->getAttribute('formato')] ?? self::FORMATOS['A4'];
        if ($pagina?->getAttribute('orientacion') === 'horizontal') {
            [$ancho, $alto] = [$alto, $ancho];
        }
        $margen    = $pagina ? $this->hijo($pagina, 'margen') : null;
        $izquierda = $this->numero($margen, 'izquierda', 15);
        $derecha   = $this->numero($margen, 'derecha', 15);

        $cabecera = $this->hijo($raiz, 'cabecera');
        $pie      = $this->hijo($raiz, 'pie');
        $cuerpo   = $this->hijo($raiz, 'cuerpo');
        $altoCab  = $this->numero($cabecera, 'altura', 0);
        $altoPie  = $this->numero($pie, 'altura', 0);

        // El cuerpo corre por el hueco que dejan la cabecera y el pie, entre los
        // márgenes laterales; lo demás va en capas fijas (se repiten en cada hoja)
        // que se sacan a la esquina de la página con desplazamientos negativos.
        $fijo = fn (float $top, float $alto) => 'position: fixed; left: ' . $this->mm(-$izquierda) . '; top: ' . $this->mm($top)
            . '; width: ' . $this->mm($ancho) . '; height: ' . $this->mm($alto) . ';';

        $nombre = preg_replace('/[^\p{L}\p{N} _.\-]/u', '', $raiz->getAttribute('nombre'));
        $blade  = '{{-- Plantilla «' . $nombre . '» del generador de plantillas. '
            . "No se toca a mano: se escribe de nuevo cada vez que se guarda la plantilla. Recibe los datos en \$datos "
            . "y, si hace falta, una marca de agua en \$marcaAgua (p. ej. EN PREPARACIÓN). --}}\n"
            . "@use('InformaticadosLago\\GeneradorPlantillas\\Support\\Formato')\n"
            . '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>'
            . '@page { size: ' . $this->mm($ancho) . ' ' . $this->mm($alto) . '; margin: ' . $this->mm($altoCab) . ' '
            . $this->mm($derecha) . ' ' . $this->mm($altoPie) . ' ' . $this->mm($izquierda) . '; }'
            . "body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #222; }"
            . '.capa { overflow: visible; } .el { position: absolute; }'
            . '.texto { line-height: 1.2; overflow: hidden; }'
            . 'table { border-collapse: collapse; table-layout: fixed; }'
            . '.tabla th, .tabla td { padding: 0.8mm 1mm; line-height: 1.3; white-space: nowrap; overflow: hidden; }'
            . '.tabla th { font-weight: bold; }'
            . '.cuerpo { width: 100%; }'
            . '.cuerpo th { font-size: 8pt; text-transform: uppercase; color: #666; font-weight: normal; border-bottom: 0.3mm solid #999; padding: 1mm; }'
            . '.cuerpo td { padding: 1.3mm 1mm; border-bottom: 0.3mm solid #ddd; }'
            . "</style></head><body>\n";

        if ($marca = $this->hijo($raiz, 'marca')) {
            $blade .= '<div class="capa" style="' . $fijo(-$altoCab, $alto) . '">' . $this->marca($marca, $ancho, $alto) . "</div>\n";
        }
        // La marca de agua que pone el programa al imprimir (p. ej. EN PREPARACIÓN), encima de la de la plantilla.
        $blade .= "@if (! empty(\$marcaAgua))\n"
            . '<div class="capa" style="' . $fijo(-$altoCab, $alto) . '"><div class="el" style="left: 0; top: ' . $this->mm($alto / 2 - 15)
            . '; width: ' . $this->mm($ancho) . '; text-align: center; opacity: 0.15; transform: rotate(315deg);">'
            . '<div style="font-size: 60pt; font-weight: bold; line-height: 1; white-space: nowrap; color: #b91c1c;">{{ $marcaAgua }}</div>'
            . "</div></div>\n@endif\n";
        if ($cabecera) {
            $blade .= '<div class="capa" style="' . $fijo(-$altoCab, $altoCab) . '">' . $this->elementos($cabecera, 0) . "</div>\n";
        }
        if ($pie) {
            // y del pie: desde el borde superior de la página, como todo lo demás.
            $blade .= '<div class="capa" style="' . $fijo($alto - $altoCab - $altoPie, $altoPie) . '">'
                . $this->elementos($pie, $alto - $altoPie) . "</div>\n";
        }
        if ($cuerpo) {
            $blade .= $this->cuerpo($cuerpo);
        }

        return $blade . "</body></html>\n";
    }

    // --- Elementos ---

    /** Los elementos de una banda; $origen es su y en la página. */
    private function elementos(DOMElement $banda, float $origen): string
    {
        $blade = '';
        foreach ($banda->childNodes as $nodo) {
            if ($nodo instanceof DOMElement) {
                $blade .= $this->elemento($nodo, 0, $origen);
            }
        }

        return $blade;
    }

    private function elemento(DOMElement $nodo, float $origenX, float $origenY, bool $dentro = false): string
    {
        $x = $this->numero($nodo, 'x', 0) - $origenX;
        $y = $this->numero($nodo, 'y', 0) - $origenY;

        return match ($nodo->nodeName) {
            'texto'      => $this->texto($nodo, $x, $y),
            'logo'       => $this->logo($nodo, $x, $y),
            'linea'      => $this->linea($nodo, $x, $y),
            'rectangulo' => $dentro ? '' : $this->rectangulo($nodo, $x, $y),
            'tabla'      => $dentro ? '' : $this->tabla($nodo, $x, $y),
            default      => '',
        };
    }

    private function texto(DOMElement $nodo, float $x, float $y): string
    {
        $estilo = $this->caja($nodo, $x, $y)
            . 'font-size: ' . $this->numero($nodo, 'tam', 10) . 'pt;'
            . ($nodo->getAttribute('negrita') === 'true' ? 'font-weight: bold;' : '')
            . 'text-align: ' . $this->alineacion($nodo->getAttribute('alinear')) . ';';
        if (($borde = $this->numero($nodo, 'borde', 0)) > 0) {
            $estilo .= 'border: ' . $this->mm($borde) . ' solid #000;';
        }
        if ($relleno = $this->color($nodo->getAttribute('relleno'))) {
            $estilo .= "background-color: {$relleno};";
        }

        return '<div class="el texto" style="' . $estilo . '">' . $this->conCampos($nodo->textContent) . "</div>\n";
    }

    private function logo(DOMElement $nodo, float $x, float $y): string
    {
        $imagen = $this->imagen($nodo);

        return $imagen ? '<div class="el" style="' . $this->caja($nodo, $x, $y) . '">'
            . '<img src="' . $imagen . '" style="width: 100%; height: 100%;"></div>' . "\n" : '';
    }

    /** La línea se dibuja horizontal con su grosor centrado en y; el giro, alrededor de su centro. */
    private function linea(DOMElement $nodo, float $x, float $y): string
    {
        $grosor = $this->numero($nodo, 'grosor', 0.3);
        $estilo = 'left: ' . $this->mm($x) . '; top: ' . $this->mm($y - $grosor / 2) . '; width: ' . $this->mm($this->numero($nodo, 'largo', 50))
            . '; height: ' . $this->mm($grosor) . '; background-color: #000;' . $this->giro($nodo);

        return '<div class="el" style="' . $estilo . '"></div>' . "\n";
    }

    /** Rectángulo, con lo que lleve dentro en coordenadas relativas a su esquina (gira con él). */
    private function rectangulo(DOMElement $nodo, float $x, float $y): string
    {
        $estilo = $this->caja($nodo, $x, $y);
        if (($grosor = $this->numero($nodo, 'grosor', 0.3)) > 0) {
            $estilo .= 'border: ' . $this->mm($grosor) . ' solid #000;';
        }
        if ($relleno = $this->color($nodo->getAttribute('relleno'))) {
            $estilo .= "background-color: {$relleno};";
        }

        $dentro = '';
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof DOMElement) {
                // El borde ocupa sitio dentro de la caja: se descuenta para que el
                // contenido quede donde dice su x, y.
                $dentro .= $this->elemento($hijo, $grosor, $grosor, true);
            }
        }

        return '<div class="el" style="' . $estilo . '">' . $dentro . "</div>\n";
    }

    /** Tabla libre: una fila por elemento de su lista (o una sola, con rutas completas, sin lista). */
    private function tabla(DOMElement $nodo, float $x, float $y): string
    {
        $lista    = trim($nodo->getAttribute('datos'));
        $columnas = $this->columnas($nodo);
        if (! $columnas || ($lista !== '' && ! $this->esRuta($lista))) {
            return '';
        }

        $borde   = $this->numero($nodo, 'borde', 0.2);
        $celda   = $borde > 0 ? 'border: ' . $this->mm($borde) . ' solid #000;' : '';
        $relleno = $this->color($nodo->getAttribute('relleno'));
        $estilo  = 'left: ' . $this->mm($x) . '; top: ' . $this->mm($y) . '; width: ' . $this->mm($this->numero($nodo, 'ancho', 100))
            . '; font-size: ' . $this->numero($nodo, 'tam', 9) . 'pt;' . $this->giro($nodo);

        $blade = '<table class="el tabla" style="' . $estilo . '">';
        if ($nodo->getAttribute('titulos') !== 'false') {
            $blade .= '<tr>';
            foreach ($columnas as $columna) {
                $blade .= '<th style="' . $this->celda($columna) . $celda . ($relleno ? "background-color: {$relleno};" : '') . '">'
                    . $this->literal($columna['titulo']) . '</th>';
            }
            $blade .= '</tr>';
        }

        if ($lista === '') {
            return $blade . $this->fila('$datos', $columnas, $celda) . "</table>\n";
        }

        $filas = 'Formato::lista($datos, ' . $this->cadena($lista) . ')';

        return "\n@if (count({$filas}))\n" . $blade
            . "\n@foreach ({$filas} as \$fila)\n" . $this->fila('$fila', $columnas, $celda) . "\n@endforeach\n"
            . "</table>\n@endif\n";
    }

    /** Tabla del cuerpo: títulos que se repiten en cada hoja y una fila por elemento de la lista. */
    private function cuerpo(DOMElement $nodo): string
    {
        $lista    = trim($nodo->getAttribute('datos')) ?: 'lineas';
        $columnas = $this->columnas($nodo);
        if (! $columnas) {
            return '';
        }

        $blade = '<table class="cuerpo"><thead><tr>';
        foreach ($columnas as $columna) {
            $blade .= '<th style="' . $this->celda($columna) . '">' . $this->literal($columna['titulo']) . '</th>';
        }
        $blade .= '</tr></thead><tbody>';
        if ($this->esRuta($lista)) {
            $blade .= "\n@foreach (Formato::lista(\$datos, " . $this->cadena($lista) . ") as \$fila)\n"
                . $this->fila('$fila', $columnas, '') . "\n@endforeach\n";
        }

        return $blade . "</tbody></table>\n";
    }

    /** Una fila: cada celda, el campo de su columna dentro de $variable. */
    private function fila(string $variable, array $columnas, string $celda): string
    {
        $blade = '<tr>';
        foreach ($columnas as $columna) {
            $valor = $this->esRuta($columna['campo'])
                ? '{{ Formato::valor(' . $variable . ', ' . $this->cadena($columna['campo']) . ', ' . $this->cadena($this->formato($columna['formato'])) . ') }}'
                : '';
            $blade .= '<td style="' . $this->celda($columna) . $celda . '">' . $valor . '</td>';
        }

        return $blade . '</tr>';
    }

    /** Marca de agua: a página completa, detrás de todo y centrada. */
    private function marca(DOMElement $nodo, float $ancho, float $alto): string
    {
        $opacidad = min(1, max(0, $this->numero($nodo, 'opacidad', 0.08)));
        $giro     = $this->numero($nodo, 'giro', 0);

        if ($imagen = $this->hijo($nodo, 'logo')) {
            $src = $this->imagen($imagen);
            if (! $src) {
                return '';
            }
            $anchoImagen = $this->numero($imagen, 'ancho', 120);
            $contenido   = '<img src="' . $src . '" style="width: ' . $this->mm($anchoImagen) . ';">';
        } elseif ($texto = $this->hijo($nodo, 'texto')) {
            $anchoImagen = $ancho;
            $contenido   = '<div style="font-size: ' . $this->numero($texto, 'tam', 100) . 'pt; line-height: 1; white-space: nowrap;'
                . ($texto->getAttribute('negrita') === 'false' ? '' : 'font-weight: bold;')
                . 'color: ' . ($this->color($texto->getAttribute('color')) ?: '#000000') . ';">' . $this->conCampos($texto->textContent) . '</div>';
        } else {
            return '';
        }

        return '<div class="el" style="left: ' . $this->mm(($ancho - $anchoImagen) / 2) . '; top: ' . $this->mm($alto / 2 - 20)
            . '; width: ' . $this->mm($anchoImagen) . '; text-align: center; opacity: ' . $opacidad . ';'
            . ($giro ? "transform: rotate({$giro}deg);" : '') . '">' . $contenido . '</div>';
    }

    // --- Textos y datos ---

    /** El texto con cada {{ruta|formato}} cambiado por su valor; los saltos de línea, <br>. */
    private function conCampos(string $texto): string
    {
        $blade = '';
        $desde = 0;
        preg_match_all(self::CAMPO, $texto, $campos, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($campos as $campo) {
            [$marca, $posicion] = $campo[0];
            $blade .= nl2br($this->literal(substr($texto, $desde, $posicion - $desde)), false)
                . '{!! nl2br(e(Formato::valor($datos, ' . $this->cadena($campo[1][0]) . ', '
                . $this->cadena($this->formato($campo[2][0] ?? '')) . ')), false) !!}';
            $desde = $posicion + strlen($marca);
        }

        return $blade . nl2br($this->literal(substr($texto, $desde)), false);
    }

    /** Texto fijo, tal cual: solo < > & como entidades (lo que sea código ya lo ha rechazado codigoEn()). */
    private function literal(string $texto): string
    {
        return htmlspecialchars($texto, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Una ruta o un formato como cadena de PHP; solo llegan aquí [\w.]. */
    private function cadena(string $valor): string
    {
        return "'" . $valor . "'";
    }

    private function esRuta(string $ruta): bool
    {
        return (bool) preg_match('/^\w+(\.\w+)*$/', $ruta);
    }

    private function formato(string $formato): string
    {
        return in_array($formato, Formato::FORMATOS, true) ? $formato : '';
    }

    // --- Ayudas ---

    /** Las columnas de una tabla; ancho, en % sobre lo que sumen entre todas. */
    private function columnas(DOMElement $nodo): array
    {
        $columnas = [];
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof DOMElement && $hijo->nodeName === 'columna') {
                $columnas[] = [
                    'campo'   => trim($hijo->getAttribute('campo')),
                    'titulo'  => $hijo->getAttribute('titulo'),
                    'ancho'   => $this->numero($hijo, 'ancho', 10),
                    'alinear' => $hijo->getAttribute('alinear'),
                    'formato' => $hijo->getAttribute('formato'),
                ];
            }
        }

        $total = array_sum(array_column($columnas, 'ancho')) ?: 1;

        return array_map(fn ($columna) => ['ancho' => round($columna['ancho'] * 100 / $total, 3)] + $columna, $columnas);
    }

    /** Ancho y alineación de una celda (Dompdf no hace caso del ancho en <col>). */
    private function celda(array $columna): string
    {
        return 'width: ' . $columna['ancho'] . '%; text-align: ' . $this->alineacion($columna['alinear']) . ';';
    }

    private function caja(DOMElement $nodo, float $x, float $y): string
    {
        return 'left: ' . $this->mm($x) . '; top: ' . $this->mm($y) . '; width: ' . $this->mm($this->numero($nodo, 'ancho', 50))
            . '; height: ' . $this->mm($this->numero($nodo, 'alto', 10)) . ';' . $this->giro($nodo);
    }

    /** Giro en grados, horario, alrededor del centro de la caja. */
    private function giro(DOMElement $nodo): string
    {
        $giro = $this->numero($nodo, 'giro', 0);

        return $giro ? "transform: rotate({$giro}deg); transform-origin: 50% 50%;" : '';
    }

    /** data: URI del logo, si trae una imagen válida en base64. */
    private function imagen(DOMElement $nodo): ?string
    {
        $datos = preg_replace('/\s+/', '', $nodo->textContent);
        $tipo  = $nodo->getAttribute('tipo');

        return $datos !== '' && in_array($tipo, self::TIPOS_IMAGEN, true) && preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $datos)
            ? "data:{$tipo};base64,{$datos}" : null;
    }

    private function alineacion(string $alinear): string
    {
        return match ($alinear) {
            'centro'  => 'center',
            'derecha' => 'right',
            default   => 'left',
        };
    }

    private function color(string $color): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color) ? $color : null;
    }

    private function hijo(DOMElement $nodo, string $nombre): ?DOMElement
    {
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof DOMElement && $hijo->nodeName === $nombre) {
                return $hijo;
            }
        }

        return null;
    }

    private function numero(?DOMElement $nodo, string $atributo, float $defecto): float
    {
        $valor = $nodo?->getAttribute($atributo);

        return is_numeric($valor) ? (float) $valor : $defecto;
    }

    private function mm(float $valor): string
    {
        return round($valor, 3) . 'mm';
    }
}
