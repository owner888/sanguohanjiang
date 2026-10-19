// 是否调试运行
// false：JS 不带随机 ?t= 时间戳，浏览器可走协商缓存（304），刷新不再全量重下 JS。
// 服务端文本资源为 no-cache + 真实 Last-Modified：改了 JS/CSS 用户刷新即可生效，
// 无需再靠随机戳破缓。真需强制破缓时临时改回 true 即可。
var isdebug = false;
// 是否访问测试服
var islocal = true;
// 是否Phonegap
var isPhonegap = typeof cordova != 'undefined';
/* 移动设备判定（index.html 内联脚本在其后执行，这里自行计算，避免 undefined） */
window.HJ_IS_MOBILE = window.HJ_IS_MOBILE || /Android|iPhone|iPad|iPod|IEMobile|Opera Mini|Mobile/i.test(navigator.userAgent)
    || (navigator.maxTouchPoints > 1 && /Macintosh/.test(navigator.userAgent)); /* iPadOS 桌面 UA */
/* 本地静态文件地址
 * 如果是PC/H5版本，资源已全部内置在 static/ 目录，无需外链
 */
var staticUrl = 'https://kzh-1316691196.cos.ap-shanghai.myqcloud.com/static/'; /* 服务端注入：配置 cos_base_url 时为 COS 直连地址，否则 ./static/ */
/* 地图资源版本号：由 cos_upload.py --texts 按 img/map 内容指纹自动注入
   （占位符 __HJ_MAP_VER__）。素材走 30 天长缓存，同名覆盖玩家看不到新图，
   加版本号后只要地图文件有改动，URL 就变、浏览器自动重下，无需玩家清缓存。
   CSS 里的 ../img/map/ 由上传脚本统一重写加同一版本号，两边保持一致。 */
var HJ_MAP_VER = '__HJ_MAP_VER__';
function mapAssetUrl(url) {
    if (!url) { return url; }
    return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'v=' + HJ_MAP_VER;
}
/* 资源版本号：取 autoload.js 自身 URL 上的 ?v=（服务端注入的启动时间戳，每次部署重启变化）。
   用途：给动态插入的 js/css/tmpl 加版本，绕开浏览器损坏的旧缓存条目——玩家报
   base.js ERR_CACHE_READ_FAILURE、卡在"正在加载模块 1/29"就是这个（2026-10-19）。
   不部署时版本不变、缓存照常命中；部署后 URL 自然更新，也避免新旧代码混用。 */
