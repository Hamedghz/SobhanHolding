<?php
require __DIR__.'/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

messenger_run(function ($u) use ($method) {
    $in = messenger_input();
    if ($method === 'GET') {
        return MessengerService::conversations((int)$u['id'], (string)($_GET['q'] ?? ''), (int)($_GET['limit'] ?? 100));
    }

    $type = (string)($in['type'] ?? 'private');
    if ($type !== 'private') {
        return MessengerService::createConversation($in, $u);
    }

    MessengerSecurity::requirePermission('messenger.private.send', 'create');
    $actorId = (int)$u['id'];
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['participant_ids'] ?? [])))));
    $ids = array_values(array_filter($ids, static fn($id) => $id > 0 && $id !== $actorId));
    if (count($ids) !== 1) {
        throw new InvalidArgumentException('برای گفتگوی خصوصی دقیقاً یک همکار را انتخاب کنید.');
    }
    $recipientId = (int)$ids[0];
    $recipient = Database::fetch('SELECT id,name FROM users WHERE id=? AND status="active"', [$recipientId]);
    if (!$recipient) {
        throw new InvalidArgumentException('کاربر انتخاب‌شده فعال یا معتبر نیست.');
    }

    $existing = Database::fetch(
        'SELECT c.* FROM chat_conversations c
         JOIN chat_participants a ON a.conversation_id=c.id AND a.user_id=? AND a.deleted_at IS NULL
         JOIN chat_participants b ON b.conversation_id=c.id AND b.user_id=? AND b.deleted_at IS NULL
         WHERE c.type="private" AND c.deleted_at IS NULL
           AND (SELECT COUNT(*) FROM chat_participants x WHERE x.conversation_id=c.id AND x.deleted_at IS NULL)=2
         LIMIT 1',
        [$actorId, $recipientId]
    );
    if ($existing) return $existing;

    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        $uuid = MessengerSecurity::uuid();
        $title = MessengerSecurity::text($in['title'] ?? '', 190) ?: 'گفتگوی خصوصی';
        Database::execute(
            'INSERT INTO chat_conversations(uuid,conversation_uuid,title,type,scope_type,organization_scope,created_by,owner_id,metadata_json,created_at,updated_at)
             VALUES(?,?,?,"private","custom","custom",?,?,?,NOW(),NOW())',
            [$uuid, $uuid, $title, $actorId, $actorId, json_encode(['created_from'=>'web_private'], JSON_UNESCAPED_UNICODE)]
        );
        $conversationId = (int)Database::lastInsertId();
        Database::execute('INSERT INTO chat_participants(conversation_id,user_id,participant_role,can_send,joined_at) VALUES(?,?,"owner",1,NOW())', [$conversationId, $actorId]);
        Database::execute('INSERT INTO chat_participants(conversation_id,user_id,participant_role,can_send,joined_at) VALUES(?,?,"member",1,NOW())', [$conversationId, $recipientId]);
        MessengerService::audit($actorId, 'conversation_created', $conversationId, null, ['type'=>'private','members'=>2]);
        $pdo->commit();
        return Database::fetch('SELECT * FROM chat_conversations WHERE id=?', [$conversationId]) ?: [];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}, $method !== 'GET');
