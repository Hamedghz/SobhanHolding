<?php

$root = dirname(__DIR__);
require_once $root . '/core/SpreadsheetImportReader.php';
require_once $root . '/tests/support/xlsx_fixture.php';

if (SpreadsheetImportReader::MAX_UNCOMPRESSED_SIZE < 256 * 1024 * 1024) {
    throw new RuntimeException('The XLSX structure limit is too small for wide ERP exports.');
}

$path = tempnam(sys_get_temp_dir(), 'xlsx-stream-') . '.xlsx';
$rows = [];
for ($index = 1; $index <= 650; $index++) {
    $rows[] = [(string)$index, 'P-' . $index, 'ردیف ' . $index];
}

try {
    createXlsxFixture($path, 'داده بزرگ', 'tblstream', ['ردیف','کد کالا','عنوان'], $rows);
    $workbook = SpreadsheetImportReader::read($path, 'xlsx');
    $sheet = $workbook['sheets'][0] ?? [];
    if (($sheet['populated_rows'] ?? 0) !== 651) throw new RuntimeException('Populated worksheet row count is incorrect.');
    if (count($sheet['rows'] ?? []) !== SpreadsheetImportReader::SAMPLE_ROWS) throw new RuntimeException('Discovery sample is not bounded.');

    $count = 0;
    $last = null;
    foreach (SpreadsheetImportReader::candidateRows(['rows'=>$sheet['rows'],'stream'=>$sheet['stream'],'ref'=>null]) as $item) {
        $count++;
        $last = $item;
    }
    if ($count !== 651 || ($last['values'][1] ?? '') !== 'P-650') throw new RuntimeException('Streamed staging rows were truncated after the discovery sample.');
} finally {
    @unlink($path);
}

echo "Spreadsheet import reader streaming: PASS\n";
