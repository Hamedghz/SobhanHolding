<?php
require_once __DIR__ . '/SalesAggregateImportService.php';
require_once __DIR__ . '/InventoryImportService.php';

class SalesReferenceImportService
{
    public static function readUploadedFile(string $sourceModule, array $file, string $mode, int $actorId, ?string $periodKey = null): array
    {
        if ($sourceModule === 'sales_aggregate') {
            return SalesAggregateImportService::readUploadedFile($file, $mode, $actorId, $periodKey);
        }
        if ($sourceModule === 'inventory_aggregate') {
            return InventoryImportService::readUploadedFile($file, $mode, $actorId, $periodKey);
        }
        throw new InvalidArgumentException('منبع اطلاعات مرجع معتبر نیست.');
    }
}
