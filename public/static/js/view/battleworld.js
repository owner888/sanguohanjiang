var frames = 0;
var timer2, fpsContainer, fpsTimer, fpsTimer2, stage;
var sprites = {};
var loaderTimer = null;

function loaderTimerStart(restart) {
    if(restart && loaderTimer) {//重新开始
        cleanLoaderTimer();
        //console.log('重启战斗定时器检查');
    } else {
        //console.log('开始战斗定时器检查');
    }
    loaderTimer = setTimeout(function() {
        console.log('战斗加载失败，回主城');

        $('#ajaxloading').hide();

        if (xGame.guide == true) {
            guide.loadGuide();
        } else {
            xGame.loadWorld('city');
        }

        var errormsg = appLanguage == 'zh-cn' ? '很抱歉，进入战场失败 (经验奖励不受影响)' : 'Fail to enter (Reward have been obtained) ';
        xGame.toast.show(errormsg);
    }, 20000);
}
function cleanLoaderTimer() {
    if(loaderTimer) {
        //console.log('清除战斗定时器检查');
        clearTimeout(loaderTimer);
        loaderTimer = null;
    }
}


/* 战斗本地图片下载器 */
var battleLoader = null;
var BattleLoader = function() {
    this.imagelist = [];
    this.next = null;
    this._index = -1;
    this.dom_progress = null;
};
BattleLoader.prototype.init = function(props) {
    this.imagelist = props.imagelist;
    this.next = props.next;
    this.dom_progress = $('#worldloading_progress');

    var that = this;
    if(appFileSystem == null) {//先判断文件系统是否已经准备好
        window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, function(filesystem) {
            appFileSystem = filesystem;//保存指针
            cached_path = appFileSystem.root.fullPath.replace('Documents', '');

            that.load();
            that = null;
        }, function() {
            location.reload();
        });
    } else {
        that.load();
        that = null;
    }
};
BattleLoader.prototype.load = function() {

    var that = this;

    var doneFn = function() {
        that._index = -1;
        that.next();
        that.next = null;
        that.imagelist.length = 0;
        that.dom_progress.html(lang('battle_ready'));
        that.dom_progress = null;
    };
    if(that.imagelist.length == 0) {
        doneFn();
        return;
    }

    that._index++;
    if(typeof that.imagelist[that._index] == 'undefined') {//加载完了
        doneFn();
        return;
    }

    that.dom_progress.html('<em class="now">'+(that._index + 1)+'</em> / <em class="now">'+that.imagelist.length+'</em>');

    var weburl = that.imagelist[that._index];//本地化：图片走 staticUrl + img/hero/
    var filename = weburl.substring(weburl.lastIndexOf('/') + 1);//a9.png

    var _file = 'sg_cache/'+filename;//本地目录里的真实路径

    var onError = function() {
        console.log('Download Error: ' + weburl);
        that._index--;//重新加载(由于网络问题，可能会有临时下载失败的)
        that.load();
    };
    //取本机图像
    appFileSystem.root.getFile(_file, null,
      function(FileEntry) {//如果有，跳过
          that.load();
      },
      function() {//无的话，下载下来（先打开目录）
          appFileSystem.root.getDirectory('sg_cache', {create:true}, function() {
              var ft = new FileTransfer();
              ft.download(weburl, cached_path + 'Documents/sg_cache/' + filename, function(e){
                that.load();
                loaderTimerStart(true);
              }, onError);
          }, onError);
      }
    );

};

