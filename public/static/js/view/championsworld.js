/* 战神殿 */
(function() {
    championsWorldClass = function() {
        this.e = null;
        
        this.enterDom;//进入dom
        this.pvpDom;//战神殿对决dom
        
        this.rewardDom;//奖励弹窗dom
        
        this.historyScroller;//战神榜的scroller
        this.detailScroller;//战神榜的scroller
        
        this.leftScroller;//战神榜的scroller
        this.rightScroller;//战神榜的scroller
        
        this.rewardScroller;//奖励的scroller
        
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
        this.isTimeout = [];
        
    };

    championsWorldClass.prototype = {
        init: function(returnData) {
            
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                switch (res.status) {
                    
                    case 1:
                        //这里面做很多初始化的东西
                        that.initPage();
                        that.palace = res.palace;
                        //res.stage 1 活动未开启 2 报名阶段  4 活动结束 3 战斗阶段
                        
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
                        } 
                        $('#worldloading').removeClass('black').hide();
                        break;
                    case -1:
                        xGame.toast.show(lang('champions_get_info_fail'));
                        xGame.loadWorld('city');
                        break;
                    case -3:
                        xGame.toast.show(lang('champions_join_fail'));
                        xGame.loadWorld('city');
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.loadWorld('city');
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
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
                        
                        //res.stage 1 活动未开启 2 报名阶段  4 活动结束 3 战斗阶段
                        
                        if (res.stage != 3) {
                            that.buildEnter(res);
                            that.e.attr('class', 'world_page bg1');
                        } else {
                            that.buildPvp(res);
                            that.e.attr('class', 'world_page bg2');
                        } 
                        
                        break;
                    case -1:
                        xGame.toast.show(lang('champions_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.loadWorld('city');
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
                'm': 'index'
            },
            initRender, true); 
            initRender = null;
        },

        initPage: function() {
            if (this.e != null) {
                return false;
            }

            $('#header').hide();
            $('#footer').hide();

            $('div#world').append($.template('championsworld_tmpl'));

            this.e = $('#championsworld_page');
            this.enterDom = this.e.find('#champions_enter');
            this.pvpDom = this.e.find('#champions_pvp');
            this.rewardDom = this.enterDom.find('#champions_reward_dom');
            
            this.leftDom = this.pvpDom.find('#pvp_left').find('.supporter');
            this.rightDom = this.pvpDom.find('#pvp_right').find('.supporter');
            
            
            this.e.find('#championsworld_back').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                e.stopPropagation();
                xGame.loadWorld('city');
            });
            
            this.autoRefresh = null;
            
            this.leftSupport = 0;
            this.rightSupport = 0;
            
            this.bindAll();
            
        },
        
        bindAll: function(){
            var that = this;
            
            //显示规则
            that.enterDom.undelegate('#show_rule', 'touchend').delegate('#show_rule', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.enterDom.find('#champions_enter_rule').css('display','block');
                that.enterDom.find('#champions_enter_rule_mask').css('display','block');
            });
            
            //战帝堂
            that.enterDom.undelegate('#go_to_house', 'touchend').delegate('#go_to_house', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.palace == 0) {
                    xGame.toast.show(lang('champions_not_open'));
                    return ;
                }
                xGame.loadWorld('palacehero');
            });
            
            //关闭规则
            that.enterDom.undelegate('#rule_hide', 'touchend').delegate('#rule_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.enterDom.find('#champions_enter_rule').css('display','none');
                that.enterDom.find('#champions_enter_rule_mask').css('display','none');
            });
            
            //报名按钮
            that.enterDom.undelegate('#champions_join.pop_btn_middle', 'touchend').delegate('#champions_join.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.joinFunc();
            });
            
            //历程按钮
            that.enterDom.find('#champions_history_list').undelegate('.t3', 'click').delegate('.t3', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getHistoryDetailFunc($(this).attr('uid'), $(this).attr('serverid'));
            });
            
            //返回按钮
            that.enterDom.undelegate('#champions_detail_back', 'touchend').delegate('#champions_detail_back', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.enterDom.attr('class','pop_page show4');
                if (that.historyScroller != null){
                    that.historyScroller.refresh();
                }
                
            });
            
            //点击查看按钮
            that.enterDom.find('#champions_detail_list').undelegate('.t2', 'click').delegate('.t2', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.viewHistoryReport($(this).attr('vid'), $(this).attr('stage'), true, that.hDataDetail);
            });
            
            //点击宝箱
            that.enterDom.find('#champions_enter_rewardbox').undelegate('span', 'click').delegate('span', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                //1 参赛者 2 观看者 默认1
                that.getRewardList(1);
            });
            
            //点击我的历程
            that.enterDom.undelegate('#champions_my_history', 'click').delegate('#champions_my_history', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getHistoryDetailFunc(player.info.uid, xGame.server_prefix.replace(islocal ? 't' : 's',''));
            });
            
            that.rewardDom.undelegate('.pop_btn_yellow', 'click').delegate('.pop_btn_yellow', 'click',
            function() {
                if (xGame.checkLock(true) || that.rewardDom.hasClass('t' + $(this).attr('type'))) { //不设置属性
                    return false;
                }
                that.rewardDom.find('#champions_reward_list').html('');
                that.rewardDom.attr('class', 't' + $(this).attr('type'));
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
            that.rewardDom.undelegate('#champions_reward_hide', 'click').delegate('#champions_reward_hide', 'click',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.rewardScroller != null) {
                    that.rewardScroller.destroy();
                    that.rewardScroller = null;
                }
                that.rewardDom.css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });
            
            /************分割线******************/
            
            //左右下方按钮
            that.pvpDom.undelegate('.champion_pvp_btn.pop_btn_middle', 'click').delegate('.champion_pvp_btn.pop_btn_middle', 'click',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('type') == 'sync') {
                    that.syncFunc($(this).parent());
                } else if ($(this).attr('type') == 'support') {
                    that.supportFunc($(this).attr('side'), $(this).attr('serverid'), $(this).attr('uid'));
                }
                e.preventDefault();
                e.stopPropagation();
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
                        var obj = that.enterDom.find('#champions_enter_join');
                        obj.find('#champions_join').attr('class', 'btn pop_btn_middle_disable').html(lang('had_registration'));
                        
                        var html = '';
                        for (var i = 0;i < res.list.length;i ++) {
                            html += '<li>' + res.list[i].nickname + '</li>';
                        }
                        obj.find('ul').html(html);
                        
                        obj = null;
                        
                        xGame.toast.show(lang('champions_registration_sucess'));

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
                        xGame.toast.show(lang('champions_get_rule_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case -2:
                        xGame.toast.show(lang('champions_registration_end'));
                        break;
                    case -1:
                        xGame.toast.show(lang('champions_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
                'm': 'entry',
            },
            Func);
            Func = null;
        },

        buildEnter: function(res) {
            var that = this;

            //res.stage 1 活动未开启 2 报名阶段  4 活动结束 5 等待阶段
                    
            if (res.stage == 1) {
                xGame.updateCd('championsopen', res.cd);
            } else if (res.stage == 2) {
                that.fillJoinList(res.info);
                xGame.updateCd('championsjoin', res.cd);
            } else if (res.stage == 4) {
                that.fillHistoryList(res.info);
            } else if (res.stage == 5) {
                xGame.updateCd('championswait', res.cd);
            }
            that.enterDom.attr('class','pop_page show' + res.stage);

        },
        
        //填充已报名列表
        fillJoinList: function(data) {
            var obj = this.enterDom.find('#champions_enter_join');
            var html = '';
            for (var i = 0;i < data.member_list.length;i ++) {
                html += '<li>' + data.member_list[i].nickname + '</li>';
            }
            obj.find('ul').html(html);
            
            obj.find('#champions_need_lv').html(lang('champions_need_lv', [data.limit_level]));
            if (data.is_entry == 1) {
                obj.find('#champions_join').attr('class', 'btn pop_btn_middle_disable').html(lang('had_registration'));
            } else {
                obj.find('#champions_join').attr('class', 'btn pop_btn_middle').html(lang('champions_registration'));
            }
            
            html = null;
            obj = null;
        },
        
        //填充战神榜列表
        fillHistoryList: function(data){
            var that = this;
            var html = '';
            if (data.entry == 1) {
                that.enterDom.find('#champions_my_history').css('display', 'block');
            } else {
                that.enterDom.find('#champions_my_history').css('display', 'none');
            }
            data = data.list;
            for (var i = 0;i < data.length;i ++) {
                if (data[i] != undefined) {
                    html += '<li ' + (i < 2 ? 'class="sp"' : '') + '><em class="t1">' + data[i].round + '</em><em class="t2">' + data[i].nickname + '</em><em class="t3" uid="' + data[i].uid + '" serverid="' + data[i].serverid + '">' + lang('champions_history') + '</em></li>';
                }
            }
            this.enterDom.find('#champions_enter_history').find('ul').html(html);
            
            this.historyScroller = new iScroll('champions_history_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            
            this.isTimeout.push(setTimeout(function() {
                that.historyScroller.refresh();
            },500));
            
        },
        
        //显示历程详情
        showHistoryDetatil: function(data){
            var that = this;
            this.enterDom.find('#champions_history_d1').html('<em class="t1">' + data.info.rank + '</em><em class="t2">' + 'Lv.' + data.info.level + ' ' + data.info.nickname + '</em>');
            this.enterDom.find('#champions_history_d2').html('<em class="t1">' + lang('champions_server') + ':</em><em class="t2">' + data.info.server + '</em>');
            this.enterDom.attr('class','pop_page show4');
            
            var html = '';
            for (var i = 0;i < data.list.length;i ++) {
                html += '<li><em class="t1">' + data.list[i].round + data.list[i].turn + '</em><em class="t2" vid="' + data.list[i].id + '" stage="' + data.list[i].stage + '">' + lang('champions_view') + '</em></li>';
            }
            this.enterDom.find('#champions_enter_history_detail').find('ul').html(html);
            
            this.detailScroller = new iScroll('champions_detail_scroller', {
                bounce: false,
                momentum: false,
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
                                xGame.loadBattle(res, null, 'champions',
                                function() {
                                    if (data != undefined) {
                                        xGame.loadWorld('champions', data);
                                    } else {
                                        xGame.loadWorld('champions');
                                    }
                                    
                                });
                                xGame.unlock();
                            },
                            500));
                        } else {
                            that.isTimeout.push(setTimeout(function() {
                                xGame.loadBattle(res,
                                function() {
                                    miscpop.battleResultFromChampions(res, id, stage);
                                },
                                'champions');
                                xGame.unlock();
                            },
                            500));
                        }
                        break;
                    case -1:
                        xGame.toast.show(lang('champions_get_report_fail'));
                        xGame.unlock();
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.unlock();
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
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
                        xGame.toast.show(lang('champions_get_history_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('champions_not_over'));
                        break;
                    case -1:
                        xGame.toast.show(lang('champions_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
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
                        that.rewardDom.find('#champions_reward_list').css('display', 'none').html('');
                        //get  0 未领取 -1 不可以领取 1 已领取
                        var html = '';
                        for (var i = 0;i < res.list.length;i ++) {
                            html += '<li>';
                            if (type == 1) {

                                if (res.list[i].reward.general_list != undefined) {
                                    html += '<span class="general" style="background-image:url('+ xGame.avatar(res.list[i].reward.general_list[0].image) +');"></span>';
                                } else if (res.list[i].reward.mount_list != undefined) {
                                    html += '<span class="horse" style="background-position:' + helper.getHorsePostion('small', res.list[i].reward.mount_list[0].id) + ';"></span>';
                                } else if (res.list[i].reward.equip_list != undefined) {
                                    html += '<span class="equip" style="background-image:url(' + xGame.itemurl(res.list[i].reward.equip_list[0].id) + ');"></span>';
                                } else {
                                    html += '<span class="box"></span>';
                                }

                                html += '<div class="reward">';

                                if (res.list[i].reward.general_list != undefined) {
                                    for (var z = 0, len = res.list[i].reward.general_list.length; z < len; z++) {
                                        html += '<p class="t2 general">' + res.list[i].reward.general_list[z].name + '</p>';
                                    }
                                }

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
                                html += '<span class="text">' + lang('champions_yazhu_reward', [res.list[i].reward.gold]) + '</span>';
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
                        that.rewardDom.find('#champions_reward_list').html(html).css('display', 'block');
                        html = null;
                        
                        if (that.rewardScroller == null) {
                            that.rewardScroller = new iScroll('champions_reward_scroller', {
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
                        xGame.toast.show(lang('champions_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'god',
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
                        xGame.toast.show(lang('champions_get_equip_fail'));
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
                'c': 'god',
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
            that.pvpDom.find('.champion_pvp_btn').attr('class', 'btn pop_btn_middle champion_pvp_btn').removeAttr('type').removeAttr('style');
            that.pvpDom.find('#pvp_round').removeAttr('class').html('');
            
            //构建左右数据，只是傻瓜式填充，按钮在这里进行第一次设置
            that.fillSide(1, res.info.play.left, res.info.report.left, res.info.entry);
            that.fillSide(2, res.info.play.right, res.info.report.right, res.info.entry);
            
            //如果小于60秒，不管什么按钮都设置为disable，按钮的第二次设置
            if (res.cd < 65) {
                that.pvpDom.find('.champion_pvp_btn').attr('class', 'btn pop_btn_middle_disable champion_pvp_btn');
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
                    that.pvpDom.find('.champion_pvp_btn[type="support"]').removeAttr('style');
                    //白板的传入空对象就不显示奖励
                    that.fillReward({}, that.pvpDom.find('#pvp_reward'));
                }
                
                //倒计时文字
                that.pvpDom.find('#pvp_time').find('em').html(lang('champions_wait_cd'));
                that.stage = 0;
                //轮空的时候不显示
                that.pvpDom.find('#pvp_name').html('');
                that.pvpDom.find('#left_win_num').removeClass();
                that.pvpDom.find('#right_win_num').removeClass();
            } else {//非三局两胜战斗
                that.pvpDom.find('#pvp_time').find('em').html(lang('champions_open_cd'));
                //组装奖励
                that.fillReward(res.info.reward, that.pvpDom.find('#pvp_reward'));
                if (res.info.mode != 1) {
                    that.pvpDom.find('#pvp_round').attr('class', 'r' + res.info.stage);
                    that.pvpDom.find('#pvp_round').html('<span class="w' + res.info.mode + '"></span>');
                } else {
                    that.pvpDom.find('#pvp_round').attr('class', 'r0');
                    that.pvpDom.find('#pvp_round').html('<span class="w1"></span>');
                }
                that.stage = res.info.stage;
                //非轮空的时候不显示
                that.pvpDom.find('#pvp_name').html(res.info.type);
            }
            
            
            
            //只有第一局的时候，会根据刷新接口显示先手,加上轮空的时候也刷新支持数
            if (that.stage == 1 || (that.stage == 0 && (res.info.play.left.uid == 0 || res.info.play.right.uid == 0))) {
                if (res.cd > 65) {//如果进来时就就小于1分钟了就不更新支持数
                    that.autoRefresh = setInterval(function() { 
                        that.refreshSupport();
                    }, 15000);
                }
            } else {//第二局第三局的时候没支持过的时候隐藏支持按钮，已经支持过的不处理
                if (that.stage != 0 && parseInt(res.info.entry) >= 0) {
                    that.pvpDom.find('.champion_pvp_btn[type="support"]').removeAttr('style');
                }
            }
            
            //cd时间
            xGame.updateCd('championspvp', res.cd);
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
                'c': 'god',
                'm': 'get_support',
                'id' : that.roundid 
            },
            Func, true, errorFunc);
            Func = null;
            errorFunc = null;
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
                if (i == 15 || (CD['championspvp'] != undefined && CD['championspvp'].s < 65)) {//其实最后一步是不算的，只要保证前面小于我的数字就行
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
                            obj.find('.t2').find('em').html('Lv.' + res.info.level);
                        }
                        
                        if (res.info.country != undefined) {
                            obj.find('.t3').find('em').html(res.info.country == '' ? lang('no') : res.info.country);
                        }
                        
                        xGame.updateCd('championssync', 10);
                        xGame.toast.show(lang('champions_sync_sucess'));
                        break;
                    case -5:
                        xGame.toast.show(lang('champions_save_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('champions_get_info_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('champions_join_not_end'));
                        break;
                    case -2:
                        xGame.toast.show(lang('champions_sync_later'));
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
                'c': 'god',
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
                        xGame.toast.show(lang('champions_support_sucess'));
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
                        
                        that.pvpDom.find('#pvp_' + me).find('.champion_pvp_btn').attr('class', 'btn pop_btn_middle_disable champion_pvp_btn').html(lang('champions_had_support')).show();
                        that.pvpDom.find('#pvp_' + duimian).find('.champion_pvp_btn').removeAttr('style');
                        
                        that.checkMore();
                        
                        me = duimian = null;
                        break;
                    case -1:
                        xGame.toast.show(lang('champions_support_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
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
            html += '<li><em class="t1">' + lang('champions_win_get') + ':</em><em class="t2"></em></li>';
            if (data.general_list != undefined) {
                for (var i = 0, len = data.general_list.length; i < len; i++) {
                    html += '<li><em class="t1">' + lang('general') + ':</em><em class="t2" style="padding-left:5px;">' + data.general_list[i].name + '</em></li>';
                }
            }
            if (data.equip_list != undefined) {
                for (var j = 0;j < data.equip_list.length;j ++) {
                    html += '<li><em class="t1">' + lang('champions_equip') + ':</em><em class="t2" style="padding-left:5px;color:' + helper.getItemColor(data.equip_list[j].quality) + '">' + data.equip_list[j].name + '</em></li>';
                }
            }
            if (data.mount_list != undefined) {
                for (var i = 0;i < data.mount_list.length;i ++) {
                    html += '<li><em class="t1"></em><span class="horse" style="background-position:' + helper.getHorsePostion('buff', data.mount_list[i].id) + ';"></span><em class="t2" style="color:' + helper.getItemColor(data.mount_list[i].quality) + '">' + data.mount_list[i].name + '</em></li>';
                }
            }
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
            if (data.gem != undefined) {
                html += '<li><em class="t1"></em><em class="t2 gempart">' + data.gem + '</em></li>';
            }
            if (data.reputation != undefined) {
                html += '<li><em class="t1"></em><em class="t2 reputation">' + data.reputation + '</em></li>';
            }
            
            obj.html(html);
            html = null;
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
                        log += (duimian + 'vs' + me);
                    } else {
                        log += (me + 'vs' + duimian);
                    }
                    log += '<em class="v2" vid="' + report[i].id + '" stage="' + report[i].stage + '">' + lang('champions_view') + '</em>';
                    log += '</li>';
                }
            }
            
            //区分部分
            if (side == 1) {
                dom = that.pvpDom.find('#pvp_left');
                this.pvpDom.find('#champions_pvpleft_list').html(log);
                this.leftScroller = new iScroll('champions_pvpleft_scroller', {
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
                
                this.pvpDom.find('#champions_pvpright_list').html(log);
                this.rightScroller = new iScroll('champions_pvpright_scroller', {
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
            if (entry == 1) {
                //是自己
                if (data.uid == player.info.uid) {
                    dom.find('.champion_pvp_btn').attr('type', 'sync').html(lang('champions_sync')).show();
                }
            } else {
                if (entry == 0) {//还未支持
                    if (data.uid != 0) {
                        dom.find('.champion_pvp_btn').attr({
                            'side' : side,
                            'serverid' : data.serverid,
                            'uid' : data.uid,
                            'type' : 'support',
                            'class': 'btn pop_btn_middle champion_pvp_btn'
                        }).html(lang('support')).show();
                    }
                } else {//已支持过
                    if ((side == 1 && entry == -1) || (side == 2 && entry == -2)) {
                        dom.find('.champion_pvp_btn').attr({
                            'class': 'btn pop_btn_middle_disable champion_pvp_btn',
                            'type': 'support'
                        }).html(lang('champions_had_support')).show();
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
            
            html += '<li class="t1">' + data.nickname + '</li>';
            html += '<li class="t2">' + lang('level') + ':<em>Lv.' + data.level + '</em></li>';
            html += '<li class="t3">' + lang('country') + ':<em>' + (data.countryid == 0 ? lang('warena_no_rank') : data.country) + '</em></li>';
            html += '<li class="t4">' + lang('champions_server') + ':<em>' + data.server + '</em></li>';
            
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
            if (CD['championssync'] != undefined) {//同步按钮特殊处理
                if(CD['championssync'].s - s > 0) {
                    xGame.updateCd('championssync', CD['championssync'].s - s);
                } else {
                    xGame.updateCd('championssync', 0);
                }
            } 
            
            if (CD['championsopen'] != undefined) {//战神殿开启
                if(CD['championsopen'].s - s > 0) {
                    xGame.updateCd('championsopen', CD['championsopen'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['championswait'] != undefined) {//战神殿等待
                if(CD['championswait'].s - s > 0) {
                    xGame.updateCd('championswait', CD['championswait'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['championsjoin'] != undefined) {
                if(CD['championsjoin'].s - s > 0) {
                    xGame.updateCd('championsjoin', CD['championsjoin'].s - s);
                } else {
                    that.reload();
                }
            } else if (CD['championspvp'] != undefined) {
                if(CD['championspvp'].s - s > 0) {
                    xGame.updateCd('championspvp', CD['championspvp'].s - s);
                } else {
                    that.reload();
                }
            }
        },
        
        //刷新方法
        reload: function() {
            championsWorld.clear();
            xGame.loadWorld('champions');
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#championsworld_back').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.enterDom) {
                this.enterDom.undelegate('#show_rule', 'touchend');
                this.enterDom.undelegate('#go_to_house', 'touchend');
                this.enterDom.undelegate('#rule_hide', 'touchend');
                this.enterDom.undelegate('#champions_join.pop_btn_middle', 'touchend');
                this.enterDom.find('#champions_history_list').undelegate('.t3', 'click');
                this.enterDom.undelegate('#champions_detail_back', 'touchend');
                this.enterDom.find('#champions_detail_list').undelegate('.t2', 'click');
                this.enterDom.find('#champions_enter_rewardbox').undelegate('span', 'click');
                this.enterDom.undelegate('#champions_my_history', 'click');
                this.enterDom.remove();
                this.enterDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.undelegate('.pop_btn_yellow', 'click');
                this.rewardDom.undelegate('.pop_btn_middle', 'click');
                this.rewardDom.undelegate('#champions_reward_hide', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.pvpDom) {
                this.pvpDom.undelegate('.champion_pvp_btn.pop_btn_middle', 'click');
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
            if (this.leftDom) {
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.remove();
                this.rightDom = null;
            }
            
            this.leftSupport = null;
            this.rightSupport = null;
            
            this.hDataDetail = null;
        
            this.uninstall(this.historyScroller);
            this.uninstall(this.detailScroller);
            this.uninstall(this.leftScroller);
            this.uninstall(this.rightScroller);
            this.uninstall(this.rewardScroller);
            
            xGame.updateCd('championsopen', 0);
            xGame.updateCd('championswait', 0);
            xGame.updateCd('championsjoin', 0);
            xGame.updateCd('championspvp', 0);
            xGame.updateCd('championssync', 0);
            
            this.unbindAll();
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            
        }
    };
})();