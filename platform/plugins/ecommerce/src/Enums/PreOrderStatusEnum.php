<?php

namespace Botble\Ecommerce\Enums;

use Botble\Base\Supports\Enum;
use Illuminate\Support\HtmlString;

/**
 * @method static PreOrderStatusEnum NOT_STARTED()
 * @method static PreOrderStatusEnum ACTIVE()
 * @method static PreOrderStatusEnum EXPIRED()
 * @method static PreOrderStatusEnum COMPLETED()
 * @method static PreOrderStatusEnum CANCELLED()
 */
class PreOrderStatusEnum extends Enum
{
    public const NOT_STARTED = 'not_started';
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public static $langPath = 'plugins/ecommerce::pre-orders.statuses';

    public function toHtml(): HtmlString|string
    {
        $color = match ($this->value) {
            self::NOT_STARTED => 'info',
            self::ACTIVE => 'success',
            self::EXPIRED => 'warning',
            self::COMPLETED => 'primary',
            self::CANCELLED => 'danger',
            default => 'secondary',
        };

        return Html::tag('span', $this->label(), ['class' => "badge bg-{$color}"])->toHtml();
    }
} 