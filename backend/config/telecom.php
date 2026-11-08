<?php

return [
    // Contract end reminder (days ahead), sent once per contract and end date.
    'contract_notice_days' => (int) env('TELECOM_CONTRACT_NOTICE_DAYS', 30),
];
