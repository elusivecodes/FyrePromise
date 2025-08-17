<?php
declare(strict_types=1);

namespace Tests;

use Closure;
use Exception;
use Fyre\Promise\AsyncPromise;
use Fyre\Promise\Promise;
use Fyre\Promise\PromiseInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sleep;

final class AsyncPromiseTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAny(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve): void {
            sleep(1);
            $resolve(1);
        });

        $promise2 = new AsyncPromise(function(Closure $resolve): void {
            sleep(3);
            $resolve(3);
        });

        Promise::any([$promise1, $promise2])
            ->then(function(int $value): void {
                $this->assertSame(
                    1,
                    $value
                );
            });
    }

    #[RunInSeparateProcess]
    public function testAnyReject(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            sleep(1);
            $reject();
        });

        $promise2 = new AsyncPromise(function(Closure $resolve): void {
            sleep(3);
            $resolve(3);
        });

        Promise::any([$promise1, $promise2])
            ->then(function(int $value): void {
                $this->assertSame(
                    3,
                    $value
                );
            })
            ->catch(function(): void {
                $this->fail();
            });
    }

    #[RunInSeparateProcess]
    public function testAnyRejectAfter(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve): void {
            sleep(1);
            $resolve(1);
        });

        $promise2 = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            sleep(3);
            $reject();
        });

        Promise::any([$promise1, $promise2])
            ->then(function(int $value): void {
                $this->assertSame(
                    1,
                    $value
                );
            })
            ->catch(function(): void {
                $this->fail();
            });
    }

    #[RunInSeparateProcess]
    public function testAnyRejectAll(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            sleep(1);
            $reject(new Exception('test1'));
        });

        $promise2 = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            sleep(3);
            $reject(new Exception('test2'));
        });

        Promise::any([$promise1, $promise2])
            ->then(function(): void {
                $this->fail();
            })
            ->catch(function(): void {
                $this->assertTrue(true);
            });
    }

    #[RunInSeparateProcess]
    public function testAsync(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve): void {
            sleep(1);
            $resolve();
        });

        $promise2 = new AsyncPromise(function(Closure $resolve): void {
            sleep(1);
            $resolve();
        });

        $start = microtime(true);

        Promise::all([$promise1, $promise2]);

        $finish = microtime(true);

        $time = ($finish - $start) * 1000;

        $this->assertGreaterThan(1000, $time);
        $this->assertLessThan(1500, $time);
    }

    #[RunInSeparateProcess]
    public function testAwait(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve): void {
            $resolve('test');
        });

        $this->assertSame(
            'test',
            Promise::await($promise)
        );
    }

    #[RunInSeparateProcess]
    public function testAwaitRejection(): void
    {
        $this->expectException(Exception::class);

        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            $reject(new Exception('test'));
        });

        Promise::await($promise);
    }

    #[RunInSeparateProcess]
    public function testCatch(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            $reject();
        });

        $promise->catch(function(): void {
            $this->assertTrue(true);
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testCatchReason(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            throw new Exception('test');
        });

        $promise->catch(function(Throwable $reason): void {
            $this->assertSame(
                'test',
                $reason->getMessage()
            );
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testCatchThen(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            throw new Exception();
        });

        $promise->catch(function(): int {
            return 1;
        })->then(function(int $value) {
            $this->assertSame(
                1,
                $value
            );
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testCatchThenCatch(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            throw new Exception();
        });

        $promise->catch(function(): void {})->then(function() {
            throw new Exception('test');
        })->catch(function(Throwable $reason): void {
            $this->assertSame(
                'test',
                $reason->getMessage()
            );
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testCatchThenPromise(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            throw new Exception();
        });

        $promise->catch(function(): PromiseInterface {
            return Promise::resolve(1);
        })->then(function(int $value) {
            $this->assertSame(
                1,
                $value
            );
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testMultipleThen(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve): void {
            $resolve(1);
        });

        $results = [];

        $promise->then(function(int $value) use (&$results): void {
            $results[] = $value;
        });

        $promise->then(function(int $value) use (&$results): void {
            $results[] = $value + 1;
        });

        $promise->wait();

        $this->assertSame(
            [1, 2],
            $results
        );
    }

    #[RunInSeparateProcess]
    public function testRace(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve): void {
            sleep(1);
            $resolve(1);
        });

        $promise2 = new AsyncPromise(function(Closure $resolve): void {
            sleep(3);
            $resolve(3);
        });

        Promise::race([$promise1, $promise2])
            ->then(function(int $value): void {
                $this->assertSame(
                    1,
                    $value
                );
            });
    }

    #[RunInSeparateProcess]
    public function testRaceReject(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            sleep(1);
            $reject(new Exception('test'));
        });

        $promise2 = new AsyncPromise(function(Closure $resolve): void {
            sleep(3);
            $resolve(3);
        });

        Promise::race([$promise1, $promise2])
            ->catch(function(Throwable $reason): void {
                $this->assertSame(
                    'test',
                    $reason->getMessage()
                );
            });
    }

    #[RunInSeparateProcess]
    public function testRaceRejectAfter(): void
    {
        $promise1 = new AsyncPromise(function(Closure $resolve): void {
            sleep(1);
            $resolve(1);
        });

        $promise2 = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            sleep(3);
            $reject();
        });

        Promise::race([$promise1, $promise2])
            ->then(function(int $value): void {
                $this->assertSame(
                    1,
                    $value
                );
            })
            ->catch(function(): void {
                $this->fail();
            });
    }

    #[RunInSeparateProcess]
    public function testThen(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve): void {
            $resolve();
        });

        $promise->then(function(): void {
            $this->assertTrue(true);
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testThenResolve(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve): void {
            $resolve(1);
        });

        $promise->then(function(int $value): void {
            $this->assertSame(
                1,
                $value
            );
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testUncaughtException(): void
    {
        $this->expectException(Exception::class);

        $promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
            $reject(new Exception('test'));
        });

        $promise->wait();
    }

    #[RunInSeparateProcess]
    public function testWaitFinally(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve): void {
            $resolve();
        });

        $promise->wait();

        $promise->finally(function(): void {
            $this->assertTrue(true);
        });
    }

    #[RunInSeparateProcess]
    public function testWaitThen(): void
    {
        $promise = new AsyncPromise(function(Closure $resolve): void {
            $resolve();
        });

        $promise->wait();

        $promise->then(function(): void {
            $this->assertTrue(true);
        });
    }
}
