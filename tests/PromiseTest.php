<?php
declare(strict_types=1);

namespace Tests;

use Closure;
use Exception;
use Fyre\Promise\Promise;
use Fyre\Promise\PromiseInterface;
use Fyre\Utility\Traits\MacroTrait;
use Fyre\Utility\Traits\StaticMacroTrait;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_diff;
use function class_uses;

final class PromiseTest extends TestCase
{
    public function testCatch(): void
    {
        (new Promise(function(Closure $resolve, Closure $reject): void {
            $reject();
        }))->catch(function(): void {
            $this->assertTrue(true);
        });
    }

    public function testCatchCatch(): void
    {
        $this->expectNotToPerformAssertions();

        Promise::reject()
            ->catch(function(): void {})
            ->catch(function(): void {
                $this->fail();
            });
    }

    public function testCatchCatchException(): void
    {
        Promise::reject()
            ->catch(function(): void {
                throw new Exception('test');
            })
            ->catch(function(Throwable $reason): void {
                $this->assertSame(
                    'test',
                    $reason->getMessage()
                );
            });
    }

    public function testCatchException(): void
    {
        (new Promise(function(Closure $resolve, Closure $reject): void {
            throw new Exception('test');
        }))->catch(function(Throwable $reason): void {
            $this->assertSame(
                'test',
                $reason->getMessage()
            );
        });
    }

    public function testCatchFinally(): void
    {
        Promise::reject()
            ->catch(function(): void {})
            ->finally(function(): void {
                $this->assertTrue(true);
            });
    }

    public function testCatchReason(): void
    {
        (new Promise(function(Closure $resolve, Closure $reject): void {
            $reject(new Exception('test'));
        }))->catch(function(Throwable $reason): void {
            $this->assertSame(
                'test',
                $reason->getMessage()
            );
        });
    }

    public function testCatchThen(): void
    {
        (new Promise(function(Closure $resolve, Closure $reject): void {
            throw new Exception();
        }))->catch(function(): int {
            return 1;
        })->then(function(int $value) {
            $this->assertSame(
                1,
                $value
            );
        });
    }

    public function testCatchThenCatch(): void
    {
        (new Promise(function(Closure $resolve, Closure $reject): void {
            throw new Exception();
        }))->catch(function(): void {})->then(function() {
            throw new Exception('test');
        })->catch(function(Throwable $reason): void {
            $this->assertSame(
                'test',
                $reason->getMessage()
            );
        });
    }

    public function testCatchThenPromise(): void
    {
        (new Promise(function(Closure $resolve, Closure $reject): void {
            throw new Exception();
        }))->catch(function(): PromiseInterface {
            return Promise::resolve(1);
        })->then(function(int $value) {
            $this->assertSame(
                1,
                $value
            );
        });
    }

    public function testMacroable(): void
    {
        $this->assertEmpty(
            array_diff([MacroTrait::class, StaticMacroTrait::class], class_uses(Promise::class))
        );
    }

    public function testMultipleThen(): void
    {
        $promise = new Promise(function(Closure $resolve): void {
            $resolve(1);
        });

        $results = [];

        $promise->then(function(int $value) use (&$results): void {
            $results[] = $value;
        });

        $promise->then(function(int $value) use (&$results): void {
            $results[] = $value + 1;
        });

        $this->assertSame(
            [1, 2],
            $results
        );
    }

    public function testThen(): void
    {
        (new Promise(function(Closure $resolve): void {
            $resolve();
        }))->then(function(): void {
            $this->assertTrue(true);
        });
    }

    public function testThenCatch(): void
    {
        Promise::reject(new Exception('test'))
            ->then(function(): void {
                $this->fail();
            })
            ->catch(function(Throwable $reason): void {
                $this->assertSame(
                    'test',
                    $reason->getMessage()
                );
            });
    }

    public function testThenCatchFinallyThenCatchFinally(): void
    {
        $results = [];

        Promise::resolve(1)
            ->then(function(int $value) use (&$results): int {
                $results[] = $value;

                return 2;
            })
            ->catch(function(): void {
                $this->fail();
            })
            ->finally(function() use (&$results): void {
                $results[] = 3;
            })
            ->then(function(int $value) use (&$results): int {
                $results[] = $value;

                return 4;
            })
            ->catch(function(): void {
                $this->fail();
            })
            ->finally(function() use (&$results): void {
                $results[] = 5;
            });

        $this->assertSame(
            [1, 3, 2, 5],
            $results
        );
    }

    public function testThenFinally(): void
    {
        Promise::resolve()
            ->then(function(): void {})
            ->finally(function(): void {
                $this->assertTrue(true);
            });
    }

    public function testThenResolve(): void
    {
        (new Promise(function(Closure $resolve): void {
            $resolve(1);
        }))->then(function(int $value): void {
            $this->assertSame(
                1,
                $value
            );
        });
    }

    public function testThenThen(): void
    {
        Promise::resolve(1)
            ->then(fn(int $value): int => $value + 1)
            ->then(function(int $value): void {
                $this->assertSame(
                    2,
                    $value
                );
            });
    }

    public function testThenThenCatch(): void
    {
        Promise::resolve()
            ->then(function(): void {
                throw new Exception('test');
            })
            ->then(function(): void {
                $this->fail();
            })
            ->catch(function(Throwable $reason): void {
                $this->assertSame(
                    'test',
                    $reason->getMessage()
                );
            });
    }

    public function testThenThenPromise(): void
    {
        Promise::resolve()
            ->then(fn(): PromiseInterface => Promise::resolve(1))
            ->then(function(int $value): void {
                $this->assertSame(
                    1,
                    $value
                );
            });
    }

    public function testThenThenThen(): void
    {
        Promise::resolve()
            ->then(fn(): int => 1)
            ->then(fn(int $value): int => $value + 1)
            ->then(function(int $value): void {
                $this->assertSame(
                    2,
                    $value
                );
            });
    }

    public function testUncaughtCaughtException(): void
    {
        $this->expectException(Exception::class);

        Promise::reject()->catch(function() {
            throw new Exception();
        });
    }

    public function testUncaughtException(): void
    {
        $this->expectException(Exception::class);

        (new Promise(function(Closure $resolve, Closure $reject): void {
            $reject(new Exception('test'));
        }));
    }

    public function testUncaughtThenException(): void
    {
        $this->expectException(Exception::class);

        Promise::resolve(1)->then(function() {
            throw new Exception();
        });
    }
}
