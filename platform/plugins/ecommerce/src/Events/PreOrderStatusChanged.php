<?php

namespace Botble\Ecommerce\Events;

use Botble\Base\Events\Event;
use Botble\Ecommerce\Models\PreOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreOrderStatusChanged extends Event
{
    use SerializesModels;
    use Dispatchable;

    public function __construct(
        public PreOrder $preOrder,
        public string $oldStatus,
        public string $newStatus
    ) {
    }
} 