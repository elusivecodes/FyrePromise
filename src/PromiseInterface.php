<?php
declare(strict_types=1);

namespace Fyre\Promise;

use Closure;

/**
 * PromiseInterface
 */
interface PromiseInterface
{
    /**
     * Execute a callback if the Promise is rejected.
     *
     * @param Closure $onRejected The rejected callback.
     * @return PromiseInterface A new Promise.
     */
    public function catch(Closure $onRejected): PromiseInterface;

    /**
     * Execute a callback once the Promise is settled.
     *
     * @param Closure $onFinally The settled callback.
     * @return PromiseInterface A new Promise.
     */
    public function finally(Closure $onFinally): PromiseInterface;

    /**
     * Execute a callback when the Promise is resolved.
     *
     * @param Closure|null $onFulfilled The fulfilled callback.
     * @param Closure|null $onRejected The rejected callback.
     * @return PromiseInterface A new Promise.
     */
    public function then(Closure|null $onFulfilled, Closure|null $onRejected = null): PromiseInterface;
}
