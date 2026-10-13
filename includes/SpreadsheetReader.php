<?php
// includes/SpreadsheetReader.php
// Lightweight zero-dependency spreadsheet parser for CSV, XLSX, and XLS in PHP 8+

class SpreadsheetReader {

    /**
     * Parse uploaded file into an array of rows (2D array)
     * @param string $filePath
     * @param string $extension ('csv', 'xlsx', 'xls')
     * @return array
     */
    public static function parseFile(string $filePath, string $extension): array {
        $ext = strtolower($extension);
        if ($ext === 'csv') {
            return self::parseCsv($filePath);
        } elseif ($ext === 'xlsx') {
            return self::parseXlsx($filePath);
        } elseif ($ext === 'xls') {
            return self::parseXls($filePath);
        } else {
            throw new Exception("Format berkas .$ext tidak didukung. Harap gunakan format .xlsx, .xls, atau .csv.");
        }
    }

    /**
     * Parse CSV with auto-detection of delimiter and BOM removal
     */
    public static function parseCsv(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new Exception("Berkas CSV tidak ditemukan: " . basename($filePath));
        }

        $content = file_get_contents($filePath);
        // Remove UTF-8 BOM if present
        $bom = pack('H*','EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Detect delimiter: check first line
        $firstLine = strtok($content, "\r\n");
        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');
        $tabCount = substr_count($firstLine, "\t");

        $delimiter = ',';
        if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
            $delimiter = ';';
        } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
            $delimiter = "\t";
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $data = str_getcsv($line, $delimiter);
            // Trim all cell values
            $cleanData = array_map(function($val) {
                return trim($val ?? '');
            }, $data);
            $rows[] = $cleanData;
        }

        return $rows;
    }

    /**
     * Parse XLSX using native PHP ZipArchive and SimpleXML
     */
    public static function parseXlsx(string $filePath): array {
        if (!class_exists('ZipArchive')) {
            // Fallback: try CSV parsing if ZipArchive is missing
            return self::parseCsv($filePath);
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Tidak dapat membuka file XLSX. File mungkin rusak atau terproteksi.");
        }

        // 1. Read shared strings if exists
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xml = @simplexml_load_string($sharedStringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Locate first sheet
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            // Try looking for any sheet
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('#xl/worksheets/sheet\d+\.xml#i', $stat['name'])) {
                    $sheetXmlContent = $zip->getFromName($stat['name']);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXmlContent === false) {
            throw new Exception("Lembar kerja (sheet) tidak ditemukan di dalam berkas Excel.");
        }

        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (!$sheetXml || !isset($sheetXml->sheetData)) {
            throw new Exception("Struktur lembar kerja Excel tidak valid.");
        }

        $rows = [];
        foreach ($sheetXml->sheetData->row as $rowNode) {
            $rowCells = [];
            $currentCol = 0;

            foreach ($rowNode->c as $cell) {
                $cellRef = (string)$cell['r']; // e.g. "A1", "B2"
                $colIndex = self::columnLetterToIndex($cellRef);

                // Fill gaps with empty string
                while ($currentCol < $colIndex) {
                    $rowCells[] = '';
                    $currentCol++;
                }

                $cellType = (string)$cell['t'];
                $val = '';

                if (isset($cell->v)) {
                    $rawVal = (string)$cell->v;
                    if ($cellType === 's') { // shared string
                        $val = $sharedStrings[(int)$rawVal] ?? '';
                    } elseif ($cellType === 'b') { // boolean
                        $val = ($rawVal === '1') ? 'TRUE' : 'FALSE';
                    } else {
                        $val = $rawVal;
                    }
                } elseif (isset($cell->is->t)) { // inline string
                    $val = (string)$cell->is->t;
                }

                $rowCells[] = trim($val);
                $currentCol++;
            }

            // Skip entirely empty rows
            $hasContent = false;
            foreach ($rowCells as $c) {
                if ($c !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if ($hasContent) {
                $rows[] = $rowCells;
            }
        }

        return $rows;
    }

    /**
     * Parse XLS (HTML table or CSV fallback)
     */
    public static function parseXls(string $filePath): array {
        $content = file_get_contents($filePath);

        // Check if file is actually an HTML table
        if (stripos($content, '<table') !== false) {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
            $rows = [];
            $table = $dom->getElementsByTagName('table')->item(0);
            if ($table) {
                $trList = $table->getElementsByTagName('tr');
                foreach ($trList as $tr) {
                    $rowCells = [];
                    $tdList = $tr->getElementsByTagName('td');
                    if ($tdList->length === 0) {
                        $tdList = $tr->getElementsByTagName('th');
                    }
                    foreach ($tdList as $td) {
                        $rowCells[] = trim($td->textContent);
                    }
                    if (!empty($rowCells)) {
                        $rows[] = $rowCells;
                    }
                }
            }
            if (!empty($rows)) {
                return $rows;
            }
        }

        // Fallback to CSV parsing
        return self::parseCsv($filePath);
    }

    /**
     * Convert cell coordinate letter (e.g. 'A', 'B', 'AA') to 0-based index
     */
    private static function columnLetterToIndex(string $cellRef): int {
        // Strip out the numbers
        $letters = preg_replace('/[0-9]/', '', $cellRef);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord(strtoupper($letters[$i])) - ord('A') + 1);
        }
        return max(0, $index - 1);
    }
}
