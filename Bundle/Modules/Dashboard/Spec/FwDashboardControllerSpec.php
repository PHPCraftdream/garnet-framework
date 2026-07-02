<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Spec {
    use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // Minimal concrete subclasses used to verify abstract contract
    // ---------------------------------------------------------------------------

    class GuestDashboardController extends FwDashboardController {
        protected static function isModerator(): bool {
            return false;
        }

        protected static function isOwner(): bool {
            return false;
        }

        protected static function getSideMenu(string $url): array {
            return [];
        }

        protected static function getMainMenu(string $url): array {
            return [];
        }
    }

    class AdminDashboardController extends FwDashboardController {
        protected static function isModerator(): bool {
            return true;
        }

        protected static function isOwner(): bool {
            return true;
        }

        protected static function getSideMenu(string $url): array {
            return [
                ['label' => 'Users',    'url' => '/admin/users/',    'active' => $url === '/admin/users/'],
                ['label' => 'Settings', 'url' => '/admin/settings/', 'active' => $url === '/admin/settings/'],
            ];
        }

        protected static function getMainMenu(string $url): array {
            return [
                ['label' => 'Home', 'url' => '/', 'active' => $url === '/'],
            ];
        }
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwDashboardController', function (): void {
        // -----------------------------------------------------------------------
        describe('class structure', function (): void {
            it('is abstract', function (): void {
                $ref = new ReflectionClass(FwDashboardController::class);
                expect($ref->isAbstract())->toBe(true);
            });

            it('declares isModerator() as abstract', function (): void {
                $ref = new ReflectionClass(FwDashboardController::class);
                $method = $ref->getMethod('isModerator');
                expect($method->isAbstract())->toBe(true);
            });

            it('declares isOwner() as abstract', function (): void {
                $ref = new ReflectionClass(FwDashboardController::class);
                $method = $ref->getMethod('isOwner');
                expect($method->isAbstract())->toBe(true);
            });

            it('declares getSideMenu() as abstract', function (): void {
                $ref = new ReflectionClass(FwDashboardController::class);
                $method = $ref->getMethod('getSideMenu');
                expect($method->isAbstract())->toBe(true);
            });

            it('declares getMainMenu() as abstract', function (): void {
                $ref = new ReflectionClass(FwDashboardController::class);
                $method = $ref->getMethod('getMainMenu');
                expect($method->isAbstract())->toBe(true);
            });

            it('all abstract methods are protected', function (): void {
                $ref = new ReflectionClass(FwDashboardController::class);
                $methods = ['isModerator', 'isOwner', 'getSideMenu', 'getMainMenu'];

                foreach ($methods as $name) {
                    $method = $ref->getMethod($name);
                    expect($method->isProtected())->toBe(true);
                }
            });
        });

        // -----------------------------------------------------------------------
        describe('GuestDashboardController — unprivileged concrete impl', function (): void {
            it('isModerator() returns false for guest', function (): void {
                $ref = new ReflectionClass(GuestDashboardController::class);
                $method = $ref->getMethod('isModerator');
                $method->setAccessible(true);
                expect($method->invoke(null))->toBe(false);
            });

            it('isOwner() returns false for guest', function (): void {
                $ref = new ReflectionClass(GuestDashboardController::class);
                $method = $ref->getMethod('isOwner');
                $method->setAccessible(true);
                expect($method->invoke(null))->toBe(false);
            });

            it('getSideMenu() returns empty array', function (): void {
                $ref = new ReflectionClass(GuestDashboardController::class);
                $method = $ref->getMethod('getSideMenu');
                $method->setAccessible(true);
                expect($method->invoke(null, '/admin/'))->toBe([]);
            });

            it('getMainMenu() returns empty array', function (): void {
                $ref = new ReflectionClass(GuestDashboardController::class);
                $method = $ref->getMethod('getMainMenu');
                $method->setAccessible(true);
                expect($method->invoke(null, '/'))->toBe([]);
            });
        });

        // -----------------------------------------------------------------------
        describe('AdminDashboardController — privileged concrete impl', function (): void {
            it('isModerator() returns true for admin', function (): void {
                $ref = new ReflectionClass(AdminDashboardController::class);
                $method = $ref->getMethod('isModerator');
                $method->setAccessible(true);
                expect($method->invoke(null))->toBe(true);
            });

            it('isOwner() returns true for admin', function (): void {
                $ref = new ReflectionClass(AdminDashboardController::class);
                $method = $ref->getMethod('isOwner');
                $method->setAccessible(true);
                expect($method->invoke(null))->toBe(true);
            });

            it('getSideMenu() returns non-empty array of items', function (): void {
                $ref = new ReflectionClass(AdminDashboardController::class);
                $method = $ref->getMethod('getSideMenu');
                $method->setAccessible(true);
                $menu = $method->invoke(null, '/admin/users/');
                expect(count($menu))->toBeGreaterThan(0);
            });

            it('getSideMenu() marks current url as active', function (): void {
                $ref = new ReflectionClass(AdminDashboardController::class);
                $method = $ref->getMethod('getSideMenu');
                $method->setAccessible(true);
                $menu = $method->invoke(null, '/admin/users/');
                $usersItem = array_values(array_filter($menu, fn ($i) => $i['url'] === '/admin/users/'))[0] ?? null;
                expect($usersItem)->not->toBeNull();
                expect($usersItem['active'])->toBe(true);
            });

            it('getSideMenu() does not mark other items as active', function (): void {
                $ref = new ReflectionClass(AdminDashboardController::class);
                $method = $ref->getMethod('getSideMenu');
                $method->setAccessible(true);
                $menu = $method->invoke(null, '/admin/users/');

                foreach ($menu as $item) {
                    if ($item['url'] !== '/admin/users/') {
                        expect($item['active'])->toBe(false);
                    }
                }
            });

            it('getMainMenu() returns array items with url keys', function (): void {
                $ref = new ReflectionClass(AdminDashboardController::class);
                $method = $ref->getMethod('getMainMenu');
                $method->setAccessible(true);
                $menu = $method->invoke(null, '/');
                expect(count($menu))->toBeGreaterThan(0);

                foreach ($menu as $item) {
                    expect(isset($item['url']))->toBe(true);
                }
            });
        });
    });
}
