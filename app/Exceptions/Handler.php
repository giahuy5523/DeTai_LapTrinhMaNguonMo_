<?php

namespace App\Exceptions;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Psr\Log\LogLevel;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
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
        $this->renderable(function (LockTimeoutException $exception) {
            return response('Yêu cầu trước đang được xử lý. Vui lòng thử lại sau.', 503)->header('Retry-After', '2');
        });
        $this->renderable(function (QueryException $exception, $request) {
            $code = $exception->errorInfo[1] ?? null;
            if (in_array($code, [1205, 1213, 1062, 1451, 1452], true)) {
                $message = 'Dữ liệu vừa thay đổi hoặc đang được xử lý. Vui lòng tải lại trang và thử lại.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 409)
                    : back()->withErrors(['data' => $message]);
            }
        });
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
