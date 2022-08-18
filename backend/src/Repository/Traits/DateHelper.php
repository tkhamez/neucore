<?php

namespace Neucore\Repository\Traits;

trait DateHelper
{
    /**
     * @param int $mode 1 = "year + month", 2 "year + month + day", 3 "year + month + day + hour"
     */
    private function getDateNumber($time, int $mode = 1): int
    {
        $year = (int)date('Y', $time);
        $month = (int)date('m', $time);
        $day = (int)date('j', $time);
        $hour = (int)date('G', $time);

        if ($mode === 3) {
            // e.g. 2022081817 (2022-08-18 17:00:00)
            return ((($year * 100) + $month) * 100 + $day) * 100 + $hour;
        } elseif ($mode === 2) {
            return (($year * 100) + $month) * 100 + $day;
        } else {
            return ($year * 100) + $month;
        }
    }
}
