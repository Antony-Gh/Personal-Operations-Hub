<?php

return [
    'backups' => [
        'directory' => env('OPERATIONS_BACKUP_PATH', storage_path('app/backups')),
        'source_database' => env('OPERATIONS_BACKUP_SOURCE_DB'),
        'default_retention_days' => (int) env('OPERATIONS_BACKUP_RETENTION_DAYS', 14),
    ],
];
