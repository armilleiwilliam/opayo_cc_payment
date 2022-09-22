<?php
/**
 * Created by PhpStorm.
 * User: William Armillei
 * Date: 09/09/21
 * Time: 11:18
 */

namespace App\Http\Controllers;

use App\SagePay;
use Illuminate\Support\Facades\App;
use App\RawResponse;
use App\Services\Sagepay\SageException;
use Illuminate\Http\Request;
use App\Services\Sagepay\Model\SagepayConstants;
use App\Traits\SanitisationTrait;
use Illuminate\Support\Facades\Session;
use App\PaymentRequests;
use App\Transaction;
use App\RefundHistory;
use App\ActiveLog;
use App\Site;
use App\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SagePayController extends Controller
{
    use SanitisationTrait;

    private $currentBasket;
    private $transaction = NULL;
    private $telephonePaymentVersion = false;

    public function index(Request $request)
    {
        // if page refreshed delete all the sessions stored and start over
        Transaction::deleteAllSessions();

        // check if token provided
        if (!isset($request->token)) {
            return view('errors.notoken');
        }

        // check if the token given exists
        $transactionRequest = PaymentRequests::where('token', '=', $request->token)->first();

        if (!$transactionRequest) {
            return view('errors.notoken');
        } else {
            $this->buildBasket($transactionRequest);

            // save site ID to session for vendor lookup
            Session::put("siteId", $transactionRequest->site_id);
            $threeDsEnabled = $transactionRequest->securepay_enabled ? "E" : "M";
            Session::put("accountType", $threeDsEnabled);

            // check if time execution tests are requested
            if (request()->has("test")) {
                Session::put("test", true);
            } else {
                Session::forget("test");
            }
        }

        // check account type and set it up for enabling telephone payment or 3Ds secure auth accordingly
        if (isset($request->accounttype)) {
            if (in_array($request->accounttype, array("E", "M"))) {
                Session::put("accountType", $request->accounttype);
                $this->telephonePaymentVersion = true;
            }
        }

        // start transaction
        $transactionId = $this->storeTransactionToDb();
        Session::put("transaction_id", $transactionId);
        $listCountries = SagePay::listOfCountries();

        // create a Merchant ID for Opayo/SagePay credit card identifier
        $merchantIdService = app()->make('Services\SagePayMerchantIdService');
        $sessionMerchantKey = $merchantIdService->handle([]);
        Session::put('merchantSessionId', $sessionMerchantKey);

        if (isset($transactionRequest)) {
            $siteData = Site::where('site_id', '=', $transactionRequest["site_id"])->first();
        };

        App::setLocale(strtolower($siteData->lang ?? 'EN'));
        Session::put("locale", App::getLocale());

        // check view mode
        $modeViewOnly = null;
        if($request->has('mode') &&$request->query("mode") == "view"){
            $modeViewOnly = true;
        }

        return view('forms.sagepayform', ['transaction' => $transactionRequest, 'telephone_payment_version' => $this->telephonePaymentVersion, 'site' => $siteData, 'listCountries' => $listCountries, 'viewOnly' => $modeViewOnly]);
    }

    /**
     * Create sessionMerchantKey to be used to pull up credit card form with Javascript
     * in the iframe
     */
    public function sagePayment()
    {
        // Testing time mark
        Session::put("StartProcessTime", Carbon::now()->format("h:i:s"));
        try {
            //check if transaction has been payed already
            if ($this->paymentRequestStatus(Request()->token)) {
                return response()->json([
                    'iframe' => view('forms.transactionpayedalert')->render()]);
            }

            // start transaction
            $data = $this->sanitizeInputArray(Request()->all());
            Session::put('currentBasket', serialize(Request()->all()));

            $transactionId = $this->storeTransactionToDb(NULL, "NOTPROCESSED", $data);
            Session::put("transaction_id", $transactionId);

            $this->buildBasket($data, $transactionId);

            $activeLog = new ActiveLog();
            $activeLog->event = "Client Redirect";
            $activeLog->comment = "Client has successfully submitted their details";
            $activeLog->client_id = Session::get("client_id");
            $activeLog->trans_id = Session::get("transaction_id") ?? 0;
            $activeLog->site_id = $data["site_id"];
            $activeLog->date = Carbon::now()->format('Y-m-d H:i:s');
            $activeLog->save();
            Session::put("submittedDetailsLogId", $activeLog->log_id);

            // create card Identifier value
            $cardIdentifier = app()->make('Services\SagePayCardIdentifierService');
            $cardIdentifierKey = $cardIdentifier->handle($data);

            if (!array_key_exists("cardIdentifier", $cardIdentifierKey)) {
                // If any error triggered
                $sagePayExceptionService = app()->make('Services\SagePayExceptionService');
                return $sagePayExceptionService->handleError($cardIdentifierKey);
            }

            Session::put('cardIdentifier', $cardIdentifierKey["cardIdentifier"]);

            // Start transaction with Secure 3D Auth.
            $transSagePay = app()->make('Services\SagePayTransactionService');
            $response = $transSagePay->handle($data);

            return $this->handleSageResponse($response);

        } catch (SageException $e) {
            if ($e->getTriggerRecord()) {
                report($e);
            }

            return $e->render();
        }
    }

    /**
     * Filter transaction responses: Challenge the result (3D v2), fall back to 3D v1 auth., transaction succesful or error.
     * @param $response
     */
    public function handleSageResponse($response)
    {
        // set 3Dv2 to true to be used when storing the transaction
        Session::put('versionTwo', true);
        // check if 3D v2 "challenge" is available with the CardHolder and CardIssuer
        if (is_array($response)) {
            if (isset($response["statusCode"]) && $response["statusCode"] === SagepayConstants::getTransaction3dRedirectCodeV2()) {
                return $this->secureRedirectIframe($response);
            }
        }

        // In case 3D v2 challenge not available check if 3D v1 is available instead
        if (is_array($response)) {
            if (isset($response["statusCode"]) && $response["statusCode"] === SagepayConstants::getTransaction3dRedirectCode()) {
                Session::put('versionTwo', false);
                return $this->secureRedirectIframe($response);
            }
        }

        // Testing time mark
        Session::put("EndOfEveryThing", Carbon::now()->format("h:i:s"));

        // If none of the above transactions type is triggered access to check out
        if (isset($response["3DSecure"]) && $response["statusCode"] === SagepayConstants::getAuthenticated()) {
            $accountType = Session::get("accountType");
            $this->transactionDetails = $response["statusCode"] . ": " . $response["statusDetail"];

            // both Ok and AttemptOnly transactions are treated as successful
            if ($response["3DSecure"]["status"] === SagepayConstants::get3DAuthenticated()
                || in_array($accountType, array("M", "T"))
                || $response["3DSecure"]["status"] === SagepayConstants::getAttemptOnly()) {
                return response()->json([
                    'redirect' => view('forms.checkoutredirect', ['success' => true])->render()
                ]);
            }
        }

        // If any error triggered
        $sagePayExceptionService = app()->make('Services\SagePayExceptionService');
        $sagePayExceptionService->handleError($response);
    }

    /**
     * Return SagePay iframe for 3D v2 or 3D v1 authentication
     * @param $sageResponse
     * @param bool $versionTwo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    private function secureRedirectIframe($sageResponse)
    {
        Session::put('secure3dData', $sageResponse);

        return response()->json([
            'iframe' => view('forms.3dsecureiframe')->render()
        ]);
    }

    /**
     * If the authentication is "challenged" (3D auth. v2) or NOT ENROLLED and fall back to previous version (3D auth. v1)
     * use the link provided by SagePay to show the gateway/platform to prove a further authentication with the password
     * provided by the bank. This process is called two-factors authentication.
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function return3dSecureIframe()
    {
        $secureData = Session::get('secure3dData');
        $secureData['curmerchid'] = Session::get('curmerchid');
        $secureData['curcardid'] = Session::get('curcardid');
        $secureData['acsTransID'] = Session::get('acsTransID');
        $secureData['threeD2version'] = Session::get('versionTwo');
        Session::put('curtransid', $secureData['transactionId']);

        return view('forms.3dsecure', [
            'secureData' => $secureData
        ]);
    }

    /**
     * Process the 3D Secure authentication results
     */
    public function sagepayPost3dCheckout()
    {
        try {
            $request = $this->sanitizeInputArray(request()->all());

            $payload = [
                'siteId' => Session::get('siteId'),
                'curMerchId' => Session::get('curmerchid'),
                'curCardId' => Session::get('curcardid'),
                'curTransId' => Session::get('curtransid'),
                'cRes' => $request['cres'] ?? NULL,
                'paRes' => $request['PaRes'] ?? NULL,
            ];

            $sagePay3dSecureService = app()->make('Services\Sage3DSecureService');
            $response = $sagePay3dSecureService->handle($payload);

            $sagePayRetrieveTransactionService = app()->make('Services\SagePayRetrieveTransactionService');
            $response = $sagePayRetrieveTransactionService->handle(Session::get('curtransid'));

            $sagePayExceptionService = app()->make('Services\SagePayExceptionService');
            $errorResponse = $sagePayExceptionService->handleError($response, true);

            return view('forms.checkoutredirect', ['responseStatus' => $errorResponse['message']]);

        } catch (SageException $e) {
            if ($e->getTriggerRecord()) {
                report($e);
            }
            return $e->render();
        }
    }

    private function paymentRequestStatus($token)
    {
        $paymentRequest = PaymentRequests::where("token", "=", $token)->first();

        if (!$paymentRequest) {
            return true;
        } else {
            return false;
        }
    }

    public function storeTransactionToDb($id = NULL, $status = "NOTPROCESSED", $further_form_data = NULL, $pay_type = "CC")
    {
        // unserialize basket for later usage
        $data = unserialize(Session::get('currentBasket'));

        // start transaction
        $txType = Session::get("accountType");

        $now = Carbon::now();
        $mysqlDate = $now->format('Y-m-d H:i:s');
        $transDetails = "";

        // Recalculate pre_vat and vat_amount according to amount given.
        // The following is to fix a bug very odd, basically for a reason we could not find out FDR website very few times
        // miscalculates the amounts, we believe, when creating the payment request. We tried to replicate the issue but
        // nothing came up (we used same amounts and same clients of the issue reported). The only way to fix it is to
        // recalculate the vat and pre vat again and not relying on the ones provided by the payment request
        $pre_vat = $data["pre_vat"];
        $vat_amount = $data["vat_amount"];
        if((float)$data["vat_amount"] !== 0.00 && $data["vat_amount"] !== null && (float)$data["vat_amount"] !== 0 && $data["currency"] == "GBP"){
            $minusVatPercentage = 100 / 120;
            $pre_vat = round($data["amount"] * $minusVatPercentage, 2);
            $vat_amount = $data["amount"] - $pre_vat;
        }

        // Store transaction
        if (!$transaction = Transaction::find($id)) {
            $transaction = new Transaction();
            $transaction->invoice_no = $data["invoice_no"] ?? 0;
            $transaction->process_by = $data["processed_by"];
            $transaction->assigned_to = $data["assigned_to"];
            $transaction->Pay_Type = $pay_type;
            $transaction->amount = $data["amount"];
            $transaction->pre_vat = Transaction::convertIntoGBP($pre_vat, $data["currency"]);
            $transaction->vat_amount = Transaction::convertIntoGBP($vat_amount, $data["currency"]);
            $transaction->post_vat = Transaction::convertIntoGBP($data["amount"], $data["currency"], true);
            $transaction->non_vat_amount =  $data["non_vat_amount"];
            $transaction->description = $data["package"];
            $transaction->company_id = $data["company_id"];
            $transaction->site_client_id = $data["site_client_id"];
            $transaction->site_id = $data["site_id"];
            $transaction->site_job_number = $data["site_job_number"];
            $transaction->customerRef = 0;
            $transaction->currency = $data["currency"];
            $transaction->vat_number = $further_form_data["vat_number"] ?? "";
            $transaction->discount_price = 0;
            $transaction->discount_description = "None";
            $transaction->Tx_Type = $txType == "M" ? "MANUAL" : "PAYMENT";
            $transaction->date = $mysqlDate;
            $transaction->ip_address = $_SERVER["REMOTE_ADDR"] ?? NULL;
            $transDetails = 'Transaction has not been processed yet';

        } else {
            $transaction->VendorTxCode = Transaction::vendorTXcode($transaction->site_id, $transaction->transID);

            $transaction->amount = $data["amount"];
            $transaction->non_vat_amount = $data["non_vat_amount"];
            $transaction->pre_vat = Transaction::convertIntoGBP($pre_vat, $data["currency"]);
            $transaction->vat_amount = Transaction::convertIntoGBP($vat_amount, $data["currency"]);
            $transaction->post_vat = Transaction::convertIntoGBP($data["amount"], $data["currency"], true);
            $pay_type = $data["isAmex"] ? "AMEX" : $pay_type;
            $transaction->Pay_Type = $pay_type;

            // SagePay transaction id stored in VSPTxID column
            if ($transactionSagePayId = Session::get('curtransid')) {
                $transaction->VSPTxID = $transactionSagePayId;
            }

            if ($bankAuthorisationCode = Session::get("bankAuthorisationCode")) {
                $transaction->TxAuthNo = $bankAuthorisationCode;
            }

            // store which 3D version has been used during transaction
            $threeDv = SagepayConstants::getIs3DvTwo();
            $version3D = Session::get("versionTwo");
            if ($version3D == false) {
                $threeDv = SagepayConstants::getIs3DvOne();
            }

            // Store SagePay response details
            if ($transactionDetails = Session::get("transactionResponse")) {

                $transactionDetails = json_decode($transactionDetails, true);
                if (isset($transactionDetails["statusCode"]) && isset($transactionDetails["statusDetail"])) {
                    $transDetails = $transactionDetails["statusCode"] . ": " . $transactionDetails["statusDetail"] . " " . $threeDv;
                }
                $transaction->AVSCV2 = Transaction::checkAvsCvcCheck($transactionDetails);

                if ($transactionDetails !== NULL) {
                    // store response from SagePay in different formats
                    RawResponse::storeResponse($transaction->transID);
                }
            }
        }

        // get gateway client ID and client creation
        $clientDetails = Client::clientAndVendorDataRetrieve($data["site_id"], $pay_type);
        $transaction->client_id = $clientDetails["client_id"];
        $transaction->client_account = $clientDetails["client_account"];
        $transaction->paid = $status == "OK" && $pay_type == "CC" ? '1' : '0';
        $transaction->transDetails = $transDetails;
        $transaction->status = $status;
        $transaction->save();

        // update just created log entry with the pmissing TransId
        if (!empty($clientDetails["log_id"])) {
            ActiveLog::where("log_id", "=", $clientDetails["log_id"])->update(["trans_id" => $transaction->transID]);
        }

        if ($submittedDetailsLogId = Session::get("submittedDetailsLogId")) {
            ActiveLog::where("log_id", "=", $submittedDetailsLogId)->update(["trans_id" => $transaction->transID]);
        }

        // update the Active_Log's trans_id column of just landed entry
        if (!empty($clientDetails["user_landed_active_log_id"])) {
            ActiveLog::where("log_id", "=", $clientDetails["user_landed_active_log_id"])->update(["trans_id" => $transaction->transID]);
        }

        if ($transaction->status == "OK") {
            PaymentRequests::where("id", $data["id_reference"])->delete();
            Transaction::storeOnActiveLog("OK", "Successful Payment", "The payment has been successfully processed", $transaction->transID, $transaction->client_id);
        }
        $transaction->save();

        return $transaction->transID;
    }

    /**
     * Show checkout page
     */
    public function checkOutShow()
    {

        // only for testing purposes the execution times are shown on checkout page,
        // to trigger it add a flag "test" on the url
        $listSteps = Transaction::showStageExecutionTimes();

        if ($checkoutValues = unserialize(Session::get('currentBasket'))) {

            App::setLocale(strtolower(Session::get("locale") ?? 'EN'));
            $transactionId = Session::get("transaction_id");
            $this->storeTransactionToDb($transactionId, "OK");
            $siteData = Site::where('site_id', '=', $checkoutValues["site_id"])->first();

            /*
             * $siteData = Site::where('site_id', '=', $checkoutValues["site_id"])->leftJoin("company_sites", function($join){
                $join->on("site_id", "=", "");
            })->first();
             */
            $currencySymbol = SagePay::currencySymbol($checkoutValues["currency"]);
            $redirectPageBasic = (env("BETA_PREFIX") ? "http://" . env("BETA_PREFIX") : "https://") . $checkoutValues["source"];

            // redirect to original Website if it's secure_pay = 1 and a url_from was given (meaning it's website user), or if it's a FDR employee
            if (!empty($checkoutValues["url_from"])){
                if($checkoutValues["securepay_enabled"] || strpos($checkoutValues["url_from"], "staff.fields-data-recovery") > -1){
                    return redirect(html_entity_decode($checkoutValues["url_from"]));
                }
            }

            if (!empty($checkoutValues["source"]) && in_array($checkoutValues["source"], config("websites_parameters"))) {
                if ($checkoutValues["securepay_enabled"]) {
                    // payment link
                    return view('pages.order-checkout', ['checkoutValues' => $checkoutValues, 'site' => $siteData, "listSteps" => $listSteps, "currency" => $currencySymbol]);
                } else {
                    // phone payment
                    return view('pages.order-checkout', ['checkoutValues' => $checkoutValues, 'site' => $siteData, 'source' => $redirectPageBasic . "/client/" . $checkoutValues["site_client_id"], "listSteps" => $listSteps, "currency" => $currencySymbol]);
                }
            } else {
                // web payment
                return view('pages.order-checkout', ['checkoutValues' => $checkoutValues, 'source' => $redirectPageBasic, 'site' => $siteData, "listSteps" => $listSteps, "currency" => $currencySymbol]);
            }

        } else {
            return view('errors.notoken');
        }
    }

    /**
     * All the transaction parameters retrieved from the payment_request table are stored in a currentBasket session
     * to be used whenever it is necessary and eventually to be stored in transaction table when checkout completed.
     * Also input values from payment form are stored with this method.
     * @param $transactionRequest
     */
    public function buildBasket($transactionRequest, $trans_id = NULL)
    {
        if ($trans_id !== NULL) {
            // set Unique VendorTxtCode
            $transactionRequest["VendorTxCode"] = Transaction::vendorTXcode($transactionRequest["site_id"], $trans_id);
        }

        if(is_array($transactionRequest)) {
            $transactionRequest["isAmex"] = false;
            if (isset($transactionRequest["cc_number"])) {
                $transactionRequest["isAmex"] = Transaction::isAmex($transactionRequest["cc_number"]);
            }
        }

        $this->currentBasket = $transactionRequest;
        PaymentRequests::updateData($this->currentBasket);
        Session::put('currentBasket', serialize($this->currentBasket));
    }


    /**
     * Refunds
     */
    public function sageRefunds(Request $request)
    {
        /** @var User $user */
        $this->user = Auth::user();
        /** @var int $chargeback */
        $chargeback = 0;
        /** @var string trans_type */
        $trans_type = '';
        /** @var string refunded_success_message */
        $refunded_success_message = "refunded";

        if (isset($request->transactionDetail)) {
            if (!isset($request->edit_amount) && (int)$request->edit_amount <= 0) {
                return redirect()->back()->with('error', ' We can not refund ' . (int)$request->edit_amount . 'GBP');
            }
            $refund = json_decode($request->transactionDetail);

            if ($refund->status == "NOTPROCESSED"){
                if ($refund->status == "NOTPROCESSED") {
                    $error = 'Transaction with id ' . $refund->transID . ' have a status: ' . $refund->status . ' we can not process to the refund';
                }
                return redirect()->back()->with('error', $error);
            }

            Session::put("siteId", $refund->site_id);
            $now = Carbon::now();
            $mysqlDate = $now->format('Y-m-d H:i:s');
            $date = new Carbon($refund->date, 'Europe/London');
            if ($request->transactionType == 'chargeback') {
                $chargeback = 1;
            }

            $partial_amount = $request->edit_amount / $refund->amount;
            $pre_vat = number_format((float)$refund->pre_vat * (float)$partial_amount , 2, '.', '');
            $vat_amount =  number_format((float)$refund->vat_amount * (float)$partial_amount , 2, '.', '');
            $post_vat =  number_format((float)$refund->post_vat * (float)$partial_amount , 2, '.', '');

            $refund_transaction = new Transaction();
            $refund_transaction->VSPTxID = NULL;
            $refund_transaction->status = 'NOTPROCESSED';
            $refund_transaction->Tx_Type = 'REFUND';
            $refund_transaction->VendorTxCode = NULL;
            $refund_transaction->transDetails = $refund->transDetails;
            $refund_transaction->assigned_to = $refund->assigned_to ?? "WEB";
            $refund_transaction->process_by = $refund->process_by ?? "WEB";
            $refund_transaction->date = $now;
            $refund_transaction->customerRef = 0;
            $refund_transaction->discount_price = 0;
            $refund_transaction->discount_description = "None";
            $refund_transaction->amount = $request->edit_amount;
            $refund_transaction->currency = $refund->currency;
            $refund_transaction->pre_vat = $pre_vat;
            $refund_transaction->vat_amount =  $vat_amount;
            $refund_transaction->post_vat =  $post_vat;
            $refund_transaction->description = $refund->description;
            $refund_transaction->client_id = $refund->client_id;
            $refund_transaction->company_id = $refund->company_id;
            $refund_transaction->site_client_id = $refund->site_client_id;
            $refund_transaction->site_id = $refund->site_id;
            $refund_transaction->site_job_number = $refund->site_job_number;
            $refund_transaction->invoice_no = 0;
            $refund_transaction->invoice_spooled = '0';
            $refund_transaction->save();

            $vendorTXcodeRefunds = Transaction::vendorTXcode($refund->site_id, $refund_transaction->transID);
            $refund_transaction->VendorTxCode = $vendorTXcodeRefunds;
            $refund_transaction->save();
            $payload = [];

            // if the refund is equal to the total amount and it's been processed the same day the first payment was executed
            // it will be voided
            if ($request->transactionType == "void" || ($refund->amount == $request->edit_amount && ($date->format('Y-m-d') == $now->format('Y-m-d')))) {
                $trans_event = "Cancellation processing";
                $trans_comment = " started to make a cancellation ";
                $trans_type = 'void';
                $payload = [
                    'instructionType' => 'void',
                    'amount' => (int)$refund->amount * 100,
                ];
            } else {
                $trans_event = ($request->transactionType == 'chargeback' && $chargeback == 1) ? "Charge back processing" : "Refund processing";
                $trans_comment = ($request->transactionType == 'chargeback' && $chargeback == 1) ? " started to make a charge back " : " started to make a refund ";
                $payload = [
                    'transactionType' => 'Refund',
                    'referenceTransactionId' => $refund->VSPTxID,
                    'vendorTxCode' => $refund_transaction->VendorTxCode,
                    'amount' => (int)$request->edit_amount * 100,
                    'description' => 'refund'
                ];
            }
            // active log
            $activeLog = new ActiveLog();
            $activeLog->event = $trans_event;
            $activeLog->comment = $this->user->full_name . $trans_comment . $refund_transaction->amount . "GBP against transaction: " . $refund->transID;
            $activeLog->client_id = $refund_transaction->client_id;
            $activeLog->trans_id = $refund->transID;
            $activeLog->site_id = $refund_transaction->site_id;
            $activeLog->date = $now;
            $activeLog->save();

            if($chargeback == 0) {
                $sagePayRefundsService = app()->make('Services\SagePayRefundsService');
                $response = $sagePayRefundsService->handle($payload, $refund->VSPTxID);
            }

            if ((isset($response->instructionType) && $response->instructionType == 'void') || $chargeback == 1 || is_null($refund->VSPTxID)) {

                $tx_Type = "REFUND";
                if((isset($response->instructionType) && $response->instructionType == 'void' && $refund->Tx_Type == 'REFUND') ||  is_null($refund->VSPTxID)){
                    $tx_Type = "REFUND VOID";
                    $refunded_success_message = "voided";
                }

                // Void transaction
                $voidTransaction = new Transaction();
                $voidTransaction->VSPTxID = $response->transactionId ?? NULL;
                $voidTransaction->status = 'OK';
                $voidTransaction->Tx_Type = $tx_Type;
                $voidTransaction->VendorTxCode = NULL;
                $voidTransaction->transDetails = $refund->transDetails;
                $voidTransaction->date = $now;
                $voidTransaction->assigned_to = $refund->assigned_to ?? "WEB";
                $voidTransaction->process_by = $refund->process_by ?? "WEB";
                $voidTransaction->customerRef = 0;
                $voidTransaction->discount_price = 0;
                $voidTransaction->discount_description = "None";
                $voidTransaction->amount = $request->edit_amount;
                $voidTransaction->pre_vat = $pre_vat;
                $voidTransaction->vat_amount =  $vat_amount;
                $voidTransaction->post_vat =  $post_vat;
                $voidTransaction->currency = $refund->currency;
                $voidTransaction->description = $refund->description;
                $voidTransaction->client_id = $refund->client_id;
                $voidTransaction->company_id = $refund->company_id;
                $voidTransaction->site_client_id = $refund->site_client_id;
                $voidTransaction->site_id = $refund->site_id;
                $voidTransaction->site_job_number = $refund->site_job_number;
                $voidTransaction->invoice_no = 0;
                $voidTransaction->invoice_spooled = '0';
                $voidTransaction->save();

                $vendorTXcodeRefunds = Transaction::vendorTxCodeRefund($refund->site_id, $voidTransaction->transID);
                $voidTransaction->VendorTxCode = $vendorTXcodeRefunds . "-REF";
                $voidTransaction->save();


                // refunds history
                $refund_history = new RefundHistory();
                $refund_history->transaction_id = $refund->transID;
                $refund_history->refund_amount = $voidTransaction->amount;
                $refund_history->user_name = $this->user->username;
                $refund_history->date = $now;
                $refund_history->save();

                // active log
                $activeLog = new ActiveLog();
                $activeLog->event = "Cancellation made";
                $activeLog->comment = $this->user->full_name . " made a cancellation of " . $voidTransaction->amount . "GBP against transaction: " . $refund->transID;
                $activeLog->client_id = $voidTransaction->client_id;
                $activeLog->trans_id = $refund->transID;
                $activeLog->site_id = $voidTransaction->site_id;
                $activeLog->date = $now;
                $activeLog->save();

                if($chargeback == 0){
                    $raw_responce = $this->storeOpayoRefundResponse(json_encode($response), $voidTransaction->transID);
                }
                return redirect()->back()->with('success', 'The transaction was successfully ' . $refunded_success_message);
            }
            if ((isset($response->status) && $response->status == 'Ok') || $chargeback == 1) {

                // refund transaction status OK
                $refundTransactionSuccessful = new Transaction;
                if ($chargeback == 1) {
                    $refundTransactionSuccessful->chargeback = 1;
                }
                $refundTransactionSuccessful->VSPTxID = $response->transactionId;
                $refundTransactionSuccessful->status = 'OK';
                $refundTransactionSuccessful->Tx_Type = 'REFUND';
                $refundTransactionSuccessful->VendorTxCode = NULL;
                $refundTransactionSuccessful->transDetails = $response->statusDetail;
                $refundTransactionSuccessful->assigned_to = $refund->assigned_to ?? "WEB";
                $refundTransactionSuccessful->process_by = $refund->process_by ?? "WEB";
                $refundTransactionSuccessful->date = $now;
                $refundTransactionSuccessful->customerRef = 0;
                $refundTransactionSuccessful->discount_price = 0;
                $refundTransactionSuccessful->discount_description = "None";
                $refundTransactionSuccessful->amount = $request->edit_amount;
                $refundTransactionSuccessful->pre_vat = $pre_vat;
                $refundTransactionSuccessful->vat_amount =  $vat_amount;
                $refundTransactionSuccessful->post_vat =  $post_vat;
                $refundTransactionSuccessful->description = $refund->description;
                $refundTransactionSuccessful->currency = $refund->currency;
                $refundTransactionSuccessful->client_id = $refund->client_id;
                $refundTransactionSuccessful->company_id = $refund->company_id;
                $refundTransactionSuccessful->site_client_id = $refund->site_client_id;
                $refundTransactionSuccessful->site_id = $refund->site_id;
                $refundTransactionSuccessful->site_job_number = $refund->site_job_number;
                $refundTransactionSuccessful->invoice_no = 0;
                $refundTransactionSuccessful->invoice_spooled = '0';
                $refundTransactionSuccessful->TxAuthNo = $response->bankAuthorisationCode ?? NULL;
                $refundTransactionSuccessful->save();

                $vendorTXcodeRefunds = Transaction::vendorTxCodeRefund($refund->site_id, $refundTransactionSuccessful->transID);
                $refundTransactionSuccessful->VendorTxCode = $vendorTXcodeRefunds . '-REF';
                $refundTransactionSuccessful->save();

                // refunds history
                $refund_history = new RefundHistory();
                $refund_history->transaction_id = $refund->transID;
                $refund_history->refund_amount = $refundTransactionSuccessful->amount;
                $refund_history->user_name = $this->user->username;
                $refund_history->date = $now;
                $refund_history->save();

                // active log
                $activeLog = new ActiveLog();
                $trans_event = ($request->transactionType == 'chargeback' && $refundTransactionSuccessful->chargeback == 1) ? "Charge back Made" : "Refund Made";
                $trans_comment = ($request->transactionType == 'chargeback' && $refundTransactionSuccessful->chargeback == 1) ? " made a charge back of " : " made a refund of ";
                $activeLog->event = $trans_event;
                $activeLog->comment = $this->user->full_name . $trans_comment . $refundTransactionSuccessful->amount . "GBP against transaction: " . $refund->transID;
                $activeLog->client_id = $refundTransactionSuccessful->client_id;
                $activeLog->trans_id = $refund->transID;
                $activeLog->site_id = $refundTransactionSuccessful->site_id;
                $activeLog->date = $now;
                $activeLog->save();

                $raw_responce = $this->storeOpayoRefundResponse(json_encode($response), $refundTransactionSuccessful->transID);
                return redirect()->back()->with('success', 'The refund is successful');
            }

            $jsonResponse = json_decode($response['response'], true);

            if (isset($response['status']) && $response['status'] == 403) {
                if ($jsonResponse['code'] == 1018) {
                    $message = 'Refund already submitted';
                    if(isset($jsonResponse['description'])){
                        $message = $jsonResponse['description'];
                    }
                    return redirect()->back()->with('warning', $message);
                } else if ($trans_type == 'void') {

                    $warning = 'Transaction already cancelled';
                    if($jsonResponse['code'] == 1014){
                        $warning = "This transaction can not be voided anymore. It appears that the refund process has been " .
                            "completed and the amount has been transferred to the customer account already."
                            . " Usually you can void a refund transaction within a day it has been submitted.";
                    }
                    return redirect()->back()->with('warning', $warning);
                } else {
                    $error = $jsonResponse['description'];
                    return redirect()->back()->with('error', $error);
                }
            }

            if (isset($response['status']) && $response['status'] != 403 && $response['status'] != 404) {
                $message = 'Refund already submit';
                $error = json_decode($response['response'])->statusDetail;
                $success = false;
                return redirect()->back()->with('error', $error);
            }

            if(isset($response['status']) && $response['status'] == 404){
                $message = 'Transaction not found on SagePay/Opayo';
                $success = false;
                return redirect()->back()->with('error', $message);
            }

        }

    }

    /**
     * store opayo response
     */
    public function storeOpayoRefundResponse($response, $transID)
    {
        $serializedResponse = serialize($response);
        $jsonResponseToArray = json_decode($response, true);
        $stringResponse = RawResponse::processArrayResponse($jsonResponseToArray);

        $raw_responce = RawResponse::insert([
            "ori_response" => $stringResponse,
            "response" => $response,
            "serialized_array" => $serializedResponse,
            "trans_id" => $transID,
        ]);
        return $raw_responce;
    }
}
