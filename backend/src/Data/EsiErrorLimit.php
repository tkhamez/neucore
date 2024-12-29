<?php

namespace Neucore\Data;

class EsiErrorLimit
{
    public function __construct(
        public ?int $updated,
        public ?int $remain,
        public ?int $reset,
    ) {}

    public static function fromJson(string $json): self
    {
        $data = json_decode($json);

        if ($data instanceof \stdClass) {
            return new self($data->updated, $data->remain, $data->reset);
        }

        return new self(null, null, null);
    }
}
