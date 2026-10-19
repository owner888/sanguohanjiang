/* 限售神将 */
(function() {
    limitedgodPopClass = function() {
        this.e;
        this.isshow = false;
        this.mainDom;
        this.heroData;//武将信息

        this.refreshThread;
    };

    limitedgodPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            //请求数据
            var Func = function(res) {
                if (res.status == 1) {
                    that.preparePage();
                    that.buildpage(res);
                } else {
                    switch (parseInt(res.status)) {
                        case - 4 : 
                            xGame.toast.show(lang('get_jade_info_fail'));
                            break;
                        case - 3 : 
                            xGame.toast.show(lang('get_hero_info_fail'));
                            break;
                        case - 2 : 
                            xGame.toast.show(lang('warena_exploit_not_enough_for_buy'));
                            break;
                        case - 1 : 
                            xGame.toast.show(lang('activity_closed'));
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                    }
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'limitedshop',
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

            that.e = xGame.loadPopTmpl('limitedgod');

            //绑定关闭事件
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                limitedgodpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            
            that.mainDom = that.e.find('#main_dom');
            $('#worldloading').hide();
        },

        /*组装数据*/
        buildpage: function(res) {
            var that = this;

            //倒计时
            xGame.updateCd('limitedgod', res.cd);
            
            //宝石数据
            that.e.find('.get3').html(res.jade[3]);
            that.e.find('.getr').html(res.reputation);

            var html = '';
            for (var i = 0;i < res.list.length;i ++) {
                html += '<div class="general_touch" index="' + i + '" cn="' + (i == 1 ? 'left' : 'right') + '"></div>';
                html += '<div id="limitedgod_left">' + res.list[i].num + '</div>';
                html += '<div id="limitedgod_oldprice_wrap" class="limitedgod_price_wrap"><span class="get3">' + res.list[i].cost + '</span><span class="getr">' + res.list[i].reputation + '</span></div>';
                html += '<div class="limitedgod_red_line"></div>';
                html += '<div id="limitedgod_newprice_wrap" class="limitedgod_price_wrap"><span class="get3">' + res.list[i].now + '</span><span class="getr">' + res.list[i].now_reputation + '</span></div>';
                html += '<div id="limitedgod_props_btn" class="pop_btn_small btn" index="' + i + '">'+lang('properties')+'</div>';
                if (res.list[i].isown == 1) {
                    html += '<button id="limitedgod_buy_btn" class="btn pop_btn_middle_disable" gid="' + res.list[i].id + '">' + lang('hero_had_buy') + '</button>';
                } else {
                    html += '<button id="limitedgod_buy_btn" class="btn pop_btn_middle" gid="' + res.list[i].id + '">' + lang('buy') + '</button>';
                }
            }
            that.mainDom.html(html);
            html = null;
            
            that.heroData = res.list;

            that.bindAll();
            that.autoRefresh();
        },

        /*组装数据*/
          /*
        buildpage: function(res) {
            var that = this;

            //倒计时
            xGame.updateCd('limitedgod', res.cd);
            
            //宝石数据
            for (var i = 1; i <= 3; i++) {
                //that.e.find('.get' + i).html(res.jade[i]);
            }
            var html = '';
            for (var i = 0;i < res.list.length;i ++) {
                html += '<div class="general">';
                html += '<div class="general_touch" index="' + i + '" cn="' + (i == 1 ? 'left' : 'right') + '"></div>';
                html += '<span class="number">' + res.list[i].num + '</span>';
                html += '<p class="old_price">' + lang('original_price') + '<span class="gem' + res.list[i].type + '"><em>' + res.list[i].cost + '</em></span></p>';
                html += '<div class="red_line"></div>';
                html += '<p class="now_price">' + lang('now_price') + '<span class="gem' + res.list[i].type + '"><em>' + res.list[i].now + '</em></span></p>';
                if (res.list[i].isown == 1) {
                    html += '<button class="btn pop_btn_middle_disable" gid="' + res.list[i].id + '">' + lang('hero_had_buy') + '</button>';
                } else {
                    html += '<button class="btn pop_btn_middle" gid="' + res.list[i].id + '">' + lang('buy') + '</button>';
                }
                html += '</div>';
            }
            that.mainDom.html(html);
            html = null;
            
            that.heroData = res.list;

            that.bindAll();
        },
            */

        bindAll: function() {
            var that = this;
            
            that.mainDom.undelegate('#limitedgod_props_btn', 'touchend').delegate('#limitedgod_props_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                //that.e.find('#general_info').attr('class' ,$(this).attr('cn'));
                that.buildinfo(that.heroData[$(this).attr('index')].info);
            });
            
            that.mainDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
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
            
            that.e.find('#general_info').undelegate('button', 'touchend').delegate('button', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#general_info').css('display','none');
            });
            
            
        },

        /*单个信息*/
        buildinfo: function(data) {
            var dom = this.e.find('#general_info');
            
            dom.find('#name').html(data.name);
            dom.find('#wuli').html(data.wuli);
            dom.find('#zili').html(data.zhili);
            dom.find('#tili').html(data.tili);
            dom.find('#bar_hero_star').css({
                'width': 18 * parseInt(data.star) + 'px'
            });
            dom.find('#skill').html(data.skill.name + ':' + data.skill.description);
            dom.find('#talent').html(data.talent.name + ':' + data.talent.description);

            dom.css('display','block');
            dom = null;
        },

        /* 自动刷新数量 */
        autoRefresh: function(myInterval) {
            var that = this;

            var myInterval = myInterval ? myInterval : 5000;

            that.refreshThread = setTimeout(function() {
                var render = function(res) {
                    if(res.list && that.e) {
                        for (var i = 0;i < res.list.length;i ++) {
                            that.e.find('#limitedgod_left').html(res.list[i].num);
                            if(res.list[i].num < 0) {
                                that.e.find('#limitedgod_buy_btn').attr('class', 'pop_btn_middle_disable btn');
                            }
                        }
                    }
                    that.autoRefresh();
                };
                loadContents({
                    'c': 'limitedshop',
                    'm': 'index'
                },
                render, true, function() {
                    console.log('压力过大，获取时间拉长');
                    that.autoRefresh(10000);
                }); //加载数据
            }, myInterval);
            
        },

        /*购买*/
        buy: function(obj) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case - 6 : 
                        xGame.toast.show(lang('add_hero_fail'));
                        break;
                    case - 5 : 
                        xGame.toast.show(lang('buy_hero_fail'));
                        break;
                    case - 4 : 
                        xGame.toast.show(lang('deduct_jade_fail'));
                        break;
                    case - 3 : 
                        xGame.toast.show(lang('buy_fail'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('get_hero_info_fail'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case 5:
                        xGame.toast.show(lang('newbar_reputation_not_enough'));
                        break;
                    case 4:
                        xGame.toast.show(lang('christmasgeneral_no_num'));
                        break;
                    case 3:
                        xGame.toast.show(lang('jade_not_enough_for_hero'));
                        break;
                    case 2:
                        xGame.toast.show(lang('had_this_hero'));
                        break;
                    case 1:
                        xGame.updateCd('limitedgod', res.cd);
                        obj.attr('class', 'pop_btn_middle_disable btn').html(lang('hero_had_buy'));
                        that.e.find('#limitedgod_left').html(res.num);
                        that.e.find('.get3').html(res.jade[3]);
                        that.e.find('.getr').html(res.reputation);
                        miscpop.newhero(res.general);
                        if (CD['chat'] != undefined) {
                            CD['chat'].read();
                        }
                        clearTimeout(that.refreshThread);
                        that.autoRefresh();
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'limitedshop',
                'm': 'buy_general',
                'id': obj.attr('gid')
            },
            Func); //加载数据
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#general_info').undelegate('button', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.mainDom) {
                this.mainDom.undelegate('.general_touch', 'touchend');
                this.mainDom.undelegate('.pop_btn_middle', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('limitedgod', 0);

            if(this.refreshThread) {
                clearTimeout(this.refreshThread);
                this.refreshThread = null;
            }

            this.list = null;
            this.unbindAll();
        },
    };
})();