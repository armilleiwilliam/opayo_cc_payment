<?php

/**
 * Created by Junior Kaboré.
 * Date: 01/07/2021
 * Time: 15:38
 */


namespace App\Services\Sagepay;

use App\Services\ServiceInterface;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Psr7;

class SagePayRefundsService extends BaseSageService implements ServiceInterface
{
    public function __construct($app = null)
    {
        parent::__construct();
    }

    public function handle(array $data, string $transactionId = null)
    {
        $this->setupConnectionDetails();
        $this->createRefunds($data, $transactionId);
        return $this->response;
    }

    public function createRefunds($data, $transactionId)
    {
        try {
            if (isset($data["instructionType"]) && $data["instructionType"] == "void") {
               $uri = $this->details["transactionEndpoint"] . $transactionId . "/instructions";
            } else {
                $uri = $this->details["transactionEndpoint"];
            }
            $this->requestString = json_encode($data, true);
            // dump($data["description"]);
            // dd($uri);
            $authKey = "Basic " . base64_encode(
                $this->details['integrationKey'] . ':' . $this->details['integrationPassword']
            );
            $client = new \GuzzleHttp\Client();
            $res = $client->request(
                'POST',
                $uri,
                [
                    'headers' => [
                        'Authorization' => $authKey
                    ],
                    'json' => json_decode($this->requestString)
                ]
            );
            $this->response = json_decode($res->getBody()->getContents());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->response = array("status" => $e->getResponse()->getStatusCode(), "response" => $e->getResponse()->getBody()->getContents(), "error" => $e->getResponse());
                // $this->response = array("status" => $e->getResponse()->getStatusCode(), "response" => $e->getResponse()->getBody()->getContents(), "error" => $e->getResponse()->getReasonPhrase());
            }
        }
    }
}
