<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $log_id
 * @property string $event
 * @property string $comment
 * @property int $client_id
 * @property int $trans_id
 * @property int $site_id
 * @property string $date
 */
class ActiveLog extends Model
{
    public $timestamps = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'active_log';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'log_id';

    /**
     * @var array
     */
    protected $fillable = ['event', 'comment', 'client_id', 'trans_id', 'site_id', 'date'];

    // show green background when log transaction event is successful, red if failed
    public function getShowColorClassAttribute()
    {
        $color = "";
        if(Str::contains($this->event, 'Successful')){
            $color = 'bg-log-success';
        } else if(Str::contains($this->event, 'Failed')) {
            $color = 'bg-log-failed';
        }
        return $color;
    }
}
