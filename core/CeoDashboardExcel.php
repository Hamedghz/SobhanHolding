<?php

class CeoDashboardExcel
{
    public static function output(array $sheets, string $fileName): void
    {
        $path = self::write($sheets);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }

    public static function write(array $sheets): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('برای ورود و خروجی اکسل، افزونه ZipArchive یا کتابخانه PhpSpreadsheet نصب نیست.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ceo-xlsx-');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('امکان ساخت فایل اکسل وجود ندارد.');
        }

        $sheetSpecs = [];
        $tableCount = 0;
        foreach ($sheets as $name => $value) {
            $rows = isset($value['rows']) && is_array($value['rows']) ? $value['rows'] : $value;
            $tableName = isset($value['rows']) ? trim((string)($value['table_name'] ?? '')) : '';
            $sheetSpecs[$name] = ['rows'=>$rows, 'table_name'=>$tableName, 'table_id'=>$tableName !== '' ? ++$tableCount : null];
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheetSpecs), $tableCount));
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbookXml(array_keys($sheetSpecs)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels(count($sheetSpecs)));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        $index = 1;
        foreach ($sheetSpecs as $spec) {
            $tableId = $spec['table_id'];
            $zip->addFromString("xl/worksheets/sheet{$index}.xml", self::sheetXml($spec['rows'], $tableId));
            if ($tableId !== null) {
                $lastColumn = self::columnName(max(0, count($spec['rows'][0] ?? []) - 1));
                $lastRow = max(1, count($spec['rows']));
                $range = 'A1:' . $lastColumn . $lastRow;
                $zip->addFromString("xl/worksheets/_rels/sheet{$index}.xml.rels", self::sheetRels($tableId));
                $zip->addFromString("xl/tables/table{$tableId}.xml", self::tableXml($tableId, $spec['table_name'], $range, $spec['rows'][0] ?? []));
            }
            $index++;
        }
        $zip->close();
        return $path;
    }

    public static function read(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('برای ورود و خروجی اکسل، افزونه ZipArchive یا کتابخانه PhpSpreadsheet نصب نیست.');
        }
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException('برای خواندن فایل اکسل، افزونه SimpleXML نصب نیست.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل اکسل قابل خواندن نیست.');
        }

        $sharedStrings = self::readSharedStrings($zip);
        $workbook = simplexml_load_string((string)$zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string)$zip->getFromName('xl/_rels/workbook.xml.rels'));
        if (!$workbook || !$rels) {
            $zip->close();
            throw new RuntimeException('ساختار فایل اکسل معتبر نیست.');
        }

        $relMap = [];
        foreach ($rels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $rel) {
            $attrs = $rel->attributes();
            $relMap[(string)$attrs['Id']] = (string)$attrs['Target'];
        }

        $result = [];
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $workbookMain = $workbook->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($workbookMain->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes();
            $relAttrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $name = (string)$attrs['name'];
            $target = $relMap[(string)$relAttrs['id']] ?? '';
            if ($target === '') continue;
            $sheetPath = substr($target, 0, 1) === '/' ? ltrim($target, '/') : 'xl/' . ltrim($target, '/');
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) continue;
            $result[$name] = self::parseSheet($xml, $sharedStrings);
        }

        $zip->close();
        return $result;
    }

    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $doc = simplexml_load_string($xml);
        if (!$doc) return [];
        $strings = [];
        $main = $doc->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($main->si as $si) {
            $siMain = $si->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $text = '';
            if (isset($siMain->t)) {
                $text = (string)$siMain->t;
            } elseif (isset($siMain->r)) {
                foreach ($siMain->r as $run) {
                    $runMain = $run->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $text .= (string)$runMain->t;
                }
            }
            $strings[] = $text;
        }
        return $strings;
    }

    private static function parseSheet(string $xml, array $sharedStrings): array
    {
        $doc = simplexml_load_string($xml);
        if (!$doc) return [];
        $rows = [];
        $main = $doc->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($main->sheetData->row as $row) {
            $values = [];
            foreach ($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $cell) {
                $attrs = $cell->attributes();
                $cellMain = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $ref = (string)$attrs['r'];
                $columnIndex = self::columnIndex(preg_replace('/\d+/', '', $ref));
                $type = (string)($attrs['t'] ?? '');
                $value = '';
                if ($type === 'inlineStr') {
                    $value = (string)$cellMain->is->t;
                } elseif ($type === 's') {
                    $value = $sharedStrings[(int)$cellMain->v] ?? '';
                } else {
                    $value = (string)$cellMain->v;
                }
                $values[$columnIndex] = $value;
            }
            if ($values) {
                $max = max(array_keys($values));
                $ordered = [];
                for ($i = 0; $i <= $max; $i++) {
                    $ordered[] = $values[$i] ?? '';
                }
                $rows[] = $ordered;
            }
        }
        return $rows;
    }

    private static function sheetXml(array $rows, ?int $tableId = null): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="20"/><sheetData>';
        foreach (array_values($rows) as $rowIndex => $row) {
            $r = $rowIndex + 1;
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($row) as $colIndex => $value) {
                $cell = self::columnName($colIndex) . $r;
                $value = (string)$value;
                if ($value !== '' && is_numeric($value) && !preg_match('/^0\d+$/', $value)) {
                    $xml .= '<c r="' . $cell . '"' . ($r === 1 ? ' s="1"' : '') . '><v>' . self::xml($value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cell . '" t="inlineStr"' . ($r === 1 ? ' s="1"' : '') . '><is><t>' . self::xml($value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($tableId !== null) $xml .= '<tableParts count="1"><tablePart r:id="rId1"/></tableParts>';
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function contentTypes(int $sheetCount, int $tableCount = 0): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
        $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        for ($i = 1; $i <= $tableCount; $i++) {
            $xml .= '<Override PartName="/xl/tables/table' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/>';
        }
        $xml .= '</Types>';
        return $xml;
    }

    private static function sheetRels(int $tableId): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table' . $tableId . '.xml"/></Relationships>';
    }

    private static function tableXml(int $tableId, string $tableName, string $range, array $headers): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $tableName)) throw new InvalidArgumentException('نام جدول Excel معتبر نیست.');
        $columns = '';
        foreach (array_values($headers) as $index => $header) {
            $columns .= '<tableColumn id="' . ($index + 1) . '" name="' . self::xml((string)$header) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="' . $tableId . '" name="' . self::xml($tableName) . '" displayName="' . self::xml($tableName) . '" ref="' . $range . '" totalsRowShown="0">'
            . '<autoFilter ref="' . $range . '"/><tableColumns count="' . count($headers) . '">' . $columns . '</tableColumns>'
            . '<tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/></table>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private static function workbookXml(array $sheetNames): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach (array_values($sheetNames) as $index => $name) {
            $sheetId = $index + 1;
            $xml .= '<sheet name="' . self::xml($name) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private static function workbookRels(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Tahoma"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs></styleSheet>';
    }

    private static function columnName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - $mod, 26);
        }
        return $name;
    }

    private static function columnIndex(string $name): int
    {
        $index = 0;
        foreach (str_split(strtoupper($name)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }
        return max(0, $index - 1);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
