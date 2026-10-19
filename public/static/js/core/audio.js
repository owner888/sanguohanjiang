(function() {
    gAudioClass = function() {
        this.musicstatus = true; //音乐开关
        this.ready = false; ////是否准备好加载native music
        this.musicurl = {}; //音乐url列表
        this.nativemusic = {};
        this.current = null; //当前应播放的 bgm id（用于自动播放解锁后恢复）
        this.isNative = false; //cordova 原生 Media；否则浏览器 HTML5 Audio

        this.musicfiles = ['city_bgm', 'battle_bgm'];

        this.init();
    };
    gAudioClass.prototype = {
        init: function() {
            if (localStorage['gAudiomusicstatus'] == 'false') {
                this.musicstatus = false;
            }
            // cordova 原生壳走 Media，浏览器走 HTML5 Audio（原版只有 Media，H5 静音）
            this.isNative = (typeof Media != 'undefined' && typeof cordova != 'undefined');
        },

        loadMusic: function() { //only fill url
            if (this.musicstatus == false) {
                return;
            }
            for (var i = 0; i < this.musicfiles.length; i++) {
                var musicname = this.musicfiles[i];
                this.musicurl[musicname] = staticUrl + 'audio/' + musicname + '.mp3';
                if (this.isNative) {
                    this.nativemusic[musicname] = new Media(this.musicurl[musicname],
                    function() {},
                    function() {
                        console.log('native music error')
                    });
                } else {
                    var au = new Audio(this.musicurl[musicname]);
                    au.loop = true;
                    au.preload = 'auto';
                    this.nativemusic[musicname] = au;
                }
            }
            //浏览器自动播放限制：首次用户交互后才能出声
            if (!this.isNative) {
                var self = this;
                var unlock = function() {
                    document.removeEventListener('touchend', unlock);
                    document.removeEventListener('click', unlock);
                    document.removeEventListener('keydown', unlock);
                    if (self.musicstatus == true && self.current != null) {
                        self.playbgm(self.current);
                    }
                };
                document.addEventListener('touchend', unlock, false);
                document.addEventListener('click', unlock, false);
                document.addEventListener('keydown', unlock, false);
            }
        },

        settingmusic: function(status) {
            this.musicstatus = status;
            if (status == true) {
                localStorage['gAudiomusicstatus'] = 'true';
                this.loadMusic();
                this.playbgm('city_bgm');
            } else { //停止所有音乐
                localStorage['gAudiomusicstatus'] = 'false';
                this.stopMusic();
            }
        },

        stopMusic: function() {
            for (var i in this.nativemusic) {
                var m = this.nativemusic[i];
                if (m == undefined || m == null) continue;
                if (this.isNative) {
                    m.stop();
                    m.release();
                } else {
                    m.pause();
                    try { m.currentTime = 0; } catch(e) {}
                }
            }
            this.current = null;
        },

        playbgm: function(audioid) {
            var self = this;
            self.current = audioid; //记录意图，解锁/出错恢复时用

            if (self.ready == true && self.musicstatus == true && self.musicurl[audioid] != null) {
                //step 1 干掉其他的音乐
                for (var i in self.nativemusic) {
                    if (i != audioid && self.nativemusic[i] != undefined && self.nativemusic[i] != null) {
                        if (self.isNative) {
                            self.nativemusic[i].stop();
                        } else {
                            self.nativemusic[i].pause();
                        }
                    }
                }
                var musicurl = self.musicurl[audioid];
                if (self.isNative) {
                    if (self.nativemusic[audioid] == undefined || self.nativemusic[audioid] == null) { //开始播
                        self.nativemusic[audioid] = new Media(musicurl,
                        function() {},
                        function() {
                            console.log('native music error:' + audioid)
                        });
                        self.nativemusic[audioid].play({
                            playAudioWhenScreenIsLocked: false,
                            numberOfLoops: "infinite"
                        });
                    } else { //重复播
                        // 获取媒体播放到的位置 
                        self.nativemusic[audioid].getCurrentPosition(
                        // 获取成功后调用的回调函数 
                        function(position) {
                            if (position <= -1) { //重播
                                self.nativemusic[audioid].play({
                                    playAudioWhenScreenIsLocked: false,
                                    numberOfLoops: "infinite"
                                });
                            }
                        },
                        // 发生错误后调用的回调函数 
                        function(e) {
                            console.log("Error getting pos=" + e);
                        });
                    }
                } else { //浏览器 HTML5 Audio
                    var au = self.nativemusic[audioid];
                    if (au == undefined || au == null) {
                        au = new Audio(musicurl);
                        au.loop = true;
                        au.preload = 'auto';
                        self.nativemusic[audioid] = au;
                    }
                    var p = au.play();
                    if (p != undefined && p != null && p['catch'] != undefined) {
                        p['catch'](function(e) {
                            //自动播放被拦截：等首次交互后由 unlock 恢复
                            console.log('browser bgm blocked, waiting for user gesture');
                        });
                    }
                }

            }
        },
    };

})();
