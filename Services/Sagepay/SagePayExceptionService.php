<?php
/**
 * SagePay returns a complex variety of error responses, sometime just two different wrong data in two different input form
 * return a different Json format for each form field. That is very weird. Because of that I had develop several methods
 * which deal with all the different type of responses. Sorry for the abundance of coding.
 *
 * User: William Armillei
 * Date: 7/09/2021
 * Time: 12:41
 */

namespace App\Services\Sagepay;

use App\ActiveLog;
use App\Client;
use App\RawResponse;
use App\Services\Sagepay\Model\SagepayConstants;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;
use App\Mail\NotCatchedErrorsEmail;
use Illuminate\Support\Facades\Mail;

class SagePayExceptionService
{
    private $errorCodes;
    private $successCodes = [200, 201, 202, 204];
    private $transactionStatus = "OK";
    private $responseDetails = "";
    private $dot = ". ";
    private $colon = ": ";
    private $score = " - ";
    private $avsCvc = "";
    private $transactionid = NULL;
    private $getLocale = 'en';

    public function __construct()
    {
        $this->errorCodes = SagepayConstants::getSageErrorCodes();
    }

    public function handleError($input, $noModal = false)
    {
        $this->unpackInput($input);
        $this->checkAvsCvcCheck();
        $this->checkHeaderResponseError();

        if ($noModal) {
            return $this->checkResponseCode($noModal);
        }

        $this->checkResponseCode();
        $this->checkTransactionStatus();
        $this->checkIfErrorCodeMissing();
        $this->recordCatchAllError();
        return;
    }

    /**
     * Unpack input data received and store in different variables
     * @param $input
     */
    private function unpackInput($input)
    {
        $input = (array)$input;
        $this->httpCode = $input["statusCode"] ?? $input["status"];
        $this->avsCvcCheck = $input["avsCvcCheck"] ?? NULL;
        $this->transactionid = Session::get("transaction_id") ?? NULL;
        if ($this->getLocale = Session::get("locale")) {
            App::setLocale($this->getLocale);
        }

        // convert into array if an object format is returned
        if (!is_array($this->avsCvcCheck) && !empty($this->avsCvcCheck)) {
            $this->avsCvcCheck = (array)$this->avsCvcCheck;
        }

        // Unfortunately 3D v2 may authenticate but still not processing the payment
        // The following check if the payment has been processed after authentication
        if (isset($input["statusCode"])) {
            if ($input["statusCode"] == '0000') {
                $this->httpCode = is_array($input["3DSecure"]) ? $input["3DSecure"]["status"] : $input["3DSecure"]->status;
            }
        }

        $this->response = $input;

        if (is_array($this->response) && array_key_exists("response", $this->response)) {
            $this->responseDetails = json_decode($this->response["response"], true);
        }
    }

    /**
     * See if SagePay response has AVSCV2 issues, if so store in database
     */
    private function checkAvsCvcCheck()
    {
        if (!empty($this->avsCvcCheck) && is_array($this->avsCvcCheck)) {
            if (array_key_exists("status", $this->avsCvcCheck)) {
                if ($this->avsCvcCheck["status"] !== "AllMatched") {
                    $i = 0;

                    // loop each AVSCV2 error details
                    foreach ($this->avsCvcCheck as $key => $avs) {
                        $i++;
                        if (SagepayConstants::getNotMatched() == $avs) {
                            $this->avsCvc .= strtoupper($key) . " " . SagepayConstants::getNotMatchedClientView();
                            if ($i < count($this->avsCvcCheck)) {
                                $this->avsCvc .= ", ";
                            }
                        }
                    }
                } else {
                    $this->avsCvc = SagepayConstants::getAllMatch();
                }
            }
        }
    }

    /**
     * Catch server errors.
     * @throws SageException
     */
    private function checkHeaderResponseError()
    {
        // check server errors first
        if ($this->httpCode === 500 || $this->httpCode === 502) {
            $transError = $this->httpCode . $this->colon . ($this->httpCode == 500 ? 'Server Error' : 'Bad Gateway');
            $this->storeUnProcessedTransaction("ERROR", $transError, $transError);
            throw new SageException(
                array(
                    'message' => __('msg.sagepay-technical-issue') . $this->dot . '(' . $this->httpCode . ')',
                    'ajax' => true,
                    'useFrontModal' => true,
                    'transactionStatus' => 'ERROR',
                    'triggerRecord' => true
                )
            );
        }

        // check all the other errors
        if (array_key_exists($this->httpCode, $this->errorCodes)) {
            $this->handleValidationErrors();
        }
    }

