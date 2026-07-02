# SSH in the Garnet CLI

The `php garnet ssh*` commands and the whole `deploy:diff` pipeline
ride over a single SSH connection to a single host. Connection config
lives in `ssh.ini`. The on-host directory layout lives in `deploy.ini`
(see [`deploy.md`](deploy.md)).

## Contents

- [`ssh.ini` config](#sshini-config)
- [Commands](#commands)
- [Identity: key on disk vs. inline](#identity-key-on-disk-vs-inline)
- [Security](#security)
- [Troubleshooting](#troubleshooting)

---

## `ssh.ini` config

File: `Apps/<App>/WorkDir/Config/ssh.ini` (prod) or
`WorkDir/ConfigDev/ssh.ini` (dev). Template:
`WorkDir/ConfigExample/ssh.ini`.

```ini
; SSH connection parameters
host                     = "u1234567.your-host.example"
port                     = 22
user                     = "u1234567"

; --- Identity (pick exactly ONE) ---

; Option 1: path to the private key on disk.
; Relative paths resolve against this ssh.ini's directory.
; Typical layout: drop `ssh_key` next to the ini file.
identity_file            = "ssh_key"

; Option 2: key contents inline.
; PHP parse_ini_file preserves line breaks inside double quotes.
; ConfigDev/ and Config/ are gitignored, so an inline key is safe here.
identity_key             = ""

strict_host_key_checking = "accept-new"   ; yes | no | accept-new
```

The file is read **lazily** — only when the CLI touches
`IniConfig::ssh()`. Web requests never look at it; missing `ssh.ini` on
prod is harmless.

Layout fields (`remote_path`, `public_dir`, `framework_dir`, `app_dir`,
`runtime_dir`, `public_name`) live in a **separate** `deploy.ini` — see
[`deploy.md`](deploy.md).

---

## Commands

### `ssh "<command>"`

Run an arbitrary shell command on the host.

```bash
# Inspect the hosting root
php garnet ssh "ls -la /var/www/u1234567/data/www"

# Check log size
php garnet ssh "du -sh garnet-runtime-myapp/WorkDir/LogJournal"

# Run a prod migration
php garnet ssh "cd /var/www/u1234567/data/www/garnet-runtime-myapp && php garnet migration"

# Reload the FPM pool
php garnet ssh "sudo systemctl reload php8.1-fpm"
```

The `--cd-remote` flag prepends `cd <remote_path> &&` (when
`remote_path` is set in `deploy.ini`).

```bash
# Equivalent to: cd /var/www/u…/data/www && ls -la
php garnet ssh "ls -la" --cd-remote
```

The command's stdout is printed verbatim; its exit code propagates to
the local process.

### `ssh:put <local> [<remote>]`

Upload a file or directory. `remote` is the path on the host; if
omitted, defaults to `<remote_path>/<basename(local)>`.

```bash
# A single file to an absolute path
php garnet ssh:put dist/MyApp/garnet-runtime-myapp/garnet \
    /var/www/u1234567/data/www/garnet-runtime-myapp/garnet

# A whole directory (recursive)
php garnet ssh:put dist/MyApp/garnet-app-myapp garnet-app-myapp --cd-remote
```

On Windows, `MSYS_NO_PATHCONV=1` is set automatically so Git Bash
doesn't rewrite unix paths into windows paths before they reach `scp`.

### `ssh:get <remote> [<local>]`

Download a file/directory from the host.

```bash
# Pull today's error log
php garnet ssh:get garnet-runtime-myapp/WorkDir/LogJournal/Errors/$(date +%Y-%m-%d).log \
    /tmp/prod-errors.log --cd-remote
```

### `ssh:test`

Connection sanity check. Verifies that:

- the TCP connect to `host:port` succeeds,
- key authentication is accepted,
- `whoami` on the host returns the same `user` as configured.

```bash
php garnet ssh:test
# OK: u1234567@example.com (key auth, whoami matches)
```

Run after first configuring `ssh.ini` and after rotating the key.

---

## Identity: key on disk vs. inline

`ssh.ini` accepts **exactly one** of the two:

### Option 1: `identity_file`

Path to a private-key file.

- **Absolute path** (`identity_file = "/home/user/.ssh/id_rsa"`) —
  used as-is.
- **Relative path** (`identity_file = "ssh_key"`) — resolves against
  the directory of the `ssh.ini` file. Typical layout: put the key as
  `WorkDir/ConfigDev/ssh_key` next to the ini — `ConfigDev/` is
  already gitignored.
- **With tilde** (`identity_file = "~/.ssh/id_rsa"`) — expands to
  `$HOME` / `%USERPROFILE%`.

Implementation: `IniConfig::sshIdentityFile()`.

### Option 2: `identity_key`

The private key's contents inlined in the ini file.

```ini
identity_key             = "-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAACFwAAAAdzc2gtcn
…
-----END OPENSSH PRIVATE KEY-----"
```

PHP's `parse_ini_file()` preserves line breaks inside double quotes.
At CLI invocation time, the key is dumped to a temp file with mode
`0600`, passed as `-i <tmpfile>`, and the temp file is removed when
the process exits.

If both options are set, **`identity_key` wins**, and a warning is
printed.

---

## Security

- `WorkDir/ConfigDev/*` and `WorkDir/Config/*` are gitignored. Never
  commit a real key.
- `ConfigExample/ssh.ini` is a template with empty values — safe to
  keep in the repo.
- `strict_host_key_checking = "accept-new"` accepts a new host key
  once and pins it, refusing on later mismatch. For stricter
  environments, switch to `"yes"` and pre-populate
  `~/.ssh/known_hosts`.
- Don't pass passwords through `ssh` commands. Use keys.
- The web stack never reads `ssh.ini`. `IniConfig::ssh()` exists only
  in the CLI namespace `Framework/Kernel/Io/GarnetCli/`.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| `Permission denied (publickey)` | Right key? Public key in the host's `~/.ssh/authorized_keys`? `chmod 600 ssh_key` locally? |
| `Host key verification failed` | Set `strict_host_key_checking = "accept-new"` (first connect) or clear the entry from `~/.ssh/known_hosts`. |
| `scp` on Windows complains about `cygdrive` / `/var/www/...` | Verify `MSYS_NO_PATHCONV=1` is actually set (the CLI does this; if you invoke `scp` by hand, set it yourself). |
| `Permission denied` on `ssh:put` into `app_dir/` | Permissions on the remote side. Run `php garnet ssh "ls -la garnet-app-myapp/" --cd-remote`, check the owner. Usually `chown -R u1234567:u1234567 .` as root. |
| `ssh.ini` not found | `php garnet config:init --dev` and edit `WorkDir/ConfigDev/ssh.ini`. |
| Command hangs with no output | Check firewall / `port` in `ssh.ini`. Run `ssh -v` directly with the same parameters to diagnose. |
