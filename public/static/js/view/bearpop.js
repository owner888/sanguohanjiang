/* 大熊返利 */
(function() {
    bearPopClass = function() {
        this.e;
        this.isshow = false;
        
        this.mainDom;
    };
    bearPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    case -2:
                        xGame.toast.show(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_is_end'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

                if (res.status != 1) {
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'rebate',
                'm': 'index',
            },
            initRender, true);
            
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('bear');
            
            if (player.info.jump_rebate_type == 1) {
                that.e.find('#bearpop_wrap').addClass('new');
            }
            
            that.mainDom = that.e.find('#bearpop_main');
            
            that.e.find('.content').find('#bear_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                bearpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.bindAll();
            
            xGame.updateCd('bear', res.cd);
            that.mainDom.find('#current_gold').html(res.gold);
            that.mainDom.find('#left_gold').html(res.surplus);
            that.mainDom.find('#left_day').html(res.day);
            
            //0 不可领取 1 可领取 2 已领取
            if (res.get == 0) {
                that.mainDom.find('#bear_receive').attr('class', 'pop_btn_big_disable btn').html(lang('not_reached'));
            } else if (res.get == 1) {
                that.mainDom.find('#bear_receive').attr('class', 'pop_btn_big btn').html(lang('receive'));
            } else if (res.get == 2) {
                that.mainDom.find('#bear_receive').attr('class', 'pop_btn_big_disable btn').html(lang('warena_have_fetch'));
            }
            
            that.e.show();
            $('#worldloading').hide();
            
        },
        
        bindAll: function(){
            var that = this;

            that.mainDom.undelegate('#bear_receive.pop_btn_big', 'touchend').delegate('#bear_receive.pop_btn_big', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveReward($(this));
            });
            
            that.mainDom.undelegate('#pay_btn', 'touchend').delegate('#pay_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.enterPay();
                bearpop.hide();
            });
        },
        
        receiveReward: function(obj) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        
                        player.updateinfo({
                            'gold' : res.all_gold
                        });
                        
                        that.mainDom.find('#left_gold').html(res.surplus);
                        that.mainDom.find('#left_day').html(res.day);
                        
                        xGame.toast.show(lang('receive_get_gold', [res.add]));
                        obj.attr('class', 'pop_btn_big_disable btn').html(lang('warena_have_fetch'));
                        
                        break;
                    case -6:
                        xGame.toast.show(lang('yuandan_error'));
                        break;
                    case -5:
                        xGame.toast.show(lang('bearpop_had_receive'));
                        break;
                    case -4:
                        xGame.toast.show(lang('bearpop_all_over'));
                        break;
                    case -3:
                        xGame.toast.show(lang('bearpop_not_reach'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    default:
                        beatengodpop.myToast(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'rebate',
                'm': 'get_reward',
            },
            Func);

            Func = null;
        },

        unbindAll: function() {
	    if (this.e) {
                this.e.find('.content').find('#bear_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
	    }
	    if (this.mainDom) {
                this.mainDom.undelegate('#bear_receive.pop_btn_big', 'touchend');
                this.mainDom.undelegate('#pay_btn', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
	    }
        },
        
        hide: function() {

            xGame.unlock();
            this.isshow = false;
            
            xGame.updateCd('bear', 0);
            this.unbindAll();
           
            
        },
    };
})();