import * as React from 'react';
import {D} from '@common/Debug/D';

interface ErrorBoundaryState {
	hasError: boolean;
}

export class ErrorBoundary extends React.Component<React.PropsWithChildren, ErrorBoundaryState> {
	state: ErrorBoundaryState = {hasError: false};

	static getDerivedStateFromError(): ErrorBoundaryState {
		return {hasError: true};
	}

	componentDidCatch(error: Error, info: React.ErrorInfo): void {
		D('error-boundary', {error: error.message, stack: error.stack, componentStack: info.componentStack});
	}

	private handleReload = (): void => {
		window.location.reload();
	};

	render(): React.ReactNode {
		if (this.state.hasError) {
			return (
				<div
					data-test-id="error-boundary"
					className="text-secondary"
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
						className="btn btn-primary"
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
