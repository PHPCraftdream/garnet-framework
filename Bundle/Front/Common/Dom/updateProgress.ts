export const updateProgress = (container: HTMLElement, progress: number): void => {
    const progressBar: HTMLElement = container?.querySelector('.progress-bar');

    if (progressBar) {
        const progressVal = `${progress}%`;
        progressBar.style.width = progressVal;
        progressBar.textContent = progressVal;
    }
};
