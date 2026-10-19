/* 祭祀 */
(function() {
    sacrificePopClass = function() {
        this.e;
        this.isshow = false;
        this.gemDom;
    };
    sacrificePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('get_activity_info_fail'));
                    player.hideActivity('sacrifice');
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 1:
                    that.e = xGame.loadPopTmpl('sacrifice');
                    that.gemDom = that.e.find('#gem_list');
                    that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        sacrificepop.hide();
                    });
                    xGame.updateCd('sacrifice', res.cd);
                    that.fillPrice(res.info);
                    that.showGem(res.jade);
                    that.bindAll();
                    that.e.show();
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                $('#worldloading').hide();
            };
            loadContents({
                'c':
                'sacrifice',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        fillPrice: function(data) {
            var that = this;
            for (var i = 1; i <= 3; i++) {
                that.e.find('#s_gem' + i + '_cost').html('x' + data[i]);
            }
        },

        bindAll: function() {
            var that = this;
            that.e.find('#sacrifice_gods').undelegate('button.pop_btn_middle', 'touchend').delegate('button.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.goSacrifice($(this).attr('type'));
            })
        },

        showGem: function(data) {
            for (var i = 1; i < 4; i++) {
                this.gemDom.find('#gem' + i).html(data[i]);
            }
        },

        goSacrifice: function(type) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    player.updateinfo({
                        "gold":
                        res.info.gold
                    });

                    that.e.find('#s_gem' + type + '_cost').html('x' + res.info.next);
                    if (res.info.num == 1) {
                        xGame.toast.show(lang('get') + '&nbsp;<span class="gem' + res.info.type + '">&nbsp;×&nbsp;' + res.info.num + '</span>');
                    } else {
                        xGame.toast.show('<span class="reward_crit rc' + res.info.num + '">' + lang('get') + '&nbsp;<span class="gem' + res.info.type + '">&nbsp;×&nbsp;' + res.info.num + '</span></span>');
                    }
                    that.showGem(res.jade);

                    break;
                case - 8 : xGame.toast.show(lang('update_activity_info_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('get_reward_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('get_sacrifice_reward_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('activity_info_get_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('sacrifice_type_illegal'));
                    break;
                case - 2 : xGame.toast.show(lang('activity_end'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_not_exist'));
                    break;
                case 2:
                    xGame.goPay(lang('no_gold_for_sacrifice'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'sacrifice',
                'm': 'go_sacrifice',
                'type': type
            },
            Func);
            Func = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;

            xGame.updateCd('sacrifice', 0);
            if (this.gemDom) {
                this.gemDom.remove();
                this.gemDom = null;
            }
            
            if (this.e) {
                this.e.find('#sacrifice_gods').undelegate('button.pop_btn_middle', 'touchend');
                this.e.find('.content').find('.hidebtn').unbind('touchend');
            
                this.e.remove();
                this.e = null;
            }
        }
    };
})();