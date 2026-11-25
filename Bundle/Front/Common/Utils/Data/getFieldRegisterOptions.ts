import {TGridFieldInfo} from '@common/Dom/GridTable/Models';
import {isNumericFieldInfo} from '@common/Utils/Data/isNumericFieldInfo';

/** Returns the field registration options inferred from its validation rules. */
export function getFieldRegisterOptions(fieldInfo: TGridFieldInfo): Record<string, unknown> {
    if (isNumericFieldInfo(fieldInfo)) return {valueAsNumber: true};
    return {};
}
