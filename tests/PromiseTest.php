<?php
declare(strict_types=1);

namespace Tests;

use Closure;
use Fyre\Promise\Promise;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PromiseTest extends TestCase
{
    public function testAwait(): void
    {
        $promise = new Promise(function(Closure $resolve): void {
            $resolve('test');
        });

        $this->assertSame(
            'test',
            Promise::await($promise)
        );
    }

    public function testAwaitRejection(): void
    {
        $this->expectException(RuntimeException::class);

        $promise = new Promise(function(Closure $resolve, Closure $reject): void {
            $reject('test');
        });

        Promise::await($promise);
    }

    public function testCallbackException(): void
    {
        $promise = new Promise(function(Closure $resolve, Closure $reject): void {
            throw new RuntimeException('test');
        });

        $promise->wait();

        $this->assertSame(
            'test',
            $promise->getRejectedReason()
        );
    }

    public function testCanChainRejectedReason(): void
    {
        Promise::reject('test')
            ->then(function(): void {
                $this->fail();
            })
            ->catch(function(string $reason): void {
                $this->assertSame(
                    'test',
                    $reason
                );
            })
            ->wait();
    }

    public function testCatchAfterRejected(): void
    {
        $promise = Promise::reject();

        $promise->wait();

        $promise->catch(function(): void {
            $this->assertTrue(
                true
            );
        });
    }

    public function testFinally(): void
    {
        Promise::resolve()
            ->finally(function(): void {
                $this->assertTrue(
                    true
                );
            })
            ->wait();
    }

    public function testFinallyAfterSettled(): void
    {
        $promise = Promise::resolve();

        $promise->wait();

        $promise->finally(function(): void {
            $this->assertTrue(
                true
            );
        });
    }

    public function testFinallyRejection(): void
    {
        Promise::reject()
            ->finally(function(): void {
                $this->assertTrue(
                    true
                );
            })
            ->wait();
    }

    public function testReject(): void
    {
        $promise = new Promise(function(Closure $resolve, Closure $reject): void {
            $reject();
        });

        $promise->wait();

        $this->assertTrue(
            $promise->isRejected()
        );

        $this->assertTrue(
            $promise->isSettled()
        );
    }

    public function testRejectCatchException(): void
    {
        Promise::reject()
            ->catch(function(): void {
                throw new RuntimeException('test');
            })
            ->catch(function(string $reason): void {
                $this->assertSame(
                    'test',
                    $reason
                );
            })
            ->wait();
    }

    public function testRejectReason(): void
    {
        $promise = new Promise(function(Closure $resolve, Closure $reject): void {
            $reject('test');
        });

        $promise->wait();

        $this->assertSame(
            'test',
            $promise->getRejectedReason()
        );
    }

    public function testResolve(): void
    {
        $promise = new Promise(function(Closure $resolve): void {
            $resolve();
        });

        $promise->wait();

        $this->assertTrue(
            $promise->isResolved()
        );

        $this->assertTrue(
            $promise->isSettled()
        );
    }

    public function testResolveThen(): void
    {
        $promise = Promise::resolve(1)
            ->then(fn(int $result): int => $result + 1);

        $promise->wait();

        $this->assertSame(
            2,
            $promise->getResolvedValue()
        );
    }

    public function testResolveThenChain(): void
    {
        $promise = Promise::resolve()
            ->then(fn(): int => 1)
            ->then(fn(int $result): int => $result + 1);

        $promise->wait();

        $this->assertSame(
            2,
            $promise->getResolvedValue()
        );
    }

    public function testResolveThenException(): void
    {
        Promise::resolve()
            ->then(function(): void {
                throw new RuntimeException('test');
            })
            ->then(function(): void {
                $this->fail();
            })
            ->catch(function(string $reason): void {
                $this->assertSame(
                    'test',
                    $reason
                );
            })
            ->wait();
    }

    public function testResolveThenPromise(): void
    {
        $promise = Promise::resolve()
            ->then(fn(): Promise => Promise::resolve(1))
            ->then(fn(int $result): int => $result + 1);

        $promise->wait();

        $this->assertSame(
            2,
            $promise->getResolvedValue()
        );
    }

    public function testResolveValue(): void
    {
        $promise = new Promise(function(Closure $resolve): void {
            $resolve('test');
        });

        $promise->wait();

        $this->assertSame(
            'test',
            $promise->getResolvedValue()
        );
    }

    public function testRunInParallel(): void
    {
        $promise1 = new Promise(function(Closure $resolve): void {
            sleep(1);
            $resolve();
        });

        $promise2 = new Promise(function(Closure $resolve): void {
            sleep(1);
            $resolve();
        });

        $start = microtime(true);

        $all = Promise::all([$promise1, $promise2]);

        $finish = microtime(true);

        $this->assertLessThan(1500, $finish - $start);
    }

    public function testThenAfterResolved(): void
    {
        $promise = Promise::resolve();

        $promise->wait();

        $promise->then(function(): void {
            $this->assertTrue(
                true
            );
        });
    }
}
