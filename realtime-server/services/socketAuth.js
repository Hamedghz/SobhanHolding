export const socketAuth=verify=>(socket,next)=>{try{socket.userId=verify(socket.handshake.auth?.token);next()}catch{next(new Error('unauthorized'))}};
