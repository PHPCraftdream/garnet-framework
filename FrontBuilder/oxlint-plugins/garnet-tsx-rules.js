const MAX_LINES = 500;
const MAX_COMPONENT_NESTING = 3;

const TYPE_DECLARATION_TYPES = new Set(['TSInterfaceDeclaration', 'TSTypeAliasDeclaration']);

/**
 * Counts value-level top-level exports of a .tsx file — `export type`/
 * `export interface`/`export type {...}` are excluded, since a Props type
 * colocated with its component isn't "another thing" the file does.
 */
const oneExportPerComponent = {
    meta: {
        docs: {
            description: 'A .tsx file must have exactly one value-level top-level export (a component file is one component).',
        },
    },
    create(context) {
        if (!context.filename?.endsWith('.tsx')) {
            return {};
        }

        return {
            Program(node) {
                let count = 0;
                const namedValueExports = new Set();
                let defaultExportStmt = null;

                for (const stmt of node.body) {
                    if (stmt.type === 'ExportDefaultDeclaration') {
                        defaultExportStmt = stmt;
                        continue;
                    }

                    if (stmt.type !== 'ExportNamedDeclaration') {
                        continue;
                    }

                    if (stmt.exportKind === 'type') {
                        continue;
                    }

                    if (stmt.declaration) {
                        if (TYPE_DECLARATION_TYPES.has(stmt.declaration.type)) {
                            continue;
                        }
                        if (stmt.declaration.type === 'VariableDeclaration') {
                            for (const decl of stmt.declaration.declarations) {
                                count++;
                                if (decl.id.type === 'Identifier') {
                                    namedValueExports.add(decl.id.name);
                                }
                            }
                        } else {
                            count++;
                            if (stmt.declaration.id) {
                                namedValueExports.add(stmt.declaration.id.name);
                            }
                        }
                        continue;
                    }

                    for (const spec of stmt.specifiers ?? []) {
                        if (spec.exportKind === 'type') {
                            continue;
                        }
                        count++;
                        namedValueExports.add(spec.exported.name);
                    }
                }

                // `export default Foo;` referencing an identifier that's
                // already named-exported is the same component exported two
                // ways for caller convenience, not a second thing this file
                // does — don't double-count it.
                if (defaultExportStmt) {
                    const decl = defaultExportStmt.declaration;
                    const isSameAsNamed = decl.type === 'Identifier' && namedValueExports.has(decl.name);
                    if (!isSameAsNamed) {
                        count++;
                    }
                }

                if (count > 1) {
                    context.report({
                        node,
                        message: `This .tsx file has ${count} value-level exports — only one export per component file is allowed.`,
                    });
                }
            },
        };
    },
};

/** A .tsx file must not exceed MAX_LINES lines. */
const maxComponentLines = {
    meta: {
        docs: {
            description: `A .tsx file must not exceed ${MAX_LINES} lines.`,
        },
    },
    create(context) {
        if (!context.filename?.endsWith('.tsx')) {
            return {};
        }

        return {
            Program(node) {
                const text = context.sourceCode?.text ?? context.getSourceCode().getText();
                const lineCount = text.split('\n').length;

                if (lineCount > MAX_LINES) {
                    context.report({
                        node,
                        message: `This .tsx file has ${lineCount} lines — the limit for a component file is ${MAX_LINES}.`,
                    });
                }
            },
        };
    },
};

/** Whether a JSX tag name refers to a custom component, not an HTML element. */
function isComponentTagName(nameNode) {
    if (nameNode.type === 'JSXIdentifier') {
        return /^[A-Z]/.test(nameNode.name);
    }
    return nameNode.type === 'JSXMemberExpression';
}

/**
 * Counts how deep custom (capitalized) JSX components nest inside each
 * other — HTML tags (div/span/...) don't count, only component composition
 * depth does.
 */
const maxComponentNesting = {
    meta: {
        docs: {
            description: `JSX component nesting inside a .tsx file must not exceed ${MAX_COMPONENT_NESTING} levels (HTML tags don't count).`,
        },
    },
    create(context) {
        if (!context.filename?.endsWith('.tsx')) {
            return {};
        }

        let depth = 0;

        return {
            JSXElement(node) {
                if (!isComponentTagName(node.openingElement.name)) {
                    return;
                }
                depth++;
                if (depth > MAX_COMPONENT_NESTING) {
                    context.report({
                        node,
                        message: `JSX component nesting depth is ${depth} — the limit is ${MAX_COMPONENT_NESTING} levels.`,
                    });
                }
            },
            'JSXElement:exit'(node) {
                if (isComponentTagName(node.openingElement.name)) {
                    depth--;
                }
            },
        };
    },
};

const plugin = {
    meta: { name: 'garnet-tsx' },
    rules: {
        'one-export-per-component': oneExportPerComponent,
        'max-component-lines': maxComponentLines,
        'max-component-nesting': maxComponentNesting,
    },
};

export default plugin;
