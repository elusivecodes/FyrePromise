<?php
declare(strict_types=1);

namespace Fyre\Promise;

use Closure;
use RuntimeException;
use Socket;
use Throwable;

use function array_fill;
use function call_user_func;
use function count;
use function pcntl_async_signals;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wifstopped;
use function posix_get_last_error;
use function posix_kill;
use function posix_strerror;
use function serialize;
use function socket_close;
use function socket_create_pair;
use function socket_read;
use function socket_write;
use function time;
use function unserialize;
use function usleep;

use const AF_UNIX;
use const SIGKILL;
use const SOCK_STREAM;
use const WNOHANG;
use const WUNTRACED;

/**
 * Promise
 */
class Promise
{
    protected Closure $callback;

    protected array $finallyCallbacks = [];

    protected array $fulfilledCallbacks = [];

    protected bool $isRejected = false;

    protected bool $isResolved = false;

    protected bool $isSettled = false;

    protected int $maxRunTime = 300;

    protected int $pid;

    protected array $rejectedCallbacks = [];

    protected string|null $rejectedReason = null;

    protected $resolvedValue;

    protected Socket $socket;

    protected int $startTime;

    /**
     * New Promise constructor.
     *
     * @param Closure $callback The Promise callback.
     * @param bool $sync Whether to execute the Promise synchronously.
     */
    public function __construct(Closure $callback, bool $sync = false)
    {
        $this->callback = $callback;

        if ($sync) {
            return $this->exec();
        }

        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
        [$parentSocket, $childSocket] = $sockets;

        pcntl_async_signals(true);

        $pid = pcntl_fork();

        if ($pid === 0) {
            // child
            socket_close($childSocket);

            $this->exec();

            $data = serialize([
                'isResolved' => $this->isResolved,
                'isRejected' => $this->isRejected,
                'resolvedValue' => $this->resolvedValue,
                'rejectedReason' => $this->rejectedReason,
            ]);

            socket_write($parentSocket, $data);
            socket_close($parentSocket);
            exit;
        }

        // parent
        socket_close($parentSocket);

        $this->startTime = time();
        $this->pid = $pid;
        $this->socket = $childSocket;
    }

    /**
     * Wait for all promises to settle.
     *
     * @param array $promises The promises.
     * @return Promise The Promise.
     */
    public static function all(array $promises = []): static
    {
        return new Promise(function(Closure $resolve, Closure $reject) use ($promises): void {
            $count = count($promises);
            $results = array_fill(0, $count, null);

            while ($promises !== []) {
                foreach ($promises as $i => $promise) {
                    $promise->poll();

                    if ($promise->isRejected()) {
                        $reject($promise->getRejectedReason());

                        return;
                    }

                    if ($promise->isResolved()) {
                        $results[$i] = $promise->getResolvedValue();
                        unset($promises[$i]);

                        continue;
                    }
                }

                usleep(100000);
            }

            $resolve($results);
        }, true);
    }

    /**
     * Wait for a Promise to settle.
     *
     * @param Promise $promise The Promise.
     * @return mixed The resolved value.
     *
     * @throws RuntimeException If the Promise is rejected.
     */
    public static function await(Promise $promise): mixed
    {
        $promise->wait();

        if ($promise->isRejected()) {
            throw new RuntimeException($promise->getRejectedReason());
        }

        return $promise->getResolvedValue();
    }

    /**
     * Create a Promise that rejects.
     *
     * @param string|null $reason The rejection reason.
     * @return Promise The Promise.
     */
    public static function reject(string|null $reason = null): static
    {
        return new Promise(
            fn($resolve, $reject) => $reject($reason)
        );
    }

    /**
     * Create a Promise that resolves.
     *
     * @param mixed $value The resolved value.
     * @return Promise The Promise.
     */
    public static function resolve($value = null): static
    {
        if ($value instanceof Promise) {
            return $value;
        }

        return new Promise(
            fn($resolve) => $resolve($value)
        );
    }

    /**
     * Execute a callback if the Promise is rejected.
     */
    public function catch(Closure $onRejected): static
    {
        return $this->then(null, $onRejected);
    }

    /**
     * Execute a callback when the Promise is settled.
     *
     * @param Closure|null $onFinally The settled callback.
     * @return Promise The Promise.
     */
    public function finally(Closure $onFinally): static
    {
        if ($this->isSettled) {
            call_user_func($onFinally);
        } else {
            $this->finallyCallbacks[] = $onFinally;
        }

        return $this;
    }

    /**
     * Get the rejected reason.
     *
     * @return string|null The rejected reason.
     */
    public function getRejectedReason(): string|null
    {
        return $this->rejectedReason;
    }

