/* 国家祭祀 */
(function() {
    countryworshipPopClass = function() {
        this.e;
        this.isshow = false;

        this.topDom;
        this.bottomDom;
    };
    countryworshipPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                if (res.status == -1) {
                    that.exceptionFunc();
                } else if (res.status == -2) {
                    xGame.toast.show(lang('get_worship_list_fail'));
                } else if (res.status == 3) {
                    xGame.toast.show(lang('lv_50_open'));
                } else if (res.status == 0) {
                    xGame.toast.show(lang('undefined_error'));
                } else if (res.status == 1 || res.status == 2) {

                    that.e = xGame.loadPopTmpl('countryworship');
                    that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        countryworshippop.hide();
                        e.preventDefault();
                        e.stopPropagation();
                    });
                    that.initFirst(res);
                    that.e.show();
                    that.isshow = true;
                }
                $('#worldloading').hide();
            };

            loadContents({
                'c': 'countrysacrifice',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        initFirst: function(res) {
            var that = this;

            that.topDom = that.e.find('#countryworshippop_top');
            that.bottomDom = that.e.find('#countryworshippop_bottom');

            var html1 = '',
            html2 = '';

            for (var i = 0; i < res.list.length; i++) {
                html1 += '<li>';
                html1 += '<p class="countryworship_cost">' + lang('cost') + ':<span id="countryworship_cost" class="type' + res.list[i].type + '">' + res.list[i].price + '</span></p>';
                if (player.info.vip < res.list[i].need_vip) {
                    html1 += '<button class="btn pop_btn_middle_disable">' + lang('vip_open', [res.list[i].need_vip]) + '</button>';
                } else {
                    html1 += '<button class="btn pop_btn_middle" type="' + res.list[i].id + '">' + lang('worship') + '</button>';
                }

                html1 += '<p class="countryworship_get"><em class="y">' + lang('get') + '：</em><em class="w">' + lang('country_contribut') + '</em>' + res.list[i].get_value + '</p>';
                html1 += '</li>';
            }
            that.e.find('#times').html(lang('countryworship_times_limit', [res.now_times, res.all_times]));
            that.bottomDom.find('ul').html(html1);
            if (res.status == 1) {
                that.bottomDom.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend').delegate('button.pop_btn_middle', 'touchend',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    that.worshiFunc($(this).attr('type'));
                });
            } else {
                that.bottomDom.find('ul').find('li').find('button').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
            }

            for (var j = 0; j < res.log_list.length; j++) {
                html2 += lang('%name_through_%type_get_%contribut', [res.log_list[j].nickname, res.log_list[j].type, res.log_list[j].get_value]);
            }
            that.topDom.find('ul').html(html2);

        },

        worshiFunc: function(type) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : that.exceptionFunc();
                    break;
                case - 2 : xGame.toast.show(lang('had_worship_today'));
                    break;
                case - 3 : xGame.toast.show(lang('get_worship_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('silver_not_enough'));
                    break;
                case - 5 : xGame.toast.show(lang('gold_not_enough'));
                    break;
                case - 6 : xGame.toast.show(lang('worship_need_vip_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('worship_times_full'));
                    break;
                case - 8 : xGame.toast.show(lang('countrypop_contribution_limit'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_50_open'));
                    break;
                case 1:
                    that.bottomDom.find('ul').find('li').find('button').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                    player.updateinfo({
                        'silver':
                        parseInt(res.all_silver),
                        'gold': parseInt(res.all_gold)
                    });
                    if (res.type != 1) {
                        if (that.topDom.find('ul').find('li').length == 7) {
                            that.topDom.find('ul').find('li').eq(6).remove();
                        }
                        that.topDom.find('ul').prepend(lang('%name_through_%type_get_%contribut', [res.nickname, res.type, res.get]));
                    }
                    that.e.find('#times').html(lang('countryworship_times_limit', [res.now_times, res.all_times]));
                    xGame.toast.show(lang('worship_success'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'countrysacrifice',
                'm': 'action',
                'id': type
            },
            Func);
            Func = null;
        },

        /*异常处理：被提出国家时*/
        exceptionFunc: function() {
            player.info.country = 0;
            xGame.toast.show(lang('you_are_kicked_out'));
            if (this.isshow) {
                this.hide();
            }
            xGame.loadWorld('city');
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
            this.unbindAll();
            if (this.topDom) {
                this.topDom.remove();
                this.topDom = null;
            }
        },
    };
})();