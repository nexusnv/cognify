<?php

namespace Domains\Fulfillment\States;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InTransit = 'in_transit';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Cancelled = 'cancelled';
}
