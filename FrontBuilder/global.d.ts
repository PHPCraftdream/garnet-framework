declare function setInterval(handler: TimerHandler, timeout?: number, ...arguments: any[]): number;

declare function setTimeout(handler: TimerHandler, timeout?: number, ...arguments: any[]): number;

declare function clearInterval(id: number | undefined): void;

declare function clearTimeout(id: number | undefined): void;

// Side-effect style imports bundled by rspack (type: 'css'); no runtime value.
declare module '*.css';
declare module '*.less';
