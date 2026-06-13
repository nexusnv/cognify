<?php

namespace Domains\Fulfillment\States;

enum FulfillmentTrackingEventStatus: string
{
    case Created = 'created';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Customs = 'customs';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Exception = 'exception';
}
