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
        foreach ($rateLimits as $group => $values) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            if (
                (string) $group === '' ||
                !$values instanceof self
            ) {
                continue;
            }
            $valid[$group] = $values;
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
                    !isset($values->g) ||
                    !isset($values->l) ||
                    !isset($values->r) ||
                    !isset($values->u) ||
                    !isset($values->c)
                ) {
                    continue;
                }
                $result[$group] = new self(
                    $values->g,
                    $values->l,
                    $values->r,
                    $values->u,
                    $values->c,
                );
            }
        }

        return $result;
    }

    /**
     * @param string $g Group name, e.g. "fitting"
     * @param string $l Limit, e.g. "150/15m", "15/1h"
     * @param int $r Remaining, e.g. 148
     * @param int $u Used, e.g. 2
     * @param int|null $c Character ID for authenticated requests.
     */
    public function __construct(
        public readonly string $g,
        public readonly string $l,
        public readonly int $r,
        public readonly int $u,
        public readonly ?int $c,
    ) {}
}
