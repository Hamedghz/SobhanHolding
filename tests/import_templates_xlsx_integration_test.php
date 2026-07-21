<?php

$root = dirname(__DIR__);
require_once $root . '/lib/ImportTemplateService.php';
require_once $root . '/core/CeoDashboardExcel.php';

$sources = array_keys(ImportSourceRegistry::all());
$expected = ImportTemplateService::workbook($sources);
$path = CeoDashboardExcel::write($expected);
try {
    $actual = CeoDashboardExcel::read($path);
} finally {
    @unlink($path);
}

if (count($actual) !== count($expected)) {
    throw new RuntimeException('Generated import template sheet count is incorrect.');
}
if (!isset($actual['راهنما']) || count($actual['راهنما']) < count($sources) + 1) {
    throw new RuntimeException('Generated import template guide is missing or incomplete.');
}
foreach ($expected as $sheet => $rows) {
    if (!isset($actual[$sheet])) throw new RuntimeException('Generated import template sheet is missing: '.$sheet);
    if (($actual[$sheet][0] ?? []) !== ($rows[0] ?? [])) {
        throw new RuntimeException('Generated import template headers changed after XLSX round trip: '.$sheet);
    }
}

echo "Import templates XLSX integration: PASS\n";

