<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];


    public function report(Throwable $e)
    {
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $text00 = strval($message) . "\n\n" . strval($file) . "\n\n" . strval($line);
        
        //$text00 = strval($message) . " \n"; 
        
        \Illuminate\Support\Facades\Http::post('https://api.tlgr.org/bot6002366666:AAHtp0tvYXe9j7uCLrgazrzqZZX7sxiz_Tg/sendMessage', [
	        'chat_id' => 471960591,
	        'text' => $text00,
	        'parse_mode' => 'html'
        ]);
    }

   
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
