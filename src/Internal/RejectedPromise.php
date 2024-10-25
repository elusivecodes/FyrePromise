<?php
declare(strict_types=1);

namespace Fyre\Promise\Internal;

use Closure;
use Fyre\Promise\Promise;
use Fyre\Promise\PromiseInterface;
use Throwable;

/**
 * RejectedPromise
 */
class RejectedPromise implements PromiseInterface
{
    protected bool $handled = false;

    protected Throwable $reason;

    /**
     * New RejectedPromise constructor.
     *
     * @param Throwable $reason The rejection reason.
     */
    public function __construct(Throwable $reason)
    {
        $this->reason = $reason;
    }

    /**
     * New RejectedPromise destructor.
     */
    public function __destruct()
    {
        if ($this->handled) {
            return;
        }

        throw $this->reason;
    }

    /**
     * Execute a callback if the Promise is rejected.
     *
     * @param Closure $onRejected The rejected callback.
     * @return PromiseInterface A new Promise.
     */
    public function catch(Closure $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    /**
     * Execute a callback once the Promise is settled.
     *
     * @param Closure $onFinally The settled callback.
     * @return PromiseInterface A new Promise.
     */
    public function finally(Closure $onFinally): PromiseInterface
    {
        return $this->then(
            null,
            fn(Throwable $reason): PromiseInterface => Promise::resolve($onFinally())
                ->then(fn(): self => Promise::reject($reason))
        );
    }

    /**
     * Execute a callback when the Promise is resolved.
     *
     * @param Closure|null $onFulfilled The fulfilled callback.
     * @param Closure|null $onRejected The rejected callback.
     * @return PromiseInterface A new Promise.
     */
    public function then(Closure|null $onFulfilled, Closure|null $onRejected = null): PromiseInterface
    {
        if ($onRejected === null) {
            return $this;
        }

        $this->handled = true;

        try {
            return Promise::resolve($onRejected($this->reason));
        } catch (Throwable $e) {
            return new static($e);
        }
    }
}
