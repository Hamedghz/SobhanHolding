<?php

if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}

require_once __DIR__.'/../services/UnifiedImportService.php';

$clock=UnifiedImportService::normalizeAttendanceClock('09:37');
if($clock['time']!=='09:37:00'||$clock['note']!==null)throw new RuntimeException('HH:MM normalization failed.');
$clock=UnifiedImportService::normalizeAttendanceClock('07:30:00');
if($clock['time']!=='07:30:00')throw new RuntimeException('HH:MM:SS normalization failed.');
$clock=UnifiedImportService::normalizeAttendanceClock(0.5);
if($clock['time']!=='12:00:00')throw new RuntimeException('Excel clock fraction normalization failed.');

$duration=UnifiedImportService::normalizeAttendanceDuration('07:30');
if($duration['minutes']!==450)throw new RuntimeException('Text duration normalization failed.');
$duration=UnifiedImportService::normalizeAttendanceDuration(7.5);
if($duration['minutes']!==450)throw new RuntimeException('Numeric work-hour normalization failed.');
$duration=UnifiedImportService::normalizeAttendanceDuration(0.3125);
if($duration['minutes']!==450)throw new RuntimeException('Excel duration fraction normalization failed.');
$duration=UnifiedImportService::normalizeAttendanceDuration('30','minutes');
if($duration['minutes']!==30)throw new RuntimeException('Numeric minute normalization failed.');
$duration=UnifiedImportService::normalizeAttendanceDuration('پاس');
if($duration['minutes']!==0||$duration['note']!=='پاس')throw new RuntimeException('Nonnumeric attendance note was not preserved.');

$invalidClock=false;
try{UnifiedImportService::normalizeAttendanceClock('25:10');}catch(InvalidArgumentException){$invalidClock=true;}
if(!$invalidClock)throw new RuntimeException('Impossible clock time was accepted.');
$invalidDuration=false;
try{UnifiedImportService::normalizeAttendanceDuration('24:30');}catch(InvalidArgumentException){$invalidDuration=true;}
if(!$invalidDuration)throw new RuntimeException('Impossible duration was accepted.');

echo "HR attendance import normalization: PASS\n";
