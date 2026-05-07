<?php

/**
 * Helper: Cantidad en letra (moneda MXN)
 * Portado desde AifLibNumber (AppNissi/Yazbek/cantidadLetra.php)
 */

if (! function_exists('cantidad_letra')) {
    function cantidad_letra(float $number): string
    {
        $result = AifLibNumber::toCurrency((string) $number);
        return $result ? mb_strtoupper($result) : '';
    }
}

class AifLibNumber
{
    private static array $nStr = [
        ['cero', 'uno'],
        [
            '', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete',
            'ocho', 'nueve', 'diez', 'once', 'doce', 'trece',
            'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho',
            'diecinueve', 'veinte', 'veintiuno', 'veintidós',
            'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
            'veintisiete', 'veintiocho', 'veintinueve', 100 => 'cien',
        ],
        ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'],
        ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'],
        ['', '', 'mil', 'millón', 'mil', 'billón', 'mil', 'trillón', 'mil', 'cuatrillón', 'mil', 'quintillón', 'mil', 'sextillón', 'mil', 'septillón', 'mil', 'octillón'],
        ['', '', 'mil', 'millones', 'mil', 'billones', 'mil', 'trillones', 'mil', 'cuatrillones', 'mil', 'quintillones', 'mil', 'sextillones', 'mil', 'septillones', 'mil', 'octillones', 'mil'],
    ];

    private static function _num(int $n, int $c = 1, int $l = 1): string
    {
        return ($n == 1 && ! ($l % 2)) || ! $l ? '' : (self::$nStr[$c][$n] ?? '') . ' ';
    }

    private static function _i2str(int $lev, string $number): string
    {
        $int  = intval($num = substr($number, 0, 3));
        $next = substr($number, 3);
        $str  = '';

        if ($int) {
            if ($int == 100) {
                $str = self::_num($int, 1);
            } else {
                $c = (int) $num[0];
                $d = (int) $num[1];
                $u = (int) $num[2];
                $str = $c ? self::_num($c, 3) : '';
                $du  = ($d * 10) + $u;
                if ($du < 30) {
                    $str .= self::_num($du, ($du == 1 && $lev < 2) ? 0 : 1, $lev);
                } else {
                    $str .= $d ? self::_num($d, 2) . ($u ? 'y ' : '') : '';
                    $str .= $u ? self::_num($u, ($u + $lev < 3) ? 0 : 1) : '';
                }
            }
            $str .= self::_num($lev, ($int == 1 && ($lev % 2)) ? 4 : 5)
                . (preg_match('/^000+/', $next) ? self::_num($lev - 1, 5, ! ($lev % 2)) : '');
        }

        return $lev ? ($str . self::_i2str($lev - 1, $next)) : '';
    }

    public static function toWord(string $number): string|false
    {
        $less   = (bool) preg_match('/^\-/', $number);
        $number = preg_replace('/[^0-9\.]/', '', $number);

        if (preg_match('/^(\d{1,54})$/', $number)) {
            $lev    = (int) (floor(strlen($number) / 3) + 1);
            $number = str_pad($number, $lev * 3, '0', STR_PAD_LEFT);
            $result = self::_i2str($lev, $number);
            $result || ($result = self::_num(0, 0));
        } elseif (preg_match('/^\d{1,54}\.\d{1,54}$/', $number)) {
            [$number, $decimal] = explode('.', $number);
            $result = self::toWord($number) . ' punto ';
            for ($i = 0; $i < (strlen($decimal) - 1); $i++) {
                if ($decimal[$i]) break;
                $result .= self::_num(0, 0);
            }
            $result .= self::toWord($decimal);
        }

        return isset($result) ? ($less ? 'menos ' : '') . $result : false;
    }

    public static function toCurrency(string $number): string|false
    {
        $number = preg_replace('/[^0-9\.\-]/', '', $number);
        if (preg_match('/^[\-]{0,1}(\d{1,54})$/', $number)) {
            $number .= '.00';
        } elseif (! preg_match('/^[\-]{0,1}\d{1,54}\.\d{1,54}$/', $number)) {
            return false;
        }

        [$integer, $decimal] = explode('.', $number);
        $words = self::toWord($integer);
        if (! $words) return false;

        if (preg_match('/(llones|llón)$/', $words)) {
            $words .= ' de pesos ';
        } else {
            $words = preg_match('/uno$/', $words)
                ? (preg_replace('/uno$/', '', $words) . ' un peso ')
                : ($words . ' pesos ');
        }

        // Tomar solo los 2 primeros dígitos decimales
        $dec = (int) round((float) ('0.' . $decimal) * 100);

        return $words . $dec . '/100 M.N.';
    }
}
