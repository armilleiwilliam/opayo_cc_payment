<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Transaction;
use App\Companies;
use App\Site;

trait Transactions
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
        $this->dataArray['dateStart'] = (empty($request->dateStart)) ? '2019-06-01' : $request->dateStart;
        $this->dataArray['dateEnd'] = (empty($request->dateEnd)) ? date('Y-m-d') : $request->dateEnd;
        $this->dataArray['companyID'] = (empty($request->companyID)) ? 'All' : $request->companyID;
        $this->dataArray['siteID'] = (empty($request->siteID)) ? 'All' : $request->siteID;
        $this->dataArray['status'] = (empty($request->status)) ? 'OK' : $request->status;
        $this->dataArray['type'] = (empty($request->type)) ? 'All' : $request->type;
        $this->dataArray['includeCA'] = (empty($request->includeCA)) ? false : true;

        $request->session()->put('dateStart', $this->dataArray['dateStart']);
        $request->session()->put('dateEnd', $this->dataArray['dateEnd']);
        $request->session()->put('companyID', $this->dataArray['companyID']);
        $request->session()->put('siteID', $this->dataArray['siteID']);
        $request->session()->put('status', $this->dataArray['status']);
        $request->session()->put('type', $this->dataArray['type']);
        $request->session()->put('includeCA', $this->dataArray['includeCA']);

        // return select arrays for form dropdowns
        $this->dataArray['sites'] = Site::getSelect();
        $this->dataArray['companies'] = Companies::getSelect();
        $this->dataArray['PAY_Types'] = Transaction::getSelect('PAY_Types');
    		$this->dataArray['process_by'] = Transaction::getSelect('process_by');
    		$this->dataArray['assigned_to'] = Transaction::getSelect('assigned_to');

        return $this->dataArray;
    }



    protected function getData(Request $request) {
        $dateStart = session()->get('dateStart');
        $dateEnd = session()->get('dateEnd');
        $companyID = session()->get('companyID');
        $siteID = session()->get('siteID');
        $status = session()->get('status');
        $type = session()->get('type');
        $includeCA = session()->get('includeCA');

        if (ctype_digit($companyID)) {
            $companySearch = '"="';
            $companySearchValue = $companyID;
        } else {
            $companySearch =   '"!="';
            $companySearchValue = '';
        }

        if (ctype_digit($siteID)) {
            $siteSearch =  '"="';
            $siteSearchValue = $siteID;
        } else {
            $siteSearch = '"!="';
            $siteSearchValue = '';
        }

        if ($status != 'All') {
            $statusSearch = '"="';
            $statusSearchValue = $status;
        } else {
            $statusSearch = '"!="';
            $statusSearchValue = '';
        }

        if ($type != 'All') {
            $typeSearch = '"="';
            $typeSearchValue = $type;
        } else {
            $typeSearch = '"!="';
            $typeSearchValue = '';
        }
        //$companySearch = (ctype_digit($companyID)) ? ['transaction.company_id', '=', $companyID] : ['1','=','1'];

        $data = DB::table('transaction')
          ->join('companies','transaction.company_id','companies.company_id')
          ->join('site','transaction.site_id', 'site.site_id')
          ->join('client','transaction.client_id', 'client.client_id')
          ->select(
              'transaction.*',
              'companies.company_name',
              'companies.company_short_name',
              'site.site_short_title',
              'client.full_name'
          )
          ->whereBetween('transaction.date', [$dateStart, $dateEnd])
          ->where('transaction.company_id', $companySearch, $companySearchValue)
          ->where('transaction.site_id', $siteSearch, $siteSearchValue)
          ->where('transaction.PAY_Type', $typeSearch, $typeSearchValue)
          ->where('transaction.status', $statusSearch, $statusSearchValue)
          ->get();

        return $data;
    }


}


?>
