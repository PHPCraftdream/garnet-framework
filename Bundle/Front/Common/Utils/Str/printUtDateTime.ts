export const printUtDateTime = (ut: number | string): string => {
    const a = new Date(Number(ut) * 1000);
    const year = a.getFullYear();
    const month = (a.getMonth() + 1).toString().padStart(2, '0');
    const day = a.getDate().toString().padStart(2, '0');
    const hour = a.getHours().toString().padStart(2, '0');
    const min = a.getMinutes().toString().padStart(2, '0');

    return `${year}-${month}-${day} ${hour}:${min}`;
};
