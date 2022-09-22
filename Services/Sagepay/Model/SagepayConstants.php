<?php
/**
 * Created by PhpStorm.
 * User: William Armillei
 * Date: 09/09/21
 * Time: 11:18
 */

namespace App\Services\Sagepay\Model;

class SagepayConstants
{
    private static $transaction3dRedirectCode = '2007';
    private static $transaction3dRedirectCodeV2 = '2021';
    private static $validationErrors = [
        'billingAddress.postalCode' => 'Postcode',
        'billingAddress.city'       => 'City',
        'billingAddress.address1'   => 'Address 1',
        'billingAddress.address2'   => 'Address 2',
        'customerFirstName'         => 'Firstname',
        'customerLastName'          => 'Lastname',
        'cardDetails.cardholderName'         => 'Card holder',
        'cardDetails.cardNumber'          => 'Card number',
        'cardDetails.expiryDate'          => 'Expiry date',
        'cardDetails.securityCode'  => 'Security code',
    ];
    private static $dot = ". ";
    private static $sagePayErrorCodes = [
        '400'  =>  400  ,
        '401'  =>  401  ,
        '403'  =>  403  ,
        '404'  =>  404  ,
        '405'  =>  405  ,
        '408'  =>  408  ,
        '500'  =>  500  ,
        '502'  =>  502  ,
        '422'  =>  422  ,
        'c001' =>  'c001' ,
        'c002' =>  'c002' ,
        '1000' =>  1000 ,
        '1001' =>  1001 ,
        '1002' =>  1002 ,
        '1004' =>  1004 ,
        '1005' =>  1005 ,
        '1006' =>  1006 ,
        '1007' =>  1007 ,
        '1008' =>  1008 ,
        '1009' =>  1009 ,
        '1010' =>  1010 ,
        '1011' =>  1011 ,
        '1012' =>  1012 ,
        '1013' =>  1013 ,
        '1014' =>  1014 ,
        '1015' =>  1015 ,
        '1016' =>  1016 ,
        '1017' =>  1017 ,
        '1018' =>  1018 ,
        '1019' =>  1019 ,
        '1020' =>  1020 ,
        '1021' =>  1021 ,
        '0043' =>  0043 ,
        '2002' =>  2002 ,
        '2003' =>  2003 ,
        '2015' =>  2015 ,
        '2226' =>  2226 ,
        '2022' =>  2022 ,
        '2225' =>  2225 ,
        '2227' =>  2227 ,
        '2234' =>  2234 ,
        '3006' =>  3006 ,
        '2236' =>  2236 ,
        '4021' =>  4021 ,
        '4001' =>  4001 ,
        '3115' =>  3115 ,
        '3110' =>  3110 ,
        '3108' =>  3108 ,
        '3027' =>  3027 ,
        '3031' =>  3031 ,
        '3032' =>  3032 ,
        '3034' =>  3034 ,
        '3141' =>  3141 ,
        '3147' =>  3147 ,
        '3050' =>  3050 ,
        '3051' =>  3051 ,
        '3052' =>  3052 ,
        '3053' =>  3053 ,
        '3057' =>  3057 ,
        '3059' =>  3059 ,
        '3069' =>  3069 ,
        '3089' =>  3089 ,
        '3078' =>  3078 ,
        '4008' =>  4008 ,
        '4009' =>  4009 ,
        '4020' =>  4020 ,
        '4047' =>  4047 ,
        '5003' =>  5003 ,
        '5010' =>  5010 ,
        '5011' =>  5011 ,
        '5013' =>  5013 ,
        '5017' =>  5017 ,
        '5018' =>  5018 ,
        '5036' =>  5036 ,
        '5055' =>  5055 ,
        '5087' =>  5087 ,
        '5045' =>  5045 ,
        '6000' =>  6000 ,
        '8890' =>  8890 ,
        '9998' =>  9998 ,
        '9999' =>  9999 ,
        '10000' => 10000,

    ];
    private static $timeoutCode = '2002';
    private static $authRejection3d = '4026';
    private static $authRejection3dCanNotAuthorizedCard = '4027';
    private static $invalidCardRange = '4021';
    private static $bankDeclined = '2000';
    private static $authenticated = '0000';
    private static $allmatched = 'AllMatched';
    private static $notAuthenticated = 'NotAuthenticated';
    private static $cardNotEnrolled = 'CardNotEnrolled';
    private static $issuerNotEnrolled = 'IssuerNotEnrolled';
    private static $threeDAuthenticated = 'Authenticated';
    private static $attemptOnly = 'AttemptOnly';
    private static $incomplete = 'Incomplete';
    private static $notchecked = 'NotChecked';
    private static $rejected = 'Rejected';
    private static $error = 'Error';
    private static $noauth = '2001';
    private static $notmatched = 'NotMatched';
    private static $notmatchedclientview = 'NOT MATCHED';
    private static $allmatch = 'ALL MATCH';
    private static $is3Dv2 = ' (3Dv2)';
    private static $is3Dv1 = ' (3Dv1)';

