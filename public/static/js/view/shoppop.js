/* 商店 */
(function() {
    shopPopClass = function() {
        this.e;
        this.isshow = false;

        this.ulList; //左边容器
        this.sid; //所选择的id
        this.rightDom; //右侧数据dom
        this.leftDom;

        this.nowTime; //当前次数
        this.times; //总次数
        this.myScroller;
        
        this.krData;//专门为韩语存的变量

    };
    shopPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('can_not_get_item_info'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    that.initList(res);
                    break;
                }
                xGame.unlock();
                that = null;
            };
            loadContents({
                'c': 'shop',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        initList: function(data) {
            var that = this;

            data = data || {};
            data.totals = parseInt(data.totals) || 0;
            data.list = data.list || {};
            data.info = data.info || { info: {}, need_viplevel: 0, cd: 0 };
            data.info.info = data.info.info || {};
            data.log_list = data.log_list || [];

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('shop');
            that.e.find('.content').find('.hidebtn').unbind('touchend').one('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                shoppop.hide();
                e.preventDefault();
            });

            that.rightDom = that.e.find('#shop_rightDom');
            that.leftDom = that.e.find('#shop_leftDom');
            that.krData = {};
            /* 第二步 构建数据列表等 */
            var html = '';
            for (var i = 1; i <= data.totals; i++) {
                if (!data.list[i]) continue;
                html += '<li id="shopitem' + data.list[i].id + '" class="shoppop_listitem s_i_bj' + data.list[i].quality + (i == 1 ? ' selected': '') + '" itemid="' + data.list[i].id + '"><div class="itempic" style="background-image:url(' + xGame.itemurl(data.list[i].id) + ');' + '"></div>' + that.transfer(data.list[i].need_viplevel) + '</div>';
                that.krData[data.list[i].name] = parseInt(data.list[i].id);
            }
            that.sid = (data.list[1] && data.list[1].id) ? data.list[1].id : 0;

            that.ulList = that.e.find('#ul_list'); //给左边容器赋值
            that.ulList.html(html).width(data.totals * 128 + 'px');
            that.e.find('#shoplist_scroller').width(data.totals * 128 + 'px');

            that.myScroller = new iScroll('shoplist_carousel', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.myScroller.refresh();

            /* 绑定事件(必须在旋转门之后) */
            that.ulList.undelegate('.shoppop_listitem', 'click').delegate('.shoppop_listitem', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('itemid') == undefined) {
                    return false;
                }
                that.ulList.find('#shopitem' + that.sid).removeClass('selected');
                $(this).addClass('selected');
                that.sid = $(this).attr('itemid');
                that.viewItem();
            });

            that.fillRightInfo(data.info.info, data.info.need_viplevel);

            that.rightDom.undelegate('.pop_btn_big', 'touchend').delegate('.pop_btn_big', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.buyItem();
            });
            that.leftDom.undelegate('.un', 'touchend').delegate('.un', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('uid') == undefined) {
                    return false;
                }
                xGame.showpop('info', $(this).attr('uid'));
            });

            that.initTime(parseInt(data.info.cd));

            var html2 = '';
            for (var i = 0; i < data.log_list.length; i++) {
                if (appLanguage == 'kr') {
                    html2 += '<li><p class="un" uid="' + data.log_list[i].uid + '">' + data.log_list[i].username + '</p><p><em>' + data.log_list[i].dateline + '</em>' + '<b style="color:' + helper.getItemColor(parseInt(data.log_list[i].quality)) + ';">' + data.log_list[i].itemname + '</b>' + (that.krData[data.log_list[i].itemname] > 11 ? '을' : '를') + lang('had_bought') + '</p></li>';
                } else {
                    html2 += '<li><p class="un" uid="' + data.log_list[i].uid + '">' + data.log_list[i].username + '</p><p><em>' + data.log_list[i].dateline + '</em>' + lang('had_bought') + '<b style="color:' + helper.getItemColor(parseInt(data.log_list[i].quality)) + ';">' + data.log_list[i].itemname + '</b></p></li>';
                }
            }
            that.leftDom.find('ul').html(html2);
            html2 = null;

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            html = null;
        },

        initTime: function(cd) {
            var that = this;

            xGame.updateCd('shop', cd);

        },

        transfer: function(num) {
            var s;
            if (num == 0) {
                s = '';
            } else if (num >= 10) {
                s = '<span class="ten"><em class="shop_vip' + num % 10 + '"></em><em class="shop_vip' + Math.floor(num / 10) + '"></em></span>';
            } else {
                s = '<span><em class="shop_vip' + num + '"></em></span>';
            }
            return s;
        },

        fillRightInfo: function(info, vip) {
            var that = this;
            that.rightDom.find('.itembox').attr('class', 'itembox s_i_bj' + info.quality).children('span').css({
                'background': 'url(' + xGame.itemurl(info.equipid) + ') center center no-repeat'
            });
            that.rightDom.find('#iteminfo_p').find('h4').html('<em>' + info.name + '</em>' + (vip > 0 ? '<span id="shopvip">' + lang('only_vip_can_buy', [vip]) + '</span>': '')).css({
                'color': helper.getItemColor(parseInt(info.quality))
            });
            that.rightDom.find('#iteminfo_p').find('#type').html(helper.getItemVocation(parseInt(info.type)));
            that.rightDom.find('#iteminfo_p').find('#eq_lv').html(info.needlevel);
            that.rightDom.find('#iteminfo_p').find('#atk').html(helper.getItemPowerName(parseInt(info.type)) + '：<span id="atk_num">' + info.power + '</span>');
            that.rightDom.find('#text').html(info.description);
            that.rightDom.find('#need_gold').text(info.price + (parseInt(info.shoptype) == 1 ? lang('gold') : lang('silver'))).attr('class', (parseInt(info.shoptype) == 1 ? 'g': 's'));
            that.nowTime = parseInt(info.nowmaxtimes);
            that.times = info.maxtimes;
            if (that.nowTime <= 0) {
                that.rightDom.find('.sell_up').show();
                that.rightDom.find('#iteminfo_limit').removeClass('isNum').hide();
            } else if (that.times <= 10 || that.times > 20 && that.nowTime <= parseInt(that.times / 2)) {
                that.rightDom.find('.sell_up').hide();
                that.rightDom.find('#iteminfo_limit').show();
                that.rightDom.find('#iteminfo_limit').html('<b style="color:#FF9120;">' + lang('only_left_n', [that.transfer(that.nowTime)]) + '</b>').addClass('isNum');
            } else {
                that.rightDom.find('.sell_up').hide();
                that.rightDom.find('#iteminfo_limit').show();
                that.rightDom.find('#iteminfo_limit').html(lang('only_left') + '<b></b>').removeClass('isNum');
                that.rightDom.find('#iteminfo_limit').find('b').html(that.nowTime + '/' + that.times);
            }
        },

        viewItem: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var func = function(res) {
                if (!res || res.status != 1) {
                    xGame.toast.show(lang('can_not_get_data'));
                    return;
                }
                that.fillRightInfo(res.info, res.need_viplevel);
                xGame.unlock();
            };
            loadContents({
                'c': 'shop',
                'm': 'get_info',
                'id': that.sid
            },
            func); //加载数据
            func = null;
        },

        buyItem: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            if (that.nowTime == 0) {
                xGame.toast.show(lang('item_is_sold_up'));
                xGame.unlock();
                return;
            }
            var func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('equip_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('equip_tmpl_is_not_exist'));
                    break;
                case - 3 : xGame.toast.show(lang('buy_cd_not_zero'));
                    break;
                case - 4 : xGame.goPay(lang('no_gold_to_buy'));
                    break;
                case - 5 : xGame.toast.show(lang('no_silver_to_buy'));
                    break;
                case - 6 : xGame.toast.show(lang('item_sold_up_try_24'));
                    that.rightDom.find('.sell_up').show();
                    that.rightDom.find('#iteminfo_limit').removeClass('isNum').hide();
                    break;
                case - 7 : xGame.toast.show(lang('add_item_fail'));
                    break;
                case - 8 : xGame.toast.show(lang('vip_lv_too_low'));
                    break;
                case 1:
                    xGame.toast.show(lang('buy_success'));
                    that.nowTime = res.maxtimes;
                    that.initTime(parseInt(res.cd));
                    if (res.gold != undefined) {
                        player.updateinfo({
                            'gold': res.gold
                        });
                    }
                    if (res.silver != undefined) {
                        player.updateinfo({
                            'silver': res.silver
                        });
                    }
                    if (that.rightDom.find('.isNum').length == 0) {
                        if (that.nowTime <= 10 || that.times > 20 && that.nowTime <= parseInt(that.times / 2)) {
                            that.rightDom.find('#iteminfo_limit').html('<b style="color:#FF9120;">' + lang('only_left_n', [that.transfer(that.nowTime)]) + '</b>').addClass('isNum');
                        } else {
                            that.rightDom.find('#iteminfo_limit').find('b').html(that.nowTime + '/' + that.times);
                        }
                    } else {
                        if (that.nowTime != 0) {
                            that.rightDom.find('#iteminfo_limit').html(lang('only_left_c',[that.transfer(that.nowTime)]));
                        } else {
                            that.rightDom.find('.sell_up').show();
                            that.rightDom.find('#iteminfo_limit').removeClass('isNum').hide();
                        }
                    }

                    if (that.leftDom.find('ul').find('li').size() == 5) {
                        that.leftDom.find('ul').find('li').eq(4).remove();
                    }
                    var obj = that.rightDom.find('#iteminfo_p').find('h4');
                    var time = new Date().getHours() + ':' + (new Date().getMinutes() < 10 ? '0' + new Date().getMinutes() : new Date().getMinutes()) + ':' + (new Date().getSeconds() < 10 ? '0' + new Date().getSeconds() : new Date().getSeconds());
                    if (appLanguage == 'kr') {
                        that.leftDom.find('ul').prepend('<li><p class="un">' + player.info.nickname + '</p><p><em>' + time + '</em><b style="color:' + obj.css('color') + ';">' + obj.find('em').html() + '</b>' + (that.krData[obj.find('em').html()] > 11 ? '을' : '를') + lang('had_bought') + '</p></li>');
                    } else {
                        that.leftDom.find('ul').prepend('<li><p class="un">' + player.info.nickname + '</p><p><em>' + time + '</em>' + lang('had_bought') + '<b style="color:' + obj.css('color') + ';">' + obj.find('em').html() + '</b></p></li>');
                    }
                    time = null;
                    obj = null;
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'shop',
                'm': 'buy',
                'id': that.sid
            },
            func); //加载数据
            func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.ulList) {
                this.ulList.undelegate('.shoppop_listitem', 'click');
                this.ulList.remove();
                this.ulList = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('.pop_btn_big', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
            }
            if (this.leftDom) {
                this.leftDom.undelegate('.un', 'touchend');
                this.leftDom.remove();
                this.leftDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;

            if (CD['shop']) {
                CD['shop'].update(0);
            }
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            this.krData = null;
            this.ulList = this.sid = this.nowTime = this.times = null;
            this.unbindAll();
        },
    };
})();