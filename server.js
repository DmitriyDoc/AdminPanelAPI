const PORT = 3000;
const http = require('http').Server();
const io = require("socket.io")(http,{
    cors: {
        origin: "*"
    }
});
const Redis = require('ioredis');
const redis = new Redis({ host: 'redis' });

io.on("connection", (socket) => {
    console.log('connection socket id:',socket.id);
});

//redis.subscribe('report-action');
redis.psubscribe('*');
redis.on('pmessage', function (subscribed,channel,message) {
    message = JSON.parse(message);
    io.emit(channel + ':' + message.event, message.data);
    console.log(channel + ':' + message.event, message.data);
});

http.listen(PORT, function (){
    console.log(`Listening on Port:${PORT}`);
});
