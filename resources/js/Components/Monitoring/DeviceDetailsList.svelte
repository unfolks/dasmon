<script>
    let { entries } = $props();
</script>

<dl class="detail-list">
    {#each entries as [label, value]}
        <div>
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    {/each}
</dl>
