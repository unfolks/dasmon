export function createMonitoringFeed() {
    let devices = $state([]);
    let meta = $state({ status: 'loading', updatedAt: null, message: null });
    let loading = $state(false);
    let currentEndpoint;
    let controller;
    let revision = 0;

    async function refresh(endpoint) {
        controller?.abort();
        const requestRevision = ++revision;
        controller = new AbortController();
        if (endpoint !== currentEndpoint) {
            currentEndpoint = endpoint;
            devices = [];
            meta = { status: 'loading', updatedAt: null, message: null };
        }
        loading = true;
        try {
            if (!endpoint) throw new Error('Endpoint monitoring belum dikonfigurasi.');
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Request gagal (HTTP ${response.status}).`);
            const payload = await response.json();
            if (!Array.isArray(payload.data) || payload.meta?.source !== 'zabbix') {
                throw new Error('Response monitoring tidak valid.');
            }
            if (revision !== requestRevision) return;
            devices = payload.data;
            meta = payload.meta;
        } catch (error) {
            if (revision !== requestRevision || error.name === 'AbortError') return;
            meta = {
                status: meta.updatedAt ? 'stale' : 'unavailable',
                updatedAt: meta.updatedAt,
                message: error.message,
            };
        } finally {
            if (revision === requestRevision) loading = false;
        }
    }

    return {
        get devices() {
            return devices;
        },
        get meta() {
            return meta;
        },
        get loading() {
            return loading;
        },
        refresh,
        dispose() {
            revision += 1;
            controller?.abort();
        },
    };
}
