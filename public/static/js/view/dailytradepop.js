/* 活动 */
(function() {
    dailytradePopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom; //左边DOM.具体就是左边的UL
        this.rightDom;
        this.sid; //当前选中的ID
        this.leftTimes; //剩余次数
        this.myScroll;
    };
    dailytradePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    if (res.status == -1) {
                        xGame.toast.show(lang('get_activity_info_fail'));
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('get_treade_list_fail'));
                    } else if (res.status == -3) {
                        xGame.toast.show(lang('get_trade_history_fail'));
                    } else if (res.status == -4) {
                        xGame.toast.show(lang('get_trade_list_fail'));
                    } else if (res.status == 2) {
                        xGame.toast.show(lang('lv_not_enough'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    $('#worldloading').hide();
                }
                that = null;
            }
            loadContents({
                'c': 'business',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        initFirst: function(res) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('dailytrade');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                dailytradepop.hide();
                e.preventDefault();
                e.stopPropagation();

            });

            that.leftDom = that.e.find('#dailytrade_left').find('ul');
            that.rightDom = that.e.find('#dailytrade_right');
            that.leftTimes = res.times;
            that.myScroll = null;

            that.rightDom.find('#dtrade_refresh').html('x' + res.refresh);
            that.fillLeft(res.list);
            that.showNum();
            that.fillRightTop(res.trader);
            that.fillRightBottom(res.log);
            that.bindAll();

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();

        },

        showNum: function() {
            this.rightDom.find('#dtrade_times').html(this.leftTimes);
        },

        fillLeft: function(list) {
            var that = this;
            var html = '';
            var s;
            for (var i = 0; i < 3; i++) {
                s = '';
                html += '<li id="dtlist' + (i + 1) + '"><p class="dtl_title">' + lang('lucky_combination') + (i + 1) + '<span><em>' + lang('difficulty') + '</em><em class="star" style="width:' + (list[i].length * 18) + 'px"></em></span></p><p class="dtl_text">';
                for (var j = 0; j < list[i].length; j++) {
                    s += '<em id="dtlist_trader' + (j + 1) + '" class="' + (list[i][j].finish == 0 ? 'nc': 'c') + '">' + list[i][j].name + '</em>';
                    if (j != list[i].length - 1) {
                        s += '<b>+</b>';
                    }
                }
                html += s + '</p></li>';
            }
            that.leftDom.html(html);
        },

        fillRightTop: function(list) {
            var that = this;
            var html = '';
            var className = '';

            for (var i = 0; i < 3; i++) {
                if (i == 0) {
                    that.sid = list[i].id;
                    className = 'class="selected"';
                } else {
                    className = '';
                }
                html += '<li tid="' + list[i].id + '" ' + className + ' id="dtrader_li' + list[i].id + '"><span class="dtrader role' + list[i].id + '"></span><span class="dtrade_name" ' + (list[i]['class'] != undefined && list[i]['class'] != 0 ? 'style="color:#f32020;"': '') + '>' + list[i].name + '</span><span class="status"></span></li>';
            }
            that.rightDom.find('#dtrade_list').html(html);
            html = className = null;
        },

        fillRightBottom: function(list) {
            var that = this;
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<li><span class="dtrader role' + list[i].id + '"></span><span class="dtrade_name">' + list[i].name + '</span></li>';
            }
            that.rightDom.find('#trader_list_scroller').html(html);
            html = null;

            if (list.length >= 4) {
                if (that.myScroll == null) {
                    that.myScroll = new iScroll('dailytradepop_scroller', {
                        snap: 'li',
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                }

                that.rightDom.find('#trader_list_scroller').css('width', (116 * list.length) + 'px');
                that.myScroll.refresh();
                that.myScroll.scrollTo((list.length - 4) * -116, 0);
            }
        },

        bindAll: function() {
            var that = this;

            that.rightDom.undelegate('#dtrade_buy', 'touchend').delegate('#dtrade_buy', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.buyFunc();
            });

            that.rightDom.undelegate('#dtrade_refresh', 'touchend').delegate('#dtrade_refresh', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.refreshFunc();
            });

            that.rightDom.undelegate('#dtrade_trade', 'touchend').delegate('#dtrade_trade', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('lock')) { //不设置属性
                    return false;
                }
                that.tradeFunc($(this));
            });

            that.rightDom.find('#dtrade_list').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) { //不设置属性
                    return false;
                }
                that.rightDom.find('#dtrade_list').find('#dtrader_li' + that.sid).removeClass('selected');
                that.sid = $(this).attr('tid');
                $(this).addClass('selected');
            });

        },

        buyFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_activity_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('buy_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case 1:
                    player.updateinfo({
                        "gold":
                        parseInt(res.gold)
                    });
                    that.leftTimes = res.times;
                    that.showNum();
                    //xGame.toast.show('购买成功！');
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.goPay(lang('gold_not_enough_for_buying'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();

            };
            loadContents({
                'c':
                'business',
                'm': 'buy'
            },
            Func);
            Func = null;

        },

        refreshFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_treader_list_fail'));
                    break;
                case 1:
                    player.updateinfo({
                        "gold":
                        parseInt(res.gold)
                    });
                    that.fillRightTop(res.trader);

                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.goPay(lang('no_gold_for_refresh'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'business',
                'm': 'refresh'
            },
            Func);
            Func = null;

        },

        tradeFunc: function(objBtn) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 6 : xGame.toast.show(lang('get_trade_list_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('plus_silver_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('update_history_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_treade_list_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_activity_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('trader_not_exist'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.toast.show(lang('times_not_enough'));
                    break;
                case 1:
                    objBtn.addClass('lock');
                    that.leftTimes = res.times;
                    that.showNum();
                    player.updateinfo({
                        "silver": parseInt(res.silver)
                    });
                    that.fillRightTop(res.trader);
                    that.fillRightBottom(res.log);
                    that.fillLeft(res.list);
                    if (appLanguage == 'kr') {//韩文做语序调整
                        var obj = $('<div id="dailytradepop_extra" style="display:block;">' + lang('you_get', [res.reward_silver + ' ' + lang('silver')]) + '</div>');
                    } else {
                        var obj = $('<div id="dailytradepop_extra" style="display:block;">' + lang('you_get') + res.reward_silver + ' ' + lang('silver') + '</div>');
                    }
                    that.rightDom.append(obj).one('webkitAnimationEnd',
                    function() {
                        obj.remove();
                        obj = null;
                    });

                    if ($.isArray(res.treasuremap) == false) {
                       
                    } else {
                        objBtn.removeClass('lock');
                    }

                    if (res.complete != undefined && res.complete > 0) {
                        //setTimeout(function(){
                        xGame.toast.show(lang('you_get_extra') + res.addition + ' ' + lang('silver'));
                        that.rightDom.find('#dailytradepop_complete').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                        function() {
                            $(this).css('display', 'none');
                            objBtn.removeClass('lock');
                        });
                        //},1000);
                        if (res.complete == 2) {
                            if (CD['chat'] != undefined) {
                                CD['chat'].read();
                            }
                        }
                    } else {
                        objBtn.removeClass('lock');
                    }

                    player.taskaction(4);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'business',
                'm': 'go_business',
                'id': that.sid
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
            if (this.rightDom) {
                this.rightDom.undelegate('#dtrade_buy', 'touchend');
                this.rightDom.undelegate('#dtrade_refresh', 'touchend');
                this.rightDom.undelegate('#dtrade_trade', 'touchend');
                this.rightDom.find('#dtrade_list').undelegate('li', 'touchend');
                this.rightDom.unbind('webkitAnimationEnd');
                this.rightDom.remove();
                this.rightDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;
           
            if (this.myScroll != null) {
                this.myScroll.destroy();
                this.myScroll = null;
            }
            if (this.leftDom) {
                this.leftDom.remove();
                this.leftDom = null;
            }
            this.leftTimes = this.sid = null;

            if (xGame.nowWorld == 'country' && countryWorld != undefined) {
                countryWorld.showLeftBar();
            }
            this.unbindAll();
            player.reloadInfo();
        },
    };
})();