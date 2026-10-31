/* 推图CD: CD['battle']*/
(function() {
    battleCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    battleCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            var that = this;

            this.e = $('#battle_timer');
            this.s = s;
            this.e.unbind('touchend').bind('touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }

                var nextFn = function(cdPrice) {
                    if (cdPrice.gold == 0) {
                        var golds = 0;
                        var msg = lang('clear_battle_cd_ask');
                    } else {
                        var golds = Math.ceil(CD['battle'].s / 180);
                        var msg = lang('clear_battle_cd_ask2', [golds]);
                    }
                    xGame.confirm.show(lang('notice'), msg, 2,
                    function() {
                        xGame.confirm.hide(function() {
                            var cleanRender = function(res) {
                                if (res.status == 1) {
                                    CD['battle'].update(0);
                                    xGame.toast.show(lang('clear_battle_cd_success'));
                                    player.updateinfo({
                                        'gold': (parseInt(res.all_gold))
                                    });
                                } else if (res.status == -1) {
                                    xGame.toast.show(lang('no_need_clear_cd'));
                                } else if (res.status == -2) {
                                    xGame.goPay(lang('no_gold_clear_cd'));
                                } else {
                                    xGame.toast.show(lang('clear_cd_fail'));
                                }
                                cleanRender = null;
                                xGame.unlock();
                            };
                            loadContents({
                                'c': 'map',
                                'm': 'clear_cd',
                                'type': '1'
                            },
                            cleanRender);
                            cleanRender = null;
                        });
                    },
                    function() {
                        xGame.confirm.hide();
                        xGame.unlock();
                    });
                };

                that.checkCdPrice(nextFn);
                nextFn = null;
            });
            if (s > 0) {
                this.e.show();
                $('body').addClass('inbattlecd');
                timer.addListener(this);
                this.on = true;
            } else {
                this.on = false;
                this.e.hide();
                $('body').removeClass('inbattlecd');
            }
        },

        checkCdPrice: function(callback) {
            var render = function(res) {
                if(callback) {
                    callback(res);
                }
            };
            loadContents({
                'c': 'map',
                'm': 'clear_cd',
                'type': '0'
            },
            render);
            render = null;
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                this.e.hide();
                $('body').removeClass('inbattlecd');
                this.e.children('.timer').text('').removeClass('red');
                this.clear();
            } else {
                this.e.children('.timer').text(helper.timeToClock(this.s));
                if (this.s >= 60 * 30 && !this.e.children('.timer').hasClass('red')) {
                    this.e.children('.timer').addClass('red');
                }
            }
        },

        clear: function() {
            console.log('清理推图CD');
            if (this.on == true) {
                timer && timer.removeListener(this);
            }
            this.on = false;
            this.e = null;
            this.s = 0;

        },

    };

})();

/* 征收CD：CD['levy'] */
(function() {
    levyCdClass = function() {
        this.e; //页面里倒计时的dom
        this.e2; //侧边栏
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    levyCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e2 = $('#sider_levy');
            this.s = s;

            if (s > 0) {
                timer.addListener(this);
                this.on = true;
                this.step();
            } else {
                this.updateSider();
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                player.info.levy.status = 0; //设置状态
                if (this.e == null || this.e.length <= 0) {
                    this.initPopDom();
                }
                if (this.e) {
                    this.e.hide();
                    this.e.find('#levy_timer_span').text('').removeClass('cd');
                }
                this.updateSider();
                this.clear();
            } else {
                if (this.e == null || this.e.length <= 0) {
                    this.initPopDom();
                }
                if (this.e) {
                    this.e.show();
                    this.e.find('#levy_timer_span').text(helper.timeToClock(this.s));
                    if (this.s >= 60 * 30 && !this.e.find('#levy_timer_span').hasClass('cd')) {
                        player.info.levy.status = 1; //设置状态
                        this.e.find('#levy_timer_span').addClass('cd');
                    }
                }
                this.updateSider();
            }
        },

        initPopDom: function() {
            this.e = $('#levy_timer_wrap');
        },

        recyclePopDom: function() {
            this.e = null;
        },

        updateSider: function() {
            if (player.info.levy.times <= 0) { //没有次数时
                this.e2.find('p').attr({
                    'class': 'nocd'
                }).text(lang('can_qz'));
            } else { //有次数时
                if (player.info.levy.status == 1) {
                    this.e2.find('p').text(helper.timeToClock(this.s)).attr({
                        'class': 'cd'
                    });
                } else {
                    this.e2.find('p').attr({
                        'class': 'nocd'
                    }).text(player.info.levy.times + '/50');
                }
            }
        },

        clear: function() {
            console.log('清理征收CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.e2 = null;
            player.info.levy.cd = this.s;
            this.s = 0;
        },

    };

})();

/* 侧边训练CD：CD['siderPractice'] */
(function() {
    siderPracticeCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    siderPracticeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#sider_practice').find('p');
            this.s = s;

            if (s > 0) {
                timer.addListener(this);
                this.on = true;
                this.step();
            }

        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                $(this.e).attr({
                    'class': 'nocd'
                }).text(lang('can_practice_hero'));
                this.clear();
            } else {
                $(this.e).attr({
                    'class': 'cd'
                }).text(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理侧边训练CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            player.info.practice.timers = this.s;
            this.s = 0;
        },

    };

})();

