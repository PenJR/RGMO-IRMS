<?php

return [
    'user_activity_days' => (int) env('USER_ACTIVITY_RETENTION_DAYS', 90),
    'login_history_days' => (int) env('LOGIN_HISTORY_RETENTION_DAYS', 365),
    'read_notification_days' => (int) env('READ_NOTIFICATION_RETENTION_DAYS', 90),
];
