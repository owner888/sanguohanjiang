/* 登录 */
(function() {
    loginWorldClass = function() {
        this.username = '';
        this.password = '';
        this.nickname = '';
        this.mobile = '';
        this.acountlist = null;
        this.movieclip;//暂时只有日文版独有的
        this.myScroller = null;
    };
    loginWorldClass.prototype = {
        init: function(serverlistdata) {//serverlistdata: from roleworld

            $('#world').append($.template('loginworld_tmpl'));
            $('#worldloading').hide();
            $('#version').html('ver: ' + localStorage.getItem('sg_ver') + '[' + channelID + ']');

            if (serverlistdata) {
                this.serverlistRender(serverlistdata);
            } else if(Platform.id == '91') {
                this.login91Acount();
            } else if(Platform.id == 'pp') {
                this.loginPPAcount();
            } else if(Platform.id == 'dj') {
                this.loginDJAcount();
            } else if(Platform.id == 'djn') {
                this.loginDJNAcount();
            } else if(Platform.id == 'kuaiyong') {
                this.loginKYAcount();
            } else {
                xGame.xsRemove('tryuid');
                xGame.xsRemove('trykey');
                this.loginRender();
                this.initAcountList();
            } 

            setTimeout(function() {
                if (gAudio) {
                    gAudio.playbgm('city_bgm');
                } 
            }, 800);
            
            if (_inArray([106, 152, 153, 154, 155], channelID)) {
                this.fireStart();
            }
        },

        checkFirstTime: function() {
            var lastaccount = xGame.xsGet('lastaccountsaved');
            if(lastaccount == null) {
                var tmp = xGame.xsGet('oldman');
                if(tmp == null) {
                    return true;
                }
            }
            return false;
        },

        passFirstTime: function() {
            xGame.xsSet('oldman', 'solo');
        },

        viewPop: function(pop) {
            $('#loginworld_page').attr({
                'class': 'show_' + pop
            });
            // 弹层垂直居中 + 过高时缩小（原版按 640 高屏设计，手机横屏 ~444 会裁底）。
            // 用 margin-top 负半高 + zoom，不用 transform（老 Zepto/老 Safari 行为不可控）
            if (pop != 'serverlist_wrap') { // 选服层自带 margin-top:-260px 居中
                var page = $('#loginworld_page');
                var box = page.find('#' + pop);
                if (box.length) {
                    var ph = page.height() || 0;
                    var bh = box[0].offsetHeight || 0;
                    var z = (bh > 0 && bh > ph - 20) ? Math.max(.6, (ph - 20) / bh) : 1;
                    box.css('zoom', z);
                    box.css('margin-top', -(bh * z / 2) + 'px');
                }
            }
            /*
            $('.box_wrap').each(function() {
                if(this.id == pop) {
                    this.style.display = 'block';
                } else {
                    this.style.display = 'none';
                }
            });
            */
        },

        loginRender: function() {
            var that = this;
            that.viewPop('login_wrap');

            var dom_page = $('#login_wrap');

            dom_page.find('#acountlist_btn').unbind('click').bind('click',
            function() {
                $('#acountlist_wrap').toggle();
                //2013年7月12号更新，点击外面可以隐藏下拉,并且阻止了下拉和删除按钮
                $('#loginworld_page').one('touchend', function(e) {
                    //console.log(e);
                    if (e.target.id == 'acountlist_btn' || e.target.className ==  'd_btn') {
                        //console.log('阻止');
                        return false;
                    }
                    $('#acountlist_wrap').css('display', 'none');
                })
            });
            dom_page.find('.regbtn').unbind('touchend').bind('touchend',
            function(e) {
                loginWorld.regRender();
                e.preventDefault();
                e.stopPropagation();
            });
            if (navigator && navigator.userAgent && navigator.userAgent.indexOf('5_0_1') != -1) { //ios5.0.1用户，不显示试玩
                dom_page.find('.try_btn').unbind('touchend').bind('touchend',
                function(e) {
                    xGame.confirm.show(lang('help'), lang('keft_qq'), 1,
                    function() {
                        xGame.confirm.hide();
                    });
                    e.preventDefault();
                    e.stopPropagation();
                }).html(lang('help'));
            } else {
                dom_page.find('.try_btn').unbind('touchend').bind('touchend',
                function(e) {//试玩增加确认
                    e.preventDefault();
                    e.stopPropagation();
                    xGame.confirm.show(lang('quick_start_title'), lang('quick_start_content'), 1,
                    function() {
                        if(xGame.checkLock()) {
                            return false;
                        }
                        setTimeout(function() {
                            loginWorld.tryRender();
                            xGame.confirm.hide();
                        }, 350);
                    },
                    null, lang('quick_start_go_game'), null, true);
                });
            }
            dom_page.find('.loginsubmitbtn').unbind('touchend').bind('touchend',
            function(e) { //登录
                e.preventDefault();
                e.stopPropagation();
                if (xGame.checkLock()) {
                    return false;
                }

                loginWorld.username = $('#login_username').val();
                loginWorld.password = $('#login_password').val();
                if (loginWorld.username.length <= 0 || loginWorld.password.length <= 0) {
                    xGame.toast.show(lang('account_pwd_type_complete'));
                    xGame.unlock();
                    return false;
                }
                var successFunc = function(res) {
                    if (res.status == 1) {
                        loginWorld.serverlistRender(res);
                        xGame.saveAccount(loginWorld.username, loginWorld.password);
                    } else {
                        switch (res.status) {
                            case -1:
                                xGame.toast.show(lang('member_not_exist'));
                                break;
                            case -2:
                                xGame.toast.show(lang('account_psw_error'));
                                break;
                            case -3:
                                xGame.toast.show(lang('account_pwd_no_null'));
                                break;
                            default:
                                xGame.toast.show(lang('undefined_error'));
                                break;
                        }
                    }
                    xGame.unlock();
                };
                http(UCAPI + '&c=user&m=login&', {
                    'u': loginWorld.username,
                    'p': loginWorld.password,
                    'mac': xGame.macaddr,
                    'adid': xGame.adid,
                    'devicetoken': xGame.devicetoken,
                    'channel': channelID
                },
                successFunc, false, loginWorld.tempLoginFail);
                successFunc = null;

                //AppCpa 登录事件
                if(typeof cordova != 'undefined' && typeof isAppstore != 'undefined' && isAppstore == true && window.plugins.AppCpaManager) {
                    window.plugins.AppCpaManager.sendEvent(null, null, [
                        "Login",
                        xGame.udid,
                        "",
                        ""
                    ]);
                }
            });

            //自动输入帐号密码
            var lastaccountsaved = xGame.xsGet('lastaccountsaved');
            if (lastaccountsaved != null && lastaccountsaved != '') {
                arr = lastaccountsaved.split(',');
                if (arr[0] != '' && arr[1] != '') {
                    $('#login_username').val(arr[0]);
                    $('#login_password').val(arr[1]);
                }
                lastaccountsaved = null;
            }

            //针对官网版本，不显示登录注册
            if(Platform.id == 'pc') {
                dom_page.find('.small_btn_wrap').css('visibility', 'hidden');
            }

            dom_page = null;
            that = null;
        },

        regRender: function() {
            var that = this;
            that.viewPop('reg_wrap');

            var dom_page = $('#reg_wrap');

            dom_page.find('.input').addClass('greyword').one('focus',
            function() {
                $(this).removeClass('greyword').val('');
            });
            dom_page.find('.close_btn').unbind('touchend').bind('touchend',
            function(e) {
                loginWorld.loginRender();
                e.preventDefault();
                e.stopPropagation();
            });
            dom_page.find('.regsubmitbtn').unbind('touchend').bind('touchend',
            function(e) { //注册
                e.preventDefault();
                e.stopPropagation();
                if (xGame.checkLock()) {
                    return false;
                }

                var successFunc = function(res) {
                    if (res.status == 1) {
                        xGame.saveAccount(loginWorld.username, loginWorld.password);
                        loginWorld.serverlistRender(res, loginWorld.checkFirstTime());
                        /* HasOfferTrack */
                        if(typeof cordova != 'undefined' && (channelID == 3 || channelID == 100)) {
                            if(window.plugins.HasOfferTrack) {
                                window.plugins.HasOfferTrack.regisiter(null, null, [1]);
                            }
                        }
                    } else if (res.status == -1) {
                        xGame.toast.show(lang('account_mate_error'));
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('psw_mate_error'));
                    } else if (res.status == -3) {
                        xGame.toast.show(lang('account_is_exist'));
                    } else if (res.status == -4) {
                        xGame.toast.show(lang('account_pwd_no_null'));
                    }
                    successFunc = null;
                    xGame.unlock();
                };
                loginWorld.username = $('#reg_username').val();
                loginWorld.password = $('#reg_password').val();
                loginWorld.mobile = $('#reg_mobile').val();
                if (loginWorld.username == lang('4_16')) {
                    loginWorld.username = '';
                }
                if (loginWorld.password == lang('6_16')) {
                    loginWorld.password = '';
                }
                if (loginWorld.mobile == lang('only_find_psw')) {
                    loginWorld.mobile = '';
                }
                if (helper.checkUserName(loginWorld.username) && helper.checkPassWord(loginWorld.password) && helper.checkTel(loginWorld.mobile)) {
                    http(UCAPI + '&c=register&m=reg&', {
                        'u': loginWorld.username,
                        'p': loginWorld.password,
                        'mobile': loginWorld.mobile,
                        'mac': xGame.macaddr,
                        'adid': xGame.adid,
                        'devicetoken': xGame.devicetoken,
                        'channel': channelID
                    },
                    successFunc);
                } else {
                    xGame.unlock();
                }

                //AppCpa 注册事件
                if(typeof cordova != 'undefined' && typeof isAppstore != 'undefined' && isAppstore == true && window.plugins.AppCpaManager) {
                    window.plugins.AppCpaManager.sendEvent(null, null, [
                        "Register",
                        xGame.udid,
                        "",
                        ""
                    ]);
                }
            });

            dom_page = null;
            that = null;
        },

        serverlistRender: function(json, newcommer) {
            var that = this;

            xGame.xsSet('login_servers_data', JSON.stringify(json), true);

            var dom_page = $('#serverlist_wrap');

            var html = '';
            var html2 = '';
            var str = '';
            var lastStr = '';
            if (json.status == 1) {
                var lastserver = null;
                var servers = 0;
                var firstserver = null;

                //新服预告
                for (var i in json.newserver) {
                    var server = json.newserver[i];
                    str = '<span class="preview">' + lang('server_preview_ready') + '</span>';
                    html += '<li class="btn server_preview" name="' + server.name + '" msg="' + server.message + '" >' + server.name + ' ' + str + '</li>';
                    servers++;
                }
                //已开放服务器
                for (var i in json.serverlist) {
                    if(firstserver == null) {
                        firstserver = json.serverlist[i];
                    }
                    var server = json.serverlist[i];
                    if (server.status == 1) {
                        str = '<span class="new">' + lang('new_server') + '</span>';
                    } else if (server.status == 2) {
                        str = '<span class="hot">' + lang('hot_server') + '</span>';
                    } else if (server.status == 3) {
                        str = '<span class="stop">' + lang('closed_server') + '</span>';
                    } else {
                        str = '';
                    }
                    html += '<li class="btn server" addr="' + server.addr + '" server="' + (server.server || server.id || 1) + '" name="' + server.name + '" status="' + server.status + '" msg="' + server.message + '" >' + server.name + ' ' + str + '</li>';
                    if (server.selected == 1) {
                        lastserver = server;
                        lastStr = str;
                    }
                    servers++;
                }

                //针对某些渠道，全部导入到新服的处理(目前台湾、韩文)
                if (_inArray([3, 100], channelID) && newcommer == true && firstserver) {
                    console.log(firstserver);
                    // 切服只改服号：参数模式下 API 仍走当前源，仅多带一个 server=N 由反代分流
                    if (typeof hjSetServer == 'function') {
                        hjSetServer(firstserver.server || firstserver.id || 1);
                    } else {
                        xGame.setServer(firstserver.server || firstserver.id || 1);
                    }
                    xGame.xsSet('addr', location.host);
                    xGame.xsSet('server_name', firstserver.name);

                    var successFunc = function(res) {
                        xGame.unlock();

                        if (res.status == 1) {
                            loginWorld.logindoneRender(res.token);
                            loginWorld.passFirstTime();
                        } else {
                            if (res.status == 2) {
                                xGame.toast.show(lang('server_updating') + res.time);
                            } else {
                                xGame.toast.show(lang('undefined_error'));
                            }
                            setTimeout(function() {
                                //location.reload();
                            }, 2000);
                        }
                    };
                    if (xGame.xsExist('tryuid')) {//处理是否试玩登录
                        loadContents({
                            'c': 'login',
                            'm': 'guest',
                            'mac': xGame.macaddr,
                            'adid': xGame.adid,
                            'devicetoken': xGame.devicetoken,
                            'uid': xGame.xsGet('tryuid'),
                            'guestkey': xGame.xsGet('trykey'),
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    } else {
                        loadContents({
                            'c': 'login',
                            'm': 'user',
                            'mac': xGame.macaddr,
                            'adid': xGame.adid,
                            'devicetoken': xGame.devicetoken,
                            'u': loginWorld.username,
                            'p': loginWorld.password,
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                        successFunc = null;
                    }
                    return;
                }

                if (lastserver) {
                    html2 += '<div class="lastlogin_wrap">' + lastserver.name + lastStr + '</div><div class="lastlogin_btn btn server lastserver" name="' + lastserver.name + '" addr="' + lastserver.addr + '" server="' + (lastserver.server || lastserver.id || 1) + '" status="' + lastserver.status + '" msg="' + lastserver.message + '">' + lang('enter_right_now') + '</div>';
                } else {
                    html2 += '<div class="lastlogin_wrap">' + lang('no') + '</div>';
                }
                str = lastStr = null;

                /* 新服预告 */
                var previewServerRender = function() {
                    //toast
                    xGame.toast.show($(this).attr('msg'));
                    return false;
                    //弹窗
                    /*
                    xGame.confirm.show(lang('server_preview_title'), $(this).attr('msg'), 1,
                    function() {
                        xGame.confirm.hide();
                        xGame.unlock();
                    });
                    
                    return false;
                    */
                };
                dom_page.find('.serverlist').undelegate('.server_preview', 'click').delegate('.server_preview', 'click', previewServerRender);
                previewServerRender = null;

                /* 已开放服务器 */
                var selectServerRender = function() {
                    if (xGame.checkLock()) {
                        return false;
                    }
                    // 地址经 safeAddr 修正：反代未透传域名时服务端只能给出 127.0.0.1，
                    // 该地址对客户端不可达，此时回退为当前页面的 host（登录页与 API 同源）。
                    // 注意：addr 已不再用于寻址，仅作兼容/展示——真正的服标识是 server=N。
                    var addr = xGame.safeAddr($(this).attr('addr')); //s1.game.hanjiangsanguo.com
                    if (addr == null || addr == '') {
                        return;
                    }
                    //维护判断
                    if ($(this).attr('status') == 3) {
                        //弹窗
                        xGame.confirm.show(lang('maintenance_tips'), $(this).attr('msg'), 1,
                        function() {
                            xGame.confirm.hide();
                            xGame.unlock();
                            window.location.reload();//直接刷新页面
                        });
                        return false;
                    }

                    // 切服只改服号：参数模式下 API 仍走当前源，仅多带一个 server=N 由反代分流，
                    // 不需要换地址/换端口/重载页面（hjSetServer 会同步 UCAPI 与 xGame.API）
                    if (typeof hjSetServer == 'function') {
                        hjSetServer($(this).attr('server') || 1);
                    } else {
                        xGame.setServer($(this).attr('server') || 1);
                    }
                    xGame.xsSet('addr', addr);
                    xGame.xsSet('server_name', $(this).attr('name'));

                    var successFunc = function(res) {
                        xGame.unlock();

                        if (res.status == 1) {
                            if(Platform.id == 'pc') {//针对官网版本特殊处理
                                if('vip' in res && res.vip < 5 && location.hash == '') {
                                    xGame.confirm.show(lang('notice'), lang('website_vip_limit'), 1,
                                    function() {
                                        xGame.confirm.hide();
                                    });
                                    return;
                                }
                            }
                            loginWorld.logindoneRender(res.token);
                            if(Platform.id == 'h5' && res.temptoken) {
                                window.location.hash = '#!/temptoken=' + res.temptoken;
                            }
                        } else if (res.status == -1) {
                            xGame.toast.show(lang('account_psw_error'));
                        } else if (res.status == -2) {
                            xGame.toast.show(lang('create_account_error'));
                        } else if (res.status == -3) {
                            xGame.toast.show(lang('create_token_error'));
                        } else if (res.status == 2) {
                            xGame.toast.show(lang('server_updating') + res.time);
                        }
                    };

                    //处理平台登录
                    if (Platform.id == '91') {
                        loadContents({
                            'c': 'login',
                            'm': 'user91',
                            'uin': Platform.userinfo.ndLoginUin,
                            'sessionid': Platform.userinfo.ndSessionId,
                            'mac': xGame.macaddr,
                            'adid': xGame.adid,
                            'devicetoken': xGame.devicetoken,
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    } else if (Platform.id == 'pp') {
                        loadContents({
                            'c': 'login',
                            'm': 'userpp',
                            'ppuid': Platform.userinfo.ppuid,
                            'ppsessionid': Platform.userinfo.ppsessionid,
                            'mac': xGame.macaddr,
                            'adid': xGame.adid,
                            'devicetoken': xGame.devicetoken,
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    } else if (Platform.id == 'dj') {
                        loadContents({
                            'c': 'login',
                            'm': 'userdj',
                            'uin': Platform.userinfo.djuid,
                            'sessionid': Platform.userinfo.sessionid,
                            'mac': xGame.macaddr,
                            'adid': xGame.adid,
                            'devicetoken': xGame.devicetoken,
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    } else if (Platform.id == 'djn') {
                        loadContents({
                            'c': 'login',
                            'm': 'userdjn',
                            'uin': Platform.userinfo.ndLoginUin,
                            'sessionid': Platform.userinfo.ndSessionId,
                            'mac': xGame.macaddr,
                            'adid': xGame.adid,
                            'devicetoken': xGame.devicetoken,
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    } else if (Platform.id == 'kuaiyong') {
                        var loginParams = null;
                        if(Platform.accountLogin == true) {//账号登陆模式
                            loginParams = {
                                'c': 'login',
                                'm': 'user',
                                'mac': xGame.macaddr,
                                'devicetoken': xGame.devicetoken,
                                'adid': xGame.adid,
                                'u': loginWorld.username,
                                'p': loginWorld.password,
                                'channel': channelID
                            };
                        } else {
                            loginParams = {
                                'c': 'login',
                                'm': 'userkuaiyong',
                                'uin': Platform.userinfo.uin,
                                'tokenkey': Platform.userinfo.tokenkey,
                                'mac': xGame.macaddr,
                                'adid': xGame.adid,
                                'devicetoken': xGame.devicetoken,
                                'channel': channelID
                            };
                        }

                        loadContents(loginParams, successFunc, false, loginWorld.tempLoginFail);

                    } else if (xGame.xsExist('tryuid')) {//处理是否试玩登录
                        loadContents({
                            'c': 'login',
                            'm': 'guest',
                            'mac': xGame.macaddr,
                            'devicetoken': xGame.devicetoken,
                            'adid': xGame.adid,
                            'uid': xGame.xsGet('tryuid'),
                            'guestkey': xGame.xsGet('trykey'),
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    } else {
                        loadContents({
                            'c': 'login',
                            'm': 'user',
                            'mac': xGame.macaddr,
                            'devicetoken': xGame.devicetoken,
                            'adid': xGame.adid,
                            'u': loginWorld.username,
                            'p': loginWorld.password,
                            'channel': channelID
                        },
                        successFunc, false, loginWorld.tempLoginFail);
                    }
                };
                dom_page.find('.serverlist').undelegate('.server', 'click').delegate('.server', 'click', selectServerRender);

                /* 计算高度 */
                if(typeof myScroller != 'undefined') {
                    myScroller.destroy();
                }
                if ((servers <= 8 && !lastserver) || (servers <= 7 && lastserver)) {
                    dom_page.find('#serverlist').html(html2 + '<div class="scroller" id="servers_scroller" >' + html + '</div>');
                    html = html2 = null;
                } else {
                    html = '<div class="scroller" id="servers_scroller" ><div class="scroller_root"><ul class="scroller_content" id="activitypop_list">' + html + '</ul></div></div>';
                    dom_page.find('#serverlist').html(html2 + html);
                    that.myScroller = new iScroll('servers_scroller', {
                        snap: 'li',
                        bounce: false,
                        hScrollbar: false,
                        vScrollbar: true
                    });
                    setTimeout(function() {
                        that.myScroller.refresh();
                    },
                    300);
                }

                selectServerRender = null;
                lastserver = null;
                html = null;
                json = null;

                that.viewPop('serverlist_wrap');

            } else {
                xGame.toast.show(lang('server_closing'));
            }

        },

        logindoneRender: function(token) {

            guide.createFullmask();//防止连续登录服务器

            xGame.xsSet('token', token);
            xGame.initLogin();

            var successFunc = function() {

                guide.cleanFullmask();

                if (player.info.uid == 0) {
                    xGame.toast.show(lang('get_user_info_fail'));
                    return;
                }
                /* 本地通知系统，清理训练位通知 */
                if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {
                    window.plugins.localNotification.cancel('practice');
                }
                loginWorld.fireEnd();
                loginWorld.clear();
                if (player.info.gender == 0) { //选角色
                    xGame.loadWorld('role');
                } else {
                    var callback = function () {
                        xGame.xsRemove('serverlist', true);
                        xGame.checkMenuOpen();

                        if (player.info.level && player.info.level >= 20) {
                            xGame.loadWorld('city');
                        } else {
                            guide.loadGuide();
                        }
                        xGame.updateCd('updatePlayer', null);
                        xGame.updateDeviceToken();
                        xGame.updateCd('chat', 0);

                        if (player.info.activities == 1) { //弹活动预告
                            xGame.showpop('activities');
                        }
                        
                        //这个函数只在登录的时候会被调用
                        if(typeof Platform != 'undefined' && Platform.id == 'h5') {
                            //不在引导中，每次刷新都显示一次
                            if (xGame.guide == false) {
                                miscpop.showAddToScreen();
                            }
                            //提示去app store
                            miscpop.showStandaloneTips();
                        }

                        xGame.initLocalNotication();
                        callback = null;
                    };

                    loginWorld.showMar(callback);
                }
                successFunc = null;

            };

            player.reloadInfo(successFunc, false);

        },

        showMar: function(callback) {
            
            var render = function (res) {
                
                if (parseInt(res.status) == 1 && res.king.chapter != null) {

                    $('#loginworld_page').hide();
                    var pop = xGame.loadPopTmpl('showmar');
                    var info = pop.find('#mar_info');
                    pop.find('.content').css('background', 'url(' + staticUrl + 'img/pop/showmarpop_hero_'+ res.king.chapter +'.jpg) 50% 50% no-repeat');
                    info.children('.emperor').html(res.king.chaptername);
                    info.find('.player_name').html(res.king.nickname);
                    info.find('.server_name').html('(' + res.king.servername + ')');
                    info = null;

                    pop.find('.bar').unbind().bind('webkitAnimationEnd', function (e) {
                        $(this).unbind();
                        pop.remove();
                        pop = null;
                        callback();
                    });
                } else {
                    callback();
                }
            };

            loadContents({
                'c': 'god',
                'm': 'loading'
            }, render, false, callback);
        },

        /* 试玩 */
        tryRender: function() {
            var successFunc = function(res) {
                xGame.unlock();

                if (res.status == 1) { //试玩成功，保存试玩uid
                    xGame.xsSet('tryuid', res.uid);
                    xGame.xsSet('trykey', res.guestkey);
                    xGame.xsRemove('lastaccountsaved');
                    loginWorld.serverlistRender(res, loginWorld.checkFirstTime());
                    /* HasOfferTrack */
                    if(typeof cordova != 'undefined' && (channelID == 3 || channelID == 100)) {
                        if(window.plugins.HasOfferTrack) {
                            window.plugins.HasOfferTrack.regisiter(null, null, [1]);
                        }
                    }
                } else {
                    xGame.toast.show(lang('try_fail'));
                }
            };
            http(UCAPI + '&c=register&m=guest&', {
                'mac': xGame.macaddr,
                'adid': xGame.adid,
                'devicetoken': xGame.devicetoken,
                'channel': channelID
            },
            successFunc, false, loginWorld.tempLoginFail);
            successFunc = null;
            //AppCpa 注册事件
            if(typeof cordova != 'undefined' && typeof isAppstore != 'undefined' && isAppstore == true && window.plugins.AppCpaManager) {
                window.plugins.AppCpaManager.sendEvent(null, null, [
                    "Register",
                    xGame.udid,
                    "",
                    ""
                ]);
            }

        },

        initAcountList: function() {

            $('#acountlist_wrap').remove();

            var acountlist = xGame.xsGet('acountlist');
            if (acountlist) {
                this.acountlist = JSON.parse(acountlist);
                var html = '';
                html += '<ul id="acountlist_wrap">';
                for (var i = 0; i < this.acountlist.length; i++) {
                    html += '<li id="acount' + i + '" u="' + this.acountlist[i].u + '" p="' + this.acountlist[i].p + '" k="' + i + '"><span>' + this.acountlist[i].u + '</span><button class="d_btn"></button></li>';
                }
                html += '</ul>';
                var acountlist_dom = $(html);
                acountlist_dom.undelegate('span', 'touchend').delegate('span', 'touchend',
                function(e) {
                    var u = $(this).parent().attr('u');
                    var p = $(this).parent().attr('p');
                    var login_wrap = $('#login_wrap');
                    login_wrap.find('#login_username').val(u);
                    login_wrap.find('#login_password').val(p);
                    login_wrap.find('#acountlist_wrap').hide();
                    login_wrap = null;
                    e.preventDefault();e.stopPropagation();
                });
                acountlist_dom.undelegate('button', 'touchend').delegate('button', 'touchend',
                function(e) {
                    var k = $(this).parent().attr('k');
                    loginWorld.deleteAcount(k);
                    e.preventDefault();e.stopPropagation();
                });
            } else {
                var html = '';
                html += '<ul id="acountlist_wrap">';
                html += '<li><span>' + lang('you_have_not_save_account') + '</span></li>';
                html += '</ul>';
                var acountlist_dom = $(html);
                acountlist_dom.unbind('touchend').bind('touchend',
                function() {
                    $('#acountlist_wrap').hide();
                });
            }
            $('#login_wrap').find('.real_wrap').append(acountlist_dom);
            acountlist_dom = null;

            //点击其他区域隐藏帐号列表
            $('#loginworld_page').unbind('touchend').bind('touchend',
            function(event) {
                if (event.target && event.target.id == 'loginworld_page') {
                    $('#acountlist_wrap').hide();
                }
            });

        },

        deleteAcount: function(k) {
            if (k && this.acountlist) {
                this.acountlist.splice(parseInt(k), 1);
                xGame.xsSet('acountlist', JSON.stringify(this.acountlist));
                $('#acount' + k).remove();
            }
        },

        /* 登录91帐号 */
        login91Acount: function() {
            var successFunc = function(res) {
                xGame.unlock();
                if(res.status == 1) {//登录成功
                    loginWorld.serverlistRender(res);
                } else {
                    xGame.toast.show(lang('get_user_info_fail'));
                    return false;
                }
            };
            http(UCAPI + '&c=user91&m=login_ios&', {
                'uin': Platform.userinfo.ndLoginUin,
                'sessionid': Platform.userinfo.ndSessionId,
                'mac': xGame.macaddr,
                'adid': xGame.adid,
                'devicetoken': xGame.devicetoken,
                'channel': channelID
            },
            successFunc, false, loginWorld.tempLoginFail);
            successFunc = null;
        },

         /* 登录PP帐号 */
        loginPPAcount: function() {
            this.serverlistRender(Platform.userinfo);
        },
        
        /* 登录DJ帐号 */
        loginDJAcount: function() {
            var successFunc = function(res) {
                xGame.unlock();
                if(res.status == 1) {//登录成功
                    loginWorld.serverlistRender(res);
                } else {
                    xGame.toast.show(lang('get_user_info_fail'));
                    return false;
                }
            };
            http(UCAPI + '&c=userdj&m=login_ios&', {
                'uin': Platform.userinfo.djuid,
                'sessionid': Platform.userinfo.sessionid,
                'mac': xGame.macaddr,
                'adid': xGame.adid,
                'devicetoken': xGame.devicetoken,
                'channel': channelID
            },
            successFunc, false, loginWorld.tempLoginFail);
        },

        /* 登录91兼容版帐号 */
        loginDJNAcount: function() {
            var successFunc = function(res) {
                xGame.unlock();
                if(res.status == 1) {//登录成功
                    loginWorld.serverlistRender(res);
                } else {
                    xGame.toast.show(lang('get_user_info_fail'));
                    return false;
                }
            };
            http(UCAPI + '&c=userdjn&m=login&', {
                'uin': Platform.userinfo.ndLoginUin,
                'djuin': Platform.userinfo.djUin,
                'sessionid': Platform.userinfo.ndSessionId,
                'mac': xGame.macaddr,
                'adid': xGame.adid,
                'devicetoken': xGame.devicetoken,
                'channel': channelID,
                'type': 'ios'
            },
            successFunc, false, loginWorld.tempLoginFail);
        },

        /* 登录快用帐号 */
        loginKYAcount: function() {

            if(Platform.accountLogin == true) {//账号登陆模式
                var data = JSON.parse(sessionStorage['kuaiyong_accountlogin']);
                delete sessionStorage['kuaiyong_accountlogin'];

                loginWorld.username = data.username;
                loginWorld.password = data.password;
                loginWorld.serverlistRender(data.res);
                xGame.xsSet('lastaccountsaved', loginWorld.username);
            } else {
                var successFunc = function(res) {
                    xGame.unlock();
                    if(res.status == 1) {//登录成功
                        Platform.userinfo.uin = res.uin;
                        loginWorld.serverlistRender(res);
                    } else {
                        xGame.toast.show(lang('get_user_info_fail'));
                        return false;
                    }
                };
                http(UCAPI + '&c=userkuaiyong&m=login&', {
                    'tokenkey': Platform.userinfo.tokenkey,
                    'mac': xGame.macaddr,
                    'adid': xGame.adid,
                    'devicetoken': xGame.devicetoken,
                    'channel': channelID
                },
                successFunc, false, loginWorld.tempLoginFail);
            }
            
        },

        fireStart: function() {
            if (this.movieclip != null) {
                return false;
            }
            var x,y;
            var obj = $('#loginworld_page');
            
            var now_w = obj.width();
            var now_h = obj.height();
            
            //因为是居中的，只要计算距离中心点的left top 即可
            x = now_w / 2  + 322
            y = now_h / 2 + 51;
            //全屏还需特殊处理下
            
            this.movieclip = new movieClipClass({
                "img": staticUrl + 'img/login/fire.jpg',
                "width": 150,
                "height": 180,
                "x": x,
                "y": y,
                "loop": true,
                'zindex': '1',
                "frames": [{
                    rect: [0, 0],
                    interval: 40
                },
                {
                    rect: [180, 0],
                    interval: 40
                },
                {
                    rect: [360, 0],
                    interval: 40
                },
                {
                    rect: [540, 0],
                    interval: 40
                },
                {
                    rect: [720, 0],
                    interval: 40
                },
                {
                    rect: [0, 180],
                    interval: 40
                },
                {
                    rect: [180, 180],
                    interval: 40
                },
                {
                    rect: [360, 180],
                    interval: 40
                },
                {
                    rect: [540, 180],
                    interval: 40
                },
                {
                    rect: [720, 180],
                    interval: 40
                },
                {
                    rect: [0, 360],
                    interval: 40
                },
                {
                    rect: [180, 360],
                    interval: 40
                },
                {
                    rect: [360, 360],
                    interval: 40
                },
                {
                    rect: [540, 360],
                    interval: 40
                },
                {
                    rect: [720, 360],
                    interval: 40
                },/*加入最后做延迟*/
                {
                    rect: [900, 540],
                    interval: 700
                }]
            });
            this.movieclip.appendTo(obj);
            this.movieclip.play();
            obj = null;
            
        },
        
        fireEnd: function() {
             if (this.movieclip != null) {
                this.movieclip.destory();
                this.movieclip = null;
            }
        },

        tempLoginFail: function() {
            initmsg(loadLangObj['protect_msg2']);
        },

        clear: function() {
            if(this.myScroller) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            $('#world').find('#loginworld_page').remove();
            this.fireEnd();
        },
    };
})();