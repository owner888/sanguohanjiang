// socket.io.min.js 适配层自检：node tests/js/socket_io_adapter.test.js
// 用假 WebSocket / 假定时器在 vm 里加载适配层，验证连接地址、上行帧格式、心跳与下行分发。
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/static/js/core/socket.io.min.js'), 'utf8');

function load(hjConfig) {
    const sockets = [];
    const timers = [];
    class FakeWebSocket {
        constructor(url) {
            this.url = url;
            this.readyState = 0;
            this.sent = [];
            this.closed = false;
            sockets.push(this);
        }
        send(data) { this.sent.push(JSON.parse(data)); }
        close() { this.closed = true; }
    }
    FakeWebSocket.OPEN = 1;
    const window = { HJ_CONFIG: hjConfig };
    const sandbox = {
        window,
        WebSocket: FakeWebSocket,
        console,
        JSON,
        Date,
        setTimeout: (fn, ms) => { timers.push({ fn, ms, interval: false, active: true }); return timers.length; },
        setInterval: (fn, ms) => { timers.push({ fn, ms, interval: true, active: true }); return timers.length; },
        clearInterval: (id) => { if (timers[id - 1]) timers[id - 1].active = false; },
        clearTimeout: (id) => { if (timers[id - 1]) timers[id - 1].active = false; },
    };
    vm.runInNewContext(source, sandbox);
    const open = (ws) => { ws.readyState = 1; ws.onopen(); };
    const tick = () => timers.filter((t) => t.active && t.interval).forEach((t) => t.fn());
    return { io: window.io, sockets, timers, open, tick };
}

// 1. 地址：传了 get_server 的地址就连它（http → ws），没传用 HJ_CONFIG.wsUrl
{
    const env = load({ wsUrl: 'ws://game.local:18101' });
    env.io.connect('http://elite.local:18101', {});
    env.io.connect('', {});
    env.io.connect('https://elite.local', {});
    assert.deepStrictEqual(env.sockets.map((s) => s.url), ['ws://elite.local:18101', 'ws://game.local:18101', 'wss://elite.local']);
}

// 2. 上行帧 {action, data, time}，data 原样（含标量）；连接前 emit 丢弃
{
    const env = load({ wsUrl: 'ws://game.local:18101' });
    const socket = env.io.connect('', {});
    const ws = env.sockets[0];
    socket.emit('login', { uid: 1 });
    assert.strictEqual(ws.sent.length, 0);
    let connected = 0;
    socket.on('connect', () => connected++);
    env.open(ws);
    assert.strictEqual(connected, 1);
    socket.emit('login', { uid: 7, token: 'tk', serverid: 1 });
    socket.emit('startfight', 1);
    socket.emit('eliteindex', '');
    assert.deepStrictEqual(ws.sent.map((f) => [f.action, f.data]), [
        ['login', { uid: 7, token: 'tk', serverid: 1 }],
        ['startfight', 1],
        ['eliteindex', {}],
    ]);
    assert.strictEqual(typeof ws.sent[0].time, 'number');

    // 3. 心跳：每 20 秒 {action: 'ping'}；断开后停止
    const beat = env.timers.filter((t) => t.interval && t.active);
    assert.strictEqual(beat.length, 1);
    assert.strictEqual(beat[0].ms, 20000);
    env.tick();
    assert.deepStrictEqual(ws.sent.slice(-1).map((f) => [f.action, f.data]), [['ping', {}]]);

    // 4. 下行：{type, data} 的 data 原样交给回调（标量也是）；pong 不分发
    const got = [];
    socket.on('loginstatus', (msg) => got.push(['loginstatus', msg]));
    socket.on('getteamdone', (msg) => got.push(['getteamdone', msg]));
    socket.on('pong', (msg) => got.push(['pong', msg]));
    let messages = 0;
    socket.on('message', () => messages++);
    ws.onmessage({ data: JSON.stringify({ type: 'loginstatus', data: 1, timestamp: 0 }) });
    ws.onmessage({ data: JSON.stringify({ type: 'loginstatus', data: 0, timestamp: 0 }) });
    ws.onmessage({ data: JSON.stringify({ type: 'getteamdone', data: { teamid: 3, list: [] }, timestamp: 0 }) });
    ws.onmessage({ data: JSON.stringify({ type: 'pong', data: { time: 1 }, timestamp: 0 }) });
    assert.deepStrictEqual(got, [['loginstatus', 1], ['loginstatus', 0], ['getteamdone', { teamid: 3, list: [] }]]);
    assert.strictEqual(messages, 3);

    socket.disconnect();
    assert.strictEqual(ws.closed, true);
    assert.strictEqual(env.timers.filter((t) => t.interval && t.active).length, 0);
}

// 5. 服务端断开：触发 disconnect，心跳停止
{
    const env = load({ wsUrl: 'ws://game.local:18101' });
    const socket = env.io.connect('', {});
    const ws = env.sockets[0];
    let disconnected = 0;
    socket.on('disconnect', () => disconnected++);
    env.open(ws);
    ws.onclose({ code: 1006 });
    assert.strictEqual(disconnected, 1);
    assert.strictEqual(env.timers.filter((t) => t.interval && t.active).length, 0);
}

console.log('socket.io adapter: OK');
