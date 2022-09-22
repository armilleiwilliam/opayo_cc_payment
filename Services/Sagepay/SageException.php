<?php
/**
 * Created by PhpStorm.
 * User: William Armillei
 * Date: 09/09/21
 * Time: 11:18
 */

namespace App\Services\Sagepay;

use Exception;
use Throwable;

class SageException extends Exception
{
    private $userMessage;
    private $isAjax;
    private $useFrontModal = false;
    private $transactionStatus = '';
    private $transactionDetails = '';
    private $triggerRecord = false;

    public function __construct(
        array     $customLogic = null,
        string    $message = "",
        int       $code = 0,
        Throwable $previous = null
    )
    {
        if (isset($customLogic['message']) && $customLogic['message']) {
            $this->userMessage = $customLogic['message'];
        }

        if (isset($customLogic['ajax']) && $customLogic['ajax']) {
            $this->isAjax = true;
        }

        if (isset($customLogic['useFrontModal']) && $customLogic['useFrontModal']) {
            $this->useFrontModal = true;
        }

        if (isset($customLogic['transactionStatus']) && $customLogic['transactionStatus']) {
            $this->transactionStatus = $customLogic['transactionStatus'];
        }

        if (isset($customLogic['transactionDetails']) && $customLogic['transactionDetails']) {
            $this->transactionDetails = $customLogic['transactionDetails'];
        }

        if (isset($customLogic['triggerRecord']) && $customLogic['triggerRecord']) {
            $this->triggerRecord = $customLogic['triggerRecord'];
        }

        parent::__construct($message, $code, $previous);
    }

    public function getTriggerRecord(){
        return $this->triggerRecord;
    }

    public function render()
    {
        if ($this->isAjax && $this->userMessage) {
            return response()->json([
                'error' => true,
                'errormessage' => $this->userMessage,
                'usefrontmodal' => $this->useFrontModal,
                'transactionStatus' => $this->transactionStatus,
                'transactionDetails' => $this->transactionDetails
            ]);
        }

        return response();
    }
}
