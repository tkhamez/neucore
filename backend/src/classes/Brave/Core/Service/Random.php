<?php declare(strict_types=1);

namespace Brave\Core\Service;

/**
 * Provides a fallback for random_bytes() in case that throws an exception.
 */
class Random
{
    public static function bytes(int $length): string
    {
        try {
            $bytes = random_bytes($length);
        } catch (\Exception $e) {
            $bytes = self::pseudoRandomBytes($length);
        }

        return $bytes;
    }

    /**
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

    public static function pseudoRandomBytes(int $length): string
    {
        $characters = '0123456789ABCDEF';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length * 2; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return pack('H*', $randomString);
    }
}
