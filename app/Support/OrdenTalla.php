<?php

namespace App\Support;

/**
 * Orden de tallas de uniforme: XS, S, M, L, XL, 2XL, 3XL, 4XL.
 * La columna debe ser un identificador fijo del código, nunca entrada del usuario.
 */
class OrdenTalla
{
    public static function sqlColumna(string $column): string
    {
        $col = 'upper(trim(' . $column . '))';

        return "CASE
            WHEN {$col} IN ('XXS') THEN 1
            WHEN {$col} IN ('XS') THEN 2
            WHEN {$col} IN ('S') THEN 3
            WHEN {$col} IN ('M') THEN 4
            WHEN {$col} IN ('L') THEN 5
            WHEN {$col} IN ('XL') THEN 6
            WHEN {$col} IN ('XXL', '2XL') THEN 7
            WHEN {$col} IN ('XXXL', '3XL') THEN 8
            WHEN {$col} IN ('XXXXL', '4XL') THEN 9
            WHEN {$col} IN ('5XL') THEN 10
            ELSE 50
        END";
    }

    public static function sqlEnTexto(string $column): string
    {
        $text = 'upper(' . $column . ')';

        return "CASE
            WHEN {$text} ~ '\\m(4XL|XXXXL)\\M' THEN 9
            WHEN {$text} ~ '\\m(3XL|XXXL)\\M' THEN 8
            WHEN {$text} ~ '\\m(2XL|XXL)\\M' THEN 7
            WHEN {$text} ~ '\\mXL\\M' THEN 6
            WHEN {$text} ~ '\\mXXS\\M' THEN 1
            WHEN {$text} ~ '\\mXS\\M' THEN 2
            WHEN {$text} ~ '\\mS\\M' THEN 3
            WHEN {$text} ~ '\\mM\\M' THEN 4
            WHEN {$text} ~ '\\mL\\M' THEN 5
            ELSE 50
        END";
    }

    public static function sqlFamilia(string $column): string
    {
        return "trim(regexp_replace(regexp_replace(upper({$column}), '\\m(5XL|4XL|XXXXL|3XL|XXXL|2XL|XXL|XL|XXS|XS|S|M|L|NUEVO|USADO)\\M', '', 'g'), '\\s+', ' ', 'g'))";
    }
}
