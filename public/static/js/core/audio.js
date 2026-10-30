(function() {
    gAudioClass = function() {
        this.musicstatus = true; //音乐开关
        this.ready = true; //是否准备好加载音频
        this.musicurl = {}; //音乐url列表
        this.nativemusic = {};
        this.current = null; //当前应播放的 bgm id（用于自动播放解锁后恢复）
        this.isNative = false; //cordova 原生 Media；否则浏览器 HTML5 Audio

        this.musicfiles = ['city_bgm', 'battle_bgm'];

        this.init();
    };

    var resolveAudioUrl = function(name) {
        var base = (typeof staticUrl != 'undefined' && staticUrl) ? staticUrl : 'static/';
        if (base.charAt(base.length - 1) !== '/') {
            base += '/';
        }
        return base + 'audio/' + name + '.mp3';
    };

    gAudioClass.prototype = {
        init: function() {
            if (localStorage['gAudiomusicstatus'] === 'false') {
                this.musicstatus = false;
            } else {
                this.musicstatus = true;
            }

            this.ready = true;
            this.isNative = (typeof Media !== 'undefined' && typeof cordova !== 'undefined');

            // 预先填充音乐 URL
            for (var i = 0; i < this.musicfiles.length; i++) {
                var mName = this.musicfiles[i];
                this.musicurl[mName] = resolveAudioUrl(mName);
            }

            this.bindUserGestureUnlock();
        },

        bindUserGestureUnlock: function() {
            var self = this;
            var unlock = function() {
                if (self.musicstatus === true && self.current != null) {
                    self.playbgm(self.current);
                }
            };

            // 使用捕获阶段 (capture: true) 拦截全局原生手势，即使子元素调用 stopPropagation 也能 100% 触发音频解锁
            var options = { capture: true, passive: true };
            var events = ['pointerdown', 'touchstart', 'touchend', 'mousedown', 'mouseup', 'click', 'keydown'];
            for (var i = 0; i < events.length; i++) {
                window.addEventListener(events[i], unlock, options);
                document.addEventListener(events[i], unlock, options);
            }

            // 监听切后台与切回前台 (VisibilityChange & Focus)
            var onVisibilityChange = function() {
                if (!document.hidden && self.musicstatus === true) {
                    var targetBgm = self.current || 'city_bgm';
                    if ($('#battleworld_page').length > 0 || (typeof battleWorld !== 'undefined' && battleWorld && battleWorld.isshow)) {
                        targetBgm = 'battle_bgm';
                    }
                    self.playbgm(targetBgm);
                }
            };
            document.addEventListener('visibilitychange', onVisibilityChange, { passive: true });
            window.addEventListener('focus', onVisibilityChange, { passive: true });
        },

        loadMusic: function() {
            this.ready = true;
            for (var i = 0; i < this.musicfiles.length; i++) {
                var musicname = this.musicfiles[i];
                var url = resolveAudioUrl(musicname);
                this.musicurl[musicname] = url;

                if (this.isNative) {
                    if (!this.nativemusic[musicname]) {
                        this.nativemusic[musicname] = new Media(url, function() {}, function(err) {
                            console.log('native media error', err);
                        });
                    }
                } else {
                    if (!this.nativemusic[musicname]) {
                        var au = new Audio(url);
                        au.loop = true;
                        au.preload = 'auto';
                        this.nativemusic[musicname] = au;
                    }
                }
            }
        },

        settingmusic: function(status) {
            this.musicstatus = !!status;
            this.ready = true;

            if (this.musicstatus === true) {
                localStorage['gAudiomusicstatus'] = 'true';
                this.loadMusic();
                // 优先恢复当前场景适用的背景音乐
                var targetBgm = this.current || 'city_bgm';
                if ($('#battleworld_page').length > 0 || (typeof battleWorld != 'undefined' && battleWorld && battleWorld.isshow)) {
                    targetBgm = 'battle_bgm';
                }
                this.playbgm(targetBgm);
            } else {
                localStorage['gAudiomusicstatus'] = 'false';
                this.stopMusic();
            }
        },

        stopMusic: function() {
            for (var i in this.nativemusic) {
                var m = this.nativemusic[i];
                if (!m) continue;

                if (this.isNative) {
                    try {
                        m.stop();
                        m.release();
                    } catch(e) {}
                } else {
                    try {
                        m.pause();
                        m.currentTime = 0;
                    } catch(e) {}
                }
            }
        },

        playbgm: function(audioid) {
            var self = this;
            self.current = audioid;

            if (self.musicstatus !== true) {
                self.stopMusic();
                return;
            }

            self.ready = true;
            var musicurl = self.musicurl[audioid] || resolveAudioUrl(audioid);
            self.musicurl[audioid] = musicurl;

            // 暂停并重置其他背景音轨
            for (var i in self.nativemusic) {
                if (i !== audioid && self.nativemusic[i]) {
                    if (self.isNative) {
                        try { self.nativemusic[i].stop(); } catch(e) {}
                    } else {
                        try { self.nativemusic[i].pause(); } catch(e) {}
                    }
                }
            }

            if (self.isNative) {
                if (!self.nativemusic[audioid]) {
                    self.nativemusic[audioid] = new Media(musicurl, function() {}, function(err) {
                        console.log('native media error', err);
                    });
                }
                self.nativemusic[audioid].play({
                    playAudioWhenScreenIsLocked: false,
                    numberOfLoops: "infinite"
                });
            } else {
                var au = self.nativemusic[audioid];
                if (!au) {
                    au = new Audio(musicurl);
                    au.loop = true;
                    au.preload = 'auto';
                    self.nativemusic[audioid] = au;
                }

                au.muted = false;
                au.volume = 1.0;

                var playPromise = au.play();
                if (playPromise !== undefined && playPromise !== null && typeof playPromise.catch === 'function') {
                    playPromise.catch(function(err) {
                        console.log('browser audio waiting for user gesture', err);
                    });
                }
            }
        },
    };
})();
