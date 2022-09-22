<?php

namespace Tests;

use App\PaymentRequests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Session;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @param string|null $name
     * @param array $data
     * @param $dataName
     */
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // the following are the basci fake data necessary to have a successful transaction after clicking on Pay Now on Payment Gateway
        $this->payRequestStart = [
            "email" => "onlinetest19090@40online-uk.biz",
            "tel" => "01234567890",
            "address_1" => "58 London Rd",
            "address_2" => "",
            "city" => "London",
            "country" => "UK",
            "postcode" => "S407AA",
            "token" => "UBAQmBpepxD5JPSJwGAY5qul7HEDDHguvBI4Qcra",
            "processed_by" => "WEB",
            "package" => "Personalised+Service",
            "company_id" => 12,
            "site_client_id" => 1234567890,
            "source" => "quickie-divorce.com",
            "securepay_enabled" => "1",
            "pre_vat" => "20",
            "vat_amount" => "4",
            "amount" => "24",
            "currency" => "GBP",
            "assigned_to" => "WEB",
            "non_vat_amount" => "0",
            "site_id" => 105,
            "site_job_number" => 123423466
        ];

        $this->paymentRequestFinaleFirst = [
            "firstName" => "Tim",
            "lastName" => "Barckley",
            "id_reference" => "30",
            "screen_height" => "1080",
            "screen_width" => "1920",
            "time_zone" => "-60",
            "color_depth" => "24",
            "browser_language" => "en-GB",
            "cc_number" => "4929000000006",
            "expiryMonth" => "03",
            "expiryYear" => "02",
            "cc_name" => "Tim Barckley",
            "cc_cvv" => "123"
        ];

        $this->payRequestFinaleTwo = [
            "first_name" => "Tim",
            "last_name" => "Barckley"
        ];

        // the following array is used to give parameters to $this->json("POST") for each test
        $this->payRequest = array_merge($this->payRequestStart, $this->paymentRequestFinaleFirst);

        // the following array is used to create a Payment Request model entry
        $this->trans_request = array_merge($this->payRequestStart, $this->payRequestFinaleTwo);
        $this->transactionRequest = "";
        $this->merchantIdService = null;
    }

    /**
     * @return void
     */
    public function setUp($buildPaymentRequest = true): void
    {
        parent::setUp();

        if($buildPaymentRequest){
            $this->transactionRequest = new PaymentRequests();
            $this->transactionRequest->email = $this->trans_request["email"];
            $this->transactionRequest->tel = $this->trans_request["tel"];
            $this->transactionRequest->address_1 = $this->trans_request["address_1"];
            $this->transactionRequest->address_2 = $this->trans_request["address_2"];
            $this->transactionRequest->city = $this->trans_request["city"];
            $this->transactionRequest->country = $this->trans_request["country"];
            $this->transactionRequest->postcode = $this->trans_request["postcode"];
            $this->transactionRequest->token = $this->trans_request["token"];
            $this->transactionRequest->processed_by = $this->trans_request["processed_by"];
            $this->transactionRequest->package = $this->trans_request["package"];
            $this->transactionRequest->company_id = $this->trans_request["company_id"];
            $this->transactionRequest->site_client_id = $this->trans_request["site_client_id"];
            $this->transactionRequest->source = $this->trans_request["source"];
            $this->transactionRequest->securepay_enabled = $this->trans_request["securepay_enabled"];
            $this->transactionRequest->pre_vat = $this->trans_request["pre_vat"];
            $this->transactionRequest->vat_amount = $this->trans_request["vat_amount"];
            $this->transactionRequest->amount = $this->trans_request["amount"];
            $this->transactionRequest->assigned_to = $this->trans_request["assigned_to"];
            $this->transactionRequest->non_vat_amount = $this->trans_request["non_vat_amount"];
            $this->transactionRequest->site_id = $this->trans_request["site_id"];
            $this->transactionRequest->site_job_number = $this->trans_request["site_job_number"];
            $this->transactionRequest->first_name = $this->trans_request["first_name"];
            $this->transactionRequest->last_name = $this->trans_request["last_name"];
            $this->transactionRequest->save();
        }

        // the following siteId has to be set otherwise it won't work, testing ignores Sessions and $_SERVER global variables stored from standard code
        Session::put('siteId', $this->payRequest["site_id"]);
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_USER_AGENT"] = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36";

        // excluding the middleware avoid the csrf mistmatch error
        $this->withoutMiddleware();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        PaymentRequests::where("token", $this->payRequest["token"])->delete();
    }
}
