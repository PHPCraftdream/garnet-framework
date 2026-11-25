import {TGridFieldInfo} from '@common/Dom/GridTable/Models';

const NUMERIC_VALIDATORS = new Set(['int', 'min', 'max', 'minVal', 'maxVal']);

/** Detects a numeric validator in its encoded rule form. */
function isNumericRule(rule: string): boolean {
    const match = rule.match(/^(\w+)(?:\[(.+?)])?$/);
    return !!match && NUMERIC_VALIDATORS.has(match[1]);
}

const isString = (rule: unknown): rule is string => typeof rule === 'string';

/** Detects whether field metadata contains a numeric validation rule. */
export function isNumericFieldInfo(fieldInfo: TGridFieldInfo): boolean {
    const rules = (fieldInfo.validation ?? []).filter(isString);
    return rules.some(isNumericRule);
}
