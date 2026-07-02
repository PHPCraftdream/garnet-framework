// Generic test-account logins. All end in `.test` — the framework's
// `EmailAuthMiddleware` registration gate carves out `*.test` addresses
// for an active TestScope (see `Kernel/Core/Env/TestScope.php`), so these
// can register even when `registrationsEnabled` is off. Nothing here ever
// receives real email; the code is read back from the DB (see auth.ts).
export const ACCOUNT_LOGIN = 'testuser_setup_account@garnet.test';
