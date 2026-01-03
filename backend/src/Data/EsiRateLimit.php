<?php

declare(strict_types=1);

namespace Neucore\Data;

class EsiRateLimit
{
    /**
     * @param array<string, EsiRateLimit> $rateLimits
     */
    public static function toJson(array $rateLimits): string
    {
        $valid = [];
        foreach ($rateLimits as $bucket => $values) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            if (
                (string) $bucket === '' ||
                !$values instanceof self
            ) {
                continue;
            }
            $valid[$bucket] = $values;
        }
        return (string) \json_encode($valid, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, EsiRateLimit>
     */
    public static function fromJson(string $json): array
    {
        $data = json_decode($json);

        $result = [];
        if ($data instanceof \stdClass) {
            foreach (get_object_vars($data) as $group => $values) {
                if (
                    (string) $group === '' ||
                    !property_exists($values, 'g') ||
                    !property_exists($values, 'l') ||
                    !property_exists($values, 'r') ||
                    !property_exists($values, 'u') ||
                    !property_exists($values, 't') ||
                    !property_exists($values, 'c')
                ) {
                    continue;
                }
                $result[$group] = new self(
                    $values->g,
                    $values->l,
                    $values->r,
                    $values->u,
                    $values->t,
                    $values->c,
                );
            }
        }

        return $result;
    }

    /**
     * @param string $g Route group identifier, e.g. "fitting"
     * @param string $l Total tokens per window, e.g. "150/15m", "15/1h"
     * @param int $r Available tokens remaining, e.g. 148
     * @param int $u Tokens consumed by the request, e.g. 2
     * @param int $t Time of the response, e.g. 1767448553
     * @param int|null $c Character ID for authenticated requests.
     */
    public function __construct(
        public readonly string $g,
        public readonly string $l,
        public readonly int $r,
        public readonly int $u,
        public readonly int $t,
        public readonly ?int $c,
    ) {}

    public function getBucket(): string
    {
        return $this->c ? "$this->g:$this->c" : $this->g;
    }

    /**
     * Returns 0 on error.
     */
    public function getTokensPerWindow(): int
    {
        return (int) (explode('/', $this->l)[0] ?? 0);
    }

    /**
     * Returns 0 on error.
     */
    public function getWindowInSeconds(): int
    {
        $window = explode('/', $this->l)[1] ?? '';

        if ($window === '' || !preg_match('/^\d+[mh]$/', $window)) {
            return 0;
        }

        $time = (int) substr($window, 0, -1);
        $unit = strtolower(substr($window, -1));

        return match ($unit) {
            'm' => $time * 60,
            'h' => $time * 60 * 60,
            default => 0,
        };
    }
}
