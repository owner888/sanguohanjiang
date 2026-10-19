/* 马场*/
(function() {
    studfarmPopClass = function() {
        this.e;
        this.e2;
        this.hid;
        this.priceData;
        this.isshow = false;
    };
    studfarmPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 2:
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        $('#worldloading').hide();
                        break;
                    case 1:
                        //载入界面
                        that.perpage(res.list, res.jade2, res.jade3);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        $('#worldloading').hide();
                        break;
                }
            };
            loadContents({
                'c': 'studfarm',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },
        perpage: function(data, jade2, jade3) {
            var that = this;
            //载入页面
            that.e = xGame.loadPopTmpl('studfarm');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                studfarmpop.hide();
                e.preventDefault();
            });
            //更新页面所有数据
            that.priceData = {};
            for (var i = 0,len = data.length;i < len; i ++) {
                that.e.find('#studfarm_horse' + (i + 1)).find('button').find('b').html('x' + data[i].price).attr('class', 'gem' + data[i].sub_type);
                that.priceData[data[i].id] = {};
                that.priceData[data[i].id].price = data[i].price;
                that.priceData[data[i].id].type = data[i].sub_type;
            }
            
            that.e.find('#studfarmpop_gem2').html(jade2);
            that.e.find('#studfarmpop_gem3').html(jade3);
            
            that.bindAll();
            //显示界面
            that.e.show();
            $('#worldloading').hide();
        },
        
        bindAll: function() {
            var that = this;
            
            that.e.undelegate('#studfarmpop_info', 'touchend').delegate('#studfarmpop_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#studfarm_info_dom').css('display', 'block');
            });
            
            that.e.undelegate('#studfarm_info_hide', 'touchend').delegate('#studfarm_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#studfarm_info_dom').css('display', 'none');
            });
            
            that.e.undelegate('#go_mount', 'touchend').delegate('#go_mount', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                studfarmpop.hide();
                xGame.showpop('mount');
            });
            
            that.e.undelegate('.buy_btn', 'touchend').delegate('.buy_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.buyFunc($(this).attr('hid'));
            });
        },    
        
        //买马
        buyFunc: function(hid) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                if (res.status != 1) {
                    xGame.unlock();
                }
                switch (parseInt(res.status)) {
                    case 2 : 
                        xGame.toast.show(lang('studfarmpop_lv_too_low'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('jade_not_enough'));
                        break;
                    case - 3 : 
                        xGame.toast.show(lang('studfarmpop_chose_fail'));
                        break;
                    case - 4 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case - 5 : 
                        xGame.toast.show(lang('studfarmpop_get_horse_fail'));
                        break;
                    case - 6 : 
                        xGame.toast.show(lang('studfarmpop_chose_fail'));
                        break;
                    case - 7 : 
                        xGame.toast.show(lang('studfarmpop_block'));
                        break;  
                   case 1:
                        /*
                        player.updateinfo({
                            "gold": res.all_gold,
                        });
                        */
                        that.e.find('#studfarmpop_gem2').html(res.jade2);
                        that.e.find('#studfarmpop_gem3').html(res.jade3);
                        
                        if (hid != undefined) {
                            that.hid = hid;
                        }
                        that.showHorse(res.info, res.first);
                        if (parseInt(res.info.quality) >= 5 && typeof CD['chat'] != 'undefined') {
                            CD['chat'].read();//手动请求一次，为了早点广播
                        }
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            
            if (hid != undefined) {
                loadContents({
                    'c': 'studfarm',
                    'm': 'action',
                    'id': hid,
                    'new': 1
                }, Func);
            } else {
                loadContents({
                    'c': 'studfarm',
                    'm': 'action',
                    'id': that.hid,
                    'new': 1
                }, Func);
            }
            Func = null;
        },

        showHorse: function(data, first) {
            var that = this;
            
            if(that.e2 != null) {
                that.e2.removeClass('first');
                xGame.doAnimate(that.e2.find('#horse_img'), 'newhero_scalein', '0.5s', function() {
                    if (first == 1) {
                        that.e2.addClass('first');
                        setTimeout(function() {
                            xGame.unlock();
                        }, 1000);
                    } else {
                        xGame.unlock();
                    }
                });
                that.updateHorse(data);
                return false;
            }

            that.e2 = xGame.loadPopTmpl('horsebox');
            that.e2.find('#horseboxpop_hidebtn').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.closeResult();
            });
            that.e2.find('#rebuy_horse').unbind('touchend').bind('touchend', function(e) {
                if(xGame.checkLock(true)) {
                    return false;
                }
                that.buyFunc();
                e.preventDefault();
            });

            that.updateHorse(data);
            
            that.e2.removeClass('first');
            that.e2.show();
            xGame.doAnimate(that.e2.find('#horseboxpop_wrap'), 'newhero_scalein', '0.5s', function() {
                if (first == 1) {
                    that.e2.addClass('first');
                    setTimeout(function() {
                        xGame.unlock();
                    }, 1000);
                } else {
                    xGame.unlock();
                }
            });
        },
        
        updateHorse: function(data) {
            this.e2.find('#horse_img').attr('class', 'horse' + data.mid).css('background-position', helper.getHorsePostion('big', data.mid));
            this.e2.find('#horse_name').html(data.name).css('color', helper.getItemColor(data.quality));
            this.e2.find('#rebuy_horse').find('#horse_price').html('x' + this.priceData[this.hid].price).attr('class', 'gem' + this.priceData[this.hid].type);
        },
        
        closeResult: function() {
            if(this.e2) {
                this.e2.remove();
                this.e2 = null;
            }
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.undelegate('#studfarmpop_info', 'touchend');
                this.e.undelegate('#studfarm_info_hide', 'touchend');
                this.e.undelegate('#go_mount', 'touchend');
                this.e.undelegate('.buy_btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.e2) {
                this.e2.find('#horseboxpop_hidebtn').unbind('touchend');
                this.e2.find('#rebuy_horse').unbind('touchend');
                this.e2.remove();
                this.e2 = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.hid = null;
            this.priceData = null;
            this.unbindAll();
            this.closeResult();
        }
    };
})();