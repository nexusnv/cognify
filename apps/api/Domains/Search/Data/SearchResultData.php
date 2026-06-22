<?php

namespace Domains\Search\Data;

class SearchResultData
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $status,
        public readonly string $href,
        public readonly ?string $updatedAt,
    ) {}
}
