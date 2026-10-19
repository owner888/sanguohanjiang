/* 对酒 */
(function() {
    drinkPopClass = function() {
        this.e;
        this.isshow = false;

        this.topDom;
        this.bottomDom;
    };
    drinkPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                if (res.status == -1) {
                    xGame.toast.show(lang('get_drink_list_fail'));
                } else if (res.status == 0) {
                    xGame.toast.show(lang('undefined_error'));
                } else if (res.status == 1 || res.status == 2) {

                    that.e = xGame.loadPopTmpl('drink');
                    that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        drinkpop.hide();
                        e.preventDefault();
                        e.stopPropagation();
                    });
                    that.initFirst(res);
                    that.e.show();
                }
                $('#worldloading').hide();
            };

            loadContents({
                'c': 'drink',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        initFirst: function(res) {
            var that = this;

            that.topDom = that.e.find('#drinkpop_top');
            that.bottomDom = that.e.find('#drinkpop_bottom');

            var html1 = '',
            html2 = '';

            for (var i = 0; i < res.list.length; i++) {
                html1 += '<li>' + lang('cost_%m_get_%s_act', [res.list[i].type, res.list[i].price, res.list[i].act, res.list[i].id]) + '</li>';
            }
            that.bottomDom.find('ul').html(html1);
            if (res.status == 1) {
                that.bottomDom.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend').delegate('button.pop_btn_middle', 'touchend',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    that.drinkFunc($(this).attr('type'));
                });
            } else {
                that.bottomDom.find('ul').find('li').find('button').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
            }

            for (var j = 0; j < res.log.length; j++) {
                html2 += '<li><p>' + lang('%name_drink_get_act', [res.log[j].nickname, res.log[j].type, res.log[j].act]) + '</p></li>';
            }
            that.topDom.find('ul').html(html2);

        },

        drinkFunc: function(type) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_activity_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('drink_times_not_enough'));
                    break;
                case - 3 : xGame.toast.show(lang('get_drink_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('deduct_silver_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case 3:
                    xGame.goPay(lang('gold_not_enough_for_drinking'));
                    break;
                case 2:
                    xGame.toast.show(lang('silver_not_enough'));
                    break;
                case 1:
                    that.bottomDom.find('ul').find('li').find('button').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                    player.updateinfo({
                        'silver':
                        parseInt(res.reward.silver),
                        'act': parseInt(res.reward.act),
                        'gold': parseInt(res.reward.gold)
                    });
                    if (res.log.type != 1) {
                        if (that.topDom.find('ul').find('li').length == 7) {
                            that.topDom.find('ul').find('li').eq(6).remove();
                        }
                        that.topDom.find('ul').prepend('<li><p>' + lang('%name_drink_get_act', [res.log.nickname, res.log.type, res.log.act]) + '</p></li>');
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
                'drink',
                'm': 'go_drink',
                'type': type
            },
            Func);
            Func = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.bottomDom) {
                this.bottomDom.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend');
                this.bottomDom.remove();
                this.bottomDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            
            this.isshow = false;
            if (this.topDom) {
                this.topDom.remove();
                this.topDom = null;
            }
            this.unbindAll();
        },
    };
})();