    public static function getAuthRejection3dMessage($furtherMessage = null, $furtherMessageOnly = false)
    {
        return '
        <script>function refreshform() {
            var path = window.parent.location.pathname;
            window.parent.location.href = path.substr(-6) === "reload" ? path : path + \'/reload#primaryOrderForm\';
        }</script>
        <div class="modal">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">' . __("msg.error") . '</h5>
                    </div>
                    <div class="modal-body">
                        <p>' . $furtherMessage .  self::$dot . ($furtherMessageOnly == false ?  __('msg.three-d-failed') :'') .  self::$dot .' </p>
                    </div>
                    <div class="modal-footer">
                        <div class="row">
                            <div class="col-6 text-center">
                                <button type="button" class="btn btn-secondary btn-lg text-center" id="removeModal">' . __('msg.try-again') . '</button>
                            </div>
                            <div class="col-6 text-center">
                                <button type="button" class="btn btn-secondary btn-lg text-center" onclick="window.parent.location.reload()">' . __('msg.reload') . '</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    public static function getAuthRejection3d()
    {
        return self::$authRejection3d;
    }

    public static function getCardNotAuthorized()
    {
        return self::$authRejection3dCanNotAuthorizedCard;
    }

    public static function getInvalidCardNumber()
    {
        return self::$invalidCardRange;
    }

    public static function getTimeOutCode()
    {
        return self::$timeoutCode;
    }

    public static function getValidationErrors()
    {
        return self::$validationErrors;
    }

    public static function getTransaction3dRedirectCode()
    {
        return self::$transaction3dRedirectCode;
    }

    public static function getTransaction3dRedirectCodeV2()
    {
        return self::$transaction3dRedirectCodeV2;
    }

    public static function getSageErrorCodes()
    {
        return self::$sagePayErrorCodes;
    }

    public static function getAuthenticated()
    {
        return self::$authenticated;
    }

    public static function getAllMatched()
    {
        return self::$allmatched;
    }

    public static function get3DAuthenticated()
    {
        return self::$threeDAuthenticated;
    }

    public static function getNotAuthenticated()
    {
        return self::$notAuthenticated;
    }

    public static function getError()
    {
        return self::$error;
    }

    public static function getAttemptOnly()
    {
        return self::$attemptOnly;
    }

    public static function getIncomplete()
    {
        return self::$incomplete;
    }

    public static function getRejected()
    {
        return self::$rejected;
    }

    public static function getNoAuth()
    {
        return self::$noauth;
    }

    public static function getSageBankDeclined()
    {
        return self::$bankDeclined;
    }

    public static function getCardNotEnrolled()
    {
        return self::$cardNotEnrolled;
    }

    public static function getIssuerNotEnrolled()
    {
        return self::$issuerNotEnrolled;
    }

    public static function getNotChecked()
    {
        return self::$notchecked;
    }

    public static function getNotMatched()
    {
        return self::$notmatched;
    }

    public static function getNotMatchedClientView()
    {
        return self::$notmatchedclientview;
    }

    public static function getAllMatch()
    {
        return self::$allmatch;
    }

    public static function getIs3DvOne()
    {
        return self::$is3Dv1;
    }

    public static function getIs3DvTwo()
    {
        return self::$is3Dv2;
    }
}
