<?php
/**
 * Created by William Armillei
 * Date: 26/08/2021
 */

namespace App\Services\Sagepay;

use App\Services\ServiceInterface;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use GuzzleHttp\Psr7;

class SagePayCardIdentifierService extends BaseSageService implements ServiceInterface
{

    private $queryString = "";

    public function handle(array $data)
    {
        $this->setupConnectionDetails();
        $this->makeRequestString($data);
        $this->getCardIdentifier();
        return $this->response;
    }


    private function makeRequestString($data)
    {
        $cardDetails = [];

        $cardDetails["cardDetails"] = [
            "cardholderName" => $data["cc_name"],
            "cardNumber" => $data["cc_number"],
            "expiryDate" => $data["expiryMonth"] . $data["expiryYear"],
            "securityCode" => $data["cc_cvv"]
        ];
        $this->queryString = json_encode($cardDetails, true);
    }


    public function getCardIdentifier()
    {
        $authKey = "Bearer " . Session::get("merchantSessionId");

        // Connect with Sagepay
        try {
            $client = new \GuzzleHttp\Client();

            // Testing time mark
            Session::put("EnterCardIdentifierTime", Carbon::now()->format("h:i:s"));

            $res = $client->request('POST',
                $this->details["cardIdentifierEndpoint"],
                [
                    'headers' => [
                        'Authorization' => $authKey
                    ],
                    'json' => json_decode($this->queryString)
                ]);

            // Testing time mark
            Session::put("EnterCardIdentifierTimeEnd", Carbon::now()->format("h:i:s"));

            $this->response = $res->getBody()->getContents();
            $this->decodeResponse();

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                //$this->assessIfErrors();
                $this->response = array("statusCode" => $e->getResponse()->getStatusCode(), "response" => $e->getResponse()->getBody()->getContents());

            }
        }
    }
}
