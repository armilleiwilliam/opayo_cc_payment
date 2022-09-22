<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Transaction;
use App\Companies;
use App\Site;
use App\CompanySites;
use Illuminate\Support\Facades\Auth;

trait TransactionTraits
{
    protected function addPageLog($route, $user_id, $remote_ip, $remote_host)
    {
        $values = array(
            'route' => $route,
            'user_id' => $user_id,
            'ip' => $remote_ip,
            'hostname' => $remote_host
        );
        DB::table('access_log')->insert($values);
    }

    protected function queryLimit(Request $request)
    {
        $this->results = array();
        $this->totals = array();
        //dump($request);

        // setup search values
        foreach ($this->variableArray as $field) {
            $sessionValue = $request->session()->get($field);
            $this->$field = false;

            switch (true) {
                case (empty($sessionValue)):
                    if (in_array($field, ['dateStart','dateEnd'])) {
                        $this->$field = date('Y-m-d');
                    }
                    break;
                default:
                    if ($sessionValue != 'All') {
                        $this->$field = $sessionValue;
                    }
                    break;
            }
        }

        // return select arrays for form dropdowns
        $this->sites = Site::getSelect($this->companyID);
        $this->types_op = [0 => 'All', 'PAYMENT' => 'PAYMENT', 'REFUND' =>'REFUND', 'MANUAL' => 'MANUAL', 'REFUND VOID' => 'REFUND VOID'];
        $this->companies = Companies::getSelect();
        $this->PAY_Types = Transaction::getSelect('PAY_Type', $this->dateStart, $this->dateEnd, $this->companyID);
        $this->processBy = Transaction::getSelect('process_by', $this->dateStart, $this->dateEnd, $this->companyID);
        $this->assignedTo = Transaction::getSelect('assigned_to', $this->dateStart, $this->dateEnd, $this->companyID);

        return $this;
    }

    public function getData() {
        //dump($this);
        $dateStart = $this->dateStart;
        $dateEnd = $this->dateEnd;

        $includeCA = (empty($this->includeCA)) ? '0' : '1';
        $excludeDI = (empty($this->excludeDI)) ? '0' : '1';
        $companyID = ($this->companyID) ? $this->companyID : false;
        $siteID = ($this->siteID) ? $this->siteID : false;
        $type = ($this->type) ? $this->type : false;
        $txType = ($this->Tx_Type) ? $this->Tx_Type : false;
        $status = ($this->status) ? $this->status : false;
        $processBy = ($this->process_by) ? $this->process_by : false;
        $assignedTo = ($this->assigned_to) ? $this->assigned_to : false;
        $siteClient = ($this->siteClient) ? $this->siteClient : false;
        $siteJob = ($this->siteJob) ? $this->siteJob : false;

        $query = Transaction::whereDate('date', '>=', $dateStart)
            ->whereDate('date', '<=', $dateEnd);

        if ($type) {
            $query->where('PAY_Type', $type);
        }
        if ($status) {
            if($status !== 'ALL') {
                $query->where('status', $status);
            }
        } else {
            $query->where('status', 'OK');
        }
        if ($siteClient) {
            $query->where('site_client_id', $siteClient);
        }
        if ($siteJob) {
            $query->where('site_job_number', $siteJob);
        }
        if ($processBy) {
            $query->where('process_by', $processBy);
        }
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($txType) {
            $query->where('Tx_Type', $txType);
        }

        $caSites = Site::CA_SITES;

        // exclude ELAWD
        if ($excludeDI) {
            unset($caSites[5]);
        }

        // Filter ELAW SITES
        if ($includeCA) {

            // include all CA webistes
            $query->whereIn('site_id', $caSites);
        } else {

            // filter all the others without CA websites
            if ($companyID) {
                $query->where('company_id', $companyID);
            }

            $siteIDs = [];
            if ($companyID == Companies::ELAW && !$siteID) {
                $siteIDs[] = Site::CTO;
            }

            if ($siteID) {
                if ($siteID == Site::PARTNERS) {
                    $siteIDs = Site::PARTNER_SITES;
                } else {
                    $siteIDs[] = $siteID;
                }
            } else {
                $query->whereNotIn('site_id', Site::CA_SITES);
            }

            if(!empty($siteIDs)) {
                $query->whereIn('site_id', $siteIDs);
            }
        }

        $query->with('client');
        $query->with('site');
        $query->with('company');
        $result = $query->get()->filter(function($transaction){
            if(Auth::user()->can('view', $transaction)) {
                return $transaction;
            }
        });
        return $result;
    }

    /**
     * Return SUM data for given query limits
     */
    public function getTotals($results)
    {
        $data = [
            'total_amount' => 0,
            'total_pre_vat' => 0,
            'total_post_vat' => 0,
            'total_vat' => 0
        ];

        foreach ($results as $transaction) {
            if ($transaction->status == 'OK' && $transaction->Tx_Type == 'REFUND') {
                $data['total_amount'] -= $transaction->amount;
                $data['total_pre_vat'] -= $transaction->pre_vat;
                $data['total_post_vat'] -= $transaction->post_vat;
                $data['total_vat'] -= $transaction->vat_amount;
            }
            if ($transaction->status == 'OK' && in_array($transaction->Tx_Type, ['MANUAL', 'PAYMENT', 'REFUND VOID'])) {
                $data['total_amount'] += $transaction->amount;
                $data['total_pre_vat'] += $transaction->pre_vat;
                $data['total_post_vat'] += $transaction->post_vat;
                $data['total_vat'] += $transaction->vat_amount;
            }
        }

        return $data;
    }

    public function saveSession(Request $request)
    {
        $formValues = $request->all();

        // save to session
        if ($formValues) {
            foreach ($formValues as $key => $value) {
                if ($value == 'All') {
                    $value = false;
                }
                $request->session()->put($key, $value);
            }
            if(empty($formValues["includeCA"])){
                $request->session()->forget('includeCA');
            }
            if(empty($formValues["excludeDI"])){
                $request->session()->forget('excludeDI');
            }
        }
        return;
    }

    public function clearSession(Request $request)
    {
        // empty session
        foreach ($this->variableArray as $item) {
            $request->session()->forget($item);
        }
        return;
    }

}


?>
