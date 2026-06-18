<?php

namespace App\Ingesta;

use Illuminate\Support\Carbon;

/**
 * Campos canónicos a los que el usuario mapea las columnas de su archivo.
 * Es el contrato que la Fase 3 usará para construir hechos/dimensiones.
 *
 * Modela el hecho transaccional de PERÚ COMPRAS: producto, monto, fecha,
 * proveedor, entidad, región (ver CLAUDE.md).
 */
class CanonicalSchema
{
    public const TYPE_STRING = 'string';

    public const TYPE_DATE = 'date';

    public const TYPE_NUMBER = 'number';

    /**
     * @return array<int, array{key:string,label:string,type:string,required:bool}>
     */
    public static function fields(): array
    {
        return [
            ['key' => 'fecha', 'label' => 'Fecha', 'type' => self::TYPE_DATE, 'required' => true],
            ['key' => 'producto', 'label' => 'Producto', 'type' => self::TYPE_STRING, 'required' => true],
            ['key' => 'monto', 'label' => 'Monto', 'type' => self::TYPE_NUMBER, 'required' => true],
            ['key' => 'cantidad', 'label' => 'Cantidad', 'type' => self::TYPE_NUMBER, 'required' => false],
            ['key' => 'proveedor', 'label' => 'Proveedor', 'type' => self::TYPE_STRING, 'required' => true],
            ['key' => 'entidad', 'label' => 'Entidad', 'type' => self::TYPE_STRING, 'required' => false],
            ['key' => 'region', 'label' => 'Región', 'type' => self::TYPE_STRING, 'required' => false],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_column(self::fields(), 'key');
    }

    /** @return array<int, string> */
    public static function requiredKeys(): array
    {
        return array_values(array_map(
            fn ($f) => $f['key'],
            array_filter(self::fields(), fn ($f) => $f['required']),
        ));
    }

    public static function typeOf(string $key): ?string
    {
        foreach (self::fields() as $field) {
            if ($field['key'] === $key) {
                return $field['type'];
            }
        }

        return null;
    }

    /**
     * Normaliza un valor crudo al tipo del campo canónico.
     */
    public static function cast(string $key, mixed $value): mixed
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === '' || $value === null) {
            return null;
        }

        return match (self::typeOf($key)) {
            self::TYPE_NUMBER => self::toNumber($value),
            self::TYPE_DATE => self::toDate($value),
            default => (string) $value,
        };
    }

    protected static function toNumber(mixed $value): ?float
    {
        // Tolera separadores de miles y símbolos comunes ("S/ 1,234.50").
        $clean = preg_replace('/[^\d,.\-]/', '', (string) $value) ?? '';

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace(',', '', $clean); // 1,234.50 -> 1234.50
        } elseif (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean); // 1234,50 -> 1234.50
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected static function toDate(mixed $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
