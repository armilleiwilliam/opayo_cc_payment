<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $refund_id
 * @property int $transaction_id
 * @property float $refund_amount
 * @property string $user_name
 * @property string $date
 */
class RefundHistory extends Model
{
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'refund_history';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'refund_id';

    /**
     * @var array
     */
    protected $fillable = ['transaction_id', 'refund_amount', 'user_name', 'date'];

}
