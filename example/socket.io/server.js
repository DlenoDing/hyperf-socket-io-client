var app = require('http').createServer(handler),
    io = require('socket.io').listen(app),
    fs = require('fs');

app.listen(18888);
io.set('log level', 1);//将socket.io中的debug信息关闭

function handler (req, res) {
    fs.readFile(__dirname + '/index.html',function (err, data) {
        if (err) {
            res.writeHead(500);
            return res.end('Error loading index.html');
        }
        res.writeHead(200, {'Content-Type': 'text/html'});
        res.end(data);
    });
}

io.of('/spread').on('connection', function (socket) {
    console.log('connection');

    socket.on('event', function (data) {
        console.log(data);
        return 'Event Received: ' + data;
    });

    socket.on('say', function (data) {
        console.log(socket.id +':'+ data);
        io.of('/spread').emit('event', socket.id +':'+ data);
    });

    socket.on('join-room', function (data) {
        socket.join(data);
        io.of('/spread').emit('event',  socket.id +':'+'join-room: '+data);
    });

    socket.on('disconnect', function (data) {
        console.log(data);
    });

});
