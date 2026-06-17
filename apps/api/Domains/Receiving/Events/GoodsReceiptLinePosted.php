<?php

namespace Domains\Receiving\Events;

use Domains\Receiving\Models\GoodsReceiptLine;
use Illuminate\Foundation\Events\Dispatchable;

class GoodsReceiptLinePosted
{
    use Dispatchable;

    public function __construct(
        public readonly GoodsReceiptLine $goodsReceiptLine,
    ) {}
}
