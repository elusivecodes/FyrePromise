<?php
declare(strict_types=1);

namespace Fyre\Promise;

use Closure;
use Fyre\Promise\Exceptions\CancelledPromiseException;
use ReflectionFunction;
use RuntimeException;
use Socket;
use Throwable;

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
 * AsyncPromise
 */
class AsyncPromise extends Promise
{
    protected static int $maxRunTime = 300;

    protected static int $waitTime = 100000;

    protected int $pid;

    protected Socket $socket;

    protected int $startTime;

    /**
     * Cancel the pending Promise.
     *
     * @param string|null $message The message.
     */
    public function cancel(string|null $message = null): void
    {
        if ($this->result) {
            return;
        }

        if (!posix_kill($this->pid, SIGKILL)) {
            $lastError = posix_get_last_error();
            $lastErrorString = posix_strerror($lastError);

            throw new RuntimeException('Failed to kill '.$this->pid.' - '.$lastErrorString);
        }

        $result = Promise::reject(new CancelledPromiseException($message));

        $this->settle($result);
    }

    /**
     * Wait for the Promise to settle.
     */
    public function wait(): void
    {
        while (!$this->poll()) {
            usleep(static::$waitTime);
        }
    }

    /**
     * Call the Promise callback.
     *
     * @param Closure $callback The Promise callback.
     */
    protected function call(Closure $callback): void
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
        [$parentSocket, $childSocket] = $sockets;

        pcntl_async_signals(true);

        $pid = pcntl_fork();

        if ($pid === 0) {
            // child
            socket_close($childSocket);

            $reflect = new ReflectionFunction($callback);
            $paramCount = $reflect->getNumberOfParameters();

            $settle = static function(Throwable|null $reason, mixed $value = null) use ($parentSocket): void {
                $data = serialize([$reason, $value]);

                socket_write($parentSocket, $data);
                socket_close($parentSocket);
            };

            try {
                if ($paramCount === 0) {
                    $callback();
                } else {
                    $callback(
                        static function(mixed $value = null) use (&$settle): void {
                            if (!$settle) {
                                return;
                            }

                            $settle(null, $value);
                            $settle = null;
                        },
                        static function(Throwable|null $reason = null) use (&$settle): void {
                            if (!$settle) {
                                return;
                            }

                            $settle($reason ?? new RuntimeException());
                            $settle = null;
                        }
                    );
                }
            } catch (Throwable $e) {
                $settle($e);
                $settle = null;
            } finally {
                exit;
            }
        }

        // parent
        socket_close($parentSocket);

        $this->startTime = time();
        $this->pid = $pid;
        $this->socket = $childSocket;
    }

    /**
     * Poll the child process to determine if the Promise has settled.
     *
     * @return bool TRUE if the Promise has settled, otherwise FALSE.
     *
     * @throws RuntimeException if there is a problem handling the child process.
     */
    protected function poll(): bool
    {
        if ($this->result) {
            return true;
        }

        $processStatus = pcntl_waitpid($this->pid, $status, WNOHANG | WUNTRACED);

        if ($processStatus === 0) {
            if ($this->startTime + static::$maxRunTime < time() || pcntl_wifstopped($status)) {
                $this->cancel();
            }

            return false;
        }

        if ($processStatus !== $this->pid) {
            throw new RuntimeException('Could not reliably manage process '.$this->pid);
        }

        $result = '';
        do {
            $data = socket_read($this->socket, 4096);
            $result .= $data;
        } while ($data !== '');

        $output = unserialize($result);
        socket_close($this->socket);

        [$reason, $value] = $output;

        $result = $reason ?
            Promise::reject($reason) :
            Promise::resolve($value);

        $this->settle($result);

        return true;
    }
}
