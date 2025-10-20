<?php
declare(strict_types=1);

namespace Fyre\Promise;

use Closure;
use Fyre\Promise\Internal\FulfilledPromise;
use Fyre\Promise\Internal\RejectedPromise;
use Fyre\Utility\Traits\MacroTrait;
use Fyre\Utility\Traits\StaticMacroTrait;
use LogicException;
use ReflectionFunction;
use RuntimeException;
use Throwable;

/**
 * Promise
 */
class Promise implements PromiseInterface
{
    use MacroTrait;
    use StaticMacroTrait;

    protected array $handlers = [];

    protected PromiseInterface|null $result = null;

    /**
     * Wait for all promises to resolve.
     *
     * @param array $promisesOrValues The promises or values.
     * @return PromiseInterface A new Promise.
     */
    public static function all(iterable $promisesOrValues): PromiseInterface
    {
        return new Promise(static function(Closure $resolve, Closure $reject) use ($promisesOrValues): void {
            $values = [];
            $rejected = false;

            while ($promisesOrValues !== []) {
                foreach ($promisesOrValues as $i => $promiseOrValue) {
                    if ($rejected) {
                        if ($promiseOrValue instanceof AsyncPromise) {
                            $promiseOrValue->catch(static function(): void {});
                        }

                        unset($promisesOrValues[$i]);

                        continue;
                    }

                    if ($promiseOrValue instanceof AsyncPromise && !$promiseOrValue->poll()) {
                        continue;
                    }

                    Promise::resolve($promiseOrValue)->then(
                        static function(mixed $value = null) use ($i, &$values): void {
                            $values[$i] = $value;
                        },
                        static function(Throwable|null $reason = null) use (&$rejected, $reject): void {
                            $rejected = true;
                            $reject($reason);
                        }
                    );

                    unset($promisesOrValues[$i]);
                }
            }

            if (!$rejected) {
                $resolve($values);
            }
        });
    }

    /**
     * Wait for any promise to resolve.
     *
     * @param array $promisesOrValues The promises or values.
     * @return PromiseInterface A new Promise.
     */
    public static function any(iterable $promisesOrValues): PromiseInterface
    {
        return new Promise(static function(Closure $resolve, Closure $reject) use ($promisesOrValues): void {
            $resolved = false;

            while ($promisesOrValues !== []) {
                foreach ($promisesOrValues as $i => $promiseOrValue) {
                    if ($resolved) {
                        if ($promiseOrValue instanceof AsyncPromise) {
                            $promiseOrValue->catch(static function(): void {});
                        }

                        unset($promisesOrValues[$i]);

                        continue;
                    }

                    if ($promiseOrValue instanceof AsyncPromise && !$promiseOrValue->poll()) {
                        continue;
                    }

                    Promise::resolve($promiseOrValue)->then(
                        static function(mixed $value = null) use (&$resolved, $resolve): void {
                            $resolved = true;
                            $resolve($value);
                        },
                        static function(): void {}
                    );

                    unset($promisesOrValues[$i]);
                }
            }

            if (!$resolved) {
                $reject();
            }
        });
    }

    /**
     * Await the result of a Promise.
     *
     * @param Promise $promise The Promise.
     * @return mixed The resolved value.
     */
    public static function await(PromiseInterface $promise): mixed
    {
        if ($promise instanceof AsyncPromise) {
            $promise->wait();
        }

        $result = null;
        $promise->then(
            static function(mixed $value) use (&$result): void {
                $result = $value;
            },
            static function(Throwable $e): void {
                throw $e;
            }
        );

        return $result;
    }

