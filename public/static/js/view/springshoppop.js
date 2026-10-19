/*春节商城*/
(function() {
    springshopPopClass = function() {
        this.e;
        this.sid;//当前商品id
        this.listData;//列表数据
        this.itemDom;//商品列表dom
        this.itemScroller;//商品iscroll
        this.autoThread;//自动刷新
    };
    springshopPopClass.prototype = {
        init: function() {
            var that = this;

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        that.autoThread = setTimeout(function() {
                            if (springshoppop.e) {
                                springshoppop.autoRefresh();
                            }
                        }, 5000);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }

                xGame.unlock();
                $('#worldloading').hide();

            };
            
            loadContents({
                'c': 'springshop',
                'm': 'index'
            }, initRender, true);
            initRender = null;
        },

        initPage: function(data) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('springshop');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                springshoppop.hide();
                e.preventDefault();
            });
            
            that.e.find('#springshop_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#springshop_info_dom').css('display', 'none');
                e.preventDefault();
            });
            
            that.itemDom = that.e.find('#springshop_list_scroller');
            
            that.sid = 0;
            that.listData = data.list;
            that.fillData(data.list);
            
            xGame.updateCd('springshop', data.end_time);
            that.e.find('#springshop_gem3').html(data.get3);
            that.e.find('#springshop_prestige').html(data.reputation);
            that.bindAll();
            that.e.show();
            
        },
        
        autoRefresh: function() {
            var that = this;
            var Func = function(res) {
                
                if (res.status == '1') {
                    that.autoThread = setTimeout(function() {
                        if (springshoppop.e) {
                            springshoppop.fillData(res.list);
                            springshoppop.autoRefresh();
                        }
                    }, 5000);
                } 
            };
            
            //加载数据
            loadContents({
                'c': 'springshop',
                'm': 'index'
            },
            Func, true, function() {
                that.autoThread = setTimeout(function() {
                    if (springshoppop.e) {
                        springshoppop.autoRefresh();
                    }
                }, 5000);
            }, true);
            Func = null;
        },
        
        bindAll: function() {
            var that = this;
            
            that.itemDom.undelegate('.touchdiv', 'click').delegate('.touchdiv', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.sid = $(this).attr('sid');
                that.itemDom.find('.selected').removeClass('selected');
                $(this).parent().addClass('selected');
                that.showHeroInf(that.listData[$(this).attr('index')].general);
            });

            that.itemDom.undelegate('.pop_btn_middle ', 'click').delegate('.pop_btn_middle ', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var gid = $(this).attr('gid');
                var price = $(this).attr('price');
                var price1 = $(this).attr('price1');//声望
                var name = $(this).attr('hname');
                xGame.confirm.show(lang('buy'), lang('springshoppop_ask', [price, price1, name]), 2,
                function() {
                    xGame.confirm.hide();
                    that.buyHero(gid);
                },
                function() {
                    xGame.confirm.hide();
                },
                lang('ok'), lang('close'));
            });
        },

        fillData: function(list) {
            var html = '';
            var num = 0;
            for (var i = 0,len = list.length;i < len;i ++) {
                if ((this.sid == 0 && i == 0) || (this.sid != 0 && this.sid == list[i].id)) {
                    html += '<li class="selected" id="gid' + list[i].id + '" index="' + i + '">';
                    this.sid = list[i].id;
                } else {
                    html += '<li id="gid' + list[i].id + '" index="' + i + '">';
                }
                html += '<img src="' + xGame.avatar(list[i].image, true) + '" />';
                html += '<span class="bazhe"></span>'
                html += '<span class="hero_name"><em class="name_wrap">' + list[i].name + '</em></span>';
                html += '<span class="herotype">' + helper.getItemVocation(list[i].type) + '</span>';
                html += '<span class="limited">' + list[i].buytimes + '/' + list[i].number + '</span>';
                html += '<div class="price_wrap old_price_wrap">'+lang('original_price')+'<span class="price_gem">' + list[i].price + '</span><span class="price_prestige">' + list[i].price1 + '</span><span class="old_line"></span></div>';
                html += '<div class="price_wrap new_price_wrap">'+lang('now_price')+'<span class="price_gem">' + list[i].price_discount + '</span><span class="price_prestige">' + list[i].price1_discount + '</span></div>';
                html += '<div class="touchdiv" index="' + i + '" sid="' + list[i].id + '"></div>';
                if (list[i].is_buy == 1) {//可购买,有数量
                    //html += '<button class="btn pop_btn_middle pay' + list[i].buytype + '" gid="' + list[i].id + '" hname="' + list[i].name + '" price="' + list[i].price + '"><em class="springshop_btn_t">' + lang('buy') + '</em><em class="springshop_btn_p">x' + list[i].price + '</em></button>';
                    html += '<button class="btn pop_btn_middle pay' + list[i].buytype + '" gid="' + list[i].id + '" hname="' + list[i].name + '" price="' + list[i].price_discount + '" price1="' + list[i].price1_discount + '">' + lang('buy') + '</button>';
                } else if (list[i].is_buy == 2) {//已卖完
                    html += '<button class="btn pop_btn_middle_disable">' + lang('christmasgeneral_no_num') + '</button>';
                    html += '<div class="shop_over"></div>';
                } else {//已购买
                    html += '<button class="btn pop_btn_middle_disable">' + lang('christmasgiftpop_had_buy') + '</button>';
                }
                html += '</li>';
                num += 1;
            }
            
            this.itemDom.html(html).width(num * 250);
            html = null;
            if (this.itemScroller == null) {
                this.itemScroller = new iScroll('springshop_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.itemScroller.refresh();
        },
        
        showHeroInf: function(data) {
            var html = '';
            html += '<div class="hero_avatar" style="background: url(' + xGame.avatar(data.image, true) + '); 0 0 no-repeat"></div>';
            html += '<p class="hero_name">' + data.name + '</p>';
            html += '<span class="herotype">' + helper.getItemVocation(data.type) + '</span>';
            html += '<p class="hero_stars" style="width: ' + (18 * parseInt(data.star)) + 'px;"></p>';
            html += '<p class="hero_wuli hero_p">' + lang('springshoppop_wuli', [data.wuli]) + '</p>';
            html += '<p class="hero_zhili hero_p">' + lang('springshoppop_zhili', [data.zhili]) + '</p>';
            html += '<p class=" hero_tili hero_p">' + lang('springshoppop_tili', [data.tili]) + '</p>';
            html += '<p class="hero_talent_title">' + lang('springshoppop_talent') + '</p>';
            html += '<p class="hero_talent hero_infop"><span>' + data.talent.name + '</span>：' + data.talent.description + '</p>';
            html += '<p class="hero_skill_title">' + lang('springshoppop_skill') + '</p>';
            html += '<p class="hero_skill hero_infop"><span>' + data.skill.name + '</span>：' + data.skill.description + '</p>';
            
            this.e.find('#springshop_info_main').html(html);
            html = null;
            this.e.find('#springshop_info_dom').css('display', 'block');
        },
        
        buyHero: function(gid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.e.find('#springshop_info_dom').css('display', 'none');
                        that.sid = gid;
                        that.listData = res.list;
                        that.fillData(res.list);
                        that.e.find('#springshop_gem3').html(res.get3);
                        that.e.find('#springshop_prestige').html(res.reputation);
                        if (res.msg == undefined || res.msg == '') {
                            xGame.toast.show(lang('buy_success'));
                            if(res.general) {
                                miscpop.newhero(res.general);
                            }
                        } else {
                            xGame.toast.show(res.msg);
                        }
                        
                        if (that.autoThread) {
                            clearTimeout(that.autoThread);
                            that.autoThread = null;
                        }
                        that.autoThread = setTimeout(function() {
                            if (springshoppop.e) {
                                springshoppop.autoRefresh();
                            }
                        }, 5000);
                        
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();

            };
            loadContents({
                'c': 'springshop',
                'm': 'buy',
                'id': gid
            },
            Func);
            Func = null;
        },
        
        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#springshop_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.itemDom) {
                this.itemDom.undelegate('.touchdiv', 'click');
                this.itemDom.undelegate('.pop_btn_middle ', 'click');
                this.itemDom.remove();
                this.itemDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('springshop', 0);
            this.sid = null;
            this.listData = null;
            if (this.autoThread) {
                clearTimeout(this.autoThread);
                this.autoThread = null;
            }
            
            if (this.itemScroller != undefined) {
                this.itemScroller.destroy();
                this.itemScroller = null;
            }
            this.unbindAll();            
        },
    };
})();