# FyrePromise

**FyrePromise** is a free, promise library for *PHP*.


## Table Of Contents
- [Installation](#installation)
- [Promise Creation](#promise-creation)
- [Methods](#methods)
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
- `$sync` is a boolean indicating whether to execute the *Promise* synchronously, and will default to *false*.

```php
$promise = new Promise($callback, $sync);
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

**Finally**

Execute a callback when the *Promise* is settled.

- `$onFinally` is a *Closure* that will execute when the *Promise* has settled.

```php
$promise->finally($onFinally);
```

**Get Rejected Reason**

Get the rejected reason.

```php
$rejectedReason = $promise->getRejectedReason();
```

**Get Resolved Value**

Get the resolved value.

```php
$resolvedValue = $promise->getResolvedValue();
```

**Is Rejected**

Determine whether the *Promise* was rejected.

```php
$isRejected = $promise->isRejected();
```

**Is Resolved**

Determine whether the *Promise* has resolved.

```php
$isResolved = $promise->isResolved();
```

**Is Settled**

Determine whether the *Promise* has settled.

```php
$isSettled = $promise->isSettled();
```

**Poll**

Poll the child process to determine if the *Promise* has settled.

```php
$isSettled = $promise->poll();
```

**Then**

Execute a callback when the *Promise* is resolved.

- `$onFulfilled` is a *Closure* that will execute when the *Promise* is resolved.
- `$onRejected` is a *Closure* that will execute when the *Promise* is rejected, and will default to *null*.

```php
$promise->then($onFulfilled, $onRejected);
```

**Wait**

Wait for the *Promise* to settle.

```php
$promise->wait();
```


## Static Methods

**All**

Wait for all promises to settle.

- `$promises` is an array containing the promises to wait for.

```php
Promise::all($promises);
```

**Await**

Wait for a *Promise* to settle.

- `$promise` is the *Promise* to wait for.

```php
$resolvedValue = Promise::await($promise);
```

**Reject**

Create a *Promise* that rejects.

- `$reason` is a string representing the rejected reason, and will default to *null*.

```php
$promise = Promise::reject($reason);
```

**Resolve**

Create a *Promise* that resolves.

- `$value` is the resolved value, and will default to *null*.

```php
$promise = Promise::resolve($value);
```