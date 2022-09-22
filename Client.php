<?php

namespace App;

use Carbon\Carbon;
use Eloquent;
use Illuminate\Support\Facades\Session;

/**
 * @property int $client_id
 * @property string $full_name
 * @property string $address
 * @property string $postcode
 * @property string $country
 * @property string $tel
 * @property string $fax
 * @property string $email
 * @property string $date_created
 * @property string $date_lastupdated
 * @property string $ip_address
 * @property string $compName
 */
class Client extends Eloquent
{
    public $timestamps = true;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'client';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'client_id';

    /**
     * new dates constant value
     */
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_lastupdated';

    /**
     * @var array
     */
    protected $fillable = ['full_name', 'address', 'postcode', 'country', 'tel', 'fax', 'email', 'date_created', 'date_lastupdated', 'ip_address', 'compName'];

    /**
     * Join transactions
     */
    public function transaction()
    {
        return $this->hasMany('App\Transaction', 'transID');
    }

    /**
     * used when transaction is processed and client values need to be retrieved
     * @return array
     */
    static public function clientAndVendorDataRetrieve($site_id = 0, $payType = "CC")
    {
        $now = Carbon::now();
        $mysqlDate = $now->format('Y-m-d H:i:s');
        $costumerJustLandedActiveLogId = NULL;
        $data = unserialize(Session::get('currentBasket'));
        $vendorClientAccount = NULL;
        $activeLogCommentMessage = NULL;
        $site = Site::where('site_id', '=', $data["site_id"])->first();

        $vendorAccount = VendorAccount::where('vendor_id', '=', $site["vendor_id"])
            ->where('PAY_Type', '=', $payType)
            ->with('vendor')
            ->first();

        // create/update new client and get gateway client ID
        $activeLogEventMessage = "";
        $fullName = ($data["first_name"] ?? $data["firstName"])  . ' ' . ($data["last_name"] ?? $data["lastName"]);
        $address = $data["address_1"] . "\n" . (!empty($data["address_2"]) ? $data["address_2"] . "\n" : '') . $data["city"];
        $compName = (!empty($data['compName'])) ? $data['compName'] : '';

        $gatewayClient = Client::updateOrCreate(
            ['email' => $data["email"]],
            ['full_name' => $fullName,
                'address' => $address,
                'postcode' => $data["postcode"],
                'country' => $data["country"],
                'tel' => $data["tel"],
                'email' => $data["email"],
                'ip_address' => $_SERVER["REMOTE_ADDR"],
                'compName' => $compName
            ]
        );

        if(!$gatewayClient->wasRecentlyCreated && $gatewayClient->wasChanged()){
            $activeLogCommentMessage = "Client " . $fullName . " has been updated";
            $activeLogEventMessage = "Client Account Updated";
        }

        if($gatewayClient->wasRecentlyCreated){
            $activeLogCommentMessage = "Client " . $fullName . " has been created";
            $activeLogEventMessage = "Client Account Created";
        }

        if(!empty(Session::get("justLanded"))) {

            // active log for just landing in the page
            $activeLog = new ActiveLog();
            $activeLog->event = "Client Redirect";
            $activeLog->comment = $fullName . " has landed on payment page";
            $activeLog->client_id = $gatewayClient->client_id;
            $activeLog->trans_id = 0;
            $activeLog->site_id = $site_id;
            $activeLog->date = $now;
            $activeLog->save();
            $costumerJustLandedActiveLogId = $activeLog->log_id;
            Session::forget("justLanded");
        }

        if((!$gatewayClient->wasRecentlyCreated && $gatewayClient->wasChanged()) || $gatewayClient->wasRecentlyCreated){
            // active log
            $activeLog = new ActiveLog();
            $activeLog->event = $activeLogEventMessage;
            $activeLog->comment = $activeLogCommentMessage;
            $activeLog->client_id = $gatewayClient->client_id;
            $activeLog->trans_id = 0;
            $activeLog->site_id = $site_id;
            $activeLog->date = $now;
            $activeLog->save();
        }

        if(isset($vendorAccount->client_account)){
            $vendorClientAccount = $vendorAccount->client_account;
        }

        Session::put("client_id", $gatewayClient->client_id);

        return array("client_account" => $vendorClientAccount, "client_id" => $gatewayClient->client_id, "log_id" => ($activeLog->log_id ?? false), "user_landed_active_log_id" => $costumerJustLandedActiveLogId);
    }
}
