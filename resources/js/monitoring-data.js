export const navigation = [
    { id: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    { id: 'cctv', label: 'CCTV', icon: 'camera' },
    { id: 'network', label: 'Network (ISP)', icon: 'globe' },
    { id: 'computers', label: 'Computers', icon: 'computer' },
    { id: 'access', label: 'Access Point', icon: 'wifi' },
    { id: 'nas', label: 'NAS', icon: 'server' },
    { id: 'temperature', label: 'Temperature', icon: 'temperature' },
    { id: 'ups', label: 'UPS', icon: 'battery' },
    { id: 'reports', label: 'Reports', icon: 'report' },
    { id: 'settings', label: 'Settings', icon: 'settings' },
];

export const warehouses = [
    {
        id: 'krian',
        name: 'Krian Warehouse',
        cameras: 50,
        offline: 2,
        computers: 35,
        accessPoints: 20,
    },
    {
        id: 'sidoarjo',
        name: 'Sidoarjo Warehouse',
        cameras: 24,
        offline: 1,
        computers: 18,
        accessPoints: 10,
    },
    {
        id: 'surabaya',
        name: 'Surabaya Warehouse',
        cameras: 32,
        offline: 0,
        computers: 24,
        accessPoints: 14,
    },
];

const locations = [
    'Gate In',
    'Gate Out',
    'Cold Storage A',
    'Cold Storage B',
    'Loading Dock',
    'Office 1',
    'Office 2',
    'Packing Area',
    'Warehouse A',
    'Warehouse B',
];

export function buildInventory(warehouse, temperatureThreshold = 35) {
    const cameras = Array.from({ length: warehouse.cameras }, (_, index) => {
        const number = index + 1;
        const offline =
            (number === 2 && warehouse.offline > 0) || (number === 19 && warehouse.offline > 1);

        return {
            id: `CCTV-${String(number).padStart(2, '0')}`,
            location: locations[index % locations.length],
            ip: `192.168.${warehouses.indexOf(warehouse) + 1}.${101 + index}`,
            status: offline ? 'Offline' : 'Online',
            recording: !offline,
            lastSeen: offline ? '12:14:05' : `14:25:${String(30 - (index % 25)).padStart(2, '0')}`,
            model: 'IP Camera · 4 MP',
        };
    });

    const computers = Array.from({ length: warehouse.computers }, (_, index) => ({
        id: `PC-${String(index + 1).padStart(2, '0')}`,
        location: locations[index % locations.length],
        ip: `192.168.10.${index + 10}`,
        status:
            index >= warehouse.computers - (warehouse.id === 'krian' ? 3 : 1)
                ? 'Offline'
                : 'Online',
        detail: index % 2 ? 'Windows 11 · 16 GB RAM' : 'Windows 11 · 8 GB RAM',
    }));

    const accessPoints = Array.from({ length: warehouse.accessPoints }, (_, index) => ({
        id: `AP-${String(index + 1).padStart(2, '0')}`,
        location: locations[index % locations.length],
        ip: `192.168.20.${index + 10}`,
        status: [6, Math.min(12, warehouse.accessPoints - 1)].includes(index)
            ? 'Warning'
            : 'Online',
        detail: [6, Math.min(12, warehouse.accessPoints - 1)].includes(index)
            ? '180 ms latency'
            : `${18 + (index % 12)} ms latency`,
    }));

    return {
        cctv: cameras,
        computers,
        access: accessPoints,
        network: [
            {
                id: 'ISP 1',
                location: 'Main connection',
                ip: '192.168.1.1',
                status: 'Online',
                detail: '100 Mbps · 32 ms latency',
            },
            {
                id: 'ISP 2',
                location: 'Backup connection',
                ip: '192.168.2.1',
                status: 'Online',
                detail: '100 Mbps · 24 ms latency',
            },
        ],
        nas: [
            {
                id: 'NAS-01',
                location: 'IT Room',
                ip: '192.168.1.200',
                status: 'Online',
                detail: '68% storage used · Backup OK',
            },
        ],
        temperature: [
            ...[
                { id: 'TEMP-01', location: 'IT Room', temperature: 24 },
                { id: 'TEMP-02', location: 'NAS', temperature: 32 },
                { id: 'CACHE-01', location: 'Cache', temperature: 38 },
            ].map((item, index) => ({
                ...item,
                ip: `192.168.1.${210 + index}`,
                status: item.temperature >= temperatureThreshold ? 'Warning' : 'Online',
                detail: `${item.temperature}°C · ${item.temperature >= temperatureThreshold ? 'Above warning threshold' : 'Normal'}`,
            })),
        ],
        ups: [
            {
                id: 'UPS-01',
                location: 'IT Room',
                ip: '192.168.1.220',
                status: 'Online',
                detail: '42% load · 98% battery · 42 min runtime',
            },
        ],
    };
}

export function buildAlerts(warehouse) {
    return [
        ...(warehouse.offline
            ? [
                  {
                      time: '14:21',
                      device: 'CCTV-02',
                      category: 'CCTV',
                      section: 'cctv',
                      message: 'Camera offline',
                      status: 'Critical',
                  },
              ]
            : []),
        {
            time: '14:18',
            device: 'AP-07',
            category: 'Access Point',
            section: 'access',
            message: 'High latency 180 ms',
            status: 'Warning',
        },
        {
            time: '14:05',
            device: 'NAS-01',
            category: 'NAS',
            section: 'nas',
            message: 'Storage usage reached 87%',
            status: 'Warning',
        },
        {
            time: '13:55',
            device: 'CACHE-01',
            category: 'Temperature',
            section: 'temperature',
            message: 'Temperature reached 48°C',
            status: 'Warning',
        },
        {
            time: '13:40',
            device: 'UPS-01',
            category: 'UPS',
            section: 'ups',
            message: 'UPS offline · recovered at 13:42',
            status: 'Critical',
        },
        {
            time: '13:22',
            device: 'ISP 2',
            category: 'Network',
            section: 'network',
            message: 'Connection restored',
            status: 'Resolved',
        },
        {
            time: '12:45',
            device: `AP-${String(Math.min(13, warehouse.accessPoints)).padStart(2, '0')}`,
            category: 'Access Point',
            section: 'access',
            message: 'High latency 160 ms',
            status: 'Warning',
        },
        {
            time: '12:14',
            device: 'CCTV-02',
            category: 'CCTV',
            section: 'cctv',
            message: 'Camera connection interrupted',
            status: warehouse.offline ? 'Critical' : 'Resolved',
        },
    ];
}
