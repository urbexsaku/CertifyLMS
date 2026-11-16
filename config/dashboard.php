<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Cache
    |--------------------------------------------------------------------------
    |
    | 管理者ダッシュボードの集計結果をキャッシュするための設定。
    |
    */

    'admin_kpi_cache_key' => 'dashboard.admin_kpi',

    'admin_completion_rate_cache_key' => 'dashboard.admin_completion_rate',

    'admin_stats_cache_ttl' => (int) env('DASHBOARD_ADMIN_STATS_CACHE_TTL', 300),
];
