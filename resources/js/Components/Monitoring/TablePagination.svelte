<script>
    import Icon from '../../MonitoringIcon.svelte';
    let { page = $bindable(1), pageSize = 10, totalItems, itemLabel = 'devices' } = $props();
    let totalPages = $derived(Math.max(1, Math.ceil(totalItems / pageSize)));
</script>

<div class="table-footer">
    <span
        >Showing {totalItems ? (page - 1) * pageSize + 1 : 0}–{Math.min(
            page * pageSize,
            totalItems,
        )} of {totalItems}
        {itemLabel}</span
    >
    <div class="pagination">
        <button aria-label="Previous page" disabled={page <= 1} onclick={() => (page -= 1)}
            ><span class="previous-chevron"><Icon name="chevron" size={14} /></span></button
        >{#each Array.from({ length: totalPages }, (_, index) => index + 1) as pageNumber}<button
                class:current={page === pageNumber}
                aria-label={`Page ${pageNumber}`}
                aria-current={page === pageNumber ? 'page' : undefined}
                onclick={() => (page = pageNumber)}>{pageNumber}</button
            >{/each}<button
            aria-label="Next page"
            disabled={page >= totalPages}
            onclick={() => (page += 1)}><Icon name="chevron" size={14} /></button
        >
    </div>
</div>
