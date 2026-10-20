export function formatPercent(value) {
    return typeof value === 'number' && Number.isFinite(value) ? `${value.toFixed(1)}%` : 'Unknown';
}

export function formatMilliseconds(value) {
    return typeof value === 'number' && Number.isFinite(value) ? `${value} ms` : 'Unknown';
}

export function formatCheckedAt(value) {
    if (!value) return 'Unknown';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 'Unknown' : date.toLocaleString('id-ID');
}

export function formatBytes(value) {
    if (typeof value !== 'number' || !Number.isFinite(value)) return 'Unknown';
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    let index = 0;
    while (value >= 1024 && index < units.length - 1) {
        value /= 1024;
        index += 1;
    }
    return `${value.toFixed(index ? 2 : 0)} ${units[index]}`;
}

export function formatUptime(seconds) {
    if (typeof seconds !== 'number' || !Number.isFinite(seconds)) return 'Unknown';
    const duration = Math.floor(seconds);
    const days = Math.floor(duration / 86400);
    const hours = Math.floor((duration % 86400) / 3600);
    const minutes = Math.floor((duration % 3600) / 60);
    return `${days ? `${days}d ` : ''}${hours}h ${minutes}m ${duration % 60}s`;
}

export function formatBitrate(value) {
    if (typeof value !== 'number' || !Number.isFinite(value)) return 'Unknown';
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    let index = 0;
    while (value >= 1000 && index < units.length - 1) {
        value /= 1000;
        index += 1;
    }
    return `${value.toFixed(index ? 2 : 0)} ${units[index]}`;
}