    /**
     * Error handling - 3Dv1 and 3Dv2 authentication validation issues returned by SagePay related to the Card number
     * CVV, Expiry Date, Address 1 and PostCode
     * @param false $noModal
     * @return array|void
     * @throws SageException
     */
    private function checkResponseCode($noModal = false)
    {
        $message = "";
        $logmessage = "";
        $logmessageEvent = "Failed Payment";
        $ajax = true;
        $useFrontModal = true;
        $connectionTimeOutMessage = "";

        if (!isset($this->httpCode)) {
            // No status code - back out;
            return;
        }


        switch ($this->httpCode) {
            case SagepayConstants::getTimeOutCode():
                $message = __('msg.sagepay-timeout');
                $connectionTimeOutMessage = 'Sagepay connection timed out';
                $logmessageEvent = "TimeOut";
                break;
            case SagepayConstants::getSageBankDeclined():
                $declinedCode = '';
                $this->transactionStatus = "NOAUTHED";
                if (array_key_exists('bankResponseCode', $this->response) && $this->response['bankResponseCode']) {
                    $declinedCode = ' Code returned from bank: ' . $this->response['bankResponseCode'];
                }
                $message = __('msg.your-bank-has-declined-the-card') . $this->dot
                    . $declinedCode;
                $logmessage = 'The Authorisation was Declined by the bank.';
                break;
            case SagepayConstants::getError():
                $this->transactionStatus = "ERROR";
                $message = __('msg.error-while-authenticating') . $this->dot;
                $logmessage = 'General error while authenticating';
                $logmessageEvent = 'Error';
                break;
            case SagepayConstants::getInvalidCardNumber():
                $message = __('msg.card-details-incorrect');
                $this->transactionStatus = "INVALID";
                $logmessage = 'Invalid credit card details';
                $logmessageEvent = 'Malformed Request';
                break;
            case SagepayConstants::getAuthRejection3d():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.three-d-authentication-failed');
                $logmessage = '3D authentication failed';
                break;
            case SagepayConstants::getNoAuth():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.rejected-by-the-vendor-rules') . $this->dot;
                $logmessage = 'Authentication unsuccesful. Card holder name, Address, CVV or Postcode are not correct.';
                $logmessageEvent = 'Malformed Request';
                break;
            case SagepayConstants::getNotAuthenticated():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.rejected-by-the-vendor-rules-two') . $this->dot;
                $logmessage = 'Authentication unsuccesful. Address, CVV or Postcode are not correct.';
                $logmessageEvent = 'Malformed Request';
                break;
            case SagepayConstants::getAttemptOnly():
                $this->transactionStatus = "ATTEMPTONLY";
                $message = __('msg.process-incomplete') . $this->dot;
                $logmessage = 'The cardholder attempted to authenticate themselves, but the
                                process did not complete. A CAVV is returned and this is
                                treated as being successfully authenticated.';
                break;
            case SagepayConstants::getIncomplete():
                $this->transactionStatus = "INCOMPLETE";
                $message = __('msg.process-incomplete-two') . $this->dot;
                $logmessage = '3D Secure authentication not available';
                break;
            case SagepayConstants::getCardNotEnrolled():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.no-authed') . $this->dot;
                $logmessage = 'Card not enrolled to 3D authentication scheme';
                break;
            case SagepayConstants::getIssuerNotEnrolled():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.no-authed-two') . $this->dot;
                $logmessage = 'Card issure not enrolled to 3D authentication scheme';
                break;
            case SagepayConstants::getNotChecked():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.no-authed-three') . $this->dot;
                $logmessage = 'It is not possible to determine the validity of the card';
                break;
            case SagepayConstants::getCardNotAuthorized():
                $this->transactionStatus = "NOTAUTHED";
                $message = __('msg.no-authed-four') . $this->dot;
                $logmessage = '3D-Authentication failed. Cannot authorise this card.';
                break;
            case SagepayConstants::get3DAuthenticated():
                $message = "ok";
                break;
        }

        $logmessage = $this->response["statusCode"] . $this->colon . $logmessage;
        $transactionSageMessageResponse = $this->response["statusCode"] . $this->colon . $message;

        if ($this->transactionStatus !== "" && strtolower($message) !== "ok") {
            $this->storeUnProcessedTransaction($this->transactionStatus, $transactionSageMessageResponse, $logmessage, $logmessageEvent);
        }

        if($message !== ""){

            // determine if the data returned will be used in the Javascript Modal
            if ($noModal) {
                // the following is used when the "challenge" for 3Dv2 does not pass the validation
                return array(
                    'message' => $message,
                    'ajax' => $ajax,
                    'useFrontModal' => $useFrontModal,
                    'transactionStatus' => $this->transactionStatus,
                );
            } else {
                throw new SageException(
                    array(
                        'message' => $message,
                        'ajax' => $ajax,
                        'useFrontModal' => $useFrontModal,
                        'transactionStatus' => $this->transactionStatus,
                    ), $connectionTimeOutMessage
                );
            }
        }
    }