    /**
     * Wait for the first promise to resolve.
     *
     * @param array $promisesOrValues The promises or values.
     * @return PromiseInterface A new Promise.
     */
    public static function race(iterable $promisesOrValues): PromiseInterface
    {
        return new Promise(static function(Closure $resolve, Closure $reject) use ($promisesOrValues): void {
            $settled = false;

            while ($promisesOrValues !== []) {
                foreach ($promisesOrValues as $i => $promiseOrValue) {
                    if ($settled) {
                        if ($promiseOrValue instanceof AsyncPromise) {
                            $promiseOrValue->catch(static function(): void {});
                        }

                        unset($promisesOrValues[$i]);

                        continue;
                    }

                    if ($promiseOrValue instanceof AsyncPromise && !$promiseOrValue->poll()) {
                        continue;
                    }

                    Promise::resolve($promiseOrValue)->then($resolve, $reject)->finally(static function() use (&$settled): void {
                        $settled = true;
                    });

                    unset($promisesOrValues[$i]);
                }
            }
        });
    }

    /**
     * Create a rejected Promise.
     *
     * @param Throwable|null $reason The rejection reason.
     * @return RejectedPromise The RejectedPromise.
     */
    public static function reject(Throwable|null $reason = null): RejectedPromise
    {
        return new RejectedPromise($reason ?? new RuntimeException());
    }

    /**
     * Create a Promise resolved from a value.
     *
     * @param mixed $value The value to resolve.
     * @return PromiseInterface The resolved Promise.
     */
    public static function resolve(mixed $value = null): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        return new FulfilledPromise($value);
    }

    /**
     * New Promise constructor.
     *
     * @param Closure $callback The Promise callback.
     */
    public function __construct(Closure $callback)
    {
        $this->call($callback);
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
            static fn(mixed $value): PromiseInterface => Promise::resolve($onFinally())
                ->then(static fn(): mixed => $value),
            static fn(Throwable $reason): PromiseInterface => Promise::resolve($onFinally())
                ->then(static fn(): RejectedPromise => Promise::reject($reason))
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
        if ($this->result) {
            return $this->result->then($onFulfilled, $onRejected);
        }

        return new Promise(
            function(Closure $resolve, Closure $reject) use ($onFulfilled, $onRejected): void {
                $this->handlers[] = static function(PromiseInterface $promise) use ($resolve, $reject, $onFulfilled, $onRejected): void {
                    $promise = $promise->then($onFulfilled, $onRejected);

                    if ($promise instanceof Promise && $promise->result) {
                        $promise->handlers[] = static function(Promise $promise) use ($resolve, $reject): void {
                            $promise->then($resolve, $reject);
                        };
                    } else {
                        $promise->then($resolve, $reject);
                    }
                };
            }
        );
    }

    /**
     * Call the Promise callback.
     *
     * @param Closure $callback The Promise callback.
     */
    protected function call(Closure $callback): void
    {
        $reflect = new ReflectionFunction($callback);
        $paramCount = $reflect->getNumberOfParameters();

        try {
            if ($paramCount === 0) {
                $callback();
            } else {
                $target = & $this;

                $callback(
                    static function(mixed $value = null) use (&$target): void {
                        if (!$target) {
                            return;
                        }

                        $target->settle(static::resolve($value));
                        $target = null;
                    },
                    function(Throwable|null $reason = null) use (&$target): void {
                        if (!$target || $target->result) {
                            return;
                        }

                        $target = null;

                        $this->settle(static::reject($reason));
                    }
                );
            }
        } catch (Throwable $e) {
            $this->settle(static::reject($e));
        }
    }

    /**
     * Settle the resulting Promise.
     *
     * @param PromiseInterface $result The resulting Promise.
     *
     * @throws LogicException If a promise is resolved with itself.
     */
    protected function settle(PromiseInterface $result): void
    {
        if ($this->result) {
            throw new LogicException('Cannot resolve a promise that has already settled.');
        }

        while ($result instanceof self && $result->result) {
            $result = $result->result;
        }

        if ($result === $this) {
            $result = Promise::reject(new LogicException('Cannot resolve a promise with itself.'));
        }

        $handlers = $this->handlers;

        $this->handlers = [];
        $this->result = $result;

        foreach ($handlers as $handle) {
            $handle($result);
        }
    }
}
