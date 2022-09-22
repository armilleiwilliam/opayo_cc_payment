<?php

namespace App;

//use Illuminate\Database\Eloquent\Model;
use App\Services\Sagepay\Model\SagepayConstants;
use App\TransactionSummary;
use Illuminate\Database\Eloquent\Model;
use DB;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

/**
 * @property int $transID
 * @property int $invoice_no
 * @property string $VSPTxID
 * @property string $status
 * @property string $process_by
 * @property string $assigned_to
 * @property string $PAY_Type
 * @property string $client_account
 * @property string $Tx_Type
 * @property string $currency
 * @property string $tax_code
 * @property float $amount
 * @property float $pre_vat
 * @property float $non_vat_amount
 * @property float $vat_amount
 * @property float $post_vat
 * @property string $vat_number
 * @property string $security_key
 * @property string $VendorTxCode
 * @property string $description
 * @property string $date
 * @property string $TxAuthNo
 * @property string $AVSCV2
 * @property int $client_id
 * @property int $site_id
 * @property int $site_client_id
 * @property int $company_id
 * @property int $site_job_number
 * @property string $transDetails
 * @property string $testvat_number
 * @property string $updated_to_new
 * @property string $ref_no
 * @property string $paid
 * @property string $export_sage
 * @property string $invoice_spooled
 * @property string $customerRef
 * @property boolean $chargeback
 * @property float $discount_price
 * @property string $discount_description
 * @property string $ip_address
 * @property string $reference_transaction_id
 * @property string $raw_response
 */
class Transaction extends Model
{
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'transID';

    /**
     * @var array
     */
    protected $fillable = ['transID', 'invoice_no', 'VSPTxID', 'status', 'process_by', 'assigned_to', 'PAY_Type', 'client_account', 'Tx_Type', 'currency', 'tax_code', 'amount', 'pre_vat', 'non_vat_amount', 'vat_amount', 'post_vat', 'vat_number', 'security_key', 'VendorTxCode', 'description', 'date', 'TxAuthNo', 'AVSCV2', 'client_id', 'site_id', 'site_client_id', 'company_id', 'site_job_number', 'transDetails', 'test', 'updated_to_new', 'ref_no', 'paid', 'export_sage', 'invoice_spooled', 'customerRef', 'chargeback', 'discount_price', 'discount_description', 'ip_address', 'site.site_short_name', 'company.company_short_name', 'site.client_acc', 'raw_response'];

    /**
     * Join Companies
     */
    public function company()
    {
        return $this->belongsTo('App\Companies', 'company_id');
    }

    /**
     * Join Sites
     */
    public function site()
    {
        return $this->belongsTo('App\Site', 'site_id');
    }

    /**
     * Join Client
     */
    public function client()
    {
        return $this->belongsTo('App\Client', 'client_id');
    }

    /**
     * Additional columns to be loaded for datatables.
     *
     * @return array
     */
    public static function laratablesAdditionalColumns()
    {
        return ['company_id', 'site_id', 'client_id'];
    }

    protected function getCompanyId() {
        return Transaction::join('companies','transaction.company_id','companies.company_id')->pluck('companies.company_id');
    }

    protected function getCompanyShortName() {
        return Transaction::join('companies','transaction.company_id','companies.company_id')->pluck('companies.company_short_name');
    }

