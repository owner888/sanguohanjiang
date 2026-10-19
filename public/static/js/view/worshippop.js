/* 活动 */
(function() {
    worshipPopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom;
        this.rightDom;
        this.times;
        this.isturning; //是否正在旋转

    };
    worshipPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    if (res.status == -1) {
                        xGame.toast.show(lang('activity_closed'));
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('get_activity_info_fail'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'flop',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        initFirst: function(res) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('worship');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                worshippop.hide();
                e.preventDefault();
                e.stopPropagation();
                
            });

            that.leftDom = that.e.find('#worship_l');
            that.rightDom = that.e.find('#worship_r').find('ul');
            that.times = res.times;

            that.fillLeft(res.info);
            that.showLeftTime(res.times);
            xGame.updateCd('worship', res.cd);
            that.isturning = false;

            that.bindAll();

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();

        },

        fillLeft: function(data) {
            //this.leftDom.find('#worship_coin').html(data.silver);
            this.leftDom.find('#worship_tfcard').html(data.leap);
            this.leftDom.find('#worship_gold').html(data.gold);
            this.leftDom.find('#worship_act').html(data.act);
        },

        showLeftTime: function(times) {
            this.e.find('#worship_r').find('p').html(lang('left_gp_times') + times);
        },

        bindAll: function() {
            var that = this;
            that.rightDom.undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.isturning) { //是否在旋转
                    return false;
                }
                if ($(this).hasClass('flip')) {
                    that.rightDom.find('li').html('').removeClass('flip');
                    return false;
                }
                if (parseInt(that.times) <= 0) {
                    xGame.toast.show(lang('no_times_to_gp'));
                    return false;
                }
                if (CD['worship'].s <= 0) {
                    xGame.toast.show(lang('activity_end'));
                    return false;
                }

                that.flipFunc($(this));
            });

            that.e.undelegate('#worship_r', 'touchend').delegate('#worship_r', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.isturning) { //是否在旋转
                    return false;
                }
                if (that.rightDom.find('li').eq(0).hasClass('flip')) {
                    that.rightDom.find('li').html('').removeClass('flip');
                    return false;
                }
            });

        },

        flipFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case - 2 : xGame.toast.show(lang('get_activity_info_fail'));
                    break;
                case 1:
                    that.times = res.times;
                    that.showLeftTime(res.times);
                    that.isturning = true;
                    var target;
                    that.flipAnimate(obj, res.reward.type, res.reward.name, res.reward.num, null,
                    function() {
                        for (var i = 0; i < res.list.length; i++) {
                            if (res.list[i].isown != 1) {
                                target = that.rightDom.find('#card' + (i + 1));
                                that.flipAnimate(target, res.list[i].type, res.list[i].name, res.list[i].num, res.list[i].isown, null, false);
                            }
                        }
                    },
                    null, res.reward.multiply);
                    target = null;

                    if (res.info.gold != undefined) {
                        player.updateinfo({
                            'gold': parseInt(res.info.gold)
                        });
                    } else if (res.info.act != undefined) {
                        player.updateinfo({
                            'act': parseInt(res.info.act)
                        });
                    }
                    if (res.info.silver != undefined) {
                        player.updateinfo({
                            'silver': parseInt(res.info.silver)
                        });
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();

            };
            loadContents({
                'c':
                'flop',
                'm': 'turnover',
                'id': obj.attr('card')
            },
            Func);
            Func = null;

        },

        flipAnimate: function(obj, type, name, num, isown, callback, isturning, multiply) {
            var that = this;
            if (type != 2) { //除银币外
                name = name + 'x';
            }
            obj.attr('class', 'card_pop_anim1').one('webkitAnimationEnd',
            function() {
                obj.html((isown == 0 ? '': '<em></em>') + '<img src="' + staticUrl + 'img/pop/worshippop_type' + type + '.png' + '" /><span' + (name.length > 5 ? ' class="sp"' : '') + '>' + name + num + '</span>');
                obj.attr('class', 'card_pop_anim2 flip').one('webkitAnimationEnd',
                function() {
                    obj.removeClass('card_pop_anim2');
                    if (callback) {
                        setTimeout(callback, 400);
                    }
                    if (isturning != undefined) {
                        that.isturning = isturning;
                    } else { //没有参数代表是第一次
                        if (appLanguage == 'kr') {//韩文做语序调整
                            xGame.toast.show((multiply == 2 ? '<span class="reward_crit rc' + multiply + '">': '') + lang('you_get', [(type < 4 ? '<span class="add_' + (type == 1 ? 'gold': (type == 2 ? 'silver': 'junling')) + '"></span>&nbsp;x': name) + num]));
                        } else {
                            xGame.toast.show((multiply == 2 ? '<span class="reward_crit rc' + multiply + '">': '') + lang('you_get') + (type < 4 ? '<span class="add_' + (type == 1 ? 'gold': (type == 2 ? 'silver': 'junling')) + '"></span>&nbsp;x': name) + num);
                        }
                    }
                });
            });
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('worship', 0);
            if (this.rightDom) {
                this.rightDom.undelegate('li', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
            }
            if (this.leftDom) {
                this.leftDom.remove();
                this.leftDom = null;
            }
            
            this.times = this.isturning = null;
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.undelegate('#worship_r', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },
    };
})();