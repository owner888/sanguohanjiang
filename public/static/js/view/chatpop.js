/* 聊天 */
(function() {
    chatPopClass = function() {
        this.e;
        this.isshow = false;

        this.topDom;
        this.centerDom;
        this.bottomDom;
        this.popDom;
        this.msgScroller;

        this.privateName; //私聊对象的名字
        this.privateUid; //当前私聊的uid
        this.chatType; //当前的tab,1 = 世界，2 = 国家，3 = 私聊,只作为发送消息时的依据，
        this.tabType; //当前的头部按钮类型,,1 = 世界，2 = 国家，3 = 私聊，4 = 战报，5 = 系统,只作为显示按钮依据

        /*返回的数据中 type :0代表公共，1代表私聊,2为喇叭,3战报，4,过图战报，5，将魂战报，6，国家频道，7，系统频道, 8金银洞战报，9跨服，10战帝 11四象圣域战报*/

        this.chatUniqueList; // 聊天唯一列表
        this.isTimeout = null;

    };
    chatPopClass.prototype = {
        init: function() {
            var that = this;

            $('div#chatpop_small').hide();
            $('#cbattle_chat_wrap').hide();

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            that.e = xGame.loadPopTmpl('chat');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                chatpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#chat_text').attr('readonly', 'true');
            that.chatUniqueList = [];

            /*初始化DOM*/
            that.topDom = that.e.find('#chat_top');
            that.centerDom = that.e.find('#chatpop_list');
            that.bottomDom = that.e.find('#chat_footer');
            that.popDom = that.e.find('#private_pop');
            that.infoDom = that.e.find('#info_sub_pop');

            that.initFirst(CD['chat'].allChatData);
        },

        initFirst: function(list) {
            var that = this;

            that.chatType = 1; //初始化为世界频道
            that.tabType = 1;

            /* 从缓存获取上次Tab */
            var tmp = xGame.xsGet('chatlasttab');
            that.tabType = tmp ? tmp : 1; //初始化为世界按钮

            //上次是国家Tab
            if(that.tabType == 2) {
                that.chatType = 2;
                that.bottomDom.find('#chat_from_all').removeClass('is_private');
                that.bottomDom.find('#chat_text').css('width', '100%');
                that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType' + that.chatType).html(lang('country'));
            }
            //如果上次有私聊信息
            var lastChatdata = xGame.xsGet('chatdata');
            if(lastChatdata) {
                var arr = xGame.xsGet('chatdata').split(',');
                if(arr[0] == 3) {
                    that.privateUid = arr[1];
                    that.privateName = arr[2];

                    if(that.tabType == 3) {
                        that.chatType = 3;
                        that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType3').html(lang('private'));
                        that.bottomDom.find('#chat_from_all').addClass('is_private').find('div').html(lang('say_someone', [that.privateName]));
                        that.bottomDom.find('#chat_text').css('width', (570 - that.bottomDom.find('#chat_from_all').find('div').width()) + 'px');
                    }
                }
            }

            //设置频道class
            that.topDom.find('#'+that.convTabidToClass(that.tabType)).addClass('selected'); //that.topDom.find('#public').addClass('selected');


            /*绑定事件*/
            that.bindAll();

            var html = '';

            that.centerDom.html(that.buildLi(list));
            that.setWidth();

            that.msgScroller = new iScroll('chatpop_scroller', {
                momentum: false,
                hScrollbar: false,
                vScrollbar: true
            });

            that.chatRefresh(true);

            that.e.show();
            that.isTimeout = setTimeout(function() {
                that.chatRefresh(true);
                that.e.find('#chat_text').removeAttr('readonly');
            },
            500);
            that.isshow = true;
            $('#worldloading').hide();
        },

        convTabidToClass: function(tabid) {
            var obj = {
                "1": "public",
                "2": "country",
                "3": "private",
                "4": "battle",
                "5": "system",
            };
            return obj[tabid];
        },

        //计算宽度方法
        setWidth: function() {
            var s;
            var obj;

            var centerDomLiArr = this.centerDom.find('li');
            for (var i = 0; i < centerDomLiArr.length ; i++) {
                obj = centerDomLiArr.eq(i);
                s = obj.find('div').width();
                obj.find('p').css('width', (930 - s) + 'px');
            }
            centerDomLiArr = null;
        },

        //刷新方法
        chatRefresh: function(mustScroll) {
            //需要先获得老的高度，跟老的高度比
            var oldY = this.msgScroller.maxScrollY;
            this.msgScroller.refresh();
            var height = this.centerDom.height();
            if (height > 400 && (Math.abs(oldY - this.msgScroller.y) <= 310 || mustScroll == true)) {
                //console.log('移动 》》》》》' + (400 - height));
                this.msgScroller.scrollTo(0, 400 - height, 0);
            }
            this.msgScroller.refresh();
            oldY = null;
        },

        bindAll: function() {
            var that = this;

            /*顶部小按钮tab*/
            that.topDom.undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                //顶部dom样式更新
                that.topDom.find('.selected').removeClass('selected');
                that.topDom.find('#' + this.id).addClass('selected');

                if (this.id == 'public') {
                    that.chatType = 1;
                    that.bottomDom.find('#chattype_text').attr('class', 'chatType1').html(lang('world'));

                    that.tabType = 1;
                } else if (this.id == 'country') {
                    that.chatType = 2;
                    that.bottomDom.find('#chattype_text').attr('class', 'chatType2').html(lang('country'));

                    that.tabType = 2;
                } else if (this.id == 'private') {
                    that.tabType = 3;
                } else if (this.id == 'battle') {
                    that.tabType = 4;
                } else if (this.id == 'system') {
                    that.tabType = 5;
                }
                //如果不是按私聊按钮
                if (that.tabType != 3) {
                    //如果之前有私聊样式，取消掉
                    var chat_from_all = that.bottomDom.find('#chat_from_all');
                    if (chat_from_all.hasClass('is_private')) {
                        chat_from_all.removeClass('is_private');
                        that.bottomDom.find('#chat_text').css('width', '100%');
                    }
                    chat_from_all = null;
                    //战报和系统默认变为世界模式
                    if (that.tabType == 4 || that.tabType == 5) {
                        that.chatType = 1;
                        that.bottomDom.find('#chattype_text').attr('class', 'chatType1').html(lang('world'));
                    }
                } else { //如果按的是私聊按钮，如果上次有私聊过，直接私聊上次的人,没有私聊过就显示世界
                    if (that.privateName != undefined) {
                        that.chatType = 3;
                        that.bottomDom.find('#chattype_text').attr('class', 'chatType3').html(lang('private'));
                        that.bottomDom.find('#chat_from_all').addClass('is_private').find('div').html(lang('say_someone', [that.privateName]));
                        that.bottomDom.find('#chat_text').css('width', (570 - that.bottomDom.find('#chat_from_all').find('div').width()) + 'px');
                    } else {
                        that.chatType = 1;
                        that.bottomDom.find('#chattype_text').attr('class', 'chatType1').html(lang('world'));
                    }
                }

                //通用方法
                that.centerDom.html(that.buildLi(CD['chat'].allChatData));
                that.setWidth();
                that.chatRefresh(true);
            });
            
            //清屏按钮
            that.topDom.undelegate('#clear', 'touchend').delegate('#clear', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (CD['chat'] != undefined) {
                    CD['chat'].allChatData = [];
                    CD['chat'].newData = [];

                    if (that.msgScroller) {
                        that.msgScroller.destroy();
                    }
                    that.centerDom.html('');
                    $('#chatpop_small').find('ul').html('');
                }
            });

            /*小窗口关闭按钮*/
            that.popDom.undelegate('#hidebtn2', 'touchend').delegate('#hidebtn2', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.popDom.css('display', 'none');

                //当没有选择一个私聊的人时，关闭时清理私聊状态
                that.chatType = 1;
                that.bottomDom.find('#chat_from_all').removeClass('is_private');
                that.bottomDom.find('#chat_text').css('width', '100%');
                that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType' + that.chatType).html(that.bottomDom.find('.public').html());

            });

            /*加下滑线可以点的姓名*/
            that.centerDom.undelegate('span.under', 'touchend').delegate('span.under', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                that.privateUid = parseInt($(this).attr('uid'));
                that.privateName = $(this).text();

                that.showInfo();

            });

            /*输入框前面的私聊名字*/
            that.bottomDom.undelegate('.input_slname', 'touchend').delegate('.input_slname', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showInfo();
            });

            /*底部聊天类型切换*/
            that.bottomDom.undelegate('#chat_type', 'touchend').delegate('#chat_type', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                $(this).toggleClass('chatlist_show');
            });

            /*底部聊天切换里面li*/
            that.bottomDom.undelegate('#chat_type_list li', 'touchend').delegate('#chat_type_list li', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.chatType = parseInt($(this).attr('chattype'));
                //如果不是私聊，清除私聊的样式
                if (that.chatType != 3) {
                    xGame.xsRemove('chatdata');//切换到世界以后，清除私聊状态
                    delete that.privateUid;
                    delete that.privateName;
                    that.bottomDom.find('#chat_from_all').removeClass('is_private');
                    that.bottomDom.find('#chat_text').css('width', '100%');
                    that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType' + that.chatType).html($(this).html());
                } else if (that.chatType == 3) { //如果有人私聊过的话就直接用之前的，没有就显示弹窗
                    if (that.privateName != undefined) {
                        that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType3').html(lang('private'));
                        that.bottomDom.find('#chat_from_all').addClass('is_private').find('div').html(lang('say_someone', [that.privateName]));
                        that.bottomDom.find('#chat_text').css('width', (570 - that.bottomDom.find('#chat_from_all').find('div').width()) + 'px');
                    } else {
                        that.popDom.css('display', 'block');
                    }
                }
                that.bottomDom.find('#chat_type_list').removeClass('chatlist_show');
            });

            //绑定战报
            that.centerDom.undelegate('span.viewbattle', 'touchend').delegate('span.viewbattle', 'touchend',
            function() {
                var battleid = $(this).attr('viewid');
                if (battleid > 0) {
                    //that.hide();
                    if ($(this).attr('type') == 'champions' || $(this).attr('type') == 'sanctum' || $(this).attr('type') == 'war' || $(this).attr('type') == 'pacify') {
                        miscpop.viewBattleFromMail(battleid, $(this).attr('type'), $(this).attr('stage'));
                    } else {
                        miscpop.viewBattleFromMail(battleid, $(this).attr('type'));
                    }
                }
            });
            //绑定多人副本组队
            that.centerDom.undelegate('span.elitejoin', 'touchend').delegate('span.elitejoin', 'touchend',
            function() {
                var fid = $(this).attr('fid');
                var teamid = $(this).attr('teamid');
                var countryid = $(this).attr('countryid');
                if (fid > 0 && teamid > 0) {
                    if (typeof elitepop != 'undefined' && elitepop.e != null) {
                        if (elitepop.teamdetail != null) {
                            xGame.toast.show(lang('you_had_a_team'));
                            return false;
                        }
                        elitepop.fetchTeamList(fid, teamid, countryid);
                    } else {
                        xGame.showpop('elite', {
                            fid: fid,
                            teamid: teamid,
                            countryid: countryid
                        });
                    }
                }
            });
            
            //小弹窗绑定
            
            /*关闭按钮*/
            that.infoDom.undelegate('#hidebtn2', 'touchend').delegate('#hidebtn2', 'touchend',
            function() {
                that.infoDom.css({
                    'display': 'none'
                });
            });
            
            /*私聊按钮*/
            that.infoDom.undelegate('.sl', 'touchend').delegate('.sl', 'touchend',
            function() {
                that.chatType = 3;
                that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType3').html(lang('private'));
                that.bottomDom.find('#chat_from_all').addClass('is_private').find('div').html(lang('say_someone', [that.privateName]));
                that.bottomDom.find('#chat_text').css('width', (570 - that.bottomDom.find('#chat_from_all').find('div').width()) + 'px');
                that.infoDom.css({
                    'display': 'none'
                });
                xGame.xsSet('chatdata', '3,' + that.privateUid + ',' + that.privateName);
            });
            
            /*资料按钮*/
            that.infoDom.undelegate('.ok', 'touchend').delegate('.ok', 'touchend',
            function() {
                xGame.showpop('info', that.privateUid);
            });
            
            //发信按钮
            that.infoDom.undelegate('.cancle', 'touchend').delegate('.cancle', 'touchend',
            function() {
                that.hide(function() {
                    xGame.showpop('mailbox', that.privateName);
                });
            });
            
            //切磋按钮
            that.infoDom.undelegate('#battle_btn', 'touchend').delegate('#battle_btn', 'touchend',
            function() {
                miscpop.battleByUid(that.privateUid);
                that.infoDom.css({
                    'display': 'none'
                });
            });
            
            //屏蔽按钮
            that.infoDom.undelegate('#shield', 'touchend').delegate('#shield', 'touchend',
            function() {
                xGame.confirm.show(lang('notice'), lang('chatpop_shield_ask'), 2,
                function() {
                    xGame.confirm.hide();
                    that.shieldFunc();
                },
                function() {
                    xGame.confirm.hide();
                });
            });
        },
        
        shieldFunc: function() {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        if (CD['chat'] != undefined) {
                            CD['chat'].allChatData = [];
                            for (var i = res.list.length - 1; i >= 0; i--) {
                                if (res.list[i] != undefined && res.list[i].message != undefined && res.list[i].type > 2) {
                                    res.list[i].message = CD['chat'].transferMsgFunc(res.list[i].message);
                                }
                                CD['chat'].allChatData.push(res.list[i]);
                            }
                            /*小窗口的方法*/
                            CD['chat'].createMain(CD['chat'].allChatData);
                            /*战斗中小窗口*/
                            CD['chat'].createCountryMain(CD['chat'].allChatData);
                        }
                        chatpop.centerDom.html(chatpop.buildLi(CD['chat'].allChatData));
                        chatpop.setWidth();
                        chatpop.chatRefresh();
                        
                        xGame.toast.show(lang('chatpop_shield_success'));
                        that.infoDom.css('display', 'none');
                        
                        break;
                    case -1:
                        xGame.toast.show(lang('chatpop_do_not_again'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'chat',
                'm': 'ban_uid',
                'touid': that.privateUid
            },
            Func);
            Func = null;
        },

        showInfo: function() {
            var that = this;
            that.infoDom.css({
                'display': 'block'
            });
            that.infoDom.find('#info_name').html(lang('nickname') + that.privateName);
            
        },

        buildLi: function(list, isSend) {
            var that = this;
            var html = '';
            var class_name = '';
            /*<span class="time">' + list[i].time + '</span>*/
            if (list == undefined) {
                return false;
            }
            for (var i = list.length - 1; i >= 0; i--) {
                //聊天消息过滤 屏蔽
                /*
                if(list[i].type == 0 && list[i].message.length > 0) {
                    //console.log(list[i].message);
                    var cn = helper.chatClean(list[i].message);
                    if(cn.length > 0) {
                        if(_inArray(that.chatUniqueList, cn)) {
                            //console.log('过滤：'+list[i].message);
                            continue;
                        } else {
                            that.chatUniqueList.push(cn);
                        }
                    }
                }
                */

                that.endNum++;
                class_name = '';
                if  (list[i].rank != undefined && list[i].rank < 4 && list[i].rank > 0) {
                    class_name = ' num rank' + list[i].rank;
                }
                if (typeof list[i].tree != 'undefined' && list[i].tree > 1) {
                    class_name += ' num tree' + list[i].tree;
                }
                if (list[i].type == 0 && (that.tabType == 1)) { //公共类型只在世界tab下显示
                    if (list[i].uid == player.info.uid) {
                        html += '<li class="public"><div><span class="type">【' + lang('world') + '】</span><span class="name' + class_name + '"><em>' + lang('you') + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                    } else {
                        html += '<li class="public"><div><span class="type">【' + lang('world') + '】</span><span class="name under' + class_name + '" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                    }
                } else if (list[i].type == 1 && (that.tabType == 1 || that.tabType == 3)) { //私聊类型只在世界tab和私聊tab下显示
                    if (list[i].mine != undefined) {
                        html += '<li class="private"><div><span class="type">【' + lang('private') + '】</span><span class="name"><em>' + lang('you') + '</em></span> ' + lang('chatpop_to_someone') + ' <span class="under name" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                    } else {
                        html += '<li class="private"><div><span class="type">【' + lang('private') + '】</span><span class="name under" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span>&nbsp;' + lang('chatpop_to_someone') + ' <span class="name"><em>' + lang('you') + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                    }
                } else if (list[i].type == 3 && (that.tabType == 1 || that.tabType == 4)) { //战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 4 && (that.tabType == 1 || that.tabType == 4)) { //过图战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 5 && (that.tabType == 1 || that.tabType == 4)) { //将魂战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 6 && (that.tabType == 1 || that.tabType == 2)) { //国家类型只在世界tab和国家tab下显示
                    if (list[i].system == 1) {
                        html += '<li class="country"><div><span class="type">【' + lang('country') + '】</span></div><p>' + list[i].message + '</p></li>';
                    } else {
                        if (list[i].uid == player.info.uid) {
                            html += '<li class="country"><div><span class="type">【' + lang('country') + '】</span><span class="name' + class_name + '"><em>' + lang('you') + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].uid != player.info.uid) {
                            html += '<li class="country"><div><span class="type">【' + lang('country') + '】</span><span class="name under' + class_name + '" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                        }
                    }

                } else if (list[i].type == 7 && (that.tabType == 1 || that.tabType == 5)) { //系统类型只在世界tab和系统tab下显示
                    html += '<li class="system"><div><span class="type">【' + lang('system') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 8 && (that.tabType == 1 || that.tabType == 4)) { //金银洞战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 9 && (that.tabType == 1 || that.tabType == 4)) { //跨服战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 10 && (that.tabType == 1 || that.tabType == 4)) { //战神殿战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 11 && (that.tabType == 1 || that.tabType == 4)) { //四象圣域战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 38 && (that.tabType == 1 || that.tabType == 4)) { //通天塔战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 12 && (that.tabType == 1 || that.tabType == 4)) { //武斗会战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 49 && (that.tabType == 1 || that.tabType == 4)) { //铜雀台战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                } else if (list[i].type == 53 && (that.tabType == 1 || that.tabType == 4)) {  //平乱副本战报类型只在世界tab和战报tab下显示
                    html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                }

                if (isSend != undefined) {
                    CD['chat'].allChatData.unshift(list[i]);
                }

            }
            return html;
        },

        send: function() {
            var that = this;

            var str = that.bottomDom.find('#chat_text').val();

            if (str == '') {
                xGame.toast.show(lang('msg_not_null'));
                return;
            } else if (str.length > 60) {
                xGame.toast.show(lang('more_than_60'));
                return;
            }

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 6:
                    xGame.toast.show((res.msg ? res.msg : lang('chatpop_sendlimit')));
                    break;
                case 5:
                    xGame.toast.show(lang('no_country_to_send'));
                    break;
                case 4:
                    xGame.toast.show(lang('send_too_fast'));
                    break;
                case 3:
                    xGame.toast.show(lang('msg_too_long'));
                    break;
                case 2:
                    xGame.toast.show(lang('msg_not_null'));
                    break;
                case 1:

                    //万圣节期间获得糖果
                    if(player.info.halloween == 1){
                        if(res.candy > 0) {
                            xGame.toast.show(lang('breakthrough_reward', ['reward halloween_candy', 'x' + res.candy]));
                        }
                    }

                    if (that.chatType == 3) {
                        res.list = [{
                            'uid': that.privateUid,
                            'type': 1,
                            'time': 'no',
                            'nickname': that.privateName,
                            'message': str,
                            'mine': true
                        }];
                    }
                    for (var i = res.list.length - 1; i >= 0; i--) {
                        if (res.list[i] != undefined && res.list[i].message != undefined && res.list[i].type > 2) {
                            res.list[i].message = CD['chat'].transferMsgFunc(res.list[i].message);
                        }
                    }
                    that.centerDom.append(that.buildLi(res.list, true));
                    //新加的，超过限制时删除10条数据
                    chatpop.deleteTenLine();
                    that.setWidth();
                    that.chatRefresh(true);

                    CD['chat'].createMain(res.list);
                    CD['chat'].createCountryMain(res.list);

                    that.bottomDom.find('#chat_text').val('');

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            if (xGame.checkLock()) {
                return false;
            }

            if (that.chatType == 1) { //发送世界消息
                if (that.tabType != 1) { //除了世界都切换过来
                    that.topDom.find('.selected').removeClass('selected');
                    that.topDom.find('#public').addClass('selected');
                    that.tabType = 1;
                    that.centerDom.html(that.buildLi(CD['chat'].allChatData));
                    that.setWidth();
                    that.chatRefresh(true);
                }
                chatpop.httpChat({
                    'c': 'chat',
                    'm': 'send',
                    'message': str
                },
                Func, true);
            } else if (that.chatType == 2) { //发送国家消息
                if (that.tabType > 2) { //私聊战报系统就切换过来,国家和世界就保持现状
                    that.topDom.find('.selected').removeClass('selected');
                    that.topDom.find('#country').addClass('selected');
                    that.tabType = 2;
                    that.centerDom.html(that.buildLi(CD['chat'].allChatData));
                    that.setWidth();
                    that.chatRefresh(true);
                }
                chatpop.httpChat({
                    'c': 'chat',
                    'm': 'send',
                    'message': str,
                    'country': '1'
                },
                Func, true);
            } else if (that.chatType == 3) { //发送私聊消息消息
                if (that.tabType != 3 && that.tabType != 1) { //国家战报系统就切换过来,世界和私聊就保持现状
                    that.topDom.find('.selected').removeClass('selected');
                    that.topDom.find('#private').addClass('selected');
                    that.tabType = 3;
                    that.centerDom.html(that.buildLi(CD['chat'].allChatData));
                    that.setWidth();
                    that.chatRefresh(true);
                }
                chatpop.httpChat({
                    'c': 'chat',
                    'm': 'send',
                    'message': str,
                    'uid': that.privateUid
                },
                Func, true);
            }
            Func = null;
        },

        checkNickName: function() {
            var that = this;
            var name = that.popDom.find('#private_name').val();
            if (helper.checkNickName(name)) {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case 2:
                        xGame.toast.show(lang('player_not_exist'));
                        break;
                    case 1:
                        // that.bottomDom.find('#chat_type').find('#chattype_text').attr('class', 'chatType3').html(lang('private'));
                        that.bottomDom.find('#chat_type').find('#chattype_text').html(lang('private'));
                        that.chatType = 3;
                        that.privateName = name;
                        that.privateUid = res.uid;
                        that.bottomDom.find('#chat_from_all').addClass('is_private').find('div').html(lang('say_someone', [that.privateName]));
                        that.bottomDom.find('#chat_text').val('').css('width', (570 - that.bottomDom.find('#chat_from_all').find('div').width()) + 'px');
                        that.popDom.css('display', 'none');
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                    }
                }
                chatpop.httpChat({
                    'c':
                    'chat',
                    'm': 'getuid',
                    'name': name
                },
                Func, true);
                Func = null;
            }
        },
        
        httpChat: function(data, fn, hideloading) {
            var readUrl = UCAPI + 'c=' + data.c + '&m=' + data.m + '&';
            http(readUrl, data, fn, hideloading);
        },
        
        deleteTenLine: function() {
            if (chatpop.centerDom.find('li').length > 30) {
                for (var i = 0; i < chatpop.centerDom.find('li').length - 30; i ++) {
                    chatpop.centerDom.find('li').eq('0').remove();
                    CD['chat'].allChatData.pop();
                }
            }
        },

        unbindAll: function () {
            if(this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.topDom) {
                this.topDom.undelegate('#clear', 'touchend');
                this.topDom.undelegate('.pop_btn_small', 'touchend');
                this.topDom.remove();
                this.topDom = null;
            }
            if(this.popDom) {
                this.popDom.undelegate('#hidebtn2', 'touchend');
                this.popDom.remove();
                this.popDom = null;
            }
            if (this.bottomDom) {
                this.bottomDom.undelegate('.input_slname', 'touchend');
                this.bottomDom.undelegate('#chat_type', 'touchend');
                this.bottomDom.undelegate('#chat_type_list li', 'touchend');
                this.bottomDom.remove();
                this.bottomDom = null;
            }
            if (this.centerDom) {
                this.centerDom.undelegate('span.under', 'touchend');
                this.centerDom.undelegate('span.viewbattle', 'touchend');
                this.centerDom.undelegate('span.elitejoin', 'touchend');
                this.centerDom.remove();
                this.centerDom = null;
            }
            if (this.infoDom) {
                this.infoDom.undelegate('#hidebtn2', 'touchend');
                this.infoDom.undelegate('.sl', 'touchend');
                this.infoDom.undelegate('.ok', 'touchend');
                this.infoDom.undelegate('.cancle', 'touchend');
                this.infoDom.undelegate('#battle_btn', 'touchend');
                this.infoDom.undelegate('#shield', 'touchend');
                this.infoDom.remove();
                this.infoDom = null;
            }
        },
        
        hide: function(callback) {
            xGame.unlock();

            this.isshow = false;

            if (typeof callback != 'undefined') {
                callback();
            }

            // 保存上次聊天频道
            xGame.xsSet('chatlasttab', this.tabType);
            if (this.msgScroller) {
                this.msgScroller.destroy();
                delete this.msgScroller;
            }   
            if (this.readThread) {
                clearTimeout(this.readThread);
                this.readThread = null;
            }
            this.privateName = this.chatType = this.privateUid = this.tabType = null;
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }

            $('#chatpop_small').show();
            $('#cbattle_chat_wrap').show();

            this.unbindAll();
        },
    };
})();