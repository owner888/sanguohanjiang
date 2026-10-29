/**
 * 悍将三国 (Modern Edition) - 全局集中配置中心
 * 
 * 架构规范：
 * 1. 严格禁止在业务代码中硬编码任何第三方外链 CDN 域名或特定服务端端口；
 * 2. 集中管控 API 基础路径、静态资源根目录、CDN 加速域名及全双工 WebSocket 网关地址；
 * 3. 生产环境可通过部署构建脚本或外部环境变量注入进行平滑切换。
 */
(function(window) {
    'use strict';

    var location = window.location || { protocol: 'http:', hostname: '127.0.0.1', port: '18100' };
    var defaultHost = location.hostname || '127.0.0.1';
    var defaultWsPort = 18101;
    var wsProto = location.protocol === 'https:' ? 'wss:' : 'ws:';

    window.HJ_CONFIG = {
        // 客户端资源版本戳 (用于打破过时文件缓存)
        version: '20261029_1',

        // HTTP API 业务网关基础路径
        apiUrl: 'index.php',

        // 本地静态资源基础根路径
        staticUrl: 'static/',

        // 统一 CDN 加速基础根路径 (可无缝切换至任何独立公网 CDN 或云存储，如 'https://cdn.example.com/static/')
        cdnUrl: 'static/',

        // 全双工 WebSocket 实时网关地址
        wsUrl: wsProto + '//' + defaultHost + ':' + defaultWsPort,

        /**
         * 组装 CDN 资源完整 URL
         * @param {string} relativePath 相对资源路径 (如 'img/pop/eggpop_animate.png')
         * @returns {string}
         */
        cdn: function(relativePath) {
            if (!relativePath) return '';
            if (/^https?:\/\//i.test(relativePath) || relativePath.indexOf('//') === 0) {
                return relativePath;
            }
            var path = relativePath.replace(/^\/+/, '');
            var base = this.cdnUrl.endsWith('/') ? this.cdnUrl : (this.cdnUrl + '/');
            return base + path;
        },

        /**
         * 组装静态资源完整 URL
         * @param {string} relativePath 相对资源路径 (如 'js/autoload.js')
         * @returns {string}
         */
        static: function(relativePath) {
            if (!relativePath) return '';
            if (/^https?:\/\//i.test(relativePath) || relativePath.indexOf('//') === 0) {
                return relativePath;
            }
            var path = relativePath.replace(/^\/+/, '');
            var base = this.staticUrl.endsWith('/') ? this.staticUrl : (this.staticUrl + '/');
            return base + path;
        }
    };

    // 兼容老客户端全局变量
    window.staticUrl = window.HJ_CONFIG.staticUrl;
    window.cdnUrl = window.HJ_CONFIG.cdnUrl;
})(typeof window !== 'undefined' ? window : this);
