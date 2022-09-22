<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\ExceptionRecord;
use Illuminate\Support\Facades\Session;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable
     * @return void
     */
    public function report(Throwable $exception)
    {
        $costumerUsingCreditCardForm = "";
        $costumerId = NULL;
        if($costumerDetails = unserialize(Session::get('currentBasket'))){
            $costumerUsingCreditCardForm = "Client: " . $costumerDetails["firstName"] . " " . $costumerDetails["lastName"];
            $costumerId = $costumerDetails["site_client_id"];
        }

        $record = new ExceptionRecord();
        $record->file= $exception->getFile();
        $record->code= $exception->getCode();
        $record->line= $exception->getLine();
        $record->message= $exception->getMessage();
        $record->user_name = $costumerUsingCreditCardForm;
        $record->user_id = $costumerId;
        $record->url= url()->current() ?? "";
        $record->save();
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception);
    }
}
