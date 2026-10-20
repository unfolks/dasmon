export function buildNasTemperatures(devices, warningThreshold = 60) {
    if (!devices.length) {
        return [
            {
                id: 'nas-temperature',
                location: 'NAS',
                temperature: null,
                status: 'Unknown',
                ip: '—',
                detail: 'Suhu NAS belum tersedia',
                warningThreshold,
            },
        ];
    }
    return devices.map((device) => ({
        id: `nas-temperature-${device.id}`,
        name: device.name,
        location: `NAS · ${device.name}`,
        ip: device.ip,
        temperature: device.temperature,
        warningThreshold,
        status:
            device.temperature === null
                ? 'Unknown'
                : device.temperature >= warningThreshold
                  ? 'Warning'
                  : 'Online',
        detail:
            device.temperature === null
                ? 'Suhu NAS belum tersedia'
                : `${device.temperature}°C · Zabbix`,
    }));
}