    /**
     * Limit datatables query .
     *
     * @param \Illuminate\Database\Eloquent\Builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function laratablesQueryConditions($query)
    {
        $dateStart = session()->get('dateStart');
        $dateEnd = session()->get('dateEnd');
        $companyID = session()->get('companyID');
        $siteID = session()->get('siteID');
        $status = session()->get('status');
        $type = session()->get('type');
        $includeCA = session()->get('includeCA');
        $excludeDI = session()->get('excludeDI');
        $siteClient = session()->get('siteClient');
        $siteJob = session()->get('siteJob');
        $processBy = session()->get('process_by');
        $assignedTo = session()->get('assigned_to');

        if ($companyID == 'All') {
            $companyID = false;
        }
        if ($siteID == 'All') {
            $siteID = false;
        }
        if ($status == 'All') {
            $status = false;
        }
        if ($type == 'All') {
            $type = false;
        }
        if ($siteClient == 'All') {
            $siteClient = false;
        }
        if ($siteJob == 'All') {
            $siteJob = false;
        }
        if ($processBy == 'All') {
            $processBy = false;
        }
        if ($assignedTo == 'All') {
            $assignedTo = false;
        }

        //$company_id_search = ($companyID == 'All') ? '<>' : '=';
        //$company_id_value = ($companyID == 'All') ? '' : $companyID;
        //$site_id_search = ($siteID == 'All') ? '<>' : '=';
        //$site_id_value = ($siteID == 'All') ? '' : $siteID;
        //$pg_status_search = ($status == 'All') ? '<>' : '=';
        //$pg_status_value = ($status == 'All') ? '' : $status;
        //$tx_type_search = ($type == 'All') ? '<>' : '=';
        //$tx_type_value = ($type == 'All') ? '' : $type;
        //$site_client_id_search = (ctype_digit($siteClient)) ? '=' : 'LIKE';
        //$site_client_id_value = (ctype_digit($siteClient)) ? $siteClient : '%';
        //$site_job_number_search = (ctype_digit($siteJob)) ? '=' : 'LIKE';
        //$site_job_number_value = (ctype_digit($siteJob)) ? $siteJob : '%';
        $include_CA_value = ($includeCA) ? '1' : '0';
        $excludeDI_search = ($includeCA) ? '!=' : '<>';
        $excludeDI_value = ($includeCA) ? '108' : '';
        //$process_by_search = ($processBy == 'All') ? '<>' : '=';
        //$process_by_value = ($processBy == 'All') ? '' : $processBy;
        //$assigned_to_search = ($assignedTo == 'All') ? '<>' : '=';
        //$assigned_to_value = ($assignedTo == 'All') ? '' : $assignedTo;

        if ($dateStart == $dateEnd) {
            return $query->where(DB::raw('date(date)'), '=', $dateStart)
                ->when($companyID, function ($query, $companyID) {
                    return $query->where('transaction.company_id', $companyID);
                })
                ->when($siteID, function ($query, $siteID) {
                    return $query->where('transaction.site_id', $siteID);
                })
                ->when($status, function ($query, $status) {
                    return $query->where('transaction.status', $status);
                })
                ->when($type, function ($query, $type) {
                    return $query->where('transaction.PAY_Type', $type);
                })
                ->when($processBy, function ($query, $processBy) {
                    return $query->where('transaction.process_by', $processBy);
                })
                ->when($assignedTo, function ($query, $assignedTo) {
                    return $query->where('transaction.assigned_to', $assignedTo);
                })
                ->when($siteClient, function ($query, $siteClient) {
                    return $query->where('transaction.site_client_id', $siteClient);
                })
                ->when($siteJob, function ($query, $siteJob) {
                    return $query->where('transaction.site_job_number', $siteJob);
                });
        } else {
            return $query->whereBetween(DB::raw('date(date)'), [$dateStart, $dateEnd])
                ->when($companyID, function ($query, $companyID) {
                    return $query->where('transaction.company_id', $companyID);
                })
                ->when($siteID, function ($query, $siteID) {
                    return $query->where('transaction.site_id', $siteID);
                })
                ->when($status, function ($query, $status) {
                    return $query->where('transaction.status', $status);
                })
                ->when($type, function ($query, $type) {
                    return $query->where('transaction.PAY_Type', $type);
                })
                ->when($processBy, function ($query, $processBy) {
                    return $query->where('transaction.process_by', $processBy);
                })
                ->when($assignedTo, function ($query, $assignedTo) {
                    return $query->where('transaction.assigned_to', $assignedTo);
                })
                ->when($siteClient, function ($query, $siteClient) {
                    return $query->where('transaction.site_client_id', $siteClient);
                })
                ->when($siteJob, function ($query, $siteJob) {
                    return $query->where('transaction.site_job_number', $siteJob);
                });
        }
    }

    public static function laratablesSearchAll($query, $dateArray, $companyID, $siteID)
    {
        if ($siteID == false) {
            $siteID = '%';
        }
        if ($companyID == false) {
            $companyID = '%';
        }
        return $query->whereBetween(DB::raw('date(date)'), $dateArray)
            ->andWhere('company_id', 'LIKE', $companyID)
            ->andWhere('site_id', 'LIKE', $siteID);
    }

    public static function getSelect($column, $dateStart, $dateEnd, $companyID)
    {
        $array['All'] = 'All';
        $array['Partners'] = 'FDR Partners';

        $data = Transaction::distinct()->whereBetween(DB::raw('date(date)'), [$dateStart, $dateEnd])->orderBy($column, 'asc');
        if (ctype_digit($companyID)) {
            $data = Transaction::distinct()->whereBetween(DB::raw('date(date)'), [$dateStart, $dateEnd])->where('company_id', '=', $companyID)->orderBy($column, 'asc');
        } else {
            $data = Transaction::distinct()->whereBetween(DB::raw('date(date)'), [$dateStart, $dateEnd])->orderBy($column, 'asc');
        }

        // remove empty and numeric assigned_to and process_by values
        if($column == "process_by" || $column == "assigned_to"){
            $data = $data->where($column, "!=", "")->get($column)->reject(function ($value){
                return is_numeric($value->process_by) || is_numeric($value->assigned_to);
            });
        } else {
            $data = $data->get($column);
        }

        foreach ($data as $result) {
            $array[$result->$column] = $result->$column;
        }
        return $array;
    }

    /**
     * Update 12 month summary to speed up page loading
     */
    public static function updateSummary()
    {
        $date = new \DateTime(date('Y-m' . '-01'));
        $date->modify('-11 month');
        $dateStart = $date->format('Y-m' . '-01 00:00:00');
        $labels[] = $date->format('My');
        for ($i = 1; $i <= 11; $i++) {
            $date->modify('+1 month');
            $labels[] = $date->format('My');
            $lookupDate[] = $date->format('Y-m');
        }
        $dateEnd = $date->format('Y-m-d 23:59:59');
        //print "Start: $dateStart End: $dateEnd<br>";
        //dump($labels);

        $totalsArray = [];
        $companyArray = ['1', '8', '12'];
        foreach ($companyArray as $company_id) {
            if ($company_id != 12) {
                $transData = Transaction::whereDate('date', '>=', $dateStart)
                    ->whereDate('date', '<=', $dateEnd)
                    ->where('company_id', '=', $company_id)
                    ->where('status', '=', 'OK')
                    ->where('Tx_Type', '!=', 'REFUND')
                    ->select(DB::raw('SUM(pre_vat) as total, MONTH(date) as month, YEAR(date) as year'))
                    ->groupBy(DB::raw('MONTH(date), YEAR(date)'))
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->get()
                    ->toArray();
                //dump($transData);

                $refundData = Transaction::whereDate('date', '>=', $dateStart)
                    ->whereDate('date', '<=', $dateEnd)
                    ->where('company_id', '=', $company_id)
                    ->where('status', '=', 'OK')
                    ->where('Tx_Type', '=', 'REFUND')
                    ->select(DB::raw('SUM(pre_vat) as total, MONTH(date) as month, YEAR(date) as year'))
                    ->groupBy(DB::raw('MONTH(date), YEAR(date)'))
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->get()
                    ->toArray();
                //dump($refundData);
            } else {
                $transData = Transaction::whereDate('date', '>=', $dateStart)
                    ->whereDate('date', '<=', $dateEnd)
                    ->where('company_id', '=', $company_id)
                    ->where('status', '=', 'OK')
                    ->where('site_id', '=', 117)
                    ->where('Tx_Type', '!=', 'REFUND')
                    ->select(DB::raw('SUM(pre_vat) as total, MONTH(date) as month, YEAR(date) as year'))
                    ->groupBy(DB::raw('MONTH(date), YEAR(date)'))
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->get()
                    ->toArray();
                //dump($transData);

                $refundData = Transaction::whereDate('date', '>=', $dateStart)
                    ->whereDate('date', '<=', $dateEnd)
                    ->where('company_id', '=', $company_id)
                    ->where('status', '=', 'OK')
                    ->where('Tx_Type', '=', 'REFUND')
                    ->where('site_id', '=', 117)
                    ->select(DB::raw('SUM(pre_vat) as total, MONTH(date) as month, YEAR(date) as year'))
                    ->groupBy(DB::raw('MONTH(date), YEAR(date)'))
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->get()
                    ->toArray();
                //dump($refundData);
            }

            $finalArray = [];
            foreach ($transData as $key => $values) {
                $year = $values['year'];
                $month = $values['month'];
                $finalArray[$year][$month]['credit'] = $values['total'];
            }

            foreach ($refundData as $key => $values) {
                $year = $values['year'];
                $month = $values['month'];
                $finalArray[$year][$month]['debit'] = $values['total'];
            }

            foreach ($finalArray as $year => $monthsArray) {
                foreach ($monthsArray as $month => $monthValues) {
                    $credit = round($monthValues['credit']);
                    $debit = (!empty($monthValues['debit'])) ? round($monthValues['debit']) : 0;
                    $net = $credit - $debit;
                    $save = TransactionSummary::updateOrCreate(
                        ['year' => $year, 'month' => $month, 'company_id' => $company_id],
                        ['credit' => $credit, 'debit' => $debit, 'net_amount' => $net]
                    );
                }
            }
        }
    }

