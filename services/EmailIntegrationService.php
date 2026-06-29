<?php

require_once __DIR__ . '/../core/EmailHubModule.php';

class EmailIntegrationService
{
    public static function processPending(int $limit=20): array
    {
        $rows=Database::fetchAll("SELECT i.*,m.subject,m.from_email,m.body_text,m.body_html,m.account_id FROM email_integrations i JOIN email_messages m ON m.id=i.message_id WHERE i.status='pending' ORDER BY i.id LIMIT ".max(1,min(100,$limit)));$completed=0;$waiting=0;$failed=0;
        foreach($rows as $row){$handler=$row['integration_type']==='ticket'?'sobhan_email_create_ticket':'sobhan_email_add_to_cartable';if(!is_callable($handler)){$waiting++;continue;}try{$targetId=call_user_func($handler,$row);Database::execute("UPDATE email_integrations SET status='completed',target_id=?,last_error=NULL,updated_at=NOW() WHERE id=?",[(int)$targetId?:null,(int)$row['id']]);Database::execute('UPDATE email_messages SET related_ticket_id=COALESCE(?,related_ticket_id),updated_at=NOW() WHERE id=?',[$row['integration_type']==='ticket'?((int)$targetId?:null):null,(int)$row['message_id']]);EmailHubModule::log((int)$row['account_id'],(int)$row['message_id'],'integration_completed','اتصال ایمیل به سامانه داخلی انجام شد.');$completed++;}catch(Throwable $e){Database::execute("UPDATE email_integrations SET status='failed',last_error=?,updated_at=NOW() WHERE id=?",[mb_substr($e->getMessage(),0,1000),(int)$row['id']]);EmailHubModule::log((int)$row['account_id'],(int)$row['message_id'],'integration_failed','اتصال ایمیل به سامانه داخلی ناموفق بود.',$e->getMessage());$failed++;}}
        return ['completed'=>$completed,'waiting_for_hook'=>$waiting,'failed'=>$failed];
    }
}
