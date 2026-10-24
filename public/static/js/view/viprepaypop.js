/* 春节回馈VIP */
(function() {
    viprepayPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;

    };
    viprepayPopClass.prototype = {
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
                } else if(parseInt(res.status) == -2) {
                    xGame.toast.show(lang('reward_list_empty'));
                    $('#worldloading').hide()
                }
                 else {
                    that.buildpage(res);
                }
            }
            loadContents({
                'c': 'vipreward',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        buildpage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('viprepay');

            that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                viprepaypop.hide();
                e.preventDefault();
            });

            //组装界面数据
            var html = '';
            var firstitem_key = null;
            //1突飞卡，2黄宝石，3紫宝石，4声望
            var type = {1:'tfcard',2:'get2', 3:'get3', 4:'prestige'};

            res.list = res.list || [];
            xGame.updateCd('viprepay', res.cd || 0);
            that.e.find('.viprepay_info').html('<span>' + (res.msg || '') + '</span>');
            that.e.find('.now_vip').find('em').html('&nbsp;VIP' + player.info.vip);

            for(var i = 0; i < res.list.length; i++) {
                html += '<li>'+lang('viprepaypop_get', [res.list[i].vip])+'<em class="reward '+type[res.list[i].type]+'">'+res.list[i].value+'</em>';
                if(parseInt(res.list[i].status) == 1) {
                    //可领取
                    html += '<button class="btn pop_btn_small itemid'+res.list[i].id+'" itemid="'+res.list[i].id+'">'+lang('receive')+'</button></li>';
                    if(firstitem_key == null) {
                        firstitem_key = i;
                    }
                } else if(parseInt(res.list[i].status) == 2) {
                    //已领取
                    html += '<button class="btn pop_btn_small_disable is_get" itemid="'+res.list[i].id+'">' + lang('warena_have_fetch') + '</button></li>';
                } else {
                    //不可领取
                    html += '<button class="btn pop_btn_small_disable" itemid="'+res.list[i].id+'">'+lang('receive')+'</button></li>';
                    if(firstitem_key == null) {
                        firstitem_key = i;
                    }
                }
            }

            that.e.find('#viprepay_list_scroller').html(html);

            //绑定领取
            that.e.find('#viprepay_list_scroller').find('li').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend', function() {
                that.get_reward($(this).attr('itemid'));
            });

            that.myScroller = new iScroll('viprepay_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            /*显示界面*/
            that.e.show();

            that.myScroller.refresh();
            if(firstitem_key && firstitem_key > 3) {
                var max = (57 * res.list.length - 200) * -1;
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
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('viprepaypop_info_no_vip'));
                        break;
                    case -3:
                        xGame.toast.show(lang('viprepaypop_not_repay'));
                        break;
                    case -4:
                        xGame.toast.show(lang('get_reward_info_fail'));
                        break;
                    case -5:
                        xGame.toast.show(lang('viprepaypop_inadequate_not_repay'));
                        break;
                    case -6:
                        xGame.toast.show(lang('reward_had_receive'));
                        break;
                    case -7:
                        xGame.toast.show(lang('receiver_reward_fail_wait'));
                        break;
                    case 1:
                        var type = {1:'add_tfcard',2:'add_get2', 3:'add_get3', 4:'add_prestige'};
                        xGame.toast.show(lang('receive_success') + '<span class="'+type[res.reward.type]+'">'+res.reward.value+'</span>');
                        that.e.find('#viprepay_list_scroller').find('.itemid'+id).attr('class', 'btn pop_btn_small_disable is_get').html(lang('warena_have_fetch'));
                        player.updateinfo({'silver':res.all_silver, 'gold':res.all_gold});
                        break;
                    default:
                        xGame.toast.show(lang('no_reward_try_again'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'vipreward',
                'm': 'get_reward',
                'id': id
            },
            Func);
            Func = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('viprepay', 0);
            if (this.myScroller != null) { //先干掉原本的滑动
                this.myScroller.destroy();
                this.myScroller = null;
            }
            if (this.e) {
                this.e.find('.content').find('#hidebtn').unbind('touchend');
                this.e.find('#viprepay_list_scroller').find('li').undelegate('.pop_btn_small', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },
    };
})();