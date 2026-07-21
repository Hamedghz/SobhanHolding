<?php

$schema = (string)file_get_contents(dirname(__DIR__) . '/database/schema.sql');

foreach ([
    'period_key VARCHAR(50) NULL',
    'visitor_name VARCHAR(255) NULL',
    'supervisor_code VARCHAR(100) NULL',
    'supervisor_name VARCHAR(255) NULL',
    'sales_manager_code VARCHAR(100) NULL',
    'sales_manager_name VARCHAR(255) NULL',
    'line_name VARCHAR(100) NULL',
    'customer_guild_code VARCHAR(100) NULL',
    'brand_code VARCHAR(100) NULL',
    'discount_total DECIMAL(18,4) NULL',
    'period_total_stock DECIMAL(20,4) NULL',
    'period_key VARCHAR(50) NULL, snapshot_date DATE NULL',
] as $required) {
    if (!str_contains($schema, $required)) {
        throw new RuntimeException('Fresh schema reporting dependency missing: ' . $required);
    }
}

echo "Fresh schema reporting dependencies contract: PASS\n";