    public static function vendorTXcode($site_id, $trans_id)
    {
        $sageAcct = Credentials::leftJoin('credentials_site', function($join){
            $join->on("credentials.id", "=", "credentials_site.credentials_id");
        })->where("credentials_site.site_id", $site_id)->where("env", env("APP_ENV"))->first();
        return $sageAcct->vendorName . '-' . $trans_id;
    }

    public static function vendorTxCodeRefund($site_id, $transID){
        $sageAcct = Credentials::leftJoin('credentials_site', function($join){
            $join->on("credentials.id", "=", "credentials_site.credentials_id");
        })->where("credentials_site.site_id", $site_id)->where("env", env("APP_ENV"))->first();
        return $sageAcct->vendorName . '-' . $transID;
    }

    /**
     * To be used when accessing the payment gateway, to restore all sessions
     */
    public static function deleteAllSessions()
    {
        Session::forget("currentBasket");
        Session::forget("accountType");
        Session::forget("curtransid");
        Session::forget("versionTwo");
        Session::forget("bankAuthorisationCode");
        Session::forget("transactionResponse");
        Session::forget("transaction_id");
        Session::put("justLanded", true);
        Session::forget("EndOfEveryThing");
        Session::forget("Enter3DSecureTime");
        Session::forget("Enter3DSecureTimeEnd");
    }

