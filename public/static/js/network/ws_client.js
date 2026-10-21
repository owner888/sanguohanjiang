/**
 * 悍将三国 (Modern Workerman Edition) - 全双工 WebSocket 实时网络客户端
 * 
 * 核心特性 (完全对齐 smart-book 标准):
 * 1. 严格遵循 RFC 6455 协议级底层心跳机制（零冗余应用层心跳）
 * 2. 指数退避智能断线重连 (Exponential Backoff with Random Jitter)
 * 3. 掉线重连后无感 Token 重新鉴权 (Auto Re-Auth)
 * 4. 全服跑马灯大喇叭与实时世界聊天事件分发器
 */

(function (window) {
    'use strict';

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
    }

})(typeof window !== 'undefined' ? window : this);
