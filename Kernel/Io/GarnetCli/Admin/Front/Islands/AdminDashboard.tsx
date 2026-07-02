import * as React from 'react';
import {useState, useRef, useCallback, useEffect} from 'react';

interface AdminDashboardProps {
    currentApp: string;
    apps: string[];
}

export const AdminDashboard: React.FC<AdminDashboardProps> = ({currentApp, apps}) => {
    const [selectedApp, setSelectedApp] = useState(currentApp);
    const [activeApp, setActiveApp] = useState(currentApp);
    const [output, setOutput] = useState('');
    const [isRunning, setIsRunning] = useState(false);
    const outputRef = useRef<HTMLPreElement>(null);
    const eventSourceRef = useRef<EventSource | null>(null);

    const scrollToBottom = useCallback(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, []);

    useEffect(scrollToBottom, [output, scrollToBottom]);

    const appendOutput = useCallback((text: string) => {
        setOutput(prev => prev + text);
    }, []);

    const stopCmd = useCallback(() => {
        if (eventSourceRef.current) {
            eventSourceRef.current.close();
            eventSourceRef.current = null;
            setIsRunning(false);
            appendOutput('\n--- Stopped ---\n');
        }
    }, [appendOutput]);

    const runCmd = useCallback((cmd: string) => {
        stopCmd();
        setOutput('$ php garnet ' + cmd + '\n');
        setIsRunning(true);

        const es = new EventSource('/__garnet/api/exec?cmd=' + encodeURIComponent(cmd));
        eventSourceRef.current = es;

        es.onmessage = (e) => {
            appendOutput(JSON.parse(e.data) + '\n');
        };

        es.addEventListener('done', (e: MessageEvent) => {
            appendOutput('\nDone (exit ' + e.data + ')\n');
            es.close();
            eventSourceRef.current = null;
            setIsRunning(false);
        });

        es.onerror = () => {
            appendOutput('\n--- Connection lost ---\n');
            es.close();
            eventSourceRef.current = null;
            setIsRunning(false);
        };
    }, [stopCmd, appendOutput]);

    const switchApp = useCallback(() => {
        // NOTE: raw fetch (not sendPost) is intentional here — the `/__garnet/*`
        // endpoints are served by the GarnetCli admin server (a separate backend
        // from the main app), and do not share its CSRF token / response shape.
        fetch('/__garnet/api/app-use', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({app: selectedApp}),
        })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    setActiveApp(selectedApp);
                    appendOutput('Switched to: ' + selectedApp + '\n');
                } else {
                    appendOutput('Error: ' + (data.error || 'unknown') + '\n');
                }
            })
            .catch(err => appendOutput('Error: ' + err.message + '\n'));
    }, [selectedApp, appendOutput]);

    const logout = useCallback(() => {
        // NOTE: raw fetch (not sendPost) — see switchApp comment above. This hits
        // the GarnetCli admin server, not the main app. window.location.href is
        // also acceptable here because we are deliberately leaving the admin SPA.
        fetch('/__garnet/logout', {method: 'POST'}).then(() => {
            window.location.href = '/__garnet/';
        });
    }, []);

    const cmdBtn = 'px-4 py-2 text-sm font-medium text-white rounded disabled:opacity-40 disabled:cursor-not-allowed';

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-200 sticky top-0 z-10">
                <div className="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
                    <h1 className="text-xl font-bold text-gray-900">Garnet Admin</h1>
                    <button onClick={logout} className="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-200">
                        Logout
                    </button>
                </div>
            </header>

            <main className="max-w-4xl mx-auto px-6 py-6 space-y-4">
                {/* App Switcher */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div className="flex items-center gap-3">
                        <span className="text-sm font-medium text-gray-600">App:</span>
                        <select
                            value={selectedApp}
                            onChange={e => setSelectedApp(e.target.value)}
                            className="border border-gray-300 rounded px-2 py-1 text-sm text-gray-700 bg-white"
                        >
                            {apps.map(app => (
                                <option key={app} value={app}>{app}</option>
                            ))}
                        </select>
                        <button
                            onClick={switchApp}
                            disabled={selectedApp === activeApp}
                            className="px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            Switch
                        </button>
                        {activeApp && (
                            <span className="text-xs text-gray-400">Current: {activeApp}</span>
                        )}
                    </div>
                </div>

                {/* Command Buttons */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div className="flex flex-wrap gap-2">
                        <button onClick={() => runCmd('build')} disabled={isRunning} className={`${cmdBtn} bg-green-600 hover:bg-green-700`}>Build</button>
                        <button onClick={() => runCmd('build:watch')} disabled={isRunning} className={`${cmdBtn} bg-yellow-500 hover:bg-yellow-600`}>Build:Watch</button>
                        <button onClick={() => runCmd('prepare')} disabled={isRunning} className={`${cmdBtn} bg-cyan-500 hover:bg-cyan-600`}>Prepare</button>
                        <button onClick={() => runCmd('migration')} disabled={isRunning} className={`${cmdBtn} bg-red-600 hover:bg-red-700`}>Migration</button>
                        {isRunning && (
                            <button onClick={stopCmd} className={`${cmdBtn} bg-gray-500 hover:bg-gray-600`}>Stop</button>
                        )}
                    </div>
                </div>

                {/* Output Terminal */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-medium text-gray-600">Output</h2>
                        <button onClick={() => setOutput('')} className="text-xs text-gray-400 hover:text-gray-600">Clear</button>
                    </div>
                    <pre
                        ref={outputRef}
                        className="bg-gray-900 text-green-400 rounded p-4 h-96 overflow-y-auto text-sm font-mono whitespace-pre-wrap"
                    >
                        {output}
                    </pre>
                </div>
            </main>
        </div>
    );
};
