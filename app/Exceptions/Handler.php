<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Customize the exception rendering for the application.
     */
    public function render($request, Throwable $e)
    {
        // 🔑 419 Page Expired → netjes naar login redirecten
        if ($e instanceof TokenMismatchException) {
            return redirect()->route('login')
                ->withErrors(['message' => 'Je sessie is verlopen, log opnieuw in.']);
        }

        return parent::render($request, $e);
    }
}
