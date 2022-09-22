<?php
/**
 * Created by William Armillei
 * Date: 26/08/2021
 */

namespace App\Services\Sagepay;

use App\Services\ServiceInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Illuminate\Support\Facades\Session;

class SagePayMerchantIdService extends BaseSageService implements ServiceInterface
{
    public function handle(array $data)
    {
        $this->setupConnectionDetails();
        $this->getSessionMerchantId();

        if(is_array($this->response)){
            return $this->response['merchantSessionKey'];
        }
    }


    public function getSessionMerchantId()
    {
        $postFields = '{ "vendorName":"' . $this->details['vendorName'] . '"}';

        $authKey = "Basic " . base64_encode(
                $this->details['integrationKey'] . ':' . $this->details['integrationPassword']
            );

        // Connect with Sagepay
        try {

            $client = new \GuzzleHttp\Client();
            $microtimeStart = microtime(true);
            $res = $client->request('POST',
                $this->details["merchantIdEndpoint"],
                [
                    'headers' => [
                        'Authorization' => $authKey
                    ],
                    'json' => json_decode($postFields)
                ]);

            $this->response = $res->getBody()->getContents();
            $this->decodeResponse();

        } catch (RequestException $e) {
            //echo Psr7\str($e->getRequest());
            if ($e->hasResponse()) {
                //$this->assessIfErrors();
                return Psr7\str($e->getResponse());
            }
        }
    }

}
