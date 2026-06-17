<?php

class JalaliDate
{
    public static function isGregorian(?string $value): bool
    {
        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    public static function isJalali(?string $value): bool
    {
        $value = self::normalize($value);
        if (!preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $m)) return false;
        $jy = (int)$m[1];
        $jm = (int)$m[2];
        $jd = (int)$m[3];
        return $jy >= 1200 && $jy <= 1600 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= self::monthLength($jy, $jm);
    }

    public static function normalize(?string $value): string
    {
        $value = trim((string)$value);
        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '-' => '/', '.' => '/',
        ]);
        return preg_replace('/\s+/', '', $value) ?? '';
    }

    public static function toGregorian(?string $value): ?string
    {
        $value = self::normalize($value);
        if ($value === '') return null;
        if (self::isGregorian(str_replace('/', '-', $value)) && (int)substr($value, 0, 4) > 1700) {
            return str_replace('/', '-', $value);
        }
        if (!self::isJalali($value)) return null;
        [$jy, $jm, $jd] = array_map('intval', explode('/', $value));
        [$gy, $gm, $gd] = self::jalaliToGregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    public static function toJalali(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        $date = substr($value, 0, 10);
        if (!self::isGregorian($date)) return $value;
        [$gy, $gm, $gd] = array_map('intval', explode('-', $date));
        [$jy, $jm, $jd] = self::gregorianToJalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    public static function toJalaliDateTime(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        $date = self::toJalali(substr($value, 0, 10));
        $time = trim(substr($value, 10, 6));
        return trim($date . ($time !== '' ? ' ' . $time : ''));
    }

    public static function inputValue(?string $gregorianDate): string
    {
        return self::toJalali($gregorianDate);
    }

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) return 31;
        if ($jm <= 11) return 30;
        return self::isLeapJalali($jy) ? 30 : 29;
    }

    private static function isLeapJalali(int $jy): bool
    {
        $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
        $jump = 0;
        $j = 1;
        $breakCount = count($breaks);
        do {
            $jp = $breaks[$j - 1];
            $jm = $breaks[$j];
            $jump = $jm - $jp;
            $j++;
        } while ($j < $breakCount && $jy >= $jm);
        $n = $jy - $jp;
        if ($jump - $n < 6) $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        return ((($n + 1) % 33) - 1) % 4 === 0;
    }

    private static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    private static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv((($jy % 33) + 3), 4) + $jd;
        $days += $jm < 7 ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 1; $gm <= 12 && $gd > $sal[$gm]; $gm++) {
            $gd -= $sal[$gm];
        }
        return [$gy, $gm, $gd];
    }
}
