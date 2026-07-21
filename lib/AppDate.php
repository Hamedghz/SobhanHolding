<?php

require_once __DIR__ . '/../core/JalaliDate.php';

final class AppDate
{
    public const PERIOD_TYPES = [
        'daily' => 'روزانه',
        'weekly' => 'هفتگی',
        'monthly' => 'ماهانه',
        'quarterly' => 'فصلی',
        'half_yearly' => 'شش‌ماهه',
        'yearly' => 'سالانه',
        'custom' => 'دوره سفارشی',
    ];

    private const JALALI_MONTHS = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public static function normalize(?string $value): string
    {
        $value = trim(self::normalizeDigits((string)$value));
        $value = str_replace(["\u{200c}", "\u{200f}", "\u{202a}", "\u{202b}", "\u{202c}"], '', $value);
        $value = preg_replace('/\s*([\/.\-:])\s*/u', '$1', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    public static function toGregorian(?string $value): ?string
    {
        [$date] = self::splitDateTime($value);
        if ($date === '') return null;
        $date = str_replace('.', '/', $date);
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $date)) {
            [$year, $month, $day] = array_map('intval', explode('-', $date));
            if ($year > 1700 && checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        return JalaliDate::toGregorian(str_replace('-', '/', $date));
    }

    public static function toGregorianDateTime(?string $value, bool $withSeconds = true): ?string
    {
        [$date, $time] = self::splitDateTime($value);
        $gregorian = self::toGregorian($date);
        if ($gregorian === null) return null;
        if ($time === '') return $gregorian . ($withSeconds ? ' 00:00:00' : ' 00:00');
        $normalizedTime = self::normalizeTime($time, $withSeconds);
        return $normalizedTime === null ? null : $gregorian . ' ' . $normalizedTime;
    }

    public static function toJalali(?string $value): string
    {
        [$date] = self::splitDateTime($value);
        if ($date === '') return '';
        $gregorian = self::toGregorian($date);
        return $gregorian === null ? '' : JalaliDate::toJalali($gregorian);
    }

    public static function toJalaliDateTime(?string $value, bool $withSeconds = false): string
    {
        [$date, $time] = self::splitDateTime($value);
        $jalali = self::toJalali($date);
        if ($jalali === '') return '';
        if ($time === '') return $jalali;
        $normalizedTime = self::normalizeTime($time, $withSeconds);
        return $normalizedTime === null ? $jalali : $jalali . ' ' . $normalizedTime;
    }

    public static function isValidDate(?string $value): bool
    {
        return self::toGregorian($value) !== null;
    }

    public static function isValidDateTime(?string $value): bool
    {
        [$date, $time] = self::splitDateTime($value);
        return self::toGregorian($date) !== null && $time !== '' && self::normalizeTime($time, true) !== null;
    }

    public static function isLeapJalali(int $year): bool
    {
        return JalaliDate::monthLength($year, 12) === 30;
    }

    public static function currentJalaliDate(?DateTimeInterface $now = null): string
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
        return JalaliDate::toJalali($now->format('Y-m-d'));
    }

    public static function currentJalaliYear(?DateTimeInterface $now = null): int
    {
        return (int)explode('/', self::currentJalaliDate($now))[0];
    }

    public static function currentJalaliMonth(?DateTimeInterface $now = null): int
    {
        return (int)explode('/', self::currentJalaliDate($now))[1];
    }

    public static function formatDate(?string $value): string
    {
        return self::toJalali($value);
    }

    public static function formatDateTime(?string $value, bool $withSeconds = false): string
    {
        return self::toJalaliDateTime($value, $withSeconds);
    }

    public static function periodBounds(
        string $type,
        ?string $anchor = null,
        ?string $customFrom = null,
        ?string $customTo = null
    ): array {
        if (!isset(self::PERIOD_TYPES[$type])) {
            throw new InvalidArgumentException('نوع دوره معتبر نیست.');
        }
        if ($type === 'custom') {
            $from = self::toGregorian($customFrom);
            $to = self::toGregorian($customTo);
            if ($from === null || $to === null || $from > $to) {
                throw new InvalidArgumentException('بازه سفارشی معتبر نیست.');
            }
            return ['start_date' => $from, 'end_date' => $to];
        }

        $anchorDate = self::toGregorian($anchor ?: date('Y-m-d'));
        if ($anchorDate === null) throw new InvalidArgumentException('تاریخ مبنای دوره معتبر نیست.');
        $anchorObject = new DateTimeImmutable($anchorDate);

        if ($type === 'daily') {
            return ['start_date' => $anchorDate, 'end_date' => $anchorDate];
        }
        if ($type === 'weekly') {
            $offset = ((int)$anchorObject->format('w') + 1) % 7;
            $start = $anchorObject->modify("-{$offset} days");
            return ['start_date' => $start->format('Y-m-d'), 'end_date' => $start->modify('+6 days')->format('Y-m-d')];
        }

        [$year, $month] = array_map('intval', array_slice(explode('/', JalaliDate::toJalali($anchorDate)), 0, 2));
        if ($type === 'monthly') {
            $startMonth = $endMonth = $month;
        } elseif ($type === 'quarterly') {
            $startMonth = intdiv($month - 1, 3) * 3 + 1;
            $endMonth = $startMonth + 2;
        } elseif ($type === 'half_yearly') {
            $startMonth = $month <= 6 ? 1 : 7;
            $endMonth = $startMonth + 5;
        } else {
            $startMonth = 1;
            $endMonth = 12;
        }

        $from = JalaliDate::toGregorian(sprintf('%04d/%02d/01', $year, $startMonth));
        $to = JalaliDate::toGregorian(sprintf(
            '%04d/%02d/%02d',
            $year,
            $endMonth,
            JalaliDate::monthLength($year, $endMonth)
        ));
        if ($from === null || $to === null) throw new RuntimeException('مرز دوره قابل محاسبه نیست.');
        return ['start_date' => $from, 'end_date' => $to];
    }

    public static function defaultPeriodCatalog(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
        $today = $now->format('Y-m-d');
        $currentJalali = JalaliDate::toJalali($today);
        [$currentYear, $currentMonth] = array_map('intval', array_slice(explode('/', $currentJalali), 0, 2));
        $rows = [];

        for ($index = 0; $index < 14; $index++) {
            $date = $now->modify("-{$index} days")->format('Y-m-d');
            $jalali = JalaliDate::toJalali($date);
            $rows[] = self::periodRow(
                'daily:' . str_replace('/', '-', $jalali),
                'روز ' . $jalali,
                'daily',
                $date,
                $date,
                $date === $today,
                7000 - $index
            );
        }

        $currentWeek = self::periodBounds('weekly', $today);
        $currentWeekStart = new DateTimeImmutable($currentWeek['start_date']);
        for ($index = 0; $index < 8; $index++) {
            $start = $currentWeekStart->modify('-' . ($index * 7) . ' days');
            $end = $start->modify('+6 days');
            $startJalali = JalaliDate::toJalali($start->format('Y-m-d'));
            $endJalali = JalaliDate::toJalali($end->format('Y-m-d'));
            $rows[] = self::periodRow(
                'weekly:' . str_replace('/', '-', $startJalali),
                'هفته ' . $startJalali . ' تا ' . $endJalali,
                'weekly',
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $index === 0,
                6000 - $index
            );
        }

        for ($offset = 0; $offset < 13; $offset++) {
            [$year, $month] = self::shiftJalaliMonth($currentYear, $currentMonth, -$offset);
            $from = JalaliDate::toGregorian(sprintf('%04d/%02d/01', $year, $month));
            $to = JalaliDate::toGregorian(sprintf('%04d/%02d/%02d', $year, $month, JalaliDate::monthLength($year, $month)));
            $rows[] = self::periodRow(
                sprintf('monthly:%04d-%02d', $year, $month),
                (self::JALALI_MONTHS[$month] ?? 'ماه') . ' ' . $year,
                'monthly',
                (string)$from,
                (string)$to,
                $offset === 0,
                5000 - $offset,
                $year,
                $month
            );
        }

        $quarterStart = intdiv($currentMonth - 1, 3) * 3 + 1;
        for ($offset = 0; $offset < 8; $offset++) {
            [$year, $month] = self::shiftJalaliMonth($currentYear, $quarterStart, -($offset * 3));
            $quarter = intdiv($month - 1, 3) + 1;
            $endMonth = $month + 2;
            $from = JalaliDate::toGregorian(sprintf('%04d/%02d/01', $year, $month));
            $to = JalaliDate::toGregorian(sprintf('%04d/%02d/%02d', $year, $endMonth, JalaliDate::monthLength($year, $endMonth)));
            $rows[] = self::periodRow(
                sprintf('quarterly:%04d-Q%d', $year, $quarter),
                'فصل ' . $quarter . ' سال ' . $year,
                'quarterly',
                (string)$from,
                (string)$to,
                $offset === 0,
                4000 - $offset,
                $year
            );
        }

        $halfStart = $currentMonth <= 6 ? 1 : 7;
        for ($offset = 0; $offset < 4; $offset++) {
            [$year, $month] = self::shiftJalaliMonth($currentYear, $halfStart, -($offset * 6));
            $half = $month <= 6 ? 1 : 2;
            $endMonth = $half === 1 ? 6 : 12;
            $from = JalaliDate::toGregorian(sprintf('%04d/%02d/01', $year, $month));
            $to = JalaliDate::toGregorian(sprintf('%04d/%02d/%02d', $year, $endMonth, JalaliDate::monthLength($year, $endMonth)));
            $rows[] = self::periodRow(
                sprintf('half_yearly:%04d-H%d', $year, $half),
                'نیمه ' . ($half === 1 ? 'اول' : 'دوم') . ' سال ' . $year,
                'half_yearly',
                (string)$from,
                (string)$to,
                $offset === 0,
                3000 - $offset,
                $year
            );
        }

        for ($offset = 0; $offset < 5; $offset++) {
            $year = $currentYear - $offset;
            $from = JalaliDate::toGregorian(sprintf('%04d/01/01', $year));
            $to = JalaliDate::toGregorian(sprintf('%04d/12/%02d', $year, JalaliDate::monthLength($year, 12)));
            $rows[] = self::periodRow(
                'yearly:' . $year,
                'سال ' . $year,
                'yearly',
                (string)$from,
                (string)$to,
                $offset === 0,
                2000 - $offset,
                $year
            );
        }

        $rows[] = [
            'period_key' => 'custom',
            'title' => 'دوره سفارشی',
            'period_type' => 'custom',
            'start_date' => null,
            'end_date' => null,
            'jalali_year' => null,
            'jalali_month' => null,
            'scope_key' => 'global',
            'is_current' => 0,
            'is_system' => 1,
            'is_active' => 1,
            'sort_order' => 1000,
        ];

        return $rows;
    }

    public static function periods(array $types = [], string $scope = 'global'): array
    {
        require_once __DIR__ . '/../core/Database.php';
        $where = ['is_active=1', '(scope_key=? OR scope_key="global")'];
        $params = [$scope];
        $types = array_values(array_intersect(array_keys(self::PERIOD_TYPES), $types));
        if ($types) {
            $where[] = 'period_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            array_push($params, ...$types);
        }
        return Database::fetchAll(
            'SELECT * FROM system_periods WHERE ' . implode(' AND ', $where) .
            ' ORDER BY is_current DESC,sort_order DESC,start_date DESC,id DESC',
            $params
        );
    }

    public static function period(string $periodKey): ?array
    {
        require_once __DIR__ . '/../core/Database.php';
        return Database::fetch('SELECT * FROM system_periods WHERE period_key=? AND is_active=1 LIMIT 1', [$periodKey]);
    }

    public static function resolvePeriod(
        ?string $periodKey,
        ?string $customFrom = null,
        ?string $customTo = null,
        string $fallbackType = 'monthly'
    ): array {
        $periodKey = trim((string)$periodKey);
        if ($periodKey === 'custom') {
            return array_merge(
                ['period_key' => 'custom', 'title' => self::PERIOD_TYPES['custom'], 'period_type' => 'custom'],
                self::periodBounds('custom', null, $customFrom, $customTo)
            );
        }
        if ($periodKey !== '') {
            $period = self::period($periodKey);
            if ($period && $period['start_date'] && $period['end_date']) return $period;
        }
        $fallbackType = isset(self::PERIOD_TYPES[$fallbackType]) && $fallbackType !== 'custom' ? $fallbackType : 'monthly';
        $bounds = self::periodBounds($fallbackType);
        return [
            'period_key' => '',
            'title' => self::PERIOD_TYPES[$fallbackType] . ' جاری',
            'period_type' => $fallbackType,
            'start_date' => $bounds['start_date'],
            'end_date' => $bounds['end_date'],
        ];
    }

    private static function splitDateTime(?string $value): array
    {
        $value = self::normalize($value);
        if ($value === '') return ['', ''];
        $parts = preg_split('/[T ]/u', $value, 2) ?: [];
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private static function normalizeTime(string $value, bool $withSeconds): ?string
    {
        $value = trim(self::normalizeDigits($value));
        if (!preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $match)) return null;
        $hour = (int)$match[1];
        $minute = (int)$match[2];
        $second = isset($match[3]) ? (int)$match[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) return null;
        return $withSeconds
            ? sprintf('%02d:%02d:%02d', $hour, $minute, $second)
            : sprintf('%02d:%02d', $hour, $minute);
    }

    private static function shiftJalaliMonth(int $year, int $month, int $offset): array
    {
        $index = ($year * 12) + ($month - 1) + $offset;
        $newYear = intdiv($index, 12);
        $newMonth = ($index % 12) + 1;
        if ($newMonth <= 0) {
            $newMonth += 12;
            $newYear--;
        }
        return [$newYear, $newMonth];
    }

    private static function periodRow(
        string $key,
        string $title,
        string $type,
        string $start,
        string $end,
        bool $current,
        int $sort,
        ?int $year = null,
        ?int $month = null
    ): array {
        if ($year === null || $month === null) {
            $jalali = JalaliDate::toJalali($start);
            [$resolvedYear, $resolvedMonth] = array_map('intval', array_slice(explode('/', $jalali), 0, 2));
            $year ??= $resolvedYear;
            $month ??= $resolvedMonth;
        }
        return [
            'period_key' => $key,
            'title' => $title,
            'period_type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'jalali_year' => $year,
            'jalali_month' => $month,
            'scope_key' => 'global',
            'is_current' => $current ? 1 : 0,
            'is_system' => 1,
            'is_active' => 1,
            'sort_order' => $sort,
        ];
    }
}