    /**
     * Get the resolved value.
     *
     * @return mixed The resolved value.
     */
    public function getResolvedValue(): mixed
    {
        return $this->resolvedValue;
    }

    /**
     * Determine whether the Promise was rejected.
     *
     * @return bool TRUE if the Promise was rejected, otherwise FALSE.
     */
    public function isRejected(): bool
    {
        return $this->isRejected;
    }

    /**
     * Determine whether the Promise has resolved.
     *
     * @return bool TRUE if the Promise has resolved, otherwise FALSE.
     */
    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    /**
     * Determine whether the Promise has settled.
     *
     * @return bool TRUE if the Promise has settled, otherwise FALSE.
     */
    public function isSettled(): bool
    {
        return $this->isSettled;
    }

    /**
     * Poll the child process to determine if the Promise has settled.
     *
     * @return bool TRUE if the Promise has settled, otherwise FALSE.
     *
     * @throws RuntimeException if there is a problem handling the child process.
     */
    public function poll(): bool
    {
        if ($this->isSettled) {
            return true;
        }

        $processStatus = pcntl_waitpid($this->pid, $status, WNOHANG | WUNTRACED);

        if ($processStatus === 0) {
            if ($this->startTime + $this->maxRunTime < time() || pcntl_wifstopped($status)) {
                if (!posix_kill($this->pid, SIGKILL)) {
                    $lastError = posix_get_last_error();
                    $lastErrorString = posix_strerror($lastError);
                    throw new RuntimeException('Failed to kill '.$this->pid.' - '.$lastErrorString);
                }
            }

            return false;
        }

        if ($processStatus !== $this->pid) {
            throw new RuntimeException('Could not reliably manage process '.$this->pid);
        }

        $result = socket_read($this->socket, 4096);
        $output = unserialize($result);
        socket_close($this->socket);

        $this->isSettled = true;
        $this->isRejected = $output['isRejected'];
        $this->isResolved = $output['isResolved'];
        $this->rejectedReason = $output['rejectedReason'];
        $this->resolvedValue = $output['resolvedValue'];

        return true;
    }

    /**
     * Execute a callback when the Promise is resolved.
     *
     * @param Closure|null $onFulfilled The fulfilled callback.
     * @param Closure|null $onRejected The rejected callback.
     * @return Promise The Promise.
     */
    public function then(Closure|null $onFulfilled = null, Closure|null $onRejected = null): static
    {
        if ($onFulfilled) {
            if ($this->isResolved) {
                call_user_func($onFulfilled, $this->resolvedValue);
            } else if (!$this->isSettled) {
                $this->fulfilledCallbacks[] = $onFulfilled;
            }
        }

        if ($onRejected) {
            if ($this->isRejected) {
                call_user_func($onRejected, $this->rejectedReason);
            } else if (!$this->isSettled) {
                $this->rejectedCallbacks[] = $onRejected;
            }
        }

        return $this;
    }

    /**
     * Wait for the Promise to settle.
     *
     * @return Promise The Promise.
     */
    public function wait(): static
    {
        while (!$this->isSettled) {
            if (!$this->poll()) {
                usleep(100000);

                continue;
            }

            if ($this->isResolved) {
                foreach ($this->fulfilledCallbacks as $onFulfilled) {
                    try {
                        if ($this->resolvedValue instanceof Promise) {
                            $this->resolvedValue = static::await($this->resolvedValue);
                        }

                        $this->resolvedValue = call_user_func($onFulfilled, $this->resolvedValue);
                    } catch (Throwable $e) {
                        $this->isRejected = true;
                        $this->isResolved = false;
                        $this->rejectedReason = $e->getMessage();
                        $this->resolvedValue = null;
                        break;
                    }
                }
            }

            if ($this->isRejected) {
                foreach ($this->rejectedCallbacks as $onRejected) {
                    try {
                        call_user_func($onRejected, $this->rejectedReason);
                    } catch (Throwable $e) {
                        $this->rejectedReason = $e->getMessage();
                    }
                }
            }

            foreach ($this->finallyCallbacks as $onFinally) {
                call_user_func($onFinally);
            }
        }

        return $this;
    }

    /**
     * Execute the callback.
     */
    protected function exec(): void
    {
        $resolve = function($value = null): void {
            if ($this->isSettled) {
                return;
            }

            $this->isSettled = true;
            $this->isResolved = true;
            $this->resolvedValue = $value;
        };

        $reject = function(string|null $reason = null): void {
            if ($this->isSettled) {
                return;
            }

            $this->isSettled = true;
            $this->isRejected = true;
            $this->rejectedReason = $reason;
        };

        try {
            call_user_func($this->callback, $resolve, $reject);
        } catch (Throwable $e) {
            $reject($e->getMessage());
        }
    }
}
