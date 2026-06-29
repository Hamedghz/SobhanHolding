<?php
require_once __DIR__.'/MessengerSecurity.php';
final class MessengerInboxService
{
    public static function items(int $userId,string $tab,int $limit=300): array
    { $tab=in_array($tab,['unread','mentions','official','files','pinned'],true)?$tab:'unread';$limit=max(1,min(500,$limit));$sql='SELECT m.id,m.conversation_id,m.body,m.message_type,m.created_at,c.title conversation_title,s.name sender_name FROM chat_messages m JOIN chat_conversations c ON c.id=m.conversation_id JOIN chat_participants p ON p.conversation_id=c.id AND p.user_id=? AND p.deleted_at IS NULL LEFT JOIN users s ON s.id=m.sender_id WHERE m.deleted_at IS NULL';$sql.=match($tab){'mentions'=>' AND EXISTS(SELECT 1 FROM chat_mentions x WHERE x.message_id=m.id AND x.user_id=p.user_id)','official'=>' AND m.message_type IN("official_notice","notice")','files'=>' AND EXISTS(SELECT 1 FROM chat_files f WHERE f.message_id=m.id AND f.deleted_at IS NULL)','pinned'=>' AND m.is_pinned=1',default=>' AND m.id>COALESCE(p.last_read_message_id,0)'};return Database::fetchAll($sql." ORDER BY m.id DESC LIMIT {$limit}",[$userId]); }
}
