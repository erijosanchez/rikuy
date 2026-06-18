<?php

namespace App\Ingesta;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lectura por streaming de archivos tabulares (CSV / XLSX) vía openspout.
 * Mantiene el uso de memoria bajo aunque el archivo sea grande.
 */
class SpreadsheetReader
{
    /**
     * Devuelve los encabezados (primera fila) ya recortados.
     *
     * @return array<int, string>
     */
    public function headers(string $path): array
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return array_map(
                        fn ($value) => trim((string) $value),
                        $row->toArray(),
                    );
                }
            }
        } finally {
            $reader->close();
        }

        return [];
    }

    /**
     * Itera las filas de datos (omite el encabezado), invocando
     * $callback(array<string,mixed> $rowByHeader, int $rowNumber).
     */
    public function eachRow(string $path, callable $callback): void
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();

                    if ($headers === null) {
                        $headers = array_map(fn ($v) => trim((string) $v), $values);

                        continue;
                    }

                    // Ignora filas totalmente vacías.
                    if ($values === [] || count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                        continue;
                    }

                    $rowNumber++;
                    $assoc = [];
                    foreach ($headers as $i => $header) {
                        $assoc[$header] = $values[$i] ?? null;
                    }

                    $callback($assoc, $rowNumber);
                }

                break; // solo la primera hoja
            }
        } finally {
            $reader->close();
        }
    }

    protected function readerFor(string $path): ReaderInterface
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx' => new XlsxReader,
            default => new CsvReader,
        };
    }
}
