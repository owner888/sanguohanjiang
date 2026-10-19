/* 国师授艺 */
(function() {
    countrylearnPopClass = function() {
        this.e;
        this.isshow = false;
    };

    countrylearnPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                    switch(parseInt(res.status)) {
                        case 1:
                            that.initFirst(res);
                            break;
                        case -1:
                            xGame.toast.show(lang('countrylearn_not_join_country'));
                            break;
                        case -2:
                            xGame.toast.show(lang('countrylearn_enter_error'));
                            break;
                        case -3:
                            xGame.toast.show(lang('countrylearn_get_info_error'));
                            break;
                        case 2:
                            xGame.toast.show(lang('countrylearn_lack_level'));
                            break;
                        default:
                            xGame.toast.show(lang('countrylearn_not_enter'));
                            break;
                    }
                    $('#worldloading').hide();
            };
            loadContents({
                'c': 'country',
                'm': 'learn_index'
            },
            initRender, true);
            initRender = null;

        },

        /* 初始化界面 */
        initFirst: function(data) {

            var that = this;

            if (that.e != null) {
                return false;
            }

            that.e = xGame.loadPopTmpl('countrylearn');

            //绑定关闭事件
            that.e.find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countrylearnpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            //列表
            that.buildlist(data.list);
            //次数
            that.buildtimes(data.times);
            //学艺事件绑定
            that.e.find('#info').find('.item').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function() {
                that.learn($(this).attr('itemid'));
            });
            //经验条
            var width = 10;
            //经验条次数对应等级
            for(var i = 1; i <= 5; i++) {
            if(parseInt(data.all_times) > 1) {
                    if(parseInt(data.all_times) >= i) {
                        width = 112 * (i - 1);
                        if (parseInt(data.level) - data.rule[i] > 0) {
                            width = width + (parseInt(data.level) - parseInt(data.rule[i])) * (112 / (data.rule[i] - data.rule[i - 1]));
            }
                    }
                }
                that.e.find('#info').find('.level' + i).html(data.rule[i]);
            }
            that.e.find('#info').find('#exp').css({'width':width+'px'});
            if(parseInt(data.all_times) == 5) {
                that.e.find('#info').find('#exp').css({'border-radius':'18px'});
                that.e.find('#info').find('#exp').find('em').remove();
            }
            that.e.show();

            $('#worldloading').hide();
            that.isshow = true;
        },

        buildtimes: function(times) {
            var that = this;
            if(parseInt(times) > 0) {
                that.e.find('.item').find('.pop_btn_middle').html(lang('countrylearn_study') + ' x'+ times);
            } else {
                that.e.find('.item').find('.pop_btn_middle').attr('class', 'btn pop_btn_middle_disable').html(lang('countrylearn_study'));
            }

        },

        /*列表数据*/
        buildlist: function(data) {
            var that = this;
            for(var i = 0; i < data.length; i++) {
                var dom = that.e.find('.item'+(i+1));
                dom.find('.exp').html('+' + data[i].exp);
                dom.find('.sub').find('em').html(data[i].gold);
                dom.find('.pop_btn_middle').attr('itemid', data[i].id);
            }
        },

        /*学艺*/
        learn: function(itemid) {
            var that = this;

            if(xGame.checkLock()) {
                return false;
            }

            var initRender = function(res) {
                    switch(parseInt(res.status)) {
                        case 1:
                            xGame.toast.show(lang('countrylearn_study_ok', [res.addexp]));
                            player.updateinfo({
                                'gold':res.gold
                            });
                            //次数
                            that.buildtimes(res.times);
                            break;
                        case -1:
                            xGame.toast.show(lang('countrylearn_not_join_country'));
                            break;
                        case -2:
                            xGame.toast.show(lang('countrylearn_enter_error'));
                            break;
                        case -3:
                            xGame.toast.show(lang('get_country_member_fail'));
                            break;
                        case -4:
                            xGame.toast.show(lang('countrylearn_get_info_error'));
                            break;
                        case -5:
                            xGame.toast.show(lang('countrylearn_get_general_error'));
                            break;
                        case -6:
                            xGame.toast.show(lang('countrylearn_sub_gold_error'));
                            break;
                        case -7:
                            xGame.toast.show(lang('countrylearn_sub_times_error'));
                            break;
                        case 2:
                            xGame.toast.show(lang('countrylearn_lack_level'));
                            break;
                        case 3:
                            xGame.toast.show(lang('countrylearn_lack_gold'));
                            break;
                        case 4:
                            xGame.toast.show(lang('countrylearn_lack_times'));
                            break;
                        case 5:
                            xGame.toast.show(lang('countrylearn_not_general'));
                            break;
                        default:
                            xGame.toast.show(lang('countrylearn_not_enter'));
                            break;
                    }
                    xGame.unlock();
            };
            loadContents({
                'c': 'country',
                'm': 'learn',
                'type': itemid
            },
            initRender);
            initRender = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.find('#info').find('.item').undelegate('.pop_btn_middle', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.unbindAll();         
        },
    };
})();