<script>
    let { status, dot = false } = $props();
    let online = $derived(
        ['Online', 'Resolved', 'Recording', 'Normal', 'OK', 'Up'].includes(status),
    );
    let critical = $derived(
        [
            'Offline',
            'Critical',
            'Down',
            'Failed',
            'Crashed',
            'Degraded',
            'System Partition Failed',
        ].includes(status),
    );
    let warning = $derived(
        [
            'Warning',
            'Repairing',
            'Migrating',
            'Expanding',
            'Deleting',
            'Creating',
            'Raid Syncing',
            'Raid Parity Checking',
            'Initialized',
            'Not Initialized',
        ].includes(status),
    );
</script>

<span
    class="badge"
    class:online
    class:critical
    class:warning
    class:neutral={!online && !critical && !warning}
>
    {#if dot}<i class="status-dot" class:green={online} class:red={critical} class:orange={warning}
        ></i>{/if}
    {status}
</span>
