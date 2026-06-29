<?php
require_once __DIR__.'/DocsAccessService.php';
class DocsRepository
{
    public static function categories(bool $activeOnly=false): array{return Database::fetchAll('SELECT c.*,p.title parent_title,(SELECT COUNT(*) FROM docs_articles a WHERE a.category_id=c.id) article_count FROM docs_categories c LEFT JOIN docs_categories p ON p.id=c.parent_id'.($activeOnly?' WHERE c.active=1':'').' ORDER BY c.sort_order,c.title');}
    public static function visible(array $filters=[]): array{$rows=Database::fetchAll('SELECT a.*,c.title category_title FROM docs_articles a LEFT JOIN docs_categories c ON c.id=a.category_id WHERE a.active=1 ORDER BY a.id DESC');$rows=array_values(array_filter($rows,static fn($doc)=>DocsAccessService::canView($doc)));$q=mb_strtolower(trim((string)($filters['q']??'')));$category=(int)($filters['category_id']??0);return array_values(array_filter($rows,static function($doc)use($q,$category){if($category&&(int)$doc['category_id']!==$category)return false;if($q!==''&&!str_contains(mb_strtolower($doc['title'].' '.$doc['summary'].' '.strip_tags($doc['content'])),$q))return false;return true;}));}
}
