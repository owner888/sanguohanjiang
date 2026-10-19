/* 礼品 */
(function() {
    giftPopClass = function() {
        this.e;
        this.isshow = false;
        this.isTimeout = [];
    };
    giftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            that.e = xGame.loadPopTmpl('gift');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                giftpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.displayGift(player.info.gift);

            /* 第四步 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            if (xGame.guide == true && guide.now == 65) {
                that.isTimeout.push(setTimeout(function() {
                    var giftpop = $('#giftpop');
                    giftpop.find('.hidebtn').hide();
                    guide.create('touchend', giftpop.find('#fetch_btn'));
                    giftpop = null;
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
            that.e.find('.title1').attr('class', 'title1 giftid' + gift.id);
            that.e.find('.p1').html(gift.description);
            that.e.find('.p2').attr({
                'class': 'p2 type' + gift.type
            });
            that.e.find('.p2').children('span').html(helper.getLevelRewardType(gift.type) + '+' + gift.num);
            if (gift.open == 1 || parseInt(player.info.level) >= parseInt(gift.level)) {
                that.e.find('.pop_btn_middle').text(lang('receive')).removeClass('disabled').unbind('touchend').bind('touchend',
                function() {
                    if (xGame.checkLock()) {
                        return false;
                    }

                    $(this).unbind('touchend');

                    var fetchRender = function(res) {
                        if (res.status == 1) {
                            xGame.toast.show(lang('receive_complete'));
                            that.e.find('.pop_btn_middle').addClass('disabled');
                            //更新用户数据
                            var update = {};
                            if (gift.type == 1) {
                                update.gold = parseInt(player.info.gold) + parseInt(gift.num);
                            } else if (gift.type == 2) {
                                update.silver = parseInt(player.info.silver) + parseInt(gift.num);
                            } else if (gift.type == 3) {
                                update.act = parseInt(player.info.act) + parseInt(gift.num);
                            }
                            player.updateinfo(update);
                            update = null;

                            //更新用户礼包数据
                            player.info.gift = res.next ? res.next: null;
                            //player.showLevelReward(player.info.gift);

                            if (player.info.gift) {
                                that.isTimeout.push(setTimeout(function() {
                                    xGame.unlock();
                                    if (xGame.guide == true && guide.now == 65) {
                                        guide.hide();
                                        giftpop && giftpop.hide();
                                        guide.showNpc(lang('front_msg'),
                                        function() {
                                            guide.show(66);
                                        });
                                    } else {
                                        giftpop.displayGift(player.info.gift); //下个礼包
                                    }
                                },
                                200));
                            } else {
                                xGame.unlock();
                                giftpop.displayGift(null);
                            }
                        } else {
                            xGame.unlock();
                            xGame.toast.show(lang('reward_get_fail'));
                        }
                    };
                    loadContents({
                        'c': 'member',
                        'm': 'open_gift',
                        'id': gift.id
                    },
                    fetchRender);
                    fetchRender = null;
                });
            } else {
                that.e.find('.pop_btn_middle').text(lang('lv_not_touch')).addClass('disabled').unbind('touchend').bind('touchend',
                function() {
                    xGame.toast.show(lang('have_not_reach_reward'));
                    return false;
                });
            }
            xGame.unlock();
        },

        unbindAll: function () {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('.pop_btn_middle').text(lang('receive')).removeClass('disabled').unbind('touchend');
            this.e.find('.pop_btn_middle').text(lang('lv_not_touch')).addClass('disabled').unbind('touchend');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
        },
    };
})();