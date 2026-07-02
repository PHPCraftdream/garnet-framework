<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Event\Event;
use PHPCraftdream\Garnet\Kernel\Core\Event\EventObj;
use PHPCraftdream\Garnet\Kernel\Interfaces\IEvent;

describe('Event', function (): void {
    beforeEach(function (): void {
        // Reset static handlers between tests
        $event = new class() extends Event {
            public static function resetStatic(): void {
                static::$items = [];
            }
        };
        $event::resetStatic();
    });

    describe('get()', function (): void {
        it('returns singleton instance for same scope', function (): void {
            $event1 = Event::get();
            $event2 = Event::get();
            expect($event1)->toBe($event2);
        });

        it('returns different instances for different scopes', function (): void {
            $event1 = Event::get('scope1');
            $event2 = Event::get('scope2');
            expect($event1)->not->toBe($event2);
        });

        it('uses default main scope', function (): void {
            $event1 = Event::get();
            $event2 = Event::get(IEvent::SCOPE_MAIN);
            expect($event1)->toBe($event2);
        });
    });

    describe('subscribe()', function (): void {
        it('adds handler for event', function (): void {
            $event = Event::get();
            $called = false;

            $event->subscribe('test.event', function () use (&$called): void {
                $called = true;
            });

            $event->emit('test.event');
            expect($called)->toBe(true);
        });

        it('adds multiple handlers for same event', function (): void {
            $event = Event::get();
            $count = 0;

            $event->subscribe('test.event', function () use (&$count): void {
                $count++;
            });

            $event->subscribe('test.event', function () use (&$count): void {
                $count++;
            });

            $event->emit('test.event');
            expect($count)->toBe(2);
        });

        it('calls handlers with event object', function (): void {
            $event = Event::get();
            $receivedEventObj = null;

            $event->subscribe('test.event', function ($evt) use (&$receivedEventObj): void {
                $receivedEventObj = $evt;
            });

            $result = $event->emit('test.event', ['key' => 'value']);
            expect($receivedEventObj)->toBe($result);
        });
    });

    describe('emit()', function (): void {
        it('returns EventObj with event name', function (): void {
            $event = Event::get();
            $result = $event->emit('test.event');
            expect($result->getEventName())->toBe('test.event');
        });

        it('passes args to EventObj', function (): void {
            $event = Event::get();
            $result = $event->emit('test.event', ['key' => 'value']);
            expect($result->getArgs())->toBe(['key' => 'value']);
        });

        it('returns EventObj even without handlers', function (): void {
            $event = Event::get();
            $result = $event->emit('nonexistent');
            expect($result->isExecuted())->toBe(false);
        });

        it('sets executed flag on EventObj when handlers run', function (): void {
            $event = Event::get();
            $event->subscribe('test.event', function (): void {});
            $result = $event->emit('test.event');
            expect($result->isExecuted())->toBe(true);
        });

        it('stops calling handlers when event is stopped', function (): void {
            $event = Event::get();
            $count = 0;

            $event->subscribe('test.event', function ($evt) use (&$count): void {
                $count++;
                $evt->stop(true);
            });

            $event->subscribe('test.event', function () use (&$count): void {
                $count++;
            });

            $event->emit('test.event');
            expect($count)->toBe(1);
        });
    });

    describe('EventObj', function (): void {
        describe('constructor', function (): void {
            it('stores event name and args', function (): void {
                $obj = new EventObj('test.name', ['a' => 1]);
                expect($obj->getEventName())->toBe('test.name');
                expect($obj->getArgs())->toBe(['a' => 1]);
            });

            it('handles empty args', function (): void {
                $obj = new EventObj('test');
                expect($obj->getArgs())->toBe([]);
            });
        });

        describe('stop/isStopped/isRunning', function (): void {
            it('is running by default', function (): void {
                $obj = new EventObj('test');
                expect($obj->isRunning())->toBe(true);
                expect($obj->isStopped())->toBe(false);
            });

            it('can be stopped', function (): void {
                $obj = new EventObj('test');
                $obj->stop(true);
                expect($obj->isStopped())->toBe(true);
                expect($obj->isRunning())->toBe(false);
            });

            it('can be restarted', function (): void {
                $obj = new EventObj('test');
                $obj->stop(true);
                $obj->stop(false);
                expect($obj->isRunning())->toBe(true);
            });
        });

        describe('result/setResult', function (): void {
            it('stores and retrieves result', function (): void {
                $obj = new EventObj('test');
                expect($obj->getResult())->toBeNull();

                $obj->setResult('my result');
                expect($obj->getResult())->toBe('my result');
            });

            it('can store any type of result', function (): void {
                $obj = new EventObj('test');
                $data = ['key' => 'value', 'num' => 42];

                $obj->setResult($data);
                expect($obj->getResult())->toBe($data);
            });
        });

        describe('executed/setExecuted', function (): void {
            it('is not executed by default', function (): void {
                $obj = new EventObj('test');
                expect($obj->isExecuted())->toBe(false);
            });

            it('can be marked as executed', function (): void {
                $obj = new EventObj('test');
                $obj->setExecuted();
                expect($obj->isExecuted())->toBe(true);
            });
        });
    });

    describe('integration test', function (): void {
        it('full event workflow', function (): void {
            $event = Event::get();
            $results = [];

            $event->subscribe('user.login', function ($evt) use (&$results): void {
                $results[] = 'handler1';
                $evt->setResult('logged_in');
            });

            $event->subscribe('user.login', function ($evt) use (&$results): void {
                $results[] = 'handler2';
            });

            $eventObj = $event->emit('user.login', ['userId' => 123]);

            expect($results)->toBe(['handler1', 'handler2']);
            expect($eventObj->getResult())->toBe('logged_in');
            expect($eventObj->isExecuted())->toBe(true);
        });
    });
});
