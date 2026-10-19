/* 任务 */
(function() {
    missionPopClass = function() {
        this.e;
        this.isshow = false;
        
        this.listDom;
        this.myScroller;
        
        this.missionlevel;
        this.missionstage;
        this.finishNum;
        this.tempLock = false;//临时锁，防止领奖时连点跳入其他功能
        this.tempLockTimer = null;
        this.step = 0;  // 引导续接的步数
    };
    
    missionPopClass.prototype = {
        init: function(step) {
            var that = this;
            that.step = step;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                if (res.status == 1) {
                    that.initPage(res);
                } else {
                    var msg = '';
                    switch(parseInt(res.status)) {
                        case -1:
                            msg = lang('missionpop_get_mission_list_fail');
                            break;
                        case -2:
                            msg = lang('missionpop_get_mission_reward_fail');
                            break;
                        default:
                            msg = lang('undefined_error');
                            break;
                    }
                    xGame.toast.show(msg);
                }
            };
            loadContents({
                'c': 'mainquest',
                'm': 'index'
            }, initRender, true);
            initRender = null;
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('mission');
            that.e.find('#missionpop_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                missionpop.hide();
                e.preventDefault();
                
            });
            
            that.listDom = that.e.find('#missionpop_list');
            
            that.fillList(res.list);
            
            that.bindAll();
            if (res.list.length > 3) {
                that.myScroller = new iScroll('missionpop_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            that.e.show();
            $('#worldloading').hide();
            if (that.myScroller != null) {
                that.myScroller.refresh();
            }
            
            if (xGame.guide) {
                switch (guide.now) {
                    case 401:
                        guide.show(901);
                        break;
                    case 514:
                        guide.show(911);
                        break;
                    case 1301:
                        guide.show(921);
                        break;
                    case 1323:
                        guide.show(931);
                        break;
                    case 1431:
                        guide.show(1432);
                        break;
                    case 1602:
                        guide.show(1603);
                        break;
                    case 1801:
                        guide.show(1802);
                        break;
                    default :
                        break;
                }
            }

            // 引导续接
            if (that.step) {
                guide.show(that.step);
            }
        },
        
        fillList: function(list) {

            //需要对列表重新处理
            //var list = this.dealList(list);

            this.finishNum = 0;
            var html = '';
            var html2 = '';
            var mainquestHaveNotice = false;
            var zhuHave = false;
            var zhiHave = false;
            for (var i = 0;i < list.length;i ++) {
                if(list[i].type == 1 && zhuHave == false) {
                    zhuHave = true;
                    html += '<li class="sp sp0" id="missionpop_li' + i + '"><span class="tag"></span>';
                    if(list[i].missionlevel) {
                        this.missionlevel = list[i].missionlevel;
                        this.missionstage = list[i].missionstage;
                    }
                } else if(list[i].type == 2 && zhiHave == false) {
                    zhiHave = true;
                    html += '<li class="sp sp1" id="missionpop_li' + i + '"><span class="tag"></span>';
                } else {
                    html += '<li>';
                }
                
                html2 = '';
                if (list[i].status == 0) {//未完成
                    //html2 = '<span class="done"></span>';
                } else if (list[i].status == 1) {//完成
                    html2 = '<span class="done"></span>';
                    this.finishNum += 1;
                }
                
                html += '<p class="p1"><b>' + lang('missionpop_target') + '</b><em class="t">' + list[i].name + '</em>' + html2 +'</p>';
                html += '<p class="p2"><b>' + lang('missionpop_reward') + '</b><span class="reward_box">' + this.buildReward(list[i].reward) + '</span></p>';
                html += '<p class="p3"><b>' + lang('missionpop_tips') + '</b><em class="t">' + list[i].description + '</em></p>';
                if (list[i].task_type != 20 && list[i].status == 0) {//前往
                    html += '<button class="btn pop_btn_middle goto" method="' + list[i].task_type + '">' + lang('missionpop_goto') + '</button>';
                } else if (list[i].task_type == 20 && list[i].status == 0) {//前往
                    html += '<button class="btn pop_btn_middle_disable" method="' + list[i].task_type + '">' + lang('uncomplete') + '</button>';
                } else if (list[i].status == 1) {//完成领取奖励
                    html += '<button class="btn pop_btn_middle" tid="' + list[i].task_id + '">' + lang('receive') + '</button>';
                }
                html += '</li>';

                //主线任务的通知处理
                if(list[i].task_type == 20) {
                    //console.log('Missionpop: 主线任务 需要通知每日登录');
                    mainquestHaveNotice = true;
                    player.setMainquestNotice();
                }
            }
            if(mainquestHaveNotice == false) {
                //console.log('Missionpop: 主线任务 不需要通知每日登录');
                player.cancelMainquestNotice();
            }
            this.listDom.html(html);
            html2 = null;
            html = null;
        },
        
        /*
        1 击败关卡
        2 任意装备强化到 X 级
        3 任意武将提升到 X 级
        4 任意武将全属性（武智体）洗到≥X 点
        5 任意阵法升到 X 级
        6 同时上阵 5 个将领
        7 占领或抢夺一次银矿（无论胜败）
        8 征战一次演武榜（无论胜败）
        9 演武榜提升 X 名
        10 参加一次多人副本（无论胜负）
        11 参加一次世界 BOSS（只要有伤害）
        12 完成任意日常任务
        13 完成任意 X 星级任务
        14 完成一次海外贸易（无论是否被劫-有获得那一刻就完成）
        15 获得任意将魂-参数填 0
        16 将魂等级提升至 X 级
        17 完成一次每日通商。
        18 完成通商 X 星级
        19 获得 X 星级武将
        */
        doSthByType: function(type) {
            if(this.tempLock) {
                //console.log('锁定状态');
                return false;
            }
            player.info.level = parseInt(player.info.level);
            switch(parseInt(type)) {
                case 1:
                    //直接进关卡
                    player.info.missionsite = 3;
                    var missionlevel = this.missionlevel;
                    var missionstage = this.missionstage;
                    player.info.missionlevel = this.missionlevel;
                    player.info.missionstage = this.missionstage;
                    
                    var menu_list2 = $('#menu_list2');
                    menu_list2.find('#menu_city').show();
                    menu_list2.find('#menu_map').hide();
                    menu_list2.find('#menu_friends').hide();
                    xGame.loadWorld('map');
                    this.hide(function() {
                        player.info.missionlevel = missionlevel;
                        player.info.missionstage = missionstage;
                    });
                    break;
                case 2:
                    if (player.info.level >= 16) {
                        xGame.showpop('strong');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('strong'), 16]));
                    }
                    break;
                case 3:
                    if (player.info.level >= 11) {
                        xGame.showpop('practice');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_practice'), 11]));
                    }
                    break;
                case 4:
                    if (player.info.level >= 40) {
                        xGame.showpop('foster');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_foster'), 40]));
                    }
                    break;
                case 5:
                    xGame.showpop('team');
                    this.hide();
                    break;
                case 6:
                    xGame.showpop('team');
                    this.hide();
                    break;
                case 7:
                    if (player.info.level >= 26) { 
                        xGame.loadWorld('mine');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_mine'), 26]));
                    }
                    break;
                case 8:
                    if (player.info.level >= 26) { 
                        xGame.showpop('arena');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_arena'), 26]));
                    }
                    break;
                case 9:
                    if (player.info.level >= 26) {
                        xGame.showpop('arena');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_arena'), 26]));
                    }
                    break;
                case 10:
                    if (player.info.level >= 31) {
                        xGame.showpop('elite');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_elite'), 31]));
                    }
                    break;
                case 11:
                    if (player.info.level >= 46) {
                        xGame.loadWorld('boss');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('world_boss'), 46]));
                    }
                    break;
                case 12:
                    if (player.info.level >= 41) {
                        xGame.showpop('dailytask');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_dailytask'), 41]));
                    }
                    break;
                case 13:
                    if (player.info.level >= 41) {
                        xGame.showpop('dailytask');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_dailytask'), 41]));
                    }
                    break;
                case 14:
                    if (player.info.level >= 51) {
                        xGame.showpop('overseastrade');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_overseastrade'), 51]));
                    }
                    break;
                case 15:
                    if (player.info.level >= 51) { 
                        xGame.loadWorld('tower');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_tower'), 51]));
                    }
                    break;
                case 16:
                    if (player.info.level >= 51) {
                        xGame.loadWorld('soul');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_soul'), 51]));
                    }
                    break;
                case 17:
                    if (player.info.level >= 61) {
                        xGame.showpop('dailytrade');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_dailytrade'), 61]));
                    }
                    break;
                case 18:
                    if (player.info.level >= 61) {
                        xGame.showpop('dailytrade');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_dailytrade'), 61]));
                    }
                    break;
                case 19:
                    if (player.info.level >= 31) {
                        xGame.showpop('bar');
                        this.hide();
                    } else {
                        xGame.toast.show(lang('missionpop_lv_notice', [lang('missionpop_bar'), 31]));
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    this.hide();
                    break;
            }

        },

        bindAll: function(){
            var that = this;

            that.listDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).attr('tid') != undefined) {
                    that.getReward($(this).attr('tid'));
                }
                if ($(this).attr('method') != undefined) {
                    that.doSthByType($(this).attr('method'));
                }
            });

        },

        buildReward: function(list) {
            var html = '';
            for (var i = 0;i < list.length;i ++) {
                html += '<em class="reward r' + list[i].type + '">x' + list[i].value + '</em>';
            }
            return html;
        },

        getReward: function(tid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    //console.log(res);
                    that.fillList(res.list);
                    if (that.myScroller != null) {
                        that.myScroller.refresh();
                    }
                    var type = {1:'gold',2:'silver', 3:'act', 4:'gem1', 5:'gem2', 6:'gem3', 7:'tfcard', 8:'osoul', 9:'prestige'};
                    var html = '';
                    for(var i = 0;i < res.reward.length;i ++) {
                        html += '<span class="reward '+type[res.reward[i].type]+'">x' + res.reward[i].value + '</span>';
                    }
                    xGame.toast.show('<b class="mission_done"></b>' + lang('receive_success') + html);

                    player.updateinfo({
                        'silver':res.all_silver,
                        'gold':res.all_gold,
                        'act': res.all_act
                    });
                    
                    xGame.showGuide([901, 921, 1432, 1603, 1802]);
                    
                    if(that.tempLockTimer) {
                        clearTimeout(that.tempLockTimer);
                    }
                    that.tempLock = true;
                    //console.log('设锁');
                    that.tempLockTimer = setTimeout(function() {
                        that.tempLock = false;
                        //console.log('解锁');
                    }, 1200);

                } else if (res.status == -1) {
                    xGame.toast.show(lang('missionpop_mission_not_found'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('missionpop_uncompleted'));
                } else if (res.status == -3) {
                    xGame.toast.show(lang('missionpop_get_mission_reward_fail'));
                } else if (res.status == -4) {
                    xGame.toast.show(lang('missionpop_had_received'));
                    that.fillList(res.list);
                    if (that.myScroller != null) {
                        that.myScroller.refresh();
                    }
                } else if (res.status == -5) {
                    xGame.toast.show(lang('missionpop_receive_fail'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mainquest',
                'm': 'get_task_reward',
                'id': tid
            },
            Func);
            Func = null;
        },
        
        hide: function(callback) {
            xGame.unlock();
            this.isshow = false;
            this.step = 0;
            if (this.listDom) {
                this.listDom.undelegate('button', 'click')
                this.listDom.remove();
                this.listDom = null;
            }
            if (this.finishNum == 0) {
                $('#mission_wrap').find('.popo').remove();
                this.finishNum = null;
            }
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            
            this.missionlevel = null;
            this.missionstage = null;

            this.tempLock = false;
            if(this.tempLockTimer) {
                clearTimeout(this.tempLockTimer);
                this.tempLockTimer = null;
            }
            if (this.e) {            
                this.e.find('#missionpop_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (callback) {
                player.reloadInfo(function() {
                    callback();
                });
            } else {
                player.reloadInfo();
            }
        },
    };
})();