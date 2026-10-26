/* 贸易中心 */
(function() {
    tradePopClass = function() {
        this.e;
        this.isshow = false;

        this.gemDom;
        this.infoDom;
        this.traderDom;
        this.ButtonDom;

        this.siteInfo;

        this.gem1; //宝石123
        this.gem2;
        this.gem3;

        this.site; //当前位置

        this.siteList; //当前的位置数组，有的用1表示，没有用0表示
        this.isfree;
        this.tradepop_wrap;
        this.isTimeout = [];
    };
    tradePopClass.prototype = {
        init: function() {
            var that = this;

            /*初始化变量*/
            that.gem1 = that.gem2 = that.gem3 = 0;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            if (xGame.checkLock()) {
                return false;
            }

            var initRender = function(res) {

                xGame.unlock();

                if (parseInt(res.status) != 1) {
                    xGame.toast.show(lang('get_data_fail'));
                    $('#worldloading').hide();
                    return;
                }

                that.initList(res);

            };
            loadContents({
                'c': 'tavern',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        initList: function(data) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('trade');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                tradepop.hide();
                e.preventDefault();
            });

            that.e.find('.content').find('#goto_bar').unbind('touchend').bind('touchend',
            function() {
                that.hide();
                xGame.showpop('bar');
            });

            /*给宝石赋值*/
            that.gemDom = that.e.find('#gem_list');

            that.showGem(1, data.get1);
            that.showGem(2, data.get2);
            that.showGem(3, data.get3);

            that.tradepop_wrap = that.e.find('#tradepop_wrap');

            /*显示商人信息*/
            that.infoDom = that.e.find('#trader_info');

            that.site = data.site || 1;
            that.siteInfo = data.site_info || {};
            if (that.siteInfo && that.siteInfo[that.site]) {
                that.showMsgByData(that.siteInfo[that.site]);
            }
            that.traderDom = that.e.find('#trader_list');

            that.siteList = [];
            for (var i = 1; i < 6; i++) {
                that.siteList.push(data['site' + i] || 0);
            }
            that.showImgBySiteInfo(data);
            that.showLeftByList(that.siteList);

            /*显示贸易按钮并且绑定*/
            that.ButtonDom = that.e.find('#trade_func');
            if (parseInt(player.info.vip) >= 1) {
                that.ButtonDom.find('#l').css('display', 'block');
                var tradingCost = (that.siteInfo && that.siteInfo[that.site]) ? that.siteInfo[that.site].trading : 20;
                that.showCost1(tradingCost);
                that.ButtonDom.find('#l').undelegate('button', 'touchend').delegate('button', 'touchend',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    that.tradeFunc(1);
                });
            }

            // 显示批量贸易按钮
            that.ButtonDom.find('#c').css('display', 'block');
            that.ButtonDom.find('#c').undelegate('button', 'touchend').delegate('button', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.batchTradeFunc();
            });

            /*判断是否免费,1为有免费，0为没免费*/
            that.isfree = parseInt(data.isfree);
            if (that.isfree == 1) {
                that.ButtonDom.find('#r').find('.trade_func_p').css('display', 'none');
                that.ButtonDom.find('#r').find('button').html(lang('free_trade'));
            }

            that.ButtonDom.find('#r').css('display', 'block');
            that.showCost2(that.siteInfo[that.site].cost);

            that.ButtonDom.find('#r').undelegate('.trade_func_btn', 'touchend').delegate('.trade_func_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.tradeFunc();
            });

            that.traderDom.undelegate('img', 'touchend').delegate('img', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.site = $(this).attr('site');
                that.traderDom.attr('class', 'trader_list site' + that.site);
                that.showCost2(that.siteInfo[that.site].cost);
                that.showCost1(that.siteInfo[that.site].trading);
                that.showMsgByData(that.siteInfo[that.site]);
            });

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();

        },

        showLeftByList: function(list) {
            for (var i = 0; i < list.length; i++) {
                if (i < 4) {
                    if (list[i] == 1 && list[i + 1] == 1) {
                        this.e.find('#dd' + (i + 1)).attr('class', 'dd show');
                    } else {
                        this.e.find('#dd' + (i + 1)).attr('class', 'dd');
                    }
                }
            }
        },

        showGem: function(gemid, num) {
            var that = this;

            num = parseInt(num);

            that['gem' + gemid] = num;

            that.gemDom.find('#gem' + gemid).html(num);
        },

        showMsgByData: function(data) {
            var that = this;
            data = data || (that.siteInfo ? that.siteInfo[1] : null) || { name: '走方游医', description: '初涉江湖的小商贾' };
            that.infoDom.find('span').html(data.name || '');
            that.infoDom.find('p').html(data.description || '');
        },

        showCost1: function(money) {
            var that = this;
            that.ButtonDom.find('#cost_gold').html(money);
        },

        showCost2: function(money) {
            var that = this;
            that.ButtonDom.find('#cost_silver').html(money);
        },

        tradeFunc: function(type) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.goPay(lang('no_gold_to_trade'));
                    xGame.unlock();
                    break;
                case - 2 : xGame.toast.show(lang('no_silver_to_trade'));
                    xGame.unlock();
                    break;
                case 1:

                    player.taskaction(11);

                    /*更新免费状态*/
                    if (that.isfree == 1 && (res.isfree == undefined  || (res.isfree != undefined && res.isfree == 0))) {
                        that.ButtonDom.find('#r').find('.trade_func_p').css('display', 'inline-block');
                        that.ButtonDom.find('#r').find('.trade_func_btn').html(lang('trade'));
                        that.isfree = 0;
                    }
                    /*更新各种数据*/
                    that.showCost1(res.trading);
                    that.showCost2(res.cost);
                    player.updateinfo({
                        'gold': res.gold,
                        'silver': res.silver
                    });

                    if (res.addnum > 0) {
                        $('<span id="gem_down" class="pos' + that.site + ' gem' + res.addtype + '"></span>').appendTo(that.tradepop_wrap).one('webkitAnimationEnd',
                        function() {
                            $(this).remove();
                        });

                        if (res.addnum > 1) {
                            $('<span id="gem_down" style="margin-left:50px;" class="pos' + that.site + ' gem' + res.addtype + '"></span>').appendTo(that.tradepop_wrap).one('webkitAnimationEnd',
                            function() {
                                $(this).remove();
                            });
                        }
                    }

                    that.isTimeout.push(setTimeout(function() {
                        that.site = parseInt(res.site) || 1;
                        that.showImgBySiteInfo(res);
                        that.showGem(res.addtype, res.number);
                        that.showMsgByData(that.siteInfo ? that.siteInfo[that.site] : null);
                        //xGame.toast.show('<span class="gem' + res.addtype + '">&nbsp;&nbsp;+&nbsp;&nbsp;' +  res.addnum + '</span>');
                        that.showLeftByList(that.siteList);
                        xGame.unlock();
                    },
                    100));

                    break;
                }

            };

            if (type == undefined) {
                loadContents({
                    'c': 'tavern',
                    'm': 'trade',
                    's': that.site
                },
                Func);
            } else {
                loadContents({
                    'c': 'tavern',
                    'm': 'trade',
                    'type': type,
                    's': that.site
                },
                Func);
            }
            Func = null;

        },

        // 批量贸易
        batchTradeFunc: function(){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case - 2 : xGame.toast.show(lang('silver_not_enough'));
                        xGame.unlock();
                        break;
                    case 1:
                        //更新宝石和银币
                        that.showGem(1, res.info[1]);
                        that.showGem(2, res.info[2]);
                        that.showGem(3, res.info[3]);

                        /*更新免费状态*/
                        if (that.isfree == 1 && (res.isfree == undefined  || (res.isfree != undefined && res.isfree == 0))) {
                            that.ButtonDom.find('#r').find('.trade_func_p').css('display', 'inline-block');
                            that.ButtonDom.find('#r').find('.trade_func_btn').html(lang('trade'));
                            that.isfree = 0;
                        }

                        that.site = res.site;
                        that.showCost1(that.siteInfo[that.site].trading);
                        that.showCost2(that.siteInfo[that.site].cost);

                        that.showImgBySiteInfo(res);
                        that.showMsgByData(that.siteInfo[that.site]);
                        that.showLeftByList(that.siteList);

                        player.taskaction(11, true);

                        player.updateinfo({
                            'silver' : parseInt(res.silver)
                        });
                        that.rewardDown(res.get);
                        break;
                    default: xGame.toast.show(lang('undefined_error'));
                        xGame.unlock();
                        break;
                }
            };

            if(player.info.silver < 10000000) { //判断银币不足
                xGame.toast.show(lang('silver_not_enough'));
                xGame.unlock();
            } else {
                loadContents({
                    'c': 'tavern',
                    'm': 'trade_batch',
                },
                Func);
            }
            Func = null;
        },

        // 显示贸易结果
        rewardDown: function(get) {

            var that = this;
            var html = '';
            html += '<div class="trade_reward_box">';
            html += '<span class="reward_down pos1 gem1">x' + get.get1 + '</span>';
            html += '<span class="reward_down pos2 gem2">x' + get.get2 + '</span>';
            html += '<span class="reward_down pos3 gem3">x' + get.get3 + '</span>';
            html += '</div>';
            that.e.find('.content').append(html);

            var i = 0;
            var dom_trade_reward_box = that.e.find('.trade_reward_box');
            dom_trade_reward_box.unbind('webkitAnimationEnd').bind('webkitAnimationEnd',function(){
                if(i == 2) {
                    that.isTimeout.push(setTimeout(function() {
                        dom_trade_reward_box.remove();
                        dom_trade_reward_box = null;
                        xGame.unlock();
                    },
                    1000));
                } else {
                    i++;
                }
            });
        },


        showImgBySiteInfo: function(data) {
            var that = this;
            for (var i = 1; i <= 5; i++) {
                if (data['site' + i] == 1) {
                    that.traderDom.find('#trader' + i).html('<img src="' + staticUrl + 'img/pop/tradepop_role' + i + '.jpg' + '" site="' + i + '"/><span class="jt' + i + '"></span>');
                    that.siteList[i - 1] = 1;
                } else {
                    that.traderDom.find('#trader' + i).html('');
                    that.siteList[i - 1] = 0;
                }
            }
            that.traderDom.attr('class', 'trader_list site' + that.site);
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('.content').find('#goto_bar').unbind('touchend');
                this.e.find('.trade_reward_box').unbind('webkitAnimationEnd');
                this.e.remove();
                this.e = null;
            }
            if (this.ButtonDom) {
                this.ButtonDom.find('#l').undelegate('button', 'touchend');
                this.ButtonDom.find('#c').undelegate('button', 'touchend');
                this.ButtonDom.find('#r').undelegate('.trade_func_btn', 'touchend');
                this.ButtonDom.remove();
                this.ButtonDom = null;
            }
            if (this.traderDom) {
                this.traderDom.undelegate('img', 'touchend');
                this.traderDom.remove();
                this.traderDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;

            this.unbindAll();
            if (this.gemDom) {
                this.gemDom.remove();
                this.gemDom = null;
            }
            if (this.infoDom) {
                this.infoDom.remove();
                this.infoDom = null;
            }
            if (this.tradepop_wrap) {
                this.tradepop_wrap.remove();
                this.tradepop_wrap = null;
            }
            this.site = this.gem1 = this.gem2 = this.gem3 = this.siteInfo = this.siteList = null;
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
        },
    };
})();