/* 整军演武 */
(function() {
    exercisePopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        this.levynums = 0;
        this.giftnums = 0;
    };
    exercisePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                $('#worldloading').hide();

                if (res.info.cd <= 0) {
                    xGame.toast.show(lang('exercise_end'));
                    player.hideActivity('exercise');
                    return false;
                }
                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('activity_not_exist'));
                case - 3 : xGame.toast.show(lang('get_player_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('exercise_end'));
                    //that.e.find('.content').find('#get_gift').attr('class','pop_btn_middle_disable');
                    break;
                case - 1 : xGame.toast.show(lang('activity_not_exist'));
                    break;
                case 1:
                    that.preparePage();
                    that.buildPage(res);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
            };

            loadContents({
                'c':
                'exercise',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        refreshPage: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.buildPage(res);
                }
            };
            loadContents({
                'c': 'exercise',
                'm': 'index'
            },
            initRender); //加载数据
            initRender = null;
        },

        preparePage: function() {
            var that = this;

            //进入页面
            that.e = xGame.loadPopTmpl('exercise');
            //关闭按钮
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                exercisepop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            //把数据塞到页面中去
            that.e.find('.content').find('.info').find('.date').show();
            that.e.find('.content').find('.info').find('.end').hide();

            //绑定资料事件
            that.e.find('#exercise_list').undelegate('.list_name', 'click').delegate('.list_name', 'click',
            function() {
                var uid = parseInt($(this).attr('uid'));
                if (uid > 0 || uid != NaN) {
                    xGame.showpop('info', uid);
                }
            });

            that.e.find('#golevy_wrap').unbind('touchend').bind('touchend',
            function() {
                xGame.showpop('levy', '1');
            });
            that.e.find('#get_gift').unbind('touchend').bind('touchend',
            function() {
                that.get_gift();
            });

            /* 滑动 */
            that.myScroller = new iScroll('exercisepop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
            });

            that.e.show();

            $('#worldloading').hide();

        },

        buildPage: function(res) {
            var that = this;
            if (!that.e) {
                return;
            }
            that.e.find('#gift').html(res.info.gift);
            //列表
            var html = '';
            var addition = 100;
            for (var i = 0; i < res.info.list.length; i++) {
                if (parseInt(res.info.list[i].uid) == player.info.uid) {
                    addition = res.info.list[i].addition;
                }
                html += '<li' + (res.info.list[i].uid == player.info.uid ? ' style="color:red;"' : '') + '>';
                html += '<b class="tit">' + res.info.list[i].sort + '</b>';
                html += '<em class="list_name" uid="' + res.info.list[i].uid + '">' + res.info.list[i].nickname + '</em>';
                html += '<em class="list_times">' + res.info.list[i].times + lang('times') + '</em>';
                html += '<em class="list_reward">' + lang('levy_income', [res.info.list[i].addition]) + '</em>';
                html += '</li>';
            }
            that.e.find('#exercise_list').html(html);
            that.e.find('.note').html(lang('my_exercise_times') + '<span id="exercise_mylevynums" style="text-align: right;position: absolute;width: 85px;left: 129px;"></span><span style="position: absolute;right: 0px;">' + lang('levy_income', [addition]) + '</span>');

            //征收次数
            that.levynums = parseInt(res.info.times);
            if(that.levynums == 0) {
                that.buildtimes(-1);
            } else {
            that.buildtimes(that.levynums);
            }

            //活动倒计时
            xGame.updateCd('exercise', res.info.cd);
            //绑定领取按钮事件
            that.giftnums = parseInt(res.info.gift);
            that.buildgifts(that.giftnums);

            that.myScroller.refresh();
        },

        buildtimes: function(res, gifts) {
            var that = this;
            if (typeof res == 'undefined' || res == 0) {
                res = that.levynums + 1;
            } else if(res == -1) {
                res = 0;
            }
            that.levynums = res;

            $('#exercise_mylevynums').html(that.levynums + lang('times'));

            var times = new Array(5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 100);
            var gift = new Array(5, 15, 30, 50, 75, 105, 140, 180, 225, 275, 500);
            var html = '';
            var html2 = '';
            var num = null;

            for (var i = times.length - 1; i >= 0; i--) {
                if (parseInt(res) >= times[i]) {
                    html += '<p class="red">' + times[i] + '</p>';
                    html2 += '<p style="color:red">' + gift[i] + '</p>';
                    if (num == null) {
                        num = i + 1;
                    }
                } else {
                    html += '<p>' + times[i] + '</p>';
                    html2 += '<p>' + gift[i] + '</p>';
                }
            }

            that.e.find('#times').html(html);
            that.e.find('#number').html(html2);

            var surplus = 0;
            if (num < 11) {
                number = parseInt(res) - times[num - 1] > 0 ? parseInt(res) - times[num - 1] : 1;
                surplus = Math.floor(27.9 / (times[num] - times[num - 1]) * number);
            }
            that.e.find('#exercise_exp').css({
                'height': (num * 27.9 + surplus) + 'px'
            });
            that.e.find('#exercise_times').html(that.levynums).css({
                'bottom': (num < 11 ? num * 27.9 + surplus + 4 : 315) + 'px'
            });

            //处理礼包数量
            if (typeof gifts != 'undefined' && gifts > 0) {
                //console.log('现在礼包' + gifts);
                that.buildgifts(gifts);
                //that.buildgifts(that.giftnums + gifts);
            }
        },

        buildgifts: function(giftnums) {
            var that = this;

            var dom_giftnum = this.e.find('#gift');
            var dom_giftbtn = this.e.find('#get_gift');

            if (!giftnums) {
                giftnums = that.giftnums++;
            }
            that.giftnums = giftnums;

            //处理页面显示
            dom_giftnum.html(giftnums);
            if (giftnums > 0) {
                dom_giftbtn.attr('class', 'btn pop_btn_middle');
            } else {
                dom_giftbtn.attr('class', 'btn pop_btn_middle_disable');
            }
        },

        get_gift: function() {
            var that = this;

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 6 : xGame.toast.show(lang('open_bag_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('reward_not_exist'));
                    break;
                case - 4 :
                    //xGame.toast.show('没有可领取的礼包');
                    return false;
                    break;
                case - 3 : xGame.toast.show(lang('exercise_end'));
                    break;
                case - 2 : xGame.toast.show(lang('exercise_end'));
                    break;
                case - 1 : xGame.toast.show(lang('exercise_end'));
                    break;
                case 1:
                    if (res.info.type == 1) {
                        if (parseInt(res.info.hit) > 1) {
                            xGame.toast.show('<span class="reward_crit rc' + parseInt(res.info.hit) + '">' + lang('silver') + res.info.num + '</span>');
                        } else {
                            xGame.toast.show(lang('receive_success_get_silver') + res.info.num);
                        }
                        player.updateinfo({
                            silver: parseInt(res.all_silver)
                        });
                    } else if (res.info.type == 2) {
                        if (parseInt(res.info.num) > 1) {
                            xGame.toast.show(lang('receive_success_get_act2', [res.info.num, res.info.num]));
                        } else {
                            xGame.toast.show(lang('receive_success_get_act', [res.info.num]));
                        }
                        player.updateinfo({
                            act: parseInt(res.all_act)
                        });
                    }

                    that.giftnums = parseInt(res.info.gift);
                    if (that.giftnums == 0) {
                        that.e.find('#get_gift').unbind('touchend').attr('class', 'btn pop_btn_middle_disable');
                    }
                    var dom_gift = that.e.find('#gift');
                    xGame.doAnimate2(dom_gift, 'changcolor', 100, 7,
                    function() {
                        dom_gift.html(res.info.gift);
                        dom_gift = null;
                    });
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
            }
            loadContents({
                'c': 'exercise',
                'm': 'open_gift'
            },
            initRender, true);
            initRender = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#golevy_wrap').unbind('touchend');
                this.e.find('#get_gift').unbind('touchend');
                this.e.find('#exercise_list').undelegate('.list_name', 'click');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('exercise', 0);
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            this.isshow = false;

            this.levynums = 0;
            this.giftnums = 0;
            this.unbindAll();
        },
    };
})();