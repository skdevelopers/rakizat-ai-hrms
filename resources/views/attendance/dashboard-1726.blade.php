<x-app-layout title="Attendance Dashboard" is-header-blur="true">
    <main
        class="main-content w-full px-[var(--margin-x)] pb-8"
        x-data="attendanceDashboard()"
        x-init="init()"
    >

        <!-- HEADER -->
        <div class="mt-6 flex items-center justify-between">
            <h2 class="text-base font-medium tracking-wide text-slate-700 dark:text-navy-100">
                Attendance Devices
            </h2>

            <div class="flex gap-3">
                <span class="text-xs text-slate-500 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                    Auto-refresh (60s)
                </span>

                <button
                    class="btn bg-primary/10 text-primary hover:bg-primary/20
                           dark:bg-accent-light/10 dark:text-accent-light dark:hover:bg-accent-light/20"
                    @click="refreshAll(true)"
                    :disabled="loadingAll"
                >
                    <span x-show="!loadingAll">↻ Refresh</span>
                    <span x-show="loadingAll">Refreshing...</span>
                </button>
            </div>
        </div>

        <!-- SUMMARY BAR -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="card p-4 text-center">
                <p class="text-xs text-slate-500">Total Devices</p>
                <p class="text-xl font-semibold" x-text="devices.length"></p>
            </div>

            <div class="card p-4 text-center">
                <p class="text-xs text-slate-500">Online</p>
                <p class="text-xl font-semibold text-success"
                   x-text="devices.filter(d => d.online).length"></p>
            </div>

            <div class="card p-4 text-center">
                <p class="text-xs text-slate-500">Offline</p>
                <p class="text-xl font-semibold text-error"
                   x-text="devices.filter(d => !d.online).length"></p>
            </div>
        </div>

        <!-- PER COUNTRY SUMMARY -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="country in ['PK','SA']" :key="country">
                <div class="card p-4">
                    <h3 class="text-sm font-semibold mb-2"
                        x-text="country === 'PK' ? '🇵🇰 Pakistan' : '🇸🇦 Saudi Arabia'"></h3>
                    <p class="text-sm text-slate-600">
                        Online:
                        <span class="text-success font-semibold"
                              x-text="devices.filter(d => d.site_id.startsWith(country + '_') && d.online).length"></span>
                        /
                        <span x-text="devices.filter(d => d.site_id.startsWith(country + '_')).length"></span>
                    </p>
                </div>
            </template>
        </div>

        <!-- DEVICES GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">

            <!-- GROUPED RENDER -->
            <template x-for="group in groupedDevices()" :key="group.country">

                <template x-if="group.devices.length">
                    <div class="col-span-full">
                        <h3 class="mt-6 text-sm font-semibold uppercase text-slate-500"
                            x-text="group.country === 'PK' ? '🇵🇰 Pakistan Offices' : '🇸🇦 Saudi Arabia Offices'"></h3>
                    </div>
                </template>

                <template x-for="device in group.devices" :key="device.device_sn">
                    <div class="card p-5 relative">

                        <!-- ALERT STRIPE -->
                        <div
                            x-show="!device.online && device.offline_minutes >= 10"
                            class="absolute top-0 left-0 right-0 h-1 bg-error animate-pulse">
                        </div>

                        <!-- CARD HEADER -->
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-700 dark:text-navy-100"
                                    x-text="device.site_id"></h3>
                                <p class="text-xs text-slate-500"
                                   x-text="device.device_sn"></p>
                            </div>

                            <span
                                class="badge"
                                :class="
                                    device.online
                                        ? 'bg-success/15 text-success'
                                        : device.offline_minutes >= 10
                                            ? 'bg-error text-white animate-pulse'
                                            : 'bg-warning/15 text-warning'
                                ">
                                <span
                                    x-text="
                                        device.online
                                            ? 'ONLINE'
                                            : device.offline_minutes >= 10
                                                ? 'OFFLINE (ALERT)'
                                                : 'OFFLINE'
                                    ">
                                </span>
                            </span>
                        </div>

                        <!-- DETAILS -->
                        <div class="mt-4 space-y-1 text-sm text-slate-600 dark:text-navy-200">
                            <p><strong>IP:</strong> <span x-text="device.ip ?? '—'"></span></p>
                            <p><strong>Last Seen:</strong> <span x-text="device.last_seen_human ?? '—'"></span></p>
                            <p><strong>Last Sync:</strong> <span x-text="device.last_sync ?? '—'"></span></p>
                            <p><strong>Records Today:</strong> <span x-text="device.records_today ?? 0"></span></p>

                            <p>
                                <strong>Heartbeat:</strong>
                                <span :class="device.online ? 'text-success' : 'text-error'"
                                      x-text="device.online ? 'Alive' : 'No heartbeat'"></span>
                                <span x-show="!device.online"
                                      class="ml-1 inline-block w-2 h-2 bg-error rounded-full animate-ping"></span>
                            </p>

                            <p>
                                <strong>Health:</strong>
                                <span
                                    :class="
                                        device.health_score >= 80
                                            ? 'text-success'
                                            : device.health_score >= 50
                                                ? 'text-warning'
                                                : 'text-error'
                                    "
                                    x-text="device.health_score + '%'">
                                </span>
                            </p>
                        </div>

                        <!-- ACTIONS -->
                        <div class="mt-4 flex gap-3">
                            <button
                                class="btn btn-primary w-full"
                                @click="syncNow(device.site_id)"
                                :disabled="syncLoading[device.site_id] === true">
                                <span x-show="syncLoading[device.site_id] !== true">🔄 Sync Now</span>
                                <span x-show="syncLoading[device.site_id] === true">Syncing...</span>
                            </button>

                            <button
                                class="btn bg-slate-150 hover:bg-slate-200 text-slate-700
                                       dark:bg-navy-600 dark:hover:bg-navy-500 dark:text-navy-100"
                                @click="openAudit(device)"
                            >
                                🧾
                            </button>
                        </div>

                    </div>
                </template>
            </template>

            <!-- EMPTY STATE -->
            <div
                x-show="devices.length === 0 && !loadingAll"
                class="card p-6 text-center text-slate-500">
                No attendance devices registered yet.
            </div>
        </div>

        <!-- AUDIT DRAWER -->
        <div
            x-show="auditDevice"
            x-transition
            class="fixed inset-0 bg-black/40 flex justify-end z-50"
            @click.self="auditDevice=null"
        >
            <div class="w-full max-w-md bg-white dark:bg-navy-800 p-6 overflow-y-auto">
                <h3 class="text-lg font-semibold mb-4">Audit Timeline</h3>
                <p class="text-sm mb-2"><strong>Device:</strong> <span x-text="auditDevice?.device_sn"></span></p>
                <p class="text-sm mb-4"><strong>Site:</strong> <span x-text="auditDevice?.site_id"></span></p>

                <template x-for="event in auditEvents" :key="event.ts">
                    <div class="mb-3 border-l-2 pl-3">
                        <p class="text-xs text-slate-500" x-text="event.ts"></p>
                        <p class="text-sm" x-text="event.msg"></p>
                    </div>
                </template>
            </div>
        </div>

    </main>

    <!-- ALPINE LOGIC -->
    <script>
        function attendanceDashboard() {
            return {
                devices: [],
                loadingAll: false,
                siteLoading: {},
                syncLoading: {},
                auditDevice: null,
                auditEvents: [],
                refreshTimer: null,

                groupedDevices() {
                    return [
                        { country: 'PK', devices: this.devices.filter(d => d.site_id.startsWith('PK_')) },
                        { country: 'SA', devices: this.devices.filter(d => d.site_id.startsWith('SA_')) },
                    ];
                },

                async init() {
                    await this.loadDevices();
                    await this.refreshAll();
                    this.startAutoRefresh();
                },

                startAutoRefresh() {
                    this.refreshTimer = setInterval(() => {
                        this.refreshAll(false);
                    }, 60000);
                },

                async loadDevices() {
                    const res = await axios.get('/attendance/devices');
                    if (res?.data?.status === 'ok') {
                        this.devices = res.data.data.map(d => ({
                            ...d,
                            health_score: d.health_score ?? (d.online ? 100 : 40),
                        }));
                    }
                },

                async refreshAll(showLoader = true) {
                    if (showLoader) this.loadingAll = true;
                    try {
                        await Promise.all(
                            this.devices.map(d => this.refreshDevice(d.site_id))
                        );
                    } finally {
                        if (showLoader) this.loadingAll = false;
                    }
                },

                async refreshDevice(site) {
                    this.siteLoading[site] = true;
                    try {
                        const res = await axios.get(`/attendance/status/${encodeURIComponent(site)}`);
                        if (res?.data?.status === 'ok') {
                            const idx = this.devices.findIndex(d => d.site_id === site);
                            if (idx !== -1) {
                                this.devices[idx] = {
                                    ...this.devices[idx],
                                    ...res.data.data,
                                };
                            }
                        }
                    } finally {
                        this.siteLoading[site] = false;
                    }
                },

                async syncNow(site) {
                    this.syncLoading[site] = true;
                    try {
                        const res = await axios.post('/attendance/sync', { site_id: site });
                        if (res?.data?.status === 'ok') {
                            window.showToast(res.data.message, 'success');
                            await this.refreshDevice(site);
                        }
                    } finally {
                        this.syncLoading[site] = false;
                    }
                },

                openAudit(device) {
                    this.auditDevice = device;
                    this.auditEvents = [
                        { ts: device.last_seen_human, msg: 'Last heartbeat received' },
                        { ts: device.last_sync, msg: 'Last sync completed' },
                    ];
                }
            }
        }
    </script>
</x-app-layout>
