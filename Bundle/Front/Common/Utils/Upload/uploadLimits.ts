/** Whole megabytes, rounded down — never promise more room than there is. */
export function megabytes(bytes: number): number {
    return Math.floor(bytes / (1024 * 1024));
}

export {uploadMaxBytes} from '@common/Utils/Upload/uploadMaxBytes';
