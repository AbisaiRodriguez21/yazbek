<?php

/**
 * Helper: fecha_helper
 * Funciones para formatear fechas en español sin depender de setlocale()
 * y para corregir encoding de texto proveniente de la BD.
 */

// ══════════════════════════════════════════════════════════════════
// CORRECCIÓN DE ENCODING (latin1 doble-codificado como UTF-8)
//
 // Problema común: datos guardados como bytes UTF-8 en columnas latin1.
// Al leer con conexión utf8mb4, MySQL re-codifica → "JosÃ©" en vez de "José".
//
// Solución: detectar si el string está doble-codificado y revertirlo.
// ══════════════════════════════════════════════════════════════════

if (! function_exists('fix_enc')) {
    /**
     * Corrige un string con doble codificación UTF-8/latin1.
     * Si el string ya está bien, lo devuelve sin cambios.
     *
     * @param string|null $str  Texto recibido de la BD.
     * @return string           Texto corregido.
     */
    function fix_enc(?string $str): string
    {
        if ($str === null || $str === '') {
            return (string) $str;
        }

        // ── Por qué falla mb_convert_encoding con Windows-1252 ────────────────
        // cp1252 tiene 5 bytes indefinidos: 0x81, 0x8D, 0x8F, 0x90, 0x9D.
        // Cuando el segundo byte UTF-8 de un char (ej: Á=C3 81, Í=C3 8D) es uno
        // de esos, mb_convert_encoding lo descarta → el check de UTF-8 válido falla
        // y el string corrupto se devuelve sin corregir.
        //
        // ── Solución: procesar carácter por carácter ──────────────────────────
        // Para cada char Unicode del string de entrada:
        //   - Si está en U+0000-U+007F (ASCII)     → byte directo (sin cambio).
        //   - Si está en U+0080-U+00FF (Latin-1)   → su byte raw (chr($cp)).
        //   - Si es uno de los 27 chars especiales de cp1252 (U+2018, U+00C3, …)
        //     → su byte cp1252 correspondiente.
        //   - Si no puede representarse como 1 byte → el string NO estaba doble-
        //     codificado; devolver original intacto.
        //
        // Después se verifica si los bytes resultantes forman UTF-8 válido.
        // Si sí → el string ESTABA doble-codificado → devolver la versión corregida.
        // Si no → el string ya estaba bien → devolver original.
        // ─────────────────────────────────────────────────────────────────────

        // Mapa cp1252: codepoints Unicode > 0xFF que tienen byte cp1252 en 0x80-0x9F
        static $cp1252 = [
            0x20AC => 0x80, // €
            0x201A => 0x82, // ‚
            0x0192 => 0x83, // ƒ
            0x201E => 0x84, // „
            0x2026 => 0x85, // …
            0x2020 => 0x86, // †
            0x2021 => 0x87, // ‡
            0x02C6 => 0x88, // ˆ
            0x2030 => 0x89, // ‰
            0x0160 => 0x8A, // Š
            0x2039 => 0x8B, // ‹
            0x0152 => 0x8C, // Œ
            0x017D => 0x8E, // Ž
            0x2018 => 0x91, // '  ← Ñ en doble-codificación usa este
            0x2019 => 0x92, // '
            0x201C => 0x93, // "  ← Ó usa este
            0x201D => 0x94, // "
            0x2022 => 0x95, // •
            0x2013 => 0x96, // –
            0x2014 => 0x97, // —
            0x02DC => 0x98, // ˜
            0x2122 => 0x99, // ™
            0x0161 => 0x9A, // š
            0x203A => 0x9B, // ›
            0x0153 => 0x9C, // œ
            0x017E => 0x9E, // ž
            0x0178 => 0x9F, // Ÿ
        ];

        $result  = '';
        $changed = false;

        // Dividir en caracteres UTF-8 individuales
        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) return $str;

        foreach ($chars as $ch) {
            $cp = mb_ord($ch, 'UTF-8');

            if ($cp <= 0x7F) {
                // ASCII puro — byte directo, sin cambio
                $result .= chr($cp);

            } elseif ($cp <= 0xFF) {
                // Latin-1 (U+0080–U+00FF) — tomar raw byte
                // Incluye los 5 bytes indefinidos en cp1252 (0x81, 0x8D, 0x8F, 0x90, 0x9D)
                // que MySQL mapea a U+0081, U+008D, etc.
                $result  .= chr($cp);
                $changed  = true;

            } elseif (isset($cp1252[$cp])) {
                // Carácter especial cp1252 (ej: U+2018 = ' → 0x91)
                $result  .= chr($cp1252[$cp]);
                $changed  = true;

            } else {
                // El carácter no puede representarse como 1 byte cp1252
                // → el string NO está doble-codificado → devolver intacto
                return $str;
            }
        }

        // ¿Hubo algún cambio Y el resultado es UTF-8 válido?
        if ($changed && $result !== $str && mb_check_encoding($result, 'UTF-8')) {
            return $result;
        }

        return $str;
    }
}

