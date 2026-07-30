<?php
declare(strict_types=1);

if (!extension_loaded('Zend OPcache')) {
    echo '<section style="padding:16px"><div style="background:#fff3cd;color:#856404;border:1px solid #ffeeba;padding:12px;border-radius:6px;">Zend OPcache extension is not loaded.</div></section>';
    return;
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

final class OpCacheDashboard
{
    private array $configuration;
    private array $status;
    private array $statusRows = [];
    private array $configRows = [];
    private array $scriptGroups = [];
    private int $scriptCount = 0;

    public function __construct()
    {
        $this->configuration = (array)(opcache_get_configuration() ?: []);
        $this->status = (array)(opcache_get_status() ?: []);
        $this->prepareStatusRows();
        $this->prepareConfigRows();
        $this->prepareScriptGroups();
    }

    public function getPageTitle(): string
    {
        return 'PHP ' . phpversion() . ' with OpCache ' . ($this->configuration['version']['version'] ?? 'unknown');
    }

    public function getStatusRows(): array
    {
        return $this->statusRows;
    }

    public function getConfigRows(): array
    {
        return $this->configRows;
    }

    public function getScriptGroups(): array
    {
        return $this->scriptGroups;
    }

    public function getScriptCount(): int
    {
        return $this->scriptCount;
    }

    public function getGraphDataSetJson(): string
    {
        $st = $this->status['opcache_statistics'] ?? [];
        $m = $this->status['memory_usage'] ?? [];
        $c = (int)($st['num_cached_keys'] ?? 0);
        $mx = (int)($st['max_cached_keys'] ?? 0);

        return (string)json_encode([
                'memory' => [(float)($m['used_memory'] ?? 0), (float)($m['free_memory'] ?? 0), (float)($m['wasted_memory'] ?? 0)],
                'keys' => [$c, max(0, $mx - $c)],
                'hits' => [(int)($st['misses'] ?? 0), (int)($st['hits'] ?? 0)],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function prepareStatusRows(): void
    {
        foreach ($this->status as $key => $value) {
            if ($key === 'scripts') continue;
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $this->statusRows[] = ['key' => (string)$k, 'value' => $this->formatMetricValue((string)$k, $v)];
                }
                continue;
            }
            $this->statusRows[] = ['key' => (string)$key, 'value' => $this->normalizeValue($value)];
        }
    }

    private function prepareConfigRows(): void
    {
        foreach ((array)($this->configuration['directives'] ?? []) as $key => $value) {
            $this->configRows[] = [
                    'key' => (string)$key,
                    'value' => (string)$key === 'opcache.memory_consumption'
                            ? $this->formatBytes((float)$value)
                            : $this->normalizeValue($value),
            ];
        }
    }

    private function prepareScriptGroups(): void
    {
        $scripts = $this->status['scripts'] ?? [];
        if (!is_array($scripts) || $scripts === []) return;

        $dirs = [];
        foreach ($scripts as $path => $data) {
            if (is_array($data)) $dirs[dirname((string)$path)][basename((string)$path)] = $data;
        }

        ksort($dirs);
        $id = 1;
        foreach ($dirs as $dir => $files) {
            ksort($files);
            $count = count($files);
            $mem = 0.0;
            $rows = [];

            foreach ($files as $file => $data) {
                $m = (float)($data['memory_consumption'] ?? 0);
                $mem += $m;
                $rows[] = [
                        'groupId' => $id,
                        'hits' => $this->formatNumber((int)($data['hits'] ?? 0)),
                        'memory' => $this->formatBytes($m),
                        'path' => $count > 1 ? (string)$file : (rtrim($dir, '/') . '/' . $file),
                ];
            }

            $this->scriptGroups[] = [
                    'id' => $id++,
                    'dir' => (string)$dir,
                    'count' => $count,
                    'fileLabel' => $count === 1 ? 'file' : 'files',
                    'memory' => $this->formatBytes($mem),
                    'rows' => $rows,
            ];
            $this->scriptCount += $count;
        }
    }

    private function formatMetricValue(string $key, mixed $value): string
    {
        if (in_array($key, ['used_memory', 'free_memory', 'wasted_memory'], true)) {
            return $this->formatBytes((float)$value);
        }
        if (in_array($key, ['current_wasted_percentage', 'opcache_hit_rate', 'blacklist_miss_ratio'], true)) {
            return number_format((float)$value, 2) . '%';
        }
        if (in_array($key, ['start_time', 'last_restart_time'], true)) {
            $ts = (int)$value;
            return $ts > 0 ? date(DATE_RFC822, $ts) : 'never';
        }
        return $this->normalizeValue($value);
    }

    private function normalizeValue(mixed $value): string
    {
        return match (true) {
            $value === true => 'true',
            $value === false => 'false',
            $value === null => 'null',
            is_int($value) => $this->formatNumber($value),
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_array($value) => (string)json_encode($value, JSON_UNESCAPED_SLASHES),
            default => (string)$value,
        };
    }

    private function formatNumber(int $v): string
    {
        return number_format($v);
    }

    private function formatBytes(float|int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => sprintf('%.2f MB', $bytes / 1048576),
            $bytes >= 1024 => sprintf('%.2f kB', $bytes / 1024),
            default => sprintf('%d bytes', (int)$bytes),
        };
    }
}

if (($_POST['reset'] ?? '') === '1') {
    opcache_reset();
    header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
    exit;
}

$dashboard = new OpCacheDashboard();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($dashboard->getPageTitle()); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root { color-scheme: light; --border: #e5e7eb; }
        html, body { background: #f5f7fb; color: #1f2937; }
        .dashboard-header { background: #fff; border-bottom: 1px solid var(--border); }
        .dashboard-card { border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(17,24,39,.05); height: 100%; }
        .dashboard-card .card-content { padding: 1.1rem; }
        .tabs.is-boxed a { font-size: .88rem; font-weight: 600; padding: .5rem .8rem; }
        .tabs.is-boxed { margin-bottom: .8rem !important; }
        .table-scroll { max-height: 610px; overflow: auto; }
        .table-scroll thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
        .table-scroll .table tbody th { width: 38%; text-align: left; white-space: nowrap; }
        .table-scroll .table tbody td { text-align: right; white-space: nowrap; }
        .table-scroll .table th,
        .table-scroll .table td { font-size: .86rem; padding: .38rem .45rem; vertical-align: middle; }
        .scripts-table th:last-child,
        .scripts-table td:last-child { text-align: left; white-space: normal; word-break: break-word; }
        .clickable { cursor: pointer; }
        .graph-layout { display: flex; flex-direction: column; gap: .7rem; }
        .graph-canvas-wrap { min-height: 220px; height: 220px; position: relative; }
        .chart-title { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
        .chart-title .title { margin: 0; }
        .metric-grid { display: grid; grid-template-columns: 1fr; gap: .8rem; }
        .metric-card { border: 1px solid var(--border); border-radius: 10px; padding: .7rem; background: #fff; }
        .metric-card h3 { font-size: .85rem; font-weight: 700; margin: 0 0 .45rem; text-align: center; }
        .metric-values .table th,
        .metric-values .table td { font-size: .8rem; padding: .32rem .4rem; vertical-align: middle; }
        .metric-values .table th { text-align: left; white-space: nowrap; }
        .metric-values .table td { text-align: right; }
        .runtime-metric-name { display: inline-flex; align-items: center; gap: .45rem; font-weight: 700; }
        .stats-percent { color: #6b7280; font-size: .78rem; margin-left: .35rem; }
        .live-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }
        @media (min-width: 1024px) { .metric-grid { grid-template-columns: repeat(3,minmax(0,1fr)); } }
    </style>
</head>
<body>
<section class="dashboard-header py-4">
    <div class="container is-max-desktop">
        <h1 class="title is-4 mb-2"><?= e($dashboard->getPageTitle()); ?></h1>
        <form method="post" class="mb-2" onsubmit="return confirm('Reset OPcache?')">
            <button class="button is-small is-warning" name="reset" value="1">Reset cache</button>
        </form>
        <p class="subtitle is-6 has-text-grey mb-0">Operational status for OpCache memory, hit rates, and cached
            scripts.</p>
    </div>
</section>

<section class="section py-4">
    <div class="container is-max-desktop">
        <div class="card dashboard-card mb-4">
            <div class="card-content graph-layout">
                <div class="chart-title">
                    <p class="title is-6">Runtime Dashboard</p>
                    <span class="live-dot" title="Live data"></span>
                </div>
                <div class="metric-grid">
                    <?php foreach (['memory' => 'Memory', 'keys' => 'Keys', 'hits' => 'Hits'] as $k => $label): ?>
                        <div class="metric-card">
                            <h3><?= $label; ?></h3>
                            <div class="graph-canvas-wrap">
                                <canvas id="chart-<?= $k; ?>"></canvas>
                            </div>
                            <div class="metric-values">
                                <table class="table is-fullwidth is-narrow mb-0">
                                    <tbody id="stats-<?= $k; ?>"></tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card dashboard-card">
            <div class="card-content">
                <div id="main-tabs" class="tabs is-boxed is-medium mb-4">
                    <ul>
                        <li class="is-active" data-target="panel-scripts"><a>Scripts
                                (<?= $dashboard->getScriptCount(); ?>)</a></li>
                        <li data-target="panel-status"><a>Status</a></li>
                        <li data-target="panel-config"><a>Configuration</a></li>
                    </ul>
                </div>

                <div id="panel-scripts" class="tab-panel">
                    <div class="table-scroll">
                        <table class="table is-fullwidth is-striped is-hoverable is-narrow scripts-table">
                            <thead>
                            <tr>
                                <th>Hits</th>
                                <th>Memory</th>
                                <th>Path</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($dashboard->getScriptCount() === 0): ?>
                                <tr>
                                    <td colspan="3">No cached scripts found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dashboard->getScriptGroups() as $group): ?>
                                    <?php if ((int)$group['count'] > 1): ?>
                                        <tr>
                                            <th class="clickable" id="head-<?= (int)$group['id']; ?>" colspan="3"
                                                onclick="toggleVisible(<?= (int)$group['id']; ?>)">
                                                <?= e((string)$group['dir']); ?>
                                                (<?= (int)$group['count']; ?> <?= e((string)$group['fileLabel']); ?>
                                                , <?= e((string)$group['memory']); ?>)
                                            </th>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($group['rows'] as $row): ?>
                                        <tr class="script-row group-<?= (int)$row['groupId']; ?>">
                                            <td><?= e((string)$row['hits']); ?></td>
                                            <td><?= e((string)$row['memory']); ?></td>
                                            <td title="<?= e((string)$row['path']); ?>"><?= e((string)$row['path']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="panel-status" class="tab-panel is-hidden">
                    <div class="table-scroll">
                        <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                            <tbody>
                            <?php foreach ($dashboard->getStatusRows() as $row): ?>
                                <tr>
                                    <th><?= e((string)$row['key']); ?></th>
                                    <td><?= e((string)$row['value']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="panel-config" class="tab-panel is-hidden">
                    <div class="table-scroll">
                        <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                            <tbody>
                            <?php foreach ($dashboard->getConfigRows() as $row): ?>
                                <tr>
                                    <th><code><?= e((string)$row['key']); ?></code></th>
                                    <td><?= e((string)$row['value']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    const collapsed = {};

    function toggleVisible(id) {
        const rows = document.querySelectorAll('.group-' + id);
        const head = document.getElementById('head-' + id);
        if (!rows.length || !head) return;
        collapsed[id] = !collapsed[id];
        rows.forEach(r => r.style.display = collapsed[id] ? 'none' : '');
        head.style.color = collapsed[id] ? '#9ca3af' : '';
    }

    document.querySelectorAll('#main-tabs li[data-target]').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#main-tabs li').forEach(t => t.classList.remove('is-active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('is-hidden'));
            tab.classList.add('is-active');
            document.getElementById(tab.dataset.target)?.classList.remove('is-hidden');
        });
    });

    const dataset = <?= $dashboard->getGraphDataSetJson(); ?>;

    const fmtBytes = v => v >= 1048576 ? (v / 1048576).toFixed(2) + ' MB' : v >= 1024 ? (v / 1024).toFixed(2) + ' kB' : Math.round(v) + ' bytes';
    const fmtVal   = (metric, v) => metric === 'memory' ? fmtBytes(v) : Number(v).toLocaleString('en-US');

    const metrics = {
        memory: { labels: ['Used', 'Free', 'Wasted'], values: dataset.memory, colors: ['#ef4444', '#10b981', '#f59e0b'], canvasId: 'chart-memory', statsId: 'stats-memory' },
        keys: { labels: ['Cached Keys', 'Free Keys'], values: dataset.keys, colors: ['#3b82f6', '#22c55e'], canvasId: 'chart-keys', statsId: 'stats-keys'   },
        hits: { labels: ['Misses', 'Cache Hits'], values: dataset.hits, colors: ['#f97316', '#16a34a'], canvasId: 'chart-hits', statsId: 'stats-hits'   },
    };

    function pct(values) {
        const total = values.reduce((s, v) => s + +v, 0);
        return total > 0 ? values.map(v => (+v / total) * 100) : values.map(() => 0);
    }

    const percentPlugin = {
        id: 'percentLabels',
        afterDatasetsDraw(chart) {
            const pcts = pct(chart.data.datasets[0].data);
            const { ctx } = chart;
            ctx.save();
            ctx.font = '600 11px Inter, Segoe UI, Arial, sans-serif';
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach((arc, i) => {
                if (pcts[i] < 4) return;
                const p = arc.getCenterPoint();
                ctx.fillText(pcts[i].toFixed(1) + '%', p.x, p.y);
            });
            ctx.restore();
        }
    };

    Object.entries(metrics).forEach(([metric, cfg]) => {
        new Chart(document.getElementById(cfg.canvasId), {
            type: 'doughnut',
            plugins: [percentPlugin],
            data: { labels: cfg.labels, datasets: [{ data: cfg.values, backgroundColor: cfg.colors, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { display: false } } },
        });

        const pcts = pct(cfg.values);
        document.getElementById(cfg.statsId).innerHTML = cfg.labels.map((label, i) =>
            `<tr><th><span class="runtime-metric-name"><span class="tag is-light" style="background:${cfg.colors[i]};width:.85rem;height:.85rem;border-radius:9999px"></span>${label}</span></th>` +
            `<td><strong>${fmtVal(metric, cfg.values[i])}</strong><span class="stats-percent">(${pcts[i].toFixed(1)}%)</span></td></tr>`
        ).join('');
    });
</script>
</body>
</html>
