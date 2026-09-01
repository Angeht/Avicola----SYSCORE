<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'directory' => env('BACKUP_DIRECTORY', 'respaldos/mysql'),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 900),
    'dump_binary' => env('MYSQLDUMP_BINARY'),
    'mysql_binary' => env('MYSQL_BINARY'),
    'laragon_mysql_root' => env('LARAGON_MYSQL_ROOT', 'C:\\laragon\\bin\\mysql'),
    'scheduler_task_name' => env('BACKUP_SCHEDULER_TASK', 'Avicola Laravel Scheduler'),
];
