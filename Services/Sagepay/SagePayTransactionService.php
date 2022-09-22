<?php
/**
 * Created by William Armillei.
 * User: warmille
 * Date: 01/07/2021
 * Time: 15:38
 */

namespace App\Services\Sagepay;

use App\Services\ServiceInterface;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;

class SagePayTransactionService extends BaseSageService implements ServiceInterface
{

    private $requestString;
    private $country = 'GB';
    private $transactionId;
    private $paReq;

    public function __construct($app = null)
    {
        parent::__construct();
    }

    public function handle(array $data, $threeDv2 = true)
    {
        $this->setupConnectionDetails();
        $this->makeRequestString($data, $threeDv2);
        $this->createTransaction();

        return $this->response;
    }

    private function checkIfGB($country){
        $country = trim(strtoupper($country));
        $country = $country == "UK" ? "GB" : $country;
        return $country;
    }

    // reformat amounts
    private function reformatAmount($amount): int
    {
        return (integer) number_format($amount * 100, 0, '.', '');
    }

    private function makeRequestString($data, $threeDv2 = true)
    {
        $currentBasket = unserialize(Session::get('currentBasket'));
        $merchantSessionId = Session::get('merchantSessionId');
        $cardIdentifier = Session::get('cardIdentifier');
        $accountType = Session::get('accountType');

        // these variables verify if the color_depth value is included in the Opayo range validation values
        // if not javascript will be disabled and color_depth excluded from the 3Dv2 validation process
        $opayoColorDepthAccepted = [1,4,8,15,16,24,32,48];
        $browserDepthColor = $currentBasket["color_depth"];
        $javascriptEnabled = array_key_exists($browserDepthColor, $opayoColorDepthAccepted);

        // parameters for old 3D authentication v. 1
        $requestString3Dv1 = [
            "transactionType" => "Payment",
            "paymentMethod" => [
                "card" => [
                    "merchantSessionKey" => $merchantSessionId,
                    "cardIdentifier" => $cardIdentifier,
                    "save" => "false",
                ]
            ],
            "vendorTxCode" => $currentBasket["VendorTxCode"],
            "amount" => $this->reformatAmount($currentBasket["amount"]), // Sage Pay does not want float values, if it's 125.03 I should put 12503
            "currency" => $currentBasket["currency"] ?? "GBP",
            "description" => $currentBasket["package"],
            "apply3DSecure" => "UseMSPSetting",
            "customerFirstName" => $currentBasket["firstName"],
            "customerLastName" => $currentBasket["lastName"],
            "billingAddress" => array(
                "address1" => $currentBasket["address_1"],
                "address2" => $currentBasket["address_2"],
                "city" => $currentBasket["city"],
                "postalCode" => $currentBasket["postcode"],
                "country" => $this->checkIfGB($currentBasket["country"])
            )
        ];

        if(trim(strtoupper($currentBasket["country"])) == "US"){
            $requestString3Dv1["billingAddress"]["state"] = $currentBasket["state"];
        }

        if($accountType === "T"){
            $requestString3Dv1["entryMethod"] = "TelephoneOrder";
        } else if ($accountType === "M"){
            $requestString3Dv1["entryMethod"] = "MailOrder";
        } else {
            $requestString3Dv1["entryMethod"] = "Ecommerce";
        }

        // parameters for 3D authentication v. 2
        if ($threeDv2) {
            $requestString3D2v2 = [
                "customerPhone" => $currentBasket["tel"],
                "customerEmail" => $currentBasket["email"] ?? "",
                "customerMobilePhone" => $currentBasket["tel"],
                "customerWorkPhone" => "",
                "strongCustomerAuthentication" => [
                    "website" => "https://secure.ukds.net",
                    "notificationURL" => route('3dsecureCheckout'),
                    "browserIP" => $_SERVER["REMOTE_ADDR"],
                    "browserAcceptHeader" => "text/html, application/json",
                    "browserJavascriptEnabled" => $javascriptEnabled,
                    "browserJavaEnabled" => false,
                    "browserLanguage" => $currentBasket["browser_language"],
                    "browserScreenHeight" => $currentBasket["screen_height"],
                    "browserScreenWidth" => $currentBasket["screen_width"],
                    "browserTZ" => $currentBasket["time_zone"],
                    "browserUserAgent" => $_SERVER['HTTP_USER_AGENT'],
                    "challengeWindowSize" => "Small",
                    "transType" => "GoodsAndServicePurchase",
                    "threeDSRequestorAuthenticationInfo" => [
                        "threeDSReqAuthData" => "data",
                        "threeDSReqAuthMethod" => "LoginWithThreeDSRequestorCredentials",
                        "threeDSReqAuthTimestamp" => Carbon::now()->format("YmdHi")
                    ],
                    "threeDSRequestorPriorAuthenticationInfo" => [
                        "threeDSReqPriorAuthData" => "data",
                        "threeDSReqPriorAuthMethod" => "FrictionlessAuthentication"
                    ]
                ]
            ];

            // if color_depth value is not included in the Opayo range (1,4,8,15,16,24,32,48) the javascript will not be enabled
            // and color depth property not added to the 3dv2 validation script
            if($javascriptEnabled){
                $requestString3D2v2["strongCustomerAuthentication"]["browserColorDepth"] = (string)$browserDepthColor;
            }

            $requestString = array_merge($requestString3Dv1, $requestString3D2v2);
        }

        $this->requestString = json_encode($requestString, true);
        Session::put('results', $this->requestString);
    }

    public function createTransaction()
    {
        $authKey = "Basic " . base64_encode(
                $this->details['integrationKey'] . ':' . $this->details['integrationPassword']
            );

        // Start transaction
        try {
            $client = new \GuzzleHttp\Client();

            // Testing time mark
            Session::put("EnterCreateTransactionTime", \Carbon\Carbon::now()->format("h:i:s"));
            $res = $client->request('POST',
                $this->details["transactionEndpoint"],
                [
                    'headers' => [
                        'Authorization' => $authKey
                    ],
                    'json' => json_decode($this->requestString)
                ]);

            // Testing time mark
            Session::put("EnterCreateTransactionTimeEnd", \Carbon\Carbon::now()->format("h:i:s"));

            $this->response = $res->getBody()->getContents();
            Session::put("transactionResponse", $this->response);
            $this->decodeResponse();
            $this->transactionId = $this->response["transactionId"] ?? "None";
            $this->paReq = $this->response["paReq"] ?? "None";
            $this->bankAuthorisationCode = $this->response["bankAuthorisationCode"] ?? NULL;

            Session::put([
                'curtransid' => $this->transactionId,
                'paReq' => $this->paReq,
                'bankAuthorisationCode' => $this->bankAuthorisationCode
            ]);

        } catch (RequestException $e) {
            $this->response = array("statusCode" => $e->getResponse()->getStatusCode(), "response" => $e->getResponse()->getBody()->getContents());
        }
    }
}
