<?php

return [
    'pdf' => [
        'max_detail_records' => (int) env('SALES_FEE_PDF_MAX_DETAIL_RECORDS', 1000),
        'estimated_bytes_per_record' => (int) env('SALES_FEE_PDF_ESTIMATED_BYTES_PER_RECORD', 286720),
        'memory_reserve_bytes' => (int) env('SALES_FEE_PDF_MEMORY_RESERVE_BYTES', 50331648),
    ],
];
