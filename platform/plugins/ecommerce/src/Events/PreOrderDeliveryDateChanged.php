<?php

namespace Botble\Ecommerce\Events;

use Botble\Base\Events\Event;
use Botble\Ecommerce\Models\PreOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreOrderDeliveryDateChanged extends Event
{
    use SerializesModels;
    use Dispatchable;

    public function __construct(
        public PreOrder $preOrder,
        public Carbon $oldDeliveryDate,
        public Carbon $newDeliveryDate
    ) {
    }
} 