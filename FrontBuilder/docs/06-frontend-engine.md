# Frontend Engine

## Overview

FrontBuilder ships its own frontend engine that implements an SPA on top of server-side Twig rendering:

- **HTML rendered on the server** (Twig) → SEO, fast FCP
- **JS swaps content without a full reload** → SPA UX
- **REST API for data** → forms, tables

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Request Flow                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Browser                                                         │
│     │                                                            │
│     │ GET /page                                                  │
│     ▼                                                            │
│  ┌──────────────┐                                                │
│  │  PHP + Twig  │ ──► HTML Response                              │
│  └──────────────┘                                                │
│     │                                                            │
│     │ HTML contains:                                             │
│     │  - <script src="...gen.js">                                │
│     │  - window.__GARNET_CSRF__                                  │
│     │  - window.__GARNET_BASE_URL__                              │
│     │                                                            │
│     ▼                                                            │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    JS Initialization                       │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │  1. Framework.ts ──► hotClickInit()                       │   │
│  │  2. Component subclasses ──► init()                       │   │
│  │  3. Event listeners attached                              │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  User clicks <a class="hot-click" href="/other">                │
│     │                                                            │
│     │ hotClickHandler() prevents default                        │
│     │ goTo(href) fetches HTML                                   │
│     │ PageLoader.updatePage() replaces body                     │
│     ▼                                                            │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Page Transition                         │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │  1. fetch(href) ──► HTML                                   │   │
│  │  2. extractResourcesFromHTML()                             │   │
│  │  3. loadStyles() ──► inject <link>                         │   │
│  │  4. body.innerHTML = newContent                            │   │
│  │  5. loadScripts() ──► inject <script>                      │   │
│  │  6. PageEvents.emmit(PAGE_RELOADED)                        │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Core Components

### Component (`Common/Dom/Component.ts`)

Base class for every UI component. Implements a lifecycle:

```typescript
class Component {
    constructor(protected mainElement: HTMLElement) {
        // 1. Register removal observer
        this.removeHandlerId = domObserver.registerElementRemoval(
            mainElement,
            this.onRemove.bind(this)
        );

        // 2. Defer init() to next event loop
        setTimeout(() => this.init(), 0);
    }

    init() { }      // Override: initialize component
    onRemove() { }  // Override: cleanup

    // Helpers
    apply(selector, fn)   // Query + execute
    get<T>(selector)      // Get single element wrapped in DomEl
    items<T>(selector)    // Get all elements as DomEl[]
    clearErrors()         // Remove .garnet-form-error elements
}
```

**Usage:**
```typescript
class MyPage extends Component {
    init() {
        this.get('.submit-btn')?.on('click', this.handleSubmit);
    }

    onRemove() {
        // Cleanup event listeners, intervals, etc.
    }
}
```

### DomEl (`Common/Dom/DomEl.ts`)

A jQuery-like wrapper around `HTMLElement`:

```typescript
class DomEl<T extends HTMLElement> {
    getEl(): T
    text(): string
    html(): string
    setHtml(html: string)
    toggle(show: boolean)
    hide() / show()
    remove()
    on(event, handler)
    then(fn)  // Execute with (domEl, element)
    validate(): boolean
    getValue(resultObj): any
    appendError(message: string)
    isHidden(): boolean
    disable() / enable()
}
```

### PageLoader (`Common/Dom/PageLoader.ts`)

SPA navigation without a full client-side router:

```typescript
class PageLoader {
    // Extract scripts/styles from HTML
    static extractResourcesFromHTML(html): {
        scripts: string[],
        styles: string[],
        newHTMLText: string
    }

    // Load scripts with deduplication + timeout
    static loadScripts(resources, timeout = 30000): Promise<string[]>

    // Load styles with deduplication + timeout
    static loadStyles(resources, timeout = 30000): Promise<string[]>

    // Main transition method
    static updatePage(html): Promise<void> {
        // 1. Extract resources
        // 2. Load styles
        // 3. Replace body.innerHTML
        // 4. Load scripts
        // 5. Emit PAGE_RELOADED event
    }
}
```

