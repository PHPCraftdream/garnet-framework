/**
 * Строка формы: подпись, поле и место для сообщения об ошибке.
 */

import * as React from 'react';


export const FormRow: React.FC<{label: string; error?: string; align?: 'start' | 'center'; children: React.ReactNode}> = ({label, error, align = 'start', children}) => (
	<div className={align === 'center' ? 'form-row-center' : 'form-row-start'}>
		<label className="form-row-label">{label || '\u00A0'}</label>
		<div className="form-row-field">
			{children}
			{error && <div className="garnet-form-error" data-form-row-error="true">{error}</div>}
		</div>
	</div>
);

// --- Main FormBuilder ---
