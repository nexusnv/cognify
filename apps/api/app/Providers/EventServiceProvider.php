<?php

namespace App\Providers;

use Domains\Invoice\Listeners\ReRunMatchingOnGoodsReceipt;
use Domains\Receiving\Events\GoodsReceiptLinePosted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        GoodsReceiptLinePosted::class => [
            ReRunMatchingOnGoodsReceipt::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
