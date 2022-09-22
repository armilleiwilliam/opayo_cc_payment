<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentRequests extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_requests';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'site_id',
        'site_client_id',
        'site_job_number',
        'first_name',
        'last_name',
        'email',
        'tel',
        'address_1',
        'address_2',
        'city',
        'postcode',
        'country',
        'source',
        'amount',
        'currency',
        'pre_vat',
        'vat_amount',
        'package',
        'date_created',
        'date_actioned',
        'processed_by',
        'assigned_to',
        '3ds_enabled',
        'paypal_enabled',
        'user_ip',
        'transaction_id'
    ];

    public static function updateData($updateData = null){
        if($updateData && is_array($updateData) && array_key_exists("id_reference", $updateData)){
            if($payment_request = PaymentRequests::find($updateData["id_reference"])){
                $payment_request->first_name = $updateData["firstName"];
                $payment_request->last_name = $updateData["lastName"];
                $payment_request->email = $updateData["email"];
                $payment_request->tel = $updateData["tel"];
                $payment_request->address_1 = substr($updateData["address_1"], 0, 99);
                $payment_request->address_2 = !empty($updateData["address_2"]) ? substr($updateData["address_2"], 0, 200) : '';
                $payment_request->city = substr($updateData["city"], 0, 39);
                $payment_request->country = substr($updateData["country"], 0, 99);
                $payment_request->postcode = $updateData["postcode"];
                $payment_request->save();
            }
        }
    }

    public function getIsFdrAttribute(){
        return $this->company_id == env("FDR_SITE");
    }

    public function site(){
        return $this->hasOne(Site::Class, 'site_id', 'site_id');
    }

    public function getPreVatDecimalAttribute()
    {
        return number_format($this->pre_vat, 2, '.', '');
    }

    public function getAmountDecimalAttribute()
    {
        return number_format($this->amount, 2, '.', '');
    }

    public function getVatAmountDecimalAttribute()
    {
        return number_format($this->vat_amount, 2, '.', '');
    }

    public function getNonVatAmountDecimalAttribute()
    {
        return number_format($this->non_vat_amount, 2, '.', '');
    }

    public function getIsVatableSetAttribute()
    {
        if($this->pre_vat_decimal > 0.00 && $this->vat_amount_decimal > 0.00){
            return "selected";
        }
    }

}
