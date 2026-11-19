import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';

interface AdminDashboardProps {
    currentApp: string;
    apps: string[];
    csrfToken: string;
}

const JSON_HEADERS = {'Content-Type': 'application/json'};
const readJson = (response: Response) => response.json();
const renderAppOption = (app: string) => <option key={app} value={app}>{app}</option>;
const returnToAdmin = () => {
    window.location.href = '/__garnet/';
};

export class AdminDashboard extends AntiHookComponent<AdminDashboardProps> {
    private readonly ui = new Carburetor({
        selectedApp: this.props.currentApp,
        activeApp: this.props.currentApp,
        output: '',
        isRunning: false,
    });
    private readonly outputRef = React.createRef<HTMLPreElement>();
    private eventSource: EventSource | null = null;
    private commandGeneration = 0;
    private unmounted = false;

    private readonly scrollToBottom = () => {
        const pre = this.outputRef.current;
        if (pre) {
            pre.scrollTop = pre.scrollHeight;
        }
    };

    protected useEffects(): void {
        this.unmounted = false;
        // Scroll only after React has committed the new output to the DOM.
        this.useEffect('scrollOutput', this.scrollToBottom, [this.outputRef.current?.textContent]);
    }

    public componentWillUnmount(): void {
        this.unmounted = true;
        this.closeCommand();
        super.componentWillUnmount();
    }

    private patchUi(updates: Partial<ReturnType<AdminDashboard['ui']['getData']>>): void {
        this.ui.setData({...this.ui.getData(), ...updates});
    }

    private appendOutput(text: string): void {
        this.patchUi({output: this.ui.getData().output + text});
    }

    private closeCommand(): void {
        ++this.commandGeneration;
        this.eventSource?.close();
        this.eventSource = null;
    }

    private readonly stopCmd = () => {
        if (this.eventSource) {
            this.closeCommand();
            this.patchUi({isRunning: false});
            this.appendOutput('\n--- Stopped ---\n');
        }
    };

    private runCmd(cmd: string): void {
        this.stopCmd();
        const generation = ++this.commandGeneration;
        this.patchUi({output: '$ php garnet ' + cmd + '\n', isRunning: true});

        // EventSource cannot POST the CSRF token, so its GET only redeems a ticket.
        fetch('/__garnet/api/exec-ticket', {
            method: 'POST',
            headers: JSON_HEADERS,
            body: JSON.stringify({cmd, csrf: this.props.csrfToken}),
        })
            .then(readJson)
            .then(data => {
                if (generation !== this.commandGeneration) return;
                if (!data.ticket) {
                    this.appendOutput('Error: ' + (data.error || 'could not start command') + '\n');
                    this.patchUi({isRunning: false});
                    return;
                }

                const es = new EventSource('/__garnet/api/exec?ticket=' + encodeURIComponent(data.ticket));
                this.eventSource = es;

                es.addEventListener('message', (e) => {
                    if (this.eventSource === es) {
                        this.appendOutput(JSON.parse(e.data) + '\n');
                    }
                });
                es.addEventListener('done', (e: MessageEvent) => {
                    this.finishCommand(es, '\nDone (exit ' + e.data + ')\n');
                });
                es.addEventListener('error', () => {
                    this.finishCommand(es, '\n--- Connection lost ---\n');
                });
            })
            .catch(err => {
                if (generation !== this.commandGeneration) return;
                this.appendOutput('Error: ' + err.message + '\n');
                this.patchUi({isRunning: false});
            });
    }

    private finishCommand(es: EventSource, message: string): void {
        if (this.eventSource !== es) return;
        this.appendOutput(message);
        this.closeCommand();
        this.patchUi({isRunning: false});
    }

    private readonly selectApp = (e: React.ChangeEvent<HTMLSelectElement>) => {
        this.patchUi({selectedApp: e.target.value});
    };

    private readonly switchApp = async () => {
        const {selectedApp} = this.ui.getData();
        try {
            const response = await fetch('/__garnet/api/app-use', {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({app: selectedApp, csrf: this.props.csrfToken}),
            });
            const data = await readJson(response);
            if (this.unmounted) return;
            if (data.ok) {
                this.patchUi({activeApp: selectedApp});
                this.appendOutput('Switched to: ' + selectedApp + '\n');
            } else {
                this.appendOutput('Error: ' + (data.error || 'unknown') + '\n');
            }
        } catch (err) {
            if (!this.unmounted) this.appendOutput('Error: ' + (err as Error).message + '\n');
        }
    };

    private readonly logout = () => {
        fetch('/__garnet/logout', {
            method: 'POST',
            headers: JSON_HEADERS,
            body: JSON.stringify({csrf: this.props.csrfToken}),
        }).then(returnToAdmin);
    };

    private readonly build = () => this.runCmd('build');
    private readonly watch = () => this.runCmd('build:watch');
    private readonly prepare = () => this.runCmd('prepare');
    private readonly migrate = () => this.runCmd('migration');
    private readonly clear = () => this.patchUi({output: ''});

    private renderHeader() {
        return (
            <header className="clidashboard-header">
                <div className="clidashboard-header-inner">
                    <h1 className="clidashboard-title">Garnet Admin</h1>
                    <button onClick={this.logout} className="clidashboard-logout">
                        Logout
                    </button>
                </div>
            </header>
        );
    }

    private renderAppSwitcher(selectedApp: string, activeApp: string) {
        const {apps} = this.props;
        return (
            <div className="clidashboard-card">
                <div className="clidashboard-app-row">
                    <span className="clidashboard-label">App:</span>
                    <select
                        value={selectedApp}
                        onChange={this.selectApp}
                        className="clidashboard-select"
                    >
                        {apps.map(renderAppOption)}
                    </select>
                    <button
                        onClick={this.switchApp}
                        disabled={selectedApp === activeApp}
                        className="clidashboard-switch"
                    >
                        Switch
                    </button>
                    {activeApp && (
                        <span className="clidashboard-current">Current: {activeApp}</span>
                    )}
                </div>
            </div>
        );
    }

    private renderCommands(isRunning: boolean) {
        return (
            <div className="clidashboard-card">
                <div className="clidashboard-command-row">
                    <button onClick={this.build} disabled={isRunning} className="clidashboard-build">Build</button>
                    <button onClick={this.watch} disabled={isRunning} className="clidashboard-watch">Build:Watch</button>
                    <button onClick={this.prepare} disabled={isRunning} className="clidashboard-prepare">Prepare</button>
                    <button onClick={this.migrate} disabled={isRunning} className="clidashboard-migration">Migration</button>
                    {isRunning && (
                        <button onClick={this.stopCmd} className="clidashboard-stop">Stop</button>
                    )}
                </div>
            </div>
        );
    }

    private renderOutput(output: string) {
        return (
            <div className="clidashboard-card">
                <div className="clidashboard-output-header">
                    <h2 className="clidashboard-label">Output</h2>
                    <button onClick={this.clear} className="clidashboard-clear">Clear</button>
                </div>
                <pre
                    ref={this.outputRef}
                    className="clidashboard-output"
                >
                    {output}
                </pre>
            </div>
        );
    }

    public render() {
        const {selectedApp, activeApp, output, isRunning} = this.useCarburetor(this.ui);
        return (
            <div className="clidashboard-page">
                {this.renderHeader()}
                <main className="clidashboard-main">
                    {this.renderAppSwitcher(selectedApp, activeApp)}
                    {this.renderCommands(isRunning)}
                    {this.renderOutput(output)}
                </main>
            </div>
        );
    }
}
