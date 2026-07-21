<?php

function createXlsxFixture(
    string $path,
    string $sheetName,
    string $tableName,
    array $headers,
    array $rows,
    array $formulaCells = []
): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive is required for XLSX fixtures.');

    $xml = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $columnName = static function (int $index): string {
        $name = '';
        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $name = chr(65 + (($number - 1) % 26)) . $name;
        }
        return $name;
    };
    $cell = static function (string $reference, mixed $value, ?string $formula = null) use ($xml): string {
        if ($formula !== null) {
            return '<c r="' . $reference . '"><f>' . $xml($formula) . '</f><v>' . $xml($value) . '</v></c>';
        }
        return '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
            . $xml($value) . '</t></is></c>';
    };

    $sheetRows = [];
    $allRows = array_merge([$headers], $rows);
    foreach ($allRows as $rowIndex => $row) {
        $rowNumber = $rowIndex + 1;
        $cells = [];
        foreach ($row as $columnIndex => $value) {
            $reference = $columnName($columnIndex) . $rowNumber;
            $cells[] = $cell($reference, $value, $formulaCells[$reference] ?? null);
        }
        $sheetRows[] = '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
    }

    $lastColumn = $columnName(max(0, count($headers) - 1));
    $lastRow = max(1, count($allRows));
    $range = 'A1:' . $lastColumn . $lastRow;
    $tableColumns = [];
    foreach (array_values($headers) as $index => $header) {
        $tableColumns[] = '<tableColumn id="' . ($index + 1) . '" name="' . $xml($header) . '"/>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/tables/table1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="' . $range . '"/><sheetData>' . implode('', $sheetRows) . '</sheetData>'
            . '<tableParts count="1"><tablePart r:id="rId1"/></tableParts></worksheet>',
        'xl/worksheets/_rels/sheet1.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/>'
            . '</Relationships>',
        'xl/tables/table1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" '
            . 'name="' . $xml($tableName) . '" displayName="' . $xml($tableName) . '" ref="' . $range . '" totalsRowShown="0">'
            . '<autoFilter ref="' . $range . '"/><tableColumns count="' . count($headers) . '">'
            . implode('', $tableColumns) . '</tableColumns>'
            . '<tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/>'
            . '</table>',
    ];

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create XLSX fixture.');
    }
    foreach ($files as $name => $contents) $zip->addFromString($name, $contents);
    $zip->close();

    return $path;
}

