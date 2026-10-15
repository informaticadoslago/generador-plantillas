<?php

namespace InformaticadosLago\GeneradorPlantillas\Support;

use DOMDocument;

/**
 * Las mismas comprobaciones que api/index.php (modo suelto), más que ningún
 * texto lleve código (va tal cual a la Blade). Cada método devuelve el mensaje
 * de error, o null si está bien.
 */
class ValidadorPlantilla
{
    /** Lo que el editor admite como imagen (logo y marca de agua). */
    const TIPOS_IMAGEN = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];

    const ALFABETO_BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    public function nombre(string $nombre): ?string
    {
        if (! preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.\-]{0,79}$/u', $nombre)) {
            return 'Nombre no válido: usa letras, números, espacios, guion, guion bajo o punto (máximo 80 caracteres).';
        }

        return null;
    }

    public function xml(string $xml, string $nombre): ?string
    {
        $maximo = (int) config('generador-plantillas.max_xml', 5_000_000);
        if ($xml === '' || strlen($xml) > $maximo) {
            return 'El XML está vacío o supera ' . round($maximo / 1_000_000, 1) . ' MB.';
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return 'El XML no puede llevar DOCTYPE ni entidades.';
        }

        $dom = $this->cargar($xml);
        if ($dom === null) {
            return 'El XML está mal formado.';
        }
        $raiz = $dom->documentElement;
        if ($raiz->nodeName !== 'plantilla') {
            return 'El elemento raíz debe ser <plantilla>.';
        }
        if ($raiz->getAttribute('nombre') !== $nombre) {
            return 'El atributo nombre de <plantilla> debe coincidir con el nombre de la plantilla.';
        }
        // Los textos van tal cual a la Blade: nada que Blade o PHP puedan ejecutar.
        if (($texto = GeneradorBlade::codigoEn($dom)) !== null) {
            return 'Este texto lleva algo que parece código ({{ o {!! fuera de un campo, @ de directiva o <?) y no se admite: «'
                . mb_strimwidth($texto, 0, 80, '…') . '».';
        }

        // Las imágenes de los logos van dentro del XML en base64.
        foreach ($dom->getElementsByTagName('logo') as $logo) {
            $datos = preg_replace('/\s+/', '', $logo->textContent);
            if ($datos === '') {
                continue;
            }
            $resto = substr($datos, strspn($datos, self::ALFABETO_BASE64));
            if (! in_array($logo->getAttribute('tipo'), self::TIPOS_IMAGEN, true) || ! in_array($resto, ['', '=', '=='], true)) {
                return 'La imagen de un logo no es válida (tipo o base64 incorrecto).';
            }
        }

        return null;
    }

    /** El mismo XML con otro nombre en <plantilla nombre="...">. */
    public function renombrar(string $xml, string $nombre): string
    {
        $dom = $this->cargar($xml);
        if ($dom === null) {
            return $xml;
        }
        $dom->documentElement->setAttribute('nombre', $nombre);

        return $dom->saveXML();
    }

    private function cargar(string $xml): ?DOMDocument
    {
        $anterior = libxml_use_internal_errors(true);
        $dom      = new DOMDocument();
        $correcto = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $correcto && $dom->documentElement !== null ? $dom : null;
    }
}
