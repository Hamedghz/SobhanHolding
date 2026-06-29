export const room=id=>`conversation:${Number(id)}`;export const publish=(io,id,event,payload)=>io.to(room(id)).emit(event,payload);
