// 多人副本端到端冒烟：两个游客经 HTTP 注册登录拿 token，经 WebSocket（与 socket.io.min.js 适配层相同的帧）
// 登录实时组队 → 创建 / 加入队伍 → 队长开战，断言两人都收到 enterfight。
// 先用 tests/e2e/elite_smoke_server.php 启动服务；需要本机 mysql 客户端（root/root）给两人写主线进度。
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
    // 主线打通第 50 关（军团 1 开启）：Mission 50 = level 2 / stage 1 / mission 10
    execFileSync('mysql', ['-uroot', '-proot', 'zc_sanguo', '-e',
        `DELETE FROM zc_sanguo_open_map WHERE uid=${Number(reg.uid)}; INSERT INTO zc_sanguo_open_map (uid, level, stage, mission) VALUES (${Number(reg.uid)}, 2, 1, 10);`],
        { stdio: ['ignore', 'ignore', 'ignore'] });
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
    emit(action, data) {
        this.ws.send(JSON.stringify({ action, data: data || {}, time: Date.now() }));
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

const [pa, pb] = [await guest(), await guest()];
const [a, b] = [new Client('A'), new Client('B')];
await a.open();
await b.open();

for (const [c, p] of [[a, pa], [b, pb]]) {
    c.emit('login', { uid: p.uid, token: p.token, serverid: 1 });
    assert.strictEqual(await c.wait('loginstatus'), 1);
    c.emit('eliteindex', '');
    const index = await c.wait('eliteindex');
    assert.strictEqual(index.list[0].open, 1);
    c.emit('teamlist', { id: 1 });
    assert.strictEqual((await c.wait('teamlist')).id, 1);
}

a.emit('createTeam', { id: 1, type: 1, topteam: false });
const { teamid } = await a.wait('createteamdone');
await b.wait('teamlist');
b.emit('jointeam', { id: 1, teamid });
await b.wait('jointeamdone');
const detail = await a.wait('getteamdone');
assert.ok(detail.list.length >= 1);
assert.strictEqual((await a.wait('getteamdone')).num, 2);

a.emit('startfight', 1);
await a.wait('readyfight');
await b.wait('readyfight');
const [ea, eb] = [await a.wait('enterfight', 15000), await b.wait('enterfight', 15000)];
assert.strictEqual(ea.status, 1, JSON.stringify(ea).slice(0, 300));
assert.deepStrictEqual(ea, eb);
assert.strictEqual(ea.info.teamid, teamid);
assert.ok(ea.info[pa.uid] && ea.info[pb.uid]);
assert.ok(ea.battle && ea.battle.battle_log.length > 0);

// 心跳：应用层 ping 回 pong
a.emit('ping', {});
await a.wait('pong');

a.ws.close();
b.ws.close();
console.log(`elite smoke: OK (team ${teamid}, win=${ea.info.win}, A number=${ea.info[pa.uid].number}, B number=${ea.info[pb.uid].number})`);
