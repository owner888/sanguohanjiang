// 踏青寻宝
(function() {
    acttreasurePopClass = function () {
        this.e = null;
        this.isshow = false;
        this.rewardListDom = null;  // 转盘的奖励列表
        this.freeDom = null;        // 本次免费
        this.costDom = null;        // 寻宝花费
        this.spaceX = 129;          // 奖励项的水平间距
        this.spaceY = 97;           // 奖励项的垂直间距
        this.prizeDom = null;       // 神秘大奖
        this.prizeRuleDom = null;   // 神秘大奖界面的查看规则
        this.getRewardDom = null;   // 今日得宝
        this.roleFlagDom = null;    // 代表玩家的旗子
        this.moveTimer = null;      // 移动旗子的计时器
        this.moveItemTimer = null;  // 移动奖品的计时器
        this.exchangeTimer = null;  // 兑换大奖的计时器
        this.getRewardBtnDom = null;    // 今日得宝按钮
        this.nowPos = 0;    // 玩家当前的位置
        this.huntData = null;       // 点寻宝按钮获得的数据
        this.rewardListData = null; // 奖品列表数据
        this.getRewardListData = null;      // 今日得宝的数据
        this.prizeListData = null;          // 神秘大奖的数据
        this.avatarDom = null;      // 将领信息界面
        this.itemDom = null;        // 装备信息界面
        this.getRewardScroll = null;
        this.viewRewardScroll = null;
        this.prizeScroll = null;
        this.autoHunt = false;       // 是否自动探宝
        this.huntTimes = 0;          // 自动探宝的次数
        this.huntTotalTimes = 0;     // 自动探宝的总次数
        this.prizeRewardIndex = 0;      // 玩家领取的神秘大奖的索引
        this.rankDom = null;            // 排行榜
        this.rankScroller = null;
        this.achievementDom = null;     // 集字成就
        this.achievementScroller = null;
        this.achieveList = null;        // 集字成就的奖励数据
        this.achieveTimer = null;
        this.huntHundredCost = 0;       // 探百次的花费
        this.huntTimer = null;          // 探百次的计时器
    };

    acttreasurePopClass.prototype = {

        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
            }

            var render = function (res) {
                
                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            }

            loadContents({
                'c': 'act_treasure',
                'm': 'index'
            }, render);
            render = null;
        },

        initPage: function (res) {
            
            var that = this;
            that.e = xGame.loadPopTmpl('acttreasure');
            that.nowPos = res.site;
            that.rewardListData = res.list;
            that.huntHundredCost = res.sub_h_gold;

            // 关闭按钮
            that.e.find('#acttreasurepop_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            that.rewardListDom = that.e.find('#reward_list');  // 转盘的奖励列表
            that.freeDom = that.e.find('#free');                    // 本次免费
            that.costDom = that.e.find('#cost');                    // 寻宝花费
            that.prizeDom = that.e.find('#acttreasurepop_prize');      // 神秘大奖
            that.prizeRuleDom = that.prizeDom.find('#prize_rule');     // 神秘大奖界面的查看规则
            that.getRewardDom = that.e.find('#acttreasurepop_getreward');      // 今日得宝
            that.getRewardBtnDom = that.e.find('#getreward_btn');    // 今日得宝按钮
            that.avatarDom = that.e.find('#acttreasurepop_avatar_dom');     // 将领信息界面
            that.itemDom = that.e.find('#acttreasurepop_item_dom');         // 装备信息界面
            that.rankDom = that.e.find('#rank_dom');    // 寻宝排行榜
            that.achievementDom = that.e.find('#achievement_dom');   // 集字成就

            // 是否免费
            that.judgeFree(res.free);
            that.costDom.children('#cost_gold').html('x' + res.sub_gold);

            // 活动时间
            that.e.find('#time_limit').html(res.time);

            that.bindAll();
            that.buildRewardList(res.list);

            that.roleFlagDom = that.rewardListDom.find('#role_flag');          // 代表玩家的旗子
            that.moveFlag(0, that.nowPos, true, false);     // 移动旗子

            that.showPopo(res.hzexch_popo, res.hzach_popo);

            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();

        },

        bindAll: function () {

            var that = this;
            
            // 神秘大奖按钮
            that.e.find('#prize_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getPrizeData();
                e.preventDefault();
            });

            // 神秘大奖的关闭按钮
            that.prizeDom.find('#prize_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.prizeScroll != null) {
                    that.prizeScroll.destroy();
                    that.prizeScroll = null;
                }

                if (that.exchangeTimer != null) {
                    clearTimeout(that.exchangeTimer);
                    that.exchangeTimer = null;
                }

                that.prizeRewardIndex = 0;
                that.prizeDom.find('.reward_box').remove();
                that.prizeDom.hide();
                e.preventDefault();
            });

            // 今日得宝按钮
            that.e.find('#getreward_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getGetRewardData();
                e.preventDefault();
            });

            // 今日得宝的关闭按钮
            that.getRewardDom.find('#getreward_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.getRewardScroll != null) {
                    that.getRewardScroll.destroy();
                    that.getRewardScroll = null;
                }

                that.getRewardDom.hide();
                e.preventDefault();
            });

            // 寻宝按钮
            that.e.find('#hunt_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.hunt();
                e.preventDefault();
            });

            // 探十次按钮
            that.e.find('#tenhunt_btn').unbind('touchend').bind('touchend', function (e) {

                if (xGame.checkLock()) {
                    return false;
                }

                that.huntLoop(10);
                e.preventDefault();
            });

            // 探百次按钮
            that.e.find('#hundredhunt_btn').unbind('touchend').bind('touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('acttreasurepop_hunt_title'), lang('acttreasurepop_hunt_text', [that.huntHundredCost]), 2, function () {
                    that.huntHundred();
                    xGame.confirm.hide();
                }, function () {
                    xGame.confirm.hide();
                });
                e.preventDefault();
            });

            // 去掉奖励框
            that.e.unbind('touchend').bind('touchend', function (e) {

                if (that.e != null) {
                    var rewardBox = that.e.children('.reward_box');

                    if (rewardBox.length > 0) {
                        rewardBox.remove();
                    }
                }

                e.preventDefault();
            });

            // 转盘中查看将领和装备
            that.rewardListDom.undelegate('.item', 'touchend').delegate('.item', 'touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }
                
                var type = parseInt($(this).attr('type'));
                var pos = parseInt($(this).attr('pos'));

                if (type == 13) {   // 将领
                    miscpop.showHeroInfo(that.rewardListData[pos].general);
                } else if (type == 8) { // 装备
                    miscpop.showItemInfo(that.rewardListData[pos].equip);
                }

                e.preventDefault();
            });

            // 今日得宝界面中查看将领和装备
            that.getRewardDom.undelegate('.item', 'click').delegate('.item', 'click', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var type = parseInt($(this).attr('type'));
                var pos = parseInt($(this).attr('pos'));

                if (type == 13) {   // 将领
                    miscpop.showHeroInfo(that.getRewardListData[pos].general);
                } else if (type == 8) { // 装备
                    miscpop.showItemInfo(that.getRewardListData[pos].equip);
                }

                e.preventDefault();
            });

            // 神秘大奖界面的查看规则按钮
            that.prizeDom.find('#view_rule').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.prizeRuleDom.show();
                e.preventDefault();
            });

            // 神秘大奖界面的查看规则关闭按钮
            that.prizeDom.find('#prize_rule_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.prizeRuleDom.hide();
                e.preventDefault();
            });

            // 神秘大奖界面的兑换按钮
            that.prizeDom.find('#prize_reward_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                var index = $(this).attr('pid');
                that.prizeRewardIndex = index;
                that.exchangePrize(index);
                e.preventDefault();
            });

            // 排行榜按钮
            that.e.find('#rank_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getRankData();
                e.preventDefault();
            });

            // 排行榜的关闭按钮
            that.rankDom.find('#rank_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.rankDom.hide();
                e.preventDefault();
            });

            // 排行榜的玩家名
            that.rankDom.find('#rank_list').undelegate('.player_name', 'touchend').delegate('.player_name', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var uid = parseInt($(this).attr('uid'));
                xGame.showpop('info', uid);
                e.preventDefault();
            });

            // 排行榜的领取按钮
            that.rankDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getRankReward();
                e.preventDefault();
            });

            // 集字成就按钮
            that.e.find('#achievement_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getAchievementData();
                e.preventDefault();
            });

            // 集字成就的关闭按钮
            that.achievementDom.find('#achievement_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                }
                that.achieveTimer = null;

                that.achievementDom.find('.reward_box').remove();
                that.achievementDom.hide();
                e.preventDefault();
            });

            // 集字成就的领取按钮
            that.achievementDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var id = parseInt($(this).attr('aid'));
                that.getAchievementReward(id);
                e.preventDefault();
            });
        },

        // 判断是否免费寻宝
        judgeFree: function (free) {
            
            if (parseInt(free) == 0) {
                this.freeDom.hide();
                this.costDom.show();
            } else {
                this.freeDom.show();
                this.costDom.hide();
            }
        },

        // 是否显示感叹号
        showPopo: function (prize_popo, achieve_popo) {

            var that = this;
            
            if (prize_popo != null) {
                if (parseInt(prize_popo) == 1) {
                    that.e.find('#prize_btn').children('.popo').show();
                } else {
                    that.e.find('#prize_btn').children('.popo').hide();
                }
            }

            if (achieve_popo != null) {
                if (parseInt(achieve_popo) == 1) {
                    that.e.find('#achievement_btn').children('.popo').show();
                } else {
                    that.e.find('#achievement_btn').children('.popo').hide();
                }
            }
        },

        // 判断寻宝图标上是否显示感叹号
        showOutPopo: function () {
            
            if (this.e.find('.popo:visible').length > 0) {
                $('#header #acttreasure_btn .popo').show();
            } else {
                $('#header #acttreasure_btn .popo').hide();
            }
        },

        buildRewardList: function (list) {

            var html = '';
            var item = null;
            var type = 0;

            for (var i = 0; i < 20; i++) {

                if (i > 0) {
                    item = list[i];
                }

                if (i <= 6) {

                    if (i == 0) {
                        html += '<li class="item origin" pos="'+ i +'" style="left:'+ (this.spaceX * i) +'px;top:0;">';
                    } else {
                        html += '<li class="item type'+ item.type +' ptype'+ item.p_type +'" pos="'+ i +'" type="'+ item.type +'" style="left:'+ (this.spaceX * i) +'px;top:0;">';
                    }
                } else if (i <= 10) {
                    html += '<li class="item type'+ item.type +' ptype'+ item.p_type +'" pos="'+ i +'" type="'+ item.type +'" style="left:'+ (this.spaceX * 6) +'px;top:'+ (this.spaceY * (i - 6)) +'px;">';
                } else if (i <= 16) {
                    html += '<li class="item type'+ item.type +' ptype'+ item.p_type +'" pos="'+ i +'" type="'+ item.type +'" style="left:'+ (this.spaceX * (16 - i)) +'px;top:'+ (this.spaceY * (10 - 6)) +'px;">';
                } else {
                    html += '<li class="item type'+ item.type +' ptype'+ item.p_type +'" pos="'+ i +'" type="'+ item.type +'" style="left:0px;top:'+ (this.spaceY * (20 - i)) +'px;">';
                }
                
                if (i == 0) {
                    html += '<span class="pic"></span>';
                } else {

                    type = parseInt(item.type);

                    if (type == 13) {   // 武将
                        html += '<span class="pic" style="background-image:url('+ xGame.avatar(item.general.image) +');"><em class="name">'+ item.general.name +'</em></span>';
                    } else if (type == 11) {    // 坐骑
                        html += '<span class="pic" style="background-position:'+ helper.getHorsePostion('small', item.mount.id) +';"><em class="name" style="color:'+ helper.getItemColor(item.mount.quality) +';">'+ item.mount.name +'</em></span>';
                    } else if (type == 8) {     // 装备
                        html += '<span class="pic" style="background-image:url('+ xGame.itemurl(item.equip.image) +');"><em class="name" style="color:'+ helper.getItemColor(item.equip.quality) +';">'+ item.equip.name +'</em></span>';
                    } else if (type == 19) {    // 汉字
                        html += '<span class="pic text'+ item.id +'"><em class="name">'+ item.name +'</em></span>';
                    } else if (type >= 21 && type <= 29) {  // 精华
                        html += '<span class="essence pic" style="background-image:url('+ xGame.essence(item.info.id) +');color:'+ helper.getItemColor(item.info.quality) +'"><em class="name">'+ item.name +'</em></span>';
                    } else {
                        html += '<span class="pic"><em class="name">'+ item.name +'</em></span>';
                    }

                    html += '<span class="animate"></span>';
                }

                html += '<span class="arrow"></span>';
                html += '</li>';
            }

            html += '<li class="item flag" id="role_flag"><span class="rotate"></span><span class="pic"></span></li>';

            this.rewardListDom.html(html);
        },

        // 寻宝
        hunt: function () {
            var that = this;

            var render = function (res) {
                
                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        xGame.islock = true;
                        that.huntData = res;
                        that.judgeFree(0);  // 更新玩家钱币

                        player.updateinfo({
                            'gold': res.all_gold,
                            'silver': res.all_silver
                        });

                        that.showPopo(res.hzexch_popo, res.hzach_popo);
                        that.rewardListDom.children('.item').eq(res.now_site).children('.arrow').show();    // 显示箭头
                        
                        if (that.autoHunt) {    // 是否自动探宝
                            that.moveFlag(that.nowPos, res.now_site, false, true);
                        } else {
                            that.moveFlag(that.nowPos, res.now_site, false, false);
                        }
                        break;
                    case 4:
                        that.autoHunt = false;
                        that.huntTimes = 0;
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    default :
                        that.autoHunt = false;
                        that.huntTimes = 0;
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'treasure'
            }, render);

            render = null;
        },

        // 探十次
        huntLoop: function (totalTimes) {
            var that = this;
            that.autoHunt = true;
            that.huntTotalTimes = totalTimes;
            that.hunt();
            that.huntTimes = 1;
        },

        /* 
         * 名称：移动旗子
         * 参数：nextPos：要移动到的位置
                 isInit：是否是刚进界面，如果不是，则使用动画移动
                 speedup：是否加速
         */
        moveFlag: function (nowPos, nextPos, isInit, speedup) {
            
            var that = this;
            var speed = 20;

            if (speedup) {
                speed *= 2;
            }

            var ulOffset = that.rewardListDom.offset();
            var nowOffset = that.roleFlagDom.offset();
            var nowLeft = nowOffset.left - ulOffset.left;
            var nowTop = nowOffset.top - ulOffset.top;
            var destinationDom = that.rewardListDom.children('.item').eq(nextPos);
            var nextOffset = destinationDom.offset();
            var nextLeft = nextOffset.left - ulOffset.left;
            var nextTop = nextOffset.top - ulOffset.top;

            if (nowPos == 5 && (nextPos - nowPos) == 6) {   // 特殊处理
                var transferDom = that.rewardListDom.children('.item').eq(nowPos + 1);
                var transferLeft = transferDom.offset().left - ulOffset.left;
                transferDom = null;
            }

            function move() {
                
                that.moveTimer = setTimeout(move, 33);
                var isMoveReward = false;

                if (nowPos < 6) {  // 上侧的奖品

                    if (nowPos != 5 || (nextPos - nowPos) != 6) {

                        if (nowLeft < nextLeft) {   // 先移动水平方向
                            nowLeft += speed;

                            if (nowLeft > nextLeft) {
                                nowLeft = nextLeft;
                            }
                        } else {                    // 再移动垂直方向
                            nowTop += speed;

                            if (nowTop > nextTop) {
                                
                                nowTop = nextTop;

                                if (that.moveTimer != null) {
                                    clearTimeout(that.moveTimer);
                                    that.moveTimer = null;
                                }
                                
                                isMoveReward = true;
                            }
                        }
                    } else {    // 特殊处理

                        if (nowLeft < transferLeft) {
                            nowLeft += speed;

                            if (nowLeft > transferLeft) {
                                nowLeft = transferLeft;
                                nowPos = 6;
                            }
                        }
                    }

                } else if (nowPos < 10) {  // 右侧的奖品
                    
                    if (nowTop < nextTop) {     // 先移动垂直方向
                        nowTop += speed;

                        if (nowTop > nextTop) {
                            nowTop = nextTop;
                        }
                    } else {                    // 再移动水平方向
                        nowLeft -= speed;

                        if (nowLeft < nextLeft) {
                            nowLeft = nextLeft;

                            if (that.moveTimer != null) {
                                clearTimeout(that.moveTimer);
                                that.moveTimer = null;
                            }
                            
                            isMoveReward = true;
                        }
                    }
                } else if (nowPos < 16) {  // 下侧的奖品
                    
                    if (nowLeft > nextLeft) {   // 先移动水平方向
                        nowLeft -= speed;

                        if (nowLeft < nextLeft) {
                            nowLeft = nextLeft;
                        }
                    } else {                    // 再移动垂直方向
                        nowTop -= speed;

                        if (nowTop < nextTop) {
                            
                            nowTop = nextTop;

                            if (that.moveTimer != null) {
                                clearTimeout(that.moveTimer);
                                that.moveTimer = null;
                            }
                            
                            isMoveReward = true;
                        }
                    }
                } else {    // 左侧的奖品

                    if (nowTop > nextTop) {     // 先移动垂直方向
                        nowTop -= speed;

                        if (nowTop < nextTop) {
                            nowTop = nextTop;
                        }
                    } else {                    // 再移动水平方向
                        nowLeft += speed;

                        if (nowLeft > nextLeft) {
                            nowLeft = nextLeft;

                            if (that.moveTimer != null) {
                                clearTimeout(that.moveTimer);
                                that.moveTimer = null;
                            }

                            isMoveReward = true;
                        }
                    }
                }

                that.roleFlagDom.css({'-webkit-transform': 'translate3d('+ nowLeft +'px, '+ nowTop +'px, 0)'});

                if (isMoveReward) {
                    that.moveReward(destinationDom.children('.pic'), speedup);
                    destinationDom = null;
                }
            }

            if (isInit) {
                that.roleFlagDom.css({'-webkit-transform': 'translate3d('+ nextLeft +'px, '+ nextTop +'px, 0)'});
            } else {
                move();
            }

        },

        // 移动奖品至今日得宝中
        // dom：要移动的奖品
        moveReward: function (dom, speedup) {
            
            var that = this;
            that.nowPos = that.huntData.now_site;
            var nowItemDom = dom.parent();
            var type = parseInt(nowItemDom.attr('type'));
            nowItemDom.children('.arrow').hide();

            // 如果是起点
            if (that.nowPos == 0) {
                that.judgeFree(1);
                var itemDom = that.rewardListDom.find('.item').not('.origin').not('.flag');
                itemDom.removeClass('ptype3').addClass('bomb');

                // 监听动画结束事件
                itemDom.children('.animate').first().unbind('webkitAnimationEnd').bind('webkitAnimationEnd', function (e) {

                    $(this).unbind();
                    itemDom.removeClass('bomb');
                    that.rewardListData = that.huntData.refresh_list;
                    that.buildRewardList(that.rewardListData);
                    that.roleFlagDom = that.rewardListDom.find('#role_flag');

                    // 如果是自动探宝
                    if (that.autoHunt == true) {

                        if (that.huntTimes < that.huntTotalTimes) {
                            that.hunt();
                            that.huntTimes ++;
                        } else {
                            that.autoHunt = false;
                            that.huntTimes = 0;
                            xGame.unlock();
                        }
                    } else {
                        xGame.unlock();
                    }
                });

                return false;
            }

            // 是否是双倍格
            if (type == 20) {

                xGame.toast.show(lang('acttreasurepop_msg19'));

                // 如果是自动探宝
                if (that.autoHunt == true) {

                    if (that.huntTimes < that.huntTotalTimes) {
                        that.hunt();
                        that.huntTimes ++;
                    } else {
                        that.autoHunt = false;
                        that.huntTimes = 0;
                        xGame.unlock();
                    }
                } else {
                    xGame.unlock();
                }
                
                return false;
            }

            // 问号格
            if (type == 18) {

                nowItemDom.removeClass('type18').addClass('type' + that.huntData.info.type);

                switch (parseInt(that.huntData.info.type)) {
                    case 13:    // 武将
                        dom.css('background-image', 'url('+ xGame.avatar(that.huntData.info.image) +')');
                        break;
                    case 11:    // 坐骑
                        dom.css('background-position', helper.getHorsePostion('small', that.huntData.info.id));
                        break;
                    case 8:     // 装备
                        dom.css('background-image', 'url('+ xGame.itemurl(that.huntData.info.image) +')');
                        break;
                    case 19:    // 汉字
                        dom.addClass('text' + that.huntData.info.hanzi_id);
                        break;
                    // 精华
                    case 21:
                    case 22:
                    case 23:
                    case 24:
                    case 25:
                    case 26:
                    case 27:
                    case 28:
                    case 29:
                        dom.css('background-image', 'url('+ xGame.essence(that.huntData.info.id) +')');
                        break;
                    default :
                        break;
                }
            }

            var offset1 = dom.offset();
            var offset2 = that.getRewardBtnDom.offset();

            var speed = 30;

            if (speedup) {
                speed *= 2;
            }

            var sumLeft = 0;
            var sumTop = 0;
            var XLen = offset2.left - offset1.left;
            var YLen = offset2.top - offset1.top;
            var distance = Math.sqrt(XLen * XLen + YLen * YLen);
            var xSpeed = XLen * speed / distance;
            var ySpeed = YLen * speed / distance;

            nowItemDom.removeClass('.ptype3');
            dom.children('.name').hide();
            dom.css('z-index', '10');

            moveItem();

            function moveItem() {
                
                that.moveItemTimer = setTimeout(moveItem, 33);
                sumLeft += xSpeed;
                sumTop += ySpeed;

                if (Math.abs(sumLeft) > Math.abs(XLen)) {
                    sumLeft = XLen;
                }

                if (Math.abs(sumTop) > Math.abs(YLen)) {
                    sumTop = YLen;
                }

                dom.css({'left': sumLeft + 'px', 'top': sumTop + 'px'});

                // 如果移动到了目标位置
                if (sumLeft == XLen && sumTop == YLen) {

                    if (that.moveItemTimer != null) {
                        clearTimeout(that.moveItemTimer);
                        that.moveItemTimer != null;
                    }

                    that.showMessage();
                    nowItemDom.attr('type', 0);
                    dom.fadeOut('normal', function () {
                        dom.remove();

                        // 如果是自动探宝
                        if (that.autoHunt == true) {

                            if (that.huntTimes < that.huntTotalTimes) {
                                that.hunt();
                                that.huntTimes ++;
                            } else {
                                that.autoHunt = false;
                                that.huntTimes = 0;
                                xGame.unlock();
                            }
                        } else {
                            xGame.unlock();
                        }
                    });
                }
            }
        },

        // 显示玩家获得的奖励
        showMessage: function () {
            
            var that = this;
            var info = that.huntData.info;
            var type = parseInt(info.type);

            switch (type) {
                case 1:     // 元宝
                    xGame.toast.show(lang('acttreasurepop_msg1', [info.num]));
                    break;
                case 2:     // 银币
                    xGame.toast.show(lang('acttreasurepop_msg2', [info.num]));
                    break;
                case 3:     // 黄宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [2, info.num, lang('yellow_jade')]));
                    break;
                case 4:     // 紫宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [3, info.num, lang('purple_jade')]));
                    break;
                case 12:    // 蓝宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [1, info.num, lang('blue_jade')]));
                    break;
                case 5:     // 原魂
                    xGame.toast.show(lang('acttreasurepop_msg4', [info.num]));
                    break;
                case 6:     // 玉石
                    xGame.toast.show(lang('acttreasurepop_msg5', [info.num]));
                    break;
                case 7:     // 声望
                    xGame.toast.show(lang('acttreasurepop_msg6', [info.num]));
                    break;
                case 8:     // 装备
                    xGame.toast.show(lang('acttreasurepop_msg7', [helper.getItemColor(info.quality), info.name]));
                    break;
                case 9:     // 宝石碎片
                    xGame.toast.show(lang('acttreasurepop_msg8', [info.num]));
                    break;
                case 10:    // 突飞卡
                    xGame.toast.show(lang('acttreasurepop_msg9', [info.num]));
                    break;
                case 11:    // 坐骑
                    xGame.toast.show(lang('acttreasurepop_msg10', [helper.getItemColor(info.quality), info.name]));
                    break;
                case 13:    // 武将
                    xGame.toast.show(lang('acttreasurepop_msg11', [info.name]));
                    break;
                case 14:     // 军令
                    xGame.toast.show(lang('acttreasurepop_msg12', [info.num]));
                    break;
                case 15:     // 精炼石
                    xGame.toast.show(lang('acttreasurepop_msg14', [info.num]));
                    break;
                case 16:     // 五彩石
                    xGame.toast.show(lang('acttreasurepop_msg15', [info.num]));
                    break;
                case 17:     // 上古血玉
                    xGame.toast.show(lang('acttreasurepop_msg16', [info.num]));
                    break;
                case 19:     // 汉字
                    xGame.toast.show(lang('acttreasurepop_msg17', [info.hanzi_num]) + lang('acttreasurepop_text'+ info.hanzi_id));
                    break;
                // 精华
                case 21:
                case 22:
                case 23:
                case 24:
                case 25:
                case 26:
                case 27:
                case 28:
                case 29:
                    xGame.toast.show(lang('acttreasurepop_msg18', [info.name, xGame.essence(info.id), info.num]));
                    break;
                default :
                    xGame.toast.show(lang('acttreasurepop_err_msg6'));
                    break;
            }
        },

        // 获取神秘大奖界面数据
        getPrizeData: function () {
            var that = this;

            var render = function (res) {
                
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.buildPrizePage(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'hanzi_index'
            }, render);

            render = null;
        },

        // 构建神秘大奖界面
        buildPrizePage: function (res) {
            var that = this;
            that.prizeListData = res.list;
            var list = res.list;
            var html = '';
            var item = null;

            for (var i = 0, len = list.length; i < len; i++) {

                item = list[i];

                html += '<li class="prize_item item'+ item.level +'">';
                html += '<div class="title"><span class="rank"></span><span class="desc">'+ item.desc +'</span></div>';

                for (var j = 1; j <= 3; j++) {

                    type = parseInt(item['type' + j]);

                    if (type == 0) {
                        continue;
                    }

                    html += '<div class="item type'+ type +' pos'+ j +'">';

                    if (type == 13) {   // 武将
                        html += '<span class="pic" style="background-image:url('+ xGame.avatar(item.general.image) +');"><em class="name">'+ item.name +'</em></span>';
                    } else if (type == 11) {    // 坐骑
                        html += '<span class="pic" style="background-position:'+ helper.getHorsePostion('small', item.mount.id) +';"><em class="name" style="color:'+ helper.getItemColor(item.mount.quality) +';">'+ item.mount.name +'</em></span>';
                    } else if (type == 8) {     // 装备
                        html += '<span class="pic" style="background-image:url('+ xGame.itemurl(item.equip.image) +');"><em class="name" style="color:'+ helper.getItemColor(item.equip.quality) +';">'+ item.equip.name +'</em></span>';
                    } else {
                        html += '<span class="pic"><em class="name">x'+ item['value' + j] +'</em></span>';
                    }

                    html += '</div>';
                }

                if (parseInt(item.open) == 0) {
                    html += '<button class="btn pop_btn_small_disable" pid="'+ i +'">' + lang('acttreasurepop_exchange') + '</button>';
                } else {
                    html += '<button class="btn pop_btn_small" pid="'+ i +'">' + lang('acttreasurepop_exchange') + '</button>';
                }

                html += '</li>';
            }

            that.prizeDom.find('#prize_reward_list').html(html).children('.prize_item').last().find('.btn').hide();

            that.prizeScroll = new iScroll('acttreasurepop_prize_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            html = '';
            list = res.hanzi_list;
            item = null;
            var num = 0;

            // 构建汉字
            for (var i = 0, len = list.length; i < len; i++) {

                num = parseInt(list[i].num);
                html += '<li class="item type19 '+ (num == 0 ? 'disable' : '') +'" id="text'+ list[i].id +'">';
                html += '<span class="pic text'+ list[i].id +'"><em class="num">x'+ num +'</em></span>';
                html += '</li>';
            }

            that.prizeDom.find('#have_text').html(html);
            that.prizeDom.show();

            var listItemMarginB = parseInt(that.prizeDom.find('.prize_item').css('margin-bottom')); // 每条奖励的边距
            var listItemHeight = that.prizeDom.find('.prize_item').height() + listItemMarginB; // 每条奖励所占高度
            var my = listItemHeight * that.prizeRewardIndex * -1;
            that.prizeScroll.refresh();
            that.prizeScroll.scrollTo(0, my);
        },

        // 兑换奖励
        exchangePrize: function (index) {
            var that = this;
            var data = that.prizeListData[index];

            var render = function (res) {
                
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        player.updateinfo({
                            'gold': res.all_gold,
                            'silver': res.all_silver
                        });

                        that.showPopo(res.hzexch_popo);
                        that.buildPrizePage(res);

                        if (that.exchangeTimer != null) {
                            clearTimeout(that.exchangeTimer);
                            that.exchangeTimer = null;
                        }
                        that.rewardDown(data);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'hanzi_exchange',
                'hid': data.id
            }, render);

            render = null;
        },

        // 奖励掉落
        rewardDown: function(data) {
            
            var that = this;
            
            var rewardBox = that.prizeDom.find('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var html = '';
            var type = 0;

            html += '<div class="reward_box reward_list">';

            for (var i = 1; i <= 3; i ++) {
                type = parseInt(data['type' + i]);

                if (type == 0) {
                    continue;
                } else if (type == 11) {
                    html += '<span class="reward_down pos' + i + ' type' + type + '"><em class="pic" style="background-position:'+ helper.getHorsePostion('buff', data.mount.id) +'"></em>x' + data['value' + i] + '</span>';
                } else {
                    html += '<span class="reward_down pos' + i + ' type' + type + '"><em class="pic"></em>x' + data['value' + i] + '</span>';
                }
            }

            html += '</div>';
            that.prizeDom.append(html);
            
            that.exchangeTimer = setTimeout(function() {
                
                if (that.prizeDom.css('display') == 'block') {
                    that.prizeDom.find('.reward_box').remove();
                }

            }, 3000);
            
            html = null;
        },

        // 获取今日得宝界面数据
        getGetRewardData: function () {
            var that = this;

            var render = function (res) {
                
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.getRewardListData = res.list;
                        that.buildGetRewardPage(res.list);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'today_reward',
            }, render);

            render = null;
        },

        // 构建今日得宝界面
        buildGetRewardPage: function (list) {
            var that = this;
            var html = '<li class="reward_item">';
            var n = 0;
            var type = 0;
            var item = null;

            for (var i = 0, len = list.length; i < len; i++) {

                item = list[i];
                type = parseInt(item.type);
                
                // 下一页
                if (n > 0 && n % 10 == 0) {
                    html += '</li><li class="reward_item">';
                }

                html += '<div class="item type'+ type +'" pos="'+ i +'" type="'+ type +'">';

                if (type == 13) {   // 武将
                    html += '<span class="pic" style="background-image:url('+ xGame.avatar(item.general.image) +');"><em class="num">'+ item.general.name +'</em></span>';
                } else if (type == 11) {    // 坐骑
                    html += '<span class="pic" style="background-position:'+ helper.getHorsePostion('small', item.mount.id) +';"><em class="num" style="color:'+ helper.getItemColor(item.mount.quality) +';">'+ item.mount.name +'</em></span>';
                } else if (type == 8) {     // 装备
                    html += '<span class="pic" style="background-image:url('+ xGame.itemurl(item.equip.image) +');"><em class="num" style="color:'+ helper.getItemColor(item.equip.quality) +';">'+ item.equip.name +'</em></span>';
                } else if (type == 19) {    // 汉字
                    html += '<span class="pic text'+ item.id +'"><em class="num">'+ item.num +'</em></span>';
                } else if (type >= 21 && type <= 29) {  // 精华
                    html += '<span class="pic" style="background-image:url('+ xGame.essence(item.info.id) +');"><em class="num">'+ item.num +'</em></span>';
                } else {
                    html += '<span class="pic"><em class="num">'+ that.getWan(item.num) +'</em></span>';
                }

                html += '</div>';
                n++;
            }

            html += '</li>';

            that.getRewardDom.find('.carousel_content').html(html);
            that.getRewardScroll = xGame.carousel('acttreasurepop_getReward_carousel', 390);
            that.getRewardDom.show();
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        // 获取排行榜数据
        getRankData: function () {
            
            var that = this;

            var render = function (res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.showRank(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            }

            loadContents({
                'c': 'act_treasure',
                'm': 'treasure_rank'
            }, render);

            render = null;
        },

        // 显示排行榜
        showRank: function (res) {
            
            var that = this;
            var item = null;
            var html = '';
            var type = 0;

            // 构建排行榜
            for (var i = 0, len = res.list.length; i < len; i++) {
                item = res.list[i];
                type = parseInt(item.type);

                html += '<li class="rank_item">'
                html += '<span class="rank">' + item.tank + '</span>';
                html += '<span class="player_name" uid="'+ item.uid +'">' + item.nickname + '</span>';
                html += '<span class="hunt_times">' + item.num + '</span>';
                
                if (type == 8 || type == 11) { // 装备和坐骑
                    html += '<span class="rank_reward type'+ type +'" style="color:'+ helper.getItemColor(item.info.quality) +'">' + item.info.name + '</span>';
                } else if (type >= 21 && type <= 29) { // 精华
                    html += '<span class="rank_reward essence" style="background-image:url('+ xGame.essence(item.info.id) +');color:'+ helper.getItemColor(item.info.quality) +'">x' + item.info.value + '</span>';
                } else {
                    html += '<span class="rank_reward type'+ type +'">x' + that.getWan(item.value) + '</span>';
                }

                html += '</li>';
            }

            that.rankDom.find('#rank_list').html(html);


            var rankReward = res.rank_reward;
            var nowType = parseInt(rankReward.type);
            var nowRewardDom = that.rankDom.find('#now_reward').children('.rank_reward');
            var rankRewardBtn = that.rankDom.find('#rank_reward_btn');
            var vipLimitDom = that.rankDom.find('.vip_limit');
            nowRewardDom.attr('class', 'rank_reward');

            // 领取按钮
            switch (parseInt(res.is_get)) {
                case 0:
                    rankRewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                    xGame.updateCd('acttreasure', res.reward_cd);
                    break;
                case 1:
                    rankRewardBtn.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
                    break;
                case 2:
                    rankRewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                    break;
                default :
                    break;
            }

            // 玩家排名
            if (parseInt(res.rank) != 0) {

                that.rankDom.find('#now_rank').children('.value').html(res.rank);
                vipLimitDom.hide();

                if (nowType == 8 || nowType == 11) { // 装备和坐骑
                    nowRewardDom.addClass('type' + nowType).html(rankReward.info.name).css('color', helper.getItemColor(rankReward.info.quality));
                } else if (nowType >= 21 && nowType<= 29) { // 精华
                    nowRewardDom.addClass('essence').css({'background-image': 'url('+ xGame.essence(rankReward.info.id) +')', 'color': helper.getItemColor(rankReward.info.quality)}).html('x' + rankReward.info.value);
                } else {
                    nowRewardDom.addClass('type' + nowType).html('x' + that.getWan(rankReward.value));
                }
            } else {
                rankRewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                that.rankDom.find('#now_rank').children('.value').html(lang('beatengod_not_reach'));
                nowRewardDom.html('');

                if (parseInt(player.info.vip) < 2) {
                    that.rankDom.find('#now_reward').hide();
                    vipLimitDom.show();
                } else {
                    that.rankDom.find('#now_reward').show();
                    vipLimitDom.hide();
                }
            }

            that.rankDom.show();

            that.rankScroller = new iScroll('acttreasurepop_rank_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
        },

        // 领取排行奖励
        getRankReward: function () {
            
            var that = this;

            var render = function (res) {
                
                switch (parseInt(res.status)) {
                    case 1:
                        that.showRankMessage(res.reward);
                        that.rankDom.find('#rank_reward_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'treasure_rank_reward'
            }, render);

            render = null;
        },

        // 显示玩家获得的排行奖励
        showRankMessage: function (reward) {
            
            var that = this;
            var type = parseInt(reward.type);

            switch (type) {
                case 1:     // 元宝
                    xGame.toast.show(lang('acttreasurepop_msg1', [reward.value]));
                    break;
                case 2:     // 银币
                    xGame.toast.show(lang('acttreasurepop_msg2', [reward.value]));
                    break;
                case 3:     // 黄宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [2, reward.value, lang('yellow_jade')]));
                    break;
                case 4:     // 紫宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [3, reward.value, lang('purple_jade')]));
                    break;
                case 5:     // 原魂
                    xGame.toast.show(lang('acttreasurepop_msg4', [reward.value]));
                    break;
                case 6:     // 玉石
                    xGame.toast.show(lang('acttreasurepop_msg5', [reward.value]));
                    break;
                case 7:     // 声望
                    xGame.toast.show(lang('acttreasurepop_msg6', [reward.value]));
                    break;
                case 8:     // 装备
                    xGame.toast.show(lang('acttreasurepop_msg7', [helper.getItemColor(reward.info.quality), reward.info.name]));
                    break;
                case 9:     // 宝石碎片
                    xGame.toast.show(lang('acttreasurepop_msg8', [reward.value]));
                    break;
                case 10:    // 突飞卡
                    xGame.toast.show(lang('acttreasurepop_msg9', [reward.value]));
                    break;
                case 11:    // 坐骑
                    xGame.toast.show(lang('acttreasurepop_msg10', [helper.getItemColor(reward.info.quality), reward.info.name]));
                    break;
                case 15:     // 精炼石
                    xGame.toast.show(lang('acttreasurepop_msg14', [reward.value]));
                    break;
                case 16:     // 五彩石
                    xGame.toast.show(lang('acttreasurepop_msg15', [reward.value]));
                    break;
                case 17:     // 上古血玉
                    xGame.toast.show(lang('acttreasurepop_msg16', [reward.value]));
                    break;
                case 21:     // 精华
                case 22:     // 精华
                case 23:     // 精华
                case 24:     // 精华
                case 25:     // 精华
                case 26:     // 精华
                case 27:     // 精华
                case 28:     // 精华
                case 29:     // 精华
                    xGame.toast.show(lang('acttreasurepop_msg18', [reward.info.name, xGame.essence(reward.info.id), reward.info.value]));
                    break;
                default :
                    break;
            }
        },

        // 获取集字成就数据
        getAchievementData: function () {
            
            var that = this;

            var render = function (res) {
                
                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        that.showAchievement(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'hanzi_achieve'
            }, render);

            render = null;
        },

        // 显示集字成就
        showAchievement: function (res) {
            
            var that = this;
            var item = null;
            var html = '';
            var type = 0;
            var index = 0; // 用于进度条
            var nowNum = parseInt(res.num);
            var percent = 0;
            that.achieveList = res.list;

            // 构建奖励列表
            for (var i = 0, len = res.list.length; i < len; i++) {
                
                item = res.list[i];
                html += '<li class="achieve_item">';
                html += '<span class="des">' + item.desc + '</span>';
                html += '<div class="achieve_reward">'

                for (var j = 1; j <= 3; j++) {
                    type = parseInt(item['type' + j]);

                    if (type == 8) { // 装备
                        html += '<span class="reward type'+ type +'" style="color:'+ helper.getItemColor(item['info' + j].quality) +'"><em class="item" style="background-image:url('+ xGame.itemurl(item['info' + j].id) +')"></em>' + item['info' + j].name + '</span>';
                    } else if (type == 11) { // 坐骑
                        html += '<span class="reward type'+ type +'" style="color:'+ helper.getItemColor(item['info' + j].quality) +'"><em class="item" style="background-position:'+ helper.getHorsePostion('buff', item['info' + j].id) +'">' + item['info' + j].name + '</span>';
                    } else if (type == 13) { // 武将
                        html += '<span class="reward type'+ type +'"><em class="item" style="background-image:url('+ xGame.avatar(item['info' + j].id) +')"></em>' + item['info' + j].name + '</span>';
                    } else if (type >= 21 && type <= 29) { // 精华
                        html += '<span class="reward essence" style="background-image:url('+ xGame.essence(item['info' + j].id) +');color:'+ helper.getItemColor(item['info' + j].quality) +'">x' + that.getWan(item['info' + j].value) + '</span>';
                    } else {
                        html += '<span class="reward type'+ type +'">x' + that.getWan(item['value' + j]) + '</span>';
                    }
                }

                html += '</div>';

                switch (parseInt(item.open)) {
                    case 0:
                        html += '<button class="reward_btn pop_btn_middle_disable">' + lang('not_reached') + '</button>';
                        break;
                    case 1:
                        html += '<button class="reward_btn pop_btn_middle" id="reward'+ item.id +'" aid="'+ item.id +'">' + lang('warena_fetch') + '</button>';
                        break;
                    case 2:
                        html += '<span class="received"></span>';
                    default :
                        break;
                }

                html += '</li>';
            }

            that.achievementDom.find('#achievement_list').html(html);
            that.achievementDom.find('#complete_num').html(nowNum);

            html = '';

            // 进度条
            for (var i = 0, len = res.num_list.length; i < len; i++) {
                item = parseInt(res.num_list[i]);
                html += '<li class="num">' + item + '</li>';

                if (item <= nowNum) {
                    index = i + 1;
                }
            }

            that.achievementDom.find('#progress_phase').html(html);

            if (index == 0) {
                percent = 0;
            } else {
                percent = index * 10;
                percent += (nowNum - res.num_list[index - 1]) / (res.num_list[index] - res.num_list[index - 1]) * 10;
            }

            if (percent < 100) {
                that.achievementDom.find('#now_progress').width(percent + '%').html('<span class="progress_light"></span>');
            } else {
                that.achievementDom.find('#now_progress').width('100%').html('');
            }
            
            that.achievementDom.show();
            that.achievementScroller = new iScroll('acttreasurepop_achievement_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
        },

        // 领取集字成就奖励
        getAchievementReward: function (id) {
            
            var that = this;

            var render = function (res) {

                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        player.updateinfo({
                            'gold': res.all_gold,
                            'silver': res.all_silver
                        });

                        that.showPopo(null, res.hzach_popo);
                        that.achievementDom.find('#reward' + id).replaceWith('<span class="received"></span>');

                        if (that.achieveTimer != null) {
                            clearTimeout(that.achieveTimer);
                            that.achieveTimer = null;
                        }

                        that.achieveRewardDown(that.achieveList[id - 1]);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'hanzi_achieve_reward',
                'aid': id
            }, render);

            render = null;
        },

        // 奖励掉落
        achieveRewardDown: function(item) {
            
            var that = this;
            
            var rewardBox = that.achievementDom.find('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var html = '';
            var type = 0;

            html += '<div class="reward_box">';

            for (var i = 1; i <= 3; i ++) {
                type = parseInt(item['type' + i]);

                if (type == 8) { // 装备
                    html += '<span class="reward_down pos' + i + ' type'+ type +'" style="color:'+ helper.getItemColor(item['info' + i].quality) +'"><em class="pic" style="background-image:url('+ xGame.itemurl(item['info' + i].id) +')"></em>' + item['info' + i].name + '</span>';
                } else if (type == 11) { // 坐骑
                    html += '<span class="reward_down pos' + i + ' type'+ type +'" style="color:'+ helper.getItemColor(item['info' + i].quality) +'"><em class="pic" style="background-position:'+ helper.getHorsePostion('buff', item['info' + i].id) +'"></em>' + item['info' + i].name + '</span>';
                } else if (type == 13) { // 武将
                    html += '<span class="reward_down pos' + i + ' type'+ type +'"><em class="pic" style="background-image:url('+ xGame.avatar(item['info' + i].id) +')"></em>' + item['info' + i].name + '</span>';
                } else if (type >= 21 && type <= 29) { // 精华
                    html += '<span class="reward_down pos' + i + '" style="color:'+ helper.getItemColor(item['info' + i].quality) +'"><em class="pic reward essence" style="background-image:url('+ xGame.essence(item['info' + i].id) +')"></em>x' + item['info' + i].value + '</span>';
                } else {
                    html += '<span class="reward_down pos' + i + '"><em class="pic reward type'+ type +'"></em>x' + item['value' + i] + '</span>';
                }
            }

            html += '</div>';
            that.achievementDom.append(html);
            
            that.achieveTimer = setTimeout(function() {
                
                if (that.achievementDom.css('display') == 'block') {
                    that.achievementDom.find('.reward_box').remove();
                }

            }, 3000);
            
            html = null;
        },

        // 探百次
        huntHundred: function () {
            
            var that = this;

            if (xGame.checkLock(true)) {
                return false;
            }

            var render = function (res) {

                xGame.unlock();
                
                switch (res.status) {
                    case 1:
                        xGame.islock = true;

                        player.updateinfo({
                            'gold': res.all_gold,
                            'silver': res.all_silver
                        });

                        that.showPopo(res.hzexch_popo, res.hzach_popo);

                        if (that.huntTimer != null) {
                            clearTimeout(that.huntTimer);
                        }
                        that.huntTimer = null;

                        that.moveFlag(0, res.now_site, true, false);     // 移动旗子
                        that.hundredRewardDown(res.reward);
                        break;
                    case 3:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c': 'act_treasure',
                'm': 'treasure_two',
                'type': 1
            }, render);

            render = null;
        },

        hundredRewardDown: function (reward) {
            
            var that = this;
            
            var rewardBox = that.e.children('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var type = 0;
            var arr = [];
            var item = null;
            var rewardBox = $('<div class="reward_box reward_list big"></div>');
            var j = 0;

            for (var i = 0, len = reward.length; i < len; i ++) {

                item = reward[i];
                type = item.type;

                if (type == 8) { // 装备
                    arr.push('<span class="reward_down type'+ type + '" style="color:'+ helper.getItemColor(item.info.quality) +'"><em class="pic" style="background-image:url('+ xGame.itemurl(item.info.id) +')"></em>' + item.info.name + '</span>');
                } else if (type == 11) { // 坐骑
                    arr.push('<span class="reward_down type'+ type + '" style="color:'+ helper.getItemColor(item.info.quality) +'"><em class="pic" style="background-position:'+ helper.getHorsePostion('buff', item.info.id) +'"></em>' + item.info.name + '</span>');
                } else if (type == 13) { // 武将
                    arr.push('<span class="reward_down type'+ type + '"><em class="pic" style="background-image:url('+ xGame.avatar(item.info.id) +')"></em>' + item.info.name + '</span>');
                } else if (type == 19) { // 汉字
                    arr.push('<span class="reward_down type' + type + '"><em class="pic text'+ item.hid +'"></em>x' + item.num + '</span>');
                } else if (type >= 21 && type <= 29) { // 精华
                    arr.push('<span class="reward_down type' + type + ' essence" style="color:'+ helper.getItemColor(item.info.quality) +'"><em class="pic" style="background-image:url('+ xGame.essence(item.info.id) +');"></em>x' + item.num + '</span>');
                } else {
                    arr.push('<span class="reward_down type' + type + '"><em class="pic"></em>x' + item.num + '</span>');
                }
            }

            that.e.append(rewardBox);

            function loop() {

                if (j < len) {
                    rewardBox.append($(arr[j]));
                    j++;
                    that.huntTimer = setTimeout(loop, 100);
                } else {
                    xGame.unlock();
                }
            }

            loop();


        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#acttreasurepop_hidebtn').unbind('touchend');
                this.e.find('#prize_btn').unbind('touchend');
                this.e.find('#getreward_btn').unbind('touchend');
                this.e.find('#hunt_btn').unbind('touchend');
                this.e.find('#tenhunt_btn').unbind('touchend');
                this.e.find('#hundredhunt_btn').unbind('touchend');
                this.e.unbind('touchend');
                this.e.find('#rank_btn').unbind('touchend');
                this.e.find('#achievement_btn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.prizeDom) {
                this.prizeDom.find('#prize_hidebtn').unbind('touchend');
                this.prizeDom.find('#view_rule').unbind('touchend');
                this.prizeDom.find('#prize_rule_hidebtn').unbind('touchend');
                this.prizeDom.find('#prize_reward_list').undelegate('.pop_btn_small', 'touchend');
                this.prizeDom.remove();
                this.prizeDom = null;
            }
            if (this.getRewardDom) {
                this.getRewardDom.find('#getreward_hidebtn').unbind('touchend');
                this.getRewardDom.undelegate('.item', 'click');
                this.getRewardDom.remove();
                this.getRewardDom = null;
            }
            if (this.rewardListDom) {
                this.rewardListDom.undelegate('.item', 'touchend');
                this.rewardListDom.remove();
                this.rewardListDom = null;
            }
            if (this.rankDom) {
                this.rankDom.find('#rank_hidebtn').unbind('touchend');
                this.rankDom.find('#rank_list').undelegate('.player_name', 'touchend');
                this.rankDom.undelegate('.pop_btn_middle', 'touchend');
                this.rankDom.remove();
                this.rankDom = null;
            }
            if (this.achievementDom) {
                this.achievementDom.find('#achievement_hidebtn').unbind('touchend');
                this.achievementDom.undelegate('.pop_btn_middle', 'touchend');
                this.achievementDom.remove();
                this.achievementDom = null;
            }
        },

        hide: function () {
            xGame.unlock();
            this.showOutPopo();
            this.isshow = false;
            this.spaceX = 129;
            this.spaceY = 97;
            this.nowPos = 0;
            this.autoHunt = false;
            this.huntTimes = 0;
            this.huntTotalTimes = 0;
            this.prizeRewardIndex = 0;
            this.huntHundredCost = 0;
            
            xGame.updateCd('acttreasure', 0);

            if (this.getRewardScroll != null) {
                this.getRewardScroll.destroy();
                this.getRewardScroll = null;
            }

            if (this.viewRewardScroll != null) {
                this.viewRewardScroll.destroy();
                this.viewRewardScroll = null;
            }

            if (this.prizeScroll != null) {
                this.prizeScroll.destroy();
                this.prizeScroll = null;
            }

            if (this.moveTimer != null) {
                clearTimeout(this.moveTimer);
                this.moveTimer = null;
            }

            if (this.moveItemTimer != null) {
                clearTimeout(this.moveItemTimer);
                this.moveItemTimer = null;
            }

            if (this.exchangeTimer != null) {
                clearTimeout(this.exchangeTimer);
                this.exchangeTimer = null;
            }

            if (this.rankScroller != null) {
                this.rankScroller.destroy();
                this.rankScroller = null;
            }

            if (this.achievementScroller != null) {
                this.achievementScroller.destroy();
                this.achievementScroller = null;
            }

            if (this.achieveTimer != null) {
                clearTimeout(this.achieveTimer);
                this.achieveTimer = null;
            }

            if (this.huntTimer != null) {
                clearTimeout(this.huntTimer);
                this.huntTimer = null;
            }
            if (this.freeDom) {
                this.freeDom.remove();
                this.freeDom = null;
            }
            if (this.costDom) {
                this.costDom.remove();
                this.costDom = null;
            }
            if (this.roleFlagDom) {
                this.roleFlagDom.remove();
                this.roleFlagDom = null;
            }
            if (this.getRewardBtnDom) {
                this.getRewardBtnDom.remove();
                this.getRewardBtnDom = null;
            }
            if (this.avatarDom) {
                this.avatarDom.remove();
                this.avatarDom = null;
            }
            if (this.itemDom) {
                this.itemDom.remove();
                this.itemDom = null;
            }
            this.unbindAll();
            this.huntData = null;
            this.rewardListData = null;
            this.prizeRuleDom = null;
            this.getRewardListData = null;
            this.prizeListData = null;
            this.achieveList = null;

        }
    };
})();