    /**
     * store transaction when failing
     * @param string $status
     * @param string $transDetails
     * @param string $logmessage
     * @param string $logmessageEvent
     */
    public function storeUnProcessedTransaction($status = "ERROR", $transDetails = "", $logmessage = "", $logmessageEvent = "Failed Payment")
    {
        $data = unserialize(Session::get('currentBasket'));
        $txType = Session::get("accountType");

        // create a Merchant ID for Opayo/SagePay credit card identifier
        $merchantIdService = app()->make('Services\SagePayMerchantIdService');
        $sessionMerchantKey = $merchantIdService->handle([]);
        Session::put('merchantSessionId', $sessionMerchantKey);

        // if transaction exists I update the data
        if ($transaction = Transaction::where("transID", $this->transactionid)->first()) {

            $threeDv = SagepayConstants::getIs3DvTwo();
            $version3D = Session::get("versionTwo");
            if ($version3D == false) {
                $threeDv = SagepayConstants::getIs3DvOne();
            }

            // get gateway client ID
            $clientDetails = Client::clientAndVendorDataRetrieve($data["site_id"]);
            $now = Carbon::now();
            $mysqlDate = $now->format('Y-m-d H:i:s');

            $transaction->invoice_no = $data["invoice_no"] ?? 0;
            $transaction->status = $status;
            $transaction->process_by = $data["processed_by"];
            $transaction->assigned_to = $data["assigned_to"];
            $transaction->Pay_Type = "CC";
            $transaction->VendorTxCode = $data["VendorTxCode"];
            $transaction->amount = $data["amount"];
            $transaction->pre_vat = Transaction::convertIntoGBP($data["pre_vat"], $data["currency"]);
            $transaction->vat_amount = Transaction::convertIntoGBP($data["vat_amount"], $data["currency"]);
            $transaction->post_vat = Transaction::convertIntoGBP($data["amount"], $data["currency"], true);
            $transaction->non_vat_amount =  $data["non_vat_amount"];
            $transaction->description = $data["package"];
            $transaction->client_id = $clientDetails["client_id"];
            $transaction->site_id = $data["site_id"];
            $transaction->company_id = $data["company_id"];
            $transaction->site_job_number = $data["site_job_number"];
            $transaction->paid = '0';
            $transaction->customerRef = 0;
            $transaction->transDetails = $transDetails . $threeDv;
            $transaction->discount_price = 0;
            $transaction->discount_description = "None";
            $transaction->Tx_Type = $txType == "M" ? "MANUAL" : "PAYMENT";
            $transaction->site_client_id = $data["site_client_id"];
            $transaction->date = $mysqlDate;
            $transaction->AVSCV2 = $this->avsCvc;
            $transaction->ip_address = $_SERVER["REMOTE_ADDR"] ?? NULL;
            Session::put("transactionResponse", json_encode($this->response));
            $transaction->TxAuthNo = Session::get("bankAuthorisationCode");
            $transaction->client_account = $clientDetails["client_account"];

            if ($transaction->save()) {
                // update active log just created with the right trans_id value
                if (!empty($clientDetails["log_id"])) {
                    ActiveLog::where("log_id", "=", $clientDetails["log_id"])->update(["trans_id" => $transaction->transID]);
                }

                if ($submittedDetailsLogId = Session::get("submittedDetailsLogId")) {
                    ActiveLog::where("log_id", "=", $submittedDetailsLogId)->update(["trans_id" => $transaction->transID]);
                }

                // store SagePay/Opayo response
                RawResponse::storeResponse($transaction->transID);
                Transaction::storeOnActiveLog($status, $logmessageEvent, str_replace("<br/>", "\r\n", $logmessage), $transaction->transID, $clientDetails["client_id"]);
            }
        }
    }