    /*
     * Show each stage execution time
     * shown only if flat "test" found on the url
     */
    public static function showStageExecutionTimes()
    {
        $listSteps = "";
        if(Session::has("test")) {
            $listSteps = "<h4>Execution times, only for test purposes: </h4><h6>Open the console to see aJax/javascript execution times</h6><ol class='text-left'>"
                . "<li> Process start: " . (Session::get("StartProcessTime") ?? "Not provided") . "</li>"
                . "<li> Card Identifier request: " . (Session::get("EnterCardIdentifierTime") ?? "Not provided") . "</li>"
                . "<li> Card Identifier response: " . (Session::get("EnterCardIdentifierTimeEnd") ?? "Not provided") . "</li>"
                . "<li> Transaction creation request: " . (Session::get("EnterCreateTransactionTime") ?? "Not provided") . "</li>"
                . "<li> Transaction creation response: " . (Session::get("EnterCreateTransactionTimeEnd") ?? "Not provided") . "</li>"
                . "<li> 3D Secure request: " . (Session::get("Enter3DSecureTime") ?? "Not provided") . "</li>"
                . "<li> 3D Secure response: " . (Session::get("Enter3DSecureTimeEnd") ?? "Not provided") . "</li>"
                . "<li> Process end: " . (Session::get("EndOfEveryThing") ?? "Not provided") . "</li></ol>";
        }
        return $listSteps;
    }

