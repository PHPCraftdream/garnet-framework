import {TValidationMapped} from '@common/Dom/GridTable/Models';

/** Parses a serialized validation rule into its name and arguments. */
export const parseValidation = (el: string): TValidationMapped | null => {
	const params = el.match(/^(\w+)(\[(.+?)])?$/);
	const name = params?.[1];
	const args = params?.[3]?.split(',') || [];
	return name ? {name, args} : null;
};

