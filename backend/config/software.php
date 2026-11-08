<?php

/*
|--------------------------------------------------------------------------
| Software, licences, antivirus (Phase 5)
|--------------------------------------------------------------------------
*/

return [

    // Asset categories (codes) whose devices are expected to carry software and antivirus
    // (policies without a category, compliance report, dashboard).
    'device_categories' => ['computer', 'server'],

    // An antivirus report older than this many days counts as "unknown".
    'stale_report_days' => (int) env('SOFTWARE_STALE_REPORT_DAYS', 30),

    // Licences expiring within this many days raise one notification per licence and date.
    'license_notice_days' => (int) env('SOFTWARE_LICENSE_NOTICE_DAYS', 30),

    'import_max_rows' => 5000,

];
