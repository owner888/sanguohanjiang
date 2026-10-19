/* 主线任务 */
(function() {
    taskgiftPopClass = function() {
        this.e;
        this.isshow = false;
        this.isTimeout = [];
    };
    taskgiftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            that.e = xGame.loadPopTmpl('taskgift');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                taskgiftpop.hide();
                e.preventDefault();
            });

            that.displayGift(player.info.gift);

            /* 第四步 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            if (xGame.guide == true && guide.now == 65) {
                this.isTimeout.push(setTimeout(function() {
                    var taskgiftpop = $('#taskgiftpop');
                    taskgiftpop.find('.hidebtn').hide();
                    guide.create('touchend', taskgiftpop.find('#fetch_btn'));
                    taskgiftpop = null;
                },
                300));
            }
        },

        displayGift: function(gift) {
            var that = this;

            //检查并设置属性
            if (xGame.checkLock()) {
                return false;
            }

            if (gift == undefined || gift == null || gift == 0) {
                that.hide();
                xGame.unlock();
                //xGame.toast.show('已经没有礼包了！');
                return false;
            }
            if (that.e == null) {
                xGame.unlock();
                return;
            }
            that.e.find('.title').html(gift.title);
            that.e.find('.description').html(gift.description);
            if (gift.description.length > 22) {
                that.e.find('.description').addClass('sp');
            } else {
                that.e.find('.description').removeClass('sp');
            }
            if (gift.type == 1) {
                that.e.find('.reward').find('.gold').css({
                    'background': 'url(' + staticUrl + 'img/common/header_gold.png) 0 0 no-repeat'
                });
            } else if (gift.type == 2) {
                that.e.find('.reward').find('.gold').css({
                    'background': 'url(' + staticUrl + 'img/common/header_money.png) 0 0 no-repeat'
                });
            } else if (gift.type == 3) {
                that.e.find('.reward').find('.gold').css({
                    'background': 'url(' + staticUrl + 'img/common/header_junling.png) 0 0 no-repeat'
                });
            } else if (gift.type == 4) {
                that.e.find('.reward').find('.gold').css({
                    'background': 'url(' + staticUrl + 'img/pop/pop_gem1.png) 0 0 no-repeat'
                });
            } else if (gift.type == 5) {
                that.e.find('.reward').find('.gold').css({
                    'background': 'url(' + staticUrl + 'img/pop/pop_gem2.png) 0 0 no-repeat'
                });
            } else if (gift.type == 6) {
                that.e.find('.reward').find('.gold').css({
                    'background': 'url(' + staticUrl + 'img/pop/pop_gem3.png) 0 0 no-repeat'
                });
            }

            that.e.find('.reward').children('span').html(gift.num);

            if (gift.open == 1) {
                that.e.find('.pop_btn_middle').text(lang('receive')).removeClass('disabled').unbind('touchend').bind('touchend',
                function() {
                    if (xGame.checkLock()) {
                        return false;
                    }

                    $(this).unbind('touchend');

                    var fetchRender = function(res) {
                        if (res.status == 1) {
                            xGame.toast.show(lang('receive_complete'));
                            //that.e.find('.pop_btn_middle').addClass('disabled');
                            //更新用户数据
                            var update = {};
                            if (gift.type == 1) {
                                update.gold = parseInt(res.all_gold);
                            } else if (gift.type == 2) {
                                update.silver = parseInt(res.all_silver);
                            } else if (gift.type == 3) {
                                update.act = parseInt(res.all_act);
                            }
                            player.updateinfo(update);
                            update = null;

                            //更新用户礼包数据
                            player.info.gift = res.next ? res.next: null;
                            player.showLevelReward(player.info.gift);

                            if (player.info.gift) {
                                that.isTimeout.push(setTimeout(function() {
                                    xGame.unlock();
                                    if (xGame.guide == true && guide.now == 65) {
                                        guide.hide();
                                        taskgiftpop && taskgiftpop.hide();
                                        guide.showNpc(lang('front_msg'),
                                        function() {
                                            guide.show(66);
                                        });
                                    } else {
                                        taskgiftpop.displayGift(player.info.gift); //下个礼包
                                    }
                                },
                                200));
                            } else {
                                xGame.unlock();
                                taskgiftpop.displayGift(null);
                            }
                        } else {
                            xGame.unlock();
                            xGame.toast.show(lang('reward_get_fail'));
                        }
                    };
                    loadContents({
                        'c': 'member',
                        'm': 'open_gift'
                    },
                    fetchRender);
                });
            } else {
                that.e.find('.pop_btn_middle').text(lang('uncomplete')).addClass('disabled').unbind('touchend').bind('touchend',
                function() {
                    xGame.toast.show(lang('have_not_reach_reward'));
                    return false;
                });
            }
            xGame.unlock();
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.undelegate('#fetch_btn.pop_btn_middle', 'touchend');
                this.e.remove();
                this.e = null;
            }

            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            
        },
    };
})();