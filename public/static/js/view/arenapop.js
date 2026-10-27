/* 演武榜 */
(function() {
    arenaPopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom;
        this.rightDom;

        this.lefttimes = 0;
        this.rank; //当前排名
        this.recoverScroller1 = null;
        this.recoverScroller2 = null;
        this.infoPageLoaded = false;
        this.rewardData;
    };
    arenaPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1 || res.status == 2) {
                    xGame.updateCd('arena', 0);
                    that.fillData(res.list, res.log, res.reward, res.top_list, res.status, res.end_time);
                } else if (res.status == -1) {
                    xGame.toast.show(lang('lv_26'));
                    $('#worldloading').hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'arena',
                'm': 'index'
            },
            initRender, true);

            initRender = null;

        },

        fillData: function(list, log, reward, topList, status, endtimes) {
            var that = this;
            log = log || { cd: 0, rank: 1, act_arena_int: 0, times: 15 };
            reward = reward || {};
            topList = topList || {};
            list = list || {};

            that.e = xGame.loadPopTmpl('arena');

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                arenapop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.leftDom = that.e.find('#arena_left');
            that.rightDom = that.e.find('#arena_right');

            /*这块有问题，倒计时和status不符合*/
            if (status == 1) {
                xGame.updateCd('areanReward', endtimes);
                //that.leftDom.find('#arena_getReward').html('明日领取').addClass('pop_btn_middle_disable');
            } else {
                that.leftDom.find('#arena_getReward').html(lang('receive')).addClass('pop_btn_middle');
            }

            /*填充自己的数据,对应 log*/
            xGame.updateCd('arena', log.cd);

            if (log.rank > 100000) {
                that.leftDom.find('#arena_rank').html(lang('12')).addClass('notRank');
            } else {
                that.leftDom.find('#arena_rank').html(log.rank);
            }
            that.leftDom.find('#arena_history_rank').html(log.act_arena_int);
            that.rightDom.find('#arena_times').html(log.times + lang('times'));
            that.lefttimes = log.times;
            that.rank = parseInt(log.rank);
            $('#sider_arena').find('p').text(lang('rank', [that.rank]));

            that.fillLeftReward(reward);

            /*填充前三名对应 topList*/
            that.fillTopThree(topList);

            that.fillRightList(list);

            that.allBind();

            that.e.show();
            $('#worldloading').hide();

        },

        fillTopThree: function(topList) {
            var html = '';
            var num = 0;
            for (var i = 1; i <= 5; i++) {
                if (topList[i] != undefined) {
                    if (typeof topList[i].tree != 'undefined' && parseInt(topList[i].tree) > 1) {
                        html += '<li class="list' + i + '"><p class="top_userinfo tree' + topList[i].tree + '" uid="' + topList[i].uid + '">' + topList[i].username + '</p></li>';
                        num += 1;
                    } else {
                        html += '<li class="list' + i + '"><p class="top_userinfo" uid="' + topList[i].uid + '">' + topList[i].username + '</p></li>';
                    }
                }
            }
            this.leftDom.find('#arena_toplist').html(html);
            if (num > 0) {
                this.leftDom.find('#arena_toplist').attr('class', 'tree');
            }
        },

        fillLeftReward: function(reward) {
            var that = this;
            /*填充当前奖励 对应 reward*/
            if (reward.intnum != undefined) {
                that.leftDom.find('#arena_inter_num').html(reward.intnum);
            } else {
                that.leftDom.find('#arena_inter_num').hide();
            }
        },

        fillRightList: function(list) {
            var that = this;
            /*填充目前可以打的5名玩家 list*/
            var html1 = '';
            for (var i = 1; i <= 5; i++) {
                if (list[i] != undefined) {
                    html1 += '<li class="' + (list[i].gender == 1 ? 'arena_man': 'arena_woman') + '">';
                    html1 += '<div>';
                    html1 += '<p>' + lang('player') + ':<span class="name">' + list[i].username + '</span></p>';
                    html1 += '<p>' + lang('level') + ':<span>' + list[i].level + '</span></p>';
                    html1 += '<p>' + lang('ranks') + ':<span>' + list[i].rank + '</span></p>';
                    html1 += '</div>';
                    html1 += '<div class="small_mask" uid="' + list[i].uid + '"></div>';
                    /*不是自己并且排名在自己以上*/
                    if (list[i].uid != player.info.uid && parseInt(that.rank) > parseInt(list[i].rank)) {
                        html1 += '<button class="arena_atk_btn ' + (list[i].status == 0 ? 'pop_btn_middle': 'pop_btn_middle_disable') + ' btn" id="arena_atk_btn" uid="' + list[i].uid + '">' + lang('attack') + '</button>';
                    }
                    html1 += '</li>';
                }
            }
            
            that.rightDom.find('#arena_playerlist').html(html1);
	    html1 = '';
        },

        allBind: function() {
            var that = this;

            /*绑定领取奖励次数*/
            that.leftDom.undelegate('#arena_getReward.pop_btn_middle', 'touchend').delegate('#arena_getReward.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getReward();
            });

            /*绑定清除cd
            that.rightDom.undelegate('#area_clear_btn','touchend').delegate('#area_clear_btn','touchend',function(){
                if(xGame.checkLock(true)) {//不设置属性
                    return false;
                }
                that.clearFunc();
            });
            */

            /*绑定购买次数*/
            that.rightDom.undelegate('#area_buy_btn', 'touchend').delegate('#area_buy_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.buytimesConfirm();
            });

            /*绑定攻击按钮*/
            that.rightDom.find('#arena_playerlist').undelegate('.arena_atk_btn.pop_btn_middle', 'touchend').delegate('.arena_atk_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.atkFunc(parseInt($(this).attr('uid')));
            });

            /*绑定资料按钮*/
            that.rightDom.find('#arena_playerlist').undelegate('.small_mask', 'touchend').delegate('.small_mask', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });

            /*绑定前三名的资料*/
            that.leftDom.find('#arena_toplist').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).find('.top_userinfo').attr('uid')));
            });

            /* 奖励列表 */
            that.e.find('#area_rewardlist_btn').unbind('touchend').bind('touchend', function() {
                that.showRewardlistPage();
            });
            /* 积分商城按钮 */
            that.e.find('#arena_getInter').unbind('touchend').bind('touchend', function() {
                that.showInterlistPage();
            });
            /* 积分兑换按钮 */
            that.e.find('.inter_btn').unbind('touchend').bind('touchend', function() {
                var id = $(this).attr("id") + '_con';
                $(this).siblings('.inter_btn').removeClass('changeBtn');
                $(this).addClass('changeBtn');
                that.e.find("#" + id).siblings('.carousel').hide();
                that.e.find("#" + id).show();
                if(id == 'change_inter_con'){
                    that.recoverScroller1.refresh();  
                }else{           
                    that.recoverScroller2.refresh(); 
                }            
            });
            that.e.find('.carousel_content').undelegate('.box span', 'click').delegate('.box span', 'click', function (e) {  
                if ($(this).parent().hasClass('type12')) {
                    var index = $('#interpop_info_dom .box').index($(this).parent()); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(self.rewardData[index].info);
                } else if ($(this).parent().hasClass('type8')) {
                    var index = $('#interpop_info_dom .box').index($(this).parent()); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(self.rewardData[index].info);
                }else if ($(this).parent().hasClass('type26')) {
                    var index = $('#interpop_info_dom .box').index($(this).parent()); // 取得坐骑在所有奖励中的索引
                    miscpop.showMarchInfo(self.rewardData[index].info);
                }else if ($(this).parent().hasClass('type28')) {
                    var index = $('#interpop_info_dom .box').index($(this).parent()); // 取得宝石在所有奖励中的索引
                    miscpop.showGemInfo(self.rewardData[index].info);
                }else if ($(this).parent().hasClass('type27')) {
                    var index = $('#interpop_info_dom .box').index($(this).parent()); // 取得将魂在所有奖励中的索引
                    miscpop.showSoulInfo(self.rewardData[index].info);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('.interpop_carousel').undelegate('.changeBtn.pop_btn_small', 'click').delegate('.changeBtn.pop_btn_small', 'click', function (e) {
                var obj = $(this);
                var id = obj.attr("pid");
                var render = function(res) { 
                    switch(parseInt(res.status)) {
                        case 1:
                            that.showMessage(res.reward,res.act_arena_int);
                             if(obj.parent()){     //兑换武将之后，显示已招募。
                                obj.addClass('pop_btn_small_disable');
                                obj.parent().find('.isexist').addClass('show');
                            }
                          
                            for (var i = 0; i < res.list.length; i++) {
                                if(res.list[i].re_type == 12) {
                                    if(res.list[i].info.is_exist == 1) {
                                         that.e.find('.interpop_carousel').find('#item'+parseInt(res.list[i].id)).removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                                    }    
                                }else{
                                    var is_exch = res.list[i].is_exch;
                                    if (is_exch == 0) {
                                       that.e.find('.interpop_carousel').find('#item'+parseInt(res.list[i].id)).removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                                    } else {
                                       that.e.find('.interpop_carousel').find('#item'+parseInt(res.list[i].id)).removeClass('pop_btn_small_disable').addClass('pop_btn_small');
                                    }
                                }
                        }
                            break;
                        case 4:
                            xGame.toast.show(lang('arenapop_little_inter'));
                            break;
                        case -1:
                            xGame.toast.show(lang('arenapop_low_rank'));
                            break;
                        case -2:
                            xGame.toast.show(lang('arenapop_re_error'));
                            break;
                        case -3:
                            xGame.toast.show(lang('arenapop_datatable_error'));
                            break;
                        case  2:
                            xGame.toast.show(lang('arenapop_change_fast'));
                            break;
                        case  3:
                            xGame.toast.show(lang('arenapop_change_time'));
                            break;
                        default:
                            xGame.toast.show(res.msg);
                            break;
                    }
                };

                loadContents({
                    'c': 'arena',
                    'm': 'shop_exchange',
                    'id': id
                }, render);
                render = null;
                e.preventDefault();
                e.stopPropagation();
            });
            
        },

        atkFunc: function(uid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    xGame.updateCd('arena', parseInt(res.info.cd));
                    that.rightDom.find('#arena_times').html(res.info.times + lang('times'));
                    that.lefttimes = res.info.times;

                    that.leftDom.find('#arena_rank').html(res.info.rank);
                    $('#sider_arena').find('p').text(lang('rank', [res.info.rank]));
                    that.rank = res.info.rank;
                    that.fillRightList(res.info.list);

                    if (res.info.reward_status == 1) {
                        that.fillLeftReward(res.info.reward.reward);
                    }
                    that.fillTopThree(res.info.top_list);

                    player.taskaction(10);
                    miscpop.enterBattleFromArena(res, uid);

                    break;
                case - 1 : that.buytimesConfirm();
                    break;
                case - 2 :
                    //xGame.toast.show('挑战cd未清除！');
                    //that.clearFunc();
                    break;
                case - 3 : xGame.toast.show(lang('hava_ont_arena'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'arena',
                'm': 'action',
                't': uid
            },
            Func);

            Func = null;
        },
        //显示玩家获得的奖励
        showMessage: function (reward,act_arena_int) {
            if (typeof reward === 'string') {
                xGame.toast.show(reward);
                return;
            }
            reward = reward || {};
            var type = parseInt(reward.re_type);
            var id = reward.id;
            if(reward.type == 1){
                this.e.find("#item" + id).siblings('.time').html(lang('acttreasurepop_today_times',[reward.exch_num]));
            }else{
                this.e.find("#item" + id).siblings('.time').html(lang('acttreasurepop_times',[reward.exch_num]));
            }
           
            this.e.find("#act_arena_int").html(act_arena_int);
            this.e.find("#arena_history_rank").html(act_arena_int);
            if(reward.is_exch == 0){
               this.e.find("#item" + id).addClass('pop_btn_small_disable'); 
            }
            switch (type) {
                case 1:     // 元宝
                    xGame.toast.show(lang('acttreasurepop_msg1', [reward.re_value1]));
                    break;
                case 2:     // 银币
                    var silver = parseInt(player.info.silver) + parseInt(reward.re_value1);
                    player.updateinfo({'silver': silver});
                    xGame.toast.show(lang('acttreasurepop_msg2', [reward.re_value1]));
                    break;
                case 3:     // 黄宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [2, reward.re_value1, lang('yellow_jade')]));
                    break;
                case 4:     // 紫宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [3, reward.re_value1, lang('purple_jade')]));
                    break;
                case 11:    // 蓝宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [1, reward.re_value1, lang('blue_jade')]));
                    break;
                case 5:     // 原魂
                    xGame.toast.show(lang('acttreasurepop_msg4', [reward.re_value1]));
                    break;
                case 6:     // 玉石
                    xGame.toast.show(lang('acttreasurepop_msg5', [reward.re_value1]));
                    break;
                case 7:     // 声望
                    xGame.toast.show(lang('acttreasurepop_msg6', [reward.re_value1]));
                    break;
                case 8:     // 装备
                    xGame.toast.show(lang('acttreasurepop_msg7', [helper.getItemColor(reward.info.quality), reward.info.name]));
                    break;
                case 9:     // 宝石碎片
                    xGame.toast.show(lang('acttreasurepop_msg8', [reward.re_value1]));
                    break;
                case 10:    // 突飞卡
                    xGame.toast.show(lang('acttreasurepop_msg9', [reward.re_value1]));
                    break;
                case 12:    // 武将
                    miscpop.newhero(reward.info);
                    break;
                case 13:     // 军令
                    xGame.toast.show(lang('acttreasurepop_msg12', [reward.re_value1]));
                    break;
                case 14:     // 精炼石
                    xGame.toast.show(lang('acttreasurepop_msg14', [reward.re_value1]));
                    break;
                case 15:     // 五彩石
                    xGame.toast.show(lang('acttreasurepop_msg15', [reward.re_value1]));
                    break;
                case 16:     // 上古血玉
                    xGame.toast.show(lang('acttreasurepop_msg16', [reward.re_value1]));
                    break;
                case 17:    //武器精华（白）
                case 18:    //防具精华（白）
                case 19:    //饰品精华（白）
                case 20:    //武器精华（绿）
                case 21:    //防具精华（绿）
                case 22:    //饰品精华（绿）
                case 23:    //武器精华（蓝）
                case 24:    //防具精华（蓝）
                case 25:    //饰品精华（蓝）
                    xGame.toast.show(lang('acttreasurepop_msg18', [reward.info.name, xGame.essence(reward.info.id), reward.info.re_value1]));
                    break;
                case 26:   //坐骑
                    xGame.toast.show(lang('acttreasurepop_msg26', [helper.getItemColor(reward.info.quality),reward.info.name]));
                    break;
                case 27:    //原魂
                    xGame.toast.show(lang('acttreasurepop_soul',[helper.getItemColor(reward.info.quality),reward.info.name]));
                    break;
                case 28:    //宝石
                    xGame.toast.show(lang('acttreasurepop_msg28', [reward.info.gem_level,helper.getItemColor(reward.info.gem_quality),reward.info.gem_name]));
                    break;
                default :
                    xGame.toast.show(lang('acttreasurepop_err_msg6') || (reward && reward.info ? ('恭喜获得：' + reward.info.name) : '兑换成功！'));
                    break;
            }
        },

        getReward: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    player.updateinfo({
                        "silver": parseInt(res.all_silver)
                    });
                    xGame.toast.show(lang('reward_success'));

                    /*更新dom*/
                    xGame.updateCd('areanReward', res.end_time);
                    //that.leftDom.find('#arena_getReward').html('明日领取').addClass('pop_btn_middle_disable');
                    that.leftDom.find('#arena_history_rank').html(res.act_arena_int);  //更新当前积分
                    that.leftDom.find('#arena_inter_num').html(res.reward.intnum);  //更新当前排名积分
                    that.fillLeftReward(res.new_reward);

                } else if (res.status == -1) {
                    xGame.toast.show(lang('no_reward'));
                } else {
                    xGame.toast.show(lang('have_not_begin'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'arena',
                'm': 'get_reward'
            },
            Func);

            Func = null;
        },

        /* 购买次数 价格确认框 */
        buytimesConfirm: function() {
            var that = this;

            var priceRender = function(res) {
                if (res.status == 1 && res.price > 0) {
                    xGame.confirm.show(lang('notice'), lang('buy_times_ask', [res.price, that.lefttimes]), 2,
                    function() {
                        that.buytimesProcess();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
                } else {
                    xGame.toast.show(lang('can_not_buy_times_yet'));
                    return false;
                }
            };
            loadContents({
                'c': 'arena',
                'm': 'buy_pk_num',
                't': 0
            },
            priceRender);

            priceRender = null;
        },

        /* 开始购买次数 */
        buytimesProcess: function() {
            var that = this;

            var buyRender = function(res) {
                if (res.status == 1) {
                    player.updateinfo({
                        "gold": res.gold
                    });
                    that.rightDom.find('#arena_times').html(res.times + lang('times'));
                    that.lefttimes = res.times;
                    $('#buy_arenatimes_price').html(res.price);
                    $('#buy_arenatimes').html(res.times);
                    xGame.toast.show('购买成功！演武挑战次数 +1');
                } else if (res.status == -1) {
                    xGame.goPay(lang('gold_not_enough_for_buying'));
                } else {
                    xGame.toast.show(lang('buy_fail_try_later'));
                }

            };
            loadContents({
                'c': 'arena',
                'm': 'buy_pk_num',
                't': 1
            },
            buyRender);

            buyRender = null;
        },

        showRewardlistPage: function() {
            var that = this;
            var Func = function(res) {
                if(res.status == 1) {
                    that.infoPageLoaded = true;
                    var infoPage = that.e.find('#arenapop_info_dom');
                    infoPage.find('#arenapop_info_hide').unbind('touchend').bind('touchend', function(e) {
                    that.e.find('#arenapop_info_dom').hide();
                    e.preventDefault();
                });
                var html = '';
                for(var i = 0; i < res.list.length; i++) {
                    html += '<li class="areanpop_rewardlist_item">';
                    html += '<p>'+res.list[i].intnum+'</p>'
                    html += '</li>';
                }
                    infoPage.find('#arenapop_rewardlist_wrap').html(html);
                    infoPage.show();
                } else{
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'arena',
                'm': 'get_reward_list',
            },
            Func); 
            Func = null;
        },
        /* 显示积分商城 */
        showInterlistPage: function() {
            var that = this;
                var Func = function(res) {
                    if(res.status == 1) {
                        
                        this.rewardData = res.list;
                        that.infoPageLoaded = true;

                        var infoPage = that.e.find('#interpop_info_dom');

                        infoPage.find('#interpop_inter_hidebtn').unbind('touchend').bind('touchend', function(e) {
                            that.e.find('#interpop_info_dom').hide();
                            that.e.find("#limit_comm").removeClass("changeBtn");
                            that.e.find("#limit_comm_con").hide();
                            that.e.find("#change_inter").addClass("changeBtn");
                            that.e.find("#change_inter_con").show();
                            e.preventDefault();
                        });
                        that.buildInterList(res.list,res.act_arena_int);
                        that.e.find('#interpop_info_dom').show();
                    } else if(res.status == -1){
                        xGame.toast.show(lang('arenapop_ranklow_error'));
                    } else if(res.status == -2){
                        xGame.toast.show(lang('arenapop_datatable_error'));
                    }else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                };
                loadContents({
                    'c': 'arena',
                    'm': 'shop_index',
                },
                Func); 

                Func = null;
        },
        buildInterList: function (list,act_arena_int) {
            act_arena_int = (act_arena_int != null) ? act_arena_int : 0;
            this.e.find('#act_arena_int').html(act_arena_int);
            list = $.isArray(list) ? list : helper.array_values(list || []);
            var html1 = '<li>';
            var html2 = '<li>'; 
            var n = 0;
            var m = 0;

            for (var i = 0, len = list.length; i < len; i++) {

                var item = list[i];
                
                // 下一页
                if(item.type == 1){
                    if (n > 0 && n % 6 == 0) {
                        html1 += '</li><li>';
                    }

                        html1 += '<div class="box type'+ item.re_type +'">';
                    if (parseInt(item.re_type) == 12) {//武将
                        html1 += '<span class="img" style="background-image:url(' + xGame.avatar(item.info.image) + ')"></span>';
                    }else {
                        html1 += '<span class="img"></span>'; 
                    }
                        html1 += '<em class="re_value1">x' + item.re_value1 + '</em>';
                        html1 += '<em class="num">' + item.use_int + '</em>';
                        html1 += '<button class="changeBtn btn ' + (item.is_exch == 1 ? 'pop_btn_small' : 'pop_btn_small_disable') + '" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                        html1 += '<em class="time">' + lang('acttreasurepop_today_times',[item.exch_num]) + '</em>';
                        html1 += '</div>';
                        n++;
                }else{
                   if (m > 0 && m % 6 == 0) {
                        html2 += '</li><li>';
                    }

                        html2 += '<div class="box type'+ item.re_type +'">';
                    if (parseInt(item.re_type) == 12) {//武将
                        html2 += '<span class="img" style="background-image:url(' + xGame.avatar(item.info.image) + ')"></span>';
                    } else if (parseInt(item.re_type) == 8) { //装备
                        html2 += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.image) +');"></span>';
                    } 
                    //西游暂无装备精炼材料
                    /*else if (parseInt(item.re_type) >= 17 && parseInt(item.type) <= 25) {//精华
                        html2 += '<span class="img" style="background-image:url('+ xGame.essence(item.info.id) +');"><em class="name">'+ item.info.name +'</em></span>';
                    } */
                    else if(parseInt(item.re_type) == 26) { //坐骑
                        html2 += '<span class="img" style="background-position:' + helper.getHorsePostion('small', item.info.id) + ';"></span>';

                    } else if(parseInt(item.re_type) == 28){
                        var level =  item.info.gem_level / 4  ; 
                        if( level <= 1 ){
                            html2 += '<span class="img" style="background-image:url('+ xGame.gem(item.info.gem_type, 1) +')"></span>';
                        }else if (level >1 && level <= 2 ){
                            html2 += '<span class="img" style="background-image:url('+ xGame.gem(item.info.gem_type, 2) +')"></span>';
                        }else if (level >2 && level <= 3 ) {
                            html2 += '<span class="img" style="background-image:url('+ xGame.gem(item.info.gem_type, 3) +')"></span>';
                        }else if (level >3 && level <= 4 ) {
                            html2 += '<span class="img" style="background-image:url('+ xGame.gem(item.info.gem_type, 4) +')"></span>';
                        }else{
                            html2 += '<span class="img" style="background-image:url('+ xGame.gem(item.info.gem_type, 5) +')"></span>';
                        }
                    } else if (parseInt(item.re_type) == 27) {
                        html2 += '<span class="img" style="background-size: 60px;background-image:url(static/img/pop/soul_' + item.info.image +'.png)"></span>';
                            
                    } else {
                        html2 += '<span class="img"></span>';     
                    } if(parseInt(item.re_type) == 8){ 
                        html2 += '<em class="re_value1" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.info.name + '</em>';
                    } else if(parseInt(item.re_type) == 26 || parseInt(item.re_type) == 12 || parseInt(item.re_type) == 27 ){
                        html2 += '<em class="re_value1" style="color:'+ helper.getItemColor(item.info.quality)+'">' + item.info.name + '</em>';
                    } else if(parseInt(item.re_type) == 28) {
                        html2 += '<em class="re_value1" style="color:'+ helper.getItemColor(item.info.gem_quality)+'">' + item.info.gem_name + '</em>';
                    } else{
                        html2 += '<em class="re_value1">x' + item.re_value1 + '</em>';
                    }
                    html2 += '<em class="num">' + item.use_int + '</em>';
                    if(parseInt(item.re_type) == 12) {
                        html2 += '<span class="isexist' + (item.info.is_exist == 1 ? ' show' : '') + '"></span>';
                    }
                    if(parseInt(item.re_type) == 12){

                        if(item.info.is_exist == 1 || item.is_exch == 0) {

                            html2 += '<button class="changeBtn btn pop_btn_small_disable" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                        }else{
                            html2 += '<button class="changeBtn btn pop_btn_small" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                        }
                    }else{
                    html2 += '<button class="changeBtn btn ' + (item.is_exch == 1 ? 'pop_btn_small' : 'pop_btn_small_disable') + '" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                    }
                    html2 += '<em class="time">' + lang('acttreasurepop_times', [item.exch_num]) + '</em>';
                    html2 += '</div>';
                    m++;
                }
            }
            html1 += '</li>';
            html2 += '</li>';
            this.e.find('#change_inter_con ul').html(html1);
            this.e.find('#limit_comm_con ul' ).html(html2);
            this.recoverScroller1 = xGame.carousel('change_inter_con', 649, Math.ceil(n/6));
            this.recoverScroller2 = xGame.carousel('limit_comm_con', 649, Math.ceil(m/6));
        },
        /*
        clearFunc : function(isbuy) {
            var that = this;

            var Price = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    xGame.confirm.show('提示', '确定要花费' + res.price + '元宝清除CD吗？', 2,
                        function() {
                            that.clearFunc(true);
                            xGame.confirm.hide();
                        },
                        function() {
                            xGame.confirm.hide();
                        }
                    );
                } else {
                    xGame.toast.show('未知错误！');
                }
            };
            var Clear = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    xGame.updateCd('arena',0);
                    player.updateinfo({"gold":res.gold});
                    xGame.toast.show('清除成功！');
                } else if(res.status == -1) {
                    xGame.toast.show('不需要清除CD！');
                } else if(res.status == -2) {
                    xGame.goPay('您的元宝不足，无法清除CD！');//由于当前也是confirm，所以会弹不出来，要注意
                } else {
                    xGame.toast.show('未知错误！');
                }
            };
            if(isbuy == undefined) {
                loadContents({'c':'arena', 'm':'clear_cd','t':0}, Price);
            }else{
                loadContents({'c':'arena', 'm':'clear_cd','t':1}, Clear);
            }
        },
        */

        unbindAll: function() {
	    if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#area_rewardlist_btn').unbind('touchend');
                this.e.find('#arenapop_info_dom').find('#arenapop_info_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
	    }
	    if (this.leftDom) {
                this.leftDom.undelegate('#arena_getReward.pop_btn_middle', 'touchend');
                this.leftDom.find('#arena_toplist').undelegate('li', 'touchend');
                this.leftDom.remove();
                this.leftDom = null;
	    }
	    if (this.rightDom) {
                this.rightDom.undelegate('#area_buy_btn', 'touchend');
                this.rightDom.find('#arena_playerlist').undelegate('#arena_atk_btn.pop_btn_middle', 'touchend');
                this.rightDom.find('#arena_playerlist').undelegate('.small_mask', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
	    }
        },

        hide: function() {

            xGame.unlock();

            xGame.updateCd('arena', 0);
            xGame.updateCd('areanReward', 0);
            this.isshow = false;

            this.unbindAll();
            this.rank = null;

            this.infoPageLoaded = false;
            this.recoverScroller1 = null;
            this.recoverScroller2 = null;
            player.reloadInfo();
        },
    };
})();