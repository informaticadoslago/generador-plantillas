<?php

namespace InformaticadosLago\GeneradorPlantillas\Support;

use DateTimeInterface;

/**
 * Lo que usan las Blades que escribe GeneradorBlade para sacar los datos: el
 * valor de una ruta (grupo.campo) con su formato, como la vista previa del
 * editor, y las listas que se repiten.
 */
final class Formato
{
    /** Formatos que entiende: {{campo|moneda}} en los textos y formato="" en las columnas. */
    const FORMATOS = ['moneda', 'numero', 'fecha'];

    /** El valor de $ruta en $datos (array u objeto), con formato; vacío si no existe. */
    public static function valor(mixed $datos, string $ruta, string $formato = ''): string
    {
        $valor = data_get($datos, $ruta);

        if ($valor === null || $valor === '') {
            return '';
        }
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('d/m/Y');
        }
        if (is_bool($valor)) {
            return $valor ? 'sí' : 'no';
        }
        if (! is_scalar($valor)) {
            return '';
        }

        return match ($formato) {
            'moneda' => is_numeric($valor) ? number_format((float) $valor, 2, ',', '.') . ' €' : (string) $valor,
            'numero' => is_numeric($valor) ? rtrim(rtrim(number_format((float) $valor, 4, ',', '.'), '0'), ',') : (string) $valor,
            'fecha'  => preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $valor, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $valor,
            default  => (string) $valor,
        };
    }

    /** Los elementos de la lista $ruta (vacía si no existe). */
    public static function lista(mixed $datos, string $ruta): array
    {
        $lista = data_get($datos, $ruta, []);

        return is_iterable($lista) ? array_values(is_array($lista) ? $lista : iterator_to_array($lista)) : [];
    }
}
