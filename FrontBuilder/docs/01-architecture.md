# FrontBuilder Architecture

## Overview

FrontBuilder is a monorepo for the frontend code of every Garnet Framework application. It uses Rspack to bundle JS/TS/CSS.

## Directory Structure

```
FrontBuilder/
├── rspack.config.ts           # Main build configuration
├── tailwind.config.js         # Tailwind CSS config
├── package.json
├── tsconfig.json
├── global.d.ts                # Global type declarations
│
├── Framework/                 # Shared framework code
│   ├── Scripts/               # TypeScript entry points (Auth, Framework, GridTable, etc.)
│   ├── Styles/                # LESS files + Tailwind directives
│   ├── Assets/                # Static assets (libraries, images)
│   └── ThirdParty/            # Third-party integrations
│
├── Common/                    # Shared utilities across all apps
│   ├── Dom/                   # DOM manipulation (Component, FormTool, etc.)
│   ├── Api/                   # API helpers (sendPost, etc.)
│   ├── Utils/                 # Utilities (I18n, Events, etc.)
│   ├── Models.ts              # TypeScript interfaces
│   └── Enums.ts               # TypeScript enums
│
├── {AppName}/                 # App-specific bundles (MyApp, Blog, etc.)
│   ├── Common/
│   ├── Dashboard/
│   └── Foreground/
│
└── docs/                      # Documentation
```

## Application Directories

Each application (e.g., `MyApp/`, `Blog/`) follows this pattern:

```
{AppName}/
├── Common/
│   ├── Scripts/    # Shared scripts for the app
│   ├── Styles/     # Shared styles (LESS)
│   └── Assets/     # Static files
├── Dashboard/
│   ├── Scripts/    # Dashboard-specific scripts
│   ├── Styles/
│   └── Assets/
└── Foreground/
    ├── Scripts/    # Public-facing scripts
    ├── Styles/
    └── Assets/
```

## Key Files

| File | Purpose |
|------|---------|
| `rspack.config.ts` | Build configuration, entry points, PHP class generation |
| `tailwind.config.js` | Tailwind CSS content paths, plugins |
| `Common/Models.ts` | TypeScript interfaces (IApiResponse, TFormErrors, etc.) |
| `Common/Dom/Component.ts` | Base class for all UI components |
| `Common/Dom/PageLoader.ts` | SPA page transitions |
| `Common/Dom/FormTool.ts` | Form validation and submission |
