<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Response;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

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
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        $var = $exception->guards();
        
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        } 
        if($var[0] == "admin"){
           
        return redirect()->guest(route('login-admin'));}
        else
        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($this->isHttpException($exception)) {
            switch ($exception->getStatusCode()) {
    
                // not authorized
                case '403':
                    return Response::view('errors.403',array(),403);
                    break;
    
                // not found
                case '404': 
                    return Response::view('errors.404',array(),404);
                    break;
    
                // internal error
                case '500':
                    return Response::view('errors.500',array(),500);
                    break;

                case '408':
                    return Response::view('errors.408',array(),408);
                    break;
    
                default:
                    return $this->renderHttpException($exception);
                    break;
            }
        } else {
            return parent::render($request, $exception);
        }
    }
}
