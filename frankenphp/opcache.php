<?php
declare(strict_types=1);

define('THOUSAND_SEPARATOR', true);

if (!extension_loaded('Zend OPcache')) {
    echo '<section style="padding:16px"><div style="background:#fff3cd;color:#856404;border:1px solid #ffeeba;padding:12px;border-radius:6px;">Zend OPcache extension is not loaded.</div></section>';
    return;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

final class OpCacheDashboard
{
    private array $configuration;
    private array $status;
    private array $statusRows = array();
    private array $configRows = array();
    private array $scriptGroups = array();

    public function __construct()
    {
        $configuration = opcache_get_configuration();
        $status = opcache_get_status();

        $this->configuration = is_array($configuration) ? $configuration : array();
        $this->status = is_array($status) ? $status : array();

        $this->prepareStatusRows();
        $this->prepareConfigRows();
        $this->prepareScriptGroups();
    }

    public function getPageTitle(): string
    {
        $version = $this->configuration['version']['version'] ?? 'unknown';
        return 'PHP ' . phpversion() . ' with OpCache ' . $version;
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
        $scripts = $this->status['scripts'] ?? array();
        return is_array($scripts) ? count($scripts) : 0;
    }

    public function getGraphDataSetJson(): string
    {
        $stats = $this->status['opcache_statistics'] ?? array();
        $memory = $this->status['memory_usage'] ?? array();

        $cachedKeys = (int)($stats['num_cached_keys'] ?? 0);
        $maxKeys = (int)($stats['max_cached_keys'] ?? 0);
        $freeKeys = max(0, $maxKeys - $cachedKeys);

        $dataset = array(
            'memory' => array(
                (float)($memory['used_memory'] ?? 0),
                (float)($memory['free_memory'] ?? 0),
                (float)($memory['wasted_memory'] ?? 0),
            ),
            'keys' => array($cachedKeys, $freeKeys),
            'hits' => array(
                (int)($stats['misses'] ?? 0),
                (int)($stats['hits'] ?? 0),
            ),
            'TSEP' => THOUSAND_SEPARATOR ? 1 : 0,
        );

        return (string)json_encode($dataset, JSON_UNESCAPED_SLASHES);
    }

    public function getHumanUsedMemory(): string
    {
        return $this->formatBytes($this->status['memory_usage']['used_memory'] ?? 0);
    }

    public function getHumanFreeMemory(): string
    {
        return $this->formatBytes($this->status['memory_usage']['free_memory'] ?? 0);
    }

    public function getHumanWastedMemory(): string
    {
        return $this->formatBytes($this->status['memory_usage']['wasted_memory'] ?? 0);
    }

    public function getWastedMemoryPercentage(): string
    {
        return number_format((float)($this->status['memory_usage']['current_wasted_percentage'] ?? 0), 2);
    }

    private function prepareStatusRows(): void
    {
        foreach ($this->status as $key => $value) {
            if ($key === 'scripts') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $nestedKey => $nestedValue) {
                    $this->statusRows[] = array(
                        'key' => (string)$nestedKey,
                        'value' => $this->formatMetricValue((string)$nestedKey, $nestedValue),
                    );
                }
                continue;
            }

            $this->statusRows[] = array(
                'key' => (string)$key,
                'value' => $this->normalizeValue($value),
            );
        }
    }

    private function prepareConfigRows(): void
    {
        $directives = $this->configuration['directives'] ?? array();
        if (!is_array($directives)) {
            return;
        }

        foreach ($directives as $key => $value) {
            $formatted = (string)$key === 'opcache.memory_consumption'
                ? $this->formatBytes((float)$value)
                : $this->normalizeValue($value);

            $this->configRows[] = array(
                'key' => (string)$key,
                'value' => $formatted,
            );
        }
    }

    private function prepareScriptGroups(): void
    {
        $scripts = $this->status['scripts'] ?? array();
        if (!is_array($scripts) || $scripts === array()) {
            return;
        }

        $dirs = array();
        foreach ($scripts as $fullPath => $data) {
            if (!is_array($data)) {
                continue;
            }

            $path = (string)$fullPath;
            $dir = dirname($path);
            $file = basename($path);
            $dirs[$dir][$file] = $data;
        }

        ksort($dirs);
        foreach ($dirs as &$files) {
            ksort($files);
        }
        unset($files);

        $groupId = 1;
        foreach ($dirs as $dir => $files) {
            $count = count($files);
            $totalMemory = 0.0;
            $rows = array();

            foreach ($files as $file => $data) {
                $hits = (int)($data['hits'] ?? 0);
                $memory = (float)($data['memory_consumption'] ?? 0);
                $totalMemory += $memory;

                $rows[] = array(
                    'groupId' => $groupId,
                    'hits' => $this->formatNumber($hits),
                    'memory' => $this->formatBytes($memory),
                    'path' => $count > 1 ? (string)$file : ($dir . DIRECTORY_SEPARATOR . $file),
                );
            }

            $this->scriptGroups[] = array(
                'id' => $groupId,
                'dir' => (string)$dir,
                'count' => $count,
                'fileLabel' => $count === 1 ? 'file' : 'files',
                'memory' => $this->formatBytes($totalMemory),
                'rows' => $rows,
            );

            $groupId++;
        }
    }

    private function formatMetricValue(string $key, mixed $value): string
    {
        if (in_array($key, array('used_memory', 'free_memory', 'wasted_memory'), true)) {
            return $this->formatBytes((float)$value);
        }

        if (in_array($key, array('current_wasted_percentage', 'opcache_hit_rate', 'blacklist_miss_ratio'), true)) {
            return number_format((float)$value, 2) . '%';
        }

        if (in_array($key, array('start_time', 'last_restart_time'), true)) {
            $timestamp = (int)$value;
            return $timestamp > 0 ? date(DATE_RFC822, $timestamp) : 'never';
        }

        return $this->normalizeValue($value);
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_int($value)) {
            return $this->formatNumber($value);
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        if (is_array($value)) {
            return (string)json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return (string)$value;
    }

    private function formatNumber(int $value): string
    {
        return THOUSAND_SEPARATOR ? number_format($value) : (string)$value;
    }

    private function formatBytes(float|int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.2f kB', $bytes / 1024);
        }

        return sprintf('%d bytes', (int)$bytes);
    }
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
        :root {
            color-scheme: light;
            --app-bg: #f5f7fb;
            --app-border: #e5e7eb;
            --app-text: #1f2937;
        }

        html,
        body {
            background: var(--app-bg);
            color: var(--app-text);
        }

        .dashboard-header {
            background: #fff;
            border-bottom: 1px solid var(--app-border);
        }

        .dashboard-header .subtitle {
            max-width: 52rem;
        }

        .dashboard-card {
            border: 1px solid var(--app-border);
            box-shadow: 0 10px 30px rgba(17, 24, 39, 0.05);
            height: 100%;
        }

        .dashboard-card .card-content {
            padding: 1.1rem;
        }

        .tabs.is-boxed a {
            font-size: 0.88rem;
            font-weight: 600;
            padding: 0.5rem 0.8rem;
        }

        .tabs.is-boxed {
            margin-bottom: 0.8rem !important;
        }

        .table-scroll {
            max-height: 610px;
            overflow: auto;
        }

        .table-scroll .table tbody th {
            width: 38%;
            text-align: left;
            white-space: nowrap;
        }

        .table-scroll .table tbody td {
            text-align: right;
            white-space: nowrap;
        }

        .table-scroll .table th,
        .table-scroll .table td {
            font-size: 0.86rem;
            padding: 0.38rem 0.45rem;
            vertical-align: middle;
        }

        .scripts-table th:last-child,
        .scripts-table td:last-child {
            text-align: left;
            white-space: normal;
            word-break: break-word;
        }

        .clickable {
            cursor: pointer;
        }

        .graph-layout {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        .graph-canvas-wrap {
            min-height: 220px;
            height: 220px;
            position: relative;
        }

        .chart-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.8rem;
        }

        .metric-card {
            border: 1px solid var(--app-border);
            border-radius: 10px;
            padding: 0.7rem;
            background: #fff;
        }

        .metric-card h3 {
            font-size: 0.85rem;
            font-weight: 700;
            margin: 0 0 0.45rem;
            text-align: center;
        }

        .chart-title .title {
            margin: 0;
        }

        .metric-values .table th,
        .metric-values .table td {
            font-size: 0.8rem;
            padding: 0.32rem 0.4rem;
            vertical-align: middle;
        }

        .metric-values .table th {
            text-align: left;
            white-space: nowrap;
        }

        .metric-values .table td {
            text-align: right;
        }

        .runtime-metric-name {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 700;
        }

        .stats-table th {
            text-align: left;
            white-space: nowrap;
            font-size: 0.84rem;
            font-weight: 600;
        }

        .stats-table td {
            text-align: right;
            white-space: nowrap;
            font-size: 0.84rem;
        }

        .stats-percent {
            color: #6b7280;
            font-size: 0.78rem;
            margin-left: 0.35rem;
        }

        .stats-table th,
        .stats-table td {
            padding-top: 0.38rem;
            padding-bottom: 0.38rem;
        }

        @media screen and (min-width: 1024px) {
            .metric-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<section class="dashboard-header py-4">
    <div class="container is-max-desktop">
        <h1 class="title is-4 mb-2"><?= e($dashboard->getPageTitle()); ?></h1>
        <p class="subtitle is-6 has-text-grey mb-0">Operational status for OpCache memory, hit rates, and cached scripts.</p>
    </div>
</section>

<section class="section py-4">
    <div class="container is-max-desktop">
        <div class="card dashboard-card mb-4">
            <div class="card-content graph-layout">
                <div class="chart-title">
                    <p class="title is-6">Runtime Dashboard</p>
                    <span class="tag is-light">Live</span>
                </div>

                <div class="metric-grid">
                    <div class="metric-card">
                        <h3>Memory</h3>
                        <div class="graph-canvas-wrap">
                            <canvas id="chart-memory"></canvas>
                        </div>
                        <div class="metric-values">
                            <table class="table is-fullwidth is-narrow mb-0">
                                <tbody id="stats-memory"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="metric-card">
                        <h3>Keys</h3>
                        <div class="graph-canvas-wrap">
                            <canvas id="chart-keys"></canvas>
                        </div>
                        <div class="metric-values">
                            <table class="table is-fullwidth is-narrow mb-0">
                                <tbody id="stats-keys"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="metric-card">
                        <h3>Hits</h3>
                        <div class="graph-canvas-wrap">
                            <canvas id="chart-hits"></canvas>
                        </div>
                        <div class="metric-values">
                            <table class="table is-fullwidth is-narrow mb-0">
                                <tbody id="stats-hits"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card dashboard-card">
            <div class="card-content">
                <div id="main-tabs" class="tabs is-boxed is-medium mb-4">
                    <ul>
                        <li class="is-active" data-target="panel-status"><a>Status</a></li>
                        <li data-target="panel-config"><a>Configuration</a></li>
                        <li data-target="panel-scripts"><a>Scripts (<?= $dashboard->getScriptCount(); ?>)</a></li>
                    </ul>
                </div>

                <div id="panel-status" class="tab-panel">
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
                                    <th><?= e((string)$row['key']); ?></th>
                                    <td><?= e((string)$row['value']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="panel-scripts" class="tab-panel is-hidden">
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
                                            <th class="clickable" id="head-<?= (int)$group['id']; ?>" colspan="3" onclick="toggleVisible(<?= (int)$group['id']; ?>)">
                                                <?= e((string)$group['dir']); ?> (<?= (int)$group['count']; ?> <?= e((string)$group['fileLabel']); ?>, <?= e((string)$group['memory']); ?>)
                                            </th>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($group['rows'] as $row): ?>
                                        <tr class="script-row group-<?= (int)$row['groupId']; ?>">
                                            <td><?= e((string)$row['hits']); ?></td>
                                            <td><?= e((string)$row['memory']); ?></td>
                                            <td><?= e((string)$row['path']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    const collapsedGroups = {};

    function toggleVisible(groupId) {
        const rows = document.querySelectorAll('.group-' + groupId);
        const header = document.getElementById('head-' + groupId);
        if (!rows.length || !header) {
            return;
        }

        const isCollapsed = collapsedGroups[groupId] === true;
        rows.forEach(function (row) {
            row.style.display = isCollapsed ? '' : 'none';
        });
        collapsedGroups[groupId] = !isCollapsed;
        header.style.color = isCollapsed ? '' : '#9ca3af';
    }

    const tabs = document.querySelectorAll('#main-tabs li[data-target]');
    const panels = document.querySelectorAll('.tab-panel');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.getAttribute('data-target');
            tabs.forEach(function (entry) {
                entry.classList.remove('is-active');
            });
            panels.forEach(function (panel) {
                panel.classList.add('is-hidden');
            });
            tab.classList.add('is-active');
            const targetPanel = document.getElementById(target);
            if (targetPanel) {
                targetPanel.classList.remove('is-hidden');
            }
        });
    });

    const dataset = <?= $dashboard->getGraphDataSetJson(); ?>;

    function formatValue(value) {
        if (dataset.TSEP === 1) {
            return Number(value).toLocaleString('en-US');
        }
        return String(value);
    }

    function formatBytes(bytes) {
        const value = Number(bytes);
        if (value >= 1048576) {
            return (value / 1048576).toFixed(2) + ' MB';
        }
        if (value >= 1024) {
            return (value / 1024).toFixed(2) + ' kB';
        }
        return Math.round(value) + ' bytes';
    }

    function formatMetricValue(metric, value) {
        if (metric === 'memory') {
            return formatBytes(value);
        }
        return formatValue(value);
    }

    const datasetMap = {
        memory: {
            title: 'Memory',
            labels: ['Used', 'Free', 'Wasted'],
            values: dataset.memory,
            colors: ['#ef4444', '#10b981', '#f59e0b']
        },
        keys: {
            title: 'Keys',
            labels: ['Cached Keys', 'Free Keys'],
            values: dataset.keys,
            colors: ['#3b82f6', '#22c55e']
        },
        hits: {
            title: 'Hits',
            labels: ['Misses', 'Cache Hits'],
            values: dataset.hits,
            colors: ['#f97316', '#16a34a']
        },
    };

    const dashboardCharts = {
        memory: {
            canvasId: 'chart-memory',
            statsId: 'stats-memory'
        },
        keys: {
            canvasId: 'chart-keys',
            statsId: 'stats-keys'
        },
        hits: {
            canvasId: 'chart-hits',
            statsId: 'stats-hits'
        }
    };

    function getPercentages(values) {
        const total = values.reduce(function (sum, value) {
            return sum + Number(value);
        }, 0);

        if (total <= 0) {
            return values.map(function () {
                return 0;
            });
        }

        return values.map(function (value) {
            return (Number(value) / total) * 100;
        });
    }

    const doughnutPercentLabels = {
        id: 'doughnutPercentLabels',
        afterDatasetsDraw: function (chartInstance) {
            const values = chartInstance.data.datasets[0].data || [];
            const percentages = getPercentages(values);
            const meta = chartInstance.getDatasetMeta(0);
            const ctx = chartInstance.ctx;

            ctx.save();
            ctx.font = '600 11px Inter, Segoe UI, Arial, sans-serif';
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            meta.data.forEach(function (arc, index) {
                const pct = percentages[index] || 0;
                if (pct < 4) {
                    return;
                }

                const p = arc.getCenterPoint();
                ctx.fillText(pct.toFixed(1) + '%', p.x, p.y);
            });

            ctx.restore();
        }
    };

    function createDoughnutChart(metric) {
        const selected = datasetMap[metric];
        const canvas = document.getElementById(dashboardCharts[metric].canvasId);
        return new Chart(canvas, {
            type: 'doughnut',
            plugins: [doughnutPercentLabels],
            data: {
                labels: selected.labels,
                datasets: [{
                    data: selected.values,
                    backgroundColor: selected.colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    function renderMetricValuesTable(metric) {
        const item = datasetMap[metric];
        const valuesBody = document.getElementById(dashboardCharts[metric].statsId);
        const percentages = getPercentages(item.values);
        const rows = item.labels.map(function (label, index) {
            return '<tr>' +
                '<th><span class="runtime-metric-name"><span class="tag is-light" style="background:' + item.colors[index] + '; width: 0.85rem; height: 0.85rem; border-radius: 9999px;"></span>' + label + '</span></th>' +
                '<td><strong>' + formatMetricValue(metric, item.values[index]) + '</strong><span class="stats-percent">(' + percentages[index].toFixed(1) + '%)</span></td>' +
                '</tr>';
        });

        valuesBody.innerHTML = rows.join('');
    }

    Object.keys(dashboardCharts).forEach(function (metric) {
        createDoughnutChart(metric);
        renderMetricValuesTable(metric);
    });
</script>
</body>
</html>