    /**
     * Basically filter errors returned by SagePay if there are wrong billng information format provided
     * @throws SageException
     */
    private function handleValidationErrors()
    {
        if (array_key_exists('response', $this->response)) {

            $errorList = \GuzzleHttp\json_decode($this->response["response"], true);
            $this->creditCardMainValidationIssues($errorList);

            // some responses statusCode 422 they then have a message which shows statusCode with another number (4021, 4026)
            // this code check if in the response the error response is different and return it
            $response = json_decode($this->response["response"], true);

            if (array_key_exists('statusCode', $response)) {

                $this->response = $response;
                $this->checkTransactionStatus();
            } else {

                if (array_key_exists("statusCode", $errorList)) {
                    $errorMessage = $this->errorCodes[$errorList["statusCode"]];
                    $errorMessagePlusStatusCode = $errorList["statusCode"] . $this->colon . $errorMessage;
                    $this->storeUnProcessedTransaction("INVALID", $errorMessagePlusStatusCode, $errorMessagePlusStatusCode);

                    throw new SageException(array(
                        'message' => '<h4>' . __("msg.check-the-following") . '</h4> <br/>'
                            . \Lang::get('msg.' . $errorMessage), 'ajax' => true,
                        'validationError' => true,
                        'useFrontModal' => true
                    ));
                }
            }
        }

        if (array_key_exists('statusCode', $this->response)) {

            $this->httpCode = $this->response["statusCode"];

            if (array_key_exists($this->httpCode, $this->errorCodes)) {
                $responseFiltered = json_decode($this->response["response"], true);

                $this->creditCardMainValidationIssues($responseFiltered);

                $message = $this->errorCodes[$this->httpCode];
                if(array_key_exists("code", $responseFiltered) && array_key_exists($responseFiltered["code"], $this->errorCodes)){
                    $message = $this->errorCodes[$responseFiltered["code"]];
                }

                if(array_key_exists("description", $responseFiltered)) {
                    $this->storeUnProcessedTransaction("INVALID", $responseFiltered["description"], $responseFiltered["description"]);
                }

                throw new SageException(
                    array(
                        'message' => \Lang::get('msg.' . $message),
                        'ajax' => 'yes',
                        'useFrontModal' => true,
                        'transactionStatus' => $errorList["status"] ?? "ERROR",
                        'triggerRecord' => true
                    ),
                    'Sagepay API error ' . $this->httpCode
                    . ' '
                    . $this->errorCodes[$this->httpCode]
                );
            }
        }
    }

    /**
     * credit card main validation issues
     */
    private function creditCardMainValidationIssues($responseFiltered = array())
    {
        if (array_key_exists('errors', $responseFiltered)) {

            $missingVariablesClientLanguageVersion = '';
            $missingVariablesEnglishVersion = '';
            $foundStandardValidationErrors = false;
            $valdiationErrorArray = SagepayConstants::getValidationErrors();

            foreach ($responseFiltered['errors'] as $errorArray) {
                if (array_key_exists($errorArray['property'], $valdiationErrorArray)) {

                    if (!empty($errorArray["clientMessage"]) && (!empty($errorArray["statusCode"]) || !empty($errorArray["code"]))) {
                        $code = $errorArray["statusCode"] ?? $errorArray["code"];
                        $missingVariablesClientLanguageVersion .= __("msg." . strtolower($errorArray['property'])) . $this->colon . __("msg." . $this->errorCodes[$code]) . $this->dot . '<br/>';
                        $missingVariablesEnglishVersion .= $valdiationErrorArray[$errorArray['property']] . $this->colon . $errorArray["clientMessage"] . '<br/>';

                    } else if (!empty($errorArray["description"]) && !empty($errorArray["code"])) {
                        $missingVariablesClientLanguageVersion .= __("msg." . strtolower($errorArray['property'])) . $this->colon . __("msg." . $this->errorCodes[$errorArray["code"]]) . $this->dot . '<br/>';
                        $missingVariablesEnglishVersion .= $valdiationErrorArray[$errorArray['property']] . $this->colon . $errorArray["description"];
                    }
                    $foundStandardValidationErrors = true;
                }
            }

            $transactionSageMessageResponse = $missingVariablesEnglishVersion !== '' ? (isset($this->response["statusCode"]) ? $this->response["statusCode"] . $this->colon : "") . $missingVariablesEnglishVersion : $transactionSageMessageResponse;
            $this->storeUnProcessedTransaction("INVALID", $transactionSageMessageResponse, $transactionSageMessageResponse);
            if ($foundStandardValidationErrors) {
                throw new SageException(array(
                    'message' => '<h4>' . __("msg.check-the-following") . '</h4> <br/>'
                        . $missingVariablesClientLanguageVersion, 'ajax' => true,
                    'validationError' => true,
                    'useFrontModal' => true
                ));
            }
        }
    }


