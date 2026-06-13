<?php

namespace Domains\Receiving\States;

enum GoodsReceiptStatus: string
{
    case Completed = 'completed';
    case RequesterConfirmed = 'requester_confirmed';
    case BuyerConfirmed = 'buyer_confirmed';
}
