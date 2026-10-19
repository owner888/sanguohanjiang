/* 群服争霸 */
(function() {
    gvgWorldClass = function() {
        this.e = null;
        
        this.scheduleDom;//赛程dom
        this.gvgDom;//主要的界面
        
        this.leftDom;
        this.rightDom;
        this.centerDom;
        
        this.leftScroller;//左边的scroller
        this.rightScroller;//右边的scroller
        
        this.leftBet;//左边押注人数
        this.rightBet;//右边押注人数
        
        this.leftBetDom;
        this.rightBetDom;
        
        this.round;
        this.roundid;
        
        this.scheduleData;
        
        this.autoRefresh;
        this.isBlock;//是否是轮空
        
        this.historyScroller;
    };

    gvgWorldClass.prototype = {
        init: function() {
            
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                if (res.status == 1) {
                    //这里面做很多初始化的东西
                    that.initPage();
                    that.buildPage(res);
                    $('#worldloading').removeClass('black').hide();
                } else {
                    switch (res.status) {
                        
                        case -3:
                            xGame.toast.show(lang('activity_closed'));
                            break;
                        case -2:
                            xGame.toast.show(lang('champions_get_info_fail'));
                            break;
                        case -1:
                            xGame.toast.show(lang('gvg_world_lv_low'));
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                    xGame.loadWorld('city');
                }
            };
             
            loadContents({
                'c': 'gvg',
                'm': 'index'
            },
            initRender, true); 
            initRender = null;

        },
        
        cdEndRefresh: function(justRefreshTeam) {
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                //如果只是刷新队伍
                if (justRefreshTeam) {
                    if (res.status == 1) {
                        that.fillTeam(res.info.team);
                    }    
                } else { 
                    if (res.status == 1) {
                        that.buildPage(res);
                    } else {
                        switch (res.status) {
                            
                            case -3:
                                xGame.toast.show(lang('activity_closed'));
                                break;
                            case -2:
                                xGame.toast.show(lang('champions_get_info_fail'));
                                break;
                            case -1:
                                xGame.toast.show(lang('gvg_world_lv_low'));
                                break;
                            default:
                                xGame.toast.show(lang('undefined_error'));
                                break;
                        }
                        xGame.loadWorld('city');
                    }
                }
            };
            var errorFunc = function() {
                return false;
            };
            loadContents({
                'c': 'gvg',
                'm': 'index'
            },
            initRender, true, errorFunc); 
            initRender = null;
        },

        initPage: function() {
            if (this.e != null) {
                return false;
            }

            $('#header').hide();
            $('#footer').hide();

            $('div#world').append($.template('gvgworld_tmpl'));

            this.e = $('#gvgworld_page');
            this.gvgDom = this.e.find('#gvgworld_gvg');
            this.scheduleDom = this.e.find('#gvg_schedule_dom');
            
            this.leftDom = this.gvgDom.find('#gvg_left');
            this.rightDom = this.gvgDom.find('#gvg_right');
            this.centerDom = this.gvgDom.find('#gvg_center');
            
            this.leftBet = 0;
            this.rightBet = 0;
            
            this.leftBetDom = this.leftDom.find('.bet_num').find('em');
            this.rightBetDom = this.rightDom.find('.bet_num').find('em');
            
            this.e.find('#gvgworld_back').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.loadWorld('city');
                e.preventDefault();
                e.stopPropagation();
            });
            
            this.bindAll();
        },
        
        bindAll: function(){
            var that = this;
            
            //玩法介绍
            that.e.undelegate('#gvg_info', 'touchend').delegate('#gvg_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#gvg_info_dom').css('display', 'block');
            });
            
            that.e.undelegate('#gvg_info_hide', 'touchend').delegate('#gvg_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#gvg_info_dom').css('display', 'none');
            });
            
            //显示赛程
            that.e.undelegate('#gvg_view', 'touchend').delegate('#gvg_view', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showSchedule();
            });
            
            that.scheduleDom.undelegate('#gvg_schedule_hide', 'touchend').delegate('#gvg_schedule_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.scheduleDom.css('display', 'none');
                that.scheduleDom.find('#gvg_schedule_ul').html('');
            });
            
            that.gvgDom.undelegate('.gvg_btn.pop_btn_middle', 'click').delegate('.gvg_btn.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('type') == 'sync') {
                    that.syncFunc($(this).parent());
                } else if ($(this).attr('type') == 'bet') {
                    that.betFunc($(this).attr('serverid'), $(this).attr('side'));
                }
            });
            
            this.e.find('#gvgworld_result').undelegate('.histroy_btn', 'touchend').delegate('.histroy_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showHistrory();
            });
            
            this.e.find('#gvg_histroy_dom').undelegate('#gvg_histroy_hide', 'touchend').delegate('#gvg_histroy_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#gvg_histroy_dom').css('display', 'none');
            });
            
            this.e.find('#gvg_histroy_dom').undelegate('.view', 'touchend').delegate('.view', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.round = $(this).attr('round');
                that.roundid = $(this).attr('roundid');
                that.getBattleData();
            });
        },
        
        buildPage: function(res) {
            
            //初始化一些状态
            this.gvgDom.find('.pop_btn_middle').removeAttr('style').removeAttr('type');
            this.rightDom.removeClass('block');//只有右边会轮空
            
            if (res.stage != 4) {
                this.round = res.info.play.round;
                this.roundid = res.info.play.roundid;
                this.isBlock = false;
                this.e.attr('class', 'world_page bg1');
                if (res.stage == 1) {//选手确定倒计时
                    this.centerDom.find('#gvg_cd').find('p').html(lang('gvg_world_player_ensure_cd')).attr('class', 'wait');
                    xGame.updateCd('gvgwait', res.cd);
                    
                    //等待中刷新队伍列表
                    var that = this;
                    if (that.autoRefresh != null) {
                        clearInterval(that.autoRefresh);
                    }
                    that.autoRefresh = setInterval(function() { 
                        that.cdEndRefresh(true);
                    },15000);
                    
                } else if (res.stage == 2) {//对手显示倒计时
                    this.centerDom.find('#gvg_cd').find('p').html(lang('gvg_world_player_show_cd')).attr('class', 'wait');
                    xGame.updateCd('gvgwait', res.cd);
                } else if (res.stage == 3){//战斗倒计时，15分钟开战，最后5分钟无法押注
                    res.cd = parseInt(res.cd);
                    //当cd== 0 的时候直接再刷一次
                    if (res.cd == 0) {
                        this.cdEndRefresh();
                        return;
                    }
                    if (parseInt(res.info.play.round) <= 32) {//小于32的时候显示文字
                        this.centerDom.find('#gvg_cd').find('p').html(res.info.play.round_name);
                    } else {
                        this.centerDom.find('#gvg_cd').find('p').html(lang('champions_open_cd'));
                    }
                    this.centerDom.find('#gvg_cd').attr('class', 'battle');
                    
                    //说明是观看者，需要显示押注按钮
                    if (res.info.play.is_player == 0) {
                        var arr = ['', 'left', 'right'];
                        if (res.info.play.bet != 2) {//1可押注 0时间过了无法押注
                            for (var i = 1;i < 3;i ++) {
                                //如果不是轮空
                                if (res.info.play['serverid' + i] != 0) {
                                    
                                    this[arr[i] + 'Dom'].find('.gvg_btn').css('display', 'block').html(lang('gvg_world_bet_text')).attr({
                                        'type' : 'bet',
                                        'side' : arr[i],
                                        'serverid' : res.info.play['serverid' + i],
                                        'class' : 'btn gvg_btn pop_btn_middle' + ((res.info.play.bet == 0 || res.cd <= 300) ? '_disable' : '')
                                    });
                                    this[arr[i] + 'Dom'].find('.scroller').height('295px');
                                    if (this[arr[i] + 'Scroller'] != null) {
                                        this[arr[i] + 'Scroller'].wrapperH = 295;
                                        this[arr[i] + 'Scroller'].refresh();
                                    }
                                }
                            }
                        } else if (res.info.play.bet == 2) {//已押注
                            for (var i = 1;i < 3;i ++) {
                                if (res.info.play['serverid' + i] != 0) {
                                    //如果押的是这个服务器
                                    if (res.info.play.bet_server == res.info.play['serverid' + i]) {
                                        this[arr[i] + 'Dom'].find('.gvg_btn').css('display', 'block').html(lang('gvg_world_had_bet')).attr({
                                            'class' : 'btn gvg_btn pop_btn_middle_disable'
                                        });
                                    } else {//如果押的不是这个服务器
                                        this[arr[i] + 'Dom'].find('.gvg_btn').css('display', 'block').html(lang('gvg_world_bet_text')).attr({
                                            'class' : 'btn gvg_btn pop_btn_middle_disable'
                                        });
                                    }
                                    
                                    this[arr[i] + 'Dom'].find('.scroller').height('295px');
                                    if (this[arr[i] + 'Scroller'] != null) {
                                        this[arr[i] + 'Scroller'].wrapperH = 295;
                                        this[arr[i] + 'Scroller'].refresh();
                                    }
                                }
                            }
                        } 
                    } else {//说明是参赛者，需要显示同步按钮
                        var my_server = xGame.server_prefix.replace(islocal ? 't' : 's','');
                        var d = my_server == res.info.play.serverid1 ? 'left' : 'right';
                        this[d + 'Dom'].find('.gvg_btn').css('display', 'block').html(lang('gvg_world_sync_text')).attr({
                            'type' : 'sync',
                            'class' : 'btn gvg_btn pop_btn_middle' + (res.cd <= 300 ? '_disable' : '')
                        });
                        this[d + 'Dom'].find('.scroller').height('295px');
                        if (this[d + 'Scroller'] != null) {
                            this[d + 'Scroller'].wrapperH = 295;
                            this[d + 'Scroller'].refresh();
                        }
                    }
                    
                    xGame.updateCd('gvgbattle', res.cd);
                    //战斗中自动刷支持人数
                    var that = this;
                    //如果大于5分钟5秒，刷新支持数
                    if (res.cd > 305) {
                        if (that.autoRefresh != null) {
                            clearInterval(that.autoRefresh);
                        }
                        that.autoRefresh = setInterval(function() { 
                            that.refreshBet();
                        }, 15000);
                    }
                    
                }
                this.scheduleData = res.info.schedule;
                this.fillInfo(res.info.play);
                this.fillTeam(res.info.team);
                
            } else {//结果页，出一张原画
                this.e.attr('class', 'world_page bg2');
                var html = '';
                for (var i = 1; i < 6; i ++) {
                    html += '<div class="d' + i + '">' + res.match_result[i].rank + ':' + res.match_result[i].server + '</div>';
                }
                html += '<button class="btn histroy_btn"></button>'
                this.e.find('#gvgworld_result').html(html);
                html = null;
                this.scheduleData = res.schedule;
            }
            
        },
        
        // 填充支持者
        fillBet: function() {
            if (this.leftBetDom != null && this.rightBetDom != null) {
                this.leftBetDom.html(this.leftBet);
                this.rightBetDom.html(this.rightBet);
            }
        },
        
        /*显示赛程 一个对象，
            key 1 表示是否显示王座
            key 2,3 表示4个的那条线
            key 4,5,6,7 表示8个的那条线
        */
        showSchedule: function() {
            var data = this.scheduleData;
            //data = {"1":{"round":"1","roundid":"1","serverid1":"5","serverid2":"22","win":"0"},"2":{"round":"2","roundid":"1","serverid1":"13","serverid2":"5","win":"2"},"3":{"round":"2","roundid":"2","serverid1":"1","serverid2":"22","win":"2"},"4":{"round":"4","roundid":"1","serverid1":"13","serverid2":"27","win":"1"},"5":{"round":"4","roundid":"2","serverid1":"5","serverid2":"26","win":"1"},"6":{"round":"4","roundid":"3","serverid1":"1","serverid2":"15","win":"1"},"7":{"round":"4","roundid":"4","serverid1":"22","serverid2":"18","win":"1"}} ;
            //console.log(data);
            var html = '';
            var name = '';
            var classname = '';
            var line1 = '';//8强的线
            var line2 = '';//4强的线
            var line3 = '';//半决赛的线
            var sdata = {};
            
            for (var i = 1;i <= 2;i ++) {//可以理解为半决赛
                //line3 = '';
                html += '<li class="l' + i + '">';
                /*这一段是最上面的线
                if (data[1] != undefined && data[1].win == i) {
                    line3 = '<span class="l' + i + '"><em></em></span>';
                    line3 += '<i></i>';
                }
                html += line3;
                */
                html += '<ul>';
                    for (var j = 1;j <= 2;j ++) {//可以理解为四强赛 1 + i获得2，3
                        html += '<li class="l' + j + '">';
                        
                        line2 = '';
                        if (data[1 + i].win == j) {
                            line2 = '<span class="l' + j + '"><em></em></span>';
                        }
                        html += line2;
                        html += '<ul>';
                            for (var k = 1;k <= 2;k ++) {//可以理解为8强赛
                                //i + j + i + 1得到 4，5，6,7
                                sdata = data[i + j + i + 1];
                                if (sdata['serverid1'] == 0 && sdata['serverid2'] == 0) {
                                    name = '<b class="b1">' + lang('gvg_world_none') + '</b>';
                                } else {
                                    if (sdata['serverid' + k] == 0) {
                                        name = lang('gvg_world_block');
                                        name = '<b class="red b2">' + name.split('').join('</b><b class="red">') + '</b>';
                                    } else {
                                        /*
                                        name = this.getServerName(sdata['serverid' + k]);
                                        name = '<b class="b3">' + name.split('').join('</b><b>') + '</b>';
                                        name += '<b>' + sdata['serverid' + k] + '</b>';
                                        if (appLanguage == 'zh-cn' || appLanguage == 'zh-tw') {
                                            name += '<b>' + lang('gvg_world_fu') + '</b>';
                                        }
                                        */
                                        name = this.getServerName(sdata['server' + k]);
                                    }
                                    
                                }
                                
                                line1 = '';
                                //如果没人赢就是普通的,随便加入span，表示亮的线,em表示对战的剑
                                if (sdata.win == 0) {
                                    classname = 'l' + k;
                                } else {
                                    if (sdata.win == k) {
                                        classname = 'l' + k + ' win';
                                        line1 = '<span class="l' + k + '"><em></em></span>';
                                    } else {
                                        classname = 'l' + k + ' lose';
                                    }
                                    
                                    //这里是如果已经进行到半决赛了八强的有些线要暗掉
                                    if (data[1 + i].win != 0) {
                                        //console.log('半决赛的左右两边是：' + data[1 + i]['serverid' + data[1 + i].win]);
                                        //console.log('八个参赛者分别是：' + sdata['serverid' + k]);
                                        //不是赢的那一方，木牌灰掉
                                        if (data[1 + i]['serverid' + data[1 + i].win] != sdata['serverid' + k]) {
                                            classname = 'l' + k + ' lose';
                                        }
                                        //两边都没有，线也灰掉
                                        if (data[1 + i]['serverid' + data[1 + i].win] != sdata['serverid1'] && data[1 + i]['serverid' + data[1 + i].win] != sdata['serverid2']) {
                                            line1 = '';
                                        }
                                    }
                                }
                                
                                html += '<li class="' + classname + '">' + name + '</li>' + line1;
                            }
                        html += '</ul>';
                        html += '</li>';
                    }
                html += '</ul>';
                html += '</li>';
            }
            
            this.scheduleDom.find('#gvg_schedule_ul').html(html);
            this.scheduleDom.css('display', 'block');
            
            if (data[1] != undefined) {
                this.scheduleDom.attr('class', 'king');
            }
            
            data = null;
            html = null;
            name = null;
            classname = null;
            line1 = null;
            line2 = null;
            line3 = null;
            sdata = null;
        },
        
        /*0-3000官方 3000-4000海外
        getServerName: function(num) {
            num = parseInt(num);
            if (appLanguage == 'zh-cn' || appLanguage == 'zh-tw') {
                if (num <= 3000) {
                    return lang('gvg_world_server_office');
                } else {
                    return lang('gvg_world_server_oversea');
                }
            } else {
                return ' S'
            }
        },
        */
        
        getServerName: function(str) {
            var arr = str.split('');
            var index = 0;
            var  str1 = '',str2 = '',str3 = '';
            for (var i = 0;i < arr.length;i ++) {
                if (isNaN(Number(arr[i]))) {
                    if (index == 0) {
                        str1 += '<b' + (i == 0 ? ' class="b3"' : '') + '>' + arr[i] + '</b>';
                    } else {
                        str3 += '<b>' + arr[i] + '</b>';
                    }
                } else {
                    str2 += arr[i];
                    index = i;
                }
            }
            return (str1 +  '<b>' + str2 + '</b>' + str3);
        },
        
        //填充基本奖励信息
        fillInfo: function(data) {
            this.leftBet = parseInt(data.bet1);
            this.rightBet = parseInt(data.bet2);
            
            this.fillBet();
            
            this.leftDom.find('#left_name').html(data.left_name);
            if (parseInt(data.serverid2) == 0) {
                this.rightDom.addClass('block');
                this.isBlock = true;
            } else {
                this.rightDom.find('#right_name').html(data.right_name);
            }
            
            this.fillReward(data.reward.player, this.centerDom.find('#gvg_reward_player'), lang('gvg_world_player'));
            this.fillReward(data.reward.server, this.centerDom.find('#gvg_reward_server'), lang('gvg_world_server'));
            //全服等级限制
            this.centerDom.find('#gvg_reward_server').find('em').last().append('<small>' + lang('gvg_world_lv_reach', [data.level_limit]) + '</small>');
        },
        
        //构建队伍列表
        fillTeam: function(data) {
            var arr = ['left', 'right'];
            var html = '';
            for (var i = 0;i < 2;i ++) {
                //如果这一方有数据
                if (data[arr[i]].length != 0) {
                    html = '';
                    for (var j = 0;j < data[arr[i]].length; j ++) {
                        html += '<li><em class="t1">' + (j + 1) + '.</em><em class="t2">' + data[arr[i]][j].nickname + '</em><em class="t3">Lv.' + data[arr[i]][j].level + '</em></li>';
                    }
                    this[arr[i] + 'Dom'].find('ul').html(html);
                    if (this[arr[i] + 'Scroller'] == undefined) {
                        this[arr[i] + 'Scroller'] = new iScroll('gvg_' + arr[i] + '_scroller', {
                            bounce: false,
                            momentum: false,
                            hScrollbar: false,
                            vScrollbar: false
                        });
                    }
                    this[arr[i] + 'Scroller'].refresh();
                }
            }
        },
        
        //同步方法
        syncFunc: function(obj) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        xGame.updateCd('gvgsync', 10);
                        xGame.toast.show(lang('champions_sync_sucess'));
                        break;
                     case -3:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('champions_sync_later'));
                        break;
                    case -1:
                        xGame.toast.show(lang('gvg_world_sync_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'gvg',
                'm': 'sync_info',
            },
            Func);
            Func = null;
        },
        
        //押注方法 side 1 左 2 右
        betFunc: function(serverid, me) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        xGame.toast.show(lang('gvg_world_bet_success'));
                        //自己这方支持+1
                        that[me + 'Bet'] += 1;
                        that.fillBet();
                        
                        //全部变灰色,自己变文字
                        that.gvgDom.find('.gvg_btn').attr('class', 'btn pop_btn_middle_disable gvg_btn');
                        that.gvgDom.find('#gvg_' + me).find('.gvg_btn').html(lang('gvg_world_had_bet'));
                        
                        break;
                    case -3:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('warena_bet_error'));
                        break;
                    case -1:
                        xGame.toast.show(lang('gvg_world_had_bet'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'gvg',
                'm': 'betting',
                'round' : that.round,
                'roundid' : that.roundid,
                'toserverid' : serverid
            },
            Func);
            Func = null;
        },
        
        //自动刷新接口
        refreshBet: function(justRefresh) {
            var that = this;
            
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        //不管发生什么，先清除自增
                        if (that.autoPlus != null) {
                            clearInterval(that.autoPlus);
                            that.autoPlus = null;
                        }
                        if (that.gvgDom != undefined) {
                            //如果只刷新
                            if (justRefresh) {
                                that.leftBet = parseInt(res.info.bet1);
                                that.rightBet = parseInt(res.info.bet2);
                                that.fillBet();
                            } else {
                                that.autoUpFunc(parseInt(res.info.bet1), parseInt(res.info.bet2));
                            }
                        }
                        break;
                }
            };
            var errorFunc = function() {
                return false;
            };
            loadContents({
                'c': 'gvg',
                'm': 'get_bet',
                'round' : that.round,
                'roundid' : that.roundid
            },
            Func, true, errorFunc);
            Func = null;
            errorFunc = null;
        },
        
        //这个方法自动将获得的数字分成4次随机显示，中间要不断调用checkMore来达到变位置的效果
        autoUpFunc: function(left, right) {
            //如果传入比现在小的数字，默认现在的
            if (left < this.leftBet || right < this.rightBet || (left == 0 && right == 0)) {
                console.log('直接返回');
                return false;
            }
            
            var that = this;
            
            var sleft = left - that.leftBet;
            var sright = right - that.rightBet;
            var rule = [0.04, 0.06, 0.02, 0.09, 0.04, 0.05, 0.24, 0.04, 0.08, 0.24, 0.11, 0.01, 0.03, 0.03, 0.04];
            var randomsort = function(a, b) {
                return Math.random()>.5 ? -1 : 1;
            }
            rule = rule.sort(randomsort);
            var leftArr = that.getArr(sleft, rule);
            rule = rule.sort(randomsort);
            var rightArr = that.getArr(sright, rule);
            
            rule = null;
            randomsort = null;
            
            var i = 1;
            var auto = function() {
                if (i == 15 || (CD['gvgbattle'] != undefined && CD['gvgbattle'].s < 300)) {//其实最后一步是不算的，只要保证前面小于我的数字就行
                    clearInterval(that.autoPlus);
                    that.autoPlus = null;
                    
                    that.leftBet = left;
                    that.rightBet = right;
                    that.fillBet();
                    //console.log(i);
                    //console.log('that.leftBet = ' + that.leftBet);
                    //console.log('that.rightBet = ' + that.rightBet);
                    
                    sleft = null;
                    sright = null;
                    leftArr = null;
                    rightArr = null;
                    
                    auto = null;
                    that = null;
                    i = null;
                    
                    //console.log('停止');
                    
                    return false;
                }
                
                that.leftBet += leftArr.shift();
                that.rightBet += rightArr.shift();
                that.fillBet();
                //console.log(i);
                //console.log('that.leftBet = ' + that.leftBet);
                //console.log('that.rightBet = ' + that.rightBet);
                i += 1;
            };
            auto();
            that.autoPlus = setInterval(function() {
                auto();
            }, 1000);
        },
        
        getArr: function(num, rule) {
            var arr = [];
            for (var i = 0;i < rule.length;i ++) {
                arr.push(Math.floor(num * rule[i]));
            }
            rule = null;
            return arr;
        },
        
        //填充奖励
        fillReward: function(data, obj, text) {
            var html = '';
            html += '<li class="text">' + text + '</li>';
            if (data.silver != undefined) {
                html += '<li><em class="t1"></em><em class="t2 silver">' + data.silver + '</em></li>';
            }
            if (data.gold != undefined) {
                html += '<li><em class="t1"></em><em class="t2 gold">' + data.gold + '</em></li>';
            }
            if (data.tavernjade != undefined) {
                html += '<li><em class="t1"></em><em class="t2 gem3">' + data.tavernjade + '</em></li>';
            }
            if (data.jade != undefined) {
                html += '<li><em class="t1"></em><em class="t2 jade">' + data.jade + '</em></li>';
            }
            if (data.soul != undefined) {
                html += '<li><em class="t1"></em><em class="t2 soul">' + data.soul + '</em></li>';
            }
            if (data.equip != undefined) {
                html += '<li><em class="t1">' + lang('champions_equip') + ':</em><em class="t2" style="padding-left:5px;color:' + helper.getItemColor(data.equip.quality) + '">' + data.equip.name + '</em></li>';
            }
            obj.html(html);
            html = null;
        },
        
        getBattleData: function() {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        //放进去一个round,计算是否是最后一局
                        res.report.round = that.round;
                        xGame.loadWorld('gvgbattle', res.report);
                        
                        break;
                    case -3:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('champions_get_report_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('gvg_world_report_not_found'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'gvg',
                'm': 'get_report',
                'round' : that.round,
                'roundid' : that.roundid
            },
            Func);
            Func = null;
        },
        
        refreshByTime: function(s) {
            if (CD['championssync'] != undefined) {//同步按钮特殊处理
                if(CD['gvgsync'].s - s > 0) {
                    xGame.updateCd('gvgsync', CD['gvgsync'].s - s);
                } else {
                    xGame.updateCd('gvgsync', 0);
                }
            } 
            
            if (CD['gvgwait'] != undefined) {//战神殿等待
                if(CD['gvgwait'].s - s > 0) {
                    xGame.updateCd('gvgwait', CD['gvgwait'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['gvgbattle'] != undefined) {
                if(CD['gvgbattle'].s - s > 0) {
                    xGame.updateCd('gvgbattle', CD['gvgbattle'].s - s);
                } else {
                    that.reload();
                }
            }
        },
        
        //刷新方法
        reload: function() {
            gvgWorld.clear();
            xGame.loadWorld('gvg');
        },
        
        showHistrory: function() {
            var data = this.scheduleData;
            var dom = this.e.find('#gvg_histroy_dom');
            var html = '';
            var name = '';
            
            for (var i = 0;i < data.length;i ++) {
                if (i == 0) {
                    html += '<li class="title">' + lang('gvg_world_juesai') + '</li>';
                } else if (i == 1) {
                    html += '<li class="title">' + lang('gvg_world_banjuesai') + '</li>';
                } else if (i == 3) {
                    html += '<li class="title">' + lang('gvg_world_siqiang') + '</li>';
                } else if (i == 7) {
                    html += '<li class="title">' + lang('gvg_world_baqiang') + '</li>';
                }
                html += '<li>' + data[i].serverid1 + 'VS' + data[i].serverid2 + '<em class="view" round="' + data[i].round + '" roundid="' + data[i].roundid + '">' + lang('champions_view') + '</em></li>';
            }
            
            dom.find('ul').html(html);
            
            if (this.historyScroller == undefined) {
                this.historyScroller = new iScroll('gvg_histroy_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            
            dom.css('display', 'block');
            data = null;
            html = null;
            dom = null;
            name = null;
            this.historyScroller.refresh();
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#gvgworld_back').unbind('touchend');
                this.e.undelegate('#gvg_info', 'touchend');
                this.e.undelegate('#gvg_info_hide', 'touchend');
                this.e.undelegate('#gvg_view', 'touchend');
                this.e.find('#gvgworld_result').undelegate('.histroy_btn', 'touchend');
                this.e.find('#gvg_histroy_dom').undelegate('#gvg_histroy_hide', 'touchend');
                this.e.find('#gvg_histroy_dom').undelegate('.view', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.scheduleDom) {
                this.scheduleDom.undelegate('#gvg_schedule_hide', 'touchend');
                this.scheduleDom.remove();
                this.scheduleDom = null;
            }
            if (this.gvgDom) {
                 this.gvgDom.undelegate('.gvg_btn.pop_btn_middle', 'click');
                 this.gvgDom.remove();
                 this.gvgDom = null;
             }
        },
        
        clear: function() {
            xGame.unlock();
            if (this.e != undefined) {
                
                if (this.leftDom) {
                    this.leftDom.remove();
                    this.leftDom = null;
                }
                if (this.rightDom) {
                    this.rightDom.remove();
                    this.rightDom = null;
                }
                if (this.centerDom) {
                    this.centerDom.remove();
                    this.centerDom = null;
                }
                
                if (this.leftScroller != undefined) {
                    this.leftScroller.destroy();
                    delete this.leftScroller;
                }

                if (this.rightScroller != undefined) {
                    this.rightScroller.destroy();
                    delete this.rightScroller;
                }
                
                if (this.historyScroller != undefined) {
                    this.historyScroller.destroy();
                    delete this.historyScroller;
                }
                
                this.leftBet = null;
                this.rightBet = null;
                
                this.leftBetDom = null;
                this.rightBetDom = null;
                
                this.round = null;
                this.roundid = null;
                this.isBlock = null;
                this.scheduleData = null;
                
                if (this.autoRefresh != null) {
                    clearInterval(this.autoRefresh);
                    this.autoRefresh = null;
                }
            
                xGame.updateCd('gvgbattle', 0);
                xGame.updateCd('gvgsync', 0);
                xGame.updateCd('gvgwait', 0);
                
                this.unbindAll();
            }
            
        }
    };
})();