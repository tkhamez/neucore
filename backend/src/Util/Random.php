<?php

declare(strict_types=1);

namespace Neucore\Util;

use Exception;

abstract class Random
{
    /**
     * Generates a random string.
     *
     * @throws Exception
     */
    public static function chars(
        int $length,
        string $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ): string {
        $max = mb_strlen($characters) - 1;
        if ($length < 1 || $max < 1) {
            return '';
        }
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, $max)];
        }

        return $string;
    }

    /**
     * Generates a random HEX value.
     *
     * @param int $length should be even, if not it's rounded up to the nearest even number
     * @return string
     * @throws Exception
     */
    public static function hex(int $length): string
    {
        $length = (int) ceil($length / 2);
        if ($length < 1) {
            return '';
        }
        return bin2hex(
            random_bytes($length)
        );
    }
}
