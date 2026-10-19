/* 藏宝大殿 */
(function() {
    countrytreasurePopClass = function() {
        this.e;
        this.isshow = false;
        
        this.recoverDom;
        this.type;//类型
        this.recoverScroller;
        this.myScroller;
        this.index;//当前商品的数组位置
        this.sid;//商品id
        this.cid;//红装id
        this.listData;//当前的商品列表数据
        this.recoverData;
    };
    countrytreasurePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var render = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    case 2:
                        that.exceptionFunc();
                        break;
                    case 3 : 
                        xGame.toast.show(lang('countrytreasurepop_lv_50'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('countrytreasurepop_list_block'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                if (parseInt(res.status) != 1) {
                    $('#worldloading').hide();
                }
                xGame.unlock();
                that = null;
            };
            loadContents({
                'c': 'countryshop',
                'm': 'index'
            },
            render, true); //加载数据
            render = null;
        },

        initPage: function(res) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('countrytreasure');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countrytreasurepop.hide();
                e.preventDefault();
            });
            
            that.recoverDom = that.e.find('#countrytreasure_info_dom');
            that.type = 1;
            
            that.showDesc();
            that.showItem(res.list);
            
            that.e.find('#my_equip_part').html(res.debris);
            
            that.bindAll();

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            that.isshow = true;

        },
        
        getList: function(type) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        if (type == 1) {
                            that.e.find('#countrytreasure_list').attr('class', 'countrytreasure_list juanzhou');
                        } else {
                            that.e.find('#countrytreasure_list').attr('class', 'countrytreasure_list gem');
                        }
                        
                        that.type = type;
                        that.showDesc();
                        that.showItem(res.list);
                        break;
                    case 2:
                        that.exceptionFunc();
                        break;
                    case 3 : 
                        xGame.toast.show(lang('countrytreasurepop_lv_50'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('countrytreasurepop_list_block'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
                that = null;
            }
            loadContents({
                'c': 'countryshop',
                'm': 'index',
                'tab': type
            },
            Func);
            Func = null;
        },
        
        showItem: function(list) {
            this.listData = list;
            var html = '';
            var num;
            var class_name;
            if (this.type == 1) {
                class_name = 'talent';
            } else {
                class_name = 'gem';
            }
            num = list.length;
            for (var i = 0; i < num;i ++) {
                if (i == 0) {
                    html += '<li class="q' + list[i].quality + ' selected" sid="' + list[i].id + '" index="' + i + '">';
                    this.index = i;
                    this.sid = list[i].id;
                    this.fillItemInfo();
                } else {
                    html += '<li class="q' + list[i].quality + '" sid="' + list[i].id + '" index="' + i + '">';
                }
                html += '<div class="img ' + class_name + list[i].itemtype + '"></div>';
                html += '<span class="light q' + list[i].quality + '"></span>';
                if (this.type == 1) {
                    html += '<span class="name" style="color:' + helper.getItemColor(list[i].quality) + ';">' + list[i].itemname + '</span>';
                } else {
                    html += '<span class="name" style="color:' + helper.getItemColor(list[i].quality) + ';">' + list[i].itemname + '(Lv.' + list[i].itemlevel + ')</span>';
                }
                html += '</li>';
            }
            this.e.find('#countrytreasure_list_scroller').html(html).width(num * 190 + 'px');
            html = null;
            num = null;
            class_name = null;
            if (this.myScroller == null) {
                this.myScroller = new iScroll('countrytreasure_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.myScroller.refresh();
        },

        bindAll: function() {
            var that = this;

            that.e.undelegate('#red_recover', 'touchend').delegate('#red_recover', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showRecover();
            });
            
            that.e.find('#countrytreasure_list_scroller').undelegate('li', 'click').delegate('li', 'click',
            function() {
                if (xGame.checkLock(true) || that.index == $(this).attr('index')) { //不设置属性
                    return false;
                }
                that.e.find('#countrytreasure_list_scroller').find('.selected').removeClass('selected');
                $(this).addClass('selected');
                that.sid = $(this).attr('sid');
                that.index = $(this).attr('index');
                that.fillItemInfo();
            });
            
            that.e.find('#countrytreasure_carousel').undelegate('.recover_box', 'touchend').delegate('.recover_box', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('block') || that.cid == $(this).attr('cid')) { //不设置属性
                    return false;
                }
                that.cid = $(this).attr('cid');
                that.index = $(this).attr('index');
                that.e.find('#countrytreasure_carousel').find('.selected').removeClass('selected');
                $(this).addClass('selected');
                that.recoverDom.find('#countrytreasure_part').html(that.recoverData[that.index].saleprice);
            });
            
            that.e.undelegate('#countrytreasure_buy', 'touchend').delegate('#countrytreasure_buy', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.buyFunc();
            });
            
            that.e.undelegate('#countrytreasure_juanzhou', 'touchend').delegate('#countrytreasure_juanzhou', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.type == 1) { //不设置属性
                    return false;
                }
                that.getList(1);
            });
            
            that.e.undelegate('#countrytreasure_gem', 'touchend').delegate('#countrytreasure_gem', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.type == 2) { //不设置属性
                    return false;
                }
                that.getList(2);
            });
            
            that.recoverDom.undelegate('#countrytreasure_info_hide', 'touchend').delegate('#countrytreasure_info_hide', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.recoverScroller.destroy();
                that.recoverDom.css('display', 'none');
                e.preventDefault();
            });
            
            that.recoverDom.undelegate('#countrytreasure_sell', 'touchend').delegate('#countrytreasure_sell', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.cid == 0) { //不设置属性
                    return false;
                }
                var str = '';
                if (that.recoverData[that.index].chip == 0) {
                    str = lang('countrytreasurepop_sell_ask', [that.recoverData[that.index].name, that.recoverData[that.index].saleprice]);
                } else {
                    str = lang('countrytreasurepop_sell_ask2', [that.recoverData[that.index].name, that.recoverData[that.index].saleprice]);
                }
                xGame.confirm.show(lang('notice'), str, 2,
                function() {
                    xGame.confirm.hide(function() {
                        countrytreasurepop.sellFunc();
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
               
            });
        },
        
        showRecover: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.fillRecover(res.list);
                        break;
                    case 2:
                        that.exceptionFunc();
                        break;
                    case 3 : 
                        xGame.toast.show(lang('countrytreasurepop_lv_50'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
                that = null;
            }
            loadContents({
                'c': 'countryshop',
                'm': 'red_item_list',
            },
            Func);
            Func = null;
        },
        
        fillRecover: function(list) {
            if (list.length == 0) {
                this.recoverDom.find('#countrytreasure_part').html(0);
                this.cid = 0;
            }
            this.recoverData = list;
            var html = '<li>';
            var n = 0;
            for (var i = 0;i < list.length;i ++) {
                if (n > 0 && n % 6 == 0) { //下一页了
                    html += '</li><li>';
                }
                if (n == 0) {
                    this.index = i;
                    this.cid = list[i].id;
                    this.recoverDom.find('#countrytreasure_part').html(list[i].saleprice);
                }
                html += '<div class="has recover_box q' + list[i].quality + ' ' + (n == 0 ? ' selected' : '') + '" cid="' + list[i].id + '" index="' + i + '">';
                html += '<div class="itempic" style="background-image:url(' + xGame.itemurl(list[i].equipid) + ');"></div>';
                
                if (parseInt(list[i].chip) > 0) {
                    html += '<div class="name big">' + list[i].full_num + '</div>';
                    html += '<div class="itempart"></div>';
                } else {
                    html += '<div class="name">' + list[i].name + '</div>';
                }

                if (parseInt(list[i].hammer) > 0) {
                    html += '<span class="hammer hammer'+ list[i].hammer_quality +'">' + list[i].hammer + '</div>';
                }

                html += '</div>';
                n++;
            }
            /* 补空格 */
            var repeat_s = '<div class="recover_box q1 block"></div>';
            if (n == 0) { //神马也没有
                html += helper.str_repeat(repeat_s, 6);
            } else if (n % 6 == 0) {
                html += '';
            } else {
                html += helper.str_repeat(repeat_s, 6 - n % 6);
            }
            html += '</li>';
            
            this.e.find('.carousel_content').html(html);

            /* 创建旋转门 domid, 单页宽度 */
            this.recoverScroller = xGame.carousel('countrytreasure_carousel', 369);

            this.recoverDom.css('display', 'block');
        },
        
        fillItemInfo: function() {
            var data = this.listData[this.index];
            if (this.type == 1) {
                this.e.find('#countrytreasure_name').html(data.itemname + '(x' + data.number + ')');
            } else {
                this.e.find('#countrytreasure_name').html(data.itemname + '(Lv.' + data.itemlevel + ')');
            }
            if (data.sale_type == 1) {
                this.e.find('#countrytreasure_cost').html(data.price).attr('class', 'gold');
            } else {
                this.e.find('#countrytreasure_cost').html(data.price).attr('class', 'part');
            }
            data = null;
        },
        
        showDesc: function() {
            if (this.type == 1) {
                this.e.find('#countrytreasure_info').attr('class', 'juanzhou');
            } else {
                this.e.find('#countrytreasure_info').attr('class', 'gem');
            }
        },
        
        buyFunc: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        if (res.type == 1) {
                            player.updateinfo({
                                'gold': res.all_gold
                            });
                            var str = '<em style="color:' + helper.getItemColor(res.quality) + ';">' + res.name + 'x' + res.num + '</em>'
                            xGame.toast.show(lang('countrytreasurepop_buy', [str]));
                        } else {
                            that.e.find('#my_equip_part').html(res.all_debris);
                            var str = '<em class="reward ct_gem"style="color:' + helper.getItemColor(res.quality) + ';background: url(' + xGame.gem(res.itemtype, res.quality) + ') 0 50% no-repeat;">' + res.name + 'Lv.' + res.level + '</em>';
                            xGame.toast.show(lang('countrytreasurepop_buy', [str]));
                        }
                        break;
                    case 2:
                        that.exceptionFunc();
                        break;
                    case 3 : 
                        xGame.toast.show(lang('countrytreasurepop_lv_50'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('countrytreasurepop_not_exist'));
                        break;
                    case - 3 : 
                        xGame.goPay(lang('gold_not_enough'));
                        break; 
                    case - 4 : 
                        xGame.toast.show(lang('countrytreasurepop_part_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
                that = null;
            }
            loadContents({
                'c': 'countryshop',
                'm': 'buy',
                'id': that.sid
            },
            Func);
            Func = null;
        },
        
        sellFunc: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        xGame.toast.show(lang('countrytreasurepop_sell_get', [res.add_debris]));
                        that.e.find('#my_equip_part').html(res.all_debris);
                        var dom = that.e.find('#countrytreasure_carousel').find('.selected');
                        dom.attr('class', 'recover_box q1 block').html('');
                        var near = dom.siblings('.has');
                        if (near.length > 0) {
                            near = $(near[0]);
                            near.addClass('selected');
                            that.cid = near.attr('cid');
                            that.index = near.attr('index');
                            that.recoverDom.find('#countrytreasure_part').html(that.recoverData[that.index].saleprice);
                        } else {
                            that.recoverDom.find('#countrytreasure_part').html(0);
                            that.cid = 0;
                        }
                        break;
                    case 2:
                        that.exceptionFunc();
                        break;
                    case 3 : 
                        xGame.toast.show(lang('countrytreasurepop_lv_50'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('countrytreasurepop_not_exist'));
                        break;
                    case - 3 : 
                        xGame.toast.show(lang('countrytreasurepop_unsellable'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
                that = null;
            }
            loadContents({
                'c': 'countryshop',
                'm': 'sale_red_item',
                'id': that.cid
            },
            Func);
            Func = null;
        },
        
        /* 领取奖励 */
        get_reward: function(obj) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case - 5 : xGame.toast.show(lang('update_player_info_fail'));
                        break;
                    case - 4 : xGame.toast.show(lang('get_house_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('get_house_info_fail'));
                        break;
                    case - 2 : that.exceptionFunc();
                        break;
                    case - 1 : that.exceptionFunc();
                        break;
                    case 1:
                        obj.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                        if (res.act != undefined) {
                            player.updateinfo({
                                'act': parseInt(res.act)
                            });
                        }
                        if (res.silver != undefined) {
                            player.updateinfo({
                                'silver': parseInt(res.silver)
                            });
                        }
                        if (res.jade != undefined) {
                            player.updateinfo({
                                'jade': parseInt(res.jade)
                            });
                        }
                        if (res.soul != undefined) {
                            player.updateinfo({
                                'soul': parseInt(res.soul)
                            });
                        }

                        xGame.toast.show(lang('reward_success'));
                        break;
                    case 2:
                        xGame.toast.show(lang('today_had_recive'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv50_open'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
                that = null;
            }
            loadContents({
                'c': 'country',
                'm': 'get_salary'
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
                this.e.undelegate('#red_recover', 'touchend');
                this.e.find('#countrytreasure_list_scroller').undelegate('li', 'click');
                this.e.find('#countrytreasure_carousel').undelegate('.recover_box', 'touchend');
                this.e.undelegate('#countrytreasure_buy', 'touchend');
                this.e.undelegate('#countrytreasure_juanzhou', 'touchend');
                this.e.undelegate('#countrytreasure_gem', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.recoverDom) {
                this.recoverDom.undelegate('#countrytreasure_info_hide', 'touchend');
                this.recoverDom.undelegate('#countrytreasure_sell', 'touchend');
                this.recoverDom.remove();
                this.recoverDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
    
            this.isshow = false;
            this.sid = null;
            this.cid = null;
            this.type = null;
            
            this.index = null;
            this.listData = null;
            this.recoverData = null;
            
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            if (this.recoverScroller != null) {
                this.recoverScroller.destroy();
                delete this.recoverScroller;
            }
            this.unbindAll();
            
        },
    };
})();