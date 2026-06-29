export async function setPresence(call,userId,status,socketId){return call('/api/messenger/internal/presence.php',{user_id:userId,status,socket_id:socketId})}
