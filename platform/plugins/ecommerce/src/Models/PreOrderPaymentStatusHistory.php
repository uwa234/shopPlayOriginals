<?php

namespace Botble\Ecommerce\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PreOrderPaymentStatusHistory extends BaseModel
{
    protected $table = 'ec_pre_order_payment_status_history';

    protected $fillable = [
        'pre_order_payment_id',
        'status',
        'description',
        'user_id',
        'user_type',
    ];

    protected $casts = [
        'description' => SafeContent::class,
    ];

    public function preOrderPayment(): BelongsTo
    {
        return $this->belongsTo(PreOrderPayment::class, 'pre_order_payment_id');
    }

    public function user(): MorphTo
    {
        return $this->morphTo('user', 'user_type', 'user_id');
    }
}
