# Upload a file safely

Garnet has a two-phase upload pattern: **pending → commit**. A POST
endpoint accepts the bytes into a quarantined directory, hands back a
token, and the actual entity update (avatar, photo, attachment) commits
the token in a separate request. That keeps malformed bodies, mime
mismatches, and abandoned uploads from polluting your live storage.

## The phases

```
1. POST /upload/~stash          → returns { token: "abc…" }
   (file lands in WorkDir/Uploads/Pending/<token>/<orig-name>)

2. POST /user/~save-avatar      → body includes { avatarToken: "abc…" }
   (FileUploadManager::commit("abc…", $finalDir) moves it into place)
```

Both phases go through `FileUploadManager` so quotas, mime checks and
size limits are enforced once.

## Stash endpoint

```php
use PHPCraftdream\Garnet\Bundle\Modules\Files\FileUploadManager;

public function post__stash(GlobalReqParams $g, RouterUriParams $u): ResponseInterface
{
    $file = $g->files()['file'] ?? null;
    if ($file === null) {
        return $this->jsonError('no file');
    }

    $token = FileUploadManager::stash($file, [
        'mime'   => ['image/jpeg', 'image/png', 'image/webp'],
        'maxKb'  => 4096,
    ]);

    return $this->json(['ok' => true, 'token' => $token]);
}
```

`stash()` validates mime + size, generates a one-time token, writes the
file under `WorkDir/Uploads/Pending/<token>/`, and prunes pending
uploads older than 24h on each call.

## Commit at the entity boundary

```php
public function post__saveAvatar(GlobalReqParams $g, RouterUriParams $u): ResponseInterface
{
    $token = $g->post()['avatarToken'] ?? '';
    if ($token === '') {
        return $this->jsonError('missing token');
    }

    $finalDir = $this->user()->avatarDir();   // e.g. WorkDir/Uploads/Users/42/
    $rel = FileUploadManager::commit($token, $finalDir);

    $this->user()->saveParam('avatar', $rel);
    return $this->json(['ok' => true, 'url' => $this->user()->avatarUrl()]);
}
```

`commit()` rejects unknown / expired tokens, moves the file atomically,
and returns the relative path you store on the entity.

## Serving the file back

Uploaded files live **outside** the docroot — they're served by PHP
through `SecureFileServing` with an `accessCheck` callback that decides
whether the caller is allowed to see the bytes.

```php
return SecureFileServing::serve(
    file: $absolutePath,
    accessCheck: fn () => $this->isOwner($entity) || $this->isAdmin(),
);
```

That gives you per-request authorisation, range support, and
proper `Content-Disposition` headers without dropping the file into a
public folder.

## Why two phases

- The body validation (mime, size) happens in the cheap stash phase.
  If it fails, you've spent no DB writes.
- The entity update is a single small POST that's easy to validate
  client-side (`zod` schema) and resend on flake.
- Abandoned uploads (user navigates away after stash, before commit)
  are mopped up by the pruning sweep — they never reach the entity's
  storage area.

## Related

- [`../../Kernel/Io/FileUpload/`](../../Kernel/Io/FileUpload/) — `FileUploadManager` and `SecureFileServing` source.

---

↑ Back to [Cookbook](README.md)