if (! function_exists('fix_enc_row')) {
    /**
     * Aplica fix_enc() a todos los campos string de un array asociativo (una fila de BD).
     *
     * @param array $row  Fila de resultado de una query.
     * @return array      Fila con encoding corregido.
     */
    function fix_enc_row(array $row): array
    {
        foreach ($row as $k => $v) {
            if (is_string($v)) {
                $row[$k] = fix_enc($v);
            }
        }
        return $row;
    }
}

if (! function_exists('fix_enc_rows')) {
    /**
     * Aplica fix_enc() a todos los campos string de un array de filas (resultado de query).
     *
     * @param array $rows  Array de filas (getResultArray()).
     * @return array       Filas con encoding corregido.
     */
    function fix_enc_rows(array $rows): array
    {
        return array_map('fix_enc_row', $rows);
    }
}


// ══════════════════════════════════════════════════════════════════
// FECHAS EN ESPAÑOL
// ══════════════════════════════════════════════════════════════════

if (! function_exists('fecha_es')) {
    /**
     * Devuelve la fecha en español.
     *
     * Formatos disponibles:
     *   'full'   → "Domingo, 17 de Mayo de 2026"
     *   'short'  → "17 de Mayo de 2026"
     *   'abbr'   → "Dom 17 May 2026"
     *   'mes'    → "Mayo"
     *   'mes_ab' → "May"
     *   'dia'    → "Domingo"
     *   'dia_ab' → "Dom"
     *
     * @param string   $formato   Uno de los formatos indicados arriba.
     * @param int|null $timestamp Unix timestamp (null = ahora).
     */
    function fecha_es(string $formato = 'full', ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $diasLargo  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
        $diasCorto  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        $mesesLargo = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $mesesCorto = ['','Ene','Feb','Mar','Abr','May','Jun',
                       'Jul','Ago','Sep','Oct','Nov','Dic'];

        $w   = (int) date('w', $timestamp);   // 0=domingo
        $d   = (int) date('j', $timestamp);
        $m   = (int) date('n', $timestamp);
        $Y   = (int) date('Y', $timestamp);

        return match ($formato) {
            'full'   => "{$diasLargo[$w]}, {$d} de {$mesesLargo[$m]} de {$Y}",
            'short'  => "{$d} de {$mesesLargo[$m]} de {$Y}",
            'abbr'   => "{$diasCorto[$w]} {$d} {$mesesCorto[$m]} {$Y}",
            'mes'    => $mesesLargo[$m],
            'mes_ab' => $mesesCorto[$m],
            'dia'    => $diasLargo[$w],
            'dia_ab' => $diasCorto[$w],
            default  => "{$diasLargo[$w]}, {$d} de {$mesesLargo[$m]} de {$Y}",
        };
    }
}

if (! function_exists('nombre_mes_es')) {
    /**
     * Devuelve el nombre del mes en español dado su número (1-12).
     *
     * @param int  $mes     Número del mes (1-12).
     * @param bool $abrev   true = abreviado (May), false = completo (Mayo).
     */
    function nombre_mes_es(int $mes, bool $abrev = false): string
    {
        $mesesLargo = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $mesesCorto = ['','Ene','Feb','Mar','Abr','May','Jun',
                       'Jul','Ago','Sep','Oct','Nov','Dic'];

        if ($mes < 1 || $mes > 12) return '';
        return $abrev ? $mesesCorto[$mes] : $mesesLargo[$mes];
    }
}
