# API Helpers

## Available modules

### sendPostFormData

**Path:** `Common/Api/sendPostFormData.ts`

Sends a POST request with `FormData`. Automatically adds the CSRF token from `window.__GARNET_CSRF__`.

```ts
import {sendPostFormData} from '@common/Api/sendPostFormData';

const formData = new FormData();
formData.append('name', 'value');
const result = await sendPostFormData('/my/endpoint', formData);
```

- Uses `XMLHttpRequest` (not `fetch`)
- Automatically adds `CSRF_TOKEN` to the `FormData`
- Returns parsed JSON
- Throws `ApiError` on any non-2xx response

### sendPost

**Path:** `Common/Api/sendPost.ts`

Sends a POST request with a JSON body. Automatically adds the CSRF token.

```ts
import {sendPost} from '@common/Api/sendPost';

const result = await sendPost('/my/endpoint', { key: 'value' });
```

- Uses `fetch` with `Content-Type: application/json`
- Automatically adds `CSRF_TOKEN` to the body

---

## Calling from inline JS (Twig templates)

If you need to invoke an API helper from an inline `<script>` inside a Twig template, expose it on `window` from your bundle's entry point.

### Step 1: Export in the entry point

```ts
// FrontBuilder/MyApp/Foreground/Scripts/Foreground.ts
import {sendPostFormData} from '@common/Api/sendPostFormData';
(window as any).sendPostFormData = sendPostFormData;
```

### Step 2: Build

```bash
cd FrontBuilder
COMMON_GARNET_WEB_DIR="/path/to/your-app" npx cross-env NODE_ENV=production npx rspack build --config rspack.config.ts
```

After the build, `ForegroundJsGen.php` is regenerated with a fresh content hash.

### Step 3: Wire the JS bundle into the page

Pass the bundle to `js_assets` through `TwigParams::init()->get()` from your controller:

```php
use PHPCraftdream\MyApp\Foreground\ForegroundJsGen;

return Twig::get()->render(
    'Layout/Main.twig',
    TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
        'content' => $content,
        'js_assets' => [
            ForegroundJsGen::foreground(),  // attaches the bundle
        ],
    ])
);
```

> **Important:** `TwigParams::init()->get()` uses `ArrayTools::array_merge_recursive()`, so `js_assets` from the merge array are **appended** to the defaults (Bootstrap, Framework) rather than replacing them.

### Step 4: Call from inline JS

```html
<script>
document.getElementById('myForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var result = await window.sendPostFormData(this.action, formData);
    console.log(result);
});
</script>
```

Scripts are loaded with `async`, but the function will be available by the time the user interacts with the page.
