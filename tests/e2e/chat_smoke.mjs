// 聊天室实时推送端到端冒烟：三个游客经 HTTP 注册登录拿 token，A、B 以 GameWS（ws_client.js）相同的帧
// {action: 'auth', token} 鉴权；S 经 HTTP c=chat&m=send 发世界消息，断言 A、B 都收到 chat_message 推送
// （HTTP 进程写 Redis → ws 进程取通知推送），且推送过的消息不会再被 c=chat&m=read 取到。
// 先启动服务（php server.php start）；需要本机 mysql 客户端（root/root）把发言人升到 50 级。
import { execFileSync } from 'node:child_process';
import assert from 'node:assert';

const HTTP = process.env.HJ_HTTP || 'http://127.0.0.1:18100/index.php';
const WS = process.env.HJ_WS || 'ws://127.0.0.1:18101';

async function api(params) {
    const res = await fetch(HTTP + '?' + new URLSearchParams(params));
    return res.json();
}

async function guest() {
    const reg = await api({ c: 'register', m: 'guest' });
    assert.strictEqual(reg.status, 1, JSON.stringify(reg));
    const login = await api({ c: 'login', m: 'guest', uid: reg.uid, guestkey: reg.guestkey });
    assert.ok(login.token, JSON.stringify(login));
    return { uid: Number(reg.uid), token: login.token };
}

class Client {
    constructor(name) {
        this.name = name;
        this.frames = [];
        this.waiters = [];
    }
    open() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(WS);
            this.ws.onopen = () => resolve();
            this.ws.onerror = (e) => reject(e);
            this.ws.onmessage = (ev) => {
                const f = JSON.parse(ev.data);
                this.frames.push(f);
                this.waiters = this.waiters.filter((w) => !(w.type === f.type && (w.resolve(f.data), true)));
            };
        });
    }
    send(payload) {
        this.ws.send(JSON.stringify(payload));
    }
    wait(type, ms = 5000) {
        const i = this.frames.findIndex((f) => f.type === type);
        if (i >= 0) {
            return Promise.resolve(this.frames.splice(i, 1)[0].data);
        }
        return new Promise((resolve, reject) => {
            const t = setTimeout(() => reject(new Error(`${this.name}: 等待 ${type} 超时，收到 ${this.frames.map((f) => f.type)}`)), ms);
            this.waiters.push({ type, resolve: (d) => { clearTimeout(t); this.frames = this.frames.filter((f) => f.type !== type); resolve(d); } });
        });
    }
}

const [ps, pa, pb] = [await guest(), await guest(), await guest()];
execFileSync('mysql', ['-uroot', '-proot', 'zc_sanguo', '-e', `UPDATE zc_sanguo_member SET level = 50 WHERE uid = ${ps.uid};`],
    { stdio: ['ignore', 'ignore', 'ignore'] });

const [a, b] = [new Client('A'), new Client('B')];
for (const [c, p] of [[a, pa], [b, pb]]) {
    await c.open();
    c.send({ action: 'auth', token: p.token, uid: p.uid });
    assert.strictEqual((await c.wait('auth_success')).uid, p.uid);
}

const text = '冒烟' + Date.now();
const sent = await api({ c: 'chat', m: 'send', token: ps.token, message: text });
assert.strictEqual(sent.status, 1, JSON.stringify(sent));

for (const c of [a, b]) {
    const push = await c.wait('chat_message');
    assert.strictEqual(push.status, 1);
    const msg = push.list.find((m) => m.message === text);
    assert.ok(msg, `${c.name}: ${JSON.stringify(push)}`);
    assert.deepStrictEqual([msg.type, msg.uid, msg.system], [0, ps.uid, 0]);
}
// 已推送：read 取不到
const read = await api({ c: 'chat', m: 'read', token: pa.token });
assert.deepStrictEqual([read.status, read.list], [1, []]);

a.ws.close();
b.ws.close();
console.log(`chat smoke: OK (${text} → A ${pa.uid}, B ${pb.uid})`);
