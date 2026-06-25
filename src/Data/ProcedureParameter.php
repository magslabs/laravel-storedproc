<?php

namespace MagsLabs\LaravelStoredProc\Data;

final class ProcedureParameter
{
    public function __construct(
        public readonly string $name,
        public readonly int $ordinal,
        public readonly string $direction,
        public readonly ?string $data_type = null,
    ) {}

    public function isInput(): bool
    {
        return in_array($this->direction, ['IN', 'INOUT'], true);
    }

    public function isOutput(): bool
    {
        return in_array($this->direction, ['OUT', 'INOUT'], true);
    }
}
