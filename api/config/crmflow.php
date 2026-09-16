<?php

return [
    'worker_token' => env('CRM_FLOW_WORKER_TOKEN', 'crmflow-worker-dev-token'),
    'payment_webhook_secret' => env('CRM_FLOW_PAYMENT_WEBHOOK_SECRET', 'crmflow-payment-whsec'),
    'claim_lock_ttl_ms' => (int) env('CRM_FLOW_CLAIM_LOCK_TTL_MS', 300000),
    'intake' => [
        'source_refill_per_second' => (float) env('CRM_FLOW_SOURCE_REFILL', 200),
        'source_burst' => (int) env('CRM_FLOW_SOURCE_BURST', 5000),
        'key_refill_per_second' => (float) env('CRM_FLOW_KEY_REFILL', 200),
        'key_burst' => (int) env('CRM_FLOW_KEY_BURST', 5000),
    ],
    'outbound' => [
        'refill_per_second' => (float) env('CRM_FLOW_OUTBOUND_REFILL', 20),
        'burst' => (int) env('CRM_FLOW_OUTBOUND_BURST', 40),
    ],
    'streams' => [
        'order_created' => env('CRM_FLOW_ORDER_STREAM', 'crmflow:stream:order.created'),
    ],
];
