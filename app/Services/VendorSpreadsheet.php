<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class VendorSpreadsheet
{
    public const MAX_ROWS = 1000;

    public const MAX_COLUMNS = 40;

    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true) || $file->getSize() > 5 * 1024 * 1024) {
            $this->fail('Upload a CSV or XLSX file of at most 5 MB. Save legacy XLS files as XLSX first.');
        }
        $rows = [];
        if ($extension === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            try {
                while (($row = fgetcsv($handle, separator: ',', escape: '')) !== false) {
                    $rows[] = $row;
                    if (count($rows) > self::MAX_ROWS + 1 || count($row) > self::MAX_COLUMNS) {
                        $this->fail('Use at most 1,000 data rows and 40 columns.');
                    }
                }
            } finally {
                fclose($handle);
            }
        } else {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->fail('This is not a readable XLSX workbook.');
            }
            $expanded = 0;
            try {
                if ($zip->numFiles > 1000) {
                    $this->fail('The workbook contains too many archive entries.');
                }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i);
                    $expanded += $entry['size'];
                    if ($expanded > 20 * 1024 * 1024) {
                        $this->fail('The expanded workbook exceeds 20 MB. Use a smaller sheet.');
                    }
                    if (preg_match('/vbaProject|externalLinks/i', $entry['name'])) {
                        $this->fail('Remove macros and external workbook links before uploading.');
                    }
                }
            } finally {
                $zip->close();
            }
            try {
                $reader = new Xlsx;
                $info = $reader->listWorksheetInfo($file->getRealPath());
                if (count($info) !== 1 || $info[0]['totalRows'] > self::MAX_ROWS + 1 || $info[0]['totalColumns'] > self::MAX_COLUMNS) {
                    $this->fail('Use one worksheet with at most 1,000 data rows and 40 columns.');
                }
                $reader->setReadDataOnly(true);
                $reader->setReadFilter(new class implements IReadFilter
                {
                    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                    {
                        return $row <= VendorSpreadsheet::MAX_ROWS + 1 && Coordinate::columnIndexFromString($columnAddress) <= VendorSpreadsheet::MAX_COLUMNS;
                    }
                });
                $book = $reader->load($file->getRealPath());
                try {
                    $sheet = $book->getSheet(0);
                    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                        if ($sheet->getCell($coordinate)->getDataType() === DataType::TYPE_FORMULA) {
                            $this->fail('Formulas are not accepted. Paste values into a fresh worksheet before uploading.');
                        }
                    }
                    $rows = $sheet->toArray(null, false, false, false);
                } finally {
                    $book->disconnectWorksheets();
                }
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $this->fail('The workbook could not be read. Save it as an unencrypted XLSX containing values only.');
            }
        }
        foreach ($rows as &$row) {
            foreach ($row as &$cell) {
                $cell = trim((string) ($cell ?? ''));
                if ($extension === 'csv' && preg_match("/^'[=+@-]/", $cell)) {
                    $cell = substr($cell, 1);
                }
                if (! mb_check_encoding($cell, 'UTF-8') || mb_strlen($cell) > 20000 || str_contains($cell, "\0")) {
                    $this->fail('Use UTF-8 text and cells shorter than 20,000 characters.');
                }
                if (str_starts_with($cell, '=')) {
                    $this->fail('Formulas are not accepted. Import values only.');
                }
            }
            unset($cell);
        }
        unset($row);
        if (count($rows) < 2) {
            $this->fail('Include a header row and at least one vendor.');
        }
        $headers = array_shift($rows);
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        if (in_array('', $headers, true) || count(array_unique(array_map('mb_strtolower', $headers))) !== count($headers)) {
            $this->fail('Each column needs a nonempty, unique header.');
        }
        foreach ($rows as $index => &$row) {
            if (count($row) > count($headers)) {
                $this->fail('Row '.($index + 2).' contains more cells than the header.');
            }
            $row = array_pad($row, count($headers), '');
        }
        unset($row);

        return [$headers, $rows];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    public function download(array $headers, iterable $rows, string $format, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $format) {
            if ($format === 'csv') {
                $stream = fopen('php://output', 'w');
                fwrite($stream, "\xEF\xBB\xBF");
                foreach ([$headers, ...$rows] as $row) {
                    fputcsv($stream, array_map(function ($value) {
                        $value = (string) ($value ?? '');

                        return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                    }, $row), escape: '');
                }
                fclose($stream);

                return;
            }
            $book = new Spreadsheet;
            try {
                $sheet = $book->getActiveSheet();
                $sheet->setTitle('Vendors');
                foreach ([$headers, ...$rows] as $rowIndex => $row) {
                    foreach (array_values($row) as $column => $value) {
                        $sheet->setCellValueExplicit([$column + 1, $rowIndex + 1], (string) ($value ?? ''), DataType::TYPE_STRING);
                    }
                }
                $end = Coordinate::stringFromColumnIndex(count($headers));
                $sheet->getStyle('A1:'.$end.'1')->getFont()->setBold(true);
                $sheet->freezePane('C2');
                $sheet->setAutoFilter('A1:'.$end.$sheet->getHighestRow());
                foreach (range(1, count($headers)) as $column) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(24);
                }
                $writer = new XlsxWriter($book);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename.'.'.$format, [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
