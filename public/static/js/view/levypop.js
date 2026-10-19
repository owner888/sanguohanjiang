/* 征收 */
(function() {
    levyPopClass = function() {
        this.e;
        this.isshow = false;
        this.data;
        this.t = null;
        this.timeseconds = 0;
        this.force_gold = 0;
        this.frome = 0; //从整军演武进来的
    };
    levyPopClass.prototype = {
        init: function(frome) {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            if (typeof frome != 'undefined') {
                this.frome = frome;
            }

            var initRender = function(res) {

                if (!res || res.status != 1) {
                    xGame.toast.show(lang('can_noe_get_levy_date'));
                    $('#worldloading').hide();
                    return;
                }
                /* 保存数据 */
                that.data = res;

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                that.e = xGame.loadPopTmpl('levy');
                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    levypop.hide();
                    e.preventDefault();
                    e.stopPropagation();
                });

                /* 第二步 构建数据列表等 */
                CD['levy'].initPopDom();
                that.e.find('#levy_lefttime').children('span').text(res.times + '/50');
                //player.info.levy.times = parseInt(res.times);
                //if(player.info.levy.times <= 0) {
                //    res.cd.cd = 0;
                //}
                if (res.cd.cd > 0) { //清CD
                    CD['levy'].update(res.cd.cd);
                    //xGame.updateCd('levy', res.cd.cd);
                    //that.countDown(res.cd.cd);
                    if (res.cd.status == 1) {
                        that.e.find('#levy_timer_span').addClass('cd');
                    }
                }
                that.e.find('#levy_force_cost').html(lang('cost_%s_gold', [res.gold])); //强征花费
                that.force_gold = res.gold;

                /* 绑定事件 */
                that.e.undelegate('.levy_result_wrap,.treasuremap_complete_bg,.treasuremap_complete', 'webkitAnimationEnd').delegate('.levy_result_wrap,.treasuremap_complete_bg,.treasuremap_complete', 'webkitAnimationEnd',
                function() {
                    $(this).remove();
                });
                that.e.find('#levy_normal_btn').unbind('touchend').bind('touchend',
                function() {
                    that.normalLevy();
                });
                that.e.find('#levy_force_btn').unbind('touchend').bind('touchend',
                function() {
                    that.forceLevy();
                });
                that.e.find('#levy_timer_wrap').unbind('touchend').bind('touchend',
                function() {
                    that.qzFunc();
                });
                /*
                that.e.find('#levy_cleantime_btn').bind('touchend', function() {
                    that.cleanTimerConfirm();
                });
                */

                /* 第四步 展示弹窗 */
                that.e.show();
                $('#worldloading').hide();

                /* 新手引导 */
                xGame.showGuide([1402]);

            };
            loadContents({
                'c': 'city',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        qzFunc: function() {
            var golds = Math.ceil(CD['levy'].s / 600);
            xGame.confirm.show(lang('notice'), lang('levy_clear_ask', [golds]), 2,
            function() {
                xGame.confirm.hide(function() {
                    var cleanRender = function(res) {
                        if (res.status == 1) {
                            player.info.levy.status = 0; //设置状态
                            CD['levy'].update(0);
                            //xGame.toast.show('征收CD清除成功');
                            player.updateinfo({
                                'gold': (parseInt(res.all_gold))
                            });
                        } else if (res.status == -1) {
                            xGame.toast.show(lang('on_need_to_clear_levy_cd'));
                        } else if (res.status == -2) {
                            xGame.goPay(lang('no_gold_to_clear_levy_cd'));
                        } else {
                            xGame.toast.show(lang('clear_cd_fail'));
                        }
                        cleanRender = null;
                    };
                    loadContents({
                        'c': 'city',
                        'm': 'clear_cd',
                        'type': '1'
                    },
                    cleanRender);
                    cleanRender = null;
                });
            },
            function() {
                xGame.confirm.hide();
            });
        },

        /* 普通征收 */
        normalLevy: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            if (that.timeseconds >= 60 * 30) {
                xGame.unlock();
                //xGame.toast.show('征收前，请先清除CD！');
                that.qzFunc();
                return;
            }

            var render = function(res) {
                if (res.status == 1) {
                    player.taskaction(2);
                    /* 更新页面 */
                    that.e.find('#levy_lefttime').children('span').text(res.levytimes + '/50');
                    player.info.levy.times = res.levytimes;
                    //if(player.info.levy.times <= 0) {//没有征收次数，直接隐藏CD
                    //    CD['levy'].update(0);
                    //} else {
                    CD['levy'].update(res.cd);
                    //}
                    player.updateinfo({
                        'silver': parseInt(res.all_silver)
                    });
                    /* pop */
                    that.createResult(1, res.silver, res.addition);
                    /* 新手引导 */
                    xGame.showGuide([1403, 1404, 1405, 1406, 1407]);

                    if ($.isArray(res.treasuremap) == false) {
                        var type = '';
                        if (parseInt(res.treasuremap.type) == 1) {
                            type = lang('part1');
                        } else if (parseInt(res.treasuremap.type) == 2) {
                            type = lang('part2');
                        } else {
                            type = lang('part3');
                        }
                        var html = '<div class="treasuremap_complete"></div>';
                        html += '<div class="treasuremap_complete_bg"><em class="desc' + res.treasuremap.quality + '">' + res.treasuremap.name + '(' + type + ')</em></div>';
                       
                        that.e.append(html);
                    }
                    if (typeof exercisepop != 'undefined' && exercisepop.e != null) {
                        exercisepop.buildtimes(0, parseInt(res.exercisetimes));
                    }
                } else if (res.status == -1) {
                    xGame.toast.show(lang('today_times_is_use_up'));
                } else if (res.status == -2) {
                    //xGame.toast.show('征收前，请先清除CD！');
                    that.qzFunc();
                    that.e.find('#levy_timer').children('span').addClass('cd');
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'city',
                'm': 'impose',
                'e': this.frome
            },
            render);
            render = null;
        },

        /* 强征 */
        forceLevy: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var render = function(res) {

                if (res.status == 1) {
                    player.taskaction(2);
                    player.updateinfo({
                        'silver': parseInt(res.all_silver),
                        'gold': parseInt(res.all_gold)
                    });
                    that.e.find('#levy_force_cost').html(lang('cost_%s_gold', [res.gold]));
                    that.force_gold = res.gold;
                    that.createResult(2, res.silver, res.addition);
                    if ($.isArray(res.treasuremap) == false) {
                        var type = '';
                        if (parseInt(res.treasuremap.type) == 1) {
                            type = lang('part1');
                        } else if (parseInt(res.treasuremap.type) == 2) {
                            type = lang('part2');
                        } else {
                            type = lang('part3');
                        }

                        var html = '<div class="treasuremap_complete"></div>';
                        html += '<div class="treasuremap_complete_bg"><em class="desc' + res.treasuremap.quality + '">' + res.treasuremap.name + '(' + type + ')</em></div>';
                       
                        that.e.append(html);
                    }
                    if (typeof exercisepop != 'undefined' && exercisepop.e != null) {
                        //整军演武礼包数
                        exercisepop.buildtimes(0, parseInt(res.exercisetimes));
                    }
                    //xGame.toast.show('强征成功！获得' + res.silver + '银币');
                } else if (res.status == -1) {
                    xGame.goPay(lang('no_gold_to_levy'));
                }
                xGame.unlock();

            };
            loadContents({
                'c': 'city',
                'm': 'impress',
                'e': this.frome
            },
            render);
            render = null;
        },

        /* 显示征收结果 */
        createResult: function(type, silver, addition) {
            var text = '';
            if (parseInt(player.info.level) > 20 && parseInt(addition) > 0) {
                text = lang('addition', [addition]);
            }
            var html = '<div class="levy_result_wrap">' + (type == 2 ? lang('heavy_levy_get') : lang('levy_get')) + '<span>' + silver + '</span>' + text + '</div>';
            this.e.find('#levypop_wrap').append(html);
        },

        unbindAll: function() {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.undelegate('.levy_result_wrap,.treasuremap_complete_bg,.treasuremap_complete', 'webkitAnimationEnd');
            this.e.find('#levy_normal_btn').unbind('touchend');
            this.e.find('#levy_force_btn').unbind('touchend');
            this.e.find('#levy_timer_wrap').unbind('touchend');
        },

        hide: function() {
            xGame.unlock();
            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {//如果可以
                window.plugins.localNotification.cancel('levy');
                if (CD['levy'] != undefined && CD['levy'].s > 300) {//如果有最近的训练中的武将
                    var time = Math.round(new Date().getTime()/1000) + CD['levy'].s;//当前时间加上cd时间
                    window.plugins.localNotification.add({
                        date: time, // your set date object
                        message: lang('levy_clock_end'),
                        badge: 1,
                        id: 'levy',
                        sound:'sub.caf'
                    });
                }
            }
            CD['levy'].recyclePopDom();
            //刷新整军演武
            if (typeof exercisepop != 'undefined' && exercisepop.e != null) {
                exercisepop.refreshPage();
            }

            this.isshow = false;
            this.data = null;
            if (this.t != null) { //清定时器
                clearInterval(this.t);
                this.t = null;
            }
            this.timeseconds = 0;
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
        },
    };
})();