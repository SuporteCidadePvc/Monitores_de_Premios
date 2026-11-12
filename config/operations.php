<?php

return [
    'snapshot_cache_store' => env('MONITOR_SNAPSHOT_CACHE_STORE', 'file'),
    'query_lock_seconds' => (int) env('MONITOR_QUERY_LOCK_SECONDS', 10),
    'query_lock_wait_seconds' => (int) env('MONITOR_QUERY_LOCK_WAIT_SECONDS', 2),
    'stale_snapshot_after_seconds' => max(1, (int) env('MONITOR_STALE_SNAPSHOT_AFTER_SECONDS', 300)),
    'odbc_retry_after_failure_seconds' => max(1, (int) env('MONITOR_ODBC_RETRY_AFTER_FAILURE_SECONDS', 60)),
];
