<x-app-layout title="Attendance Dashboard" is-header-blur="true">
    <div class="relative">

        <!-- 🔥 ALPINE ROOT (everything must be inside) -->
        <main class="main-content w-full px-[var(--margin-x)] pb-8"
              x-data="attendanceDashboard()"
              x-init="init()"
              x-on:alpine:destroy="
                if (_interval) clearInterval(_interval)
                "  >

            <!-- =========================================================
             HEADER
            ========================================================== -->
            <div class="mt-6 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-slate-800">
                    Attendance Devices
                </h2>

                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-500 flex items-center gap-1">
                        <span class="w-2 h-2 bg-success rounded-full animate-pulse"></span>
                        Auto refresh (60s)
                    </span>

                    <button class="btn bg-primary/10 text-primary"
                            @click="refreshAll(true)"
                            :disabled="loading">
                        ↻ Refresh
                    </button>
                </div>
            </div>
            <!-- DATE RANGE FILTER -->
            <div class="mb-4 space-y-2">
                <div class="flex gap-2">
                    <button class="btn btn-xs"
                            :class="range === 'today' && 'btn-primary'"
                            @click="setRange('today')">Today</button>

                    <button class="btn btn-xs"
                            :class="range === '7' && 'btn-primary'"
                            @click="setRange('7')">7 Days</button>

                    <button class="btn btn-xs"
                            :class="range === '30' && 'btn-primary'"
                            @click="setRange('30')">30 Days</button>
                </div>

                <!-- CUSTOM RANGE -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <input type="datetime-local"
                           class="input input-sm"
                           x-model="customFrom"
                           @change="setCustomRange">

                    <input type="datetime-local"
                           class="input input-sm"
                           x-model="customTo"
                           @change="setCustomRange">
                </div>
            </div>

            <!-- =========================================================
             SUMMARY
            ========================================================== -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Total Devices</p>
                    <p class="text-2xl font-bold" x-text="devices.length"></p>
                </div>

                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Online</p>
                    <p class="text-2xl font-bold text-success"
                       x-text="devices.filter(d => d.online).length"></p>
                </div>

                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Offline</p>
                    <p class="text-2xl font-bold text-error"
                       x-text="devices.filter(d => !d.online).length"></p>
                </div>
            </div>
            <!-- =========================================================
             DEVICE GROUPS
            ========================================================== -->
            <template x-for="group in grouped()" :key="group.code">
                <div class="mt-10">
                    <h3 class="text-sm font-semibold uppercase text-slate-500 mb-3"
                        x-text="group.title"></h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <template x-for="d in group.devices" :key="d.device_sn">
                            <div class="card p-5 border-l-4"
                                 :class="d.online ? 'border-success' : 'border-error'">

                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-semibold" x-text="d.site_id"></h3>
                                        <p class="text-xs text-slate-500" x-text="d.device_sn"></p>
                                    </div>

                                    <span class="badge"
                                          :class="d.online
                                            ? 'bg-success/15 text-success'
                                            : 'bg-error/15 text-error'">
                                        <span x-text="d.online ? 'ONLINE' : 'OFFLINE'"></span>
                                    </span>
                                </div>

                                <div class="mt-4 space-y-1 text-sm">
                                    <p><strong>Last Seen:</strong> <span x-text="d.last_seen_human"></span></p>
                                    <p><strong>Records Today:</strong> <span x-text="d.records_today"></span></p>
                                </div>

                                <div class="mt-3 flex items-center gap-2">
                                    <span class="text-xs text-slate-500">Last Sync:</span>

                                    <span class="badge"
                                          :class="{
                                        'bg-success/15 text-success': d.sync_freshness === 'fresh',
                                        'bg-primary/15 text-primary': d.sync_freshness === 'ok',
                                        'bg-warning/15 text-warning': d.sync_freshness === 'stale',
                                        'bg-error/15 text-error': d.sync_freshness === 'dead',
                                        'bg-slate-200 text-slate-600': d.sync_freshness === 'never'
                                      }">
                                        <span x-text="d.last_attendance_human ?? 'Never'"></span>
                                    </span>

                                    <span x-show="d.sync_freshness === 'stale' || d.sync_freshness === 'dead'"
                                          class="text-xs text-error">
                                        (sync delayed)
                                    </span>
                                </div>

                                <div x-show="d.no_punch_severity"
                                     :class="{
                                       'text-warning bg-warning/10': d.no_punch_severity === 'amber',
                                       'text-error bg-error/10': d.no_punch_severity === 'red'
                                     }"
                                     class="mt-2 text-xs font-semibold border rounded px-2 py-1">
                                    ⚠ Device alive but no punches today
                                </div>

                                <div class="grid grid-cols-2 gap-3 mt-4">
                                    <button class="btn btn-primary"
                                            @click="syncNow(d.site_id)">
                                        🔄 Sync
                                    </button>

                                    <button class="btn bg-slate-200"
                                            @click="openLogs(d)">
                                        📄 Logs
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- =========================================================
             SLIDE-OVER LOG PANEL (INSIDE ALPINE)
            ========================================================== -->
            <div x-show="showLogs"
                 x-transition
                 @click.self="closeLogs()"
                 class="fixed inset-y-0 right-0 z-50 flex bg-black/40">

                <div class="w-[420px] lg:w-[480px] bg-white h-full p-6 overflow-y-auto shadow-2xl">
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <h3 class="text-lg font-semibold">Attendance Logs</h3>
                            <p class="text-xs text-slate-500">
                                <span x-text="activeDevice?.site_id"></span> /
                                <span x-text="activeDevice?.device_sn"></span>
                            </p>
                        </div>
                        <button class="btn btn-sm" @click="closeLogs()">✕</button>
                    </div>

                    <div x-show="logsLoading" class="text-center py-10 text-slate-500">
                        Loading logs…
                    </div>

                    <div x-show="!logsLoading && logs.length === 0"
                         class="text-center py-10 text-slate-400">
                        No attendance records found.
                    </div>

                    <table x-show="logs.length" class="w-full text-xs">
                        <thead class="border-b text-slate-500">
                        <tr>
                            <th>User</th>
                            <th>Time</th>
                            <th>Punch</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y">
                        <template x-for="r in logs" :key="r.timestamp">
                            <tr>
                                <td x-text="r.user_id"></td>
                                <td x-text="new Date(r.timestamp).toLocaleString()"></td>
                                <td class="text-center" x-text="r.punch"></td>
                                <td class="text-center" x-text="r.status"></td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
    <!-- =========================================================
      ALPINE CONTROLLER
     ========================================================== -->
    <!-- =========================================================
     SCRIPT (NO CHANGES REQUIRED)
    ========================================================== -->
    <script>
        function attendanceDashboard() {
            return {
                devices: [],
                loading: false,

                range: 'today',
                customFrom: null,
                customTo: null,

                showLogs: false,
                logsLoading: false,
                logs: [],
                activeDevice: null,

                pollTimer: null,
                sse: null,
                sseConnected: false,
                backoffMs: 5000,

                /* ---------------- INIT ---------------- */
                async init() {
                    await this.loadDevices();
                    await this.refreshAll(false);
                    this.setupVisibilityHooks();
                    this.startLive();
                },

                /* ---------------- VISIBILITY ---------------- */
                setupVisibilityHooks() {
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            this.startLive();
                        } else {
                            this.stopLive();
                        }
                    });

                    window.addEventListener('beforeunload', () => this.stopLive());
                },

                /* ---------------- LIVE MODE ---------------- */
                startLive() {
                    if (document.visibilityState !== 'visible') return;
                    this.startSSE();

                    setTimeout(() => {
                        if (!this.sseConnected) this.startPolling();
                    }, 3000);
                },

                stopLive() {
                    this.stopSSE();
                    this.stopPolling();
                },

                /* ---------------- SSE ---------------- */
                startSSE() {
                    if (this.sse) return;

                    try {
                        this.sse = new EventSource('/dashboard/attendance/stream?interval=5');
                    } catch {
                        return;
                    }

                    this.sseConnected = false;

                    this.sse.addEventListener('hello', () => {
                        this.sseConnected = true;
                        this.backoffMs = 5000;
                    });

                    this.sse.addEventListener('status', (ev) => {
                        this.sseConnected = true;
                        this.backoffMs = 5000;

                        try {
                            const payload = JSON.parse(ev.data);
                            this.applyStreamPayload(payload);
                        } catch {}
                    });

                    this.sse.onerror = () => {
                        this.stopSSE();
                        this.startPolling();
                        this.backoffMs = Math.min(this.backoffMs * 2, 60000);
                    };
                },

                stopSSE() {
                    if (!this.sse) return;
                    this.sse.close();
                    this.sse = null;
                    this.sseConnected = false;
                },

                /* ---------------- POLLING ---------------- */
                startPolling() {
                    if (this.pollTimer) return;

                    this.pollTimer = setInterval(() => {
                        if (document.visibilityState !== 'visible') return;
                        this.refreshAll(false);
                    }, this.backoffMs);
                },

                stopPolling() {
                    if (!this.pollTimer) return;
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                },

                /* ---------------- APPLY SSE PAYLOAD ---------------- */
                applyStreamPayload(payload) {
                    if (!payload?.sites) return;

                    for (const siteId in payload.sites) {
                        const site = payload.sites[siteId];
                        if (!site?.devices) continue;

                        for (const live of site.devices) {
                            const idx = this.devices.findIndex(
                                d => d.site_id === siteId && d.device_sn === live.device_sn
                            );

                            if (idx === -1) continue;

                            this.devices[idx] = {
                                ...this.devices[idx],
                                online: !!live.online,
                                offline_minutes: live.offline_minutes ?? null,
                                records_today: live.records_today ?? 0,
                                last_seen_human: live.last_seen_human ?? '—',

                                last_attendance_human: live.last_attendance_human ?? 'Never',
                                last_attendance_ts: live.last_attendance_ts ?? null,
                                sync_freshness: live.sync_freshness ?? 'never',

                                no_punch_severity: live.no_punch_severity ?? null,
                            };
                        }
                    }
                },

                /* ---------------- GROUPING ---------------- */
                grouped() {
                    return [
                        { code: 'PK', title: '🇵🇰 Pakistan Offices', devices: this.devices.filter(d => d.site_id.startsWith('PK_')) },
                        { code: 'SA', title: '🇸🇦 Saudi Arabia Offices', devices: this.devices.filter(d => d.site_id.startsWith('SA_')) }
                    ];
                },

                /* ---------------- LOAD DEVICES ---------------- */
                async loadDevices() {
                    const res = await axios.get('/dashboard/attendance/devices');
                    if (res.data.status !== 'ok') return;

                    this.devices = res.data.data.map(d => ({
                        site_id: d.site_id,
                        device_sn: d.device_sn,

                        online: false,
                        offline_minutes: null,
                        records_today: 0,
                        last_seen_human: '—',

                        last_attendance_human: 'Never',
                        last_attendance_ts: null,
                        sync_freshness: 'never',

                        no_punch_severity: null,
                    }));
                },

                /* ---------------- POLL REFRESH ---------------- */
                async refreshAll(showLoader = true) {
                    if (showLoader) this.loading = true;

                    const sites = [...new Set(this.devices.map(d => d.site_id))];
                    for (const site of sites) {
                        await this.refreshSite(site);
                    }

                    if (showLoader) this.loading = false;
                },

                async refreshSite(site_id) {
                    const res = await axios.get(`/dashboard/attendance/status/${site_id}`);
                    if (res.data.status !== 'ok') return;

                    for (const live of res.data.data.devices ?? []) {
                        const idx = this.devices.findIndex(
                            d => d.site_id === site_id && d.device_sn === live.device_sn
                        );

                        if (idx === -1) continue;

                        this.devices[idx] = {
                            ...this.devices[idx],
                            online: !!live.online,
                            offline_minutes: live.offline_minutes ?? null,
                            records_today: live.records_today ?? 0,
                            last_seen_human: live.last_seen_human ?? '—',

                            last_attendance_human: live.last_attendance_human ?? 'Never',
                            last_attendance_ts: live.last_attendance_ts ?? null,
                            sync_freshness: live.sync_freshness ?? 'never',

                            no_punch_severity: live.no_punch_severity ?? null,
                        };
                    }
                },

                /* ---------------- LOGS ---------------- */
                async openLogs(device) {
                    this.activeDevice = device;
                    this.showLogs = true;
                    this.logsLoading = true;
                    this.logs = [];

                    const res = await axios.get(`/dashboard/attendance/device/${device.site_id}/${device.device_sn}`);
                    if (res.data.status === 'ok') this.logs = res.data.data;

                    this.logsLoading = false;
                },

                closeLogs() {
                    this.showLogs = false;
                    this.activeDevice = null;
                    this.logs = [];
                },

                async syncNow(site_id) {
                    await this.refreshSite(site_id);
                }
            };
        }
    </script>
</x-app-layout>
