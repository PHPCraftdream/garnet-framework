# garnet-browser-mcp

MCP server exposing a headless Playwright browser (plus DB/log helpers) as
tools for an AI coding agent to drive and inspect a Garnet app during
development.

## Trust model — LOCAL, TRUSTED use only

This tool is designed for an AI agent acting on behalf of a developer on
their own machine. It intentionally has **no sandboxing** around a few
tools:

- `evaluate` runs arbitrary JavaScript in the browser page context via
  `new Function(code)()` — full page-context access, no restrictions.
- `session_create`'s `storageState` parameter accepts an arbitrary file
  path on disk and loads it as Playwright storage state — no path
  restriction.

Both are safe under the intended usage (a trusted local agent, trusted
local input) but **must not** be exposed to untrusted callers, remote
input, or multi-tenant/shared-server scenarios.
