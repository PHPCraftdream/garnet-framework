# SPA Architecture

## Overview

FrontBuilder implements a Single Page Application on top of server-side Twig rendering. It is a hybrid approach:

|| Feature | Traditional MPA | Our Approach |
|---------|-----------------|--------------|
| Initial Load | Full HTML | Full HTML |
| Navigation | Full reload | Partial reload |
| SEO | Excellent | Excellent |
| FCP | Fast | Fast |
| UX | Clunky | Smooth |

## How It Works

### Traditional MPA
```
User clicks link → Browser requests page → Server returns HTML → Full page render
```

### Our Hybrid SPA
```
Initial load → Server returns full HTML (SEO, fast FCP)
Navigation → fetch HTML → replace body.innerHTML (SPA UX)
Forms → fetch JSON → update DOM (REST API)
```

## Key Components

```
┌─────────────────────────────────────────────────────────────────┐
│                    Hybrid SPA Flow                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │   Server    │     │   Browser   │     │  Component  │       │
│  │   (Twig)    │────►│  (HTML+JS)  │────►│  (Init)     │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│                                                                  │
│  User navigates:                                                 │
│                                                                  │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │ HotClick    │────►│   goTo()    │────►│ PageLoader  │       │
│  │ (intercept) │     │ (fetch HTML)│     │ (swap body) │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│                                                                  │
│  User submits form:                                              │
│                                                                  │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐       │
│  │  FormTool   │────►│  sendPost   │────►│   Update    │       │
│  │ (validate)  │     │  (REST API) │     │    DOM      │       │
│  └─────────────┘     └─────────────┘     └─────────────┘       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Current Limitations

### What We Don't Have

1. **Client-side routing with state preservation**
   - Full page swap on navigation
   - No partial updates (except forms)

2. **Component state management**
   - No global state store
   - State lives in DOM or PHP session

3. **Optimistic updates**
   - All updates wait for server response
   - No rollback on error

4. **Code splitting per route**
   - All JS for a page loads at once
   - No lazy loading of components

### Workarounds in Current Stack

1. **For partial updates**: Use `sendPost()` + manual DOM update
2. **For state**: Use `window.__GARNET__` global or data attributes
3. **For optimistic updates**: Update DOM first, revert on error
4. **For lazy loading**: Dynamic `import()` in entry points

## Best Practices

### DO

```typescript
// Use Component lifecycle
class MyPage extends Component {
    init() {
        // Setup
    }
    onRemove() {
        // Cleanup
    }
}

// Use FormTool for forms
formTool.send('/api/save').then(handleSuccess);

// Use goTo() for navigation
goTo('/dashboard');

// Use PageEvents for cross-component communication
PageEvents.init().on(ECommonEvents.PAGE_RELOADED, reinit);
```

### DON'T

```typescript
// Don't bypass FormTool validation
fetch('/api/save', { body: JSON.stringify(data) });

// Don't use window.location.href directly
window.location.href = '/dashboard'; // Full page reload!

// Don't forget cleanup in onRemove
class LeakyComponent extends Component {
    init() {
        this.interval = setInterval(poll, 1000);
    }
    // Missing onRemove() = memory leak
}
```

## Summary

|| Aspect | Status |
|--------|--------|
| SPA navigation | Yes (HotClick + PageLoader) |
| REST API | Yes (sendPost + FormTool) |
| SEO | Yes (Twig SSR) |
| Fast FCP | Yes (HTML from server) |
| Component lifecycle | Yes (Component class) |
| Form handling | Yes (FormTool) |
| Data tables | Yes (GridTable + GridJS) |
| I18n | Yes (I18nBase + generated) |
| Events | Yes (PageEvents) |
