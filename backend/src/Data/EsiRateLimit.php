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
                    !isset($values->group) ||
                    !isset($values->limit) ||
                    !isset($values->remaining) ||
                    !isset($values->used) ||
                    !isset($values->characterId)
                ) {
                    continue;
                }
                $result[$group] = new self(
                    $values->group,
                    $values->limit,
                    $values->remaining,
                    $values->used,
                    $values->characterId,
                );
            }
        }

        return $result;
    }

    public function __construct(
        public readonly string $group,
        public readonly string $limit,
        public readonly int $remaining,
        public readonly int $used,
        public readonly ?int $characterId,
    ) {}
}
