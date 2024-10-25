# FyrePromise

**FyrePromise** is a free, open-source promise library for *PHP*.

It is a modern library, and features support for synchronous aand asynchronous promises.


## Table Of Contents
- [Installation](#installation)
- [Promise Creation](#promise-creation)
- [Promise Methods](#promise-methods)
- [Async](#async)
- [Static Methods](#static-methods)



## Installation

**Using Composer**

```
composer require fyre/promise
```

In PHP:

```php
use Fyre\Promise\Promise;
```


## Promise Creation

- `$callback` is a *Closure*.

```php
$promise = new Promise($callback);
```

The `$callback` should be expressed in the following format:

```php
$callback = function(Closure $resolve, Closure $reject): void {
    $resolve();
};
```


## Methods

**Catch**

Execute a callback if the *Promise* is rejected.

- `$onRejected` is a *Closure* that will execute when the *Promise* is rejected.

```php
$promise->catch($onRejected);
```

This method will return a new *Promise*.

**Finally**

Execute a callback when the *Promise* is settled.

- `$onFinally` is a *Closure* that will execute when the *Promise* has settled.

```php
$promise->finally($onFinally);
```

This method will return a new *Promise*.

**Then**

Execute a callback when the *Promise* is resolved.

- `$onFulfilled` is a *Closure* that will execute when the *Promise* is resolved.
- `$onRejected` is a *Closure* that will execute when the *Promise* is rejected, and will default to *null*.

```php
$promise->then($onFulfilled, $onRejected);
```

This method will return a new *Promise*.

## Async

The `\Fyre\Promise\AsyncPromise` class extends the *Promise* class, while providing additional methods for handling asynchronous operations.

```php
use \Fyre\Promise\AsyncPromise;

$promise = new AsyncPromise(function(Closure $resolve, Closure $reject): void {
    // this will be executed on a forked process
    sleep(3);

    $resolve(1);
})->then(function(int $value): void {
    // this will be executed on the main thread

    echo $value;
});

$promise->wait();
```

**Cancel**

Cancel the pending *AsyncPromise*.

- `$message` is a string representing the cancellation message.

```php
$promise->cancel($message);
```

A cancelled promise will reject with a `Fyre\Promise\Exceptions\CancelledPromiseException`.

**Wait**

Wait for the *AsyncPromise* to settle.

```php
$promise->wait();
```


## Static Methods

**Any**

Wait for any promise to resolve.

- `$promises` is an iterable containing the promises or values to wait for.

```php
$promise = Promise::any($promises);
```

This method will return a new *Promise*.

**All**

Wait for all promises to resolve.

- `$promises` is an iterable containing the promises or values to wait for.

```php
$promise = Promise::all($promises);
```

This method will return a new *Promise*.

**Await**

Wait for a *Promise* to settle.

- `$promise` is the *Promise* to wait for.

```php
try {
    $resolvedValue = Promise::await($promise);
} catch (Throwable $reason) {
    //...
}
```

**Race**

Wait for the first promise to resolve.

- `$promises` is an iterable containing the promises or values to wait for.

```php
$promise = Promise::all($promises);
```

This method will return a new *Promise*.

**Reject**

Create a *Promise* that rejects.

- `$reason` is a *Throwable* representing the rejected reason, and will default to *null*.

```php
$promise = Promise::reject($reason);
```

**Resolve**

Create a *Promise* that resolves.

- `$value` is the resolved value, and will default to *null*.

```php
$promise = Promise::resolve($value);
```