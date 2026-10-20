export function filterDevices(
    devices,
    search = '',
    status = 'all',
    switchGroupId = 'all',
) {
    const query = search.toLowerCase();

    return devices.filter((device) => {
        const matchesSearch =
            `${device.id} ${device.name ?? ''} ${device.location} ${device.ip} ${device.detail ?? ''} ${(device.switchGroups ?? []).map((group) => group.name).join(' ')}`
                .toLowerCase()
                .includes(query);
        const matchesStatus =
            status === 'all' ||
            device.status === status ||
            (status === 'Reachable' && device.isReachable === true) ||
            (status === 'Attention' &&
                ['Warning', 'Offline'].includes(device.status));

        const matchesSwitch =
            switchGroupId === 'all' ||
            (device.switchGroups ?? []).some(
                (group) => group.id === switchGroupId,
            );

        return matchesSearch && matchesStatus && matchesSwitch;
    });
}

export function filterAlerts(alerts, search = '', status = 'all') {
    const query = search.toLowerCase();

    return alerts.filter(
        (alert) =>
            `${alert.device} ${alert.category} ${alert.message}`
                .toLowerCase()
                .includes(query) &&
            (status === 'all' || alert.status === status),
    );
}

export function getDeviceSummary(devices, section, navigationItem) {
    const online = devices.filter(
        (device) => device.status === 'Online',
    ).length;

    if (section === 'nas') {
        return [
            {
                label: 'Total NAS',
                value: devices.length,
                icon: navigationItem.icon,
                color: 'navy',
                filter: 'all',
            },
            {
                label: 'Online',
                value: online,
                icon: 'check',
                color: 'green',
                filter: 'Online',
            },
            {
                label: 'Needs Attention',
                value: devices.filter((device) =>
                    ['Warning', 'Offline'].includes(device.status),
                ).length,
                icon: 'bell',
                color: 'orange',
                filter: 'Attention',
            },
            {
                label: 'Unknown',
                value: devices.filter((device) => device.status === 'Unknown')
                    .length,
                icon: 'shield',
                color: 'navy',
                filter: 'Unknown',
            },
        ];
    }

    if (['access', 'computers'].includes(section)) {
        return [
            {
                label:
                    section === 'access'
                        ? 'Total Access Point'
                        : 'Total Computers',
                value: devices.length,
                icon: navigationItem.icon,
                color: 'navy',
                filter: 'all',
            },
            {
                label: 'Reachable',
                value: devices.filter((device) => device.isReachable === true)
                    .length,
                icon: 'check',
                color: 'green',
                filter: 'Reachable',
            },
            {
                label: 'Needs Attention',
                value: devices.filter((device) =>
                    ['Warning', 'Offline'].includes(device.status),
                ).length,
                icon: 'bell',
                color: 'orange',
                filter: 'Attention',
            },
            {
                label: 'Unknown',
                value: devices.filter((device) => device.status === 'Unknown')
                    .length,
                icon: 'shield',
                color: 'navy',
                filter: 'Unknown',
            },
        ];
    }

    if (section === 'cctv') {
        return [
            {
                label: 'Total Camera',
                value: devices.length,
                icon: 'camera',
                color: 'navy',
                filter: 'all',
            },
            {
                label: 'Online',
                value: online,
                icon: 'check',
                color: 'green',
                filter: 'Online',
            },
            {
                label: 'Offline',
                value: devices.filter((device) => device.status === 'Offline')
                    .length,
                icon: 'signalOff',
                color: 'red',
                filter: 'Offline',
            },
            {
                label: 'Unknown',
                value: devices.filter((device) => device.status === 'Unknown')
                    .length,
                icon: 'shield',
                color: 'navy',
                filter: 'Unknown',
            },
        ];
    }

    return [
        {
            label: 'Total Devices',
            value: devices.length,
            icon: navigationItem.icon,
            color: 'navy',
            filter: 'all',
        },
        {
            label: 'Online',
            value: online,
            icon: 'check',
            color: 'green',
            filter: 'Online',
        },
        {
            label: 'Needs Attention',
            value: devices.length - online,
            icon: 'bell',
            color: 'orange',
            filter: section === 'computers' ? 'Offline' : 'Warning',
        },
    ];
}
