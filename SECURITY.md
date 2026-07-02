# Security policy

Thank you for taking the time to disclose responsibly.

## Reporting a vulnerability

If you believe you've found a security issue in Garnet Framework, please
**do not** open a public GitHub issue. Instead, email the maintainers
privately so we can investigate and ship a fix before the details are public.

- **Contact:** open a private security advisory on GitHub
  ([Security → Advisories → "Report a vulnerability"](https://github.com/PHPCraftdream/garnet-framework/security/advisories/new)),
  or email `security@phpcraftdream.dev` (placeholder — update when the
  domain is live).

Please include:

- A clear description of the issue and the impact you observed.
- The smallest reproduction you can share — a code snippet, a curl
  command, or a screenshot is enough; full PoC is welcome but not
  required.
- The affected version(s) — Composer-installed version, git commit, or
  `vendor/bin/garnet --version`.
- Whether the issue is already public anywhere (it shouldn't be, but if
  it leaked we want to know).

## What to expect

- **Acknowledgement** within **5 business days** that we received the
  report and have started looking at it.
- **Initial assessment** (vulnerable / not vulnerable / need more info)
  within **14 days**.
- A **fix and a coordinated disclosure window** for confirmed issues.
  We'll discuss timing with you, default 90 days from the initial
  acknowledgement.

## Supported versions

While Garnet is on `0.x`, we only patch the latest minor. After `1.0.0`
ships, we'll publish a support window per major.

| Version | Supported |
|---|---|
| `0.x` (latest) | ✅ |
| anything older | ❌ — please upgrade |

## Scope

In scope:
- Authentication / authorisation logic, including the passwordless flow.
- CSRF, session, and cookie handling.
- SQL handling in `DbTable` and `DbPool` (raw SQL injection vectors).
- Path traversal / SSRF in file uploads, asset serving, deploy tooling.
- Twig auto-escape bypasses.
- Anything in the CLI / `garnet` binary that runs untrusted input.

Out of scope:
- Issues that require a privileged local attacker on the developer's own
  machine.
- DoS via maliciously crafted inputs at unrealistic scale.
- Vulnerabilities in third-party Composer / npm packages — please report
  upstream.

## Hall of fame

We're happy to credit reporters in release notes (with their consent).
Just let us know how you want to be named when you report.