### GoTo (`Common/Dom/Nav/GoTo.ts`)

Navigation via `pushState`:

```typescript
const goTo = (href: string): Promise<void> => {
    return getHtml(href)
        .then((html) => {
            window.history.pushState({}, "", href);
            return PageLoader.updatePage(html);
        })
        .catch((error) => {
            // Fallback: show error page content
            if (typeof error.response === 'string') {
                window.history.pushState({}, "", href);
                PageLoader.updatePage(error.response);
            }
        });
};
```

### HotClick (`Common/Dom/Nav/HotClickInit.ts`)

Intercepting links for SPA transitions:

```typescript
// Links with class="hot-click" trigger SPA navigation
<a class="hot-click" href="/dashboard">Dashboard</a>

// Handler:
const hotClickHandler = (event, element) => {
    event.preventDefault();
    element.classList.add('hot-clicked');
    goTo(element.getAttribute('href'));
};
```

**Initialization in Framework.ts:**
```typescript
// Container-level delegation
classClick(container, 'hot-click', hotClickHandler);

// Back/forward browser navigation
window.addEventListener('popstate', () => {
    goTo(window.location.href);
});
```

---

## Form Handling

### FormTool (`Common/Dom/FormTool.ts`)

End-to-end form handling: validation, submission, error reporting.

```typescript
class FormTool {
    // Setup
    addField(name, domEl, readOnly = false)
    addControl(domEl)
    setCommonErrors(container)
    setProgress(progressEl)
    setDefaultValue(name, value)

    // Validation
    validate(): FormData | false
    validateField(name): boolean
    clearErrors()

    // State
    disableForm()
    enableForm()

    // Submission
    send<T>(url): Promise<T> | null

    // Error handling
    addCommonError(message)
    addCommonErrors(messages[])
}
```

**Usage:**
```typescript
const formTool = new FormTool(component);

formTool
    .setCommonErrors(commonErrorsEl)
    .setProgress(progressEl)
    .addField('email', emailEl)
    .addField('name', nameEl)
    .addControl(submitBtn);

// Submit
const result = formTool.send<{ ok: boolean }>('/api/save');
if (result) {
    result.then(data => {
        if (data.ok) goTo(window.location.href);
    });
}
```

**Features:**
- Debounced validation on input (300ms)
- Validation on blur
- Server error mapping to fields
- Upload progress handling
- CSRF token auto-injection

---

## Data Tables

### GridTable (`Common/Dom/GridTable/GridTable.ts`)

Editable data tables built on top of GridJS:

```typescript
class GridTable extends Component {
    init() {
        // 1. Load grid config from base64 JSON
        // 2. Build GridJS table
        // 3. Attach edit handlers
    }

    buildGrid() {
        new Grid({
            columns: this.makeGridConfig(gridInfo).columns,
            data: gridItems,
            ...gridConfig()
        }).render(container);
    }

    editHandler(event, element) {
        // Show edit form instead of grid
        this.formBuilder.buildForm(rowData);
    }

    handleSuccess(result) {
        // Update grid data + re-render
        this.grid.updateConfig(newConfig);
    }
}
```

### FormBuilder (`Common/Dom/GridTable/FormBuilder.ts`)

Builds forms dynamically from a config object:

```typescript
class FormBuilder {
    constructor(
        container: HTMLElement,
        gridInfo: IGridInfo,
        onSuccess: (data) => void,
        onCancel: () => void
    ) {}

    buildForm(data: Record<string, unknown>) {
        // Create form fields based on gridInfo.fields
        // Attach FormTool
        // Setup submit handler
    }

    getFormTool(): FormTool
}
```

---

## API Layer

