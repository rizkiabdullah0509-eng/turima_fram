<?php

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use RuntimeException;

class TaskImportService
{
    /** Kolom pada template impor tugas harian. */
    private const HEADINGS = ['Username Karyawan', 'Tanggal', 'Urutan', 'Tugas'];

    public static function sampleRows(): array
    {
        $today = date('Y-m-d');

        return [
            ['andi', $today, '1', 'Membuka toko'],
            ['andi', $today, '2', 'Membersihkan area depan'],
            ['andi', $today, '3', 'Mengecek stok'],
            ['budi', $today, '1', 'Membersihkan gudang'],
            ['budi', $today, '2', 'Mengecek barang masuk'],
            ['budi', $today, '3', 'Membuat laporan'],
            ['cici', $today, '1', 'Menyiapkan meja'],
            ['cici', $today, '2', 'Melayani pelanggan'],
        ];
    }

    /** Buat workbook template .xlsx yang bisa langsung diisi oleh manajer. */
    public static function template(): string
    {
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Impor Tugas" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => self::templateSheetXml(),
        ];

        return self::zip($files);
    }

    /**
     * Membaca baris dari .xlsx tanpa paket eksternal. Template dapat diedit
     * di Excel/WPS karena pembaca mendukung inline strings dan shared strings.
     */
    public static function read(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('File Excel tidak dapat dibaca.');
        }

        $entries = self::unzip($contents);
        $sheet = $entries['xl/worksheets/sheet1.xml'] ?? null;
        if (! $sheet) {
            foreach ($entries as $name => $entry) {
                if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                    $sheet = $entry;
                    break;
                }
            }
        }

        if (! $sheet) {
            throw new RuntimeException('Lembar kerja Excel tidak ditemukan. Gunakan template yang tersedia.');
        }

        return self::extractRows($sheet, self::sharedStrings($entries['xl/sharedStrings.xml'] ?? null));
    }

    private static function extractRows(string $sheetXml, array $sharedStrings): array
    {
        $sheet = self::xmlDocument($sheetXml, 'Struktur lembar kerja Excel tidak valid.');
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = $sheet->xpath('//x:sheetData/x:row') ?: [];
        $headerMap = null;
        $result = [];

        foreach ($rows as $row) {
            $row->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rowNumber = (int) ($row['r'] ?? 0);
            $values = [];
            foreach ($row->xpath('./x:c') ?: [] as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $column = preg_replace('/\d+/', '', $reference) ?: '';
                if ($column !== '') {
                    $values[$column] = self::cellValue($cell, $sharedStrings);
                }
            }

            if ($headerMap === null) {
                $candidate = self::headerMap($values);
                if ($candidate !== null) {
                    $headerMap = $candidate;
                }
                continue;
            }

            $username = self::text($values[$headerMap['username']] ?? '');
            $date = self::normaliseDate($values[$headerMap['date']] ?? '');
            $orderRaw = isset($headerMap['order']) ? self::text($values[$headerMap['order']] ?? '') : '';
            $title = self::text($values[$headerMap['title']] ?? '');
            if ($username === '' && $date === '' && $title === '') {
                continue;
            }

            $result[] = [
                'row' => $rowNumber,
                'username' => $username,
                'date' => $date,
                'order' => $orderRaw !== '' ? $orderRaw : null,
                'title' => $title,
            ];
        }

        if ($headerMap === null) {
            throw new RuntimeException('Kolom template tidak ditemukan. Gunakan kolom: Username Karyawan, Tanggal, Urutan, dan Tugas.');
        }

        return $result;
    }

    private static function headerMap(array $values): ?array
    {
        $aliases = [
            'username' => ['username karyawan', 'username', 'login', 'karyawan', 'user'],
            'date' => ['tanggal', 'tanggal tugas', 'date', 'tgl'],
            'order' => ['urutan', 'urutan tugas', 'urutan pengerjaan', 'no', 'nomor', 'order', 'sort_order'],
            'title' => ['tugas', 'nama tugas', 'judul tugas', 'task', 'pekerjaan'],
        ];
        $map = [];

        foreach ($values as $column => $value) {
            $heading = self::heading((string) $value);
            foreach ($aliases as $key => $possible) {
                if (in_array($heading, $possible, true)) {
                    $map[$key] = $column;
                }
            }
        }

        return isset($map['username'], $map['date'], $map['title']) ? $map : null;
    }

    private static function sharedStrings(?string $xml): array
    {
        if (! $xml) {
            return [];
        }

        $document = self::xmlDocument($xml, 'Daftar teks Excel tidak valid.');
        $document->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($document->xpath('//x:si') ?: [] as $item) {
            $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = $item->xpath('.//x:t') ?: [];
            $strings[] = implode('', array_map(fn ($part) => (string) $part, $parts));
        }

        return $strings;
    }

    private static function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $type = (string) ($cell['t'] ?? '');
        if ($type === 'inlineStr') {
            $parts = $cell->xpath('./x:is//x:t') ?: [];

            return implode('', array_map(fn ($part) => (string) $part, $parts));
        }

        $values = $cell->xpath('./x:v') ?: [];
        $value = isset($values[0]) ? (string) $values[0] : '';

        return $type === 's' ? ($sharedStrings[(int) $value] ?? '') : $value;
    }

    private static function normaliseDate(string $value): string
    {
        $value = self::text($value);
        if ($value !== '' && is_numeric($value) && (float) $value > 0) {
            $days = (int) floor((float) $value);

            return (new DateTimeImmutable('1899-12-30'))
                ->add(new DateInterval('P'.$days.'D'))
                ->format('Y-m-d');
        }

        return $value;
    }

    private static function cellValueForTemplate(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t>'
            .self::xml($value).'</t></is></c>';
    }

    private static function templateSheetXml(): string
    {
        $sampleRows = self::sampleRows();

        $headers = '';
        foreach (self::HEADINGS as $index => $heading) {
            $headers .= self::cellValueForTemplate(chr(65 + $index).'2', $heading, 1);
        }

        $lastRow = 2 + count($sampleRows);

        $rowsXml = '';
        $rowsXml .= '<row r="1" ht="26" customHeight="1">'.self::cellValueForTemplate('A1', 'Template Impor Tugas Harian', 2).'</row>';
        $rowsXml .= '<row r="2" ht="22" customHeight="1">'.$headers.'</row>';

        foreach ($sampleRows as $index => $row) {
            $rowNum = $index + 3;
            $cells = '';
            foreach ($row as $colIdx => $val) {
                $cells .= self::cellValueForTemplate(chr(65 + $colIdx).$rowNum, (string) $val, 3);
            }
            $rowsXml .= '<row r="'.$rowNum.'" ht="20" customHeight="1">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:D'.$lastRow.'"/><sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="24" customWidth="1"/>'
            .'<col min="2" max="2" width="16" customWidth="1"/>'
            .'<col min="3" max="3" width="12" customWidth="1"/>'
            .'<col min="4" max="4" width="36" customWidth="1"/></cols><sheetData>'
            .$rowsXml
            .'</sheetData><autoFilter ref="A2:D'.$lastRow.'"/><mergeCells count="1"><mergeCell ref="A1:D1"/></mergeCells></worksheet>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>'
            .'<font><b/><sz val="14"/><name val="Arial"/><color rgb="FF125B3A"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF125B3A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD5E8DD"/></left><right style="thin"><color rgb="FFD5E8DD"/></right>'
            .'<top style="thin"><color rgb="FFD5E8DD"/></top><bottom style="thin"><color rgb="FFD5E8DD"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs>'
            .'</styleSheet>';
    }

    private static function xmlDocument(string $xml, string $message): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new RuntimeException($message);
        }

        return $document;
    }

    private static function text(string $value): string
    {
        return trim(str_replace("\xC2\xA0", ' ', $value));
    }

    private static function heading(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', self::text($value)) ?? '');
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Read only the unencrypted parts of a small .xlsx (ZIP) file. */
    private static function unzip(string $archive): array
    {
        $end = strrpos($archive, "PK\x05\x06");
        if ($end === false || strlen($archive) < $end + 22) {
            throw new RuntimeException('File yang diunggah bukan file Excel .xlsx yang valid.');
        }

        $footer = unpack('Vsignature/vdisk/vcentralDisk/ventriesDisk/ventries/Vsize/Voffset/vcommentLength', substr($archive, $end, 22));
        $entries = [];
        $offset = $footer['offset'];

        for ($index = 0; $index < $footer['entries']; $index++) {
            if (substr($archive, $offset, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Struktur file Excel tidak dapat dibaca.');
            }

            $header = unpack(
                'Vsignature/vmade/vneeded/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/VlocalOffset',
                substr($archive, $offset, 46)
            );
            $name = substr($archive, $offset + 46, $header['nameLength']);
            $offset += 46 + $header['nameLength'] + $header['extraLength'] + $header['commentLength'];

            if ($header['uncompressed'] > 5 * 1024 * 1024 || $header['flags'] & 1) {
                throw new RuntimeException('File Excel terlalu besar atau dilindungi sandi.');
            }

            if (! in_array($name, ['xl/sharedStrings.xml', 'xl/worksheets/sheet1.xml'], true)
                && ! str_starts_with($name, 'xl/worksheets/')) {
                continue;
            }

            $local = unpack(
                'Vsignature/vneeded/vflags/vcompression/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength',
                substr($archive, $header['localOffset'], 30)
            );
            if (($local['signature'] ?? 0) !== 0x04034b50) {
                throw new RuntimeException('Struktur file Excel tidak dapat dibaca.');
            }

            $dataOffset = $header['localOffset'] + 30 + $local['nameLength'] + $local['extraLength'];
            $compressed = substr($archive, $dataOffset, $header['compressed']);
            $contents = match ($header['compression']) {
                0 => $compressed,
                8 => gzinflate($compressed),
                default => false,
            };

            if ($contents === false) {
                throw new RuntimeException('File Excel menggunakan format kompresi yang tidak didukung.');
            }

            $entries[$name] = $contents;
        }

        return $entries;
    }

    private static function zip(array $files): string
    {
        $data = '';
        $centralDirectory = '';
        $offset = 0;
        $entries = 0;
        $now = getdate();
        $dosTime = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
        $dosDate = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];

        foreach ($files as $name => $contents) {
            $compressed = gzdeflate($contents);
            if ($compressed === false) {
                throw new RuntimeException('Gagal membuat template Excel.');
            }

            $crc = crc32($contents);
            $nameLength = strlen($name);
            $compressedLength = strlen($compressed);
            $uncompressedLength = strlen($contents);
            $localFile = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, $crc,
                $compressedLength, $uncompressedLength, $nameLength, 0).$name.$compressed;
            $data .= $localFile;
            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate,
                $crc, $compressedLength, $uncompressedLength, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
            $offset += strlen($localFile);
            $entries++;
        }

        return $data.$centralDirectory.pack('VvvvvVVv', 0x06054b50, 0, 0, $entries, $entries,
            strlen($centralDirectory), $offset, 0);
    }
}
