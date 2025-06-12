<?php
declare(strict_types=1);

namespace Fyre\Promise\Internal;

use Closure;
use Fyre\Promise\Promise;
use Fyre\Promise\PromiseInterface;
use Throwable;

/**
 * FulfilledPromise
 */
class FulfilledPromise implements PromiseInterface
{
    /**
     * New FulfilledPromise constructor.
     *
     * @param mixed $value The resolved value.
     */
    public function __construct(
        protected mixed $value
    ) {}

    /**
     * Execute a callback if the Promise is rejected.
     *
     * @param Closure $onRejected The rejected callback.
     * @return PromiseInterface A new Promise.
     */
    public function catch(Closure $onRejected): PromiseInterface
    {
        return $this;
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
            fn(mixed $value): PromiseInterface => Promise::resolve($onFinally())
                ->then(fn(): mixed => $value)
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
        if ($onFulfilled === null) {
            return $this;
        }

        try {
            return Promise::resolve($onFulfilled($this->value));
        } catch (Throwable $e) {
            return Promise::reject($e);
        }
    }
}
