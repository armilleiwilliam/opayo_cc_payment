<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientNotes;
use App\Models\ClientSms;
use Illuminate\Http\JsonResponse;

class MessagebirdService
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->messageBird = new \MessageBird\Client(env('MESSAGEBIRD_ACCESS_KEY'));
        $this->messageNotDeliveredToPhoneNumbers = [];
        $this->messageDeliveredToPhoneNumbers = [];
    }

    /**
     * @param $bodymessage
     * @param array $phones
     * @return \MessageBird\Objects
     */
    public function toMessagebird($bodymessage, $phones = [])
    {
        $this->message = new \MessageBird\Objects\message();
        $this->message->originator = env('MESSAGEBIRD_SMS_ORIGINATOR');
        $this->message->recipients = $phones;

        $this->message->body = $bodymessage;
        return $this->messageBird->messages->create($this->message);
        //dd($this->messageBird->messages->create($this->message));
    }

    /**
     * @param $message
     * @param $phones
     * @param null $client
     * @param null $request
     * @return JsonResponse or \MessageBird
     */
    public function processMessageDeliveryResult($request)
    {
        // add a mobile numberif (isset($request->tel)) {
        $phones[] = $request->tel;

        // start sending messages to phone
        $result = $this->toMessagebird($request->textMessage, $phones);

        // loop each mobile number processed and return/store the related successful message
        // related message
        if ($result && !empty($result->recipients->items) && $result->recipients->totalCount > 0) {
            foreach ($result->recipients->items as $item) {
                if ($item->status !== "sent") {
                    $smsDeliverySuccessmessage = "SMS delivery attempt failed!";
                    $this->messageNotDeliveredToPhoneNumbers[] = $item->recipient;
                } else {
                    $smsDeliverySuccessmessage = "SMS sent to " . $item->recipient;
                    $this->messageDeliveredToPhoneNumbers[] = $item->recipient;
                }
            }
        }

        $this->phonesWhichSmsNotDeliveredTo();

        return response()->json(["successMobileSmsDelivery" => $this->messageDeliveredToPhoneNumbers, "failMobileSmsDelivery" => $this->messageNotDeliveredToPhoneNumbers]);
    }

    /**
     * The following code filters the returned birdMessage phone numbers after sent, they are returned because the delivery was succesfull. They are compared
     * with the list given to birdMessage at the beginning. The ones not found in the birdMessage list are the ones which failed and are added to
     * $this->messageNotDeliveredToPhoneNumbers.
     *
     * $this->messageDeliveredToPhoneNumbers = contains the phones processed by messageBird, which takes off "0" from the beginning
     * and replace it with "44" or "+" or "+44" or "". Taken this into account it compares the possible new phone numbers values and compare them
     * with the original phones submitted ($this- >message->recipients)
     *
     * @param array $phoneNumbersPossibleFormats - it stores the life of possible formats a phone number might have
     * @param string $phoneReformatted - the phone number without the sign "0" or "+"
     * @param array $this- >message->recipients
     *
     */
    public function phonesWhichSmsNotDeliveredTo()
    {
        if (!empty($this->message->recipients)) {
            $smsPhoneNotSuccesfull = $this->messageNotDeliveredToPhoneNumbers;

            // loop each phone number provided to birdMessage and compare with the ones returned to find out which one did not go through
            foreach ($this->message->recipients as $key => $phone) {

                $phoneNumbersPossibleFormats = array($phone);

                // check if the phone number starts with '0' and remove it
                if (strpos($phone, "0") == 0 && strpos($phone, "0") !== false) {
                    $phoneReformatted = substr($phone, 1);

                    // check if the phone number starts with '00' and remove it
                    if (strpos($phone, "00") == 0 && strpos($phone, "00") !== false) {
                        $phoneReformatted = substr($phoneReformatted, 1);
                    }

                    $phoneNumbersPossibleFormats = array("44" . $phoneReformatted, "+44" . $phoneReformatted, "+" . $phoneReformatted, $phoneReformatted, $phone);
                } else if (strpos($phone, "+") == 0) {
                    $phoneReformatted = substr($phone, 1);
                    $phoneNumbersPossibleFormats = array($phoneReformatted, "0" . $phoneReformatted, $phone);
                }

                // compare the two lists of numbers created and if at least an element in common found it won't store the number in the not delevered phones list, because
                // it means the phone number has been found in the list of successfully delivered ones
                if (count(array_intersect($phoneNumbersPossibleFormats, $this->messageDeliveredToPhoneNumbers)) && !count(array_intersect($phoneNumbersPossibleFormats, $smsPhoneNotSuccesfull))) {
                    continue;
                }
                $this->messageNotDeliveredToPhoneNumbers[] = $phone;
            }
        }
    }
}
