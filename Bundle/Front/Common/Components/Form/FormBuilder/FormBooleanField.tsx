import * as React from 'react';

interface Props {
    column: string;
    label: string;
    checked: boolean;
    disabled?: boolean;
    onChange: (column: string, value: boolean) => void;
    inputRefs: React.MutableRefObject<Record<string, HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>>;
}

/** Controlled boolean field used by FormBuilder. */
export class FormBooleanField extends React.Component<Props> {
    /** Handles this component operation. */
    private readonly handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.props.onChange(this.props.column, event.target.checked);
    };

    /** Handles this component operation. */
    private readonly setInputRef = (element: HTMLInputElement | null): void => {
        if (element) this.props.inputRefs.current[this.props.column] = element;
    };

    /** Handles this component operation. */
    public render() {
        const {column, label, checked, disabled} = this.props;
        return (
            <div className="form-builder-switch">
                <input
                    type="checkbox"
                    className="form-builder-toggle"
                    checked={checked}
                    disabled={disabled}
                    onChange={this.handleChange}
                    ref={this.setInputRef}
                    data-test-id={`form-field-${column}`}
                />
                <label className="form-builder-switch-label">{label}</label>
            </div>
        );
    }
}
