/* 威震天下 */
(function() {
    smashPopClass = function() {
        this.e;
        this.isshow = false;

        this.mainDom;
    };
    smashPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                if (res.status == -1) {
                    xGame.toast.show(lang('lv_not_enough'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('get_user_info_fail'));
                } else if (res.status == -3) {
                    xGame.toast.show(lang('get_expostulation_list_fail'));
                } else if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
                $('#worldloading').hide();
            };

            loadContents({
                'c': 'smash',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        initFirst: function(res) {
            var that = this;
            
            that.e = xGame.loadPopTmpl('smash');
            that.mainDom = that.e.find('#smashpop_main');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                smashpop.hide();
                e.preventDefault();
            });
            
            var html = '';
            for (var i = 0;i < res.list.length;i ++) {
                html += '<li>';
                html += '<span class="s1">' + res.list[i].get + '</span>';
                html += '<span class="s2">x' + res.list[i].sub + '</span>';
                html += '<button class="btn pop_btn_middle' + (res.isuse == 0 ? '' : '_disable') + '" bid="' + res.list[i].id + '">' + res.list[i].name + '</button>';
                html += '</li>';
            }
            that.mainDom.html(html);
            html = null;
            
            that.mainDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) {
                    return false;
                }
                that.actionFunc($(this).attr('bid'));
            });
            
            that.showPrestige();
            
            that.e.show();
            
        },
        
        showPrestige: function() {
            this.e.find('#current_prestige').html(player.info.reputation);
        },

        actionFunc: function(id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case - 2 : xGame.toast.show(lang('canshu_lost'));
                    break;
                case - 3 : xGame.toast.show(lang('get_user_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('smashpop_get_task_info_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('smashpop_had_use_today'));
                    break;
                case - 6 : xGame.goPay(lang('gold_not_enough'));
                    break;
                case - 7 : xGame.toast.show(lang('smashpop_add_fail'));
                    break;
                case 1:
                    that.mainDom.find('button').attr('class', 'btn pop_btn_middle_disable');
                    //暴击
                    if (res.rate == 0) {
                        xGame.toast.show(lang('smashpop_you_get', [res.add]));
                    } else {
                        xGame.toast.show('<span class="reward_crit rc' + res.rate + '">' + lang('smashpop_you_get', [res.add]) + '</span>');
                    }
                    
                    player.updateinfo({
                        'reputation' : res.reputation,
                        'gold' : res.all_gold
                    });
                    that.showPrestige();
                    
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'smash',
                'm': 'action',
                'id': id
            },
            Func);
            Func = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            
            if (this.mainDom) {
                this.mainDom.undelegate('.pop_btn_middle', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
            if (this.e) {            
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            
        },
    };
})();