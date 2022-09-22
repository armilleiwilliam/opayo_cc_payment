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

class SagePayRetrieveTransactionService extends BaseSageService implements ServiceInterface
{

    private $requestString;
    private $country = 'GB';
    private $transactionId;
    private $paReq;

    public function __construct($app = null)
    {
        parent::__construct();
    }

    public function handle($data)
    {
        $this->setupConnectionDetails();
        $this->retrieveTransaction($data);

        return $this->response;
    }

    public function retrieveTransaction($transactionId)
    {
        $authKey = "Basic " . base64_encode(
                $this->details['integrationKey'] . ':' . $this->details['integrationPassword']
            );

        // Retrieve transaction
        try {
            $client = new \GuzzleHttp\Client();

            // Testing time mark
            Session::put("EnterRetrieveTransaction", \Carbon\Carbon::now()->format("h:i:s"));

            $res = $client->request('GET',
                $this->details["transactionEndpoint"] . $transactionId,
                [
                    'headers' => [
                        'Authorization' => $authKey
                    ]
                ]);

            // Testing time mark
            Session::put("EnterRetrieveTransactionEnd", \Carbon\Carbon::now()->format("h:i:s"));

            $this->response = $res->getBody()->getContents();
            Session::put("transactionResponse", $this->response);
            $this->decodeResponse(false);
            $this->bankAuthorisationCode = $this->response->bankAuthorisationCode ?? NULL;

            Session::put('bankAuthorisationCode', $this->bankAuthorisationCode);

        } catch (RequestException $e) {
            $errorMessage = strpos($e->getMessage(), "404") ? 404 : 10000;
            $this->response = array("statusCode" => $errorMessage);
        }
    }
}
