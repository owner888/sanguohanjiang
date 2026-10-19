/* 战神殿 */
(function() {
    warWorldClass = function() {
        this.e = null;
        
        this.enterDom;//进入dom
        this.pvpDom;//战神殿对决dom
        
        this.rewardDom;//奖励弹窗dom
        
        this.historyScroller;//战神榜的scroller
        this.detailScroller;//战神榜的scroller
        
        this.leftScroller;//战神榜的scroller
        this.rightScroller;//战神榜的scroller
        
        this.rewardScroller;//奖励的scroller
        this.riseScroller;// 晋级的scroller
        
        this.palace;//战帝堂开启状态
        
        this.autoRefresh;//自动刷新
        this.autoPlus;//自动刷新
        this.roundid;
        this.stage;
        
        this.leftSupport;//左边支持人数
        this.rightSupport;//右边支持人数
        
        this.leftDom;//左边支持人数dom
        this.rightDom;//右边支持人数dom
        
        this.hDataDetail;//保存的单个人的详细信息
        
        this.is_entry = 0;//是否参赛者
        this.isTimeout = [];
    };

    warWorldClass.prototype = {
        init: function(returnData) {
            
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                switch (res.status) {
                    
                    case 1:
                        //这里面做很多初始化的东西
                        that.palace = res.palace;
                        that.initPage();
                        //是否参赛者
                        that.is_entry = res.is_entry;
                        //res.stage 1 活动未开启 2 报名阶段  4 活动结束 3 战斗阶段 5活动分组界面 6当日战斗结束，显示晋级列表,和下次开战倒计时
                        
                        if (res.stage != 3) {
                            that.buildEnter(res);
                            that.e.attr('class', 'world_page bg1');
                            //返回的时候有数据，直接进详情
                            if (typeof returnData != 'undefined' && res.stage == 4) {
                                that.hDataDetail = returnData;
                                that.showHistoryDetatil(that.hDataDetail);
                            }
                        } else {
                            that.buildPvp(res);
                            that.e.attr('class', 'world_page bg2');
                            that.e.find('.mask').show();
                        }

                        that.showWarHeroBtn(res.stage);
                        $('#worldloading').removeClass('black').hide();
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_info_fail'));
                        xGame.loadWorld('city');
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.loadWorld('city');
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'index'
            },
            initRender, true); 
            initRender = null;

        },
        
        cdEndRefresh: function() {
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                switch (res.status) {
                    
                    case 1:
                        that.palace = res.palace;
                        
                        //先清空一次
                        if (that.autoRefresh != null) {
                            clearInterval(that.autoRefresh);
                            that.autoRefresh = null;
                        }
                        //是否参赛者
                        that.is_entry = res.is_entry;
                        //res.stage 1 活动未开启 2 报名阶段  4 活动结束 3 战斗阶段
                        
                        if (res.stage != 3) {
                            that.buildEnter(res);
                            that.e.attr('class', 'world_page bg1');
                        } else {
                            that.buildPvp(res);
                            that.e.attr('class', 'world_page bg2');
                            that.e.find('.mask').show();
                        }

                        that.showWarHeroBtn(res.stage);
                        
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.loadWorld('city');
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'index'
            },
            initRender, true); 
            initRender = null;
        },

        initPage: function() {
            if (this.e != null) {
                return false;
            }

            //$('#header').hide();
            //$('#footer').hide();

            $('div#world').append($.template('warworld_tmpl'));

            this.e = $('#warworld_page');
            this.enterDom = this.e.find('#war_enter');
            this.pvpDom = this.e.find('#war_pvp');
            this.rewardDom = this.enterDom.find('#war_reward_dom');
            
            this.leftDom = this.pvpDom.find('#pvp_left').find('.supporter')
            this.rightDom = this.pvpDom.find('#pvp_right').find('.supporter')
            
            
            this.e.find('#warworld_back').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                xGame.loadWorld('city');
            });
            
            this.autoRefresh = null;
            
            this.leftSupport = 0;
            this.rightSupport = 0;
            
            this.bindAll();  
        },

        showWarHeroBtn: function (stage) {
            
            var that = this;

            if (stage == 1 || stage == 2 || stage == 4) {
                that.e.find('#warworld_warhero_btn').show();
            } else {
                that.e.find('#warworld_warhero_btn').hide();
            }
        },
        
        bindAll: function(){
            var that = this;
            
            //显示规则
            that.enterDom.undelegate('#warworld_rule_btn', 'touchend').delegate('#warworld_rule_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.enterDom.find('#war_enter_rule').css('display','block');
                that.enterDom.find('#war_enter_rule_mask').css('display','block');
                e.preventDefault();
            });

            // 积分查询按钮
            that.enterDom.undelegate('#warworld_queryscore_btn', 'touchend').delegate('#warworld_queryscore_btn', 'touchend',
            function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.enterDom.find('#queryscore').css('display','block');
                that.enterDom.find('#war_enter_rule_mask').css('display','block');
                e.preventDefault();
            });

            // 积分查询关闭按钮
            that.enterDom.undelegate('#queryscore_hide', 'touchend').delegate('#queryscore_hide', 'touchend',
            function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.enterDom.find('#queryscore').css('display','none');
                that.enterDom.find('#war_enter_rule_mask').css('display','none');
                e.preventDefault();
            });
            
            //斗士榜
            that.enterDom.undelegate('#warworld_warhero_btn', 'touchend').delegate('#warworld_warhero_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if(that.palace == 1) {
                    xGame.loadWorld('warhero');
                } else {
                    xGame.toast.show(lang('war_no_ranklist'));//war_not_open
                }
                e.preventDefault();
            });

            //积分商城
            that.enterDom.undelegate('#warworld_scoreshop_btn', 'touchend').delegate('#warworld_scoreshop_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('scoreshop');
                e.preventDefault();
            });
            
            //关闭规则
            that.enterDom.undelegate('#rule_hide', 'touchend').delegate('#rule_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.enterDom.find('#war_enter_rule').css('display','none');
                that.enterDom.find('#war_enter_rule_mask').css('display','none');
            });
            
            //报名按钮
            that.enterDom.undelegate('#war_join.pop_btn_middle', 'touchend').delegate('#war_join.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.joinFunc();
            });
            
            //历程按钮
            that.enterDom.find('#war_history_list').undelegate('.t3', 'click').delegate('.t3', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getHistoryDetailFunc($(this).attr('uid'), $(this).attr('serverid'));
            });
            
            //返回按钮
            that.enterDom.undelegate('#war_detail_back', 'touchend').delegate('#war_detail_back', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                if (that.is_entry == 1) {
                    that.enterDom.find('#war_my_history').css('display', 'block');
                } else {
                    that.enterDom.find('#war_my_history').css('display', 'none');
                }
                
                that.enterDom.find('#warworld_warhero_btn').show();
                that.enterDom.attr('class','pop_page show4');
                if (that.historyScroller != null){
                    that.historyScroller.refresh();
                }
                
            });
            
            //点击查看按钮
            that.enterDom.find('#war_detail_list').undelegate('.t2', 'click').delegate('.t2', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.viewHistoryReport($(this).attr('vid'), $(this).attr('stage'), true, that.hDataDetail);
            });
            
            //点击宝箱
            that.enterDom.find('#war_enter_rewardbox').undelegate('span', 'click').delegate('span', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                //1 参赛者 2 观看者 默认1
                that.getRewardList(1);
            });
            
            //点击我的历程
            that.enterDom.undelegate('#war_my_history', 'click').delegate('#war_my_history', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                that.enterDom.find('#war_my_history').hide();
                that.enterDom.find('#warworld_warhero_btn').hide();
                that.getHistoryDetailFunc(player.info.uid, xGame.server_prefix.replace(islocal ? 't' : 's',''));
            });
            
            that.rewardDom.undelegate('.pop_btn_yellow', 'click').delegate('.pop_btn_yellow', 'click',
            function() {
                if (xGame.checkLock(true) || that.rewardDom.hasClass('t' + $(this).attr('type'))) { //不设置属性
                    return false;
                }
                that.rewardDom.find('#war_reward_list').html('');
                that.rewardDom.attr('class', 't' + $(this).attr('type'))
                that.getRewardList($(this).attr('type'));
            });
            
            that.rewardDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getReward($(this));
            });
            
            //点击宝箱关闭
            that.rewardDom.undelegate('#war_reward_hide', 'click').delegate('#war_reward_hide', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.rewardScroller != null) {
                    that.rewardScroller.destroy();
                    that.rewardScroller = null;
                }
                that.rewardDom.css('display', 'none');
            });
            
            /************分割线******************/
            
            //左右下方按钮
            that.pvpDom.undelegate('.war_pvp_btn.pop_btn_middle', 'click').delegate('.war_pvp_btn.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('type') == 'sync') {
                    that.syncFunc($(this).parent());
                } else if ($(this).attr('type') == 'support') {
                    that.supportFunc($(this).attr('side'), $(this).attr('serverid'), $(this).attr('uid'));
                }
            });
            
            //点击查看按钮
            that.pvpDom.find('.pvp_side').undelegate('.v2', 'click').delegate('.v2', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.viewHistoryReport($(this).attr('vid'), $(this).attr('stage'), true);
            });
        },
        
        //报名方法
        joinFunc: function(obj) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        var obj = that.enterDom.find('#war_enter_join');
                        obj.find('#war_join').attr('class', 'btn pop_btn_middle_disable').html(lang('had_registration'));
                        
                        var html = '';
                        for (var i = 0;i < res.list.length;i ++) {
                            html += '<li>' + res.list[i].nickname + '</li>';
                        }
                        obj.find('ul').html(html);
                        
                        obj = null;
                        
                        xGame.toast.show(lang('war_registration_sucess'));

                        if(player.info.god_entry == 1) {
                            player.updateinfo({god_entry: 0});//隐藏主城里报名的气泡
                        }
			
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -5:
                        xGame.toast.show(lang('had_registration'));
                        break;
                    case -4:
                        xGame.toast.show(lang('war_get_rule_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case -2:
                        xGame.toast.show(lang('war_registration_end'));
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'entry',
            },
            Func);
            Func = null;
        },

        buildEnter: function(res) {
            var that = this;

            //res.stage 1 活动未开启 2 报名阶段  4 活动结束 5 等待阶段
                    
            if (res.stage == 1) {
                xGame.updateCd('waropen', res.cd);
                that.e.find('.mask').hide();
            } else if (res.stage == 2) {
                that.fillJoinList(res.info, res.limit_level);
                xGame.updateCd('warjoin', res.cd);
                that.e.find('.mask').show();
            } else if (res.stage == 4) {
                that.fillHistoryList(res.info);
                that.e.find('.mask').show();
            } else if (res.stage == 5) {
                xGame.updateCd('warwait', res.cd);
                that.e.find('.mask').hide();
            } else if (res.stage == 6) {
                that.fillRiseList(res.info);
                xGame.updateCd('warrise', res.cd);
                that.e.find('.mask').show();
            }
            that.enterDom.attr('class','pop_page show' + res.stage);

        },
        
        //填充已报名列表
        fillJoinList: function(data, limit_level) {
            var obj = this.enterDom.find('#war_enter_join');

            //参赛等级
            obj.find('#war_need_lv').html(limit_level);

            var html = '';
            for (var i = 0;i < data.list.length;i ++) {
                html += '<li>' + data.list[i].nickname + '</li>';
            }
            obj.find('ul').html(html);
            
            //obj.find('#war_need_lv').html(lang('war_need_lv', [data.limit_level]));
            if (data.is_entry == 1) {
                obj.find('#war_join').attr('class', 'btn pop_btn_middle_disable').html(lang('had_registration'));
            } else {
                obj.find('#war_join').attr('class', 'btn pop_btn_middle').html(lang('war_registration'));
            }
            
            html = null;
            obj = null;
        },
        
        //填充战神榜列表
        fillHistoryList: function(data){
            var that = this;
            var html = '';
            if (that.is_entry == 1) {
                that.enterDom.find('#war_my_history').css('display', 'block');
            } else {
                that.enterDom.find('#war_my_history').css('display', 'none');
            }
            data = data.list;
            for (var i = 0;i < data.length;i ++) {
                if (data[i] != undefined) {
                    html += '<li ' + (i < 2 ? 'class="sp"' : '') + '><em class="t1">' + data[i].round + '</em><em class="t2">' + data[i].nickname + '</em><em class="t3" uid="' + data[i].uid + '" serverid="' + data[i].serverid + '">' + lang('war_history') + '</em></li>';
                }
            }
            this.enterDom.find('#war_enter_history').find('ul').html(html);
            
            this.historyScroller = new iScroll('war_history_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            
            this.isTimeout.push(setTimeout(function() {
                that.historyScroller.refresh();
            },500));
            
        },
        
        //填充晋级列表
        fillRiseList: function(data) {
            var that = this;
            var risePage = this.enterDom.find('#war_enter_riselist');

            if (parseInt(data.myrank) == 0) {
                risePage.find('.nowrank_wrap').hide();
            } else {

                if (parseInt(data.isup) == 0) {
                    risePage.find('.nowrank_wrap').show();
                    risePage.find('#riselist_nowrank').html(data.myrank + lang('war_not_qualify'));
                } else if (parseInt(data.isup) == 1) {
                    risePage.find('.nowrank_wrap').show();
                    risePage.find('#riselist_nowrank').html(data.myrank);
                } else {
                    risePage.find('.nowrank_wrap').hide();
                }
            }
            risePage.find('#warworld_rise_stagename').html(lang('war_stagename' + data.pre_day));

            var html = '';
            var list = data.list;
            for(var i = 0; i < 6; i++) {
                if(list[i]) {
                    html += '<li class="riselist_li ' + (list[i].uid == player.info.uid ? 'now' : '') + '">';
                    html +=     '<div class="rank">' + list[i].rank + '</div>';
                    html +=     '<div class="namewrap">' + list[i].server + list[i].nickname  + '</div>';
                    html += '</li>';
                }
            }
            risePage.find('#riselist_ul').html(html);

            this.riseScroller = new iScroll('war_riselist_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.isTimeout.push(setTimeout(function () {
                that.riseScroller.refresh();
            }, 500));
        },

        //显示历程详情
        showHistoryDetatil: function(data){
            var that = this;
            this.enterDom.find('#war_history_d1').html('<em class="t1">' + data.info.rank + '</em><em class="t2">' + 'Lv.' + data.info.level + ' ' + data.info.nickname + '</em>');
            this.enterDom.find('#war_history_d2').html('<em class="t1">' + lang('war_server') + ':</em><em class="t2">' + data.info.server + '</em>');
            this.enterDom.attr('class','pop_page show4');
            
            var html = '';
            for (var i = 0;i < data.list.length;i ++) {
                html += '<li><em class="t1">' + data.list[i].round + data.list[i].turn + '</em>';
                html += '<span class="pvp_info"><em class="result '+ (parseInt(data.list[i].win) == 1 ? 'win' : '') +'"></em>';
                html += '<em class="player">' + data.info.nickname + ' VS ' + data.list[i].nickname + '</em>';
                html += '<em class="t2" vid="' + data.list[i].id + '" stage="' + data.list[i].stage + '">' + lang('war_view') + '</em></span></li>';
            }
            this.enterDom.find('#war_enter_history_detail').find('ul').html(html);
            
            this.detailScroller = new iScroll('war_detail_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });
            
            this.isTimeout.push(setTimeout(function() {
                that.detailScroller.refresh();
            },500));
            
            this.enterDom.attr('class','pop_page show4_2');
        },
        
        //显示历程战报
        viewHistoryReport: function(id, stage, justWatch, data) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        //是否需要判断是不是只是观看或者要结果弹窗
                        if (justWatch || res.battle.up == 0) {//只是手动点击以及非决胜局
                            that.isTimeout.push(setTimeout(function() {
                                xGame.loadBattle(res, null, 'war',
                                function() {
                                    if (data != undefined) {
                                        xGame.loadWorld('war', data);
                                    } else {
                                        xGame.loadWorld('war');
                                    }
                                    
                                });
                                xGame.unlock();
                            },
                            500));
                        } else {
                            that.isTimeout.push(setTimeout(function() {
                                xGame.loadBattle(res,
                                function() {
                                    miscpop.battleResultFromWar(res, id, stage);
                                },
                                'war');
                                xGame.unlock();
                            },
                            500));
                        }
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_report_fail'));
                        xGame.unlock();
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.unlock();
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'get_report_info',
                'id' : id,
                'stage' : stage
            },
            Func);
            Func = null;
        },
        
        //获得玩家历程方法
        getHistoryDetailFunc: function(uid, serverid) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.hDataDetail = res;
                        that.showHistoryDetatil(res);
                        break;
                    case -3:
                        xGame.toast.show(lang('war_get_history_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('war_not_over'));
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'get_report_list',
                'uid' : uid,
                'serverid' : serverid
            },
            Func);
            Func = null;
        },
        
        //获得奖励列表
        getRewardList: function(type) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.rewardDom.attr('class', 't' + type);
                        that.rewardDom.find('#war_reward_list').css('display', 'none').html('');
                        //get  0 未领取 -1 不可以领取 1 已领取
                        var html = '';
                        for (var i = 0;i < res.list.length;i ++) {
                            html += '<li>';
                            if (type == 1) {
                                if (res.list[i].reward.equip_list == undefined && res.list[i].reward.mount_list == undefined) {
                                    html += '<span class="box"></span>'; 
                                } else if ((res.list[i].reward.equip_list != undefined && res.list[i].reward.mount_list != undefined) || (res.list[i].reward.equip_list == undefined && res.list[i].reward.mount_list != undefined)){
                                    html += '<span class="horse" style="background-position:' + helper.getHorsePostion('small', res.list[i].reward.mount_list[0].id) + ';"></span>';
                                } else if (res.list[i].reward.equip_list != undefined && res.list[i].reward.mount_list == undefined){
                                    html += '<span class="equip" style="background-image:url(' + xGame.itemurl(res.list[i].reward.equip_list[0].id) + ');"></span>';
                                } 
                                html += '<div class="reward">';
                                if (res.list[i].reward.mount_list != undefined) {
                                    for (var k = 0; k < res.list[i].reward.mount_list.length;k ++) {
                                        html += '<p class="t2 horse" style="color:' + helper.getItemColor(res.list[i].reward.mount_list[k].quality) + '">' + res.list[i].reward.mount_list[k].name + '</p>';
                                    }
                                }
                                if (res.list[i].reward.equip_list != undefined) {
                                    for (var j = 0; j < res.list[i].reward.equip_list.length;j ++) {
                                        html += '<p class="t2 equip" style="color:' + helper.getItemColor(res.list[i].reward.equip_list[j].quality) + '">' + res.list[i].reward.equip_list[j].name + '</p>';
                                    }
                                }
                                if (res.list[i].reward.silver != undefined) {
                                    html += '<p class="t2 silver">' + res.list[i].reward.silver + '</p>';
                                }
                                if (res.list[i].reward.gold != undefined) {
                                    html += '<p class="t2 gold">' + res.list[i].reward.gold + '</p>';
                                }
                                if (res.list[i].reward.tavernjade != undefined) {
                                    html += '<p class="t2 gem3">' + res.list[i].reward.tavernjade + '</p>';
                                }
                                if (res.list[i].reward.jade != undefined) {
                                    html += '<p class="t2 jade">' + res.list[i].reward.jade + '</p>';
                                }
                                if (res.list[i].reward.soul != undefined) {
                                    html += '<p class="t2 soul">' + res.list[i].reward.soul + '</p>';
                                }
                                if (res.list[i].reward.gem != undefined) {
                                    html += '<p class="t2 gempart">' + res.list[i].reward.gem + '</p>';
                                }
                                if (res.list[i].reward.reputation != undefined) {
                                    html += '<p class="t2 reputation">' + res.list[i].reward.reputation + '</p>';
                                }
                                html += '</div>';
                            } else {
                                html += '<span class="text">' + lang('war_yazhu_reward', [res.list[i].reward.gold]) + '</span>';
                            }
                            html += '<p class="title">' + res.list[i].round + '</p>';
                            if (res.list[i].get == 0) {
                                html += '<button class="btn pop_btn_middle" gid="' + res.list[i].id + '" type="' + type + '">' + lang('receive_reward') + '</button>';
                            } else if (res.list[i].get == -1) {
                                html += '<button class="btn pop_btn_middle_disable">' + lang('not_reached') + '</button>';
                            } else if (res.list[i].get == 1) {
                                html += '<button class="btn pop_btn_middle_disable">' + lang('warena_have_fetch') + '</button>';
                            }
                            
                            html += '</li>';
                        }
                        that.rewardDom.find('#war_reward_list').html(html).css('display', 'block');
                        html = null;
                        
                        if (that.rewardScroller == null) {
                            that.rewardScroller = new iScroll('war_reward_scroller', {
                                bounce: false,
                                momentum: false,
                                hScrollbar: false,
                                vScrollbar: false
                            });
                        }
                        that.rewardDom.css('display', 'block');
                        that.rewardScroller.refresh();
                        that.rewardScroller.scrollTo(0,0);
                        break;
                    case -3:
                        xGame.toast.show(lang('get_reward_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('activity_cannot_receive'));
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'war',
                'm': 'get_reward_list',
                'type': type
            },
            Func);
            Func = null;
        },
        
        //获得奖励
        getReward: function(obj) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        player.updateinfo({
                            'silver' : res.silver,
                            'gold' : res.gold
                        });
                        obj.attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch'));
                        xGame.toast.show(lang('receive_complete'));
                        
                        break;
                    case -4:
                        xGame.toast.show(lang('war_get_equip_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('yuandan_error'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_reward_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('reward_had_receive'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'war',
                'm': 'get_reward',
                'type': obj.attr('type'),
                'id' : obj.attr('gid')
            },
            Func);
            Func = null;
        },
        
        /*********************简单的分割线，下面是pvp界面的方法***********************/

        buildPvp: function(res) {
            var that = this;
            
            that.roundid = res.info.roundid;
            res.cd = parseInt(res.cd);
            
            
            //初始化
            that.pvpDom.find('#pvp_left').attr('class', 'pvp_side left');
            that.pvpDom.find('#pvp_right').attr('class', 'pvp_side right');
            that.pvpDom.find('.war_pvp_btn').attr('class', 'btn pop_btn_middle war_pvp_btn').removeAttr('type').removeAttr('style');
            that.pvpDom.find('#pvp_round').removeAttr('class').html('');
            
            //构建左右数据，只是傻瓜式填充，按钮在这里进行第一次设置
            that.fillSide(1, res.info.play.left, res.info.report.left);
            that.fillSide(2, res.info.play.right, res.info.report.right);

            //当前排名数据
            if(res.is_entry == 1) {//参赛者
                var norank_dom = that.pvpDom.find('#pvp_nowrank');
                norank_dom.html(lang('war_pvp_rank_msg' + res.day, [res.info.rank, res.info.max_rank]));

                if (parseInt(res.info.rank) == 0 || parseInt(res.info.max_rank) == 0) {
                    norank_dom.hide();
                } else {
                    norank_dom.show();
                }

                if (player.info.uid == res.info.play.right.uid) {
                    norank_dom.addClass('right');
                } else {
                    norank_dom.removeClass('right');
                }
            }

            //如果小于5分03秒，不管什么按钮都设置为disable，按钮的第二次设置
            if (res.cd < 303) {
                that.pvpDom.find('.war_pvp_btn').attr('class', 'btn pop_btn_middle_disable war_pvp_btn');
            }
            
            //处理有三局两胜的情况 当三局两胜的时候,还有海选时轮空的显示也在这里处理了（海选uid== 0）
            if (res.info.play.left.win == 2 || res.info.play.right.win == 2 || res.info.play.left.uid == 0 || res.info.play.right.uid == 0) {
                var nobody;//没人的一方
                var nobody_dom;//没人的一方dom
                
                //当是16强赛的时候，有三局两胜的情况
                //todo 清空比分ok，支持数ok，先后手
                if (res.info.play.left.win == 2 || res.info.play.right.win == 2) {
                    var mybody_dom;
                    var mywin_dom;
                    if (res.info.play.left.win == 2) {
                        nobody = res.info.play.right;
                        nobody_dom = that.pvpDom.find('#pvp_right');
                        mybody_dom = that.pvpDom.find('#pvp_left');
                        mywin_dom =  that.pvpDom.find('span#left_win_num');
                    } else {
                        nobody = res.info.play.left;
                        nobody_dom = that.pvpDom.find('#pvp_left');
                        mybody_dom = that.pvpDom.find('#pvp_right');
                        mywin_dom = that.pvpDom.find('span#right_win_num');
                    }
                    that.fillSupporter(mybody_dom.find('.supporter'), 0);
                    mywin_dom.attr('class','win_t win0');
                } else {//海选轮空的时候
                    if (res.info.play.left.uid == 0) {
                        nobody = res.info.play.left;
                        nobody_dom = that.pvpDom.find('#pvp_left');
                    } else {
                        nobody = res.info.play.right;
                        nobody_dom = that.pvpDom.find('#pvp_right');
                    }
                }
                
                nobody_dom.removeClass('male').removeClass('female');
                that.pvpDom.find('.more').removeClass('more');
                //当对面uid== 0 轮空文字 ，！= 0 白板
                if (nobody.uid == 0) {
                    nobody_dom.addClass('block');
                    //轮空显示奖励
                    that.fillReward(res.info.reward, that.pvpDom.find('#pvp_reward'));
                } else {
                    nobody_dom.addClass('hide');
                    //白板的时候不显示支持按钮，轮空文字的时候不做处理
                    that.pvpDom.find('.war_pvp_btn[type="support"]').removeAttr('style');
                    //白板的传入空对象就不显示奖励
                    that.fillReward({}, that.pvpDom.find('#pvp_reward'));
                }
                
                //倒计时文字
                that.pvpDom.find('#pvp_time').find('em').html(lang('war_wait_cd'));
                that.stage = 0;
                //轮空的时候不显示
                that.pvpDom.find('#pvp_name').html('');
                that.pvpDom.find('#left_win_num').removeClass();
                that.pvpDom.find('#right_win_num').removeClass();

                that.pvpDom.find('.win_t').show();
            } else {//非三局两胜战斗
                that.pvpDom.find('#pvp_time').find('em').html(lang('war_open_cd'));
                //组装奖励
                that.fillReward(res.info.reward, that.pvpDom.find('#pvp_reward'));
                if (res.info.mode != 1) {
                    that.pvpDom.find('#pvp_round').attr('class', 'r' + res.info.stage);
                    that.pvpDom.find('#pvp_round').html('<span class="w' + res.info.mode + '"></span>');
                    that.pvpDom.find('.win_t').show();
                } else {//一场定胜负，不显示 0 vs 0
                    that.pvpDom.find('#pvp_round').attr('class', 'r0');
                    that.pvpDom.find('#pvp_round').html('<span class="w1"></span>');
                    that.pvpDom.find('.win_t').hide();
                }
                that.stage = res.info.stage;
                //非轮空的时候不显示
                that.pvpDom.find('#pvp_name').html(res.info.reward.name);
            }
            
            
            
            //只有第一局的时候，会根据刷新接口显示先手,加上轮空的时候也刷新支持数
            if (that.stage == 1 || (that.stage == 0 && (res.info.play.left.uid == 0 || res.info.play.right.uid == 0))) {
                if (res.cd > 303) {//如果进来时就就小于5分钟了就不更新支持数
                    that.autoRefresh = setInterval(function() { 
                        that.refreshSupport();
                    }, 15000);
                }
            } else {//第二局第三局的时候没支持过的时候隐藏支持按钮，已经支持过的不处理
                if (that.stage != 0 && parseInt(that.is_entry) >= 0) {
                    that.pvpDom.find('.war_pvp_btn[type="support"]').removeAttr('style');
                }
            }
            
            //cd时间
            xGame.updateCd('warpvp', res.cd);
        },
        
        //自动刷新接口
        refreshSupport: function(justRefresh) {
            var that = this;
            
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        //不管发生什么，先清除自增
                        if (that.autoPlus != null) {
                            clearInterval(that.autoPlus);
                            that.autoPlus = null;
                        }
                        if (that.pvpDom != undefined) {
                            //如果只刷新
                            if (justRefresh) {
                                that.leftSupport = parseInt(res.left);
                                that.rightSupport = parseInt(res.right); 
                                that.checkMore();   
                            } else {
                                that.autoUpFunc(parseInt(res.left), parseInt(res.right));
                            }
                        }
                        break;
                }
            };
            var errorFunc = function() {
                return false;
            };
            loadContents({
                'c': 'war',
                'm': 'get_support',
                'id' : that.roundid 
            },
            Func, true, errorFunc);
            Func = null;
        },
        
        //这个方法自动将获得的数字分成4次随机显示，中间要不断调用checkMore来达到变位置的效果
        autoUpFunc: function(left, right) {
            //如果传入比现在小的数字，默认现在的
            if (left < this.leftSupport || right < this.rightSupport || (left == 0 && right == 0)) {
                this.checkMore();
                console.log('直接返回');
                return false;
            }
            
            var that = this;
            
            var sleft = left - that.leftSupport;
            var sright = right - that.rightSupport;
            var rule = [0.04, 0.06, 0.02, 0.09, 0.04, 0.05, 0.24, 0.04, 0.08, 0.24, 0.11, 0.01, 0.03, 0.03, 0.04];
            var randomsort = function(a, b) {
                    return Math.random()>.5 ? -1 : 1;
            }
            rule = rule.sort(randomsort);
            var leftArr = that.getArr(sleft, rule);
            rule = rule.sort(randomsort);
            var rightArr = that.getArr(sright, rule);
            
            rule = null;
            randomsort= null;
            
            var i = 1;
            var auto = function() {
                if (i == 15 || (CD['warpvp'] != undefined && CD['warpvp'].s < 303)) {//其实最后一步是不算的，只要保证前面小于我的数字就行
                    clearInterval(that.autoPlus);
                    that.autoPlus = null;
                    
                    that.leftSupport = left;
                    that.rightSupport = right;
                    
                    //console.log(i);
                    //console.log('that.leftSupport = ' + that.leftSupport);
                    //console.log('that.rightSupport = ' + that.rightSupport);
                    that.checkMore();
                    
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
                
                that.leftSupport += leftArr.shift();
                that.rightSupport += rightArr.shift();
                //console.log(i);
                //console.log('that.leftSupport = ' + that.leftSupport);
                //console.log('that.rightSupport = ' + that.rightSupport);
                that.checkMore();
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
        
        //显示先手
        checkMore: function() {
            this.fillSupporter(this.leftDom, this.leftSupport);
            this.fillSupporter(this.rightDom, this.rightSupport);
            this.pvpDom.find('.more').removeClass('more');
            if (this.leftSupport >= this.rightSupport) {
                this.pvpDom.find('#pvp_left').addClass('more');
            } else {
                this.pvpDom.find('#pvp_right').addClass('more');
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
                        if (res.info.level != undefined) {
                            obj.find('.t1').find('em').html('Lv.' + res.info.level);
                        }
                        
                        if (res.info.country != undefined) {
                            obj.find('.t3').find('em').html(res.info.country == '' ? lang('no') : res.info.country);
                        }
                        
                        xGame.updateCd('warsync', 10);
                        xGame.toast.show(lang('war_sync_sucess'));
                        break;
                    case -5:
                        xGame.toast.show(lang('war_save_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('war_get_info_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('war_join_not_end'));
                        break;
                    case -2:
                        xGame.toast.show(lang('war_sync_later'));
                        break;
                    case -1:
                        xGame.toast.show(lang('have_not_had_registration'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'sync_battle_info',
            },
            Func);
            Func = null;
        },
        
        //支持方法 side 1 左 2 右
        supportFunc: function(side, serverid, uid) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        xGame.toast.show(lang('war_support_sucess'));
                        var me;
                        var duimian;
                        
                        if (side == 1) {
                            me = 'left';
                            duimian = 'right';
                            that.leftSupport += 1;
                        } else {
                            me = 'right';
                            duimian = 'left';
                            that.rightSupport += 1;
                        }
                        
                        that.pvpDom.find('#pvp_' + me).find('.war_pvp_btn').attr('class', 'btn pop_btn_middle_disable war_pvp_btn').html(lang('war_had_support')).show();
                        that.pvpDom.find('#pvp_' + duimian).find('.war_pvp_btn').removeAttr('style');
                        
                        that.checkMore();
                        
                        me = duimian = null;
                        break;
                    case -1:
                        xGame.toast.show(lang('war_support_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'support',
                'side' : side,
                'serverid' : serverid,
                'uid' : uid
            },
            Func);
            Func = null;
        },
        
        //填充奖励
        fillReward: function(data, obj) {
            var html = '';

            //不显示奖励
            if(typeof data.integral == 'undefined') {
                obj.hide();
            } else {
                var tip = '';
                var reward = 0;
                
                if(this.is_entry == 1) {//参赛者奖励
                    tip = lang('war_win_get') + ':';
                    reward = lang('war_win_reward_score', [data.integral]);
                } else {//支持者奖励
                    tip = lang('war_win_get_support') + ':';
                    reward = lang('war_win_reward_score', [data.support]);
                }
                obj.find('.pvp_reward_tip').html(tip);
                obj.find('.pvp_reward_wrap').html(reward);
                obj.show();
            }
        },
        
        //填充左右
        fillSide: function(side, data, report, entry){
            var that = this;
            var dom;
            var html = '';
            var log = '';
            
            //公共部分
            var me = '';
            var duimian = '';
            for (var i in report) {
                if (report[i] != undefined) {
                    duimian = '<em class="v1">' + report[i].nickname + '</em>';
                    me = '<em class="v1">' + data.nickname + '</em>';
                    log += '<li>';
                    
                    if (report[i].side == 1) {
                        log += (duimian + '<span class="vs">vs</span>' + me);
                    } else {
                        log += (me + '<span class="vs">vs</span>' + duimian);
                    }
                    log += '<em class="v2" vid="' + report[i].id + '" stage="' + report[i].stage + '">' + lang('war_view') + '</em>';
                    log += '</li>';
                }
            }
            
            //区分部分
            if (side == 1) {
                dom = that.pvpDom.find('#pvp_left');
                this.pvpDom.find('#war_pvpleft_list').html(log);
                this.leftScroller = new iScroll('war_pvpleft_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
                
                this.isTimeout.push(setTimeout(function() {
                    that.leftScroller.refresh();
                },500));
                
                that.pvpDom.find('#left_win_num').attr('class','win_t win' + data.win);
                that.leftSupport = parseInt(data.support);
            } else {
                dom = that.pvpDom.find('#pvp_right');
                
                this.pvpDom.find('#war_pvpright_list').html(log);
                this.rightScroller = new iScroll('war_pvpright_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
                
                this.isTimeout.push(setTimeout(function() {
                    that.rightScroller.refresh();
                },500));
                
                that.pvpDom.find('#right_win_num').attr('class','win_t win' + data.win);
                that.rightSupport = parseInt(data.support);
            }
            
            //公共部分
            dom.addClass(data.gender == 1 ? 'male' : 'female');
            if (data.first == 1) {
                dom.addClass('more');
            }
            that.fillInfo(dom.find('.info'), data);
            that.fillSupporter(dom.find('.supporter'), data.support);
                
            //判断按钮 1 参与者 0 观众 -1 支持左方  -2 支持右方
            //按钮的现实最后再外面再判断一次是不是2连
            if (that.is_entry == 1) {
                //是自己
                if (data.uid == player.info.uid) {
                    dom.find('.war_pvp_btn').attr('type', 'sync').html(lang('war_sync')).show();
                }
            } else {
                if (that.is_entry == 0) {//还未支持
                    if (data.uid != 0) {
                        dom.find('.war_pvp_btn').attr({
                            'side' : side,
                            'serverid' : data.serverid,
                            'uid' : data.uid,
                            'type' : 'support',
                            'class': 'btn pop_btn_middle war_pvp_btn'
                        }).html(lang('support')).show();
                    }
                } else {//已支持过
                    if ((side == 1 && that.is_entry == -1) || (side == 2 && that.is_entry == -2)) {
                        dom.find('.war_pvp_btn').attr({
                            'class': 'btn pop_btn_middle_disable war_pvp_btn',
                            'type': 'support'
                        }).html(lang('war_had_support')).show();
                    }
                }
                me = null;
                duimian = null;
            }
            
            html = null;
            log = null;
            dom = null;

        },
        
        //填充用户数据
        fillInfo: function(obj, data) {
            var html = '';
            
            html += '<li class="t1">' + data.nickname + ' (<em>Lv.' + data.level + '</em>)</li>';
            html += '<li class="t2">' + lang('war_warrecord') + ':<em>' + lang('war_warrecord_word', [data.round_win, data.round_lost]) + '</em></li>';
            html += '<li class="t3">' + lang('country') + ':<em>' + (data.countryid == 0 ? lang('warena_no_rank') : data.country) + '</em></li>';
            html += '<li class="t4">' + lang('war_server') + ':<em>' + data.server + '</em></li>';
            
            obj.html(html);
            html = null;
        },
        
        // 填充支持者
        fillSupporter: function(obj, num) {
            if (obj != undefined) {
                obj.html(num);
            }
        },
        
        uninstall: function(obj) {
            if (obj != null) {
                obj.destroy();
            }
            obj = null;
        },
        
        //根据时间差判断是否刷新或者只是减去时间
        refreshByTime: function(s) {
            if (CD['warsync'] != undefined) {//同步按钮特殊处理
                if(CD['warsync'].s - s > 0) {
                    xGame.updateCd('warsync', CD['warsync'].s - s);
                } else {
                    xGame.updateCd('warsync', 0);
                }
            } 
            
            if (CD['waropen'] != undefined) {//战神殿开启
                if(CD['waropen'].s - s > 0) {
                    xGame.updateCd('waropen', CD['waropen'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['warwait'] != undefined) {//战神殿等待
                if(CD['warwait'].s - s > 0) {
                    xGame.updateCd('warwait', CD['warwait'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['warjoin'] != undefined) {
                if(CD['warjoin'].s - s > 0) {
                    xGame.updateCd('warjoin', CD['warjoin'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['warpvp'] != undefined) {
                if(CD['warpvp'].s - s > 0) {
                    xGame.updateCd('warpvp', CD['warpvp'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['warrise'] != undefined) {//战神殿等待
                if(CD['warrise'].s - s > 0) {
                    xGame.updateCd('warrise', CD['warrise'].s - s);
                } else {
                    that.reload();
                }
            } 
        },
        
        //刷新方法
        reload: function() {
            this.clear();
            xGame.loadWorld('war');
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#warworld_back').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.enterDom) {
                this.enterDom.undelegate('#warworld_rule_btn', 'touchend');
                this.enterDom.undelegate('#warworld_queryscore_btn', 'touchend');
                this.enterDom.undelegate('#queryscore_hide', 'touchend');
                this.enterDom.undelegate('#warworld_warhero_btn', 'touchend');
                this.enterDom.undelegate('#warworld_scoreshop_btn', 'touchend');
                this.enterDom.undelegate('#rule_hide', 'touchend');
                this.enterDom.undelegate('#war_join.pop_btn_middle', 'touchend');
                this.enterDom.find('#war_history_list').undelegate('.t3', 'click');
                this.enterDom.undelegate('#war_detail_back', 'touchend');
                this.enterDom.find('#war_detail_list').undelegate('.t2', 'click');
                this.enterDom.find('#war_enter_rewardbox').undelegate('span', 'click');
                this.enterDom.undelegate('#war_my_history', 'click');
                this.enterDom.remove();
                this.enterDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.undelegate('.pop_btn_yellow', 'click');
                this.rewardDom.undelegate('.pop_btn_middle', 'click');
                this.rewardDom.undelegate('#war_reward_hide', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.pvpDom) {
                this.pvpDom.undelegate('.war_pvp_btn.pop_btn_middle', 'click');
                this.pvpDom.find('.pvp_side').undelegate('.v2', 'click');
                this.pvpDom.remove();
                this.pvpDom = null;
            }
        },
        
        clear: function() {

            xGame.unlock();
            if (this.autoRefresh != null) {
                clearInterval(this.autoRefresh);
                this.autoRefresh = null;
            }
            if (this.autoPlus != null) {
                clearInterval(this.autoPlus);
                this.autoPlus = null;
            }
            
            if (this.e == undefined) {
                return false;
            }
            
            this.roundid = null;
            this.stage = null;
            this.palace = null;
            
            this.leftDom = null;
            this.rightDom = null;
            
            this.leftSupport = null;
            this.rightSupport = null;
            
            this.hDataDetail = null;

            this.is_entry = 0;
        
            this.uninstall(this.historyScroller);
            this.uninstall(this.detailScroller);
            this.uninstall(this.leftScroller);
            this.uninstall(this.rightScroller);
            this.uninstall(this.rewardScroller);
            this.uninstall(this.riseScroller);
            
            xGame.updateCd('waropen', 0);
            xGame.updateCd('warwait', 0);
            xGame.updateCd('warjoin', 0);
            xGame.updateCd('warpvp', 0);
            xGame.updateCd('warsync', 0);
            xGame.updateCd('warrise', 0);
            
            this.unbindAll();
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            
        }
    };
})();