    /**
     * More error handling - 3Dv2 validation
     * @throws SageException
     */
    private function checkTransactionStatus()
    {
        $message = "";
        $logmessage = "";
        $status = "INVALID";

        if (isset($this->response["3DSecure"]) && array_key_exists('status', $this->response["3DSecure"])) {

            switch ($this->response["3DSecure"]["status"]) {
                case 'Error':
                    $message = __('msg.error-two');
                    $status = "ERROR";
                    $logmessage = "General error with the transaction";
                    break;
                case 'Incomplete':
                    $message = __('msg.incomplete-two');
                    $logmessage = $message;
                    $status = "INCOMPLETE";
                case 'CardNotEnrolled':
                    $message = __('msg.card-not-enrolled');
                    $logmessage = $message;
                    $status = "CARDNOTENROLLED";
                case 'IssuerNotEnrolled':
                    $message = __('msg.card-issuer-not-enrolled');
                    $logmessage = $message;
                    $status = "ISSUERNOTENROLLED";
                case 'AttemptOnly':
                    $message = __('msg.attempt-only');
                    $logmessage = "Card holder might not be enrolled to 3-D authentication";
                    $status = "ATTEMPTONLY";
                case 'NotAuthenticated':
                    $message = __('msg.not-authenticated');
                    $logmessage = "Authentication unsuccessful";
                    $status = "NOTAUTHED";
            }

            $logmessage = $this->response["statusCode"] . $this->colon . $logmessage;

            $transactionSageMessageResponse = $this->response["statusCode"] . $this->colon . $message;
            $this->storeUnProcessedTransaction($status, $transactionSageMessageResponse, $logmessage);
            throw new SageException(
                array(
                    'message' => SagepayConstants::getAuthRejection3dMessage($message),
                    'ajax' => 'yes',
                    'transactionStatus' => $status,
                    'transactionDetails' => $message
                )
            );

        } else if (array_key_exists('statusCode', $this->response)) {

            if(array_key_exists('transDetails', $this->response)) {

                $logMessage = $this->response["statusCode"] . $this->colon . $this->response["transDetails"];
                $this->storeUnProcessedTransaction($status, $logMessage, $logmessage);
                throw new SageException(
                    array(
                        'message' => SagepayConstants::getAuthRejection3dMessage(__("msg." . $this->response["statusCode"]) . " 42"),
                        'ajax' => 'yes',
                        'transactionStatus' => $status,
                        'transactionDetails' => $logmessage
                    )
                );

            // Validation errors due to wrong personal details entered (like PostCode)
            } else if (array_key_exists('statusDetail', $this->response)) {

                $logmessage = $this->response["statusCode"] . $this->colon . $this->response["statusDetail"];
                $this->storeUnProcessedTransaction($status, $logmessage, $logmessage);
                throw new SageException(
                    array(
                        'message' => SagepayConstants::getAuthRejection3dMessage(__("msg." . $this->response["statusCode"])),
                        'ajax' => 'yes',
                        'transactionStatus' => $status,
                        'transactionDetails' => $logmessage
                    )
                );
            } else {
                $logmessage = $this->response["statusCode"];
                $this->storeUnProcessedTransaction($status, $logmessage, $logmessage);
                throw new SageException(
                    array(
                        'message' => SagepayConstants::getAuthRejection3dMessage(__("msg." . $this->response["statusCode"])),
                        'ajax' => 'yes',
                        'transactionStatus' => $status,
                        'transactionDetails' => $logmessage
                    )
                );
            }
        }
    }

    /**
     * Just in case some errors have not been catched
     * @throws SageException
     */
    private function recordCatchAllError()
    {
        // TODO - send to developer an email if a new error not listed is triggered
        /*if(!in_array($this->httpCode, $this->successCodes)) {
            $esito = Mail::send(new NotCatchedErrorsEmail($this->httpCode, "ssdfsd"));
        }*/

        $message = __("msg.error-two");
        throw new SageException(
            array(
                'message' => SagepayConstants::getAuthRejection3dMessage($message, true),
                'ajax' => 'yes',
                'transactionStatus' => 'INVALID',
                'transactionDetails' => $message,
                'triggerRecord' => true
            )
        );
    }
}
