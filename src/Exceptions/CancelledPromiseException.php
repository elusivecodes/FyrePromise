<?php
declare(strict_types=1);

namespace Fyre\Promise\Exceptions;

use Exception;
use Throwable;

/**
 * CancelledPromiseException
 */
class CancelledPromiseException extends Exception
{
    /**
     * New CancelledPromiseException constructor.
     *
     * @param string|null $message The message.
     * @param int|null $code The error code.
     * @param Throwable|null $previous The previous exception.
     */
    public function __construct(string|null $message = null, int|null $code = null, Throwable|null $previous = null)
    {
        parent::__construct($message ?? 'Promise was cancelled.', $code, $previous);
    }
}
