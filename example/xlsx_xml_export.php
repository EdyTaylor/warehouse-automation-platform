<?php
declare(strict_types=1);

$baseDir = __DIR__;
$xlsxFiles = glob($baseDir . DIRECTORY_SEPARATOR . '*.xlsx');
if (!$xlsxFiles) {
    fwrite(STDERR, "No xlsx file found\n");
    exit(1);
}
usort($xlsxFiles, static function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});
$xlsxPath = $xlsxFiles[0];

$outDir = $baseDir . DIRECTORY_SEPARATOR . 'excel_export';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    fwrite(STDERR, "Cannot open xlsx: {$xlsxPath}\n");
    exit(1);
}

$sharedStrings = [];
$siXml = $zip->getFromName('xl/sharedStrings.xml');
if ($siXml !== false) {
    $sx = simplexml_load_string($siXml);
    if ($sx && isset($sx->si)) {
        foreach ($sx->si as $si) {
            // supports simple <t> and rich text <r><t>
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
                continue;
            }
            $parts = [];
            if (isset($si->r)) {
                foreach ($si->r as $r) {
                    $parts[] = (string)$r->t;
                }
            }
            $sharedStrings[] = implode('', $parts);
        }
    }
}

$workbookXml = $zip->getFromName('xl/workbook.xml');
$relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
if ($workbookXml === false || $relsXml === false) {
    fwrite(STDERR, "Workbook metadata missing\n");
    exit(1);
}

$workbook = simplexml_load_string($workbookXml);
$rels = simplexml_load_string($relsXml);
$relNs = $rels->getNamespaces(true);
$relMap = [];
foreach ($rels->Relationship as $rel) {
    $id = (string)$rel['Id'];
    $target = (string)$rel['Target'];
    $relMap[$id] = 'xl/' . ltrim($target, '/');
}

$wbNs = $workbook->getNamespaces(true);
$sheets = $workbook->sheets->sheet;

foreach ($sheets as $sheet) {
    $name = (string)$sheet['name'];
    $rid = (string)$sheet->attributes($wbNs['r'])['id'];
    if (!isset($relMap[$rid])) {
        continue;
    }
    $sheetPath = $relMap[$rid];
    $sheetXml = $zip->getFromName($sheetPath);
    if ($sheetXml === false) {
        continue;
    }
    $sx = simplexml_load_string($sheetXml);
    if (!$sx) {
        continue;
    }

    $rows = [];
    $maxCol = 0;
    if (isset($sx->sheetData->row)) {
        foreach ($sx->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r']; // e.g. C12
                preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                $colLetters = isset($m[1]) ? $m[1] : 'A';
                $colIndex = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - 64);
                }
                $type = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : '';
                if ($type === 's') {
                    $idx = (int)$v;
                    $val = isset($sharedStrings[$idx]) ? $sharedStrings[$idx] : '';
                } else {
                    $val = $v;
                }
                $rowData[$colIndex] = $val;
                if ($colIndex > $maxCol) {
                    $maxCol = $colIndex;
                }
            }
            $rows[] = $rowData;
        }
    }

    $safe = preg_replace('/[\\\\\\/:*?"<>|]/', '_', $name);
    $csvPath = $outDir . DIRECTORY_SEPARATOR . $safe . '.csv';
    $fh = fopen($csvPath, 'wb');
    foreach ($rows as $r) {
        $line = [];
        for ($i = 1; $i <= $maxCol; $i++) {
            $line[] = isset($r[$i]) ? $r[$i] : '';
        }
        fputcsv($fh, $line);
    }
    fclose($fh);
    echo "EXPORTED|{$name}|{$csvPath}\n";
}

$zip->close();

