/* 神将 */
(function() {
    godwillPopClass = function() {
        this.e;
        this.isshow = false;
        this.list;
    };

    godwillPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            //请求数据
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('get_off_price_fail'));
                    $('#worldloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('get_jade_info_fail'));
                    $('#worldloading').hide();
                    break;
                case - 1 : xGame.toast.show(lang('godwill_close'));
                    $('#worldloading').hide();
                    break;
                case 0:
                    xGame.toast.show(lang('godwill_enter_later'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    that.preparePage();
                    that.buildpage(res);
                    break;
                }
            };

            loadContents({
                'c':
                'general_onsale',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;

        },

        /* 初始化界面 */
        preparePage: function() {
            var that = this;

            if (that.e != null) {
                return false;
            }

            that.e = xGame.loadPopTmpl('godwill');

            //绑定关闭事件
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                godwillpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            $('#worldloading').hide();
        },

        /*组装数据*/
        buildpage: function(res) {
            var that = this;

            var heroimg = 'http://img2.hanjiangsanguo.com/hjimg/pop/godwillpop_bg'+res.version+'.' + appLanguage + '.jpg';

            that.e.find('.info').css('background', 'url(' + heroimg + ')');
            //倒计时
            xGame.updateCd('godwill', res.cd);

            var general = [];
            //宝石数据
            for (var i = 1; i <= 3; i++) {
                that.e.find('#jade' + i).find('span').html(res.jade[i]);
            }
            //将领数据
            for (var i = 0; i < res.list.length; i++) {
                var pos_id = res.list[i].position;
                that.e.find('.content').find('.info').find('.info' + pos_id).attr('gid', res.list[i].generalid).attr('site', pos_id);
                that.e.find('.content').find('.info').find('.generalinfo' + pos_id).attr('gid', res.list[i].generalid).attr('site', pos_id);
                //绑定资料按钮
                that.e.find('.content').find('.info').find('.info' + pos_id).unbind('touchend').bind('touchend',
                function() {
                    that.buildinfo($(this).attr('gid'));
                    if ($(this).attr('site') == 1) {
                        that.e.find('#general_info').css({
                            'left': '15px'
                        });
                    } else if ($(this).attr('site') == 3) {
                        that.e.find('#general_info').css({
                            'left': '634px'
                        });
                    } else {
                        that.e.find('#general_info').css({
                            'left': '333px;'
                        });
                    }
                });
                that.e.find('.content').find('.info').find('.generalinfo' + pos_id).unbind('touchend').bind('touchend',
                function() {
                    that.buildinfo($(this).attr('gid'));
                    if ($(this).attr('site') == 1) {
                        that.e.find('#general_info').css({
                            'left': '15px'
                        });
                    } else if ($(this).attr('site') == 3) {
                        that.e.find('#general_info').css({
                            'left': '634px'
                        });
                    } else {
                        that.e.find('#general_info').css({
                            'left': '333px;'
                        });
                    }
                });

                //绑定购买按钮
                if (parseInt(res.list[i].isown) == 0) {
                    that.e.find('.content').find('.info').find('.get' + pos_id).attr('gid', res.list[i].generalid);
                    that.e.find('.content').find('.info').find('.get' + pos_id).unbind('touchend').bind('touchend',
                    function() {
                        var obj = $(this);
                        xGame.confirm.show(lang('notice'), lang('buy_hero_ask2'), 2,
                        function() {
                            that.buy(obj);
                            xGame.confirm.hide();
                        },
                        function() {
                            xGame.confirm.hide();
                        });       
                    });
                } else {
                    that.e.find('.content').find('.info').find('.get' + pos_id).unbind('touchend').addClass('pop_btn_middle_disable').removeClass('pop_btn_middle').html(lang('hero_had_buy'));
                }

                that.e.find('.content').find('.info').find('.pay' + pos_id).html('<p>' + lang('original_price') + '<span class="gem' + res.list[i].paytype + '">' + res.list[i].price / 0.8 + '</span></p><p class="now">' + lang('now_price') + '<span class="gem' + res.list[i].paytype + '"><em>' + res.list[i].price + '</em></span></p><div class="red_line"></div>');
                general[res.list[i].generalid] = res.list[i];
            }

            that.list = general;
        },

        /*单个信息*/
        buildinfo: function(gid) {
            var that = this;
            var info = that.list[gid];
            that.e.find('.content').find('#general_info').find('.close_btn').unbind('touchend').bind('touchend',
            function() {
                that.e.find('.content').find('#general_info').hide();
            });

            that.e.find('#general_info').find('#name').html(info.info.name);
            that.e.find('#general_info').find('#wuli').html(info.info.wuli);
            that.e.find('#general_info').find('#zili').html(info.info.zhili);
            that.e.find('#general_info').find('#tili').html(info.info.tili);
            that.e.find('#general_info').find('#bar_hero_star').css({
                'width': 18 * parseInt(info.info.star) + 'px'
            });
            that.e.find('#general_info').find('#skill').html(info.info.skill.name + ':' + info.info.skill.description);
            that.e.find('#general_info').find('#talent').html(info.info.talent.name + ':' + info.info.talent.description);

            that.e.find('.content').find('#general_info').show();
        },

        /*购买*/
        buy: function(obj) {
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('add_hero_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('add_log_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('deduct_jade_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_cost_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 3:
                    xGame.toast.show(lang('jade_not_enough_for_hero'));
                    break;
                case 2:
                    xGame.toast.show(lang('you_had_hero'));
                    break;
                case 0:
                    xGame.toast.show(lang('buy_hero_later'));
                    break;
                case 1:
                    obj.unbind('touchend').addClass('pop_btn_middle_disable').removeClass('pop_btn_middle').html(lang('hero_had_buy'));
                    xGame.doAnimate2(that.e.find('#jade' + res.type).find('span'), 'changcolor', 100, 7,
                    function() {
                        that.e.find('#jade' + res.type).find('span').html(res.jade);
                    });
                    miscpop.newhero(res.general);
                    if (typeof CD['chat'] != 'undefined') {
                        CD['chat'].read();//手动请求一次，为了早点广播
                    }
                    break;
                }
            };
            loadContents({
                'c': 'general_onsale',
                'm': 'buy',
                'generalid': obj.attr('gid')
            },
            Func); //加载数据
            Func = null;
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('godwill', 0);
            this.list = null;
            if (this.e) {
                this.e.find('.content').find('.info').unbind('touchend');
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('.content').find('#general_info').find('.close_btn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },
    };
})();