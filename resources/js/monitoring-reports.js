export function createAlertReportCsv(alerts, warehouseName) {
    const rows = [
        ['Time', 'Device', 'Category', 'Message', 'Status', 'Warehouse'],
        ...alerts.map((alert) => [
            alert.time,
            alert.device,
            alert.category,
            alert.message,
            alert.status,
            warehouseName,
        ]),
    ];

    return (
        '\uFEFF' +
        rows
            .map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(','))
            .join('\r\n')
    );
}

export function downloadAlertReport(alerts, warehouse) {
    const csv = createAlertReportCsv(alerts, warehouse.name);
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `monitoring-${warehouse.id}-demo.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
