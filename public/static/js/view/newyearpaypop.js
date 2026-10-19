/* 春节充值 */
(function() {
    newyearpayPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;

    };
    newyearpayPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (parseInt(res.status) == -1) {
                    xGame.toast.show(lang('activity_closed'));
                    $('#worldloading').hide();
                    return;
                } else {
                    that.buildpage(res);
                }
            }
            loadContents({
                'c': 'newyearpay',
                'm': 'pay_reward'
            },
            Func, true); //加载数据
            Func = null;
        },

        buildpage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('newyearpay');
            
            if (player.info.newyearpay_type == 1) {
                that.e.addClass('new');
            }

            that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                newyearpaypop.hide();
                e.preventDefault();
            });

            //组装界面数据
            xGame.updateCd('newyearpay', res.endtime);
            that.e.find('.now_pay').find('em').html(res.now_pay);
            var html = '';
            var firstitem_key = null;
            //1银币，2元宝，3蓝宝石，4金宝石，5紫宝石，6玉石，7元魂, 
            var type = {1:'silver',2:'gold', 3:'get1', 4:'get2', 5:'get3', 6:'jade', 7:'soul', 8: 'tfcard', 9: 'prestige', 11: 'lottery'};//世界杯活动添加兑奖券
            for(var i = 0; i < res.list.length; i++) {
                html += '<li>'+lang('pay_amount_the_full', [res.list[i].amount])+'<em class="reward '+type[res.list[i].gettype]+'">'+res.list[i].getvalue+'</em>';
                if(parseInt(res.list[i].status) == 1) {
                    //可领取
                    html += '<button class="btn pop_btn_small itemid'+res.list[i].id+'" itemid="'+res.list[i].id+'">'+lang('receive')+'</button></li>';
                    if(firstitem_key == null) {
                        firstitem_key = i;
                    }
                } else if(parseInt(res.list[i].status) == 2) {
                    //已领取
                    html += '<button class="btn pop_btn_small_disable is_get" itemid="'+res.list[i].id+'"></button></li>';
                } else {
                    //不可领取
                    html += '<button class="btn pop_btn_small_disable" itemid="'+res.list[i].id+'">'+lang('receive')+'</button></li>';
                    if(firstitem_key == null) {
                        firstitem_key = i;
                    }
                }
            }

            that.e.find('#newyearpay_list_scroller').html(html);

            //绑定领取
            that.e.find('#newyearpay_list_scroller').find('li').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend', function() {
                that.get_reward($(this).attr('itemid'));
            });

            //绑定充值
            that.e.find('.info').find('.pay_btn').unbind('click').bind('click', function() {
                that.hide();
                xGame.enterPay();
            });

            that.myScroller = new iScroll('newyearpay_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            /*显示界面*/
            that.e.show();

            that.myScroller.refresh();
            if(firstitem_key && firstitem_key > 5) {
                var max = (57 * res.list.length - 304) * -1;
                var my = (57 * firstitem_key) * -1;
                console.log('scrollto ' + my);
                my = my < max ? max : my;
                that.myScroller.scrollTo(0, my);
            }

            $('#worldloading').hide();
        },

        get_reward: function(id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch(parseInt(res.status)) {
                    case -1:
                        xGame.toast.show(lang('reward_had_receive'));
                        break;
                    case -2:
                        xGame.toast.show(lang('reward_info_no_exist'));
                        break;
                    case -3:
                        xGame.toast.show(lang('not_pay_log'));
                        break;
                    case -4:
                        xGame.toast.show(lang('amount_is_not_enough'));
                        break;
                    case -5:
                        xGame.toast.show(lang('canshu_lost'));
                        break;
                    case 1:
                        var type = {1:'add_silver',2:'add_gold', 3:'add_get1', 4:'add_get2', 5:'add_get3', 6:'add_jade', 7:'add_soul', 8:'add_tfcard', 9:'add_prestige', 10:'add_gem_part',11: 'add_lottery'};
                        xGame.toast.show(lang('receive_success') + '<span class="'+type[res.gettype]+'">'+res.getvalue+'</span>');
                        that.e.find('#newyearpay_list_scroller').find('.itemid'+id).attr('class', 'btn pop_btn_small_disable is_get').html('');
                        player.updateinfo({'silver':res.all_silver, 'gold':res.all_gold});
                        break;
                    default:
                        xGame.toast.show(lang('no_reward_try_again'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'newyearpay',
                'm': 'get_reward',
                'id': id
            },
            Func);
            Func = null;
        },

        unbindAll: function() {
            this.e.find('.content').find('#hidebtn').unbind('touchend');
            this.e.find('.info').find('.pay_btn').unbind('click');
            this.e.find('#newyearpay_list_scroller').find('li').undelegate('.pop_btn_small', 'touchend');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('newyearpay', 0);
            if (this.myScroller != null) { //先干掉原本的滑动
                this.myScroller.destroy();
                this.myScroller = null;
            }
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
        },
    };
})();