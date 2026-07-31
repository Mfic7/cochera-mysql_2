<?php

namespace App\Support;

class Dates
{
    /** Convierte un datetime "naive" de MySQL (hora del servidor, sin offset) a ISO 8601 con offset explÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­cito. */
    public static function iso(?string $mysqlDatetime): ?string
    {
        if ($mysqlDatetime === null || $mysqlDatetime === '') {
            return null;
        }
        $ts = strtotime($mysqlDatetime);
        return $ts === false ? null : date('c', $ts);
    }
}