var HJ_ASSET_VER = (function () {
    try {
        var s = (document.currentScript && document.currentScript.src) || '';
        var m = s.match(/[?&]v=([^&#]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    } catch (e) { return ''; }
})();
function assetVer(url) {
    if (!HJ_ASSET_VER) { return url; }
    return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'v=' + HJ_ASSET_VER;
}
/* 版本看门狗（2026-10-19 平乱报错归因）：玩家标签页一开好几天不关，内存里还是
   老版本代码、资源 URL 还是老 v=；期间服务端部署重启后 v= 已变，旧页继续跑
   会请求已不存在的老模块（404）+ 命中 30 天长缓存的旧素材/损坏缓存条目
   （ERR_CACHE_READ_FAILURE）。每 20 分钟拉一次首页比对当前 v=，变了就在
   页面顶部挂一条"发现新版本"提示条，点击即刷新（不自动刷，避免战斗中丢状态）。 */
(function () {
    if (!HJ_ASSET_VER) { return; }
    var _chkTimer = setInterval(function () {
        try {
            if (typeof $ == 'undefined' || typeof xGame == 'undefined') { return; }
            clearInterval(_chkTimer); /* 登录进游戏后再开始周期检查，换用新的 interval */
            setInterval(function () {
                try {
                    if (typeof xGame != 'undefined' && xGame.nowWorld == 'battle') { return; } /* 战斗中不打扰 */
                    $.ajax({
                        'type': 'get',
                        'dataType': 'text',
                        'url': location.pathname + (location.search ? location.search.replace(/[?&]_vchk=\d+/g, '') + '&' : '?') + '_vchk=' + (+new Date()),
                        'success': function (t) {
                            var m = String(t).match(/autoload\.js\?v=(\d+)/);
                            if (m && m[1] && m[1] != HJ_ASSET_VER && !document.getElementById('hj_verbanner')) {
                                var bar = document.createElement('div');
                                bar.id = 'hj_verbanner';
                                bar.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:99999;'
                                    + 'background:rgba(0,0,0,.85);color:#ffd54a;padding:8px 12px;text-align:center;'
                                    + 'font-size:14px;cursor:pointer;';
                                bar.innerHTML = '发现新版本，请点击此处刷新页面（旧页面资源已过期，可能出现报错/图裂）';
                                bar.addEventListener('touchend', function (ev) { ev.stopPropagation(); location.reload(); }, false);
                                bar.addEventListener('click', function () { location.reload(); }, false);
                                document.body.appendChild(bar);
                            }
                        }
                    });
                } catch (e) {}
            }, 20 * 60 * 1000);
        } catch (e) {}
    }, 60 * 1000);
})();
/* ===================== 服寻址 =====================
 * 两种模式，自动判定，无需改代码：
 *
 *  A) 参数模式（默认，也是生产部署方式）——单域名单端口多服：
 *     所有 API 都打当前访问的源（https://nx.kzhgzs.cn/index.php），额外带 server=N，
 *     由反向代理按该参数把请求分到内部 18100/18101... 各服进程。
 *     玩家从头到尾只看到一个域名、一个端口，感知不到内部端口。
 *
 *  B) 端口模式——仅本地/内网直连 1810x 端口时生效（便于本地多服调试）：
 *     按 SERVER_BASE_PORT + 服号 - 1 拼端口，直连各服进程。
 *
 * SERVER_BASE_PORT 由服务端注入（config.yaml 的 port），前端不写死端口。
 */
var SERVER_BASE_PORT = parseInt("3225", 10) || 18100;
/* 端口模式判定：当前访问端口落在 [BASE, BASE+100) 区间即为直连多服 */
var HJ_PORT_MODE = (function () {
    var p = parseInt(location.port, 10) || 0;
    return p >= SERVER_BASE_PORT && p < SERVER_BASE_PORT + 100;
})();
/* 当前服号：URL ?server=N > 本地上次选的服 > 端口推断 > 1 */
var HJ_SERVER = (function () {
    var m = location.search.match(/[?&]server=(\d+)/);
    if (m) {
        var q = parseInt(m[1], 10);
        if (q > 0) { return q; }
    }
    try {
        var s = parseInt(localStorage.getItem('hj_server'), 10);
        if (s > 0) { return s; }
    } catch (e) {}
    if (HJ_PORT_MODE) {
        return (parseInt(location.port, 10) || SERVER_BASE_PORT) - SERVER_BASE_PORT + 1;
    }
    return 1;
})();
/* 生成指定服的 API 前缀（以 & 结尾，可直接拼 c=/m= ） */
function hjApiFor(id) {
    id = parseInt(id, 10) || 1;
    var base = location.protocol + '//' + location.hostname;
    if (HJ_PORT_MODE) {
        return base + ':' + (SERVER_BASE_PORT + id - 1) + '/index.php?';
    }
    return base + (location.port ? ':' + location.port : '') + '/index.php?server=' + id + '&';
}
/* 切换当前服：更新全局服号、UCAPI 前缀与 xGame.API，并记住选择 */
function hjSetServer(id) {
    HJ_SERVER = parseInt(id, 10) || 1;
    try { localStorage.setItem('hj_server', HJ_SERVER); } catch (e) {}
    UCAPI = hjApiFor(HJ_SERVER);
    if (typeof xGame != 'undefined' && xGame && xGame.setServer) {
        xGame.setServer(HJ_SERVER);
    }
    return HJ_SERVER;
}
/* UCAPI：API 全部走当前访问的源（同域名+端口），本地/线上/反代部署通用 */
var UCAPI = hjApiFor(HJ_SERVER);
// 客户端ID ****
var clientID = 57;
// 客户端版本号
var client_version = 20150112001;
// 本地存储版本号
var local_version = 0;
// 服务端版本号
var server_version = 0;/* 特别注意：如果是h5版本更新，需要写这个 */
// 语言
var appLanguage = 'zh-cn';//zh-cn zh-tw th kr
// 渠道ID
var channelID = 106;//1: 自营AppStore 3: 台湾 4: 91
// 是否越狱（越狱后支付就直接提示官网）
var jBreaking = false;
// 是否是appstore官方版
var isAppstore = true;
// 平台，默认空(appstore)
var Platform = {
    id: '',//91、pc、h5
    userinfo: null
};
// ###非常重要###
var packageName = 'sanguox.app';
// 是否在PhoneGap环境
var isPhonegap = typeof cordova != 'undefined';
// 用于加载的随机数
var loadRandomNum = (new Date).getTime();

// 处理location.hash
var URLHASH = window.location.hash;
if(URLHASH.indexOf('temptoken') == -1) {//H5需要保留住temptoken
    window.location.hash = "";
}

// 处理location.search 平台的选择
if(location.search != "") {
    var arr = window.location.search.substring(1).split('&');
    for(var i = 0; i < arr.length; i++) {
        var temp = arr[i].split('=');
        if(temp[0] == "channel") {
            channelID = temp[1];
        } else if(temp[0] == "platform") {
            Platform.id = temp[1];
        }
    }
}
if (Platform.id == "pc") {
    // 本地化：原官网充值页/登录页外链已失效，统一指向本站（支付功能未迁移，占位 '#'
    Platform["payurl"] = "#";
    Platform["loginurl"] = "index.html";
    /* twpc
    Platform["payurl"] = "http://web.337.com/tw/hanjiangsanguo/";
    Platform["loginurl"] = "index.html";
    */
    if(console) {
        console.log = function() {};
    }
} else if(Platform.id == "h5") {
    // 本地化：同上
    Platform["payurl"] = "#";
}


//判断是否是iphone5,包括touch5,视网膜屏幕并且高度等于548
var isIPhone5 = 'devicePixelRatio' in window && window.devicePixelRatio == 2 && window.screen.height == 568;
if(isIPhone5) {
    $('body').addClass('iPhone5');
}
//console.log(isIPhone5 ? '这是iphone5' : '这不是iphone5');
//判断该设备是否支持全屏
var supportFullScreen = false;
if(/OS 6_\d(_\d)? like Mac OS X/i.test(navigator.userAgent) && /(iPhone|iPod)/i.test(navigator.userAgent)) {
    //alert('该设备支持全屏');
    supportFullScreen = true;
} else {
    //alert('该设备不支持全屏');
    supportFullScreen = false;
}

/* 初始化事件 */
//  如果是手机浏览器，绑定touchstart（原版只认 navigator.standalone 即 iOS，
//  安卓手机上横竖屏/全屏逻辑全部失效，这里补上 HJ_IS_MOBILE 判定）
if (typeof cordova == 'undefined' && (window.navigator.standalone != undefined || window.HJ_IS_MOBILE)) {
    //touchstart
    window.addEventListener("touchstart",
        function() {
            hideAddressBar();
        },
    true);

    //旋转 / 视口变化：统一防抖延时重判。旋转瞬间 innerHeight 可能还是旧值，
    //立即判会把竖屏误判成横屏、"请横屏"遮罩不出现（2026-10-19 线上矩阵 R1 复现）；
    //部分浏览器只发 resize 不发 orientationchange，两个事件都兜住。
    var _orientTimer = null;
    function refreshOrientNotice() {
        if (_orientTimer) { clearTimeout(_orientTimer); }
        _orientTimer = setTimeout(function () {
            _orientTimer = null;
            orientationchangeNotice();
        }, 150);
    }
    window.addEventListener("orientationchange", refreshOrientNotice, true);
    window.addEventListener("resize", refreshOrientNotice, true);

    //resize的时候只触发检测全屏方法，原因是resize会触发多次，调用hideAddress会非常影响效率
    window.addEventListener("resize",
        function() {
            //TODO 可以加一个只有在地址栏出来时的一次性调用方法
            checkFullScreen();
        },
    true);

    // 自动全屏：浏览器规范要求全屏必须由用户手势触发，因此监听首次触摸。
    //  - 安卓 Chrome/Firefox：requestFullscreen 生效，随后尝试锁定横屏
    //  - iOS Safari（iPhone）不支持页面全屏 API：静默失败，退回
    //    apple-mobile-web-app-capable（添加到主屏幕后即为全屏）+ scrollTo 隐藏地址栏
    window.addEventListener("touchend",
        function() {
            var el = document.documentElement;
            var req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
            if (req && !document.fullscreenElement && !document.webkitFullscreenElement) {
                try {
                    var p = req.call(el);
                    if (p && p.then) {
                        p.then(function() {
                            // 全屏成功后尝试锁定横屏（仅部分安卓浏览器支持）
                            if (window.screen && screen.orientation && screen.orientation.lock) {
                                try { screen.orientation.lock('landscape').catch(function() {}); } catch (e) {}
                            }
                        }).catch(function() {});
                    }
                } catch (e) {}
            }
            // iOS 退路：滚一下页面把地址栏顶出可视区（一次性）
            window.scrollTo(0, 1);
        },
    { once: true, capture: true });
}

//禁止拖动
document.addEventListener('touchmove', preventTouchMove, false);

/* iOS Safari 标签页内无法全屏（苹果不开放 API），唯一真全屏途径是"添加到主屏幕"。
   未在主屏模式时展示一次性引导条，用户关闭后不再打扰 */
(function () {
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.maxTouchPoints > 1 && /Macintosh/.test(navigator.userAgent)); // iPadOS
    if (!isIOS || isPhonegap) { return; }
    var inStandalone = (window.navigator.standalone === true)
        || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    if (inStandalone) { return; }
    if (window.localStorage && localStorage['hj_addtohome_closed'] == '1') { return; }
    var show = function () {
        var bar = document.createElement('div');
        bar.id = 'hj_addtohome';
        bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:1001;'
            + 'background:rgba(0,0,0,.82);color:#fff;padding:10px 12px;'
            + 'font-size:14px;line-height:1.6;display:-webkit-box;display:flex;'
            + '-webkit-box-align:center;align-items:center;max-height:200px;overflow:hidden;';
        bar.innerHTML = '<div style="-webkit-box-flex:1;flex:1;">想要<b style="color:#ffd54a;">全屏无地址栏</b>运行？'
            + '点底部工具栏的 <b>分享</b> 图标 → 选择 <b>「添加到主屏幕」</b>，从桌面图标打开即可。</div>'
            + '<span id="hj_addtohome_close" style="padding:6px 10px;font-size:18px;color:#aaa;">✕</span>';
        document.body.appendChild(bar);
        bar.querySelector('#hj_addtohome_close').addEventListener('touchend', function (ev) {
            ev.stopPropagation();
            bar.parentNode && bar.parentNode.removeChild(bar);
            try { localStorage['hj_addtohome_closed'] = '1'; } catch (e) {}
        }, false);
        bar.addEventListener('touchmove', function (ev) { ev.stopPropagation(); }, false);
    };
    if (document.readyState == 'loading') {
        document.addEventListener('DOMContentLoaded', show, false);
    } else {
        show();
    }
})();

/*
 * 异步载入JS 实现队列
 * thank you, ZXSDN@csdn
 * ScriptQueue.AddTask(url);
 */
var ScriptQueue = {
    Loading : false,
    TaskQueue : [],
    CallBack : function(CallBackMethod) {
        /* 回调抛异常时 Loading=false 和 Load() 都不执行 → Loading 永真、
           队列永停 → 之后所有 showpop/loadWorld 全卡在"加载中"
           （2026-10-19 实测：elite/force 的 init 回调抛错后，后续 20+
           个弹窗全部 class=false + worldloading 卡住）。单个模块的 bug
           不能拖垮整条加载流水线。 */
        try {
            CallBackMethod && CallBackMethod();
        } catch (e) {
            try { console && console.error && console.error('[ScriptQueue] 模块回调异常（已跳过，队列继续）: ', e); } catch (e2) {}
        }
        this.Loading = false;
        this.Load();
    },
    Load : function () {
        if(!this.Loading && this.TaskQueue.length) {
            var BODY = document.getElementsByTagName("BODY")[0];

            var DLSQ = this, TaskQueue = this.TaskQueue.shift(), Script = document.createElement('script');
            var _finished = false, _retried = false, _timer = null;
            var _requeueWithCacheBust = function () {
                if (!TaskQueue || !TaskQueue.src) { return false; }
                var base = TaskQueue.src;
                /* 只重试一次：URL 已带 _r= 说明本模块重试过，不能再排队——
                   否则每次重新 Load 都是新的 _retried 闭包，会无限叠加 _r=
                   （2026-10-19 遍历时发现 404 模块疯狂重试） */
                if (base.indexOf('_r=') >= 0) { return false; }
                var sep = base.indexOf('?') >= 0 ? '&' : '?';
                try {
                    /* 已失败的 <script> 元素再改 src 不会重新加载（规范如此），
                       必须把带随机参数的 URL 塞回队首，由下一轮新建元素去请求 */
                    DLSQ.TaskQueue.unshift({
                        src: base + sep + '_r=' + (+new Date()),
                        CallBackMethod: TaskQueue.CallBackMethod
                    });
                } catch (e) { return false; }
                return true;
            };
            var _done = function () {
                if (_finished) { return; }
                _finished = true;
                if (_timer) { clearTimeout(_timer); _timer = null; }
                try { Script.onload = Script.onreadystatechange = Script.onerror = null; } catch (e) {}
                try { Script.parentNode && Script.parentNode.removeChild(Script); } catch (e) {}
                Script = null;
                DLSQ.CallBack(TaskQueue && TaskQueue.CallBackMethod);
                TaskQueue = null;
                var loading_em = $('#loading_init_em');
                if (loading_em.length) { loading_em.html(parseInt(loading_em.text()) + 1); }
            };
            this.Loading = true;
            Script.onload = Script.onreadystatechange = function () {
                if(Script && Script.readyState && Script.readyState != 'loaded' && Script.readyState != 'complete') {
                    return;
                }
                _done();
            };
            /* 加载失败兜底（2026-10-19）：浏览器缓存条目损坏（ERR_CACHE_READ_FAILURE）
               或插件拦截时，原来没有 onerror → Loading 永远 true → 队列停住，玩家卡在
               "正在加载模块 X/29"。这里先用随机参数重试一次强制绕过缓存，仍失败则
               跳过该模块继续加载，宁可少一个模块也不让整局卡死。 */
            Script.onerror = function () {
                if (!_retried && _requeueWithCacheBust()) {
                    _retried = true;
                    _done();
                    return;
                }
                try { console && console.warn && console.warn('[ScriptQueue] 模块加载失败，已跳过: ' + (TaskQueue && TaskQueue.src)); } catch (e) {}
                _done();
            };
            /* 超时看门狗：某些环境（缓存读取故障/插件拦截）script 的 error 事件不触发，
               单靠 onerror 仍会永久卡住。10 秒未完成即按失败处理——先重试一次，
               再超时就跳过，从机制上保证队列一定往前走。 */
            _timer = setTimeout(function () {
                if (_finished) { return; }
                if (!_retried && _requeueWithCacheBust()) {
                    _retried = true;
                    _done();
                    return;
                }
                try { console && console.warn && console.warn('[ScriptQueue] 模块加载超时，已跳过: ' + (TaskQueue && TaskQueue.src)); } catch (e) {}
                _done();
            }, 10000);
            Script.charset = 'UTF-8';
            Script.src = TaskQueue.src;
            BODY.appendChild(Script);
        }
    },
    AddTask : function (src, CallBackMethod) {
        // 本地调试模式追加时间戳，避免浏览器缓存旧 JS（改代码后无需手动清缓存）
        var suffix = (isPhonegap == true || isdebug == true) ? '?t='+loadRandomNum : '';
        this.TaskQueue.push({ 'src' : src + suffix, 'CallBackMethod' : CallBackMethod });
        this.Load();
    }
};

//基础JS列表，启动游戏时就载入，一般不再改动
var basejs_list = ['js/language.js',
'js/core/base.js',
'js/core/timer.js',
'js/core/player.js',
'js/core/audio.js',
'js/view/cd.js',
'js/view/movieclip.js',
'js/view/helper.js',
'js/view/guide.js',
'js/view/loginworld.js',
'js/view/roleworld.js',
'js/view/cityworld.js',
'js/view/mapworld.js',
'js/view/chatpop.js',
'js/view/miscpop.js',
'js/view/mailpop.js',
'js/view/mailboxpop.js',
'js/view/trendspop.js',
'js/view/battleworld.js',
'js/data/mapini.js',
'js/data/ini.js',
'js/obj/battle_hero.js',
'js/obj/battle_num.js',
'js/obj/battle_team.js',
'js/core/jsMorph-0.5.0.min.js',
'js/core/socket.io.min.js',
'js/core/PxLoader.js',
'js/core/PxLoaderImage.js',
'js/core/gamestart.js'];

//所有JS文件列表，首先载入语言包
var alljs_list = basejs_list;

//本地文件系统
var appFileSystem = null;
//已缓存文件列表
var cached_list = [];
//本地图片路径
var cached_path = '';
//加载下标
var file_index = 0;
//需要更新的文件列表
var webfile_list = {};
//仅仅是检查
var ischeck = true;
//是否停止loading动画
var stopLoadingAnim = false;

// 登录语言包
var loadLangObj = {
    /*autoload*/
    'private_msg' : '您启用了safari秘密浏览功能，请关闭后再重启游戏！',
    'protect_msg1' : '暂时无法登录游戏，请稍后重试！<br/><br/>预计恢复时间：{1}',
    'protect_msg2' : '暂时无法登录游戏，请稍后重试',
    'no_internet_msg' : '暂时无法连接网络！<br/>请检查网络状态或稍后重试！',
    'update_resource_msg' : '更新资源出错，请重试！',
    'updating_image' : '正在更新图片 {1}/{2} (共需下载{3})',
    'updating_modual' : '正在更新模块 {1}/{2} (共需下载{3})',
    'updating_style' : '正在更新样式 1/1 (共需下载{1})',
    'updating_view' : '正在更新界面 1/1 (共需下载{1})',
    'loading_modual' : '正在加载模块 <em id="loading_init_em">0</em>/{1}'
};

/*语言包转换 （要转换的字符，[字符中参数1,字符中参数2,...]）*/
function lang(key, param) {
    var str = '';

    if(typeof langObj != 'undefined' && typeof langObj[key] != 'undefined') {
        str = langObj[key];
    } else if(typeof loadLangObj != 'undefined' && typeof loadLangObj[key] != 'undefined') {
        str = loadLangObj[key];
    }

    if(str != '' && typeof param != 'undefined') {
        for(var i = 0; i < param.length; i++) {
            str = str.replace('{'+(i+1)+'}', param[i]);
        }
    }

    return str;
}

/* 界面初始化 */
    if ($.os.ipad) {
        $('#viewport').attr({
            'content': "width=1024,user-scalable=no"
        });
        $('body').css({
            'width': '1024px'
        }).addClass('iPad');
    } else if (typeof isIPhone5 != 'undefined' && isIPhone5){
        $('#viewport').attr({
            'content': "width=1136,user-scalable=no"
        });
        $('body').css({
            'width': '1136px'
        });
    }

    orientationchangeNotice();
    //隐藏地址栏
    hideAddressBar();
var init = function () {
    $('#initloading').find('.longtimetips').show();

    //测试safari秘密浏览功能是否开启（是否支持localStorage）
    try {
        localStorage['test'] = '1';
    } catch (err) {
        initmsg(lang('private_msg'));
        return false;
    }
    //临时变量
    var index = 0;
    var scriptCache = '';
    /* 开始进度条动画元素 */
    var progress_dom = $('#initloading_progress');

    var updateVersion = function() {

        local_version = localStorage['sg_version' + client_version] > 0 ? localStorage['sg_version' + client_version] : client_version;

       /* 从网络获取最新客户端版本、JS列表、需要缓存的图片列表 */
        var params = {
            'type' : 'get',
            'dataType' : 'json',
            'url' : UCAPI + 'c=ios&m=update_world_common&clientid='+clientID+'&client_version='+client_version+'&local_version='+local_version+'&lang='+appLanguage + '&channel=' + channelID,
            'complete' : function(xhr, settings) {
                    xhr = null;//回收
                },
            'success' : function(data) {
                    if(data.status == 1) {

                        server_version = data.version;
                        localStorage.setItem('sg_ver', data.versionname);

                        if(isPhonegap == true && client_version != server_version) {//当本地于服务器版本不一致
                            cached_list = data.cached_list;//需要从缓存里取的列表，用于ximg和xjs判断
                            alljs_list = data.alljs_list;//所有的JS列表

                            //需要从服务器更新
                            if(local_version != server_version) {
                                ischeck = false;
                                localStorage['sg_tmpl_v' + client_version] = '';
                                $('#updateinfo').html(data.updateinfo).show();
                                webfile_list = data.filelist;//里面包含需要更新的img,js ***
                                checkFileSystem(function() {
                                    startProgressAnim();
                                    initLocalFile_img();
                                });//开始
                            //无需更新
                            } else {
                                checkFileSystem(function() {
                                    startProgressAnim();
                                    initTmpl();
                                });//开始

                            }

                        } else {
                            startProgressAnim();
                            initTmpl();
                        }

                    } else if(data.status == 2) {//维护中，带有时间
                        initmsg(lang('protect_msg1', [data.time]));
                    } else {
                        initmsg(lang('protect_msg2'));
                    }
                },
            'error' : function(xhr, textStatus) {//无法连接网络
                    hideAddressBar();
                    initmsg(lang('no_internet_msg'));
                    return;
                },
        };
        $.ajax(params);
    };
    //PC、H5平台不走异步更新流程；浏览器环境（无 cordova）同样不走——
    //updateVersion 是 PhoneGap 本地文件 OTA 专用流程，浏览器里 checkFileSystem 也会直接退化到 initTmpl，
    //原版 isdebug=true 时该分支被强制直连，现 isdebug=false（缓存优化）后用 isPhonegap 判断保持等价行为
    if(_inArray(['h5', 'pc'], Platform.id) || isdebug || isPhonegap == false) {
        //如果有更新
        local_version = localStorage['sg_version' + client_version] > 0 ? localStorage['sg_version' + client_version] : client_version;
        if(local_version != server_version) {
            localStorage['sg_tmpl_v' + client_version] = '';
            local_version = server_version;
            localStorage['sg_version' + client_version] = local_version;
        }
        //版本号
        localStorage.setItem('sg_ver', local_version);
        // 开始
        startProgressAnim();
        initTmpl();
    } else {
        updateVersion();
    }

    function checkFileSystem(callback) {
        if(typeof cordova == 'undefined') {
            initTmpl();
        } else {
            window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, function(filesystem) {
                //console.log('请求本地存储成功，开始读写文件');
                appFileSystem = filesystem;//保存指针
                cached_path = appFileSystem.root.fullPath.replace('Documents', '');

                file_index = 0;
                callback();

            }, function() {
                stopLoadingAnim == true;
                initmsg(lang('update_resource_msg'));//更新出错
                /*
                console.log('请求本地存储失败，跳过');
                initTmpl();
                */
            });
        }
    }

    /* 更新本地图片 */
    function initLocalFile_img() {

        //正在更新图片
        setProgress((file_index + 1) / webfile_list['imgnum'], lang('updating_image',[(file_index + 1), webfile_list['imgnum'], webfile_list['allsize']]));

        if(typeof webfile_list['img'][file_index] != 'undefined') {

            //文件名，网络地址，回调
            _savefile(webfile_list['img'][file_index], staticUrl + 'img/plugin/'+webfile_list['img'][file_index], initLocalFile_img);

        } else {//完成图片加载
            file_index = 0;
            initLocalFile_css();
        }
    }

    /* 更新本地CSS */
    function initLocalFile_css() {

        //正在更新样式 1/1
        setProgress(1, lang('updating_style', [webfile_list['allsize']]));

        if(typeof webfile_list['css'][file_index] != 'undefined') {

            //文件名，网络地址，回调
            _savefile(webfile_list['css'][file_index], staticUrl + 'css/'+webfile_list['css'][file_index], initLocalFile_css);

        } else {//完成CSS加载
            file_index = 0;
            initLocalFile_js();
        }
    }

    /* 更新本地JS */
    function initLocalFile_js() {

        //正在更新模块
        setProgress((file_index + 1) / webfile_list['jsnum'], lang('updating_modual',[(file_index + 1), webfile_list['jsnum'], webfile_list['allsize']]));

        if(typeof webfile_list['js'][file_index] != 'undefined') {

            //文件名，网络地址，回调
            _savefile(webfile_list['js'][file_index], staticUrl + 'js/'+webfile_list['js'][file_index], initLocalFile_js);

        } else {
            file_index = 0;

            /* 完成所有加载 */
            localStorage['sg_version' + client_version] = server_version;
            local_version = server_version;

            initTmpl();
        }
    }

    /* 处理模版 */
    function initTmpl() {

        if(ischeck == true) {//仅仅是检查
            setProgress(1);
        } else {//有更新过
            //正在更新界面
            setProgress(1, lang('updating_view', [webfile_list['allsize']]));
        }

        if(typeof localStorage['sg_tmpl_v' + client_version] == 'undefined' || localStorage['sg_tmpl_v' + client_version] == '') {
            //这里需要根据不同的版本载入不同的语言包哟
            var tmpl_url = '';
            if(_inArray(['h5', 'pc'], Platform.id)) {//因为不能跨域，所以需要代理文件
                tmpl_url = assetVer(staticUrl + './tmpl.html');
            } else if (isPhonegap == false) {//本地测试
                tmpl_url = assetVer(staticUrl + './tmpl.html');
            } else {//phonegap
                tmpl_url = assetVer(staticUrl + './tmpl.html');
            }

            xajax(tmpl_url, function(html) {
                if(isPhonegap == true || !_inArray(['h5', 'pc'], Platform.id)) {//网页版不缓存到localStorage
                    localStorage['sg_tmpl_v' + client_version] = html;
                }
                $('body').append(html);
                initCss();
            });
        } else {
            //console.log('从缓存中读取tmpl');
            $('body').append(localStorage['sg_tmpl_v' + client_version]);
            initCss();
        }

    }


    //异步载入CSS
    function initCss() {

        var css_url = '';
        
        var h5css_url = '';
        if(client_version == server_version || _inArray(['h5', 'pc'], Platform.id) || isPhonegap == false) {//没有版本更新 || 是H5、PC版本
            css_url = assetVer(staticUrl + 'css/game.css');
        } else {
            css_url = cached_path + 'Documents/sg_cache/' + 'game.css?v='+server_version;
        }
        _insertCSSProcess(css_url);

        if(isIPhone5) {
            _insertCSSProcess(assetVer(staticUrl + 'css/iphone5.css'));
        }
        if(Platform.id == 'h5') {
            _insertCSSProcess(assetVer(staticUrl + 'css/h5.css'));
        }
        //然后加载javascript
        initScript();
    }
    
    function _insertCSSProcess(url) {
        var xcss = document.createElement("link");
        xcss.rel = "stylesheet";
        xcss.rev = "stylesheet";
        xcss.type = "text/css";
        xcss.href = url;
        document.getElementsByTagName("head")[0].appendChild(xcss);
    }

    /* 处理JS */
    function initScript() {

        setProgress(1, lang('loading_modual', [alljs_list.length]));

        for(var i in basejs_list) {
            var js_url = xjs(assetVer(staticUrl + basejs_list[i]));
            ScriptQueue.AddTask(js_url);
        }
        /*
        for(var i in alljs_list) {
            var js_url = xjs(staticUrl + alljs_list[i]);
            ScriptQueue.AddTask(js_url);
        }
        */
    }

    function startProgressAnim() {
        if(stopLoadingAnim == true) {//停止动画
            progress_dom = null;
            return false;
        }

        progress_dom.css('width', '10px').animate({
            'width' : '464px',
        }, _getRandom(100,1000), 'ease', startProgressAnim);

        function _getRandom(min,max) {
            var range = max - min;
            var rand = Math.random();
            return(min + Math.round(rand * range));
        }
    }

    /* 设置进度条 */
    function setProgress(n, text) {
        var loading_wrap = $('#initloading');
        var dom_progress = loading_wrap.find('#initloading_progress');

        if(text) {
            loading_wrap.find('#tips').html(text);
        }
        return false;

        if(n > 0) {//指定宽度
            var percent = n+'';
            percent = parseFloat(percent.substr(0, 4));
            var bar_width = 462 * percent;
        } else {//自增
            var bar_width = dom_progress.width() + 5;
            if(bar_width > 324) {//最大是0.7
                bar_width = 324;
            }
        }
        dom_progress.css({"width":bar_width+'px'});
    }

    function xajax(url, callback) {
        var params = {
            'type' : 'get',
            'dataType' : 'text',
            'url' : url,
            'complete' : function(xhr, settings) {
                    xhr = null;//回收
                },
            'success' : callback,
        };
        $.ajax(params);
    }

    function _savefile(filename, weburl, callback) {

        file_index++;

        var _file = 'sg_cache/'+filename;
        //window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, function(fileSystem) {
            //取本机图像
            appFileSystem.root.getFile(_file, null,
              //如果有，删除后，调用下载
              function(FileEntry) {
                  //console.log('本地找到:'+_file);
                  //callback();
                  FileEntry.remove(function() {
                    //console.log('删除本地原文件，重新下载');
                    appFileSystem.root.getDirectory('sg_cache', {create:true}, gotDir, onError);
                  });
              },
              //无的话，下载下来再显示
              function() {
                  appFileSystem.root.getDirectory('sg_cache', {create:true}, gotDir, onError);
              }
            );
        //}, onError);

        function gotDir(DATADIR) {
          var ft = new FileTransfer();
          var dlPath = DATADIR.fullPath + "/" + filename;
          ft.download(weburl, dlPath,
              function(e){

                //console.log('从网络:'+weburl);
                //console.log('下载完成:'+e.fullPath);

                if(filename.indexOf('.css') == -1) {//其他文件
                    callback();
                } else {//CSS文件正则替换图片路径
                    editCss();
                }

              }, onError);
        }

        function editCss() {
            appFileSystem.root.getFile(_file, {create:true}, function(f) {
                var reader = new FileReader();
                reader.onloadend = function(e) {
                    //console.log("读取到了文件内容:"+_file);
                    var csstext = e.target.result.replace(/..\/img\/plugin\//g, cached_path + 'Documents/sg_cache/');
                    csstext = csstext.replace(/..\/img\//g, cached_path + packageName + '/www/static/img/');
                    f.remove(function() {
                        //console.log('删除原css文件');
                        saveCss(csstext);
                    });
                };
                reader.readAsText(f);
            }, onError);
        }

        function saveCss(csstext) {
            appFileSystem.root.getFile(_file, {create:true}, function(f) {
                f.createWriter(function(writerOb) {
                    writerOb.onwrite=function() {
                        //console.log('正在写入css');
                    }
                    //go to the end of the file...
                    writerOb.seek(writerOb.length);
                    writerOb.write(csstext);
                    callback();
                });

            }, onError);
        }

        function onError(e) {
          console.log("ERROR");
          console.log(JSON.stringify(e));
          stopLoadingAnim == true;
          initmsg(lang('update_resource_msg'), function() {
              cleanGameCache();
          });
          //callback();
        }
    }

};
var init91 = function() {
    //绑定回调
    window.plugins.NdComPlatformPlugin.ndInitDone = function() {//init完成
        console.log('---------ndInitDone');
        window.plugins.NdComPlatformPlugin.ndLogin(null, null, []);
    };
    window.plugins.NdComPlatformPlugin.loginSuccess = function(ndUserInfo) {
        //console.log('NdCom User Info:' + ndUserInfo.ndLoginUin +'  '+  ndUserInfo.ndSessionId);
        if(typeof player != 'undefined' && player.info.uid > 0) {//已经在游戏里的登录（注销后登录的）不处理
            return false;
        }
        Platform.userinfo = ndUserInfo;
        init();
    };
    window.plugins.NdComPlatformPlugin.loginFail = function(ndLoginFail) {
        //用户信息重置
        Platform.userinfo = null;
        //停止定时器，防止继续请求用户数据
        if (typeof timer != 'undefined' && timer != null) {
            timer.paused = true;
        }
        initmsg('您登录失败或失去登录状态，请重新登录！');
    };
    window.plugins.NdComPlatformPlugin.exitPlatform = function() {//从平台出来
        console.log('---------exitPlatform');
        if(Platform.userinfo == null) {
            window.location.reload();
        }
    };
    window.plugins.NdComPlatformPlugin.sessionInvaild = function(ndsessionInvaild) {//暂时不用
        console.log('---------sessionInvaild');
        //alert(ndsessionInvaild.msg);
        //window.plugins.NdComPlatformPlugin.ndLogin(null, null, []);
    };
    window.plugins.NdComPlatformPlugin.leavePlatform = function() {//从平台出来
        console.log('---------leavePlatform');
        setTimeout(function() {
            player.reloadInfo();
        }, 1000);
    };
    window.plugins.NdComPlatformPlugin.purchaseResult = function() {
        console.log('---------purchaseResult');
        if(typeof player != 'undefined' && player.info.uid > 0) {//登录状态下，重新获取用户信息以获得充值结果
            setTimeout(function() {
                player.reloadInfo();
            }, 1000);
        }
    };
    if(sessionStorage['checkinit']) {
        console.log('----------JS CALL ndLogin');
        //直接登录
        window.plugins.NdComPlatformPlugin.ndLogin(null, null, []);
    } else {
        //检查是否初始化完成并进入91平台登录
        window.plugins.NdComPlatformPlugin.ndCheckInit(null, null, []);
        //保存状态
        sessionStorage['checkinit'] = 'true';
    }
};
var initPP = function() {
    //绑定回调
    window.plugins.PPComPlatformPlugin.loginResult = function(ppUserinfo) {
        
        if(ppUserinfo.status == 1) {
            console.log('PP User Info:' + ppUserinfo.ppuid +' '+  ppUserinfo.ppsessionid);
            if(typeof player != 'undefined' && player.info.uid > 0) {//已经在游戏里的登录（注销后登录的）不处理
                return false;
            }
            Platform.userinfo = ppUserinfo;
            init();
        } else {
            initmsg('您登录失败或失去登录状态，请重新登录！');
        }
        
    };
    window.plugins.PPComPlatformPlugin.loginFail = function() {
        //用户信息重置
        Platform.userinfo = null;
        //停止定时器，防止继续请求用户数据
        if (typeof timer != 'undefined' && timer != null) {
            timer.paused = true;
        }
        initmsg('您登录失败或失去登录状态，请重新登录！');
    };
    window.plugins.PPComPlatformPlugin.purchaseResult = function() {
        if(typeof player != 'undefined' && player.info.uid > 0) {//登录状态下，重新获取用户信息以获得充值结果
            setTimeout(function() {
                player.reloadInfo();
            }, 1000);
        }
    };
    window.plugins.PPComPlatformPlugin.ppRefresh = function() {
        if(typeof player != 'undefined' && player.info.uid > 0) {
            player.reloadInfo();
        } 
    };
    if(sessionStorage['checkinit']) {
        console.log('----------JS CALL ndLogin');
        //直接登录
        window.plugins.PPComPlatformPlugin.ppLogin(null, null, []);
    } else {
        //检查是否初始化完成并进入PP平台登录
        window.plugins.PPComPlatformPlugin.ppCheckInit(null, null, []);
        //保存状态
        sessionStorage['checkinit'] = 'true';
    }
    
};
//点金已经切换为91兼容版
var initDJ = function() {
    //绑定回调
    window.plugins.NdComPlatformPlugin.ndInitDone = function() {//init完成
        console.log('---------ndInitDone');
        window.plugins.NdComPlatformPlugin.ndLogin(null, null, []);
    };
    window.plugins.NdComPlatformPlugin.loginSuccess = function(ndUserInfo) {// 91兼容版本，里面会多一个djUin（原点金UID）
        //console.log('NdCom User Info:' + ndUserInfo.ndLoginUin +'  '+  ndUserInfo.ndSessionId);
        if(typeof player != 'undefined' && player.info.uid > 0) {//已经在游戏里的登录（注销后登录的）不处理
            return false;
        }
        Platform.userinfo = ndUserInfo;
        init();
    };
    window.plugins.NdComPlatformPlugin.loginFail = function(ndLoginFail) {
        //用户信息重置
        Platform.userinfo = null;
        //停止定时器，防止继续请求用户数据
        if (typeof timer != 'undefined' && timer != null) {
            timer.paused = true;
        }
        initmsg('您登录失败或失去登录状态，请重新登录！');
    };
    window.plugins.NdComPlatformPlugin.exitPlatform = function() {//从平台出来
        console.log('---------exitPlatform');
        if(Platform.userinfo == null) {
            window.location.reload();
        }
    };
    window.plugins.NdComPlatformPlugin.sessionInvaild = function(ndsessionInvaild) {//暂时不用
        console.log('---------sessionInvaild');
        //alert(ndsessionInvaild.msg);
        //window.plugins.NdComPlatformPlugin.ndLogin(null, null, []);
    };
    window.plugins.NdComPlatformPlugin.leavePlatform = function() {//从平台出来
        console.log('---------leavePlatform');
        setTimeout(function() {
            player.reloadInfo();
        }, 1000);
    };
    window.plugins.NdComPlatformPlugin.purchaseResult = function() {
        console.log('---------purchaseResult');
        if(typeof player != 'undefined' && player.info.uid > 0) {//登录状态下，重新获取用户信息以获得充值结果
            setTimeout(function() {
                player.reloadInfo();
            }, 1000);
        }
    };
    if(sessionStorage['checkinit']) {
        console.log('----------JS CALL ndLogin');
        //直接登录
        window.plugins.NdComPlatformPlugin.ndLogin(null, null, []);
    } else {
        //检查是否初始化完成并进入91平台登录
        window.plugins.NdComPlatformPlugin.ndCheckInit(null, null, []);
        //保存状态
        sessionStorage['checkinit'] = 'true';
    }
};
var initKY = function() {
    //绑定回调
    window.plugins.KuaiyongPlatform.onToken = function(tokenkey) {
        if(typeof player != 'undefined' && player.info.uid > 0) {//已经在游戏里的登录（注销后登录的）不处理
            return false;
        }
        Platform.userinfo = {
            uin: '',
            tokenkey: tokenkey
        };
        sessionStorage['kuaiyong_tokenkey'] = tokenkey;//将token缓存
        init();

    };
    //账号登陆模式
    window.plugins.KuaiyongPlatform.onAccountLogin = function(u, p, loginData) {
        Platform.accountLogin = true;
        var data = {
            username: u,
            password: p,
            res: loginData
        };
        sessionStorage['kuaiyong_accountlogin'] = JSON.stringify(data);
        init();
    };
    window.plugins.KuaiyongPlatform.loginFail = function() {
        //用户信息重置
        Platform.userinfo = null;
        delete sessionStorage['kuaiyong_tokenkey'];
        //停止定时器，防止继续请求用户数据
        if (typeof timer != 'undefined' && timer != null) {
            timer.paused = true;
        }
        $('#ajaxloading').show();
        setTimeout(function() {
            location.reload();
        }, 100);
        //initmsg('您登录失败或失去登录状态，请重新登录！');
    };
    window.plugins.KuaiyongPlatform.onResult = function() {
        if(typeof player != 'undefined' && player.info.uid > 0) {//登录状态下，重新获取用户信息以获得充值结果
            setTimeout(function() {
                player.reloadInfo();
            }, 1000);
        }
    };
    window.plugins.KuaiyongPlatform.kyRefresh = function() {
        if((Platform.accountLogin == true || Platform.userinfo != null) && typeof player != 'undefined' && player.info.uid > 0) {
            console.log('_______KY_Leave_Refresh');
            player.reloadInfo();
        } else/* if(DJready2Login == false)*/ {
            console.log('_______KY_Leave_Login');
            setTimeout(function() {
                window.plugins.KuaiyongPlatform.showLogin(null, null, []);
            }, 100);
        }

        if(typeof player != 'undefined' && player.info.uid > 0) {
            player.reloadInfo();
        } 
    };
    //登录或从缓存中取tokenkey
    if(sessionStorage['kuaiyong_tokenkey']) {
        Platform.userinfo = {
            uin: '',
            tokenkey: sessionStorage['kuaiyong_tokenkey']
        };
        init();
    } else {
        window.plugins.KuaiyongPlatform.showLogin(null, null, []);
    }
};

/* 91联运版 */
if(Platform.id == '91') {
    $(document).ready(function() {
        document.addEventListener("deviceready", init91, false);
    });
/* PP联运版 */
} else if(Platform.id == 'pp') {
    $(document).ready(function() {
        document.addEventListener("deviceready", initPP, false);
    });
/* 点金 */
} else if(Platform.id == 'djn') {
    $(document).ready(function() {
        document.addEventListener("deviceready", initDJ, false);
    });
/* kuaiyong */
} else if(Platform.id == 'kuaiyong') {
    $(document).ready(function() {
        document.addEventListener("deviceready", initKY, false);
    });
/* in Phonegap */
}else if(typeof cordova != 'undefined') {
    $(document).ready(function() {
        document.addEventListener("deviceready", init, false);
    });
/* 测试、H5/PC */
} else {
    init();
}

function initmsg(text, callback) {

    var initmsg_wrap = $('#initmsg');
    initmsg_wrap.find('.text').html(text);
    if(initmsg_wrap.css('display') != 'none') {//已经显示了
        return false;
    } else {
        initmsg_wrap.find('#initmsg_btn').unbind('click').bind('click', function() {
            initmsg_wrap.hide();
            initmsg_wrap = null;
            if(typeof timer != 'undefined') {
                timer.paused = true;
            }
            if(typeof gAudio != 'undefined' && gAudio != null) {
                gAudio.stopMusic();
            }
            $('#worldloading').addClass('black').show();
            $('#world').remove();

            if(callback) {
                callback();
            }

            window.location.reload();
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        });
        initmsg_wrap.show();
    }
}

/* 获取图片 @ webfile_list */
//./static/img/plugin/solo.jpg
function ximg(url) {

    if(typeof cordova == 'undefined' || url.indexOf(staticUrl + 'img/plugin/') == -1) {//本地或非新建文件，直接返回
        //console.log('ximg() 直接返回');
        return url;
    }

    url = url.replace(staticUrl + 'img/plugin/', '');
    //console.log('ximg() 用于比较的：'+url);
    if(_inArray(cached_list, url)) {
        var imgurl = cached_path + 'Documents/sg_cache/' + url;
        //console.log('img() '+imgurl);
        return imgurl;
    } else {
        var imgurl = staticUrl + 'img/plugin/' + url;
        //console.log('img() '+imgurl);
        return imgurl;
    }

}

function xjs(url) {
    if(typeof cordova == 'undefined') {//本地直接返回
        return url;
    }

    var _url = url.substring(url.lastIndexOf('/')+1);
    //console.log('xjs() 用于比较的：'+_url);
    if(_inArray(cached_list, _url)) {
        var jsurl = cached_path + 'Documents/sg_cache/' + _url;
        //console.log('xjs() '+jsurl);
        return jsurl;
    } else {
        return url;
    }

}

function _inArray(arr, find) {
    if(arr.length > 0) {
        for(var i = 0;i < arr.length; i++) {
            if(arr[i] == find) {
            return true;
            }
        }
    }
    return false;
}

/* 隐藏地址栏，这会触发resize,间接触发checkFullScreen，而且会触发多次，不可避免 */
function hideAddressBar() {

    //PC浏览器特殊处理，只设置为768（手机走下方地址栏隐藏分支）
    if (window.navigator.standalone == undefined && !window.HJ_IS_MOBILE) {
        if (window.innerHeight >= 768) {
            $("body").css("height",768);
        } else {
            $("body").css("height",window.innerHeight);
        }
        return false;
    }
    // 手机端已由 index.html 的 zoom 适配模式接管（body 固定 768 设计高），
    // 不再做"超高 body + scrollTo"的地址栏隐藏，避免与 fit() 互相踩踏
    if (window.HJ_IS_MOBILE) { return false; }
    var height = window.innerHeight;
    $('body').css("height",window.innerHeight + 100);
    window.scrollTo(0, 1);
    //重置成新高度
    $("body").css("height",window.innerHeight);
    if(Platform.id == 'h5') {
        $("#orientationchange").css("height",window.innerHeight);
    }
    //console.log('隐藏结束,window.innerHeight = ' + window.innerHeight);
    height = null;
}

function checkFullScreen() {
    // 手机端已由 index.html 的 zoom 适配模式接管 body 尺寸，跳过 642/538 高度切换
    if (window.HJ_IS_MOBILE) {
        if (typeof xGame != 'undefined' && xGame.iscroll != undefined) {
            xGame.iscroll.refresh();
        }
        return false;
    }
    //匹配iphone5
    if (window.orientation == 0 || window.orientation == 180) { //竖屏
        return false;
    }
    //已经全屏了，去掉
    if(window.navigator.standalone == true) {
        return false;
    }

    if(typeof cordova == 'undefined' && supportFullScreen) {
        //alert('支持全屏!');
        //如果高度大于538说明已经全屏了，全屏高度是642
        if (window.innerHeight > 538){
            if ($("body").attr('fullscreen') != 'true') {
                $("body").attr('fullscreen', 'true');
                $('body').css("height",window.innerHeight + 100);
                window.scrollTo(0, 1);
                $("body").css("height",642);
                //console.log('进入全屏,window.innerHeight = ' + window.innerHeight);
            }
        } else {
            if ($("body").attr('fullscreen') != 'false') {
                $("body").attr('fullscreen', 'false');
                $('body').css("height",window.innerHeight + 300);
                window.scrollTo(0, 1);
                $('body').css("height",538);
                //console.log('退出全屏,window.innerHeight = ' + window.innerHeight);
            }
        }
        if ((_inArray([106, 152, 153, 154, 155], channelID)) && typeof loginWorld != 'undefined' && loginWorld.movieclip != null) {
            loginWorld.movieclip.obj.css({'left': $('#loginworld_page').width() / 2  + 322 + 'px','top': $('#loginworld_page').height() / 2 + 51 + 'px'});
        }
    }
    if (typeof xGame != 'undefined' && xGame.iscroll != undefined) {
        xGame.iscroll.refresh();
    }
}

function orientationchangeNotice() {
    if (typeof cordova == 'undefined' && (window.navigator.standalone != undefined || window.HJ_IS_MOBILE)) {
        // 方向判定直接用视觉宽高比：部分安卓 WebView 竖屏时 window.orientation
        // 仍报 90/-90，旧分支会把竖屏误判成横屏、"请横屏"遮罩不出现
        // （2026-10-18 用户报障：竖屏打开黑屏且无提示）
        var portrait = (window.innerHeight || 0) > (window.innerWidth || 0);
        if (portrait) { //竖屏
            $('#orientationchange').css({'display':'block','height':window.innerHeight});
            if ($('#add_notice').attr('show') == 'true') {
                $('#add_notice').css('display','none');
            }

            if ($('#standalonetips').attr('show') == 'true') {
                $('#standalonetips').css('display','none');
            }
        } else { //横屏
            $('#orientationchange').css('display','none');
            hideAddressBar();
            if ($('#add_notice').attr('show') == 'true') {
                $('#add_notice').css('display','block');
            }

            if ($('#standalonetips').attr('show') == 'true') {
                $('#standalonetips').css('display','block');
            }
        }
    }
}

/* 清空缓存 */
function cleanGameCache() {

    localStorage.removeItem('sg_tmpl_v' + client_version);
    //localStorage.removeItem('htm5.app.sg.tryuid');
    if(typeof gAudio != 'undefined' && gAudio != null) {
        gAudio.stopMusic();
    }
    if(typeof cordova == 'undefined') {
        localStorage['sg_version' + client_version] = '';
        location.reload();
    } else {
        //清空目录 TODO only for AppStore
        window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, function(filesystem) {
            appFileSystem = filesystem;//保存指针

            appFileSystem.root.getDirectory('sg_cache', {create:false}, function(DATADIR) {
                //console.log('读取目录成功');
                DATADIR.removeRecursively(function() {
                    //console.log('删除目录成功');
                    localStorage['sg_version' + client_version] = '';
                    location.reload();
                }, function() {
                    //console.log('删除目录失败');
                    location.reload();
                });
            }, function() {
                //console.log('读取目录失败');
                location.reload();
            });
        }, function() {
            //console.log('请求本地存储失败，跳过');
            location.reload();
        });
    }

}

/* 防止拖动 */
function preventTouchMove(e) {
    e.preventDefault();e.stopPropagation();return false;
}

if (islocal) {
    document.addEventListener('keydown', function (e) {
        if (e.keyCode == 119) {//alert('f8');
            if ($('div#console_div').length == 0) {
                var style = '';
                var html = '';

                style += 'position:absolute;z-index:999;width:500px;height:360px;left:50%;top:50%;';
                style += 'margin-left:-250px;margin-top:-180px;background:#C7EDCC;border:5px solid #000;border-radius:0.4em;'

                html += '<span style="position:absolute;right:10px;top:10px;width:30px;height:30px;text-align:center;line-height:30px;border:1px solid #000;border-radius:0.4em;" onclick="javascript:$(\'#console_div\').remove();">X</span>'
                html += '<p><button style="width:400px;height:60px;position:absolute;left:40px;top:40px;background:#F0F0F0;" onclick="javascript:localStorage.clear();location.reload();">一键清除缓存(会自动刷新)</button></p>';
                html += '<p>';
                html += '<input id="token_input" type="text" style="position: absolute;top: 140px;left: 40px;width: 285px;height: 60px;line-height: 60px;font-size: 30px;background:#F0F0F0;"/>'
                html += '<button style="width: 100px;height: 60px;position: absolute;left: 340px;top: 140px;background:#F0F0F0;" onclick="javascript:localStorage.setItem(\'htm5.app.sg.token\', $(\'#token_input\').val());location.reload();">修改token</button>';
                html += '</p>';
                html += '<a style="width:400px;height:60px;position:absolute;left:40px;top:240px;text-align: center;color: #000;background:#F0F0F0;line-height:60px;" href="' + xGame.API + '&c=editor&token=' + xGame.token + '" target="_blank">打开编辑器</a>'

                $('body').append('<div id="console_div" style="' + style + '">' + html + '</div>');

                style = null;
            }
        }
    }, false);
}