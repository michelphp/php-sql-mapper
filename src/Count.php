<?php

namespace Michel\SqlMapper;

use http\Encoding\Stream;

final class Count
{
    private Select $select;
    private function __construct(Select $select)
    {
        $this->select = $select->cloneAndResetParts('fields','order','limit','offset','distinct');
    }

    public static function createFromSelect(Select $select): self
    {
        return new self($select);
    }

    public function __toString(): string
    {
        return $this->select->__toString();
    }

    public function on(string $field, ?string $as = null, bool $distinct = false): self
    {
        $expr = $distinct ? sprintf('COUNT(DISTINCT %s)', $field)
            : sprintf('COUNT(%s)', $field);

        if ($as !== null) {
            $expr .= ' AS ' . $as;
        }

        $this->select->select($expr);
        return $this;
    }
}
