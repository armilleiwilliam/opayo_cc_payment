<?php
/**
 * Created by PhpStorm.
 * User: William Armillei
 * Date: 09/09/21
 * Time: 11:18
 */

namespace App\Services\Sagepay;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Carbon;
use GuzzleHttp\Psr7;
use Illuminate\Support\Facades\Session;

class Sage3DSecureService extends BaseSageService
{
    private $transactionId;
    private $payload;

    public function handle(array $data)
    {
        $this->setupConnectionDetails();

        //may not need these
        $this->merchId = $data['curMerchId'];
        $this->cardId = $data['curCardId'];
        $this->transId = $data['curTransId'];
        $this->cres = $data['cRes'] ?? NULL;
        $this->paRes = $data['paRes'] ?? NULL;

        return $this->getTransactionOutCome();
    }

    public function getTransactionOutCome()
    {

        $postFields = '{ "cRes":"' . $this->cres . '", "paRes":"' . $this->paRes . '"}';

        $authKey = "Basic " . base64_encode(
                $this->details['integrationKey'] . ':' . $this->details['integrationPassword']
            );

        // Connect with Sagepay
        try {
            $client = new \GuzzleHttp\Client();

            // Testing time mark
            Session::put("Enter3DSecureTime", \Carbon\Carbon::now()->format("h:i:s"));

            $res = $client->request('POST',
                $this->details["threeDSecureEndpoint"] . $this->transId . "/3d-secure" . ($this->cres ? "-challenge" : ""),
                [
                    'headers' => [
                        'Authorization' => $authKey
                    ],
                    'json' => json_decode($postFields)
                ]);

            // Testing time mark
            Session::put("Enter3DSecureTimeEnd", \Carbon\Carbon::now()->format("h:i:s"));

            $transactionDetails = json_decode($res->getBody()->getContents());

            return $transactionDetails;

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return Psr7\str($e->getResponse());
            }
        }
    }
}
