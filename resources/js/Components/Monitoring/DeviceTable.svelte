<script>
    import StatusBadge from './StatusBadge.svelte';
    import EmptyState from './EmptyState.svelte';
    import Icon from '../../MonitoringIcon.svelte';
    import {
        formatPercent,
        formatMilliseconds,
        formatCheckedAt,
    } from '../../monitoring-formatters.js';
    let {
        activeSection,
        visibleRows,
        selectedDeviceId,
        page,
        pageSize,
        selectDevice,
        clearFilters,
    } = $props();
</script>

<div class="table-scroll">
    <table class="device-table">
        <thead
            ><tr
                ><th>No</th><th
                    >{activeSection === 'cctv'
                        ? 'Camera Name'
                        : 'Device Name'}</th
                ><th>Location</th>{#if activeSection === 'cctv'}<th>Switch</th
                    >{/if}<th>IP Address</th><th>Status</th
                >{#if activeSection === 'cctv'}<th>Latency</th><th
                        >Packet Loss</th
                    ><th>Last Checked</th
                    >{:else if activeSection === 'computers'}<th>CPU</th><th
                        >RAM</th
                    ><th>Monitoring</th>{:else if activeSection === 'nas'}<th
                        >Storage</th
                    ><th>Health</th><th>Backup</th>{:else}<th>Details</th
                    >{/if}<th>Action</th></tr
            ></thead
        ><tbody
            >{#each visibleRows as device, index}<tr
                    class:selected-row={selectedDeviceId === device.id}
                    class:offline-row={device.status === 'Offline'}
                    ><td>{(page - 1) * pageSize + index + 1}</td><td
                        ><button
                            class="device-link"
                            onclick={() => selectDevice(device.id)}
                            >{device.name ?? device.id}</button
                        ></td
                    ><td>{device.location}</td>{#if activeSection === 'cctv'}<td
                            >{(device.switchGroups ?? [])
                                .map((group) => group.name)
                                .join(', ') || 'Unknown'}</td
                        >{/if}<td class="ip-address">{device.ip}</td><td
                        ><StatusBadge status={device.status} /></td
                    >{#if activeSection === 'cctv'}<td
                            >{formatMilliseconds(device.latencyMs)}</td
                        ><td>{formatPercent(device.packetLossPercent)}</td><td
                            class="tabular"
                            >{formatCheckedAt(device.lastCheckedAt)}</td
                        >{:else if activeSection === 'computers'}<td
                            >{formatPercent(device.cpuPercent)}</td
                        ><td>{formatPercent(device.memory[0]?.usedPercent)}</td
                        ><td
                            >{device.monitoringType} · {device.monitoringStatus}</td
                        >{:else if activeSection === 'nas'}<td
                            >{formatPercent(device.storageUsage)}</td
                        ><td><StatusBadge status={device.health} /></td><td
                            >{device.backupStatus}</td
                        >{:else}<td>{device.detail}</td>{/if}<td
                        ><button
                            class="icon-button view-device"
                            aria-label={`View ${device.name ?? device.id}`}
                            onclick={() => selectDevice(device.id)}
                            ><Icon name="eye" size={16} /></button
                        ></td
                    ></tr
                >{:else}<tr
                    ><td
                        colspan={activeSection === 'cctv'
                            ? 10
                            : ['computers', 'nas'].includes(activeSection)
                              ? 9
                              : 7}
                        class="empty-state"
                        ><EmptyState
                            title="No matching devices"
                            description="Try another search or status."
                            {clearFilters}
                        /></td
                    ></tr
                >{/each}</tbody
        >
    </table>
</div>
