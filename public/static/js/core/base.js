/* for desktop touch.js */
if (! ((window.DocumentTouch && document instanceof DocumentTouch) || 'ontouchstart' in window)) {
    var script = document.createElement("script");
    script.src = staticUrl + "js/touch.js";
    var tag = $("head").append(script);
    $.os.desktop = true;
}

/*
var _dateline = Math.floor((new Date()).getTime() / 1000);
//2013-12-20日活动时间 圣诞活动
if (true || _dateline >= 1387468800) {
    $('body').addClass('christmas'); 
}
*/

(function() {
    xGameClass = function() {
        this.server_prefix = '';
        this.server_domain = '';
        this.server_id = 1;   // 当前服号（单域名多服时随 URL 参数 / 选服变化）
        this.API = '';
        this.token = '';
        this.xsPrefix = 'htm5.app.sg.';
        this.confirm; //弹窗消息类
        this.toast; //toast类
        this.nowWorld = '';
        this.lastWorld = '';
        this.battleSeconds = 0;
        this.battleTimer = null;
        this.iscroll;
        this.islock = false; //全局锁
        this.menustatus = true; //菜单是否开启状态
        this.activitymenustatus = false; //活动图标显示状态
        this.guide = false; //是否新手引导状态
        this.devicetoken = '000000';
        this.macaddr = '00:00:00:00:00:00';
        this.adid = '';//IDFA
        this.ate = 0;//是否开启idfa
        this.udid = '000000';//openUDID
        this.odin = '';//odin
        this.hornObj;
        /*喇叭对象*/
        this.payErrorNums = 0; //支付错误次数
        this.iosLastPay = 0;
        this.iosLastId = '';
        this.lastCheckMenuLevel = 0;
    };
    xGameClass.prototype = {

        /* 初始化 */
        init: function() {
            $('#initloading').hide(); //去掉进游戏的引导界面
            xGame.start();
        },

        start: function() {
            var that = this;
            hideAddressBar();
            that.initLogin();
            that.initPlugins();
            that.initMenu();
            player = player || new PlayerClass;
            player.init();

            //fix ios7状态栏问题 新包新版本，已经不需要了。
            /*
            if(/OS 7_\d(_\d)? like Mac OS X/i.test(navigator.userAgent)) {
                $('.rightside_wrap').css('top', '20px');
                $('.rightside2_wrap').css('top', '100px');
            }*/

            if (typeof cordova != 'undefined') { //初始化phonegap插件
                that.initPhoneGapPlugins();
            } else { //H5 浏览器：初始化音频（原版只在 cordova 壳里初始化，导致网页版无声）
                gAudio = gAudio || new gAudioClass;
                gAudio.ready = true;
                gAudio.loadMusic();
            }

            //如果本地有token，先取用户信息
            if (that.token != '') {
                player.initInfoAndEnter();
            //本地没有token，并且是H5版本，并且带有temptoken
            } else if (Platform.id == 'h5' && that.xsGet('temptoken')) {
                console.log('我在用temptoken');
                var temptoken = that.xsGet('temptoken');
                http(UCAPI + '&c=user&m=get_info_by_temptoken&', {
                    'temptoken': temptoken,
                },
                function(res) {
                    if(res.status == 1) {
                        that.xsRemove('temptoken');
                        that.xsSet('token', res.token);
                        that.xsSet('addr', '127.0.0.1');
                        that.xsSet('server_name', res.servername);
                        if(res.username == '') {//试玩用户，非常重要
                            that.xsSet('tryuid', res.uid);
                        } else {
                            that.xsSet('lastaccountsaved', res.username);
                        }
                        that.initLoginProcess();
                        player.initInfoAndEnter();
                    } else {
                        that.loadWorld('login');
                    }
                });

            //啥都没有，直接进入登录界面
            } else  {
                that.loadWorld('login');
            }

            if (isPhonegap) {
                that.reEditCss();
            }
        },

        /* 初始化通用插件 */
        initPlugins: function() {
            this.confirm = this.confirm || new confirmClass;
            this.toast = this.toast || new toastClass;
            miscpop = miscpop || new miscPopClass;
        },

        /*活动图标自动收起*/
        autoFadeOut: function() {
            if (this.activitymenustatus) {
                this.activitymenustatus = false;
                var activitymenu = $('#header').find('.header_activity_menu');
                $('#header').find('#header_activitymenu_control').removeClass('open');
                xGame.doAnimate(activitymenu, 'fadeOutTR', '0.35s',
                function() {
                    activitymenu.hide();
                });
            }
        },

        /* 初始化菜单 */
        initMenu: function() {
            var that = this;
            that.doMenuAnim();

            var dom_footer = $('#footer');
            var menu_list = dom_footer.find('#menu_list');
            var menu_list2 = dom_footer.find('#menu_list2');
            var menu_list3 = dom_footer.find('#menu_list3');
            var menu_control = dom_footer.find('#menu_control');
            $('#world').unbind('click').bind('click', function(e) {
                that.autoFadeOut();
            });
            $('#footer').unbind('touchend').bind('touchend', function(e) {
                that.autoFadeOut();
            });
            dom_footer.find('.menu_chat').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                var pop = $(this).attr('pop');
                that.showpop(pop);
                e.preventDefault();
                e.stopPropagation();
            });
            dom_footer.find('.menu_mailbox').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                var pop = $(this).attr('pop');
                that.showpop(pop);
                e.preventDefault();
                e.stopPropagation();
            });
            menu_list.undelegate('li', 'touchend').delegate('li', 'touchend',
            function(e) {
                that.autoFadeOut();
                var pop = $(this).attr('pop');
                if (pop != undefined) {
                    if (pop == 'country') {
                        player.reloadInfo(function() {
                            that.showpop('country');
                        }, false);
                    } else {
                        that.showpop(pop);
                    }

                } else {
                    that.loadWorld($(this).attr('world'));
                }
                pop = null;
                e.preventDefault();
                e.stopPropagation();
            });
            menu_list2.undelegate('li', 'touchend').delegate('li', 'touchend',
            function(e) {
                that.autoFadeOut();
                var world = $(this).attr('pop');
                if (world == 'city') {
                    menu_list2.find('#menu_map').show();
                    menu_list2.find('#menu_friends').show();
                    that.loadWorld(world);
                    $(this).hide();
                } else if (world == 'map') {
                    menu_list2.find('#menu_friends').hide();
                    menu_list2.find('#menu_city').show();
                    that.loadWorld(world);
                    $(this).hide();
                } else if (world == 'friends') {
                    xGame.showpop('friends');
                }
                e.preventDefault();
                e.stopPropagation();
            });
            
            menu_list3.undelegate('li', 'touchend').delegate('li', 'touchend',
            function(e) {
                that.autoFadeOut();
                var pop = $(this).attr('pop');
                if (pop != undefined) {
                    that.showpop(pop);
                } else {
                    that.loadWorld($(this).attr('world'));
                }
                pop = null;
                e.preventDefault();
                e.stopPropagation();
            });
            //绑定开启关闭菜单事件
            menu_control.unbind('touchend').bind('touchend',
            function(e) {
                that.menuToggle();
                e.preventDefault();
                e.stopPropagation();
            });

            var dom_header = $('#header');
            //活动图标
            dom_header.find('#header_activitymenu_control').unbind('touchend').bind('touchend', function(e){
                var linenum = Math.ceil(player.activityList.length/3) || 1;
                var activitymenu = $('#header').find('.header_activity_menu');
                if (that.activitymenustatus) {
                    that.activitymenustatus = false;
                    $('#header').find('#header_activitymenu_control').removeClass('open');
                    xGame.doAnimate(activitymenu, 'fadeOutTR', '0.35s',
                    function() {
                        activitymenu.hide();
                    });
                } else {
                    that.activitymenustatus = true;
                    //计算右侧活动图标区域宽度和坐标 begin
                    // 原版按 960/1136/1024 固定设计坐标；现按实际布局宽右对齐（宽屏适配）
                    var width = linenum * 105;
                    var layoutW = document.body.clientWidth || 960;
                    var left = layoutW - width - 10;
                    $('.rightside2_wrap').css({'right':linenum*5 + 'px'});
                    activitymenu.css({'width': width + 'px','left': left + 'px'});
                    //end
                    $('#header').find('#header_activitymenu_control').addClass('open');
                    xGame.doAnimate(activitymenu, 'fadeInTR', '0.35s',
                    function() {
                    });
                    activitymenu.show();
                }
                e.preventDefault();
                e.stopPropagation();
            });
            //通关奖励
            dom_header.find('#menu_newpassreward').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                var pop = $(this).attr('pop');
                that.showpop(pop);
                e.preventDefault();
                e.stopPropagation();
            });
            dom_header.undelegate('.rightsidebtn', 'touchend').delegate('.rightsidebtn', 'touchend',
            function(e) {
                var needFadeout = false;
                var pop = $(this).attr('pop');
                for (var i = 0; i < player.activityList.length; i++) {
                    if (pop == player.activityList[i]) {
                        needFadeout = true;
                        break;
                    }
                }
                if (!needFadeout || pop == "signin") {
                    that.autoFadeOut();
                }
                if(pop == 'cbattle') {
                    if(parseInt(player.info.leave_cd) > 0) {
                        that.loadWorld('countrybattle');
                    } else {
                        if(player.info.country_war == 4) {
                            that.loadWorld('countrybattle');
                        } else if (parseInt(player.info.is_saturday) == 0) {
                            if(player.info.enemy_name && player.info.enemy_name != "") {
                                xGame.toast.show(lang('country_battle_start_time_npc',[player.info.country_date,player.info.enemy_name]));
                            } else {
                                xGame.toast.show(lang('country_battle_start_time',[player.info.country_date]));
                            }
                        } else {
                            that.loadWorld('countrybattle');
                        }
                    }
                } else if (pop == 'gvg') {
                    that.loadWorld('gvg');
                } else if (pop == 'champions') {
                    that.loadWorld('champions');
                } else if (pop == 'nian') {     // 年兽
                    that.loadWorld('nian');
                } else if (pop == 'war') {
                    that.loadWorld('war');
                } else if (pop == 'breakthrough') {//过关斩将
                    that.loadWorld('breakthrough');
                } else {
                    that.showpop(pop);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            
            dom_header.find('#avatar_wrap').unbind('touchend').bind('touchend',
            function(e) {
                //console.log(e);
                that.autoFadeOut();
                that.showpop('settings');
                e.preventDefault();
                e.stopPropagation();
            });

            //2013-7-23 阻止事件冒泡
            dom_header.find('#vip_bj').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                that.showpop('vip');
                e.preventDefault();
                e.stopPropagation();
            });

            //购买军令
            dom_header.find('#add_act_btn').unbind('touchend').bind('touchend',
            function() {
                that.autoFadeOut();
                that.buyActConfirm();
            });

            //英雄榜
            dom_header.find('#sider_arena').unbind('touchend').bind('touchend',
            function(e) {
                that.showpop('arena');
                e.preventDefault();
                e.stopPropagation();
            });

            //征收
            dom_header.find('#sider_levy').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                xGame.showpop('levy');
                e.preventDefault();
                e.stopPropagation();
            });
            //训练
            dom_header.find('#sider_practice').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                xGame.showpop('practice');
                e.preventDefault();
                e.stopPropagation();
            });
            
            dom_header.find('#mission_wrap').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                xGame.showpop('mission');
                e.preventDefault();
                e.stopPropagation();
            });

            //国家谏言
            dom_header.find('#expostulation_wrap').find('p').unbind('click').bind('click',
            function() {
                xGame.showpop('expostulation');
            });

            //国宴
            dom_header.undelegate('#countrybanquet_wrap.open', 'click').delegate('#countrybanquet_wrap.open', 'click',
            function() {
                xGame.showpop('countrybanquet');
            });

            //国师
            dom_header.undelegate('#countrylearn_wrap.open', 'click').delegate('#countrylearn_wrap.open', 'click',
            function() {
                xGame.showpop('countrylearn');
            });

            //国家学院
            dom_header.undelegate('#countrycollege_wrap.open', 'click').delegate('#countrycollege_wrap.open', 'click',
            function() {
                xGame.showpop('countrycollege');
            });

            //国家boss
            dom_header.undelegate('#countryboss_wrap.open', 'click').delegate('#countryboss_wrap.open', 'click',
            function() {
                xGame.loadWorld('countryboss');
            });

            //聊天窗口
            $('#chatpop_small').unbind('touchend').bind('touchend',
            function(e) {
                that.autoFadeOut();
                xGame.showpop('chat');
                $(this).hide();
                e.preventDefault();
                e.stopPropagation();
            });

            dom_header = null;

        },

        setMenuList2: function(world) {
            var menu_list2 = $('#menu_list2');
            if (world == 'city') {
                menu_list2.find('#menu_map').show();
                menu_list2.find('#menu_friends').show();
                menu_list2.find('#menu_city').hide();
            } else if (world == 'map') {
                menu_list2.find('#menu_city').show();
                menu_list2.find('#menu_map').hide();
                menu_list2.find('#menu_friends').hide();
            }
            menu_list2 = null;
        },

        buyActConfirm: function() {
            var actRender = function(res) {
                if (res.price) {
                    xGame.confirm.show(lang('buy_act'), lang('nuy_act_ask', [res.price, player.info.act]), 2,
                    function() {
                        var actRender2 = function(res) {
                            //xGame.confirm.hide();
                            if (res.status == 1) {
                                player.updateinfo({
                                    "gold": res.gold,
                                    "act": res.act
                                });
                                $('#buy_act_price').html(res.price);
                                $('#buy_act_num').html(res.act);
                                //xGame.toast.show('购买成功，去战斗吧！');
                            } else if(res.status == -1) {
                                xGame.toast.show(lang('gold_not_enough'));
                            } else {
                                xGame.toast.show(lang('buy_act_fail'));
                            }
                            actRender2 = null;
                        };
                        loadContents({
                            'c': 'shop',
                            'm': 'buy_act',
                            't': 1
                        },
                        actRender2);
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));

                } else {
                    xGame.toast.show(lang('can_not_buy_now'));
                }
                actRender = null;
            };
            loadContents({
                'c': 'shop',
                'm': 'buy_act'
            },
            actRender);
        },

        /* 编辑器url */
        editor: function() {
            console.log(this.API + '&c=editor&token=' + this.token);
        },

        horn: function(msg) {
            if (this.nowWorld == 'elitebattle' || this.nowWorld == 'gvgbattle' || this.nowWorld == 'countrybattle') {
                //console.log('多人战场不显示喇叭');
                return false;
            }
            var width = 400;
            var obj = $('#chat_horn_msg');
            if (obj.length != 0) { //存在
                obj.find('ul').append(msg);

                var speed = Number(obj.find('ul').attr('speed'));
                //console.log('当前的速度：' + speed);
                var w = obj.find('ul').width();

                var currentW = Math.abs(obj.find('ul').offset().left - obj.offset().left - obj.width());
                //console.log('当前的位移：' + currentW);
                var new_time = (w + width - currentW) / speed;
                //console.log('当前的新时间：' + new_time );
                //obj.find('ul').animate({},new_time);
                /*先停止，然后重新reset*/
                this.hornObj.stop();
                this.hornObj = new jsMorph();

                this.hornObj.reset('msg_ul', {
                    left: -w + 'px'
                },
                {
                    duration: new_time
                },
                null, null, null,
                function() {
                    obj.addClass('chat_horn_msg_hide').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                    function() {
                        obj.remove();
                        obj = speed = w = currentW = new_time = this.hornObj = null;
                    });
                }).start();

            } else {
                obj = $('<div id="chat_horn_msg"><div class="ch_left"></div><div class="ch_center"><ul id="msg_ul">' + msg + '</ul></div><div class="ch_right"></div></div>');
                obj.appendTo($('body'));

                var w = obj.find('ul').width();
                //console.log('w在下面');
                //console.log(w);
                var time = w / 0.04;

                time = time < 8000 ? 8000 : time;

                //console.log('当前的时间：' + time);
                //var currentW = Math.abs(obj.find('ul').offset().left - obj.offset().left - obj.width());
                //console.log('当前的位移：' + currentW);
                /*有时会消失，直接弃用，用jsMorph
                obj.find('ul').attr({'speed':(w + width) / time}).animate({left:-w + 'px'},time,'linear',function(){
                    obj.addClass('chat_horn_msg_hide').one('webkitAnimationEnd',function(){
                        obj.remove();
                        obj = w = null;
                    });
                });
                */
                obj.find('ul').attr({
                    'speed': (w + width) / time
                });
                //var jsMorph = function (obj, prop, params, ease, onMorphInit, onMorph, onMorphEnd) {
                this.hornObj = new jsMorph('msg_ul', {
                    left: -w + 'px'
                },
                {
                    duration: time
                },
                null, null, null,
                function() {
                    obj.addClass('chat_horn_msg_hide').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                    function() {
                        obj.remove();
                        obj = w = null;
                    });
                }).start();
            }

        },

        menuToggle: function() {
            if (this.menustatus == true) {
                this.menuClose();
                $('body').addClass('commonhide');
                $('#chatpop_small').hide();
                $('.header_gift_btn').hide();
                $('#menu_newpassreward').hide();
                var activitymenu = $('#header').find('.header_activity_menu');
                if (this.activitymenustatus) {
                    this.activitymenustatus = false;
                    $('#header').find('#header_activitymenu_control').removeClass('open');
                    xGame.doAnimate(activitymenu, 'fadeOutTR', '0.35s',
                    function() {
                        activitymenu.hide();
                        $('#header_activitymenu_control').hide();
                    });
                } else {
                    $('#header_activitymenu_control').hide();
                }

                if (player.missionThread != null) {
                    clearInterval(player.missionThread);
                    player.missionThread = null;
                }
            } else {
                this.menuOpen();
                $('body').removeClass('commonhide');
                $('#chatpop_small').show();
                $('.header_gift_btn').show();
                if (this.nowWorld == "map" && player.info.missionsite != 1) {
                    $('#menu_newpassreward').show();    
                } else {
                    $('#menu_newpassreward').hide();    
                }
                
                $('#header_activitymenu_control').show();

            }
        },

        menuClose: function() {
            var that = this;

            if (xGame.checkLock()) {
                return;
            }

            $('#menu_control').removeClass('close');
            that.doAnimate($('#menu_list'), 'fadeOutRight', '0.35s',
            function() {

                that.menustatus = false;
                xGame.unlock();
                $('#menu_list').hide();
            });
            that.doAnimate($('#menu_list2'), 'fadeOutDown', '0.35s',
            function() {
                $('#menu_list2').hide();
                xGame.unlock();

            });
            that.doAnimate($('#menu_list3'), 'fadeOutRight', '0.35s',
            function() {
                $('#menu_list3').hide();
                xGame.unlock();
            });
        },

        menuOpen: function() {
            var that = this;

            if (xGame.checkLock()) {
                return;
            }

            $('#menu_control').addClass('close');
            that.doAnimate($('#menu_list'), 'fadeInRight', '0.35s',
            function() {
                that.menustatus = true;
                xGame.unlock();
                if (player.info.mainquest.length > 0) {
                    player.missionAutoMove();
                }
            });
            that.doAnimate($('#menu_list2'), 'fadeInUp', '0.35s',
            function() {
                xGame.unlock();
            });
            $('#menu_list').show();
            $('#menu_list2').show();
            that.doAnimate($('#menu_list3'), 'fadeInRight', '0.35s',
            function() {
                xGame.unlock();
            });
            $('#menu_list3').show();
        },

        /* 初始化本地登录信息 */
        initLogin: function() {

            /* 处理91登录 */
            if( (Platform.id == '91' || Platform.id == 'djn') && Platform.userinfo != null) {
                //91账户变更，需要重新登录游戏服务器
                if((this.xsExist('nduin') && this.xsGet('nduin') != Platform.userinfo.ndLoginUin)
                    || (this.xsExist('ndsessionid') && this.xsGet('ndsessionid') != Platform.userinfo.ndSessionId)) {
                    this.xsRemove('token');
                    this.xsRemove('addr');
                    this.xsRemove('guide');
                    this.xsRemove('guidenow');
                }
                //保存91用户信息到localStorage
                this.xsSet('nduin', Platform.userinfo.ndLoginUin);
                this.xsSet('ndsessionid', Platform.userinfo.ndSessionId);
            /* 处理PP登录 */
            } else if(Platform.id == 'pp' && Platform.userinfo != null) {
                //PP账户变更，需要重新登录游戏服务器
                if((this.xsExist('ppuid') && this.xsGet('ppuid') != Platform.userinfo.ppuid)
                    || (this.xsExist('ppsessionid') && this.xsGet('ppsessionid') != Platform.userinfo.ppsessionid)) {
                    this.xsRemove('token');
                    this.xsRemove('addr');
                    this.xsRemove('guide');
                    this.xsRemove('guidenow');
                }
                //保存91用户信息到localStorage
                this.xsSet('ppuid', Platform.userinfo.ppuid);
                this.xsSet('ppsessionid', Platform.userinfo.ppsessionid);
            /* 处理快用登陆 */
            } else if(Platform.id == 'kuaiyong' && !Platform.accountLogin && Platform.userinfo != null) {
                //PP账户变更，需要重新登录游戏服务器
                if((this.xsExist('kyuin') && this.xsGet('kyuin') != Platform.userinfo.uin)
                    || (this.xsExist('kytokenkey') && this.xsGet('kytokenkey') != Platform.userinfo.tokenkey)) {
                    this.xsRemove('token');
                    this.xsRemove('addr');
                    this.xsRemove('guide');
                    this.xsRemove('guidenow');
                }
                //保存91用户信息到localStorage
                this.xsSet('kyuin', Platform.userinfo.uin);
                this.xsSet('kytokenkey', Platform.userinfo.tokenkey);
            /* 处理从官网登录 */
            } else if((Platform.id == 'h5' || Platform.id == 'pc') && typeof URLHASH != 'undefined' && URLHASH != '') {
                var arr = URLHASH.substring(3).split('&');
                for(var i = 0; i < arr.length; i++) {
                    var temp = arr[i].split('=');
                    this.xsSet(temp[0], decodeURIComponent(temp[1]));
                    if(temp[0] == 'temptoken') {//用于H5版本保存到主屏后登录
                        window.location.hash = '#!/temptoken=' + temp[1];
                    }
                }
            }

            /* 核心处理 */
            this.initLoginProcess();

            return true;
        },

        // 服务端下发的选服地址防御（换域名/加反代/未配 public_host 都靠它兜住）：
        //  - 地址为空               → 用当前页面的 host（登录页与游戏 API 必然同源）
        //  - 外网访问却拿到回环/内网地址 → 说明服务端没能感知真实域名，同样回退当前页面 host
        //  - 本机访问（localhost/127.0.0.1）→ 回环地址是合法的（本地多服各占端口），原样保留
        safeAddr: function(addr) {
            var self = location.hostname || '';
            var selfIsLocal = !self || self === '127.0.0.1' || self === 'localhost' || self === '::1';
            if (!addr) {
                return location.host;
            }
            var a = String(addr).replace(/^https?:\/\//i, '').replace(/[\/?#].*$/, '');
            if (selfIsLocal) {
                return a;
            }
            if (/^(127\.|localhost|0\.0\.0\.0|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/i.test(a)) {
                return location.host;
            }
            return a;
        },

        /* 切换/设定当前服号，并重算 API 前缀。
         * 单域名单端口多服（生产部署）：API 始终是当前源，只靠 server=N 参数由反代分流，
         * 因此换服不需要换地址、不需要改端口、不需要重新加载页面。 */
        setServer: function(id) {
            id = parseInt(id, 10) || 1;
            this.server_id = id;
            // 兼容字段：championsworld/warworld 用 server_prefix 反解服号，保持 's'+id 形态
            this.server_prefix = 's' + id;
            this.server_domain = location.hostname;
            if (typeof hjApiFor == 'function') {
                this.API = hjApiFor(id) + 'v=' + localStorage['sg_ver'];
            } else {
                this.API = location.protocol + '//' + location.host + '/index.php?server=' + id + '&v=' + localStorage['sg_ver'];
            }
            return id;
        },

        initLoginProcess: function() {
            if (!this.xsExist('token')) {
                this.token = '';
                this.server_prefix = '';
                this.server_domain = '';
                this.API = '';
            } else {
                this.token = this.xsGet('token');
                // 服号：URL ?server= > 本地上次选的服 > 1；API 由 setServer 统一按当前源生成，
                // 不再依赖服务端下发的 addr（换域名/加反代/未配 public_host 都不受影响）
                this.setServer(typeof HJ_SERVER != 'undefined' ? HJ_SERVER : 1);
            }
        },

        preLoadWorld: function(world) {
            if(this.token == '' || player.info == null) {//未登录
                world = 'login';
            } else if (world != 'countrymap') {//其他界面进入都需要先加个黑loading【国战地图例外】
                $('#worldloading').addClass('black').show();
            }

            /* 是滑动的world，需要初始化生成iscroll */
            if (world == 'city' || world == 'map' || world == 'treasure' || world == 'country' || world == 'countrymap') {
                if (!this.iscroll) {
                    this.iscroll = new iScroll('world', {
                        hScroll: true,
                        vScroll: true,
                        bounce: false,
                        hScrollbar: false,
                        vScrollbar: false,
                        checkDOMChanges: false,
                        lockDirection: false
                    });
                }
                $(this.iscroll.scroller).show();
                /* 清除iscroll */
            } else if (this.iscroll) {
                $(this.iscroll.scroller).empty().hide(); //清空DOM
                this.iscroll.destroy();
                delete this.iscroll;
            }
            /* 处理不显示头尾的 */
            if (world == 'city' || world == 'map' || world == 'treasure' || world == 'country' || world == 'countrymap') {
                $('#header').show();
                $('#footer').show();
            } else {
                $('#header').hide();
                $('#footer').hide();
            }
            /* 清掉之前的world */
            if (this.nowWorld != '') {
                //eval('if('+this.nowWorld+'World) { '+this.nowWorld+'World.clear(); };');
                if (window[this.nowWorld + 'World'] != undefined) {
                    this.lastWorld = this.nowWorld;
                    window[this.nowWorld + 'World'].clear();
                }
            }
        },

        /* 加载相应模块 */
        loadWorld: function(world, data) {
            if (window[world + 'WorldClass'] == undefined) {
                //增加一个加载延时，防止卡死
                var worldTimer = setTimeout(function() {
                    initmsg('加载失败，请重试！');
                }, 10000);

                /* 按需加载的模块也要带版本号：无版本 URL 一旦命中损坏的缓存条目
                   （ERR_CACHE_READ_FAILURE），ScriptQueue 跳过该模块 → WorldClass
                   不存在 → 直接 new 抛 TypeError、界面永远卡在"加载中"（2026-10-19） */
                var _file = xjs(assetVer(staticUrl + 'js/view/' + world + 'world.js'));
                ScriptQueue.AddTask(_file, function() {
                    clearTimeout(worldTimer);
                    worldTimer = null;
                    
                    console.log('异步载入：' + _file);
                    if (window[world + 'WorldClass'] == undefined) {
                        $('#worldloading').hide();
                        initmsg('加载失败，请检查网络后重试！');
                        return;
                    }
                    xGame.preLoadWorld(world);

                    //eval(world + 'World = ' + world + 'World || (new ' + world + 'WorldClass);' + world + 'World.init(data);xGame.nowWorld = "' + world + '";');
                    xGame.nowWorld = world;
                    window[world + 'World'] = window[world + 'World'] || (new window[world + 'WorldClass']);
                    window[world + 'World'].init(data);
                    //that.iscrollRefresh();
                    xGame.clearMissionThreadByWorld();
                    return;
                });
            } else {
                xGame.preLoadWorld(world);
                //如果不是势力场景或者关卡场景，不显示通关奖励按钮
                var menu_passreward = $('#header').find('#menu_newpassreward');
                if (this.menustatus && world == 'map' && (player.info.missionsite == 2 || player.info.missionsite == 3)) {
                    menu_passreward.show();
                    //menu_passreward.find('.popo').show();
                } else {
                    //menu_passreward.find('.popo').hide();
                    menu_passreward.hide();
                }

                //eval(world + 'World = ' + world + 'World || (new ' + world + 'WorldClass);' + world + 'World.init(data);xGame.nowWorld = "' + world + '";');
                xGame.nowWorld = world;
                window[world + 'World'] = window[world + 'World'] || (new window[world + 'WorldClass']);
                window[world + 'World'].init(data);
                //that.iscrollRefresh();
                xGame.clearMissionThreadByWorld();
                return;
            }
        },
        
        
        //根据所在world清除定时器，省效率还不会出现空白，不显示的时候one不执行
        clearMissionThreadByWorld: function() {
            //不是这个几个世界，就要清理掉，是的话就判断是否恢复
            if (!miscpop.in_array(xGame.nowWorld, ['city', 'map', 'treasure', 'countrymap'])) {
                if (player.missionThread != null) {
                    clearInterval(player.missionThread);
                    player.missionThread = null;
                }
            } else {
                if (player.info.mainquest.length > 0) {
                    player.missionAutoMove();
                }
            }
        },

        /* 加载战斗特殊处理 */
        loadBattle: function(res, callback, bg, endfunc, disableSkip) {
            var world = 'battle';
            this.preLoadWorld(world);
            xGame.nowWorld = world;
            window[world + 'World'] = window[world + 'World'] || (new window[world + 'WorldClass']);
            window[world + 'World'].init(res, callback, bg, endfunc, disableSkip);
            xGame.clearMissionThreadByWorld();
        },

        iscrollRefresh: function() {
            this.iscroll.scrollTo(0, 0);
            this.iscroll.refresh();
            setTimeout(function() {
                xGame.iscroll.refresh();
            },
            200);
        },

        showpop: function(pop, data) {
            if (pop != 'mail' && pop != 'trends') {
                $('#worldloading').show();
            }
            if (window[pop + 'PopClass'] == undefined) {
                //增加一个加载延时，防止卡死
                var showPopTimer = setTimeout(function() {
                    initmsg('加载失败，请重试！');
                }, 10000);

                var _file = xjs(assetVer(staticUrl + 'js/view/' + pop + 'pop.js'));
                ScriptQueue.AddTask(_file, function() {
                    clearTimeout(showPopTimer);
                    showPopTimer = null;

                    console.log('异步载入：' + _file);
                    // 加载失败时 ScriptQueue 会跳过模块、回调仍会被调用：此时 PopClass
                    // 不存在，直接 new 会抛 TypeError 且 worldloading 不消失 →
                    // 界面永远"加载中"（2026-10-19 玩家点布阵必现）
                    if (window[pop + 'PopClass'] == undefined) {
                        $('#worldloading').hide();
                        initmsg('加载失败，请检查网络后重试！');
                        return;
                    }
                    window[pop + 'pop'] = window[pop + 'pop'] || (new window[pop + 'PopClass']);
                    window[pop + 'pop'].init(data);
                    return;
                });
            } else {
                //eval(pop + 'pop = ' + pop + 'pop || (new ' + pop + 'PopClass);' + pop + 'pop.init();');
                window[pop + 'pop'] = window[pop + 'pop'] || (new window[pop + 'PopClass']);
                window[pop + 'pop'].init(data);
                return;
            }
        },

        //关闭所有POP from gamestart.js
        closePops: function() {
            var pops = ['miscpop', 'packpop', 'strongpop', 'levypop', 'musterpop', 'fosterpop', 'heropop', 'teampop', 'tradepop', 'barpop', 'shoppop', 'practicepop', 'chatpop', 'lotterypop', 'vippop', 'mailpop', 'arenapop', 'settingspop', 'rushpop', 'mailboxpop', 'giftpop', 'sacrificepop', 'activitypop', 'iospaypop', 'firstpaypop', 'exercisepop', 'bossbandpop', 'drinkpop', 'taskgiftpop', 'giftchangepop', 'dailytaskpop', 'dailytradepop', 'infopop', 'treasuremappop', 'talentpop', 'dailysignpop', 'worshippop', 'rebatepop', 'helpinfopop', 'workshoppop', 'slyzpop', 'expostulationpop', 'countrypop', 'countryhousepop', 'countryworshippop', 'elitepop', 'strategypop', 'inheritpop', 'trendspop', 'blackmarketpop', 'worldarenapop', 'countrylearnpop', 'countrybanquetpop', 'countrylearnpop', 'christmaspaypop', 'christmassignpop', 'christmasgeneralpop', 'yuandansignpop', 'heromanualpop', 'godminepop', 'newyearpaypop', 'eggpop', 'newbiegiftpop', 'overseastradepop', 'newyeardinnerpop', 'beatengodpop', 'friendspop', 'smashpop', 'epicpop', 'bearpop', 'viprewardpop', 'countrydicepop', 'forcepop', 'qingmingpop', 'limitedgodpop', 'advancedpop', 'invitationpop', 'missionpop', 'sacredtreepop', 'anniversarypop', 'countrytreasurepop', 'mountpop', 'studfarmpop', 'practiceleappop', 'zhulupop', 'christmasgiftpop', 'ndgiftpop', 'viprepaypop', 'springshoppop', 'newyearlotterypop', 'firstpaygiftpop', 'levelgiftpop', 'newgiftpop', 'loginedpop', 'regrewardpop', 'countrynoticepop', 'countrywarnoticepop', 'equiprefinepop', 'refineinheritpop', 'refineachievepop', 'strongachievepop', 'acttreasurepop', 'championsrewardpop', 'recallpop', 'scoreshoppop', 'showmarpop', 'kemaripop', 'kemarisuccpop', 'kemarilosepop', 'worldcuppop', 'newpassrewardpop', 'chargegiftpop', 'signinpop', 'outprintgodpop','valentinepop', 'beautypop', 'lordsoulpop', 'soulrefinepop', 'countrycollegepop', 'midautumnpop','nationcelpop', 'loginrewardpop', 'paycheckspop', 'countrymallpop', 'nationaldaypop', 'halloweenpop', 'systempop', 'forgepop', 'resolvepop', 'pacifypop', 'sweeppop', 'enrichmentpop', 'sevenstarpop', 'christmasbuypop'];
            for (var i = 0; i < pops.length; i++) {
                if (window[pops[i]] && window[pops[i]].e != null) {
                    window[pops[i]].hide();
                }
            }
        },

        //log
        log: function(s, forceStop) {},

        //localStorage | s : is sessionStorage
        xsSet: function(k, v, s) {
            k = this.xsPrefix + k;
            if (s) {
                sessionStorage.setItem(k, v);
            } else {
                localStorage.setItem(k, v);
            }
        },
        xsGet: function(k, s) {
            k = this.xsPrefix + k;
            if (s) {
                return sessionStorage.getItem(k);
            } else {
                return localStorage.getItem(k);
            }
        },
        xsRemove: function(k, s) {
            k = this.xsPrefix + k;
            if (s) {
                return sessionStorage.removeItem(k);
            } else {
                return localStorage.removeItem(k);
            }
        },
        xsExist: function(k, s) {
            var t = this.xsGet(k, s);
            if (t != null && t != '') {
                return true;
            } else {
                return false;
            }
        },

        //from wyx.draw,thank you.:)
        doAnimate: function(e, keyframes, time, callback) {
            if (!e || e.length <= 0) {
                return;
            }

            e.css({
                '-webkit-animation': keyframes + ' ' + time,
                '-webkit-animation-fill-mode': 'both'
            });

            timeseconeds = parseFloat(time.replace('s', '')) * 1000 + 100;//'0.5s' => 500 + 100(这100是为执行留出富余时间)
            //单独新增一个定时器，防止动画卡死没有回调
            var animTimer = setTimeout(function() {
                console.log(keyframes + '动画超时，强制触发事件');
                animTimer = null;
                if(e) {
                    e.trigger('webkitAnimationEnd',[]);
                }
            }, timeseconeds);

            var fn = function() {

                e.css('-webkit-animation', '');
                if (callback) {
                    callback();
                }
                e = null;
                clearTimeout(animTimer);
            };

            return e.unbind('webkitAnimationEnd').one('webkitAnimationEnd', fn);
        },

        doAnimate2: function(e, className, time, times, callback) {
            if (!e || e.length <= 0) {
                return;
            }
            var i = 0;
            var t = 0;
            var auto = null;

            var _nowclass = e.attr('class');

            auto = setInterval(function() {
                i != 1 ? i += 1 : i = 0;
                t++;
                e.attr({
                    'class': _nowclass + ' ' + className + i
                });
                if (t == times) {
                    clearInterval(auto);
                    _nowclass = e.attr('class');
                    e.attr({
                        'class': xGame.repalceLike(_nowclass, className)
                    });
                    i = t = auto = _nowclass = e = _nowclass = null;
                    if (callback != undefined) {
                        callback();
                    }
                    return;
                }
            },
            time);

        },

        repalceLike: function(class_names, class_name) {
            var rexp = new RegExp('null', "g");
            class_names = class_names.replace(rexp, '');
            rexp = new RegExp(class_name + '1', "g");
            class_names = class_names.replace(rexp, '');
            rexp = new RegExp(class_name + '0', "g");
            return class_names.replace(rexp, '');
        },

        /* 滑动列表 */
        /*
         *参数scroller_to是列表横向移动的像素，为多人副本添加
        */
        scroller: function(dom_id, scroller_width, disable_snap, scroller_to) {
            var myScroll;
            var scroller_to = scroller_to || 0;

            var dom = $('#' + dom_id);
            dom.children('.scroller_root').css({
                'width': scroller_width + 'px'
            });

            var scoller_params = {
                snap: 'li',
                momentum: false,
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            };
            if (disable_snap == true) {
                delete scoller_params.snap;
            }
            myScroll = new iScroll(dom_id, scoller_params);

            myScroll.refresh(); //更新列表
            myScroll.scrollTo(scroller_to, 0);

            setTimeout(function() { //防止"在DOM隐藏"的情况下刷新失败
                myScroll.refresh();
                myScroll.scrollTo(scroller_to, 0);
            },
            300);

            return myScroll;
        },

        /* 旋转木马滑动门
           需要保存return的对象。不用时需要清理对象：myScroll.destroy();
           增加一个方法，传入page参数，表示生产底部的点
           endFunc是翻页的方法
        */
        carousel: function(dom_id, page_width, pages, endFunc) {
            var myScroll;

            //step 1 生成页数
            var dom = $('#' + dom_id);
            if (pages == undefined) {
                var page_list = dom.children('.carousel_scroller').children('.carousel_content').children('li');
                var pages = page_list.length;
            }
            var page_html = '';
            for (var i = 0; i < pages; i++) {
                page_html += '<li page="' + i + '" class="' + (i == 0 ? 'active': '') + '">' + (i + 1) + '</li>';
            }
            page_html += '';
            dom.find('.carousel_page').html(page_html);
            dom.children('.carousel_scroller').css({
                'width': page_width * pages + 'px'
            });

            //step 2 绑定事件
            myScroll = new iScroll(dom_id, {
                snap: 'li',
                vScroll: false,
                momentum: false,
                hScrollbar: false,
                // 圆点分页区放行默认行为：否则 iScroll 的 onBeforeScrollStart 会 preventDefault
                // 掉 mousedown/touchstart，导致桌面端真实 click 无法触发翻页（合成 trigger 不受影响，故只桌面端暴露）
                onBeforeScrollStart: function(e) {
                    var n = e.target;
                    while (n && n !== this.wrapper) {
                        if (n.className && typeof n.className === 'string' && n.className.indexOf('carousel_page') !== -1) {
                            return; // 命中圆点区：不 preventDefault，让 click 正常派发
                        }
                        n = n.parentNode;
                    }
                    e.preventDefault();
                },
                onScrollEnd: function() {
                    var currPageX = this.currPageX;
                    dom.children('.carousel_page').children('li').each(function() {
                        if ($(this).hasClass('active')) {
                            $(this).removeClass();
                        }
                        if ($(this).attr('page') == currPageX) {
                            $(this).addClass('active');
                        }
                    });
                    if (endFunc != undefined) {
                        endFunc();
                    }
                }
            });
            myScroll.refresh(); //更新列表

            //step 3 桌面端没有触摸事件，iscroll 只监听 touchstart/move，鼠标拖不动 ->
            //让分页圆点可点击直接翻页（touchend 供移动端，click 供桌面端），所有 carousel 弹窗统一受益
            dom.find('.carousel_page').children('li').unbind('touchend click').bind('touchend click', function(e) {
                var p = parseInt($(this).attr('page'), 10);
                if (!isNaN(p) && myScroll) {
                    myScroll.scrollToPage(p, 0);
                }
                e.preventDefault();
            });

            setTimeout(function() {
                myScroll.refresh();
            },
            300);
            return myScroll;
        },

        /* 加载pop模版 */
        loadPopTmpl: function(popname) {
            /* 历史上这里对"已存在"返回 false，而 100+ 个调用方全是
               this.e = xGame.loadPopTmpl(x); this.e.find(...) —— false.find
               直接 TypeError，且崩后 this.e=false 让 hide() 跳过清理、
               #xxxpop 永久残留 DOM，该功能直到刷新页面都用不了
               （2026-10-19 遍历 muster/strong/pack 实测复现，根因是
               systempop 的 #systempop 残留）。改为返回已存在的 DOM：
               二次进入变成"在旧 DOM 上重渲染"，不再崩、不再残留。
               防重入由各弹窗 init 的 isshow guard 负责。 */
            var exist = $('#' + popname + 'pop');
            if (exist.length > 0) {
                return exist;
            }
            $('#world').append($.template(popname + 'pop_tmpl'));
            var dom = $('#' + popname + 'pop');
            //xGame.doAnimate(dom, 'fadeInDown', '0.25s');
            return dom;
        },

        createCloud: function() {
            var newcloud = function() {
                return '<span class="cloud" style="top:' + helper.getRandom(100, 1000) + 'px;-webkit-animation:cloudAni ' + helper.getRandom(10, 25) + 's infinite linear;-webkit-animation-delay:' + helper.getRandom(0, 6) + 's;-webkit-animation-fill-mode:both;"></span>';
            };
            //setTimeout(function() {
            var cloudstr = '';
            for (var i = 0; i < 3; i++) {
                cloudstr += xGame.newCloud();
            }
            $('#world').append(cloudstr);

            $('#world').undelegate('.cloud', 'touchend').delegate('.cloud', 'touchend',
            function() {
                var thecloud = $(this);
                var _left = thecloud.offset().left;
                var _top = thecloud.offset().top;
                thecloud.css({
                    'left': _left + 'px',
                    'top': _top + 'px',
                    '-webkit-animation': ''
                });
                xGame.doAnimate(thecloud, 'shakeOut', '0.5s',
                function() {
                    thecloud.remove();
                    thecloud = null;
                    $('#world').append(xGame.newCloud());
                });
            });
            //}, 1000);
        },

        newCloud: function() {
            return '<span class="cloud" style="top:' + helper.getRandom(100, 640) + 'px;-webkit-animation:cloudAni ' + helper.getRandom(10, 25) + 's infinite linear;-webkit-animation-delay:' + helper.getRandom(0, 6) + 's;-webkit-animation-fill-mode:both;"></span>';
        },

        clearCloud: function() {
            var dom = $('#world');
            dom.undelegate('.cloud', 'touchend');
            dom.find('.cloud').remove();
            dom = null;
        },

        /* 更新各种CD */
        updateCd: function(type, s) {
            if (!timer) {
                /* 核心计时器开始 */
                timer = new Timer(1000); //每秒倒计时
                timer.start();
            }
            if (!CD[type]) {
                //eval("CD['"+type+"'] = new "+type+"CdClass;");
                CD[type] = new window[type + 'CdClass'];
                CD[type].init(s);
                CD[type].step();
            } else {
                CD[type].update(s);
            }
        },

        guideSthHide: function() {
            $('body').addClass('commonhide');
            $('#footer').find('.menu_team').hide();
            $('#menu_friends').hide();
            $('#menu_map').hide();
            $('#menu_city').show();
            $('#chatpop_small').hide();
            $('.header_gift_btn').hide();
        },

        guideSthShow: function() {
            $('#chatpop_small').show();
            $('body').removeClass('commonhide');
            $('.header_gift_btn').show();
            $('#header').find('.rightside_wrap').show();
        },

        /* 通过等级检查玩家按钮开启状态 */
        checkMenuOpen: function() {
            //小于11级或者本级已经检查过了，就不再处理底部菜单的开放情况
            if (player.info.level < 11 || player.info.level == this.lastCheckMenuLevel) {
                return;
            }

            var menu_list = $('#menu_list');
            var menu_list3 = $('#menu_list3');
            var width = 252;/*底部菜单*/
            var width3 = 0;/*第二排菜单*/

            if (player.info.level >= 12) { //布阵、校场
                var menu = menu_list.find('.menu_team');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width += 84;
            }
            if (player.info.level >= 16) { //征收
                var levy = $('#header').find('#sider_levy');
                if(levy.is(':hidden')) {
                    levy.show();
                }
                levy = null;
            }
            if (player.info.level >= 26) { //国家
                var menu = menu_list.find('.menu_country');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width += 84;
            }
            if (player.info.level >= 51) { //将魂
                var menu = menu_list.find('.menu_soul');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width += 84;
            }
            if (player.info.level >= 91) { //天赋
                var menu = menu_list.find('.menu_talent');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width += 84;
            }
            if (player.info.level >= 140) { //宝石
                var menu = menu_list.find('.menu_gem');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width += 84;
            }
            if (menu_list.offset().width != width) {
                menu_list.width(width);
            }

            //第二排
            if (player.info.level >= 110) { //平乱
                var menu = menu_list3.find('#menu_pacify');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width3 += 84;
            }
            if (player.info.level >= 120) { //坐骑系统
                var menu = menu_list3.find('#menu_mount');
                if(menu.is(':hidden')) {
                    menu.show();
                }
                width3 += 84;
            }
            if (player.info.level >= 80) {//主公星魄
                var menu = menu_list3.find('#menu_lordsoul');
                if (menu.is(':hidden')) {
                    menu.show();
                }

                width3 += 84;
            }
            if (menu_list3.offset().width != width3) {
                menu_list3.width(width3);
            }

            menu = null;
            menu_list = null;
            menu_list3 = null;

            //保存当前已经检查过的等级
            this.lastCheckMenuLevel = player.info.level;
        },

        checkLock: function(no) { //check lock and set lock
            if (this.islock) {
                return true;
            } else {
                if (no != true) {
                    this.islock = true;
                }
                return false;
            }
        },

        unlock: function() {
            this.islock = false;
        },

        showGuide: function(allows) {
            //var allows = [2,5,11,14,17,23,26,29,35,38];
            if (xGame.guide) {
                var checkGuide = function(n) {

                    for (var i = 0; i <= allows.length; i++) {
                        if (n == allows[i]) {
                            return true;
                        }
                    }
                    return false;
                };
                if (checkGuide(guide.now)) {
                    guide.show();
                }
            }
        },

        /* 美术资源只有 image id 1..114（原外链 CDN 已失效），超出取模回退占位 */
        wrapImageId: function (imageid, max) {
            var id = parseInt(imageid);
            if (!id || id <= 0) { id = 1; }
            if (id > max) { id = ((id - 1) % max) + 1; }
            return id;
        },

        /* 本地缺失武将形象的回退映射（scripts/_gen_img_fallback.py 生成）：
           原版 image>114 走 CDN（已失效），本地化后按 同名武将/去神魔前缀 形象回退，
           不再做取模回绕（回绕会显示成别的武将，如 小乔117 被绕到 3）。 */
        imgFallback: {"avatar":{},"miniavatar":{},"hero":{}},
        fixImageId: function(kind, imageid) {
            var id = parseInt(imageid);
            if (!id || id <= 0) { id = 1; }
            var map = this.imgFallback[kind];
            if (map && map[id] != undefined) { return map[id]; }
            return id;
        },

        /* 获取头像地址 */
        avatar: function(imageid, full) {
            //this.log('获取将领形象图片:' + imageid);
            var avatarurl = '';
            imageid = this.fixImageId('avatar', imageid);
            if (this.checkImgLocal(imageid)) {
                avatarurl = staticUrl + 'img/avatar/' + (full == true ? 'a' + imageid + '.jpg': imageid + '.png');
            } else { //ximg
                //avatarurl = ximg(staticUrl + 'img/plugin/avatar_' + (full == true ? 'a' + imageid + '.jpg': imageid + '.png'));
                avatarurl = staticUrl + 'img/avatar/' + (full == true ? 'a' + imageid + '.jpg': imageid + '.png'); // 本地化：原外链 CDN 已失效
            }
            return avatarurl;
        },

        /* miniavatar */
        miniavatar: function(imageid) {
            //this.log('获取将领形象图片:' + imageid);
            var avatarurl = '';
            imageid = this.fixImageId('miniavatar', imageid);
            if (this.checkImgLocal(imageid)) {
                avatarurl = staticUrl + 'img/avatar/miniavatar' + imageid + '.png';
            } else { //ximg
                //avatarurl = ximg(staticUrl + 'img/plugin/miniavatar' + imageid + '.png');
                avatarurl = staticUrl + 'img/avatar/miniavatar' + imageid + '.png'; // 本地化
            }
            return avatarurl;
        },

        /* 获取英雄地址 */
        hero: function(imageid, type, nativeimage) {
            //this.log('获取将领战斗图片:' + imageid);
            type = type || 1;
            imageid = this.fixImageId('hero', imageid);

            var herourl = '';
            var imgname = '';
            if (type == 1) { //站立
                imgname = imageid + '.png';
            } else if (type == 2) { //攻击
                imgname = 'a' + imageid + '.png';
            } else if (type == 3) { //受击
                imgname = imageid + '.1.png';
            }

            if (this.checkImgLocal(imageid)) {
                herourl = staticUrl + 'img/hero/' + imgname;
            } else {
                if(typeof cordova != 'undefined' && typeof nativeimage != 'undefined' && nativeimage == true) {
                    herourl = cached_path + 'Documents/sg_cache/' + imgname;
                    //console.log('xGame.hero() => nativeimage : ' + herourl);
                } else {
                    herourl = staticUrl + 'img/hero/' + imgname; // 本地化
                }
            }
            return herourl;
        },

        /* 通过图片ID判断是否从本地包里取地图 */
        checkImgLocal: function(imageid) {

            if(Platform.id == 'pc') {
                return false;
            }

            if(imageid <= 40) {
                return true;
            } else {
                return false;
            }

        },

        /* 获取道具地址 */
        itemurl: function(itemid) {
            // 防护：itemid 缺失时回退 1.png，避免 undefined.png 报错
            var id = parseInt(itemid);
            if (!id || id <= 0) { id = 1; }
            // 本地化：本地包只有 1..71，超出部分回退到取模占位图（原外链 CDN 已失效）
            if (id > 71) { id = (id % 71) + 1; }
            return staticUrl + 'img/item/' + id + '.png';
        },
        
        //获取宝石地址
        gem: function(type,quality) {
            var url = '';
            url = staticUrl + 'img/pop/gem_' + type + quality + '.png'; // 本地化
            return url;
        },

        // 获取大师头像地址
        master: function (imageid) {

            var url = staticUrl + 'img/pop/master' + imageid + '.png'; // 本地化
            return url;
        },

        // 获取装备精华地址
        essence: function (imageid) {

            var url = staticUrl + 'img/pop/essence' + imageid + '.png'; // 本地化
            return url;
        },

        // 获取铸造材料地址
        forgeMaterial: function (imageid) {

            var url = staticUrl + 'img/pop/material' + imageid + '.png'; // 本地化
            return url;
        },

        initPhoneGapPlugins: function() {
            gAudio = gAudio || new gAudioClass;

            //音乐
            gAudio.ready = true;
            gAudio.loadMusic();

            //掉线弹窗
            document.addEventListener("offline",
            function() {
                if (typeof timer != 'undefined' && timer != null) {
                    timer.paused = true;
                }
                initmsg(lang('can_not_surfer_internet'));
            },
            false);

            //恢复网络
            document.addEventListener("online",
            function() {
                var initmsg_dom = $('#initmsg');
                if (initmsg_dom.css('display') != 'none') { //如果是显示状态
                    initmsg_dom.css('display', 'none');
                    if (typeof player != 'undefined' && player != null && player.info.uid > 0) {
                        player.reloadInfo();
                    }
                }
                initmsg_dom = null;

                if (typeof timer != 'undefined' && timer != null) {
                    timer.paused = false;
                }
            },
            false);

            //放到后台，设置当前时间
            document.addEventListener("pause",
            function() {
                var nowtime = parseInt(((new Date()).getTime() + '').substring(0, 10));
                if (typeof player != 'undefined' && player != null) {
                    player.lasttime = nowtime;
                }
                if (typeof timer != 'undefined' && timer != null) {
                    timer.paused = true;
                }
            },
            false);

            //后台恢复
            document.addEventListener("resume",
            function() {
                var toolong = true; //默认回来时刷新页面

                if (typeof timer != 'undefined' && timer != null) {
                    timer.paused = false;
                }

                var nowtime = parseInt(((new Date()).getTime() + '').substring(0, 10));
                if (typeof player != 'undefined' && player != null) {
                    if (nowtime - player.lasttime < 600) { //600 => 10分钟
                        toolong = false;
                        if (CD['chat'] != undefined) {
                            CD['chat'].clear();
                            xGame.updateCd('chat', 0);
                        }
                    }
                }

                if (toolong == false || (xGame.guide == true && guide.now <= 138)) { //强制引导期间不能刷新页面
                    if (typeof player != 'undefined' && player != null && player.info.uid > 0) {
                        player.reloadInfo();
                    }

                    //战神殿
                    if (xGame.nowWorld == 'champions' && typeof championsWorld != 'undefined' && championsWorld != null) {
                        championsWorld.refreshByTime(nowtime - player.lasttime);
                    }
                    
                    //群服争霸
                    if (xGame.nowWorld == 'gvg' && typeof gvgWorld != 'undefined' && championsWorld != null) {
                        gvgWorld.refreshByTime(nowtime - player.lasttime);
                    }

                } else { //超过1小时，则重新加载界面
                    if (typeof gAudio != 'undefined' && gAudio != null) {
                        gAudio.stopMusic();
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }

            },
            false);

            //支付
            window.plugins.inAppPurchaseManager.onPurchased = function(transactionIdentifier, productId, transactionReceipt) {
                xGame.hideWaitmsg();

                xGame.toast.show(lang('pay_success'));
                console.log('purchased: ' + productId);
                console.log(transactionReceipt);

                //本地保存单据
                xGame.xsSet('pay_receipt', transactionReceipt.replace('%3D', '='));
                //进行检查
                iospayCheck();
            }

            window.plugins.inAppPurchaseManager.onRestored = function(transactionIdentifier, productId, transactionReceipt) {
                console.log('restored: ' + productId);
                /* See the developer guide for details of what to do with this */
            };

            window.plugins.inAppPurchaseManager.onFailed = function(errno, errtext) {
                xGame.toast.show(lang('pay_fail'));
                xGame.hideWaitmsg();
                console.log('failed: ' + errtext);
            };


        },

        initLocalNotication: function() {
            /* 本地通知系统 */
            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {
                //先清理其他通知
                //window.plugins.localNotification.cancelAll();
                window.plugins.localNotification.cancel('worldboss');
                window.plugins.localNotification.cancel('worldboss2');
                window.plugins.localNotification.cancel('countrywar');
                window.plugins.localNotification.cancel('newyearboss');

                var now = new Date();
                var tasklist = [];//任务列表
                //12:00 BOSS站
                var firetime = now.getFullYear() + '-' + (now.getMonth() + 1) + '-' + now.getDate();
                var firetime1 = miscpop.getTimestamp(firetime + ' 12:00:00');
                var firetime2 = miscpop.getTimestamp(firetime + ' 20:00:00');
                
                //boss战斗 大于等于46级再报
                //if (parseInt(player.info.level) >= 46) {
                    tasklist.push({
                        date: (now.getTime() < firetime1 ? Math.round(firetime1 / 1000) : (Math.round(firetime1 / 1000) + 86400)), // your set date object
                        message: lang('worldboss_clock'),
                        repeat: 'daily', // will fire every week on this day
                        badge: 1,
                        id: 'worldboss',
                        sound:'sub.caf'
                    });
                    
                    tasklist.push({
                        date: (now.getTime() < firetime2 ? Math.round(firetime2 / 1000) : (Math.round(firetime2 / 1000) + 86400)), // your set date object
                        message: lang('worldboss_clock'),
                        repeat: 'daily', // will fire every week on this day
                        badge: 1,
                        id: 'worldboss2',
                        sound:'sub.caf'
                    });
                //}
                //由国家的人提示
                //if (parseInt(player.info.country) > 0) {
                    //国战 每周六5点
                    var timestamp = 1362821400;//2013年3月9号17:30为基准
                    var now_17 = Math.round(miscpop.getTimestamp(firetime + ' 17:30:00') / 1000);
                    while(timestamp < now_17) {
                        timestamp += 604800;//只要小于当前时间，就加一个星期
                    }
                    
                    tasklist.push({
                        date: timestamp, // your set date object
                        message: lang('countrywar_clock'),
                        repeat: 'weekly', // will fire every week on this day
                        badge: 1,
                        id: 'countrywar',
                        sound:'sub.caf'
                    });
                //}
                
                //只有在1月31号-2月7号活动期间内才有推送
                //时间戳分别代表 2014年1月31日 0点 和 2014年2月8日 0点
                var _dateline = Math.floor((new Date()).getTime() / 1000);
                if (_dateline >= 1391097600 && _dateline < 1391788800) {
                    
                    var firetime3 = miscpop.getTimestamp(firetime + ' 12:30:00');
                    
                    tasklist.push({
                        date: (now.getTime() < firetime1 ? Math.round(firetime3 / 1000) : (Math.round(firetime3 / 1000) + 86400)), // your set date object
                        message: lang('nian_clock'),
                        repeat: 'daily', // will fire every week on this day
                        badge: 1,
                        id: 'newyearboss',
                        sound:'sub.caf'
                    });
                }
                
                //添加通知
                if(tasklist.length > 0) {
                    for(var i = 0; i < tasklist.length; i ++) {
                        window.plugins.localNotification.add(tasklist[i]);
                    }
                }
            }
            /* 本地通知系统 end */
        },

        /* 更新用于苹果推送的deviceToken，此版本方法仅针对3.5版本 */
        updateDeviceToken: function(onlyfetch) {
            if (typeof cordova != 'undefined') {
                var that = this;
                getDeviceInfo(function() {

                    loadContents({
                        'c': 'ios',
                        'm': 'savetoken',
                        'devicetoken': that.devicetoken
                    },
                    function() {
                        //console.log('token 发送成功');
                    },
                    true);

                });
            }
        },

        showWaitmsg: function(text) {

            var initmsg_wrap = $('#initmsg');
            initmsg_wrap.addClass('waitmsg');

            initmsg_wrap.find('.text').html(text);
            initmsg_wrap.find('.waittext').html(lang('please_wait'));
            initmsg_wrap.show();

        },

        hideWaitmsg: function() {
            $('#initmsg').removeClass('waitmsg').hide();
        },

        goPay: function(text) {

            /* AppStore 提醒充值 */
            if (xGame.confirm.isshow) {
                xGame.toast.show(text);
            } else {
                xGame.confirm.show(lang('pay_now'), text + '<br/>' + lang('pay_ask'), 2,
                function() {
                    xGame.confirm.hide(function() {
                        xGame.enterPay();
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            }

        },

        enterPay: function() {
            if (player.info.rebate == 1) {//有返利
                xGame.showpop('rebate');
            } else if(typeof jBreaking != 'undefined' && jBreaking == true) {//越狱渠道，提示选择支付宝还是神州付
                miscpop.otherPay();
            } else {//直接进入通用充值界面 AppStore、alipay、91
                xGame.showpop('iospay');
            }
            return false;
        },

        getUsername: function() {
            var myusername = '';
            if(xGame.xsGet('lastaccountsaved')) {
                var lastaccount = xGame.xsGet('lastaccountsaved');
                var _index = lastaccount.indexOf(',');
                if(_index != -1) {
                    myusername = lastaccount.substr(0, _index);
                } else {
                    myusername = lastaccount;
                }
            }
            return myusername;
        },
        updateToken: function(sid, token) {
            this.xsSet('token', token);
            this.xsSet('addr', '127.0.0.1');
            window.location.reload();
        },

        saveAccount: function(u, p) {
            /*只存当前用户名*/
            this.xsSet('lastaccountsaved', u + ',' + p);

            var localAcountList = this.xsGet('acountlist');
            if (localAcountList) { //已经有数据了
                localAcountList = JSON.parse(localAcountList);

                //循环判断当前要保存的是否存在，存在就替换
                var replaced = false;
                for (var i = 0; i < localAcountList.length; i++) {
                    if (localAcountList[i].u == u) {
                        localAcountList[i].p = p;
                        replaced = true;
                    }
                }
                //新添加
                if (replaced == false) {
                    var n = localAcountList.push({
                        u: u,
                        p: p
                    });
                    //判断数量，并删除
                    if (n > 5) {
                        localAcountList.splice(0, n - 5);
                    }
                }

                this.xsSet('acountlist', JSON.stringify(localAcountList));

            } else { //直接新建
                var list = [];
                var tempobj = {
                    "u": u,
                    "p": p
                };
                list.push(tempobj);
                this.xsSet('acountlist', JSON.stringify(list));
            }

        },

        // 精彩活动的动画
        doMenuAnim: function () {
            var obj = $('#header_activitymenu_control');
            var time = 80;

            var mc = new movieClipClass({
                'img': ximg(staticUrl + 'img/common/activitymenu_control.png'),
                'width': 100,
                'height': 100,
                'x': 0,
                'y': 0,
                'loop': true,
                'frames': [
                    {
                        rect: [0, 0],
                        interval: time,
                    },
                    {
                        rect: [100, 0],
                        interval: time,
                    },
                    {
                        rect: [200, 0],
                        interval: time,
                    },
                    {
                        rect: [300, 0],
                        interval: time,
                    },
                    {
                        rect: [0, 100],
                        interval: time,
                    },
                    {
                        rect: [100, 100],
                        interval: time,
                    },
                    {
                        rect: [200, 100],
                        interval: time,
                    },
                    {
                        rect: [300, 100],
                        interval: time,
                    },
                    {
                        rect: [0, 200],
                        interval: time,
                    },
                    {
                        rect: [100, 200],
                        interval: time,
                    },
                    {
                        rect: [200, 200],
                        interval: time,
                    },
                    {
                        rect: [300, 200],
                        interval: 6000,
                    },
                ],
            });

            mc.appendTo(obj);
            mc.play();
            mc = null;
        },

        // 当本地路径改变时，重新编辑CSS
        reEditCss: function () {

            window.requestFileSystem(LocalFileSystem.PERSISTENT, 0, function(filesystem) {
                appFileSystem = filesystem;//保存指针
                cached_path = appFileSystem.root.fullPath.replace('Documents', '');
            }, function() {});
            
            var lastVersion = xGame.xsGet('version_number');
            var nowVersion = device.version;
            var lastCachedPath = xGame.xsGet('cached_path');

            if (lastVersion == null) {
                xGame.xsSet('version_number', nowVersion);
                xGame.xsSet('cached_path', cached_path);
            } else if (lastVersion != nowVersion && cached_path != lastCachedPath) {
                xGame.xsSet('version_number', nowVersion);
                xGame.xsSet('cached_path', cached_path);

                var _file = 'sg_cache/game.css';
                editCss();
            }

            function editCss() {
                appFileSystem.root.getFile(_file, {create:true}, function(f) {
                    var reader = new FileReader();
                    reader.onloadend = function(e) {
                        //console.log("读取到了文件内容:"+_file);
                        var re = new RegExp(lastCachedPath, 'g');
                        var csstext = e.target.result.replace(re, cached_path);
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
                        window.location.reload();
                    });

                }, onError);
            }

            function onError(e) {
              initmsg(lang('update_resource_msg'), function() {
                  cleanGameCache();
              });
            }

        },
    };
})();

/* 通用弹窗类 */

(function() {
    confirmClass = function() {
        this.e = null;
        this.message_wrap = null;
        this.title = null;
        this.content = null;
        this.confirm_btn = null;
        this.cancel_btn = null;
        this.hide_btn = null;
        this.isshow = false;
        this.cancelName = '';
        this.confirmName = '';
    };
    confirmClass.prototype = {

        init: function() {
            if(this.e) {
                return true;
            } else {
                this.e = $('#confirm_page');
                this.message_wrap = this.e.find('.message_wrap');
                this.title = this.e.find('.title');
                this.content = this.e.find('.text');
                this.confirm_btn = this.e.find('.confirm_btn');
                this.cancel_btn = this.e.find('.cancel_btn');
                this.hide_btn = this.e.find('.hidebtn');
            }
        },

        setContent: function(title, contentHtml, btnShow, hideBtnDisplay) { //设置弹窗内容
            this.confirm_btn.html(this.confirmName);
            this.cancel_btn.html(this.cancelName);
            this.title.html(title);
            this.content.html(contentHtml);
            if (btnShow && btnShow == 1) { //only confirmbtn
                this.confirm_btn.addClass('only').show();
                this.cancel_btn.hide();
            } else if (btnShow && btnShow == 2) {
                this.confirm_btn.removeClass('only').show();
                this.cancel_btn.show();
            } else {
                this.confirm_btn.hide();
                this.cancel_btn.hide();
            }
            if(typeof hideBtnDisplay != 'undefined' && hideBtnDisplay == true) {
                this.hide_btn.show();
            }
        },

        setListener: function(listener) {

            //正反按钮
            if (listener.confirm != null) {
                this.confirm_btn.unbind('touchend').bind('touchend', function(e) {
                    listener.confirm();
                    e.preventDefault();e.stopPropagation();
                });
            } else {
                this.confirm_btn.unbind('touchend');
            }
            if (listener.cancel != null) {
                this.cancel_btn.unbind('touchend').bind('touchend', function(e) {
                    listener.cancel();
                    e.preventDefault();e.stopPropagation();
                });
            } else {
                this.cancel_btn.unbind('touchend');
            }

            //关闭按钮
            this.hide_btn.unbind('touchend').bind('touchend',
            function(e) {
                xGame.confirm.hide();
                e.preventDefault();e.stopPropagation();
            });

        },

        _display: function(animate, callback) {
            var that = this;

            if (that.isshow) {
                if (callback) {
                    callback();
                }
            } else {
                that.e.show();
                that.message_wrap.show();
                that.isshow = true;
                if (callback) {
                    callback();
                }
            }
            /*
            animate = animate || 'fadeInDown';
            if (that.isshow) {
                xGame.doAnimate(that.mainDiv, animate, '0.35s',
                function() {
                    if (callback) {
                        callback();
                    }
                });
            } else {
                that.e.show();
                that.message_wrap.show();
                xGame.doAnimate(that.message_wrap, animate, '0.35s',
                function() {
                    that.isshow = true;
                    if (callback) {
                        callback();
                    }
                });
            }*/
        },

        hide: function(callback) {
            var that = this;

            if (that.isshow == false) {
                false;
            }

            that.setListener({
                'confirm': null,
                'cancel': null
            });


            that.hide_btn.hide();
            that.message_wrap.hide();
            that.e.hide();
            that.isshow = false;

            this.destory();

            if (callback) {
                callback();
            }
            /*
            xGame.doAnimate(that.message_wrap, 'fadeOutUp', '0.35s',
            function() {
                that.message_wrap.hide();
                that.e.hide();
                that.isshow = false;
                if (callback) {
                    callback();
                }
            });*/
        },

        show: function(title, html, btnType, confirmFn, cancelFn, confirmName, cancelName, hideBtnDisplay) {
            var that = this;

            this.init();
            
            btnType = btnType || 1;
            confirmFn = confirmFn ||
            function() {
                xGame.confirm.hide();
            };
            that.confirmName = confirmName == undefined ? lang('ok') : confirmName;
            that.cancelName = cancelName == undefined ? lang('close') : cancelName;

            that.setContent(title, html, btnType, hideBtnDisplay);
            that._display('fadeInDown',
            function() { //弹窗显示完才绑定事件
                that.setListener({
                    'confirm': confirmFn,
                    'cancel': cancelFn
                });
            });
        },

        /* 销毁dom暂用的内存 */
        destory: function() {
            this.e = null;
            this.message_wrap = null;
            this.title = null;
            this.content = null;
            this.confirm_btn = null;
            this.cancel_btn = null;
            this.hide_btn = null;
        }

    };
})();

/* toast */
/*
var xGame.toast = new toastClass;
xGame.toast.show("欢迎来到天堂列车");
*/
(function() {
    toastClass = function() {
        $('body').undelegate(".toast_msg", 'webkitAnimationEnd').delegate(".toast_msg", 'webkitAnimationEnd',
        function() {
            $(this).remove();
        });
    };
    toastClass.prototype = {
        show: function(text, class_name) {
            $('body').append('<p class="toast_msg' + (class_name != undefined ? (' ' + class_name) : '') + '">' + text + '</p>');
        },
    };
})();

/* 网络请求 */
function http(url, data, successfn, hide, errorfn, isPOST, ignoreError) {
    data.token = xGame.token;

    var params = {
        //'async' : (async ? async : true),
        'type': 'post',
        //post
        'dataType': 'json',
        'url': url + '&token=' + xGame.token + '&channel=' + channelID + '&lang=' + appLanguage + '&rand=' + (new Date()).getTime() + '' + Math.floor(Math.random(1) * 100),
        'data': data,
        'beforeSend': function(xhr, settings) {
            if (!hide) {
                $('#ajaxloading').show();
            }
        },
        'complete': function(xhr, settings) {
            xhr = null; //回收
            if (!hide) {
                $('#ajaxloading').hide();
            }
        },
        'timeout': 10000,
        'success': function(data) {
            if (data == '403') { //token 失效
                //xGame.toast.show("您已经失去登录状态，请重新登录！");
                /* 核心定时器的处理 */
                if (timer) {
                    timer.stop();
                    timer = null;
                }
                /* token的处理 */
                xGame.xsRemove('token');
                xGame.xsRemove('addr');
                xGame.xsRemove('guide');
                xGame.xsRemove('guidenow');
                xGame.initLogin();
                if (typeof gAudio != 'undefined' && gAudio != null) {
                    gAudio.stopMusic();
                }

                guide.hide();
                $('#worldloading').hide();

                xGame.confirm.show(lang('notice'), lang('base_badtoken'), 1,
                function() {
                    xGame.confirm.hide();

                    if (Platform.id == 'pc') {
                        window.location.href = Platform.loginurl;
                    } else {
                        window.location.hash = '';
                        xGame.xsRemove('temptoken');
                        setTimeout(function() {
                            window.location.reload();
                        }, 100);
                    }
                    return false;
                });

            } else if (typeof(data) == 'String' && data.indexOf('403-') != -1) { //封号
                var forbid_days = parseInt(data.substring(4));
                xGame.toast.show(lang('account_forbid', [(forbid_days > 0 ? forbid_days + lang('day') : lang('forbid_forever'))]));
                if (timer) {
                    timer.stop();
                    timer = null;
                }
                xGame.xsRemove('token');
                xGame.xsRemove('addr');
                xGame.xsRemove('guide');
                xGame.xsRemove('guidenow');
                xGame.initLogin();
                if (typeof gAudio != 'undefined' && gAudio != null) {
                    gAudio.stopMusic();
                }
                setTimeout(function() {
                    window.location.hash = '';
                    window.location.reload();
                },
                3000);
            } else if (successfn) {
                successfn(data);
            }
        },
        'error': function(xhr, textStatus) {
            
            xGame && xGame.unlock(); //解锁
            console.log('http error:' + textStatus);
            
            if (errorfn != undefined && errorfn != null) {
                errorfn();
                errorfn = null;
                guide.hide();

                // 引导中断
                if (xGame.guide) {
                    xGame.confirm.show(lang('notice'), lang('internet_error'), 1,
                    function() {
                        xGame.confirm.hide();

                        if (Platform.id == 'pc') {
                            window.location.href = Platform.loginurl;
                        } else {
                            window.location.hash = '';
                            xGame.xsRemove('temptoken');
                            setTimeout(function() {
                                window.location.reload();
                            }, 100);
                        }
                        return false;
                    });
                }

                if (ignoreError == true) {
                    return false;
                } else {
                    $('#ajaxloading').hide();
                    $('#worldloading').hide();
                    return;
                }
            } else if(typeof ignoreError == 'undefined' || ignoreError == false) {
                //debug
                if (textStatus == 'parsererror') {
                    //xGame.toast.show('后端代码报错');
                    sendError(url, data, xhr.responseText);
                    xGame.toast.show(lang('server_error_msg'));
                } else if (textStatus == 'timeout' || textStatus == 'abort') {
                    xGame.toast.show(lang('timeout_error_msg'));
                } else {
                    xGame.toast.show(lang('internet_error'));
                }
                xGame.closePops();
                guide.hide();

                if (xGame.guide) {
                    xGame.confirm.show(lang('notice'), lang('internet_error'), 1,
                    function() {
                        xGame.confirm.hide();

                        if (Platform.id == 'pc') {
                            window.location.href = Platform.loginurl;
                        } else {
                            window.location.hash = '';
                            xGame.xsRemove('temptoken');
                            setTimeout(function() {
                                window.location.reload();
                            }, 100);
                        }
                        return false;
                    });
                } else {
                    xGame.loadWorld('city');
                }
                //2013-9-11 网络出错时如果有引导特殊处理下。
                // if (xGame.guide == true) {
                //     xGame.guideSthShow();
                //     xGame.guide = false;
                //     xGame.xsRemove('guide');
                //     xGame.xsRemove('guidenow');
                //     //2013-8-22 关卡bug修复
                //     xGame.xsRemove(player.info.uid + 'guide_hide_page', true);
                // }
                
                $('#ajaxloading').hide();
                $('#worldloading').hide();
                return;
            }
            
            xhr = null;
        },
    };
    return $.ajax(params);

}
/* 大界面的网络请求（带有大弹窗） */
function loadContents(data, successfn, hide, errorfn, ignoreError) {
    var URL = xGame.API;

    if(data && data.c) {
        URL += '&c=' + data.c + '&';
        delete data.c;
    }

    if(data && data.m) {
        URL += '&m=' + data.m + '&';
        delete data.m;
    }

    if(data && data.d) {
        URL += '&d=' + data.d + '&';
        delete data.d;
    }

    if(player && player.info && player.info.uid) {//所有请求带上UID
        URL += '&token_uid=' + player.info.uid;
    }

    http(URL, data, successfn, hide, errorfn, null, ignoreError);
}

if(typeof hideAddressBar == 'undefined') {//兼容
	var hideAddressBar = function() {};
}

function iospayCheck() {
    var receipt = xGame.xsGet('pay_receipt');
    var prepay_id = xGame.xsGet('pay_prepay_id');

    if (!receipt) {
        //console.log('木有订单信息，不操作！');
    }
    var successFunc = function(res) {
        if (res.status == 1) {
            xGame.toast.show(lang('pay_success_msg'));
            player.reloadInfo();

            //清理支付数据
            xGame.xsRemove('pay_receipt');
            xGame.xsRemove('pay_prepay_id');
            //重置错误次数
            if (xGame.payErrorNums > 0) {
                xGame.payErrorNums = 0;
            }
            receipt = null;
            prepay_id = null;
            successFunc = null;

            if(typeof cordova != 'undefined' && xGame.iosLastPay != 0) {

                //AppCpa 支付事件
                if(typeof isAppstore != 'undefined' && isAppstore == true && window.plugins.AppCpaManager) {

                    var udid = toString(xGame.udid);
                    var iosLastId = toString(xGame.iosLastId);

                    window.plugins.AppCpaManager.sendEvent(null, null, [
                        "Purchase",
                        udid,
                        xGame.iosLastPay,
                        iosLastId,
                    ]);
                }

                xGame.iosLastPay = 0;
                xGame.iosLastId = '';
            }

        } else if (res.status == -1) {
            xGame.toast.show(lang('pay_fail_msg1'));
            //清理支付数据
            xGame.xsRemove('pay_receipt');
            xGame.xsRemove('pay_prepay_id');
        } else if (res.status == -2) {
            xGame.toast.show(lang('pay_fail_msg2'));
            //清理支付数据
            xGame.xsRemove('pay_receipt');
            xGame.xsRemove('pay_prepay_id');
        } else if (res.status == -3) {
            xGame.toast.show(lang('pay_fail_msg3')); 
            //清理支付数据
            xGame.xsRemove('pay_receipt');
            xGame.xsRemove('pay_prepay_id');
        } else {//由于其他无法预料的问题，一但错误，再次到服务端验证
            if(errorFunc) {
                errorFunc();
            }
        }

    };
    var errorFunc = function() {
        if (xGame.payErrorNums < 3) { //重试3次
            xGame.payErrorNums++;
            iospayCheck(); //调用自己
        } else {
            xGame.confirm.show(lang('pay_fail_msg'), lang('pay_fail_msg4'), 1,
            function() {
                xGame.confirm.hide();
            });
        }
    };
    http(xGame.API + '&c=ios&m=verify', {
        'receipt': receipt,
        'prepay_id': prepay_id,
        'devicetoken': xGame.devicetoken,
        'macaddr': xGame.macaddr,
        'adid': xGame.adid,
    },
    successFunc, false, errorFunc, true);

}

function getSLeft(left){
    if (isIPhone5) { return Math.ceil(left * 1136 / 1024); }
    /* 2026-10-16 宽布局通铺：主线/势力/关卡/国战/寻宝地图画布已按 --hj-vw 铺满
       （game.css html.hj-wide #map_wrap .map_page 宽 + 背景 100% 100% 拉伸），
       x 坐标按 vw/1024 同比例缩放，保证怪物/关卡/势力热区与背景图对齐；
       top 纵向不缩放（getSTop 保持原值）。无 --hj-vw（非 fit 环境/旧缓存）
       维持 1024 设计坐标，行为与原版一致 */
    var vw = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--hj-vw'));
    if (vw > 0) { return Math.ceil(left * vw / 1024); }
    return left;
}

function getSTop(top){
    return isIPhone5 ? Math.ceil(top * 852 / 768) : top;
}

/* step */
if(typeof defaultExceptionHandler == 'undefined') {
    var defaultExceptionHandler = function() { };
}
if(typeof step == 'undefined') {
    var step = function() {
        var pending = [];
        var current = null;
        var currentCount = 0;
        var errorHandler = defaultExceptionHandler;

        var _isArray = function(obj) {
            return toString.call(obj) == '[object Array]';
        };
        var execute = function() {
            if (pending.length > 0) {
                current = pending.shift();
                currentCount = current.length;
                dispatch();
            }
        };

        var next = function() {
            if (arguments.length == 0) {
                if (--currentCount == 0) {
                    execute();
                }
            } else {
                errorHandler(arguments[0], next);
            }
        };

        var dispatch = function () {
            var count = current.length;
            current.forEach(function(callback) {
                setTimeout(function() {
                    try {
                        callback(next);
                    } catch (error) {
                        errorHandler(error, next);
                    }
                }, 0);
                /*
                process.nextTick(function() {
                    try {
                        callback(next);
                    } catch (error) {
                        errorHandler(error, next);
                    }
                });*/
            });
        };

        var chain = function() {
            if (current) {
                // TODO: Throw exception here, in this case the chain has run.
                return;
            }

            var arglist = Array.prototype.slice.call(arguments);
            var funcs = [];
            var array = null;
            var object = null;

            var checkState = function() {
                if (array || object) {
                    throw new TypeError();
                }
            }

            arglist.forEach(function(i) {
                if (typeof i === 'function') {
                    if (array) {
                        array.forEach(function(j, idx) {
                            funcs.push(function(next) {
                                i(next, j, idx);
                            });
                        });
                        array = null;
                    } else if (object) {
                        for(var p in object) {
                            (function(p, v) {
                                funcs.push(function(next) {
                                    i(next, p, v);
                                });
                            })(p, object[p]);
                        }
                        object = null;
                    } else {
                        funcs.push(i);
                    }
                } else if (_isArray(i)) {
                    checkState();
                    array = i;
                } else if (typeof i === 'object') {
                    checkState();
                    object = i;
                } else {
                    throw new TypeError();
                }
            });
            checkState();
            pending.push(funcs);

            return arguments.callee;
        };

        chain.run = function(errHandler) {
            if (current == null) {
                if (errHandler)
                    errorHandler = errHandler;
                execute();
            }
        };

        return chain.apply(null, arguments);
    };
}

//发送错误报告
function sendError(url, data, result){
    if (islocal) {
        xGame.toast.show('后端出现异常');
    } else {
        var obj = {};
        obj.domain = xGame.server_domain;
        obj.serverid = xGame.server_prefix;
        obj.url = url;
        obj.data = JSON.stringify(data);
        obj.result = result;
        obj.uid = player.info.uid;
        obj.nickname = player.info.nickname;
        console.log(obj);

        $.ajax({
            'type': 'post',
            //post
            'dataType': 'json',
            'url': 'http://error.zhanchengkeji.com/index.php?c=report&m=send',
            'data': obj,
            'timeout': 10000,
            'success': function(data) {
                
            },
            'error': function(xhr, status) {
                console.log(xhr);
                console.log(status);
            }
        });
    }
} 

