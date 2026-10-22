/* 设置 */
(function() {
    settingsPopClass = function() {
        this.e;
        this.isshow = false;
        this.a;
    };
    settingsPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                that.initPage(res.status);
            };
            loadContents({
                'c': 'activationcode',
                'm': 'index',
            },
            initRender, true); //加载数据
            initRender = null;
        },

        initPage: function(codestatus) {
            var that = this;

            that.e = xGame.loadPopTmpl('settings');
            that.e.find('.hidebtn').unbind('click touchend').bind('click touchend',
            function(e) {
                that.hide();
                return false;
            });
            
            //debug
            //Platform.id = '91';

            //账户信息 start
            var nickname = 'TRY_USER';
            var servername = xGame.xsGet('server_name');

            if(that.isPlatform('kakao')) {//如果是kakao
                that.e.addClass('kakao');//添加kakao样式
                if(!xGame.xsExist('kakaotry')) {//非试玩帐号，显示昵称和KAKAOID
                    nickname = Platform.userinfo.nickname;
                    that.e.find('#settings_kakaouid').html(Platform.userinfo.user_id);
                }
            } else if(that.isPlatform('91') || that.isPlatform('djn')) {
                nickname = '91通行证';
            } else if(that.isPlatform('pp')) {
                nickname = 'PP账号';
            } else if(that.isPlatform('dj')) {
                nickname = '点金平台';
            } else if(that.isPlatform('kuaiyong') && !Platform.accountLogin) {//快用平台，并且非官方账号登陆
                nickname = '快用平台';
            } else if(xGame.xsGet('lastaccountsaved')) {//正常官方账户
                var lastaccount = xGame.xsGet('lastaccountsaved');
                var _index = lastaccount.indexOf(',');
                if(_index != -1) {
                    nickname = lastaccount.substr(0, _index);
                } else {
                    nickname = lastaccount;
                }
            } else {//试玩未绑定账户
                nickname = lang('unbind_account');
            }
            that.e.find('#settings_name').html(nickname);
            that.e.find('#settings_server').html(servername); 
            //帐号信息 end

            /* 按钮处理与事件绑定 */
            if (xGame.xsGet('tryuid')) {//试玩用户，需要绑定帐号
                var bindpw_btn = that.e.find('#bindpw_btn');
                bindpw_btn.css('display', 'inline-block').unbind('touchend').bind('touchend',
                function() {
                    settingspop.hide();
                    miscpop.bindPopConfirm();
                });
                bindpw_btn = null;
            }
            if (codestatus == 1 && player.info.appreview == 0) {//可以使用兑换码
                var code_btn = that.e.find('#code_btn');
                code_btn.css('display', 'inline-block');
                code_btn.unbind('click touchend').bind('click touchend',
                function() {
                    that.exchange();
                    return false;
                });
                code_btn = null;
            }
            if(that.isPlatform('91') || that.isPlatform('djn')) {
                var plafrom91_btn = that.e.find('#platform91_btn');
                plafrom91_btn.css('display', 'inline-block');
                plafrom91_btn.unbind('touchend').bind('touchend',
                function() {
                    window.plugins.NdComPlatformPlugin.userCenter(function() {}, function() {}, []);
                });
                plafrom91_btn = null;
            }
            if(that.isPlatform('pp')) {
                var plafrompp_btn = that.e.find('#platformpp_btn');
                plafrompp_btn.css('display', 'inline-block');
                plafrompp_btn.unbind('touchend').bind('touchend',
                function() {
                    window.plugins.PPComPlatformPlugin.userCenter(function() {}, function() {}, []);
                });
                plafrompp_btn = null;
            }
            if(that.isPlatform('dj')) {
                var plafromdj_btn = that.e.find('#platformdj_btn');
                plafromdj_btn.css('display', 'inline-block');
                plafromdj_btn.unbind('touchend').bind('touchend',
                function() {
                    window.plugins.DjPlatformPlugin.switchAccount(function() {}, function() {}, []);
                });
                plafromdj_btn = null;
            }
            if(that.isPlatform('kuaiyong') && !Platform.accountLogin) {
                var plafromky_btn = that.e.find('#platformky_btn');
                plafromky_btn.css('display', 'inline-block');
                plafromky_btn.bind('touchend',
                function() {
                    window.plugins.KuaiyongPlatform.showLogin(function() {}, function() {}, []);
                });
                plafromky_btn = null;
            }
            if(that.isPlatform('kakao')) {
                //删除账号（非试玩用户才会显示）
                if(!xGame.xsExist('kakaotry')) {
                    var delete_btn = that.e.find('#delete_btn');
                    delete_btn.css('display', 'inline-block');
                    delete_btn.unbind('touchend').bind('touchend', function() {
                        xGame.confirm.show(lang('notice'), lang('kakaofriendspop_delete_ask'), 2,
                        function() {
                            xGame.confirm.hide();

                            window.plugins.KakaoPlatform.kkOnDeleteMe(null, null, [1]);
                        },
                        function() {
                            xGame.confirm.hide();
                        }, lang('kakaofriendspop_invite_ok'), lang('kakaofriendspop_invite_cancle'));
                    });
                    delete_btn = null;
                }
            }
            if(0 && window.plugins && window.plugins.childBrowser) {//暂时不提供微博
                if(player.info.weibobind == '1') {
                    that.e.find('#weibo_btn').html('已绑定微博');
                } else {
                    that.e.find('#weibo_btn').html('绑定微博');
                    that.e.find('#weibo_btn').attr('class', 'btn pop_btn_middle').unbind('touchend').bind('touchend',
                    function() {
                        that.hide();
                        miscpop.weiboBindPop();
                    });
                }
            }
            
            //退出账号
            that.e.find('#change_btn').unbind('click touchend').bind('click touchend',
            function() {
                if(that.isPlatform('kakao')) {
                    xGame.confirm.show(lang('notice'), lang('kakaofriendspop_change_ask'), 2,
                        function() {
                            if(typeof cordova != 'undefined' && !xGame.xsExist('kakaotry')) {
                                $('#ajaxloading').show();
                                window.plugins.KakaoPlatform.kkOnLogOut(null, null, [1]);
                            } else {
                                xGame.xsRemove('kakaotry');
                                miscpop.exitGame(); 
                            }
                        },
                        function() {
                            xGame.confirm.hide();
                        }, lang('ok'), lang('close'));
                } else {
                    var tryuid = xGame.xsGet('tryuid');
                    if (tryuid) { //试玩的必须先绑定
                        that.hide();
                        xGame.showpop('regreward');
                    } else {
                        miscpop.exitGame();
                    }
                }
            });
            
            // 帮助按钮
            that.e.find('#help_btn').unbind('click touchend').bind('click touchend',
            function() {
                xGame.showpop('helpinfo');
                return false;
            });

            // 声音按钮
            if (gAudio) {
                that.setMusicBtn(gAudio.musicstatus);
                var lastToggleTime = 0;
                that.e.find('#music_btn').unbind('click touchend').bind('click touchend',
                function(e) {
                    var now = Date.now();
                    if (now - lastToggleTime < 250) {
                        return false;
                    }
                    lastToggleTime = now;
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    if (gAudio) {
                        var nextStatus = !gAudio.musicstatus;
                        gAudio.settingmusic(nextStatus);
                        that.setMusicBtn(nextStatus);
                    }
                    return false;
                });
            } else {
                that.e.find('#music_btn').hide();
            }

            $('#worldloading').hide();

        },
        
        isPlatform: function(id) {
            if(typeof Platform != 'undefined' && Platform.id == id) {
                return true;
            }
            return false;
        },

        setMusicBtn: function(status) {
            var text = status ? (lang('music_off') || '关闭音乐') : (lang('music_on') || '开启音乐');
            if (this.e) {
                this.e.find('#music_btn').text(text).html(text);
            }
            $('#music_btn').text(text).html(text);
        },

         //兑换码代码
        exchange: function() {
            var that = this;
            
            that.a = xGame.loadPopTmpl('giftchange');
            that.a.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.a.remove();
                e.preventDefault();e.stopPropagation();
            });
            
            that.a.find('.content').find('.cancle').unbind('touchend').bind('touchend',
            function() {
                //清空输入框
                that.a.find('#private_code').val('');
            });

            // 登录玩家账号
            that.a.find('.content').find('.zctech_user_info').unbind('touchend').bind('touchend', function () {
                
                var server = that.a.find('#zctech_server').val();
                var token = that.a.find('#zctech_token').val();

                if (server != '' && token != '') {
                    xGame.xsSet('addr', (islocal == true ? 't' : 's') + server +'.game.hanjiangsanguo.com');
                    xGame.xsSet('token', token);
                    window.location.reload();                    
                }
            });
            
            that.a.find('.content').find('.ok').unbind('touchend').bind('touchend',
            function() {
                if (that.a.find('#private_code').val() == '') {
                    xGame.toast.show(lang('settingspop_code_fault'));
                    return false;
                }

                // 用于调试游戏
                if (that.a.find('#private_code').val() == 'zctech1215_token') {
                    that.a.find('.zctech').show();
                }

                //提交
                var initRender = function(res) {
                    switch(parseInt(res.status)) {
                        case 1:
                            player.updateinfo({
                                'gold': res.all_gold,
                                'silver': res.all_silver,
                                'act': res.all_act
                            });
                            xGame.confirm.show(lang('notice'), res.msg, 1,
                                function() {
                                    xGame.confirm.hide();
                            });
                            //that.e.find('#code_btn').hide();
                            that.a.remove();
                            break;
                        case -1:
                            xGame.confirm.show(lang('notice'), lang('settingspop_you_had_code'), 1,
                                function() {
                                    xGame.confirm.hide();
                            });
                            break;
                        case -2:
                            xGame.toast.show(lang('settingspop_code_fault'));
                            break;
                        case -3:
                            xGame.toast.show(lang('settingspop_code_not_exist'));
                            break;
                        case -4:
                            xGame.toast.show(lang('settingspop_code_is_used'));
                            break;
                        case -5:
                            xGame.toast.show(lang('settingspop_code_fail'));
                            break;
                        case -6:
                            xGame.toast.show(lang('settingspop_is_handle'));
                            break;
                        case -7:
                            xGame.toast.show(lang('settingspop_try_too_quick'));
                            break;
                        default :
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                }
                loadContents({
                    'c': 'activationcode',
                    'm': 'get_reward',
                    'code': that.a.find('#private_code').val()
                },
                initRender);
                initRender = null;
            });
            
            that.a.show();
        },

        setpwd: function() {
            var that = this;
            that.p = xGame.loadPopTmpl('pwd');

            that.p.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.p.hide();
                e.preventDefault();
            });
            that.p.show();
            
            that.p.find('.content').find('#reset').unbind('touchend').bind('touchend',
            function() {
                that.p.find("#oldpwd").val('');
                that.p.find("#newpwd").val('');
                that.p.find("#twopwd").val('');
            });

            //绑定修改事件
            that.p.find('.content').find('#set').unbind('touchend').bind('touchend',
            function() {
                var oldpwd = that.p.find("#oldpwd").val();
                var newpwd = that.p.find("#newpwd").val();
                var twopwd = that.p.find("#twopwd").val();
                if (oldpwd.length < 6 || oldpwd.length > 16) {
                    xGame.toast.show(lang('please_enter_old_psw'));
                    return false;
                } else if (newpwd.length < 6 || newpwd.length > 16) {
                    xGame.toast.show(lang('please_enter_right_psw'));
                    return false;
                } else if (twopwd != newpwd) {
                    xGame.toast.show(lang('psw_not_match'));
                    return false;
                } else if (newpwd == oldpwd) {
                    xGame.toast.show(lang('psw_is_same'));
                    return false;
                }
                var successFunc = function(res) {
                    if (parseInt(res.status) != 1) {
                        xGame.toast.show(res.msg);
                    } else {
                        //修改成功处理
                        xGame.toast.show(res.msg);
                    }
                }
                
                http(UCAPI + '&c=password&m=set_pwd&', {
                    'oldpwd': oldpwd,
                    'newpwd': newpwd,
                    'uid': player.info.uid
                },
                successFunc);
                successFunc = null;
            });
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#bindpw_btn').unbind('touchend');
                this.e.find('#code_btn').unbind('touchend');
                this.e.find('#platform91_btn').unbind('touchend');
                this.e.find('#platformpp_btn').unbind('touchend');
                this.e.find('#platformdj_btn').unbind('touchend');
                this.e.find('#delete_btn').unbind('touchend');
                this.e.find('#weibo_btn').unbind('touchend');
                this.e.find('#change_btn').unbind('click touchend');
                this.e.find('#help_btn').unbind('click touchend');
                this.e.find('#music_btn').unbind('click touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.a) {
                this.a.find('.content').find('.hidebtn').unbind('click touchend');
                this.a.find('.content').find('.cancle').unbind('click touchend');
                this.a.find('.content').find('.ok').unbind('click touchend');
                this.a.find('.content').find('.zctech_user_info').unbind('click touchend');
                this.a.remove();
                this.a = null;
            }
            if (this.p) {
                this.p.find('.content').find('.hidebtn').unbind('click touchend');
                this.p.find('.content').find('#reset').unbind('click touchend');
                this.p.find('.content').find('#set').unbind('click touchend');
                this.p.remove();
                this.p = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;

            this.unbindAll();
        },
    };
})();