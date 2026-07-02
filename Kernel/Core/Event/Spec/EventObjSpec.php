<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Event\EventObj;

describe('EventObj', function (): void {
    describe('constructor and getters', function (): void {
        it('stores event name and args', function (): void {
            $args = ['key' => 'value', 'number' => 42];
            $event = new EventObj('test.event', $args);
            expect($event->getEventName())->toBe('test.event');
            expect($event->getArgs())->toBe($args);
        });

        it('defaults to empty args when not provided', function (): void {
            $event = new EventObj('test.event');
            expect($event->getEventName())->toBe('test.event');
            expect($event->getArgs())->toBe([]);
        });
    });

    describe('event lifecycle: stop and running state', function (): void {
        it('starts as running and not stopped', function (): void {
            $event = new EventObj('test');
            expect($event->isRunning())->toBe(true);
            expect($event->isStopped())->toBe(false);
        });

        it('stops event and affects both states', function (): void {
            $event = new EventObj('test');
            $event->stop(true);
            expect($event->isStopped())->toBe(true);
            expect($event->isRunning())->toBe(false);
        });

        it('can restart event by stopping with false', function (): void {
            $event = new EventObj('test');
            $event->stop(true);
            $event->stop(false);
            expect($event->isStopped())->toBe(false);
            expect($event->isRunning())->toBe(true);
        });
    });

    describe('result handling', function (): void {
        it('returns null by default', function (): void {
            $event = new EventObj('test');
            expect($event->getResult())->toBe(null);
        });

        it('sets and returns result', function (): void {
            $event = new EventObj('test');
            $event->setResult('my result');
            expect($event->getResult())->toBe('my result');
        });

        it('overwrites previous result', function (): void {
            $event = new EventObj('test');
            $event->setResult('first');
            $event->setResult('second');
            expect($event->getResult())->toBe('second');
        });

        it('can store any type of result', function (): void {
            $event = new EventObj('test');
            $event->setResult(['array' => 'value']);
            expect($event->getResult())->toBe(['array' => 'value']);

            $event->setResult(42);
            expect($event->getResult())->toBe(42);

            $event->setResult(null);
            expect($event->getResult())->toBe(null);
        });
    });

    describe('execution flag', function (): void {
        it('starts as not executed', function (): void {
            $event = new EventObj('test');
            expect($event->isExecuted())->toBe(false);
        });

        it('marks event as executed', function (): void {
            $event = new EventObj('test');
            $event->setExecuted();
            expect($event->isExecuted())->toBe(true);
        });

        it('executed flag persists when stopping event', function (): void {
            $event = new EventObj('test');
            $event->setExecuted();
            $event->stop(true);
            expect($event->isExecuted())->toBe(true);
            expect($event->isStopped())->toBe(true);
        });
    });
});
