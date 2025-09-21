<?php

namespace Botble\Ecommerce\Enums;

use Botble\Base\Facades\Html;
use Botble\Base\Supports\Enum;

/**
 * @method static PreOrderPaymentTypeEnum DEPOSIT()
 * @method static PreOrderPaymentTypeEnum FULL_PAYMENT()
 */
class PreOrderPaymentTypeEnum extends Enum
{
    public const DEPOSIT = 'deposit';
    public const FULL_PAYMENT = 'full_payment';

    public static function labels(): array
    {
        return [
            self::DEPOSIT => __('Deposit Payment'),
            self::FULL_PAYMENT => __('Full Payment'),
        ];
    }

    public static function colors(): array
    {
        return [
            self::DEPOSIT => 'warning',
            self::FULL_PAYMENT => 'success',
        ];
    }

    public function toHtml(): string
    {
        $color = self::colors()[$this->value] ?? 'secondary';
        $label = self::labels()[$this->value] ?? $this->value;

        return Html::tag('span', $label, [
            'class' => 'badge bg-' . $color . ' text-' . $color . '-fg',
        ])->toHtml();
    }
} 