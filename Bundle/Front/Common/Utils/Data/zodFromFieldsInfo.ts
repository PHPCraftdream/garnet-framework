import {z} from 'zod';
import {TGridFieldInfo} from '@common/Dom/GridTable/Models';
import {I18nFramework as fw} from '@framework/I18nGen/I18nFramework';

type ParsedRule = {name: string; args: string[]};

function parseRule(rule: string): ParsedRule | null {
    const match = rule.match(/^(\w+)(?:\[(.+?)])?$/);
    if (!match) return null;
    return {name: match[1], args: match[2]?.split(',') ?? []};
}

const NUMERIC_VALIDATORS = new Set(['int', 'min', 'max', 'minVal', 'maxVal']);

export function isNumericFieldInfo(fieldInfo: TGridFieldInfo): boolean {
    const rules = (fieldInfo.validation ?? []).filter((v): v is string => typeof v === 'string');
    return rules.some(r => {
        const parsed = parseRule(r);
        return parsed && NUMERIC_VALIDATORS.has(parsed.name);
    });
}

export function getFieldRegisterOptions(fieldInfo: TGridFieldInfo): Record<string, unknown> {
    if (isNumericFieldInfo(fieldInfo)) return {valueAsNumber: true};
    return {};
}

function zodFieldFromInfo(fieldInfo: TGridFieldInfo): z.ZodTypeAny {
    const type = fieldInfo.type;

    if (type === 'bool' || (typeof type === 'object' && 'bool' in type)) {
        return z.boolean().optional();
    }

    const rawRules = (fieldInfo.validation ?? []).filter((v): v is string => typeof v === 'string');
    const rules = rawRules.map(parseRule).filter((r): r is ParsedRule => r !== null);

    if (rules.some(r => NUMERIC_VALIDATORS.has(r.name))) {
        let schema = z.number();
        for (const rule of rules) {
            if (rule.name === 'int') schema = schema.int(fw.Common_Int());
            if (rule.name === 'min' || rule.name === 'minVal') {
                schema = schema.min(Number(rule.args[0]), fw.Common_Min([rule.args[0]]));
            }
            if (rule.name === 'max' || rule.name === 'maxVal') {
                schema = schema.max(Number(rule.args[0]), fw.Common_Max([rule.args[0]]));
            }
        }
        return schema;
    }

    // String field
    let schema = z.string();

    for (const rule of rules) {
        if (rule.name === 'required') {
            schema = schema.min(1, fw.Common_RequiredValue());
        }
        if (rule.name === 'len') {
            const min = Number(rule.args[0]);
            const max = Number(rule.args[1]);
            const msg = fw.Common_Len([rule.args[0], rule.args[1]]);
            schema = schema.min(min, msg).max(max, msg);
        }
        if (rule.name === 'minLen' || rule.name === 'min_len') {
            schema = schema.min(Number(rule.args[0]), fw.Common_MinLength([rule.args[0]]));
        }
        if (rule.name === 'maxLen' || rule.name === 'max_len') {
            schema = schema.max(Number(rule.args[0]), fw.Common_MaxLength([rule.args[0]]));
        }
        if (rule.name === 'nameSymbols') {
            schema = schema.regex(/^[\p{L} ,]+$/u, fw.Common_IncorrectValue());
        }
        if (rule.name === 'simpleText') {
            schema = schema.regex(/^[\p{L}\p{P}\p{So} \x20-\x7E]*$/u, fw.Common_IncorrectValue());
        }
        if (rule.name === 'email') {
            schema = schema.refine(
                v => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
                fw.Common_Email(),
            );
        }
        if (rule.name === 'tzExists') {
            schema = schema.refine(
                v => Intl.supportedValuesOf('timeZone').includes(v),
                fw.Common_IncorrectValue(),
            );
        }
        if (rule.name === 'in_array') {
            const allowed = rule.args;
            schema = schema.refine(v => allowed.includes(v), fw.Common_IncorrectValue());
        }
        if (rule.name === 'pattern') {
            const pattern = rule.args[0];
            const flags = rule.args[1] ?? '';
            if (pattern) {
                schema = schema.regex(new RegExp(pattern, flags), fw.Common_Pattern());
            }
        }
        if (rule.name === 'url') {
            schema = schema.refine(v => {
                if (!v) return true;
                try { new URL(v); return true; } catch { return false; }
            }, fw.Common_Url?.() ?? 'Invalid URL');
        }
        if (rule.name === 'alphanumeric') {
            schema = schema.regex(/^[a-zA-Z0-9]+$/, fw.Common_Alphanumeric?.() ?? 'Invalid value');
        }
    }

    return schema;
}

export function zodFromFieldsInfo(
    fields: Record<string, TGridFieldInfo>,
    detailsFields: string[],
): z.ZodObject<Record<string, z.ZodTypeAny>> {
    const shape: Record<string, z.ZodTypeAny> = {};
    for (const column of detailsFields) {
        const fieldInfo = fields[column];
        if (!fieldInfo || fieldInfo.hidden) continue;
        shape[column] = zodFieldFromInfo(fieldInfo);
    }
    return z.object(shape);
}
