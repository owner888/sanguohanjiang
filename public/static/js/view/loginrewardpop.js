// 登录返礼
(function () {
    loginrewardPopClass = function () {
        this.e = null;
        this.isshow = false;
        this.rewardInfo = null; // 主界面上方的奖励信息
        this.reward1 = null; // 主界面上方的奖励列表
        this.reward2 = null; // 主界面下面的奖励列表
        this.rewardData1 = null; // 上方的奖励数据
        this.scroller = null; // 下方的奖励滑动列表
        this.rewardTime = null; // 掉落奖励计时器
    };

    loginrewardPopClass.prototype = {
        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.initPage(res);
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'loginreward',
                'm': 'index'
            }, render);
        },

        initPage: function (res) {
            
            this.e = xGame.loadPopTmpl('loginreward');
            this.rewardInfo = this.e.find('#loginreward_info');
            this.reward1 = this.rewardInfo.find('#loginreward_reward1');
            this.reward2 = this.e.find('#loginreward_reward2');

            this.e.find('.act_time').html(res.time); // 活动时间
            this.buildReward1(res);
            this.buildReward2(res.reward);
            this.bindAll();

            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();
        },

        bindAll: function () {
            
            var that = this;

            that.e.find('#loginreward_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            // 领取累计登录天数奖励
            that.rewardInfo.delegate('#loginreward_reward1_btn.pop_btn_small', 'touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getReward(1, $(this).attr('reward_id'));
                e.preventDefault();
            });

            // 领取各登录天数奖励
            that.reward2.delegate('.reward2_btn.pop_btn_small', 'click', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getReward(2, $(this).attr('reward_id'));
                e.preventDefault();
            });
        },

        // 构建上方的奖励
        buildReward1: function (res) {
            
            this.rewardInfo.find('.login_time').html(res.join_time);
            this.rewardInfo.find('.day').html(res.day);
            this.rewardData1 = res.reward_index;

            var html = '';
            var btnStatus = parseInt(this.rewardData1.is_re);
            var btn = this.rewardInfo.find('#loginreward_reward1_btn');

            // 构建上方奖励列表
            for (var i = 1; i <= 5; i++) {
                html += '<li class="item type'+ this.rewardData1['type' + i] +'">';
                html += '<span class="pic"></span>';
                html += '<em class="num">x' + helper.convertWan(this.rewardData1['num' + i]) + '</em>';
                html += '</li>';

                if (i != 5) {
                    html += '<span class="plus"></span>';
                }
            }

            this.reward1.html(html);

            // 设置按钮状态
            switch (btnStatus) {
                case 0:
                    btn.removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('not_reached'));
                    break;
                case 1:
                    btn.removeClass('pop_btn_small_disable').addClass('pop_btn_small').html(lang('receive'));
                    break;
                case 2:
                    btn.removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('newbiegift_fetched'));
                    break;
                default:
                    btn.removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('not_reached'));
                    break;
            }

            btn.attr('reward_id', this.rewardData1.id);
            btn = null;
        },

        // 构建下方的奖励列表
        buildReward2: function (list) {
            
            var html = '';
            var item = null;

            for (var i = 0, len = list.length; i < len; i++) {
                item = list[i];
                html += '<li class="item type'+ item.type +'">';
                html += '<span class="all_day">' + lang('loginreward_day', ['<em class="red">' + item.day + '</em>']) + '</span>';
                html += '<span class="gift">' + lang('loginreward_give') + '<em class="pic red">x' + helper.convertWan(item.num) + '</em></span>';

                switch (parseInt(item.is_re)) {
                    case 0:
                        html += '<button class="btn pop_btn_small_disable reward2_btn" reward_id="'+ item.id +'">' + lang('not_reached') + '</button>';
                        break;
                    case 1:
                        html += '<button class="btn pop_btn_small reward2_btn" reward_id="'+ item.id +'">' + lang('receive') + '</button>';
                        break;
                    case 2:
                        html += '<button class="btn pop_btn_small_disable reward2_btn" reward_id="'+ item.id +'">' + lang('newbiegift_fetched') + '</button>';
                        break;
                    default :
                        html += '<button class="btn pop_btn_small_disable reward2_btn" reward_id="'+ item.id +'">' + lang('not_reached') + '</button>';
                        break;
                }

                html += '</li>';
            }

            this.reward2.html(html);
            item = null;

            if (this.scroller == null) {
                this.scroller = new iScroll('loginreward_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            this.scroller.refresh();
        },

        // 领取累计登录天数奖励
        // type: 1：领取A类奖励，2：领取B类奖励；rewardId：奖励Id
        getReward: function (type, rewardId) {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {

                    if (type == 1) {
                        that.rewardDown();
                        that.rewardInfo.find('#loginreward_reward1_btn').removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('newbiegift_fetched'));
                    } else {
                        xGame.toast.show(that.showMsg(res.reward));
                        that.reward2.find('.reward2_btn').eq(parseInt(rewardId) -1).removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('newbiegift_fetched'));
                    }

                    player.updateinfo({
                        'gold': res.gold,
                        'silver': res.silver
                    });
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'loginreward',
                'm': 'action_reward',
                'type': type,
                'reward_id': rewardId
            }, render);
        },

        // 掉落奖励
        rewardDown: function () {

            var that = this;
            var html = '<div class="reward_box">';

            for (var i = 1; i <= 5; i++) {
                html += '<span class="item type'+ this.rewardData1['type' + i] +' reward_down pos'+ i +'">';
                html += '<em class="pic">x' + helper.convertWan(this.rewardData1['num' + i]) + '</em>';
                html += '</span>';
            }

            html += '</div>';

            this.e.find('.content').append(html);

            this.rewardTime = setTimeout(function () {
                that.e.find('.reward_box').remove();
            }, 3000);
        },

        // 显示获奖信息
        showMsg: function (data) {
            
            var type = parseInt(data.type);
            var num = helper.convertWan(data.num);
            var msg = '';

            switch (type) {
                case 2: // 银币
                    msg = lang('acttreasurepop_msg2', [num]);
                    break;
                case 3: // 黄宝石
                    msg = lang('acttreasurepop_msg3', [2, num, lang('yellow_jade')]);
                    break;
                case 4: // 紫宝石
                    msg = lang('acttreasurepop_msg3', [3, num, lang('purple_jade')]);
                    break;
                case 11: // 蓝宝石
                    msg = lang('acttreasurepop_msg3', [1, num, lang('blue_jade')]);
                    break;
                case 2: // 银币
                    msg = lang('acttreasurepop_msg9', [num]);
                    break;
                default :
                    break;
            }

            return msg;
        },

        unbindAll: function () {
            
            this.e.find('#loginreward_hidebtn').unbind('touchend');
            this.rewardInfo.undelegate('#loginreward_reward1_btn.pop_btn_small', 'touchend');
            this.reward2.undelegate('.reward2_btn.pop_btn_small', 'touchend');
        },

        hide: function () {
            
            xGame.unlock();
            this.unbindAll();

            if (this.rewardTime != null) {
                clearTimeout(this.rewardTime);
                this.rewardTime = null;
            }

            this.isshow = false;
            this.rewardInfo = null;
            this.reward1 = null;
            this.reward2 = null;
            this.rewardData1 = null;
            this.rewardTime = null;
            this.e.remove();
            this.e = null;

            if (loginrewardpop) {
                loginrewardpop = null;
            }
        },
    };
})();