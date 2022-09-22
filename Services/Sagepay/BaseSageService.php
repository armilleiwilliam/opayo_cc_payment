<?php
/**
 * Created by PhpStorm.
 * User: William Armillei
 * Date: 09/09/21
 * Time: 11:18
 */

namespace App\Services\Sagepay;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use App\CredentialsSite;

class BaseSageService
{
    protected $err = '';
    protected $details = [];
    protected $response = '';
    protected $errService;
    protected $requestInfo;

    public function __construct($errService = null)
    {
        if ($errService) {
            $this->errService = $errService;
        } else {
            $this->errService = new SagePayExceptionService();
        }
    }

    protected function decodeResponse($true = true)
    {
        $this->response = json_decode($this->response, $true);
    }

    protected function assessIfErrors()
    {
        $this->errService->handleError([
            'response' => $this->response,
            'error' => $this->err,
            'curlInfo' => $this->requestInfo
        ]);
    }

    protected function setupConnectionDetails()
    {
        $env = env("APP_ENV") ?? "beta";
        $siteId = Session::get('siteId');

        if ($credentialsSite = CredentialsSite::where('site_id', '=', $siteId)
            ->where('env', '=', $env)
            ->with('credentials')
            ->first()) {
            $this->details = [
                'vendorName' => $credentialsSite->credentials->vendorName,
                'integrationKey' => $credentialsSite->credentials->integrationKey,
                'integrationPassword' => $credentialsSite->credentials->integrationPassword,
                'transactionEndpoint' => $credentialsSite->credentials->transaction,
                'srcEndpoint' => $credentialsSite->credentials->src,
                'merchantIdEndpoint' => $credentialsSite->credentials->merchant,
                'threeDSecureEndpoint' => $credentialsSite->credentials->{'3dsecure'},
                'cardIdentifierEndpoint' => $credentialsSite->credentials->cardIdentifier
            ];
            return $this->details;
        } else {
            return abort(500, 'There is no payment account linked to this cost centre - unable to process
            payment. Please, contact the admin team to report the issue.');
        }
    }
}
