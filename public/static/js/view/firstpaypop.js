/* 首充礼包 */
(function() {
    firstpayPopClass = function() {
        this.e;
        this.isshow = false;
        this.anim = null;
    };

    firstpayPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (res.status == '-1') {
                    xGame.toast.show(lang('you_had_receive_first_pay'));
                    $('#worldloading').hide();
                    return false;
                }

                that.e = xGame.loadPopTmpl('firstpay');

                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    firstpaypop.hide();
                    e.preventDefault();
                    e.stopPropagation();
                });

                //给充值按钮绑定事件
                that.e.find('.content').find('#iospay').unbind('touchend').bind('touchend',
                function() {
                    firstpaypop.hide();
                    xGame.enterPay();
                });

                if (res.status == '-2') {
                    that.e.find('.content').find('#get_gift').unbind('touchend').attr('id', 'get_gift_disable');
                } else {
                    //绑定领取事件
                    that.e.find('.content').find('#get_gift').unbind('touchend').bind('touchend',
                    function() {
                        //调用领取接口
                        that.get_gift();
                    });
                }
                

                that.e.show();
                $('#worldloading').hide();

                that.playAnim();
            };

            //加载数据
            loadContents({
                'c': 'vip',
                'm': 'get_vip_reward'
            },
            Func);
            Func = null;
        },

        get_gift: function() {
            var that = this;

            var Func = function(res) {
                if (res.status == -1) {
                    xGame.toast.show(lang('you_had_receive_first_pay'));
                    return false;
                }
                if (res.status == -2) {
                    xGame.toast.show(lang('you_have_not_pay_yet'));
                    return false;
                }
                if (res.status == 1) {
                    xGame.toast.show(lang('get_first_pay'));
                    //把领取按钮改成已领取
                    that.e.find('#get_gift').unbind('touchend').attr('id', 'get_gift_disable');
                    //添加银币
                    for (var i = 0; i < res.list.length; i++) {
                        if (res.list[i].get_type == 3) {
                            player.updateinfo({
                                silver: parseInt(res.all_silver)
                            });
                        }
                        if (res.list[i].get_type == 1) {
                            miscpop.newhero(res.list[i].general);
                        }
                    }
                    //按钮隐藏
                    $('#header_firstpay_btn_wrap').hide();
                }

            };

            loadContents({
                'c': 'vip',
                'm': 'get_vip_reward',
                't': '1'
            },
            Func);
            Func = null;
        },

        playAnim: function() {
            var that = this;

            var xy_config = {
                1 : [86, 379],
                2 : [212, 378],
                3 : [336, 378]
            };

            that.anim = [];
            for (var i = 1; i <= 3; i++) {
                //that.anim[i] = {};
                that.anim[i] = new movieClipClass({
                    "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                    "width": 93,
                    "height": 90,
                    "x": xy_config[i][0],
                    "y": xy_config[i][1],
                    "loop": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 100
                    },
                    {
                        rect: [93, 0],
                        interval: 100
                    },
                    {
                        rect: [186, 0],
                        interval: 100
                    },
                    {
                        rect: [279, 0],
                        interval: 100
                    },
                    {
                        rect: [0, 90],
                        interval: 100
                    },
                    {
                        rect: [93, 90],
                        interval: 100
                    },
                    {
                        rect: [186, 90],
                        interval: 100
                    },
                    ]
                });
                //开始播放
                that.anim[i].appendTo(that.e.find('.content'));
                that.anim[i].play();
            }
        },

        hideAnim: function() {
            if (!this.anim) {
                return;
            }
            for (var i = 1; i <= 3; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('.content').find('#iospay').unbind('touchend');
                this.e.find('.content').find('#get_gift').unbind('touchend');
                this.e.find('#get_gift').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.unbindAll();
            this.hideAnim();

        },
    };
})();