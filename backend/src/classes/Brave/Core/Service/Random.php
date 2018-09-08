<?php declare(strict_types=1);

namespace Brave\Core\Service;

class Random
{
    /**
     * Provides a (cryptographically insecure) fallback for random_bytes() if that throws an exception.
     */
    public static function bytes(int $length): string
    {
        try {
            $bytes = random_bytes($length);
        } catch (\Exception $e) {
            $bytes = pack(
                'H*',
                self::chars($length * 2, '0123456789ABCDEF')
            );
        }

        return $bytes;
    }

    /**
     * Provides a (cryptographically insecure) fallback for random_int() if that throws an exception.
     */
    public static function int(int $min, int $max): int
    {
        try {
            $int = random_int($min, $max);
        } catch (\Exception $e) {
            $int = mt_rand($min, $max); // cryptographically insecure
        }

        return $int;
    }

    /**
     * Uses self::int() to generate a random string.
     */
    public static function chars(
        int $length,
        string $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    ): string {
        $max = mb_strlen($characters) - 1;
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[self::int(0, $max)];
        }

        return $string;
    }

    /**
     * Uses self::bytes() to generate a random HEX value.
     *
     * @param int $length should be even, if not it's rounded up to the nearest even number
     * @return string
     */
    public static function hex(int $length): string
    {
        return bin2hex(
            self::bytes(
                (int) ceil($length / 2)
            )
        );
    }
}