/* 战斗 */
(function() {
    battleWorldClass = function() {
        this.battledata = null;
        this.resultdata = null;
        this.callback = null;
        this.k = 0; //战斗播放index
        this.t = null; //战斗播放timer
        this.imagelist = null;
        this.skip = false;
        this.bgname = '';
        this.skipresult = null; //战斗结果
        this.endfunc = null;
        this.round = 0;
        this.leave = true;//是否已经离开战场
        this.allowSkip = true;//是否允许跳过
        this.refightSkip = true;//重播是否允许跳过
        this.timeoutArr = [];
        this.battleStringData = null; // 战斗数据的字符串格式
    };
    battleWorldClass.prototype = {
        init: function(res, callback, bg, endfunc, disableSkip) {

            var that = this;

            /* 2026-10-19 加固：真实入口 xGame.loadBattle(res) 必带 API 响应
               （含 res.battle），但 loadWorld('battle') 无参路径（自动化遍历/
               异常调用）会让 res.battle 直接 TypeError，worldloading 永驻
               "加载中"。缺数据时立即走 loaderTimer 同款兜底回主城。 */
            if (!res || !res.battle) {
                cleanLoaderTimer();
                setTimeout(function() {
                    $('#worldloading').hide();
                    if (xGame.guide == true) {
                        guide.loadGuide();
                    } else {
                        xGame.loadWorld('city');
                    }
                    xGame.toast.show(appLanguage == 'zh-cn' ? '很抱歉，进入战场失败 (经验奖励不受影响)' : 'Fail to enter (Reward have been obtained) ');
                }, 0);
                return;
            }

            //判断是否允许跳过
            if(disableSkip && parseInt(player.info.level) < 20 && parseInt(res.info.first) == 1) {
                that.allowSkip = false;
            }

            //判断重播是否允许跳过
            if (that.allowSkip == false && parseInt(res.info.first) == 1 && parseInt(res.info.win) == -1) {
                that.refightSkip = false;
            } else {
                that.refightSkip = true;
            }

            //2013-09-08新增：增加超时处理，防止进战斗load图片卡死
            this.leave = false;
            loaderTimerStart();

            //loading wrap
            $('#worldloading').children('.main').append('<div id="worldloading_progress"></div>');

            if(typeof cordova != 'undefined') {//需要先预先加载图片

                var nativeimagelist = [];//需要从网络下载或检查的文件列表
                for (var i in res.battle.leftteam) {
                    if(res.battle.leftteam[i] == null) {
                        continue;
                    }
                    var heroimageid = (res.battle.leftteam[i].image || res.battle.leftteam[i].head || '1') + '';
                    var imgurl = xGame.hero(heroimageid, 2, false);
                    if(imgurl.indexOf('http') != -1) {
                        nativeimagelist.push(imgurl);
                    }
                }
                for (var i in res.battle.rightteam) {
                    if(res.battle.rightteam[i] == null) {
                        continue;
                    }
                    var heroimageid = (res.battle.rightteam[i].image || res.battle.rightteam[i].head || '27') + '';
                    if(heroimageid.indexOf('worldboss') != 0 && heroimageid.indexOf('countryboss') != 0 && heroimageid.indexOf('newyearboss') != 0) {
                        var imgurl = xGame.hero(heroimageid, 2, false);
                        if(imgurl.indexOf('http') != -1) {
                            nativeimagelist.push(imgurl);
                        }
                    }
                }

                //开始加载或检查
                if(battleLoader == null) {
                    battleLoader = new BattleLoader();
                }
                battleLoader.init({
                    imagelist: nativeimagelist,
                    next: function() {
                        that.initImages(res, callback, bg, endfunc, true);
                    }
                });

            } else {
                that.initImages(res, callback, bg, endfunc, false);
            }
        },

        initImages: function(res, callback, bg, endfunc, nativeimage) {//nativeimage: 是否使用本地图片

            if (gAudio) {
                gAudio.playbgm('battle_bgm');
            }

            var that = this;

            /* 保存数据 */
            that.endfunc = endfunc ? endfunc: null;
            that.battledata = res.battle;
            that.battleStringData = JSON.stringify(res.battle);
            that.resultdata = res.info;
            that.skipresult = {
                left: that.battledata.leftresult,
                right: that.battledata.rightresult
            };
            /* //测试空队伍
            that.battledata.process = [];
            that.battledata.leftteam = {};
            that.resultdata.win = -1;
            */
            that.callback = callback; //完成后的处理
            var bgname = staticUrl + 'img/battle/battle_bg.jpg'; //默认背景图片
            if (bg == 'pvp' || bg == 'war') {
                bgname = staticUrl + 'img/battle/battle_pvp_bg.jpg';
            } else if (bg == 'worldboss') {
                bgname = staticUrl + 'img/battle/battle_worldboss.jpg';
            } else if (bg == 'treasure') {
                bgname = staticUrl + 'img/battle/battle_treasure_bg.jpg';
            } else if (bg == 'tower') {
                bgname = staticUrl + 'img/battle/battle_tower_bg.jpg';
            } else if (bg == 'heaven') {
                bgname = staticUrl + 'img/battle/battle_heaven_bg.jpg';
            } else if (typeof bg === 'string' && bg.indexOf('sanctum') == 0) {
                bgname = staticUrl + 'img/battle/battle_' + bg + '_bg.jpg';
            } else if (typeof bg === 'string' && bg.indexOf('pacify') == 0) {
                bgname = staticUrl + 'img/battle/battle_' + bg + '_bg.jpg';
            } else if (bg == 'generaltask') {
                bgname = staticUrl + 'img/pop/battle_generaltask_bg.jpg';
            } else if (bg == 'worldarena') {
                bgname = staticUrl + 'img/pop/battle_worldarena.jpg';
            } else if (bg == 'countryboss') {
                bgname = staticUrl + 'img/battle/battle_countryboss.jpg';
            } else if (bg == 'overseastrade') {
                bgname = staticUrl + 'img/battle/battle_overseastrade_bg.jpg';
            } else if (bg == 'champions') {
                bgname = staticUrl + 'img/battle/battle_champions.jpg';
            } else if (bg == 'newyearboss') {
                bgname = staticUrl + 'img/battle/battle_newyearboss.jpg';
            } else if (bg > 0 && bg <= 6) {
                bgname = staticUrl + 'img/battle/battle_bg' + bg + '.jpg';
            } else if (bg > 6) {//图片id断档，图片实际是从11开始，没有7-10
                //bgname = staticUrl + 'img/plugin/battle_bg' + bg + '.jpg';
                bgname = staticUrl + 'img/battle/battle_bg' + bg + '.jpg';
            } else if (typeof bg === 'string' && bg.indexOf('herothrone') == 0) {
                bgname = staticUrl + 'img/battle/battle_' + bg + '.jpg';
            }
            that.bgname = bgname;
            that.imagelist = [
            /* 基础载入资源 */
            {
                id: "bg",
                size: 1,
                src: ximg(that.bgname)
            },
            {
                id: "bar_bg",
                size: 1,
                src: staticUrl + "img/battle/bar_bg.png"
            },
            {
                id: "bar_blood",
                size: 1,
                src: staticUrl + "img/battle/bar_blood.png"
            },
            {
                id: "bar_power",
                size: 1,
                src: staticUrl + "img/battle/bar_power.png"
            },
            {
                id: "bar_skill",
                size: 1,
                src: staticUrl + "img/battle/bar_skill.png"
            },
            {
                id: "label_hit",
                size: 7,
                src: staticUrl + "img/battle/label_hit.png"
            },
            {
                id: "label_miss",
                size: 7,
                src: staticUrl + "img/battle/label_miss.png"
            },
            {
                id: "label_viewbreak",
                size: 7,
                src: staticUrl + "img/battle/label_viewbreak.png"
            },
            {
                id: "label_shadow",
                size: 2,
                src: staticUrl + "img/battle/label_shadow.png"
            },
            {
                id: "num",
                size: 7,
                src: staticUrl + "img/battle/numtext.png"
            },
            {
                id: "poweranim",
                size: 7,
                src: staticUrl + "img/battle/poweranim.png"
            },
            {
                id: "attackanim",
                size: 7,
                src: staticUrl + "img/battle/attackanim.png"
            },
            ];

            //将需要加载的角色资源加入列表
            //hero_ini: 角色配置
            for (var i in that.battledata.leftteam) {
                if(that.battledata.leftteam[i] == null) {
                    continue;
                }
                var heroimageid = (that.battledata.leftteam[i].image || that.battledata.leftteam[i].head || '1') + '';
                that.imagelist.push({//站立的图片
                    id: "herostand_" + heroimageid,
                    size: 300,
                    src: xGame.hero(heroimageid, 1)
                });
                that.imagelist.push({//攻击的图片
                    id: "heroattack_" + heroimageid,
                    size: 300,
                    src: xGame.hero(heroimageid, 2, nativeimage)
                });
                that.imagelist.push({//受击的图片
                    id: "herosuffer_" + heroimageid,
                    size: 300,
                    src: xGame.hero(heroimageid, 3)
                });
            }
            for (var i in that.battledata.rightteam) {
                if(that.battledata.rightteam[i] == null) {
                    continue;
                }
                var heroimageid = (that.battledata.rightteam[i].image || that.battledata.rightteam[i].head || '27') + '';
                
                if (heroimageid.indexOf('worldboss') == 0) {
                    that.imagelist.push({
                        id: "herostand_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/worldboss/" + heroimageid + ".png"
                    });
                    that.imagelist.push({
                        id: "heroattack_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/worldboss/" + heroimageid + ".1.png"
                    });
                    that.imagelist.push({
                        id: "herosuffer_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/worldboss/" + heroimageid + ".a.png"
                    });
                } else if (heroimageid.indexOf('countryboss') == 0) {
                    that.imagelist.push({
                        id: "herostand_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/pop/" + heroimageid + "_stand.png"
                    });
                    that.imagelist.push({
                        id: "heroattack_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/pop/" + heroimageid + "_attack.png"
                    });
                    that.imagelist.push({
                        id: "herosuffer_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/pop/" + heroimageid + "_defense.png"
                    });
                } else if (heroimageid.indexOf('newyearboss') == 0) {
                    that.imagelist.push({
                        id: "herostand_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/worldboss/" + heroimageid + "_stand.png"
                    });
                    that.imagelist.push({
                        id: "heroattack_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/worldboss/" + heroimageid + "_attack.png"
                    });
                    that.imagelist.push({
                        id: "herosuffer_" + heroimageid,
                        size: 300,
                        src: staticUrl + "img/worldboss/" + heroimageid + "_defense.png"
                    });
                } else {
                    that.imagelist.push({
                        id: "herostand_" + heroimageid,
                        size: 300,
                        src: xGame.hero(heroimageid, 1)
                    });
                    that.imagelist.push({
                        id: "heroattack_" + heroimageid,
                        size: 300,
                        src: xGame.hero(heroimageid, 2, nativeimage)
                    });
                    that.imagelist.push({
                        id: "herosuffer_" + heroimageid,
                        size: 300,
                        src: xGame.hero(heroimageid, 3)
                    });
                }
            }

            var loader = new PxLoader();
            for (var i = 0; i < that.imagelist.length; i++) {
                loader.addImage(that.imagelist[i].src);
            }
            /* 进度条元素 */
            loader.addProgressListener(function(e) {
                loaderTimerStart(true);
                // log which image completed
                //worldloading_progress.html('<em class="now">'+e.completedCount+'</em> / <em class="now">'+e.totalCount+'</em>');
            });
            loader.addCompletionListener(function() {
                if(battleWorld.leave == false) {
                    battleWorld.preparePage();
                }
                loader = null;
                $('#worldloading').find('#worldloading_progress').remove();
            });
            loader.start();
            that = null;
            /*
            var loader = new Quark.ImageLoader();
            loader.addEventListener("loaded", function(e) { console.log(Math.round(e.target.getLoadedSize()/e.target.getTotalSize()*100) + "%"); });
            loader.addEventListener("complete", that.saveResource);
            loader.load(that.imagelist);
            */
        },

        preparePage: function() {
            var that = this;

            cleanLoaderTimer();

            /* UI界面 */
            var dom_world = $('#world');
            if (dom_world.find('#battleworld_page').length <= 0) {

                dom_world.append($.template('battleworld_tmpl'));
                var battleworld_page_dom = dom_world.find('#battleworld_page');

                // 宽布局适配：舞台 960x540 按 vw/1024 等比缩放（原点底部中心），
                // 与下方铺满拉伸的背景保持原版相对位置；窄视口下收缩防精灵出屏
                helper.battleStageScale('#container');

                var dom_back = dom_world.find('#backbtn');
                dom_back.attr('class', that.allowSkip == false ? 'btn disable' : 'btn show');

                dom_world.undelegate('#backbtn', 'touchend').delegate('#backbtn', 'touchend', function(e) {
                    if(that.allowSkip == true) {
                        that.skip = true;
                        $(this).hide();
                    } else {
                        $('#skip_battle_tip').show();
                    }
                    e.preventDefault();
                });
                dom_world.undelegate('#skip_battle_tip', 'webkitAnimationEnd').delegate('#skip_battle_tip', 'webkitAnimationEnd', function(e) {
                    $(this).hide();
                    e.preventDefault();
                });

                dom_world = null;

                //设置fps计算
                if (typeof isdebug != 'undefined' && isdebug == true) {
                    frames = 0;
                    fpsTimer2 = setInterval(function() {
                        frames++;
                    },
                    1000 / 60);
                    fpsContainer = $("#fps");
                    fpsTimer = setInterval(function() {
                        fpsContainer.text("FPS:" + frames);
                        frames = 0;
                    },
                    1000);
                }

                if (that.battledata.left_role && that.battledata.right_role) {
                    var battle_vs_wrap = battleworld_page_dom.find('#battle_vs');
                    battle_vs_wrap.find('.left').html('<span>Lv.' + that.battledata.left_role.level + '</span>' + that.battledata.left_role.name);
                    battle_vs_wrap.find('.right').html('<span>Lv.' + that.battledata.right_role.level + '</span>' + that.battledata.right_role.name);
                } else {
                    battleworld_page_dom.find('#battle_vs').hide();
                }

                //显示命中精准
                if(typeof that.battledata['leftbuff'] != 'undefined') {
                    var sidesArr = ['leftbuff', 'rightbuff'];
                    var viewsArr = ['jingzhun', 'mingzhong', 'kangyun'];
                    for(var i = 0; i < sidesArr.length; i++) {
                        for(var ii = 0; ii < viewsArr.length; ii++) {
                            var sides = sidesArr[i];//leftbuff
                            var views = viewsArr[ii];//jingzhun
                            var sidesData = that.battledata[sides];//leftbuff
                            if(sidesData[views] > 0) {//leftbuff.jingzhun
                                battleworld_page_dom.find('.'+sides).children('.'+views).css('color', helper.getItemColor(sidesData[views])).show();
                            }
                        }
                        //2013-8-20 战斗显示树的图标
                        if (typeof that.battledata[sidesArr[i]].tree != 'undefined' && parseInt(that.battledata[sidesArr[i]].tree) > 1) {
                            battleworld_page_dom.find('.'+sides).find('.tree').addClass('tree' + that.battledata[sidesArr[i]].tree).css('color', helper.getItemColor(that.battledata[sidesArr[i]].tree)).show();
                        }
                        
                        //2013-8-20 战斗显示坐骑图标
                        if (typeof that.battledata[sidesArr[i]].mount != 'undefined' && parseInt(that.battledata[sidesArr[i]].mount.id) > 0) {
                            battleworld_page_dom.find('.'+sides).find('.horse').css('color', helper.getItemColor(that.battledata[sidesArr[i]].mount.quality)).html('<span style="background-position:' +  helper.getHorsePostion('buff', that.battledata[sidesArr[i]].mount.id) + ';"></span><em>' + that.battledata[sidesArr[i]].mount.name + '</em>').show();
                        }
                         
                    }
                }

                //设置背景（宽布局铺满：背景图原生 1024x768，页面为 --hj-vw x 768，
                //background-size 100% 100% 拉伸，避免宽视口两侧露黑边；精灵与
                //背景美术的对齐由 #container 的同比例 scale 保证，见 preparePage）
                battleworld_page_dom.css({
                    "background": 'url(' + ximg(that.bgname) + ') 50% bottom / 100% 100% no-repeat'
                });
                battleworld_page_dom = null;

            }

            /*
            var obj = $('#battle_mask');
            if(obj.hasClass('block')){
            	obj.attr('class','hide').unbind('webkitAnimationEnd').one('webkitAnimationEnd',function(){
                    //console.log('清除水墨的样式～');
                    obj.removeClass();
                    obj = null;
                });
            }
            */
            $('#worldloading').removeClass('black').hide();

            /* 渲染战斗 */
            stage = stage || $('#container');

            that.initGroundSprites();

            //helper.creatBox();
            this.timeoutArr.push(setTimeout(function() {

                if (that.battledata.process.length <= 0) { //直接显示结果
                    that.battleResult();
                } else {
                    that.fight();
                }
            },
            400));

        },

        initGroundSprites: function() {
            var that = this;

            sprites = {};
            sprites.left = {};
            sprites.right = {};

            //step 2 加载精灵
            for (p in that.battledata.leftteam) {
                if(that.battledata.leftteam[p] == null) {
                    continue;
                }
                var heroimageid = (that.battledata.leftteam[p].image || that.battledata.leftteam[p].head || '1') + '';
                var name = that.battledata.leftteam[p].name;
                sprites['left'][p] = new Hero({
                    id: 'left' + p,
                    name: name,
                    heroid: heroimageid,
                    herodata: that.battledata.leftteam[p],
                    pos: p,
                    team: 'left',
                    heroid: heroimageid,
                    heroimage: xGame.hero(heroimageid, 1)
                });
                stage.append(sprites['left'][p].e);
                p = null;
                heroimageid = null;
            }
            for (p in that.battledata.rightteam) {
                if(that.battledata.rightteam[p] == null) {
                    continue;
                }
                var heroimageid = (that.battledata.rightteam[p].image || that.battledata.rightteam[p].head || '27') + '';
                if (heroimageid.indexOf('worldboss') == 0) {//boss
                    var name = that.battledata.rightteam[p].name;
                    sprites['right'][p] = new Boss({
                        id: 'right' + p,
                        name: name,
                        heroid: heroimageid,
                        herodata: that.battledata.rightteam[p],
                        pos: p,
                        team: 'right',
                        heroid: heroimageid,
                        heroimage: staticUrl + 'img/worldboss/' + heroimageid + '.png'
                    });
                } else if (heroimageid.indexOf('countryboss') == 0) {
                    var name = that.battledata.rightteam[p].name;
                    sprites['right'][p] = new Boss({
                        id: 'right' + p,
                        name: name,
                        heroid: heroimageid,
                        herodata: that.battledata.rightteam[p],
                        pos: p,
                        team: 'right',
                        heroid: heroimageid,
                        heroimage: staticUrl + 'img/pop/' + heroimageid + '_stand.png'
                    });
                } else if (heroimageid.indexOf('newyearboss') == 0) {
                    var name = that.battledata.rightteam[p].name;
                    sprites['right'][p] = new Boss({
                        id: 'right' + p,
                        name: name,
                        heroid: heroimageid,
                        herodata: that.battledata.rightteam[p],
                        pos: p,
                        team: 'right',
                        heroid: heroimageid,
                        heroimage: staticUrl + 'img/worldboss/' + heroimageid + '_stand.png'
                    });
                } 
                else { 
                    var name = that.battledata.rightteam[p].name;
                    sprites['right'][p] = new Hero({
                        id: 'right' + p,
                        name: name,
                        heroid: heroimageid,
                        herodata: that.battledata.rightteam[p],
                        pos: p,
                        team: 'right',
                        heroid: heroimageid,
                        heroimage: xGame.hero(heroimageid, 1)
                    });
                }
                stage.append(sprites['right'][p].e);
                p = null;
                heroimageid = null;
            }
        },

        showSkip: function() {

            var l = ['left', 'right'];

            /* 先清除 */
            for (var i = 0; i < l.length; i++) {
                var team = l[i];

                for (x in sprites[team]) {
                    sprites[team][x] = null;
                }
                sprites[team] = null;
            }
            stage.find('.battle_sprite').remove(); //清角色
            stage.find('.faint_wrap').remove(); //清眩晕
            /* 再根据result创建 */
            for (var i = 0; i < l.length; i++) {
                var team = l[i];
                sprites[team] = {};
                for (p in this.skipresult[team]) {
                    var teamdata = this.skipresult[team][p]; //当前队数据
                    var heroimageid = teamdata.image + '';
                    var name = teamdata.name;
                    if (heroimageid.indexOf('worldboss') == 0) {
                        sprites['right'][p] = new Boss({
                            id: 'right' + p,
                            name: name,
                            heroid: heroimageid,
                            herodata: teamdata,
                            pos: p,
                            team: 'right',
                            heroid: heroimageid,
                            heroimage: staticUrl + 'img/worldboss/' + heroimageid + '.png'
                        });
                    } else if (heroimageid.indexOf('countryboss') == 0) {
                        sprites['right'][p] = new Boss({
                            id: 'right' + p,
                            name: name,
                            heroid: heroimageid,
                            herodata: teamdata,
                            pos: p,
                            team: 'right',
                            heroid: heroimageid,
                            heroimage: staticUrl + 'img/pop/' + heroimageid + '_stand.png'
                        });
                    } else if (heroimageid.indexOf('newyearboss') == 0) {
                        sprites['right'][p] = new Boss({
                            id: 'right' + p,
                            name: name,
                            heroid: heroimageid,
                            herodata: teamdata,
                            pos: p,
                            team: 'right',
                            heroid: heroimageid,
                            heroimage: staticUrl + 'img/worldboss/' + heroimageid + '_stand.png'
                        });
                    }
                    else {
                        sprites[team][p] = new Hero({
                            id: team + '' + p,
                            name: name,
                            heroid: heroimageid,
                            herodata: teamdata,
                            pos: p,
                            team: team,
                            heroid: heroimageid,
                            heroimage: xGame.hero(heroimageid, 1)
                        });
                        
                    }
                    stage.append(sprites[team][p].e);
                    p = null;
                    heroimageid = null;
                    name = null;
                }
            }
        },

        fight: function() {
            var that = this;

            if (that.skip == true) { //跳过
                that.showSkip();
                that.k = 0;
                that.battleResult();
                that = null;
                return;
            }

            nowHero = null;
            nowHero = {}; //保存当前操作的对象
            that.timeoutArr.push(setTimeout(function() {
                var now = that.battledata.process[that.k];
                if (now) {
                    //console.log('BATTLE GROUND:' + that.k);
                    /* {'launch': 'right', 'attackor':3, 'beattackors':[1,2], 'skill':1, 'hit':0, 'beAttackType':0, 'hurt':[{pos:1,blood:380}, {pos:2,blood:390}]} */
                    sprites[now.launch][now.attackor].toAttack(now);
                    that.k++;
                    if(now.round != that.round) {
                        that.round = now.round;

                        if(that.round != 1) {
                            that.afterRoundDeal('left');
                            that.afterRoundDeal('right');
                        }
                        //xGame.toast.show(that.round);//debug
                    }
                    //当不允许跳过时，如果超过10回合，则显示跳过按钮
                    //屏蔽
                    /*
                    if(that.allowSkip == false && that.round == 10) {
                        $('#backbtn').show();
                    }*/
                } else {
                    that.round = 0;
                    that.k = 0;
                    that.battleResult();
                    that = null;
                    //miscpop.show({'battleResult':that.resultdata});
                }
            },
            400));

        },

        battleResult: function() {
            var that = this;
            that.timeoutArr.push(setTimeout(function() {
                if (that.endfunc) {
                    that.endfunc();
                } else {
                    that.callback();
                }
                that = null;
            },
            500));
        },

        refight: function() {
            /* 清空stage */
            this.battledata = JSON.parse(this.battleStringData);
            nowHero = null;
            stage.empty();
            this.round = 0;
            this.skip = false;
            $('#world #backbtn').attr('class', this.refightSkip == false ? 'btn disable' : 'btn show');
            this.allowSkip = this.refightSkip;
            this.initGroundSprites();
            this.fight();
            $('#backbtn').show();
        },

        clearLoseTeam: function() {
            var team = this.resultdata.win > 0 ? '.rightteam': '.leftteam';
            stage.find(team).hide();
        },

        /* 清空，准备再战 */
        clearForContinue: function() {
            this.battledata = null;
            this.battleStringData = null;
            this.resultdata = null;
            this.callback = null;
            this.round = 0;
            this.k = 0; //战斗播放index
            this.imagelist = null;
            this.skip = false;
            nowHero = null;
            stage.empty();
        },

        afterRoundDeal: function(team) {
            var that = this;

            var spritelist = sprites[team];
            for(var i in spritelist) {
                if(spritelist[i]) {
                    spritelist[i].reduceShieldRound();
                    spritelist[i].clearPenetrate();
                    spritelist[i].clearAtk();
                }
            }
        },

        unbindAll: function () {
            var dom_world = $('#world');
            dom_world.undelegate('#backbtn', 'touchend');
            dom_world.undelegate('#skip_battle_tip', 'webkitAnimationEnd');
        },

        clear: function() {

            this.leave = true;

            if (this.t != null) {
                clearInterval(this.t);
                this.t = null;
            }
            this.battledata = null;
            this.battleStringData = null;
            this.resultdata = null;
            this.callback = null;
            this.k = 0;
            this.round = 0;
            this.skip = false;
            this.skipresult = null;
            this.endfunc = null;
            this.allowSkip = true;
            this.refightSkip = true;
            this.unbindAll();
            clearInterval(fpsTimer);
            clearInterval(fpsTimer2);
            fpsContainer = null;
            fpsTimer = null;
            fpsTimer2 = null;
            if (stage) {
                stage.empty();
                stage = null;
            }
            
            var l = ['left', 'right'];

            /* 先清除 */
            for (var i = 0; i < l.length; i++) {
                var team = l[i];

                for (x in sprites[team]) {
                    sprites[team][x].clear();
                }
            }
            sprites = null;
            nowHero = null;
            this.imagelist = null;

            $('#battleworld_page').remove();

            if (gAudio) {
                gAudio.playbgm('city_bgm');
            }
            for (var i = 0; i < this.timeoutArr.length; i++) {
                clearTimeout(this.timeoutArr[i]);
            }
            this.timeoutArr.length = 0;
        },

    };
})();

function updatefn() {
    frames++;
}