<script>
    import Icon from '../../MonitoringIcon.svelte';
    let { title, description, clearFilters } = $props();
</script>

<Icon name="search" size={28} />
<strong>{title}</strong>
<span>{description}</span>
<button class="outline-button compact" onclick={clearFilters}>Clear filters</button>
