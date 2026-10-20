<script>
    import Icon from '../../MonitoringIcon.svelte';
    import { getDeviceSummary } from '../../monitoring-filters.js';

    let {
        devices,
        activeSection,
        currentNavigation,
        switchGroups = [],
        search = $bindable(''),
        statusFilter = $bindable('all'),
        switchGroupFilter = $bindable('all'),
        filtersChanged,
    } = $props();
    let summary = $derived(
        getDeviceSummary(devices, activeSection, currentNavigation),
    );

    function filterBy(status) {
        statusFilter = status;
        filtersChanged();
    }
</script>

<section class="inventory-toolbar" aria-label="Device summary and filters">
    <div class="device-stats">
        {#each summary as stat}
            <button
                class="panel device-stat"
                class:chosen={statusFilter === stat.filter}
                onclick={() => filterBy(stat.filter)}
            >
                <span class="stat-icon {stat.color}"
                    ><Icon name={stat.icon} size={18} /></span
                >
                <span
                    ><small>{stat.label}</small><strong>{stat.value}</strong
                    ></span
                >
            </button>
        {/each}
    </div>
    <div
        class="table-controls"
        class:with-switches={activeSection === 'cctv' &&
            switchGroups.length > 0}
    >
        {#if activeSection === 'cctv' && switchGroups.length}
            <label class="compact-select switch-select">
                <select
                    aria-label="Camera switch"
                    bind:value={switchGroupFilter}
                    onchange={filtersChanged}
                >
                    <option value="all">All Switches</option>
                    {#each switchGroups as group}
                        <option value={group.id}>{group.name}</option>
                    {/each}
                </select>
                <Icon name="down" size={14} />
            </label>
        {/if}
        <label class="search-field">
            <input
                aria-label="Search devices"
                placeholder={activeSection === 'cctv'
                    ? 'Search camera...'
                    : 'Search devices...'}
                bind:value={search}
                oninput={filtersChanged}
            />
            <Icon name="search" size={17} />
        </label>
        <label class="compact-select">
            <select
                aria-label="Device status"
                bind:value={statusFilter}
                onchange={filtersChanged}
            >
                <option value="all">All Status</option><option>Online</option
                ><option>Offline</option>
                {#if activeSection === 'cctv'}<option>Unknown</option
                    >{:else}<option>Warning</option>{/if}
                {#if ['access', 'computers'].includes(activeSection)}
                    <option value="Reachable">Reachable</option>
                    <option value="Attention">Needs Attention</option>
                    <option>Unknown</option>
                {/if}
                {#if activeSection === 'nas'}
                    <option value="Attention">Needs Attention</option>
                    <option>Unknown</option>
                {/if}
            </select>
            <Icon name="down" size={14} />
        </label>
    </div>
</section>

<style>
    .with-switches {
        flex-wrap: wrap;
    }
    .switch-select {
        max-width: 100%;
    }
    .switch-select select {
        min-width: 0;
        max-width: 240px;
    }
    @media (max-width: 640px) {
        .switch-select {
            flex: 1 1 100%;
        }
        .switch-select select {
            width: 100%;
            max-width: none;
        }
    }
</style>
