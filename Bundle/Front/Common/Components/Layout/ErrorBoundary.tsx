import * as React from 'react';
import {D} from '@common/Support/Debug/D';

interface ErrorBoundaryState {
	hasError: boolean;
}

export class ErrorBoundary extends React.Component<React.PropsWithChildren, ErrorBoundaryState> {
	state: ErrorBoundaryState = {hasError: false};

	/** Marks the boundary as failed after a descendant throws. */
	static getDerivedStateFromError(): ErrorBoundaryState {
		return {hasError: true};
	}

	/** Logs the captured error and component stack. */
	componentDidCatch(error: Error, info: React.ErrorInfo): void {
		D('error-boundary', {error: error.message, stack: error.stack, componentStack: info.componentStack});
	}

	/** Reloads the current page after a boundary failure. */
	private handleReload = (): void => {
		window.location.reload();
	};

	/** Renders the fallback or the protected children. */
	render(): React.ReactNode {
		if (this.state.hasError) {
			return (
				<div
					data-test-id="error-boundary"
					className="error-boundary-message"
					style={{
						padding: '24px',
						textAlign: 'center',
					}}
				>
					<p style={{marginBottom: '12px'}}>
						Something went wrong
					</p>
					<button
						type="button"
						className="send-button-primary"
						onClick={this.handleReload}
						data-test-id="error-boundary-reload"
					>
						Reload
					</button>
				</div>
			);
		}

		return this.props.children;
	}
}
