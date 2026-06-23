<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Integrity & Concurrency Control
    | NFR #1 (Race Condition) + NFR #7 (Optimistic Locking) + NFR #8 (ACID)
    |--------------------------------------------------------------------------
    | تعطيل هذه المجموعة يُظهر:
    | - مخزون سالب تحت الضغط (بدون locking)
    | - طلب بدون دفع أو دفع بدون طلب (بدون transactions)
    */
    'use_optimistic_locking' => env('USE_OPTIMISTIC_LOCKING', true),
    'use_db_transactions'    => env('USE_DB_TRANSACTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Optimizations
    | NFR #2 (Resource Management)
    |--------------------------------------------------------------------------
    | تعطيل eager loading يُظهر N+1 query في X-Query-Count header
    | تعطيل rate limiting يُظهر كيف يُثقَل السيرفر بدون حماية
    */
    'use_eager_loading'  => env('USE_EAGER_LOADING', true),
    'use_rate_limiting'  => env('USE_RATE_LIMITING', true),
    'use_pagination'     => env('USE_PAGINATION', true),

    /*
    |--------------------------------------------------------------------------
    | Async & Background Processing
    | NFR #3 (Async Queues) + NFR #4 (Batch Processing)
    |--------------------------------------------------------------------------
    | تعطيل async يجعل checkout ينتظر توليد الفاتورة (response أبطأ)
    */
    'use_async_jobs'       => env('USE_ASYNC_JOBS', true),
    'use_batch_processing' => env('USE_BATCH_PROCESSING', true),

    /*
    |--------------------------------------------------------------------------
    | Distributed Caching
    | NFR #6 (Redis Cache)
    |--------------------------------------------------------------------------
    | تعطيله يجعل كل request يضرب DB مباشرة
    | إثبات الفرق: X-Query-Count يرتفع بشكل واضح
    */
    'use_caching' => env('USE_CACHING', false),

    /*
    |--------------------------------------------------------------------------
    | Business Logic Protection Flags
    | (كانت موجودة في مشروعك القديم — موحّدة هنا)
    |--------------------------------------------------------------------------
    */

    // يمنع حذف منتج موجود في سلة نشطة أو يمنع تغيير status منتج محذوف
    'use_soft_delete_protection' => env('USE_SOFT_DELETE_PROTECTION', true),

    // يتحقق من توفر المخزون عند إضافة/تحديث عنصر في السلة
    'use_stock_validation' => env('USE_STOCK_VALIDATION', true),


    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Simulation
    |--------------------------------------------------------------------------
    */
    'payment_simulation' => [
        'enabled'        => env('PAYMENT_SIMULATION', true),
        'latency_min_ms' => env('PAYMENT_LATENCY_MIN', 100),
        'latency_max_ms' => env('PAYMENT_LATENCY_MAX', 500),
        'failure_rate'   => env('PAYMENT_FAILURE_RATE', 0.05),
    ],

];
