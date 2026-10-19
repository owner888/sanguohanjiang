/* 蹴鞠大赛 */
(function() {
    kemariPopClass = function() {
        this.e;
        this.isshow = false;
        this.ballid;
        this.tmpl;
        this.times;
        this.rewardCd;
        this.isGoaling = false;
        this.rewardScroller;
        this.lotteryScroller;
        this.timeoutArr = [];
    };
    kemariPopClass.prototype = {
        init: function() {
            var self = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        self.initFirst(res);  
                        self.initGoalRank(res.rank_list);    
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            
            loadContents({
                'c': 'act_kemari',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
            
        },

        initFirst: function(res) {
            var self = this;
            this.times = res.times;
            this.e = xGame.loadPopTmpl('kemari');

            //活动时间
            this.e.find('.kemaripop_time').find('.times').html(res.time);

            //关闭按钮
            this.e.find('#kemaripop_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                kemaripop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            //奖券兑换按钮
            this.e.find('#lottery_btn').unbind('touchend').bind('touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.showLottery();
                e.preventDefault();
                e.stopPropagation();

            });
            // 奖券兑换中的奖励项
            this.e.find('#kemaripop_lottery').undelegate('.img', 'click').delegate('.img', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }
                var parent = $(this).parent();
                if (parent.hasClass('type12') || parent.hasClass('type26')) {
                    var index = $('#kemaripop_lottery_carousel .box').index(parent); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(self.rewardData[index].general);
                } else if (parent.hasClass('type8')) {
                    var index = $('#kemaripop_lottery_carousel .box').index(parent); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(self.rewardData[index].info);
                }

                e.preventDefault();
                e.stopPropagation();
            });
            //奖券兑换关闭按钮
            this.e.find('#lottery_hidebtn').unbind('touchend').bind('touchend', function(e) {
                self.lotteryScroller.destroy();
                self.lotteryScroller = null;
                self.e.find('#kemaripop_lottery').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            //破球奖励预览
            this.e.find('.ball1').unbind('touchend').bind('touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.showRewards(1);
                e.preventDefault();
                e.stopPropagation();
            });
            //好球奖励预览
            this.e.find('.ball2').unbind('touchend').bind('touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.showRewards(2);
                e.preventDefault();
                e.stopPropagation();
            });
            // 奖励预览中的奖励项
            this.e.find('#kemaripop_reward').undelegate('.box', 'click').delegate('.box', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }

                self.e.find('#kemaripop_reward').find('.box').removeClass('selected');
                $(this).addClass('selected');

                if ($(this).hasClass('type12') || $(this).hasClass('type26')) {
                    var index = $('#kemaripop_carousel .box').index($(this)); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(self.rewardData[index].general);
                } else if ($(this).hasClass('type8')) {
                    var index = $('#kemaripop_carousel .box').index($(this)); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(self.rewardData[index].info);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            this.e.find('#kemaripop_reward').find('.mask').unbind('touchend').bind('touchend', function(e) {
                self.rewardScroller.destroy();
                self.rewardScroller = null;
                self.e.find('#kemaripop_reward').hide();
                e.preventDefault();
                e.stopPropagation();
            });

            //奖励关闭按钮
            this.e.find('#reward_hidebtn').unbind('touchend').bind('touchend', function(e) {
                self.rewardScroller.destroy();
                self.rewardScroller = null;
                self.e.find('#kemaripop_reward').hide();
                e.preventDefault();
                e.stopPropagation();
            });

            //玩法说明按钮
            this.e.find('#play_btn').unbind('touchend').bind('touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.e.find('#kemaripop_info').css('display', 'block');
                e.preventDefault();
                e.stopPropagation();
            });

            this.e.find('#kemaripop_info').find('.mask').unbind('touchend').bind('touchend', function(e) {
                self.e.find('#kemaripop_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            //玩法说明关闭按钮
            this.e.find('#info_hidebtn').unbind('touchend').bind('touchend', function(e) {
                self.e.find('#kemaripop_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            //查看全部按钮
            this.e.find('#viewall_btn').unbind('touchend').bind('touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.viewAll();
                e.preventDefault();
                e.stopPropagation();
            });
            //左上角玩家排名
            this.e.find('#rank_list').undelegate('.player_name', 'touchend').delegate('.player_name', 'touchend', function (e) {
                if (self.isGoaling) {
                    return;
                }
                if (xGame.checkLock(true)) {
                    return false;
                }

                var uid = parseInt($(this).attr('uid'));
                xGame.showpop('info', uid);
                e.preventDefault();
                e.stopPropagation();
            });

            // 排行榜的玩家名
            this.e.find('#kemaripop_list').undelegate('.player_name', 'touchend').delegate('.player_name', 'touchend', function (e) {
                if (self.isGoaling) {
                    return;
                }
                if (xGame.checkLock(true)) {
                    return false;
                }

                var uid = parseInt($(this).attr('uid'));
                xGame.showpop('info', uid);
                e.preventDefault();
            });

            //进球榜关闭按钮
            this.e.find('#rewardrank_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.e.find('#kemaripop_rewardrank').css('display', 'none'); 
                xGame.updateCd('kemaripop', 0);
                e.preventDefault();
                e.stopPropagation();
            });

            //领取按鈕
            this.e.find("#rank_reward_btn").undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function(e) {
                var render = function(res) {
                    switch(parseInt(res.status)) {
                        case 1:
                            self.e.find('#rank_reward_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                            xGame.updateCd('kemaripop', self.rewardCd);
                            self.showMessage(res.reward);
                            break;
                        case 4:
                            self.e.find('#rank_reward_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                            break;
                        default:
                            xGame.toast.show(res.msg);
                            break;
                    }
                };

                loadContents({
                    'c': ' act_kemari',
                    'm': 'kemari_rank_reward'
                }, render);
                render = null;
                e.preventDefault();
                e.stopPropagation();
            });

            //射门按钮
            this.e.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function(e) {
                if (self.isGoaling) {
                    return;
                }
                self.isGoaling = true;
                var ballid = $(this).attr('ball');
                self.ballid = ballid;
                self.doGoal(ballid, $(this));
                e.preventDefault();
                e.stopPropagation();
            });

            if(res.list && res.list.length > 0) {
                for(var i = 0; i < res.list.length; i++) {
                    var _dom = this.e.find('#ball' + res.list[i].id);
                    
                    if (res.list[i].cd != 0) {
                        _dom.find('.pop_btn_middle').removeClass('free').children('.price').html('x'+res.list[i].price);
                        if (res.list[i].times > 0) {
                            xGame.updateCd('ball' + res.list[i].id, res.list[i].cd);
                        } else {
                            $('#ball_cd'+res.list[i].id).html(lang('kemaripop_times_over'));
                        }
                    } else {
                        if (res.list[i].times > 0) {
                            _dom.find('.pop_btn_middle').addClass('free').find('span').html(lang('kemaripop_free', [res.list[i].times]));
                        } else {
                            $('#ball_cd'+res.list[i].id).html(lang('kemaripop_times_over'));
                        } 
                    }
                }
            }

            this.e.show();
            $('#worldloading').hide();
        },

        showRewards: function(id) {
            var self = this;

            var render = function (res) {
                switch (parseInt(res.status)) {
                    case 1:
                        self.rewardData = res.list;
                        var html = self.getHtml(res.list, 'reward');
                         self.e.find('.carousel_content').html(html);
                         self.rewardScroller = xGame.carousel('kemaripop_carousel', 750);
                         self.e.find('#kemaripop_reward').show();
                        break;
                    default :
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'act_kemari',
                'm': 'kemari_reward',
                'type': id
            }, render);
            render = null;
        },

        showLottery: function() {
            var self = this;

            var render = function (res) {
                switch (parseInt(res.status)) {
                    case 1:
                        self.rewardData = res.list;
                        var html = self.getHtml(res.list, 'lottery');
                        self.e.find('.carousel_content').html(html);
                        self.e.find('.mylottery').html(''+res.lottery_num);
                        self.lotteryScroller = xGame.carousel('kemaripop_lottery_carousel', 750);
                        self.e.find('#kemaripop_lottery').show();
                        //兑换按钮
                        self.e.find('#kemaripop_lottery_carousel').find('.lotteryenablebtn').unbind('click').bind('click', function(e) {
                            var lotteryid = $(this).attr('uid');
                            xGame.confirm.show(lang('kemaripop_lottery_act_title'), lang('kemaripop_lottery_act', [self.rewardData[lotteryid-1].lottery_num]), 2, function () {
                                var render = function(res) {
                                    switch(parseInt(res.status)) {
                                        case 1:
                                            self.updateLotteryInfo(res);
                                            break;
                                        default:
                                            xGame.toast.show(res.msg);
                                            break;
                                    }
                                    xGame.confirm.hide();
                                };

                                loadContents({
                                    'c': 'act_kemari',
                                    'm': 'action_lottery',
                                    'id': lotteryid
                                }, render);
                                render = null;
                            }, function () {
                                xGame.confirm.hide();
                            })
                        });
                            
                        break;
                    default :
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'act_kemari',
                'm': 'get_lottery'
            }, render);
            render = null;
        },

        updateLotteryInfo: function(res) {
            //显示获得奖励
            this.showMessage(res.reward);
            //刷新兑换按钮
            for (var i = 0; i < res.list.length; i++) {
                var item = res.list[i];
                if (item.lottery_num > res.lottery_num) {
                   this.e.find('#kemaripop_lottery_carousel').find('#btn'+parseInt(item.id)).removeClass('lotteryenablebtn').addClass('lotterydisbtn');
                } else {
                   this.e.find('#kemaripop_lottery_carousel').find('#btn'+parseInt(item.id)).removeClass('lotterydisbtn').addClass('lotteryenablebtn');
                }
            }
            var self = this;
            //兑换按钮
            this.e.find('#kemaripop_lottery_carousel').find('.lotterydisbtn').unbind('click');
            this.e.find('#kemaripop_lottery_carousel').find('.lotteryenablebtn').unbind('click').bind('click', function(e) {
                var lotteryid = $(this).attr('uid');
                xGame.confirm.show(lang('kemaripop_lottery_act_title'), lang('kemaripop_lottery_act', [self.rewardData[lotteryid-1].lottery_num]), 2, function () {
                    var render = function(res) {
                        switch(parseInt(res.status)) {
                            case 1:
                                self.updateLotteryInfo(res);
                                break;
                            default:
                                xGame.toast.show(res.msg);
                                break;
                        }
                        xGame.confirm.hide();
                    };

                    loadContents({
                        'c': 'act_kemari',
                        'm': 'action_lottery',
                        'id': lotteryid
                    }, render);
                    render = null;
                }, function () {
                    xGame.confirm.hide();
                })
            });
            //刷新兑奖券数量
            this.e.find('.mylottery').html(''+res.lottery_num);
        },

        // 显示玩家获得的奖励
        showMessage: function (reward) {
            
            var type = parseInt(reward.type);

            switch (type) {
                case 1:     // 元宝
                    if (res.all_gold) {
                        player.updateinfo({'gold': res.all_gold});
                    } else {
                        var gold = parseInt(player.info.gold) + parseInt(reward.value);
                        player.updateinfo({'gold': gold});
                    }
                    xGame.toast.show(lang('acttreasurepop_msg1', [reward.value]));
                    break;
                case 2:     // 银币
                    var silver = parseInt(player.info.silver) + parseInt(reward.value);
                    player.updateinfo({'silver': silver});
                    xGame.toast.show(lang('acttreasurepop_msg2', [reward.value]));
                    break;
                case 3:     // 黄宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [2, reward.value, lang('yellow_jade')]));
                    break;
                case 4:     // 紫宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [3, reward.value, lang('purple_jade')]));
                    break;
                case 11:    // 蓝宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [1, reward.value, lang('blue_jade')]));
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
                    xGame.toast.show(lang('acttreasurepop_msg7', [helper.getItemColor(reward.equip.quality), reward.equip.name]));
                    break;
                case 9:     // 宝石碎片
                    xGame.toast.show(lang('acttreasurepop_msg8', [reward.value]));
                    break;
                case 10:    // 突飞卡
                    xGame.toast.show(lang('acttreasurepop_msg9', [reward.value]));
                    break;
                case 12:    // 武将
                case 26:    // 神将
                    miscpop.newhero(reward.general);
                    break;
                case 13:     // 军令
                    xGame.toast.show(lang('acttreasurepop_msg12', [reward.value]));
                    break;
                case 14:     // 精炼石
                    xGame.toast.show(lang('acttreasurepop_msg14', [reward.value]));
                    break;
                case 15:     // 五彩石
                    xGame.toast.show(lang('acttreasurepop_msg15', [reward.value]));
                    break;
                case 16:     // 上古血玉
                    xGame.toast.show(lang('acttreasurepop_msg16', [reward.value]));
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
                    xGame.toast.show(lang('acttreasurepop_msg18', [reward.info.name, xGame.essence(reward.info.id), reward.info.value]));
                    break;
                case 27:    //奖券
                    xGame.toast.show(lang('kemaripop_msg', [reward.value]));
                    break;
                default :
                    xGame.toast.show(lang('acttreasurepop_err_msg6'));
                    break;
            }
        },

        getHtml: function (list, type) {
            var html = '<li>';
            var n = 0;
            var num = 0;
            var classname = '';
            if (type == 'reward') {
                num = 12;
            } else if (type == 'lottery') {
                num = 8;
            }

            for (var i = 0, len = list.length; i < len; i++) {

                var item = list[i];
                
                // 下一页
                if (n > 0 && n % num == 0) {
                    html += '</li><li>';
                }

                html += '<div class="box type'+ item.type +'">';
               
                html += '<span class="img"></span>';
                if (type == 'lottery') {
                    if (parseInt(item.type) == 12 || parseInt(item.type) == 26) {//武将
                        html += '<em class="value">' + item.general.name + '</em>';
                    } else if (parseInt(item.type) == 8) { //装备
                        html += '<em class="value" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.info.name + '</em>';
                    } else {
                        html += '<em class="value">x' + item.value + '</em>';
                    }
                    html += '<em class="number">' + item.lottery_num + '</em>';
                } else if (type == 'reward') {
                    html += '<em class="name">' + item.name + '</em>';
                }
                if (parseInt(item.type) == 12 || parseInt(item.type) == 26) {//武将
                    html += '<span class="img" style="background-image:url(' + xGame.avatar(item.general.image) + ')"></span>';
                } else if (parseInt(item.type) == 8) { //装备
                    html += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.image) +');"><em class="name" style="color:'+ helper.getItemColor(item.info.quality) +';"></em></span>';
                } else if (parseInt(item.type) >= 17 && parseInt(item.type) <= 25) {//精华
                    html += '<span class="img" style="background-image:url('+ xGame.essence(item.id) +');"><em class="name">'+ item.name +'</em></span>';
                }
                if (type == 'lottery') {
                    if (parseInt(item.is_exch) != 0) {
                        html += '<button class="lotteryenablebtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('acttreasurepop_exchange')+'</button>';
                    } else {
                        html += '<button class="lotterydisbtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('acttreasurepop_exchange')+'</button>';
                    }
                }

                html += '</div>';
                n++;
            }

            html += '</li>';

            return html;
        },

        viewAll: function() {
            var self = this;

            var initRender = function(res) {
                 switch(parseInt(res.status)) {
                    case 1:
                        self.rewardCd = res.reward_cd;
                        self.e.find('#kemaripop_rewardrank').css('display', 'block');
                        var html = '';
                        for (var i = 0; i < res.list.length; i++) {
                            var item = res.list[i];
                            var type = res.list.type;
                            html += '<li class="rank_item"'+ (item.uid == player.info.uid ? 'style="color:red;"' : '')+'>'
                            html += '<span class="rank">' + item.rank + '</span>';
                            html += '<span class="player_name" uid="'+ item.uid +'"'+(item.uid == player.info.uid ? 'style="color:red;"' : '')+'>' + item.nickname + '</span>';
                            html += '<span class="hunt_times">' + item.num + '</span>';
                            
                            html += '<span class="rank_reward type'+ item.type +'">x' + self.getWan(item.value) + '</span>';
                            
                            html += '</li>';
                        }
                        self.e.find('#kemaripop_list').html(html);

                        var rankReward = res.rank_reward;
                        var nowType = parseInt(rankReward.type);
                        var nowRewardDom = self.e.find('#now_reward').children('.rank_reward');
                        var rankRewardBtn = self.e.find('#rank_reward_btn');
                        var vipLimitDom = self.e.find('.kemaripop_rewardrank').find('.vip_limit');
                        nowRewardDom.attr('class', 'rank_reward');

                        // 领取按钮
                       switch (parseInt(res.is_get)) {
                           case 0://活动时间未到，显示倒计时
                                rankRewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                                xGame.updateCd('kemaripop', res.reward_cd);
                                break;
                            case 1://可领取
                                rankRewardBtn.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
                                break;
                            case 2://已领取
                                rankRewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                                break;
                            default :
                                break;
                        }

                        // 玩家排名
                        if (parseInt(res.rank) != 0) {
                            self.e.find('#now_rank').children('.value').html(res.rank);
                            vipLimitDom.hide();

                            self.e.find('#now_rank').css({'margin-top':'18px'});
                            self.e.find('#now_reward').show();
                            nowRewardDom.addClass('type' + nowType).html('x' + self.getWan(rankReward.value));
                        } else {
                            rankRewardBtn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                            self.e.find('#now_rank').children('.value').html(lang('beatengod_not_reach'));
                            nowRewardDom.html('');

                            if (parseInt(player.info.vip) < 2) {
                                self.e.find('#now_rank').hide();
                                self.e.find('#now_reward').hide();
                                vipLimitDom.show();
                            } else {
                                self.e.find('#now_rank').show();
                                self.e.find('#now_reward').show();
                                vipLimitDom.hide();
                            }
                        }


                        self.iScroller = new iScroll('kemaripop_scroller', {
                            snap: 'li',
                            bounce: false,
                            momentum: false,
                            hScrollbar: false,
                            vScrollbar: false
                        });

                        self.iScroller.refresh();
                        break;
                    case 2:
                        xGame.toast.show(res.msg);
                        break;
                 }
            };

            loadContents({
                'c': 'act_kemari',
                'm': 'kemari_rank'
            },
            initRender, true);
            initRender = null;
            
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        initGoalRank: function(ranklist) {
            var html = '';
            
            for (var i = 0; i < ranklist.length; i++) {
                var item = ranklist[i];
                html += '<li class="rank_item">';
                html += '<span class="player_name" uid="'+ item.uid +'"'+(item.uid == player.info.uid ? 'style="color:red;"' : '')+'>' + item.nickname + '</span>';
                html += '<span class="goal_number">' + item.num + lang('times') +'</span>';
            }

            this.e.find('#rank_list').html(html);
            
            html = null;
        },

        doGoal: function(ballid, goalBtn) {

            var self = this;

            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        xGame.islock = true;
                        self.times = res.times;
                        player.updateinfo({'gold': res.all_gold});
                        self.goalResult(res);

                        if (goalBtn && goalBtn.hasClass('free')) {
                            goalBtn.removeClass('free').children('.price').html('x'+res.price);
                            if (res.times > 0) {
                                xGame.updateCd('ball'+self.ballid, res.cd);
                            } else {
                                $('#ball_cd'+self.ballid).html(lang('kemaripop_times_over'));
                            }
                        }
                        break;
                    case 4:
                        xGame.goPay(lang('gold_not_enough'));
                        self.isGoaling = false;
                        xGame.unlock();
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        self.isGoaling = false;
                        break;
                }
            };

            loadContents({
                'c': 'act_kemari',
                'm': 'action',
                'type': ballid
            },
            initRender, true);
            initRender = null;
        },

        goalResult: function(res) {
            var img = '';
            var rand = Math.random();
            var scaleX = 1;
            var pos = {'x':350, 'y':150};
            if (rand <= 0.5) {
                pos.x = 300;
                scaleX = -1;
            }
            var goalImg = null;
            switch(this.ballid) {
                case "1"://破球
                    goalImg = ximg(staticUrl + 'img/pop/kemaripop_goal_badball.png');
                    if (res.is_succ != 0) {
                        img = ximg(staticUrl + 'img/pop/kemaripop_badball_succ.png');
                        pos.x = 330;
                    } else {
                        img = ximg(staticUrl + 'img/pop/kemaripop_badball_lose.png');
                    }
                    break;
                case "2"://好球
                    goalImg = ximg(staticUrl + 'img/pop/kemaripop_goal_intectball.png');
                    if (res.is_succ != 0) {
                        img = ximg(staticUrl + 'img/pop/kemaripop_intectball_succ.png');
                    } else {
                        img = ximg(staticUrl + 'img/pop/kemaripop_intectball_lose.png');
                    }
                    break;
                default:
                    break;
            }
            if (res.is_succ != 0) {
                this.tmpl = 'succ';
            } else {
                this.tmpl = 'lose';
            }
            this.e.find('.kemaripop_goal').css({'background': 'url('+goalImg+') 0 0 no-repeat'});
            this.e.find('.kemaripop_goal').show();
            var self = this;
            this.timeoutArr.push(setTimeout(function() {
                self.e.find('.kemaripop_goal').hide();
                self.doAniamtion(res, img, pos, scaleX);
            }, 500));
            
        },

        closeResult: function() {
            var that = this;
            if(that.e2) {
                that.e2.remove();
                that.e2 = null;
            }
        },

        openResult: function(res) {
            var self = this;

            //刷新左上角排行
            this.initGoalRank(res.rank_list);
            if(this.e2 != null) {
                this.updateResult(res);
                xGame.doAnimate(this.e2.find('.kemari'+this.tmpl), 'newhero_scalein', '0.5s');
                return false;
            }

            this.e2 = xGame.loadPopTmpl('kemari'+this.tmpl);
            this.e2.find('#kemari'+this.tmpl+'_hidebtn').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                self.timeoutArr.push(setTimeout(function() {
                    self.closeResult();
                    self.isGoaling = false;
                }, 300));
            });
            this.e2.find('#redo'+this.tmpl+'_btn').unbind('touchend').bind('touchend', function(e) {
                if(xGame.checkLock()) {
                    return false;
                }
                self.closeResult();
                var btnArr = self.e.find('.pop_btn_middle');
                self.doGoal(self.ballid, $(btnArr[self.ballid-1]));
                e.preventDefault();
                e.stopPropagation();
            });

            this.updateResult(res);

            this.e2.show();
        },

        refreshResultBtnLabel: function(times) {
            if (this.e2) {
                var redobtn = this.e2.find('#redo'+this.tmpl+'_btn');
                if (times > 0) {
                    var btnImg = ximg(staticUrl + 'img/pop/pop_btn_middle.png');
                    redobtn.html(lang('kemaripop_free', times));
                    redobtn.find('btntext').css({'left': '12px'});
                    redobtn.css({'background': 'url('+btnImg+') 0 0 no-repeat;', 'font-size':'22px'});
                }
            }
        },

        updateResult: function(res) {
            this.e2.find('#kemari'+this.tmpl+'pop_wrap').attr('class', 'content result' + res.reward_info.type);
            this.e2.find('#redo'+this.tmpl+'_btn').children('em').html('x'+res.price);
            //文字
            this.showPicNum(res.reward_info.value, res.reward_info.font_size);

            this.timeoutArr.push(setTimeout(function() {
                xGame.unlock();
            }, 700));
        },

        showPicNum: function(number, size) {
            var numberWrap = $('<div id="kemariresult_number_wrap" class="kemariresult_numbers numberanim' + size + '"></div>');
            var numberOjb = this.createPicNum('-' + number + '');
            numberWrap.append(numberOjb.e);
            this.e2.find('#kemari'+this.tmpl+'pop_wrap').append(numberWrap);

        },

        createPicNum: function(number) {
            var numAnim = new Num({
                id: helper.createUUID("kemarinum"),
                src: ximg(staticUrl + 'img/pop/eggresultpop_number.png'),
                max: number.length,
                gap: 42,
                height: 52,
                x: 0,
                y: 0,
                value: number
            });

            return numAnim;
        },

        doAniamtion: function(res, img, pos, scaleX) {
            var self = this;
         
            var mc = new movieClipClass({
                "img": img,
                "width": 330,
                "height": 500,
                "x": pos.x,
                "y": pos.y,
                "scale": 1,
                "scaleX": scaleX,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    if(res.reward_info.type == 12 || res.reward_info.type == 26) {//获得武将，弹武将窗口
                        miscpop.newhero(res.reward_info.general, function() {
                            self.isGoaling = false;
                        }, 1000);
                        if (typeof CD['chat'] != 'undefined') {
                            CD['chat'].read();//手动请求一次，为了早点广播
                        }
                        self.initGoalRank(res.rank_list);
                    } else {
                        self.openResult(res);
                    } 
                    xGame.unlock();           
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [334, 0],
                    interval: 100
                },
                {
                    rect: [668, 0],
                    interval: 100
                },
                {
                    rect: [1002, 0],
                    interval: 110
                },
                {
                    rect: [1336, 0],
                    interval: 110
                },
                {
                    rect: [1670, 0],
                    interval: 110
                },
                {
                    rect: [2004, 0],
                    interval: 120
                },
                {
                    rect: [2338, 0],
                    interval: 120
                },
                {
                    rect: [0, 490],
                    interval: 120
                },
                {
                    rect: [334, 490],
                    interval: 120
                },
                {
                    rect: [668, 490],
                    interval: 120
                },
                {
                    rect: [1002, 490],
                    interval: 150
                },
                {
                    rect: [1336, 490],
                    interval: 150
                },
                {
                    rect: [1670, 490],
                    interval: 150
                },
                {
                    rect: [2004, 490],
                    interval: 150
                }]
            });

            mc.appendTo(this.e.find('#kemaripop_wrap'));
            mc.play();
            mc = null;
        },

        unbindAll: function() {
            this.e.find('#kemaripop_hidebtn').unbind('touchend');
            this.e.find('#lottery_btn').unbind('touchend');
            this.e.find('#kemaripop_lottery').undelegate('.box', 'click');
            this.e.find('#lottery_hidebtn').unbind('touchend');
            this.e.find('#viewball1_btn').unbind('touchend');
            this.e.find('#viewball2_btn').unbind('touchend');
            this.e.find('#kemaripop_reward').undelegate('.box', 'click');
            this.e.find('#kemaripop_reward').find('.mask').unbind('touchend');
            this.e.find('#reward_hidebtn').unbind('touchend');
            this.e.find('#play_btn').unbind('touchend');
            this.e.find('#kemaripop_info').find('.mask').unbind('touchend');
            this.e.find('#info_hidebtn').unbind('touchend');
            this.e.find('#viewall_btn').unbind('touchend');
            this.e.find('#rank_list').undelegate('.player_name', 'touchend');
            this.e.find('#kemaripop_list').undelegate('.player_name', 'touchend');
            this.e.find('#rewardrank_hidebtn').unbind('touchend');
            this.e.find("#rank_reward_btn").undelegate('.pop_btn_middle', 'touchend');
            this.e.undelegate('.pop_btn_middle', 'touchend');
            this.e.find('#kemaripop_lottery_carousel').find('.lotteryenablebtn').unbind('click');
        },

        hide: function() {
            xGame.updateCd('ball1', 0);
            xGame.updateCd('ball2', 0);
            xGame.updateCd('kemaripop', 0);
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            for (var i = 0; i < this.timeoutArr.length; i++) {
                clearTimeout(this.timeoutArr[i]);
            }
            this.timeoutArr.length = 0;
        }
    };
})();