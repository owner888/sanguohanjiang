/**
 * 悍将三国 (Modern Workerman Edition) - 全双工 WebSocket 实时网络客户端
 * 
 * 核心特性 (完全对齐 smart-book 标准):
 * 1. 严格遵循 RFC 6455 协议级底层心跳机制（零冗余应用层心跳）
 * 2. 指数退避智能断线重连 (Exponential Backoff with Random Jitter)
 * 3. 掉线重连后无感 Token 重新鉴权 (Auto Re-Auth)
 * 4. 全服跑马灯大喇叭与实时世界聊天事件分发器
 * 5. 内置全服金色流光大喇叭跑马灯横幅渲染引擎
 */

(function (window) {
    'use strict';

    class MarqueeUI {
        constructor() {
            this.container = null;
            this.textElement = null;
            this.queue = [];
            this.isPlaying = false;
        }

        initDOM() {
            if (this.container || typeof document === 'undefined') return;

            const style = document.createElement('style');
            style.innerHTML = `
                #hj_marquee_banner {
                    position: fixed;
                    top: 12px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 80%;
                    max-width: 820px;
                    height: 38px;
                    line-height: 38px;
                    background: linear-gradient(90deg, rgba(20,20,20,0) 0%, rgba(40,15,5,0.88) 15%, rgba(60,25,10,0.92) 50%, rgba(40,15,5,0.88) 85%, rgba(20,20,20,0) 100%);
                    border-top: 1px solid rgba(255,215,0,0.4);
                    border-bottom: 1px solid rgba(255,215,0,0.4);
                    box-shadow: 0 4px 16px rgba(0,0,0,0.6), 0 0 12px rgba(255,215,0,0.25);
                    border-radius: 4px;
                    overflow: hidden;
                    z-index: 999999;
                    pointer-events: none;
                    display: none;
                }
                #hj_marquee_text {
                    display: inline-block;
                    white-space: nowrap;
                    font-size: 16px;
                    font-weight: bold;
                    color: #ffd700;
                    text-shadow: 0 0 6px rgba(255,215,0,0.8), 0 1px 3px rgba(0,0,0,0.9);
                    padding-left: 100%;
                    will-change: transform;
                }
                @keyframes hj_marquee_scroll {
                    0% { transform: translateX(0); }
                    100% { transform: translateX(-100%); }
                }
            `;
            document.head.appendChild(style);

            this.container = document.createElement('div');
            this.container.id = 'hj_marquee_banner';

            this.textElement = document.createElement('div');
            this.textElement.id = 'hj_marquee_text';

            this.container.appendChild(this.textElement);
            document.body.appendChild(this.container);
        }

        enqueue(text) {
            if (!text) return;
            this.initDOM();
            this.queue.push(text);
            if (!this.isPlaying) {
                this.playNext();
            }
        }

        playNext() {
            if (this.queue.length === 0) {
                this.isPlaying = false;
                if (this.container) {
                    this.container.style.display = 'none';
                }
                return;
            }

            this.isPlaying = true;
            const content = this.queue.shift();
            if (this.container && this.textElement) {
                this.container.style.display = 'block';
                this.textElement.textContent = '📢 ' + content;

                // 动态计算滚动速度 (字数越多适当提速)
                const duration = Math.max(7, Math.min(16, content.length * 0.28));
                this.textElement.style.animation = `hj_marquee_scroll ${duration}s linear infinite`;

                setTimeout(() => {
                    this.playNext();
                }, duration * 1000);
            }
        }
    }

    class GameWSClient {
        constructor() {
            this.ws = null;
            this.url = '';
            this.token = '';
            this.uid = 0;
            this.isConnected = false;
            this.isManualClosed = false;

            // 指数退避断线重连配置
            this.reconnectAttempts = 0;
            this.baseDelay = 1000;     // 初始重连延迟 1s
            this.maxDelay = 16000;     // 最大重连延迟 16s
            this.reconnectTimer = null;

            // 事件订阅字典
            this.listeners = {};

            // 跑马灯渲染器
            this.marqueeUI = new MarqueeUI();

            this.initDefaultUrl();
        }

        initDefaultUrl() {
            if (typeof window !== 'undefined' && window.location) {
                const proto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const host = window.location.hostname || '127.0.0.1';
                this.url = `${proto}//${host}:18101`;
            } else {
                this.url = 'ws://127.0.0.1:18101';
            }
        }

        /**
         * 启动连接
         */
        connect(token = '', uid = 0) {
            if (token) this.token = token;
            if (uid) this.uid = uid;
            this.isManualClosed = false;

            if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
                return;
            }

            try {
                this.ws = new WebSocket(this.url);
                this.setupHandlers();
            } catch (err) {
                console.error('[GameWS] 连接创建失败，准备重连:', err);
                this.scheduleReconnect();
            }
        }

        setupHandlers() {
            this.ws.onopen = () => {
                this.isConnected = true;
                this.reconnectAttempts = 0;
                console.log('[GameWS] 连接成功:', this.url);

                // 登录鉴权
                if (this.token || this.uid) {
                    this.send({
                        action: 'auth',
                        token: this.token,
                        uid: this.uid
                    });
                }

                this.emit('open');
            };

            this.ws.onmessage = (event) => {
                try {
                    const msg = JSON.parse(event.data);

                    // 全服跑马灯大喇叭事件自动触发 UI 滚动
                    if (msg.type === 'marquee' && msg.data) {
                        const content = msg.data.content || msg.data.message || '';
                        this.marqueeUI.enqueue(content);
                    }

                    // 事件分发
                    if (msg.type) {
                        this.emit(msg.type, msg.data || msg);
                    }

                    this.emit('message', msg);
                } catch (e) {
                    // 非 JSON 报文
                    this.emit('raw_message', event.data);
                }
            };

            this.ws.onerror = (err) => {
                console.warn('[GameWS] 异常错误:', err);
                this.emit('error', err);
            };

            this.ws.onclose = (event) => {
                this.isConnected = false;
                console.warn(`[GameWS] 连接断开 (code: ${event.code})`);
                this.emit('close', event);

                if (!this.isManualClosed) {
                    this.scheduleReconnect();
                }
            };
        }

        /**
         * 指数退避断线重连 (含随机扰动 Jitter)
         */
        scheduleReconnect() {
            if (this.isManualClosed) return;
            if (this.reconnectTimer) return;

            this.reconnectAttempts++;
            // delay = min(maxDelay, baseDelay * 2^(attempts-1)) + jitter
            const exponential = this.baseDelay * Math.pow(2, Math.min(this.reconnectAttempts - 1, 4));
            const jitter = Math.floor(Math.random() * 500);
            const delay = Math.min(this.maxDelay, exponential) + jitter;

            console.log(`[GameWS] 正在尝试第 ${this.reconnectAttempts} 次断线重连，等待 ${delay}ms...`);
            this.emit('reconnecting', { attempt: this.reconnectAttempts, delay });

            this.reconnectTimer = setTimeout(() => {
                this.reconnectTimer = null;
                this.connect();
            }, delay);
        }

        /**
         * 发送消息
         */
        send(payload) {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                const str = typeof payload === 'string' ? payload : JSON.stringify(payload);
                this.ws.send(str);
                return true;
            }
            return false;
        }

        /**
         * 发送实时聊天
         */
        sendChat(message, name = '') {
            return this.send({
                action: 'chat',
                message: message,
                name: name,
                uid: this.uid
            });
        }

        /**
         * 关闭连接
         */
        close() {
            this.isManualClosed = true;
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
            if (this.ws) {
                this.ws.close();
            }
        }

        /**
         * 事件监听
         */
        on(event, handler) {
            if (!this.listeners[event]) {
                this.listeners[event] = [];
            }
            this.listeners[event].push(handler);
            return () => this.off(event, handler);
        }

        off(event, handler) {
            if (!this.listeners[event]) return;
            this.listeners[event] = this.listeners[event].filter(h => h !== handler);
        }

        emit(event, data) {
            if (this.listeners[event]) {
                this.listeners[event].forEach(fn => {
                    try {
                        fn(data);
                    } catch (e) {
                        console.error(`[GameWS] 事件 [${event}] 回调异常:`, e);
                    }
                });
            }
        }
    }

    // 单例挂载
    window.GameWS = new GameWSClient();

    // 默认在页面加载完成后自动尝试建立连接
    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            const token = localStorage.getItem('token') || '';
            const uid = parseInt(localStorage.getItem('uid') || '0', 10);
            window.GameWS.connect(token, uid);
        });

        // 核心混合架构 (对齐 smart-book): 发送走 HTTP 网关，接收走 WebSocket 毫秒级下推
        window.GameWS.on('chat_message', function (data) {
            if (!data || !data.message) return;

            var msgItem = {
                id: data.id || Date.now(),
                uid: data.uid || 0,
                nickname: data.nickname || data.name || '系统',
                name: data.nickname || data.name || '系统',
                type: typeof data.type !== 'undefined' ? data.type : 0,
                system: data.system || 0,
                message: data.message,
                time: data.time || Math.floor(Date.now() / 1000)
            };

            // 如果是当前玩家自己发送的，发送时的 HTTP 回调已经立即渲染并追加过了，避免重复
            var myUid = (typeof player !== 'undefined' && player.info) ? player.info.uid : 0;
            if (myUid > 0 && msgItem.uid === myUid) {
                return;
            }

            // 1. 如果大聊天弹窗当前正打开着，实时追加到弹窗 DOM 中
            if (typeof chatpop !== 'undefined' && chatpop.isshow && chatpop.centerDom) {
                chatpop.centerDom.append(chatpop.buildLi([msgItem]));
                chatpop.deleteTenLine();
                chatpop.setWidth();
                chatpop.chatRefresh(true);
            }

            // 2. 无论弹窗是否打开，都实时推送到主界面左下角小聊天窗口
            if (typeof CD !== 'undefined' && CD['chat']) {
                if (CD['chat'].allChatData) {
                    CD['chat'].allChatData.unshift(msgItem);
                }
                if (typeof CD['chat'].createMain === 'function') {
                    CD['chat'].createMain([msgItem]);
                }
                if (typeof CD['chat'].createCountryMain === 'function') {
                    CD['chat'].createCountryMain([msgItem]);
                }
            }
        });
    }

})(typeof window !== 'undefined' ? window : this);
