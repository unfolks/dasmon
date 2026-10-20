<script>
    let { meta, loading, label, showThresholds = false } = $props();
</script>

<div
    class="source-notice"
    class:warning={['stale', 'unavailable'].includes(meta.status)}
    role="status"
>
    <strong>{label} · Zabbix</strong>
    <span>
        {#if meta.status === 'loading'}Mengambil data…
        {:else if meta.status === 'live'}Snapshot terbaru · diperbarui otomatis setiap 30 detik.
        {:else if meta.status === 'stale'}Snapshot terakhir · koneksi bermasalah.
        {:else if meta.status === 'unconfigured'}Lokasi belum dikonfigurasi.
        {:else}Data belum tersedia.{/if}
        {#if loading && meta.status !== 'loading'}
            Memperbarui…{/if}
    </span>
    {#if meta.updatedAt}<small>Snapshot: {new Date(meta.updatedAt).toLocaleString('id-ID')}</small
        >{/if}
    {#if meta.message}<p>{meta.message}</p>{/if}
    {#if showThresholds && meta.thresholds}
        {#if label === 'NAS'}
            <p>
                Warning: storage ≥ {meta.thresholds.storage}%, CPU ≥ {meta.thresholds.cpu}%, suhu
                NAS ≥ {meta.thresholds.temperature}°C, disk ≥ {meta.thresholds.disk_temperature}°C.
            </p>
        {:else}
            <p>
                Warning: CPU ≥ {meta.thresholds.cpu}%, RAM ≥ {meta.thresholds.memory}%, disk ≥ {meta
                    .thresholds.disk}%.
            </p>
        {/if}
    {/if}
</div>

<style>
    .source-notice {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        padding: 14px 18px;
        margin-bottom: 16px;
        background: #eef5ff;
        border: 1px solid #dce7f8;
        border-radius: 10px;
        color: #30466c;
        font-size: 13px;
    }
    .source-notice.warning {
        background: #fff6e9;
        border-color: #f5dfba;
        color: #875713;
    }
    small {
        margin-left: auto;
    }
    p {
        flex-basis: 100%;
        margin: 0;
    }
</style>