### sendPost (`Common/Api/sendPost.ts`)

```typescript
const sendPost = <Request, Response>(
    url: string,
    params: Request
): Promise<Response> => {
    // Auto-inject CSRF token
    const params = { ...params, CSRF_TOKEN: window.__GARNET_CSRF__ };

    return fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(params)
    }).then(asyncJsonThen);
};
```

### sendPostFormData (`Common/Api/sendPostFormData.ts`)

For forms that include files:

```typescript
const sendPostFormData = <T>(
    url: string,
    formData: FormData,
    onProgress?: (percent: number) => void
): Promise<T>
```

### Response Handling

```typescript
// asyncJsonThen.ts - parse JSON response
const asyncJsonThen = <T>(response: Response): Promise<IApiResponse<T>>

// RespError.ts - structured error
class RespError extends Error {
    status: number;
    response: IApiResponse | string | null;
}
```

---

## Event System

### PageEvents (`Common/Utils/PageEvents.ts`)

Simple event bus for cross-component communication:

```typescript
enum ECommonEvents {
    PAGE_RELOADED = 'PAGE_RELOADED',
}

class PageEvents {
    static init(): PageEvents

    on(event: string, callback: (params) => void)
    emmit(event: string, params: TEventParams)
}
```

**Usage:**
```typescript
PageEvents.init().on(ECommonEvents.PAGE_RELOADED, () => {
    // Re-initialize components after page transition
});
```

---

## I18n

### I18nBase (`Common/Utils/I18nBase.ts`)

```typescript
class I18nBase {
    protected data: Record<string, string> = {};

    t(key: string, params?: string[]): string {
        let text = this.data[key] ?? key; // Return key if not found
        // Substitute params: {0}, {1}, etc.
        return text;
    }
}
```

Generated classes (`Framework/Scripts/I18nGen/I18nFramework.ts`):
```typescript
class I18nFramework extends I18nBase {
    static Common_FromHasError(): string {
        return this.getInstance().t('Common_FromHasError');
    }
}
```

---

## DOM Utilities

### ClassClick (`Common/Dom/ClassClick.ts`)

Event delegation by class name:

```typescript
const unsubscribe = classClick(
    container,
    'my-action-class',
    (event, element) => {
        // Handle click
    }
);

// Later: unsubscribe()
```

### DomObserver (`Common/Dom/DomObserver.ts`)

MutationObserver wrapper for element removal detection:

```typescript
const observer = DomObserver.init();

const id = observer.registerElementRemoval(element, () => {
    console.log('Element removed from DOM');
});

// Later: observer.unregisterElementRemoval(id);
```

---

## Type Definitions (`Common/Models.ts`)

```typescript
// Event params
type TEventParams = null | Record<string, unknown>;

// API responses
interface IApiSuccessResponse<T = Record<string, unknown>> {
    ok?: boolean;
    data?: T;
}

interface IApiErrorResponse {
    ok?: boolean;
    message?: string;
    errors?: TFromBackendErrors;
    commonErrors?: string | string[];
}

type IApiResponse<T> = IApiSuccessResponse<T> | IApiErrorResponse;

// Form errors from backend
type TFromBackendErrors = Record<string, string[] | string>;

// Form values
type TFromMap = Record<string, string | number | boolean | File | null>;
```

---

## Summary

| Component | Purpose |
|-----------|---------|
| `Component` | Base class with lifecycle |
| `DomEl` | DOM manipulation wrapper |
| `PageLoader` | SPA page transitions |
| `GoTo` | Navigation with pushState |
| `HotClick` | Intercept links for SPA nav |
| `FormTool` | Form validation & submission |
| `GridTable` | Data tables with editing |
| `FormBuilder` | Dynamic form generation |
| `sendPost` | REST API calls |
| `PageEvents` | Cross-component events |
| `I18nBase` | Internationalization |

**Total Common utilities: ~1700 lines**