    /**
     * Get latest transaction id to be used to create new VendorTxCode in a new transaction
     * @return mixed
     */
    public static function getLatestId()
    {
        return Transaction::where("VendorTxCode", "!=", "")->orderBy("transID", "DESC")->first()->transID;
    }

    /**
     * Store in Active Log when a payment has been processed
     * @param string $status
     * @param string $event
     * @param string $comment
     * @param int $tranID
     */
    public static function storeOnActiveLog($status = "OK", $event = "", $comment = "", $tranID = 0, $client_id = NULL)
    {
        $site_id = 0;
        if ($data = unserialize(Session::get('currentBasket'))) {
            $site_id = $data["site_id"] ?? 0;
        }

        if ($status == "OK") {
            $event = "Successful Payment";
            $comment = "The Payment has been succesfully processed";
        }

        $activeLog = new ActiveLog();
        $activeLog->log_id = (ActiveLog::orderBy("log_id", "DESC")->first()->log_id + 1);
        $activeLog->event = $event;
        $activeLog->comment = $comment;
        $activeLog->client_id = $client_id;
        $activeLog->trans_id = $tranID;
        $activeLog->site_id = $site_id;
        $activeLog->date = date('Y-m-d H:i:s');
        $activeLog->save();
    }


    /**
     * check AVSCV2 value and store it
     */
    static public function checkAvsCvcCheck($transactionDetails = NULL)
    {
        $avsCvc = "";
        if(!empty($transactionDetails)) {
            if (isset($transactionDetails["avsCvcCheck"])) {
                $transDetailsResultAvsCvcCheck = $transactionDetails["avsCvcCheck"];
                if (array_key_exists("status", $transDetailsResultAvsCvcCheck)) {
                    if ($transDetailsResultAvsCvcCheck["status"] !== "AllMatched") {
                        $i = 0;

                        // go through each AVSCV2 error details
                        foreach ($transDetailsResultAvsCvcCheck as $key => $avs) {
                            $i++;
                            if (SagepayConstants::getNotMatched() == $avs) {
                                $avsCvc .= strtoupper($key) . " " . SagepayConstants::getNotMatchedClientView();
                                if ($i < count($transDetailsResultAvsCvcCheck)) {
                                    $avsCvc .= ", ";
                                }
                            }
                        }
                    } else {
                        $avsCvc = SagepayConstants::getAllMatch();
                    }
                }
            }
        }
        return $avsCvc;
    }


    /**
     * Check if transaction has already been registered, if so, redirect to error page
     * $site_job_number
     */
    static public function itsAlreadyBeenPayd($site_job_number = NULL, $redirectSummary = false, $request = NULL)
    {
        if(Transaction::where("site_job_number", $site_job_number)->count() > 0){
            if($redirectSummary && $redirectSummary){
                return true;
            } else {
                abort("403", "It appears that this order has already been payed.");
            }
        }
    }

    /**
     * @param $lettersCurrency
     * @return string
     */
    static function currencySymbol($lettersCurrency)
    {
        switch ($lettersCurrency){
            case "USD":
                $symbol = "$";
                break;
            case "EUR":
                $symbol = "€";
                break;
            default:
                $symbol = "£";
        }
        return $symbol;

    }

    /**
     * @param $amount
     * @param $currency
     * @return float|int
     */
    static function convertIntoGBP($amount = null, $currency = "EUR", $is_post_vat = FALSE)
    {
        $exchangeRate = 1;
        if($exchangeRateAmount = ExchangeRate::where("currency", $currency)->first()){
            $exchangeRate = $exchangeRateAmount->amount;
        }
        if($is_post_vat && $currency == "GBP"){
            return $amount;
        }
        return $amount * $exchangeRate;
    }