/*校场计时器*/
(function() {
    practiceCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    practiceCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#practice_time');
            this.s = s;
            if (s > 0) {
                this.e.css('display', 'block');
                this.e.children('#practice_timer').html('');
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.css('display', 'none');
                this.e.children('#practice_timer').html('');
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                this.clear();
            } else {
                this.e.children('#practice_timer').html(helper.timeToClock(this.s));
            }
        },

        /*此方法不能跟着step走，step会暂停的，而这个方法只有我想让他暂停时才暂停*/
        checkEveryOne: function() {
            var that = this;
            var thread = function() {
                //console.log('按秒执行，还可以执行' + practicepop.maxSec + '次');
                if (practicepop.minData != undefined) {
                    /*当最小的那一个时间达到它对应的时间的时候*/
                    if (practicepop.heroData[practicepop.minData.gid].cd <= practicepop.rtList[practicepop.heroData[practicepop.minData.gid].num]['cd']) {
                        //console.log('最小的时间到了，所以要运行一次了啊');
                        /*如果最小的恰巧是当前的，那么就把当前的div切换*/
                        if (practicepop.minData.gid == practicepop.shid) {
                            //console.log('相等，变化dom吧');
                            practicepop.checkDisplay(0);
                        }
                        practicepop.leftDom.find('#lh' + practicepop.minData.gid).find('.training').remove();
                        practicepop.practiceListDom.find('#pid' + practicepop.minData.pid).attr('class', 'block').find('img').remove();
                        /*数据修改*/
                        practicepop.blockPlace = practicepop.practiceListDom.find('.block').first().attr('pid');
                        practicepop.heroData[practicepop.minData.gid].ispractice = 0;
                        /*修改两个新属性*/
                        practicepop.heroData[practicepop.minData.gid].pid = 0;
                        practicepop.heroData[practicepop.minData.gid].cd = 0;

                        //训练侧边栏数据更新
                        player.showPracticeSider(0);

                        /*最后一步，如果还有空位，重新计算一次mindata*/
                        if (practicepop.minData != undefined) {
                            delete practicepop.placeData[practicepop.minData.gid];
                            practicepop.setMinData();

                            practicepop.rtList[practicepop.heroData[practicepop.shid].num]['cd'] = 0;
                            practicepop.rtList[practicepop.heroData[practicepop.shid].num]['status'] = 0;
                        }
                    }
                }

                practicepop.maxSec--;
                if (practicepop.maxSec < 0) {
                    practicepop.maxSec = 0;
                    return;
                }
                for (var i = practicepop.heroData.total; i >= 1; i--) {
                    if (practicepop.rtList[i]['status'] == 1) {
                        practicepop.rtList[i]['cd'] += 1;
                    }
                }
                setTimeout(arguments.callee, 1000);
            };
            thread();

        },

        clear: function() {
            console.log('清理校场CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/* 商店CD：CD['shop'] */
(function() {
    shopCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    shopCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#shop_rightDom');
            this.s = s;

            if (s > 0) {
                this.e.find('#shoppop_shopbtn').attr('class', 'btn pop_btn_big_disable').html(lang('buy_cd') + ':' + helper.timeToString(this.s));

                timer.addListener(this);
                this.on = true;
            } else {
                this.e.find('#shoppop_shopbtn').attr('class', 'btn pop_btn_big').html(lang('buy'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                this.e.find('#shoppop_shopbtn').attr('class', 'btn pop_btn_big').html(lang('buy'));
                this.clear();
            } else {
                this.e.find('#shoppop_shopbtn').html(lang('buy_cd') + ':' + helper.timeToString(this.s));
            }
        },

        clear: function() {
            console.log('清理商店CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/* 强化CD：CD['strong'] */
(function() {
    strongCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    strongCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#cd_info');
            this.s = s;
            if (s > 0) {
                this.e.show();
                timer.addListener(this);
                this.on = true;
                this.step();
            } else {
                this.e.hide();
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                this.e.hide();
                this.e.find('#strong_cd').text('').removeClass('cd');
                strongpop.cd = false;
                this.clear();
            } else {
                this.e.find('#strong_cd').text(helper.timeToString(this.s));
                if (strongpop.cd == true && !this.e.find('#strong_cd').hasClass('cd')) {
                    this.e.find('#strong_cd').addClass('cd');
                }
            }
        },

        clear: function() {
            console.log('清理强化CD');
            this.e.find('#strong_cd').text('').removeClass('cd');
            strongpop.cd = false;
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/* 聊天：CD['chat'] */
(function() {
    chatCdClass = function() {
        this.s;
        /*倒计时秒数*/
        this.on = false; //开关

        this.allChatData;
        /*所有的聊天数据*/
        this.newData;
        /*每次最新的聊天数据，每次都清空*/
        this.needS;
        /*需要的时间*/
        this.hornList;
        /*喇叭的集合*/
        this.hasPrivate;
        /*是否有私聊*/
        this.chatBtn = $('.menu_chat');

        this.isBackForInit = false;
        this.isBackAndAfterInit = false;
        this.isError;
        /*是否错误了*/
        this.chatThread;
        /*读取的线程*/
        this.timeout;
        /*用于记录超时*/
        this.errorFunc;
        /*错误方法*/
        this.isTimeout;
        /*是否超时*/

        this.chatUniqueList; // 聊天唯一列表
    };
    chatCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            var that = this;
            /*必须用that,不然循环找不到*/
            that.s = s;
            that.allChatData = new Array();

            that.needS = 5;
            that.s = that.needS;
            that.hasPrivate = false;

            that.chatUniqueList = [];

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    console.log(new Date().toLocaleTimeString() + '  ------  ' + '成功调用index接口 ----------->');
                    if (that.isBackForInit) {
                        that.isBackAndAfterInit = true;
                    }
                    for (var i = res.list.length - 1; i >= 0; i--) {
                        if (res.list[i] != undefined && res.list[i].message != undefined && res.list[i].type > 2) {
                            res.list[i].message = that.transferMsgFunc(res.list[i].message);
                        }
                        that.allChatData.push(res.list[i]);
                    }
                    /*小窗口的方法*/
                    that.createMain(that.allChatData);
                    /*战斗中小窗口*/
                    that.createCountryMain(that.allChatData);

                    if (that.isBackAndAfterInit) {
                        /*打开窗口时*/
                        if (chatpop != undefined && chatpop.isshow) {
                            //console.log('聊天窗口存在，重连后重新刷新哦！');
                            //console.log(CD['chat'].allChatData);
                            chatpop.startNum = 0;
                            chatpop.endNum = 0;
                            chatpop.centerDom.html(chatpop.buildLi(CD['chat'].allChatData));
                            chatpop.setWidth();
                            chatpop.chatRefresh();
                        }
                    } else {
                        if (that.needS > 0) {
                            timer.addListener(that);
                            that.on = true;
                            that.step();
                        }
                    }
                    that.isError = false;
                    break;
                default:
                    //xGame.toast.show('聊天数据初始化时发生未知错误！');
                    console.log('成功返回了，但是状态码不是1');
                    break;
                }
            };

            http(UCAPI + 'c=chat&m=index&', {
                'v': localStorage['sg_ver']
            },
            initRender, true);
            initRender = null;

            // 聊天室实时推送：用当前登录 token 连接 / 鉴权 GameWS
            if (window.GameWS && xGame.token) {
                window.GameWS.connect(xGame.token);
            }

        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.on = true;
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            if (this.s <= 0) {
                this.s = 0;
                this.read();
            }
            this.s--;
        },

        read: function() {
            // GameWS 鉴权成功后，服务端把 read 的结果实时推送过来（receive），不再轮询；断线后恢复轮询
            if (window.GameWS && window.GameWS.isAuthed) {
                return;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {

                case 1:

                    that.chatThread = null;
                    clearTimeout(that.timeout);
                    that.timeout = null;
                    that.isError = false;

                    //console.log(new Date().toLocaleTimeString() + '  ------  ' + '成功调用read接口,并且也已经清掉超时计时器了 --------------->');
                    if (res.list.length == 0) {
                        if (that.needS < 30) {
                            that.needS += 5;
                        }
                        if (chatpop != undefined && chatpop.isshow) {
                            /*当现实聊天弹窗时强制5秒刷新*/
                            that.needS = 5;
                        }
                    } else if (res.list.length != 0) {
                        that.needS = 5;
                        if (that.receive(res.list) === false) {
                            return false;
                        }
                    }

                    that.s = that.needS;
                    that.update(that.s);

                    break;
                default:
                    console.log('+++++++++++++++++++聊天数据读取时发生未知错误！++++++++++++++++++');
                    break;
                }
            };
            /*错误时的方法*/
            that.errorFunc = function() {
                if (!that.isError) {
                    that.isError = true;
                    console.log('read中第一次调用错误，直接给body绑事件然后暂停程序');
                    CD['chat'].clear();
                    $('body').unbind('touchend').one('touchend',
                    function() {
                        CD['chat'].isError = false;
                        CD['chat'].needS = 5;
                        CD['chat'].isTimeout = true;
                        CD['chat'].read();
                        player.reloadInfo(null, true);
                        console.log('触发read的一次性事件');
                        return;
                    });
                    //console.log('当前网络异常，请检查您当前的网络连接状态！');
                    return;
                }
            }

            var readUrl = UCAPI;

            if ((that.isBackForInit && that.isBackAndAfterInit) || that.isTimeout) {
                console.log('恢复回来第一次');
                //loadContents({'c':'chat', 'm':'read','first':1,'v':'20120829'}, Func,true,error);
                that.chatThread = http(readUrl + '&c=chat&m=read&', {
                    'first': 1,
                    'v': localStorage['sg_ver']
                },
                Func, true, that.errorFunc, null, true);
                that.isBackAndAfterInit = false;
                that.isBackForInit = false;
                that.isTimeout = false;
                that.errorFunc = null;
            } else {
                //loadContents({'c':'chat', 'm':'read','v':'20120829'}, Func,true,error);
                that.chatThread = http(readUrl + '&c=chat&m=read&', {
                    'v': localStorage['sg_ver']
                },
                Func, true, that.errorFunc, null, true);
                that.errorFunc = null;
            }

            /*最长4.5秒的超时*/
            that.timeout = setTimeout(function() {
                if (that.chatThread != null) {
                    console.log('超时了，太不给力了吧，网速这么慢');
                    that.isTimeout = true;
                    that.chatThread.abort();
                }
            },
            4500);

        },

        /* 渲染 read 取到的新消息（HTTP 轮询与 GameWS 推送共用） */
        receive: function(list) {
            var that = this;
            that.newData = [];
            that.hornList = [];
            for (var i = list.length - 1; i >= 0; i--) {
                if (list[i] != undefined && list[i].message != undefined && list[i].type > 2) {
                    list[i].message = that.transferMsgFunc(list[i].message);
                }
                if (list[i] != undefined) {
                    that.allChatData.unshift(list[i]);
                }
                if (list[i]['type'] == 2) {
                    that.hornList.push(list[i])
                } else {
                    that.newData.unshift(list[i]);
                }
                /*如果有私聊信息并且原来没有数据和class时,并且窗口没有打开时*/
                if (list[i]['type'] == 1 && !that.hasPrivate && !that.chatBtn.hasClass('tada') && (chatpop != undefined && !chatpop.isshow || chatpop == undefined)) {
                    that.hasPrivate = true;
                    that.chatBtn.addClass('tada').unbind('touchend').one('touchend',
                    function() {
                        that.chatBtn.removeClass('tada');
                        that.hasPrivate = false;
                        /*直接切换到私聊,需要延时才行*/
                        setTimeout(function() {
                            chatpop.topDom.find('#public').removeClass('selected');
                            chatpop.topDom.find('#private').addClass('selected');
                            chatpop.tabType = 3;
                            chatpop.startNum = 0;
                            chatpop.endNum = 0;
                            chatpop.centerDom.html(chatpop.buildLi(CD['chat'].allChatData));
                            chatpop.setWidth();
                            chatpop.chatRefresh();
                        },
                        100);
                    });
                }
            }
            /*这里只插入返回的文字，自己发的文字在chatpop插入*/
            if (chatpop != undefined && chatpop.isshow) {
                chatpop.centerDom.append(chatpop.buildLi(that.newData));
                //新加的，超过限制时删除10条数据
                chatpop.deleteTenLine();
                chatpop.setWidth();
                chatpop.chatRefresh();
            }
            that.createMain(that.newData);
            /*战斗中小窗口*/
            that.createCountryMain(that.newData);
            if (that.hornList.length != 0) {
                var msg = '';
                for (var i = 0; i < that.hornList.length; i++) {
                    msg += '<li>' + that.hornList[i].message + '</li>';
                }
                /*这种情况下不发喇叭*/
                if (xGame.guide == true && player.info.level <= 26 && typeof teampop != 'undefined' && teampop.e != null) {
                    return false;
                }
                xGame.horn(msg);
            }
        },

        createMain: function(list) {
            var that = this;
            var b = [];
            for (var i = 0; i < list.length; i++) {
                if (list[i].type != 2) {
                    b.push(list[i]);
                }
            }
            list = b;
            b = null;

            var chatpop_small_ul = $('#chatpop_small ul');
            if (list.length >= 3) {
                chatpop_small_ul.html(that.buildSmallLi(list, 3));
            } else {
                var chatpop_small_ul_lis = chatpop_small_ul.find('li');
                var size = chatpop_small_ul_lis.size();
                if (size + list.length > 3) {
                    for (var i = 0; i < list.length + size - 3; i++) {
                        chatpop_small_ul_lis.eq(i).remove();
                    }
                }
                size = null;
                chatpop_small_ul.append(that.buildSmallLi(list, 3));
                chatpop_small_ul_lis = null;
            }
            chatpop_small_ul = null;
        },

        createCountryMain: function(list) {
            var that = this;

            if (typeof countrybattleWorld != 'undefined' && countrybattleWorld.e != null) {
                var b = [];
                for (var i = 0; i < list.length; i++) {
                    if (list[i].type != 2) {
                        b.push(list[i]);
                    }
                }
                list = b;
                b = null;

                var cbattle_chat_wrap = $('#cbattle_chat_wrap');
                if (list.length >= 6) {
                    cbattle_chat_wrap.html(that.buildSmallLi(list, 6));
                } else {
                    var cbattle_chat_wrap_lis = cbattle_chat_wrap.find('li');
                    var size = cbattle_chat_wrap_lis.size();
                    if (size + list.length > 6) {
                        for (var i = 0; i < list.length + size - 6; i++) {
                            cbattle_chat_wrap_lis.eq(i).remove();
                        }
                    }
                    size = null;
                    cbattle_chat_wrap.append(that.buildSmallLi(list, 6));
                    cbattle_chat_wrap_lis = null;
                }
                cbattle_chat_wrap  = null;
            } else {
                //console.log('不存在dom');
                return false;
            }

        },

        buildSmallLi: function(list, num) {
            var that = this;
            var html = '';
            var class_name = '';
            j = 0;
            for (var i = list.length; i >= 0; i--) {
                if (list[i] != undefined) {
                    //聊天消息过滤
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

                    if (i < num && j < num) {
                        class_name = '';
                        if  (list[i].rank != undefined && list[i].rank < 4 && list[i].rank > 0) {
                            class_name = ' num rank' + list[i].rank;
                        }
                        if (typeof list[i].tree != 'undefined' && list[i].tree > 1) {
                            class_name += ' num tree' + list[i].tree;
                        }
                        if (list[i].type == 0) { //公共
                            if (list[i].uid == player.info.uid) {
                                html += '<li class="public"><div><span class="type">【' + lang('world') + '】</span><span class="name' + class_name + '"><em>' + lang('you') + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                            } else {
                                html += '<li class="public"><div><span class="type">【' + lang('world') + '】</span><span class="name under' + class_name + '" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                            }
                        } else if (list[i].type == 1) { //私聊
                            if (list[i].mine != undefined) {
                                html += '<li class="private"><div><span class="type">【' + lang('private') + '】</span><span class="name"><em>' + lang('you') + '</em></span> ' + lang('chat_dui') + ' <span class="under name" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                            } else {
                                html += '<li class="private"><div><span class="type">【' + lang('private') + '】</span><span class="name under" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span>&nbsp;' + lang('chat_dui') + ' <span class="name"><em>' + lang('you') + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                            }
                        } else if (list[i].type == 3) { //战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 4) { //过图战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 5) { //将魂战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 6) { //国家类型
                            if (list[i].system == 1) {
                                html += '<li class="country"><div><span class="type">【' + lang('country') + '】</span></div><p>' + list[i].message + '</p></li>';
                            } else {
                                if (list[i].uid == player.info.uid) {
                                    html += '<li class="country"><div><span class="type">【' + lang('country') + '】</span><span class="name' + class_name + '"><em>' + lang('you') + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                                } else if (list[i].uid != player.info.uid) {
                                    html += '<li class="country"><div><span class="type">【' + lang('country') + '】</span><span class="name under' + class_name + '" uid="' + list[i].uid + '"><em>' + list[i].nickname + '</em></span><b>&nbsp;' + lang('say') + '：</b></div><p>' + list[i].message + '</p></li>';
                                }
                            }
                        } else if (list[i].type == 7) { //系统类型
                            html += '<li class="system"><div><span class="type">【' + lang('system') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 8) { //金银洞战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 9) { //跨服演武战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 10) { //战神殿战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 11) { //四象圣域战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 38) { //通天塔战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 12) { //武斗会战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 49) { //铜雀台战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        } else if (list[i].type == 53) { //平乱战报
                            html += '<li class="battlereport"><div><span class="type">【' + lang('battle_report') + '】</span></div><p>' + list[i].message + '</p></li>';
                        }
                        j++;
                    }
                }
            }
            return html;
        },

        /*以下为转换的方法*/
        /*正则匹配*/
        transferMsg2Array: function(msg) {
            //return msg.match(/\{[^\}]*\}/ig);
            return msg.match(/\{[^\}]*/ig);
        },

        /*创建替换的html*/
        buildTag: function(type, str, id) {
            //console.log('type = ' + type);
            //console.log('html = ' + html);
            //console.log('id = ' + id);
            /*所有组合的元素统一用span,
                人统一用<span class="name"><em>name</em></span>
                别人加上class:under
                物品就是<span class="xxx">
                链接也是<span class="xxx">
            */
            var html = '';
            if (type == 1) {
                /*用户*/
                if (id != undefined) {
                    if (id == player.info.uid) {
                        html += '<span class="name"><em>' + lang('you') + '</em></span>';
                    } else {
                        if (id == 0) {
                            html += '<span class="name"><em>' + str + '</em></span>';
                        } else {
                            html += '<span class="name under "' + (id != undefined ? ' uid="' + id + '"': '') + '><em>' + str + '</em></span>';
                        }
                    }
                }
            } else if (type == 2) {
                /*pm战报*/
                html += '<span type="message" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 3) {
                /*任务战报*/
                html += '<span type="map" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 4) {
                /*将魂战报*/
                html += '<span type="tower" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 5) {
                /*元宝*/
                html += '<span class="gold num">' + str + '</span>';
            } else if (type == 6) {
                /*银币*/
                html += '<span class="silver num">' + str + '</span>';
            } else if (type == 7) {
                /*玉石*/
                html += '<span class="jade num">' + str + '</span>';
            } else if (type == 8) {
                /*军令*/
                html += '<span class="act num">' + str + '</span>';
            } else if (type == 9) {
                /*武将*/
                html += '<span class="hero">' + str + '</span>';
            } else if (type == 10) {
                /*名次*/
                html += '<span>' + str + '</span>';
            } else if (type == 11) {
                /*金银洞战报*/
                html += '<span type="treasure" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 12) {
                /*加入队伍*/
                var str_arr = str.split('|');
                html += ' <span class="elitejoin" fid="' + str_arr[0] + '" teamid="' + str_arr[1] + '" countryid="' + str_arr[2] + '"> ' + lang('join_team') + ' </span> ';
            } else if (type == 13) {
                /*跨服演武战报*/
                html += '<span type="worldarena" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 14) {
                /*国战战报*/
                html += '<span type="countrybattle" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 15) {
                /*战神殿战报*/
                var str_arr = id.split('|');
                html += '<span type="champions" class="viewbattle" viewid="' + str_arr[0] + '" stage="' + str_arr[1] + '">' + str + '</span>';
            } else if (type == 16) {
                /*四向圣域战报*/
                var str_arr = id.split('|');
                html += '<span type="sanctum" class="viewbattle" viewid="' + str_arr[0] + '" stage="' + str_arr[1] + '">' + str + '</span>';
            } else if (type == 17) {
                /*通天塔战报*/
                html += '<span type="heaven" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 19) {
                /*武斗会战报*/
                var str_arr = id.split('|');
                html += '<span type="war" class="viewbattle" viewid="' + str_arr[0] + '" stage="' + str_arr[1] + '">' + str + '</span>';
            } else if (type == 49) {
                /*铜雀台战报*/
                html += '<span type="beauty" class="viewbattle"' + (id != undefined ? ' viewid="' + id + '"': '') + '">' + str + '</span>';
            } else if (type == 53) {
	    	/* 平乱战报 */
                var str_arr = id.split('|');
                html += '<span type="pacify" class="viewbattle" viewid="' + str_arr[0] + '" stage="' + str_arr[1] + '">' + str + '</span>';
            }
            //console.log('html = ' + html);
            return html;
        },

        /*主方法*/
        transferMsgFunc: function(msg) {
            var that = this;
            var list = that.transferMsg2Array(msg);
            if (list == null) {
                list = that = null;
                return msg;
            }
            var tempArr = new Array;
            for (var i = 0; i < list.length; i++) {
                tempArr = list[i].toString().replace(/{/g, '').split(',');
                //console.log(tempArr);
                msg = msg.replace(list[i].toString() + '}', that.buildTag(tempArr[0], tempArr[1], tempArr[2]));
            }
            list = list = that = null;
            return msg;
        },
        /*以上为转换的方法*/

        clear: function() {
            console.log('清理聊天CD');
            timer.removeListener(this);
            this.on = false;
            this.s = 0;

            this.allChatData = null;
            this.newData = null;
            this.needS = 0;

            this.hornList = null;
            this.hasPrivate = null;
            this.chatBtn = null;

            this.chatThread = null;
            if (this.timeout != null) {
                clearTimeout(this.timeout);
                this.timeout = null;
            }

            this.isBackForInit = false;
            this.isBackAndAfterInit = false;
            this.isError = false;
            this.errorFunc = null;
            this.isTimeout = false;

        },

    };

})();

/*矿区的倒计时*/
(function() {
    mineCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    mineCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#mine_time').find('em');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html('00:00:00');
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (mineWorld.mineData != undefined) {
                for (var i = 1; i <= 12; i++) {
                    if (mineWorld.mineData[i] != undefined && mineWorld.mineData[i].time > 0) {
                        mineWorld.mineData[i].time--;
                    }
                }
            }

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('00:00:00');
                /*只有当是放弃按钮时才可以转变*/
                if (mineWorld && mineWorld.e) {
                    var button = mineWorld.dialogDom.find('#minepop_msg_conetnt').find('button');
                    if (button.attr('id') == 'm_giveup_btn') {
                        button.attr({
                            'id': 'm_get_btn'
                        }).html(lang('shouhuo'));
                    }
                }
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s));
            }
        },

        clear: function() {
            console.log('清理矿战CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/*演武榜的倒计时*/
(function() {
    arenaCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    arenaCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#arena_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s)).addClass('isRun');
                $('#area_clear_btn').show();
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html('无');
                $('#area_clear_btn').hide();
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('no')).removeClass('isRun');
                $('#area_clear_btn').hide();
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s)).addClass('isRun');
            }
        },

        clear: function() {
            console.log('清理演武榜CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/* 更新用户数据CD：CD['updatePlayer'] */
(function() {
    updatePlayerCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    updatePlayerCdClass.prototype = {
        /* 初始化调用 */
        init: function() {
            //console.log('updatePlayer：开始执行');
            this.s = 0;
            timer.addListener(this);
            this.on = true;
            this.step();
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s++;
            if (this.s == 70) {
                //console.log('updatePlayer：开始更新数据');
                if (typeof  championsWorld != 'undefined' && championsWorld.e != null) {
                    //console.log('do nothing');
                } else {
                    player && player.reloadInfo();
                }
                this.s = 0;
            }
        },

        /* 更新时调用 */
        update: function() {
            if (this.on == false) { //重新启动
                this.init();
            } else {
                this.step();
            }
        },

        clear: function() {
            console.log('清理更新player的CD');
            timer.removeListener(this);
            this.on = false;
            this.s = 0;
        },

    };

})();

/* 竞速CD：CD['rushCd'] */
(function() {
    rushCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    rushCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#rush_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                rushpop.e.find('li').find('button').attr('class', 'pop_btn_middle_disable btn').html(lang('had_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理竞速CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 竞技场领取：CD['areanRewardCd'] */
(function() {
    areanRewardCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    areanRewardCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#arena_getReward');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s)).attr({
                    "class": 'btn pop_btn_middle_disable'
                });
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.attr({
                    "class": 'btn pop_btn_middle_disable'
                });
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('receive')).attr({
                    "class": 'btn pop_btn_middle'
                });
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s));
            }
        },

        clear: function() {
            console.log('清理演武榜领取奖励CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 整军演武CD：CD['exerciseCd'] */
(function() {
    exerciseCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    exerciseCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('.date');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                exercisepop.e.find('li').find('button').attr('class', 'pop_btn_middle_disable btn').html(lang('had_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理竞速CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 祭祀CD：CD['sacrificeCd'] */
(function() {
    sacrificeCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    sacrificeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#sacrifice_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                sacrificepop.e.find('#sacrifice_gods').find('button').attr('class', 'pop_btn_middle_disable btn').html(lang('had_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理祭祀CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* BOSSlife：CD['bosslifeCd'] */
(function() {
    bosslifeCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    bosslifeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#action');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('battle_start'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (bossWorld && bossWorld.indexdata) {
                    bossWorld.indexdata.cd = 0;
                }
                this.e.html(lang('battle_start'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理战斗复活CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* bossin：CD['bossinCd'] */
(function() {
    bossinCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    bossinCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#bossin_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (bossWorld && bossWorld.indexdata) {
                    bossWorld.indexdata.countdown = 0;
                }
                this.e.html(lang('had_end'));
                this.clear();
                setTimeout(function() {
                    if (bossWorld && bossWorld.indexdata != null) { //在BOSS界面等到0，进入排行榜
                        xGame.loadWorld(bossWorld.oldworld);
                        xGame.showpop('bossband');
                    }
                },
                1000);
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理BOSS CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 神将台CD：CD['godwillCd'] */
(function() {
    godwillCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    godwillCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#godwill_endtime').children('p');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('');
                $('#godwill_endtime').addClass('isClose');
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理神将台倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 四方来朝CD：CD['worshipCd'] */
(function() {
    worshipCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    worshipCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#worship_t');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理四方来朝CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 返利：CD['rebate'] */
(function() {
    rebateCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    rebateCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#rebate_endtime');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('activity_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log(lang('pay_back_cd'));
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 中秋CD：CD['mooncakeCd'] */
(function() {
    mooncakeCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    mooncakeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#mooncake_t');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理中秋CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 国战：CD['cbattle'] */
(function() {
    cbattleCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    cbattleCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#cbattleworld_page').find('#enterpage');
            this.s = s;

            if (s > 0) {
                this.e.find('.timer').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.find('.timer_tips').hide();
                this.e.find('.timer').html('').hide();
                this.e.find('.startfight').hide();
                this.clear();
                setTimeout(function() {
                    if (typeof countrybattleWorld != 'undefined' && countrybattleWorld.e != null) {
                        countrybattleWorld.fight();
                    }
                },
                500);

            } else {
                this.e.find('.timer').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除国战倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 黑市CD：CD['sacrificeCd'] */
(function() {
    blackmarketCdClass = function() {
        this.s;//倒计时秒数
        this.on = false;//开关
    };
    blackmarketCdClass.prototype = {
        /* 初始化调用 */
       init : function(s) {
            this.e = $('#blackmarket_cd');
            this.s = s;

            if(s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update : function(s) {
            if(this.on == false) {//重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step : function() {
            this.s--;

            if(this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                blackmarketpop.e.find('#blackmarketpop_wrap').find('button').attr('class','pop_btn_middle_disable btn').html(lang('blackmarket_is_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear : function() {
            console.log('清理黑市CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/*跨服演武的押注倒计时*/
(function() {
    worldarenabetCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    worldarenabetCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#worldarena_d2btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s)).addClass('isRun');
                timer.addListener(this);
                this.e.attr('class', 'btn pop_btn_middle_disable');
                this.on = true;
            } else {
                this.e.attr('class', 'btn pop_btn_middle').html(lang('receive'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('no')).removeClass('isRun');
                this.e.attr('class', 'btn pop_btn_middle').html(lang('receive'));
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s)).addClass('isRun');
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/*跨服演武的押注倒计时*/
(function() {
    worldarenarewardCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    worldarenarewardCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#worldarena_d1btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s)).addClass('isRun');
                timer.addListener(this);
                this.e.attr('class', 'btn pop_btn_middle_disable');
                this.on = true;
            } else {
                this.e.attr('class', 'btn pop_btn_middle').html(lang('receive'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('no')).removeClass('isRun');
                this.e.attr('class', 'btn pop_btn_middle').html(lang('receive'));
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s)).addClass('isRun');
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();


/* 战神殿开启倒计时：CD['championsopen'] */
(function() {
    championsopenCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    championsopenCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#championsworld_page').find('#champions_enter').find('#champions_enter_cd');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof championsWorld != 'undefined' && championsWorld.e != null) {
                        championsWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除战神殿开启倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 战神殿开启倒计时：CD['championswait'] */
(function() {
    championswaitCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    championswaitCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('div#champions_wait_cd');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof championsWorld != 'undefined' && championsWorld.e != null) {
                        championsWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除战神殿等待倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 战神殿报名倒计时：CD['championsjoin'] */
(function() {
    championsjoinCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    championsjoinCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#championsworld_page').find('#champions_enter').find('#champions_enter_join');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof championsWorld != 'undefined' && championsWorld.e != null) {
                        championsWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除战神殿加入倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 战神殿开战倒计时：CD['championspvp'] */
(function() {
    championspvpCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    championspvpCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#pvp_time');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            //最后一分钟不然支持，停止自动刷新
            if (this.s < 65) {
                if ($('button.champion_pvp_btn').hasClass('pop_btn_middle')) {
                    $('button.champion_pvp_btn').attr('class', 'btn pop_btn_middle_disable champion_pvp_btn');
                }

                if (typeof championsWorld != 'undefined' && championsWorld.autoRefresh != null) {
                    clearInterval(championsWorld.autoRefresh);
                    championsWorld.autoRefresh = null;
                    //championsWorld.refreshSupport(true);
                }
            }

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                if (typeof championsWorld != 'undefined' && championsWorld.e != null) {
                    if (championsWorld.stage != null) {
                        if (championsWorld.stage == 0) {
                            championsWorld.cdEndRefresh();
                        } else {
                            championsWorld.viewHistoryReport(championsWorld.roundid, championsWorld.stage);
                        }
                    }
                }

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除战神殿倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 战神殿同步倒计时：CD['championssync'] */
(function() {
    championssyncCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    championssyncCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('button.champion_pvp_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                if (this.e.hasClass('pop_btn_middle')) {
                    this.e.attr('class', 'btn pop_btn_middle_disable champion_pvp_btn');
                }
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('champions_sync'));
                if (CD['championspvp'] != undefined && CD['championspvp'].s > 65) {
                    this.e.attr('class', 'btn pop_btn_middle champion_pvp_btn');
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除战神殿同步倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();


/* 圣诞返利倒计时：CD['championsjoin'] */
(function() {
    christmaspayCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    christmaspayCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#christmaspaypop').find('#christmaspay_date');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#christmaspaypop').find('#christmaspay_date').html(lang('no'));
                $('#christmaspaypop').find('#christmaspay_list_scroller').find('button').attr('class', 'btn pop_btn_small_disable').html(lang('blackmarket_is_end'));
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 圣诞神将倒计时：CD['championsjoin'] */
(function() {
    christmasgeneralCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    christmasgeneralCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#christmasgeneralpop').find('#christmasgeneral_date');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    $('#christmasgeneralpop').find('#christmasgeneral_date').html(lang('no'));
                    $('#christmasgeneralpop').find('.buy_btn').attr('class', 'btn pop_btn_middle_disable buy_btn').html(lang('blackmarket_is_end'));
                },
                500);
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* bossin：CD['cBoss']  国家boss活动cd */
(function() {
    cBossCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    cBossCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#cbossin_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (bossWorld && bossWorld.indexdata) {
                    bossWorld.indexdata.countdown = 0;
                }
                this.e.html(lang('had_end'));
                this.clear();
                setTimeout(function() {
                    if (bossWorld && bossWorld.indexdata != null) { //在BOSS界面等到0，进入排行榜
                        xGame.loadWorld(bossWorld.oldworld);
                        xGame.showpop('bossband');
                    }
                },
                1000);
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理国家BOSS CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();


/* BOSSlife：CD['cBossButton'] */
(function() {
    cBossButtonCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    cBossButtonCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#c_action');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('battle_start'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (countrybossWorld && countrybossWorld.indexdata) {
                    countrybossWorld.indexdata.cd = 0;
                }
                this.e.html(lang('battle_start'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理国家战斗按钮CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

(function() {
    godmineCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    godmineCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#godmine_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('activity_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 春节返利倒计时：CD['newyearpay'] */
(function() {
    newyearpayCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    newyearpayCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#newyearpaypop').find('#newyearpay_date');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#newyearpaypop').find('#newyearpay_date').html(lang('no'));
                $('#newyearpaypop').find('#newyearpay_list_scroller').find('button').attr('class', 'btn pop_btn_small_disable').html(lang('blackmarket_is_end'));
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

//蹴鞠大赛
(function() {
    ball1CdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    ball1CdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#ball_cd1');
            this.s = s;

            if (s > 0) {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (kemaripop) {
                    if (kemaripop.times > 0) {
                        this.e.html(lang('kemaripop_free', [kemaripop.times]));
                        this.e.parent().addClass('free');
                    } else {
                        this.e.html(lang('kemaripop_times_over'));
                    }
                    kemaripop.refreshResultBtnLabel(kemaripop.times);
                }
                xGame.islock = true;
                var time = setTimeout(function () {
                    xGame.unlock();
                    clearTimeout(time);
                    time = null;
                }, 1000);
                this.clear();
            } else {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

(function() {
    ball2CdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    ball2CdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#ball_cd2');
            this.s = s;

            if (s > 0) {
                this.e.html(lang('kemaripop_time_free', [helper.timeToClock(this.s)]));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (kemaripop) {
                    if (kemaripop.times > 0) {
                        this.e.html(lang('kemaripop_free', [kemaripop.times]));
                        this.e.parent().addClass('free');
                    } else {
                        this.e.html(lang('kemaripop_times_over'));
                    }
                    kemaripop.refreshResultBtnLabel(kemaripop.times);
                }
                xGame.islock = true;
                var time = setTimeout(function () {
                    xGame.unlock();
                    clearTimeout(time);
                    time = null;
                }, 1000);
                this.clear();
            } else {
                this.e.html(lang('kemaripop_time_free', [helper.timeToClock(this.s)]));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

(function() {
    kemaripopCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    kemaripopCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#rank_reward_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (kemaripop) {
                kemaripop.rewardCd--;
            }
            if (this.s <= 0) {
                this.s = 0;
                if (kemaripop) {
                    kemaripop.rewardCd = 0;
                }
                this.e.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 我爱世界杯*/
(function() {
    worldcupCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    worldcupCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#worldcup_cd');
            this.e2 = $('#worldcup_rank_rbtn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.addClass('end').html('');
                this.e2.attr('class', 'btn pop_btn_middle').html(lang('receive'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.e2 = null;
            this.s = 0;
        }
    };

})();

(function() {
    worldcupRankCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    worldcupRankCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#rank_reward_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (worldcuppop) {
                worldcuppop.rewardCd --;
            }
            if (this.s <= 0) {
                this.s = 0;
                if (worldcuppop) {
                    worldcuppop.rewardCd = 0;
                }
                this.e.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

(function() {
    egg1CdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    egg1CdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#egg_cd1');
            this.s = s;

            if (s > 0) {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('eggpop_free'));
                this.e.parent().addClass('free');
                xGame.islock = true;
                var time = setTimeout(function () {
                    xGame.unlock();
                    clearTimeout(time);
                    time = null;
                }, 1000);
                this.clear();
            } else {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

(function() {
    egg2CdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    egg2CdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#egg_cd2');
            this.s = s;

            if (s > 0) {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('eggpop_free'));
                this.e.parent().addClass('free');
                xGame.islock = true;
                var time = setTimeout(function () {
                    xGame.unlock();
                    clearTimeout(time);
                    time = null;
                }, 1000);
                this.clear();
            } else {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

(function() {
    egg3CdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    egg3CdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#egg_cd3');
            this.s = s;

            if (s > 0) {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('eggpop_free'));
                this.e.parent().addClass('free');
                xGame.islock = true;
                var time = setTimeout(function () {
                    xGame.unlock();
                    clearTimeout(time);
                    time = null;
                }, 1000);
                this.clear();
            } else {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 海外贸易倒计时：CD['overseastrade'] */
(function() {
    overseastradeCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    overseastradeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#overseastrade_cd');
            this.s = s;

            if (s > 0) {
                $('#overseastrade_cd').css('display','block');
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#overseastrade_cd').css('display','none');
                //倒计时到了，刷新一下页面
                if (overseastradepop.e != null) {
                    overseastradepop.showShipByPage();
                }

            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 新手3日礼包 */
(function() {
    newbiegiftCdClass = function() {
        this.s; //倒计时秒数 是数组哟
        this.on = false; //开关
        this.e = null;//是数组哟
        this.isclear = null;
    };
    newbiegiftCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#newbiegift_page').find('.fetch_btn');
            this.s = s;
            this.isclear = [false, false, false];

            for(var i = 0; i < this.s.length; i++) {
                if (this.s[i] > 0) {
                    $(this.e[i]).html(helper.timeToString(this.s[i])).addClass('incd');
                    this.on = true;
                } else if(this.s[i] == 0) {//当有0进来时，说明不再处理
                    this.isclear[i] = true;
                }
            }
            if(this.on == true) {
                timer.addListener(this);
            }

        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            var allclear = true;

            for(var i = 0; i < this.s.length; i++) {
                this.s[i]--;

                if (this.s[i] <= 0) {
                    this.s[i] = 0;

                    if(this.isclear[i]) {
                        continue;
                    }

                    $(this.e[i]).html(lang('newbiegift_enable_fetch')).attr('class', 'btn fetch_btn pop_btn_middle');
                    this.isclear[i] = true;

                } else {
                    allclear = false;
                    $(this.e[i]).html(helper.timeToString(this.s[i]));
                }
            }

            if(allclear) {
                this.clear();
            }

        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = null;
            this.isclear = [false, false, false];
        }

    };

})();

/* 年夜饭倒计时：CD['newyeardinner'] */
(function() {
    newyeardinnerCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    newyeardinnerCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#newyeardinner_cd');
            this.s = s;

            if (s > 0) {
                $('#newyeardinner_cd').css('display','block');
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#newyeardinner_cd').css('display','none');
                $('#newyeardinner_reward_btn').attr('class','btn pop_btn_middle');

            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 免费至尊继承倒计时：CD['inherit'] */
(function() {
    inheritCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    inheritCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#inherit_cd');
            this.s = s;

            if (s > 0) {
                this.e.css('display','block');
                if(player.info.free_inherit_site == 1) {
                    this.e.html(lang('ordinary_inherit_cd') +'：'+ helper.timeToClock(this.s));
                } else if(player.info.free_inherit_site == 2) {
                    this.e.html(lang('free_inherit_strengthen') +'：'+ helper.timeToClock(this.s));
                } else if(player.info.free_inherit_site == 3) {
                    this.e.html(lang('free_inherit_platinum') +'：'+ helper.timeToClock(this.s));
                } else if(player.info.free_inherit_site == 4) {
                    this.e.html(lang('inherit_cd') +'：'+ helper.timeToClock(this.s));
                }
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#inherit_cd').css('display','none');
                $('#inheritpop_li' + player.info.free_inherit_site).find('.t2').addClass('free').html('');
                $('#inheritpop_li' + player.info.free_inherit_site).find('.t5').removeClass('off_5');
            } else {
                if(player.info.free_inherit_site == 1) {
                    this.e.html(lang('ordinary_inherit_cd') +'：'+ helper.timeToClock(this.s));
                } else if(player.info.free_inherit_site == 2) {
                    this.e.html(lang('free_inherit_strengthen') +'：'+ helper.timeToClock(this.s));
                } else if(player.info.free_inherit_site == 3) {
                    this.e.html(lang('free_inherit_platinum') +'：'+ helper.timeToClock(this.s));
                } else if(player.info.free_inherit_site == 4) {
                    this.e.html(lang('inherit_cd') +'：'+ helper.timeToClock(this.s));
                }
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();


/* 暴打名将冷却时间：CD['beatengod'] */
(function() {
    beatengodCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    beatengodCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#beatengod_cd');
            this.e2 = $('#beatengod_rank_rbtn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.addClass('end').html('');
                this.e2.attr('class', 'btn pop_btn_middle').html(lang('receive'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.e2 = null;
            this.s = 0;
        }
    };

})();

/* 大熊返利冷却时间：CD['bear'] */
(function() {
    bearCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    bearCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#bear_cd');
            this.s = s;

            if (s > 0) {
                this.e.css('display','block');
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#bear_cd').html(lang('activity_is_end'));
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 清明冷却时间：CD['qingming'] */
(function() {
    qingmingCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    qingmingCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#qingming_cd');
            this.e2 = $('#qingming_rank_rbtn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (typeof qingmingpop != 'undefined' && qingmingpop.e != null) {
                    qingmingpop.cdEnd();
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 更换国家进入国战倒计时：CD['leavecountry'] */
(function() {
    leavecountryCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    leavecountryCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#enter_country_battle_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 选手确定倒计时：CD['gvgwait'] */
(function() {
    gvgwaitCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    gvgwaitCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('div#gvg_cd').find('b');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof gvgWorld != 'undefined' && gvgWorld.e != null) {
                        gvgWorld.cdEndRefresh();
                    }
                },
                500);
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除群服争霸等待倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 群服争霸开战倒计时：CD['gvgbattle'] */
(function() {
    gvgbattleCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    gvgbattleCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('div#gvg_cd').find('b');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            //最后5分钟不让押注，同步，停止自动刷新
            if (this.s < 300) {
                if ($('button.gvg_btn').hasClass('pop_btn_middle')) {
                    $('button.gvg_btn').attr('class', 'btn pop_btn_middle_disable gvg_btn');
                }

                if (typeof gvgWorld != 'undefined' && gvgWorld.autoRefresh != null) {
                    clearInterval(gvgWorld.autoRefresh);
                    gvgWorld.autoRefresh = null;
                    //gvgWorld.refreshBet(true);
                }
            }

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                if (typeof gvgWorld != 'undefined' && gvgWorld.e != null) {
                    if (gvgWorld.round != null) {
                        if (gvgWorld.isBlock) {
                            gvgWorld.cdEndRefresh();
                        } else {
                            gvgWorld.getBattleData();
                        }
                    }
                }

            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除群服争霸战斗倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 群服争霸同步倒计时：CD['gvgsync'] */
(function() {
    gvgsyncCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    gvgsyncCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('button.gvg_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                if (this.e.hasClass('pop_btn_middle')) {
                    this.e.attr('class', 'btn pop_btn_middle_disable gvg_btn');
                }
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('gvg_world_sync_text'));
                if (CD['gvgbattle'] != undefined && CD['gvgbattle'].s > 300) {
                    this.e.attr('class', 'btn pop_btn_middle gvg_btn');
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除群服争霸同步倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 限售神将倒计时：CD['limitedgod'] */
(function() {
    limitedgodCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    limitedgodCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('p#limitedgod_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.addClass('end').html('');
                if (typeof limitedgodpop != 'undefined' && limitedgodpop.mainDom != undefined) {
                    limitedgodpop.mainDom.find('.pop_btn_middle').attr('class', 'btn pop_btn_middle_disable').html(lang('blackmarket_is_end'));
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 武将折旧倒计时：CD['salehero'] */
(function() {
    saleheroCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    saleheroCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('div#hero_sale_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s)).css('display', 'block');
                if (typeof musterpop != 'undefined' && musterpop.e != undefined) {
                    musterpop.e.find('.pop_btn_small').css('display', 'none');
                }
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.css('display', 'none').html('');
                if (typeof musterpop != 'undefined' && musterpop.e != undefined) {
                    musterpop.e.find('.pop_btn_small').css('display', 'block');
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 武将进阶倒计时：CD['advanced'] */
(function() {
    advancedCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    advancedCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('div#advancedpop_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s)).css('display', 'block');
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.css('display', 'none').html('');
                if (typeof advancedpop != 'undefined' && advancedpop.e != undefined) {
                    advancedpop.checkInheritBtn();
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/*国家矿区的倒计时*/
(function() {
    countrymineCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    countrymineCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('li#mine_time').find('em');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html('00:00:00');
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (countrymineWorld.mineData != undefined) {
                for (var i = 1; i <= 12; i++) {
                    if (countrymineWorld.mineData[i] != undefined && countrymineWorld.mineData[i].time > 0) {
                        countrymineWorld.mineData[i].time--;
                    }
                }
            }

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('00:00:00');
                /*只有当是放弃按钮时才可以转变*/
                var button = countrymineWorld.dialogDom.find('#countrymine_msg_conetnt').find('button');
                if (button.attr('id') == 'm_giveup_btn') {
                    button.attr({
                        'id': 'm_get_btn'
                    }).html(lang('shouhuo'));
                }
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s));
            }
        },

        clear: function() {
            console.log('清理国家矿CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/*神树的倒计时*/
(function() {
    sacredtreeCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    sacredtreeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#sacredtreepop_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html('').addClass('end');
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            
            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('end');
                if (typeof sacredtreepop != 'undefined' && sacredtreepop.e != undefined) {
                    sacredtreepop.closeAllBtn();
                }
                
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s));
            }
        },

        clear: function() {
            console.log('清理神树CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/*周年庆的倒计时*/
(function() {
    anniversaryCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    anniversaryCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#anniversary_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToString(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            
            if (this.s <= 0) {
                this.s = 0;
                if (typeof anniversarypop != 'undefined' && anniversarypop.e != undefined) {
                    anniversarypop.e.find('#anniversary_receive').removeClass('disable');
                    $('#anniversary_cd').css('display', 'none');
                }
                
                this.clear();
            } else {
                this.e.html(helper.timeToString(this.s));
            }
        },

        clear: function() {
            console.log('清理周年庆CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

(function() {
    zhuluCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    zhuluCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#zhulupop_cd');
            this.e2 = $('#fetch_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s)).show();
                this.e2.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('activity_end'));
                this.e2.attr('class', 'btn pop_btn_middle fetch_btn').html(lang('receive'));
                $('#zhulupop_nearlist').find('.attack_btn').hide();
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
                this.e2.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.e2 = null;
            this.s = 0;
        }

    };

})();

/* 春节VIP回馈倒计时：CD['viprepay'] */
(function() {
    viprepayCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    viprepayCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#viprepaypop').find('#viprepay_date');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#viprepaypop').find('#viprepay_date').html(lang('no'));
                $('#viprepaypop').find('#viprepay_list_scroller').find('button').attr('class', 'btn pop_btn_small_disable').html(lang('blackmarket_is_end'));
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/*圣诞礼包*/
(function() {
    christmasgiftCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    christmasgiftCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#christmasgift_list').find('.cd');
            this.s = s;
            
            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.clearBtn();
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                if (christmasgiftpop.e && christmasgiftpop.isRefresh == false) {
                    this.s = s;
                }
                this.e = $('#christmasgift_list').find('.cd');
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            
            if (this.s <= 0) {
                this.s = 0;
                this.clearBtn();
                
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },
        
        clearBtn: function() {
            //倒计时到后，初始化按钮价格，数量
            var obj = $('#christmasgift_list').find('li');
            var temp;
            var btn;
            var limited;
            if (obj) {
                for (var i = 0; i < obj.length; i ++) {
                    temp = obj.eq(i);
                    temp.removeClass('show_cd');
                    btn = temp.find('button');
                    btn.attr('class', 'btn pop_btn_middle').html(btn.attr('str'));
                    limited = temp.find('.limited');
                    if (limited) {
                        limited.html(limited.attr('str'));
                    }
                }
            }
            
            //设置是否是最后一天
            if (christmasgiftpop.e && christmasgiftpop.lastDay == 2) {
                $('#christmasgift_list').find('.cd').html(lang('anniversarypop_last_day')).css('display', 'block');
            }
        },

        clear: function() {
            console.log('清理圣诞礼包CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();


/* 春节商城CD：CD['springshopCd'] */
(function() {
    springshopCdClass = function() {
        this.s;//倒计时秒数
        this.on = false;//开关
    };
    springshopCdClass.prototype = {
        /* 初始化调用 */
       init : function(s) {
            this.e = $('#springshop_cd');
            this.s = s;

            if(s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update : function(s) {
            if(this.on == false) {//重新启动
                this.init(s);
                this.e.removeClass('end');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step : function() {
            this.s--;

            if(this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('end');
                if (springshoppop && springshoppop.e) {
                    springshoppop.itemDom.find('button').attr('class','btn pop_btn_middle_disable').html(lang('had_end'));
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear : function() {
            console.log('清理春节商城CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* bossin：CD['cBoss']  年兽活动cd */
(function() {
    nianCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    nianCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#nian_in_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                
                if (nianWorld && nianWorld.indexdata) {
                    nianWorld.indexdata.countdown = 0;
                }
                this.e.html(lang('had_end'));
                this.clear();

                if (nianWorld && nianWorld.indexdata && nianWorld.indexdata.countdown <= 0 && nianWorld.indexdata.status == 1) {
                    nianWorld.achieve(4);
                }
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理年兽 CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();


/* BOSSlife：CD['cBossButton'] */
(function() {
    nianButtonCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    nianButtonCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#action');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('battle_start'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (nianWorld && nianWorld.indexdata) {
                    nianWorld.indexdata.cd = 0;
                }
                this.e.html(lang('battle_start'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理年兽战斗按钮CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 年兽奖励领取CD */
(function() {
    nianRankButtonCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    nianRankButtonCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#nian_rank_fetch.cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.attr('class', 'btn pop_btn_middle').html(lang('receive'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理年兽领奖 CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 春节抽奖领取倒计时*/
(function() {
    newyearlotteryCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    newyearlotteryCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#newyearlottery_reward_btn');
            this.s = s;

            this.e.html(helper.timeToClock(this.s)).attr('class', 'btn disable');
            timer.addListener(this);
            this.on = true;
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('receive')).attr('class', 'btn btn_middle');
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        },

    };

})();

/* 新服大礼包倒计时 */
(function() {
    newgiftCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    newgiftCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#newgift_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#newgift_time').html(lang('no'));
                $('#newgift_list').find('button').attr('class', 'btn pop_btn_small_disable').html(lang('had_end'));
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 装备精炼倒计时 */
(function() {
    equiprefineCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    equiprefineCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#refresh_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                if (equiprefinepop.isshow == true) {
                    equiprefinepop.refreshMaster(1);
                }
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();
// 寻宝
(function() {
    acttreasureCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    acttreasureCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#rank_reward_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 战神殿报名倒计时：CD['championreward'] */
(function() {
    championrewardCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    championrewardCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#championsrewardpop').find('.reward_center_cd').find('.cd');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                $('#championsrewardpop').find('.reward_center_cd').hide();
                $('#championsrewardpop').find('#champions_join').show();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除战神殿开启倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 武斗会开启倒计时：CD['waropen'] */
(function() {
    waropenCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    waropenCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#war_enter_cd');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof warWorld != 'undefined' && warWorld.e != null) {
                        warWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除武斗会开启倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 武斗会开启倒计时：CD['warwait'] */
(function() {
    warwaitCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    warwaitCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#war_wait_cd');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof warWorld != 'undefined' && warWorld.e != null) {
                        warWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除武斗会等待倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 武斗会报名倒计时：CD['warjoin'] */
(function() {
    warjoinCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    warjoinCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#war_enter_join');
            this.s = s;

            if (s > 0) {
                this.e.find('.countdown').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof warWorld != 'undefined' && warWorld.e != null) {
                        warWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.find('.countdown').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除武斗会加入倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 武斗会开战倒计时：CD['warpvp'] */
(function() {
    warpvpCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    warpvpCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#pvp_time');
            this.s = s;

            if (s > 0) {
                this.e.find('span').html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            //最后一分钟不然支持，停止自动刷新
            if (this.s < 303) {
                if ($('button.war_pvp_btn').hasClass('pop_btn_middle')) {
                    $('button.war_pvp_btn').attr('class', 'btn pop_btn_middle_disable war_pvp_btn');
                }

                if (typeof warWorld != 'undefined' && warWorld.autoRefresh != null) {
                    clearInterval(warWorld.autoRefresh);
                    warWorld.autoRefresh = null;
                    //warWorld.refreshSupport(true);
                }
            }

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                if (typeof warWorld != 'undefined' && warWorld.e != null) {
                    if (warWorld.stage != null) {
                        if (warWorld.stage == 0) {
                            warWorld.cdEndRefresh();
                        } else {
                            warWorld.viewHistoryReport(warWorld.roundid, warWorld.stage);
                        }
                    }
                }

            } else {
                this.e.find('span').html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除武斗会倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

/* 武斗会同步倒计时：CD['warsync'] */
(function() {
    warsyncCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    warsyncCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('button.war_pvp_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                if (this.e.hasClass('pop_btn_middle')) {
                    this.e.attr('class', 'btn pop_btn_middle_disable war_pvp_btn');
                }
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('war_sync'));
                if (CD['warpvp'] != undefined && CD['warpvp'].s > 303) {
                    this.e.attr('class', 'btn pop_btn_middle war_pvp_btn');
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除武斗会同步倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

/* 武斗会晋级倒计时：CD['warrise'] */
(function() {
    warriseCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    warriseCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#warworld_rise_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.clear();
                setTimeout(function() {
                    if (typeof warWorld != 'undefined' && warWorld.e != null) {
                        warWorld.cdEndRefresh();
                    }
                },
                500);

            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清除武斗会晋级倒计时');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

//过关斩将
(function() {
    breakthroughCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    breakthroughCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#breakthrough_btn1');
            this.s = s;

            if (s > 0) {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]) + '<em class="spend_gold">x' + breakthroughWorld.price1 + '</em>');
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            if (this.s <= 0) {
                this.s = 0;
                if (breakthroughWorld) {
                    this.e.html(lang('breakthrough_free', [breakthroughWorld.times]));
                }
                xGame.islock = true;
                var time = setTimeout(function () {
                    xGame.unlock();
                    clearTimeout(time);
                    time = null;
                }, 1000);
                this.clear();
            } else {
                this.e.html(lang('eggpop_time_free', [helper.timeToClock(this.s)]) + '<em class="spend_gold">x' + breakthroughWorld.price1 + '</em>');
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();


/*绝版神将*/
(function() {
    outprintCdClass = function() {
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    outprintCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#duration');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
                this.e.removeClass('isClose');
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html('').addClass('isClose');
                //exercisepop.e.find('li').find('button').attr('class', 'pop_btn_middle_disable btn').html(lang('had_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            console.log('清理绝版神将CD');
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();
/*灵雀赠礼的倒计时*/
(function() {
    valentineCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    valentineCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#valentine_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            
            if (this.s <= 0) {
                this.s = 0;
                if (typeof valentinepop != 'undefined' && valentinepop.e != undefined) {
                    valentinepop.e.find('#valentine_reward_btn').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                    this.e.html(lang('had_end'));
                }
                
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

// 铜雀台免战CD
(function() {
    beautyAvoidCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    beautyAvoidCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#beauty_avoid_cd');
            s = parseInt(s) || 0;
            this.s = s;

            if (s > 0 && !isNaN(s)) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                if (this.e && this.e.parent()) {
                    this.e.parent().hide();
                }
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                beautypop.avoiding = false;
                this.e.parent().hide();
                var dom = $('#beauty_avoid_war');
                dom.removeClass('pop_btn_smaller_disable').addClass('pop_btn_smaller');
                dom = null;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 铜雀台美人技能生效CD
(function() {
    beautySkillCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    beautySkillCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#beauty_skill_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.parent().hide();
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 铜雀台问候CD
(function() {
    beautyInteractGreetCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    beautyInteractGreetCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#beauty_interact_greet').find('.colling_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.parent().hide();
                beautypop.checkInteractBtn();
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 铜雀台升级CD
(function() {
    beautyLevelUpCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    beautyLevelUpCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#beauty_levelup_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;

                if (beautypop.isshow == true) {
                    beautypop.levelUp(beautypop.nowBeautyId, 2);
                }

                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
                beautypop.runClock(this.s);
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 抢夺碎片刷新列表CD
(function() {
    beautyRefreshCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    beautyRefreshCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#robchip_refresh_btn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                this.e.html(lang('beautypop_chip_refresh')).removeClass('pop_btn_small_disable').addClass('pop_btn_small');
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 名将成就周年庆限时CD
(function() {
    heromanualLimitCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    heromanualLimitCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('.heromanualcd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                $('.cp6').remove();
                $('.cp7').remove();
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 国家学院效果CD
(function() {
    countrynoticeCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    countrynoticeCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('.countrynoticecd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                if (countrycollegepop && countrycollegepop.e) {
                    countrycollegepop.e.find('.notice_wrap').css('display', 'block');
                    countrycollegepop.e.find('.notice_wrap2').css('display', 'none');
                    countrycollegepop.e.find('.notice_time').css('display', 'none');
                }
               
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 查看资料国家学院效果CD
(function() {
    collegeinfoCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    collegeinfoCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('.collegeinfocd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                if (infopop && infopop.e) {
                    infopop.e.find('.collegeinfolimit').css('display', 'none');
                    infopop.e.find('.collegeinfolimit2').css('display', 'none');
                    infopop.e.find('.collegeinfocd').css('display', 'none');
                }
               
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };
})();

/*国家庆典的倒计时*/
(function() {
    nationcelCdClass = function() {
        this.e; //页面里倒计时的dom
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    nationcelCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#nationcel_cd');
            this.s = s;
            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;
            
            if (this.s <= 0) {
                this.s = 0;
                if (typeof nationcelpop != 'undefined' && nationcelpop.e != undefined) {
                    nationcelpop.e.find('#nationcel_reward_btn').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                    this.e.html(lang('had_end'));
                }
                
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();

// 招募好友领工资
(function() {
    paychecksCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    paychecksCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#paychecks_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.e.html(lang('had_end'));
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 国家技能CD
(function() {
    countrycollegeskillCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    countrycollegeskillCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#countrycollege_effect_time');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.e.html(lang('countrycollege_skill_info'));
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

// 貔貅聚财CD
(function() {
    enrichmentCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    enrichmentCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#enrichment_cdtime');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.e.html(lang('had_end'));
                this.s = 0;
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };   
})();

//七星灯
(function() {
    sevenstarCdClass = function() {
        this.e = null;
        this.s; //倒计时秒数
        this.on = false; //开关
    };
    sevenstarCdClass.prototype = {
        /* 初始化调用 */
        init: function(s) {
            this.e = $('#sevenstar_rank_rbtn');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            }
        },

        /* 更新时调用 */
        update: function(s) {
            if (this.on == false) { //重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step: function() {
            this.s--;

            if (this.s <= 0) {
                this.s = 0;
                if (typeof sevenstarpop != 'undefined' && sevenstarpop.e != null) {
                    sevenstarpop.cdEnd();
                }
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear: function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }
    };

})();

//圣诞团购倒计时
(function() {
    christmasbuyCdClass = function() {
        this.s;//倒计时秒数
        this.on = false;//开关
    };
    christmasbuyCdClass.prototype = {
        /* 初始化调用 */
       init : function(s) {
            this.e = $('#christmasbuy_cd');
            this.s = s;

            if (s > 0) {
                this.e.html(helper.timeToClock(this.s));
                timer.addListener(this);
                this.on = true;
            } else {
                this.e.html(lang('had_end'));
            }
        },

        /* 更新时调用 */
        update : function(s) {
            if(this.on == false) {//重新启动
                this.init(s);
            } else {
                this.s = s;
                this.step();
            }
        },

        /* 不能修改，定时器步进自动调用 */
        step : function() {
            this.s--;

            if(this.s <= 0) {
                this.s = 0;
                this.e.html(lang('had_end'));
                this.e.find('#christmasbuy_wrap').find('button').attr('class','hidebtn btn').html(lang('christmasbuy_is_end'));
                this.clear();
            } else {
                this.e.html(helper.timeToClock(this.s));
            }
        },

        clear : function() {
            timer.removeListener(this);
            this.on = false;
            this.e = null;
            this.s = 0;
        }

    };

})();