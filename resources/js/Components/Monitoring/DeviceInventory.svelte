<script>
    import { untrack } from 'svelte';
    import { filterDevices } from '../../monitoring-filters.js';
    import DeviceToolbar from './DeviceToolbar.svelte';
    import DeviceTable from './DeviceTable.svelte';
    import TablePagination from './TablePagination.svelte';
    import DeviceDetailPanel from './DeviceDetailPanel.svelte';

    let {
        devices,
        activeSection,
        currentNavigation,
        alerts,
        switchGroups = [],
        initialDeviceId = null,
    } = $props();
    let search = $state('');
    let statusFilter = $state('all');
    let switchGroupFilter = $state('all');
    let page = $state(1);
    let selectedDeviceId = $state(untrack(() => initialDeviceId));
    let detailRevision = $state(0);
    const pageSize = 10;

    let scopedDevices = $derived(
        activeSection === 'cctv'
            ? filterDevices(devices, '', 'all', switchGroupFilter)
            : devices,
    );
    let filteredRows = $derived(
        filterDevices(scopedDevices, search, statusFilter),
    );
    let visibleRows = $derived(
        filteredRows.slice((page - 1) * pageSize, page * pageSize),
    );
    let selectedDevice = $derived(
        filteredRows.find((device) => device.id === selectedDeviceId),
    );

    $effect(() => {
        const lastPage = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        if (page > lastPage) page = lastPage;
        if (
            switchGroupFilter !== 'all' &&
            !switchGroups.some((group) => group.id === switchGroupFilter)
        ) {
            switchGroupFilter = 'all';
        }
    });

    function clearFilters() {
        search = '';
        statusFilter = 'all';
        switchGroupFilter = 'all';
        selectedDeviceId = null;
        page = 1;
    }

    function selectDevice(deviceId) {
        selectedDeviceId = deviceId;
        detailRevision += 1;
    }
</script>

<DeviceToolbar
    devices={scopedDevices}
    {switchGroups}
    {activeSection}
    {currentNavigation}
    bind:search
    bind:statusFilter
    bind:switchGroupFilter
    filtersChanged={() => {
        page = 1;
        selectedDeviceId = null;
    }}
/>
<div class="inventory-layout" class:with-detail={selectedDevice}>
    <section class="panel inventory-panel">
        <DeviceTable
            {activeSection}
            {visibleRows}
            {selectedDeviceId}
            {page}
            {pageSize}
            {selectDevice}
            {clearFilters}
        />
        <TablePagination
            bind:page
            {pageSize}
            totalItems={filteredRows.length}
            itemLabel={activeSection === 'cctv' ? 'cameras' : 'devices'}
        />
    </section>
    {#if selectedDevice}
        {#key detailRevision}
            <DeviceDetailPanel
                {selectedDevice}
                {currentNavigation}
                {activeSection}
                {alerts}
                closeDetails={() => (selectedDeviceId = null)}
            />
        {/key}
    {/if}
</div>