    /**
     * return a list of the transactions refunded or voided otherwise only the first on the list or the one matching
     * the amount given
     * @param $transaction
     * @return false
     */
    static function hasBeenRefundedOrRefundVoided($transaction = NULL, $list = false, $tx_Type = "REFUND", $amount  = NULL)
    {
        if($transaction !== NULL && ($tx_Type == "REFUND"  || $tx_Type == "REFUND VOID" || $tx_Type = "BOTH")){
            $where = [
                "site_client_id" => $transaction->site_client_id,
                "company_id" => $transaction->company_id,
                "client_id" => $transaction->client_id,
                "site_id" => $transaction->site_id,
                "status" => "OK"
            ];

            if($amount !== NULL && is_numeric($amount)){
                $where["amount"] = $amount;
            }
            $transList = Transaction::where($where);

            if($tx_Type == "BOTH"){
                $transList->whereIn("Tx_Type", ["REFUND", "REFUND VOID"]);
            } else {
                $transList->where(["Tx_Type" => $tx_Type]);
            }

            if($list){
                $transList = $transList->get();
            } else {
                $transList = $transList->first();
            }
            return $transList;
        }
        return false;
    }

    /**
     * Get the link to the parent transaction (for example if it's refund or voided
     * transaction and wants to get to the payment one)
     * @return false|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getLinkToMainTransactionAttribute()
    {
        if($this->Tx_Type == "REFUND" || $this->Tx_Type == "REFUND VOID"){
            $trans =  Transaction::where(
                [
                    "site_client_id" => $this->site_client_id,
                    "company_id" => $this->company_id,
                    "client_id" => $this->client_id,
                    "site_id" => $this->site_id,
                    "status" => "OK"
                ])->whereIn("Tx_Type", array("MANUAL", "PAYMENT"))->first();
            if(isset($trans->transID)){
                return url('/transactions/' . $trans->transID);
            }
        }
        return false;
    }

    /**
     * Return a payment is voided when date and amount of the payment and refund transactions match
     * @return bool
     */
    public function getVoidedTransAttribute()
    {
        if($this->Tx_Type == "REFUND" || $this->Tx_Type == "REFUND VOID"){
            $trans =  Transaction::where(
                [
                    "site_client_id" => $this->site_client_id,
                    "company_id" => $this->company_id,
                    "client_id" => $this->client_id,
                    "site_id" => $this->site_id,
                    "status" => "OK"
                ])->whereIn("Tx_Type", array("MANUAL", "PAYMENT"))->first();

            $dateRefund = Carbon::createFromFormat('Y-m-d H:i:s', $this->date)->format("Y-m-d");
            $dateParentTransaction = Carbon::createFromFormat('Y-m-d H:i:s', $trans->date)->format("Y-m-d");
            if($dateRefund == $dateParentTransaction && $trans->amount == $this->amount){
                return true;
            }
        }
        return false;
    }

    /**
     * Check if payment has been processed today
     * @return bool
     */
    public function getPaymentSubmittedTodayAttribute()
    {
        $todayPayment = Carbon::createFromFormat('Y-m-d H:i:s', $this->date)->format('Y-m-d');
        if($todayPayment == Carbon::now()->format('Y-m-d')){
            return true;
        }
        return false;
    }

    /**
     * Check If Refunded Fully
     * the amount given
     * @param $transaction
     * @return false
     */
    static function hasBeenRefundedFully($transaction = NULL, $amount  = NULL)
    {
        if($transaction !== NULL){
            $where = [
                "site_client_id" => $transaction->site_client_id,
                "company_id" => $transaction->company_id,
                "client_id" => $transaction->client_id,
                "site_id" => $transaction->site_id,
                "status" => "OK"
            ];

            // get all refunded transactions and transaction and sum the refund and substract refund void
            $transList = Transaction::where($where)->whereIn("Tx_Type", ["REFUND", "REFUND VOID"])->get();

            $totalRefunded = 0;
            foreach($transList AS $trans){
                if($trans->Tx_Type == "REFUND"){
                    $totalRefunded += $trans->amount;
                } else if($trans->Tx_Type == "REFUND VOID"){
                    $totalRefunded -= $trans->amount;
                }
            }

            if($transaction->amount == $totalRefunded){
                return true;
            }
        }
        return false;
    }

    /**
     * Check if it's an american express card
     */
    public function isAmex($cc)
    {
        if(strpos($cc, "34") > -1 || strpos($cc, "37") > -1){
            if(strlen($cc) == 15){
                return true;
            }
        }
        return false;
    }
}
