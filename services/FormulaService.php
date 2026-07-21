<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/FormulaModule.php';
require_once __DIR__ . '/../lib/FormulaRepository.php';

final class FormulaService
{
    public static function boot(): void
    {
        FormulaModule::repair(Database::connection());
    }

    public static function can(string $action): bool
    {
        if (Auth::isAdmin()) return true;
        return match ($action) {
            'view' => Auth::can('formula_builder.view') || Auth::can('sales_data_manage_formulas'),
            'manage' => Auth::can('formula_builder.manage') || Auth::can('sales_data_manage_formulas'),
            'publish' => Auth::can('formula_builder.publish'),
            'test' => Auth::can('formula_builder.test') || Auth::can('sales_data_run_commission'),
            'rollback' => Auth::can('formula_builder.rollback'),
            default => false,
        };
    }

    public static function require(string $action): void
    {
        Auth::requireLogin();
        if (!self::can($action)) {
            http_response_code(403);
            exit('دسترسی غیرمجاز');
        }
    }

    public static function uiError(Throwable $error, string $fallback): string
    {
        if ($error instanceof InvalidArgumentException || $error instanceof DomainException) {
            return $error->getMessage();
        }
        error_log('Formula builder: ' . $error->getMessage());
        return $fallback;
    }
}
