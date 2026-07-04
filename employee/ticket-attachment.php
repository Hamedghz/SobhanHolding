<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../services/TicketService.php';
Auth::requireLogin();
ini_set('display_errors','0');
try {
    $file=TicketService::attachment((int)($_GET['id']??0));
    header('Content-Type: '.$file['mime']);
    header('Content-Length: '.$file['size']);
    header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['name']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($file['path']);
} catch (Throwable $e) {
    error_log('Ticket attachment: '.$e->getMessage());
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'فایل پیوست در دسترس نیست.';
}
