/* 海外贸易 */
(function() {
    overseastradePopClass = function() {
        this.e;
        
        this.mainDom;//主dom
        this.page;//当前页数
        this.pages;//当前总页数
        this.shipDom;//船只dom
        this.shipInfoDom;//船只信息dom
        
        this.shopDom;//外部商店弹窗
        
        this.teamDom;//外部组队弹窗
        this.tpage;//队伍页码
        this.tpages;//队伍总页码
        
        this.historyDom;//贸易历史弹窗
        this.hpage;//队伍页码
        this.hpages;//队伍总页码
        
        this.tradeTimes;//贸易次数
        this.robTimes;//抢劫次数
        this.shipData;//本地保存的船数组
        this.goodsData;//本地保存数据，显示货物信息
        this.myTeamId;//我的队伍，用来判断是否显示打劫
        
        this.refreshTeamThread;//自动刷新队伍
        this.refreshShipThread;//自动刷新船只
        
        this.myStatus;//自己当前的贸易状态  1贸易进行中，2完成贸易，3被抢劫 然后要在各个地方判断变化，默认值是0
        
        this.isshow = false;

        this.isTimeout = [];
        this.robBuyCost = 0; // 购买抢劫次数花费
        this.robBuyTimes = 0; // 剩余可购买抢劫次数
    };
    overseastradePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            var initRender = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    switch (res.status) {
                        case 2 :
                            xGame.toast.show(lang('no_country_no_func'));
                            break;
                        case 3 :
                            xGame.toast.show(lang('lv_not_enough'));
                            break;
                        case -1 :
                            xGame.toast.show(lang('get_user_info_fail'));
                            break;
                        case -2 :
                            xGame.toast.show(lang('get_team_info_fail'));
                            break;
                        case -3 :
                            xGame.toast.show(lang('team_info_error'));
                            break;
                        case -4 :
                            xGame.toast.show(lang('get_goods_info_fail'));
                            break;
                        default :
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'overseastrade',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
            
        },

        initFirst: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('overseastrade');
            that.mainDom = that.e.find('#overseastrade_page');
            that.shipDom = that.mainDom.find('#ship_dom');
            that.historyDom = that.e.find('#ot_history_dom');
            that.shipInfoDom = that.e.find('#shipinfo_dom');
            
            that.e.find('#overseastradepop_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                overseastradepop.hide();
                e.preventDefault();

            });
            
            //默认值
            that.myStatus = 0;
            that.myTeamId = 0;
            
            //只有等于3的时候才显示倒计时,也就代表在贸易中
            if (res.btn_status == 3) {
                xGame.updateCd('overseastrade', res.endtime);
                that.myStatus = 1;
            }
            
            that.tradeTimes = parseInt(res.info.times);
            that.showTradeTimes(that.tradeTimes);
            
            that.robTimes = parseInt(res.info.robtimes);
            that.showRobTimes(that.robTimes);
            that.robBuyCost = res.rob.price;
            that.robBuyTimes = res.rob.times;
            
            that.showBtnByStatus(res.btn_status);
            
            that.myTeamId = parseInt(res.info.team);
            that.page = 1;
            that.pages = parseInt(res.team.allpage);
            that.showMainPageNum(that.page);
            that.budildShip(res.team.list);
            that.bindAll();
            
            that.goodsData = {
                '1' : lang('foods'),
                '2' : lang('pigiron'),
                '3' : lang('silk'),
                '4' : lang('n_gold'),
                '5' : lang('agate')
            }
            
            that.showPageBtn();
            
            that.e.show();
            $('#worldloading').hide();
            
            that.refreshShipThread = setInterval(function() {
                that.showShipByPage();
            }, 12000);
            
            //1贸易进行中，2完成贸易，3被抢劫 然后要在各个地方判断变化，没有任何状态是0 这里1不提示了
            if (that.myStatus != res.trade) {
                if (res.trade == 2) {
                    xGame.confirm.show(lang('notice'), lang('leave_trade_complete' ,[that.getWan(res.get)]), 1,
                    function() {
                        xGame.confirm.hide()
                    });
                    player.updateinfo({
                        'silver':parseInt(res.all_silver)
                    });
                    if (that.tradeTimes > 0) {
                        that.showBtnByStatus(0);//开启按钮
                    }
                } else if (res.trade == 3) {
                    xGame.confirm.show(lang('notice'), lang('leave_trade_robbed'), 1,
                    function() {
                        xGame.confirm.hide()
                    });
                    player.updateinfo({
                        'silver':parseInt(res.all_silver)
                    });
                    if (that.tradeTimes > 0) {
                        that.showBtnByStatus(0);//开启按钮
                    }
                    xGame.updateCd('overseastrade', 0);
                }
                that.myStatus = res.trade;
            }
            
        },
        
        showPageBtn: function() {
            if (this.mainDom == null) {
                return false;
            }
            if (this.page == 1) {
                this.mainDom.find('#ot_prev').attr('class', 'page_btn_disable');
            } else {
                this.mainDom.find('#ot_prev').attr('class', 'page_btn');
            }
            
            if (this.page == this.pages) {
                this.mainDom.find('#ot_next').attr('class', 'page_btn_disable');
            } else {
                this.mainDom.find('#ot_next').attr('class', 'page_btn');
            }
        },
        
        /*根据状态显示按钮*/
        showBtnByStatus: function(status) {
            var btn1 = this.mainDom.find('#ot_buy');
            var btn2 = this.mainDom.find('#ot_start');
            if (status == 0) {//0可购买货物
                btn1.attr('class', 'btn pop_btn_middle');
                btn2.attr('class', 'btn pop_btn_middle_disable');
            } else if (status == 1 || status == 2) {//1可开始贸易//2等待中
                btn1.attr('class', 'btn pop_btn_middle_disable');
                btn2.attr('class', 'btn pop_btn_middle');
            } else if (status == 3 || status == 4) {//3正在贸易//4没次数了
                btn1.attr('class', 'btn pop_btn_middle_disable');
                btn2.attr('class', 'btn pop_btn_middle_disable');
            }
        },

        bindAll: function() {
            var that = this;
            
            that.mainDom.undelegate('#ot_rule', 'touchend').delegate('#ot_rule', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#rule_dom').css('display','block');
            });
            
            that.e.find('#rule_dom').undelegate('#rule_hide_btn', 'touchend').delegate('#rule_hide_btn', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.isTimeout.push(setTimeout(function() {
                    that.e.find('#rule_dom').css('display','none');
                },
                400));
            });
            
            that.mainDom.undelegate('#ot_buy.pop_btn_middle', 'touchend').delegate('#ot_buy.pop_btn_middle', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                
                that.showShop();
            });
            
            that.mainDom.undelegate('#ot_start.pop_btn_middle', 'touchend').delegate('#ot_start.pop_btn_middle', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showTrade();
            });
            
            that.mainDom.undelegate('#ot_prev.page_btn', 'touchend').delegate('#ot_prev.page_btn', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                
                that.showShipByPage(that.page - 1);
            });
            
            that.mainDom.undelegate('#ot_next.page_btn', 'touchend').delegate('#ot_next.page_btn', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                
                that.showShipByPage(that.page + 1);
            });
            
            that.mainDom.undelegate('#ot_history', 'touchend').delegate('#ot_history', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showTradeHistory();
            });
            
            that.historyDom.undelegate('.n', 'touchend').delegate('.n', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
            
            that.historyDom.undelegate('.viewbattle', 'touchend').delegate('.viewbattle', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.viewBattleFromOverseastrade($(this).attr('viewid'));
            });
            
            
            
            that.historyDom.undelegate('#ot_history_hide', 'touchend').delegate('#ot_history_hide', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.isTimeout.push(setTimeout(function() {
                    that.historyDomClose();
                },
                400));
            });
            
            that.historyDom.undelegate('#ot_hprev', 'touchend').delegate('#ot_hprev', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showTradeHistory(that.hpage - 1);
            });
            
            that.historyDom.undelegate('#ot_hnext', 'touchend').delegate('#ot_hnext', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showTradeHistory(that.hpage + 1);
            });
            
            that.shipDom.undelegate('div.ship', 'touchend').delegate('div.ship', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).hasClass('single')) {//单人贸易
                    that.showShipInfo(that.shipData[$(this).attr('index')], true);
                } else {//双人贸易
                    that.showShipInfo(that.shipData[$(this).attr('index')]);
                }
            });
            
            that.shipInfoDom.undelegate('.n', 'touchend').delegate('.n', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
            
            that.shipInfoDom.undelegate('#shipinfo_hide', 'touchend').delegate('#shipinfo_hide', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.shipInfoDom.css('display','none');
            });
            
            that.shipInfoDom.undelegate('#ot_rob.pop_btn_middle', 'touchend').delegate('#ot_rob.pop_btn_middle', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.robFunc($(this).attr('robid'));
            });

            // 购买打劫次数
            that.e.find('#overseastrade_buy_times').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('overseastradepop_rob_title'), lang('overseastradepop_rob_content', [that.robBuyCost, that.robBuyTimes, that.robTimes]), 2, function () {
                    that.buyRobTimes();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });
        },
        
        showMainPageNum: function(num) {
            this.mainDom.find('#ot_num').html(num);
        },
        
        showTradeTimes: function(num){
            this.mainDom.find('#ot_trade_times').html(num);
        },
        
        showRobTimes: function(num){
            this.mainDom.find('#ot_rob_times').html(num);
        },
        
        getWan: function (num) {
            return Math.floor(parseInt(num) / 10000) + lang('10k');
        },
        
        /*船翻页方法*/
        showShipByPage: function(page, callback){
            if (this.refreshShipThread == null) {
                return false;
            }
        
            if (page <= 0 ) {
                console.log('没有上一页');
                return false;
            } else if (page > this.pages) {
                console.log('没有下一页');
                return false;
            }
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                if (that.pages == null) {
                    return false;
                }
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    that.pages = res.team.allpage;
                    if (page != undefined) {
                        that.page = page;
                        that.showMainPageNum(that.page);
                    }
                    that.budildShip(res.team.list);
                    
                    
                    //1贸易进行中，2完成贸易，3被抢劫 然后要在各个地方判断变化，没有任何状态是0
                    if (that.myStatus != res.trade) {
                        if (res.trade == 1) {
                            xGame.confirm.show(lang('notice'), lang('your_trade_begin'), 1,
                            function() {
                                xGame.confirm.hide()
                            });
                            that.showBtnByStatus(3);//关闭按钮
                            if (that.teamDom != null) {
                                that.teamDomClose();
                            }
                            
                            if (res.endtime > 0) {
                                xGame.updateCd('overseastrade', res.endtime);
                            }
                        } else if (res.trade == 2) {
                            xGame.confirm.show(lang('notice'), lang('trade_complete_you_get', [that.getWan(res.get)]), 1,
                            function() {
                                xGame.confirm.hide()
                            });
                            if (that.tradeTimes > 0) {
                                that.showBtnByStatus(0);//开启按钮
                            }
                        } else if (res.trade == 3) {
                            xGame.confirm.show(lang('notice'), lang('your_trade_robbed'), 1,
                            function() {
                                xGame.confirm.hide()
                            });
                            if (that.tradeTimes > 0) {
                                that.showBtnByStatus(0);//开启按钮
                            }
                            xGame.updateCd('overseastrade', 0);
                        }
                        that.myStatus = res.trade;
                    }
                    
                    that.showPageBtn();
                    
                    if (callback != undefined) {
                        callback();
                    }
                } else {
                    if (page == undefined) {//如果是自动刷新，就不提示错误的信息了
                        return false;
                    }
                    if (res.status == 2) {
                        xGame.toast.show(lang('no_country_no_func'));
                    } else if (res.status == 3) {
                        xGame.toast.show(lang('lv_not_enough'));
                    } else if (res.status == -1) {
                        xGame.toast.show(lang('get_user_info_fail'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                }
            };
            
            if (page == undefined) {//只有自动刷新时用到
            
                loadContents({
                    'c': 'overseastrade',
                    'm': 'refresh',
                    'p': that.page,
                },
                Func, true);
            
            } else {//手动翻页时用到
                loadContents({
                    'c': 'overseastrade',
                    'm': 'refresh',
                    'p': page,
                },
                Func);
            }
            Func = null;
        },
        
        /*构建船*/
        budildShip: function(list) {
            this.shipData = list;
            var html = '';
            var cn = '';
            for (var i = 0;i < list.length; i ++) {
                if (list[i].member1 != 0 && list[i].member2 != 0) {//2个人的时候
                    if (list[i].member1 == player.info.uid || list[i].member2 == player.info.uid) {
                        cn = ' me';
                    } else {
                        cn = '';
                    }
                    html += '<div class="ship pos' + list[i].site + cn + '" index="' + i + '"><span class="left ship' + list[i].member1_commodity + '"></span><span class="right ship' + list[i].member2_commodity + '"></span><em>' + list[i].member1_name.substr(0,2) + '——' + list[i].member2_name.substr(0,2) + '</em></div>';
                } else {//1个人的时候
                    var user = list[i].member1 != 0 ? 'member1' : 'member2';
                    var uid = list[i].member1 != 0 ? list[i].member1 : list[i].member2;
                    if (uid == player.info.uid) {
                        cn = ' me';
                    } else {
                        cn = '';
                    }
                    html += '<div class="single ship pos' + list[i].site + cn + '" index="' + i + '"><span class="single ship' + list[i][user + '_commodity'] + '"></span><em>' + list[i][user + '_name'] + '</em></div>';
                    user = uid = null;
                }
            }
            cn = null;
            if (this.shipDom) {
            this.shipDom.html(html);
            }
        },
        
        /*商店pop绑定*/
        shopDomBind: function() {
            var that = this;
            that.shopDom.undelegate('#shop_hide_btn', 'touchend').delegate('#shop_hide_btn', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.shopDomClose();
            });
            
            that.shopDom.find('ul').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var id = $(this).attr('gid');
                xGame.confirm.show(lang('buy_goods'), lang('buy_goods_ask', [$(this).attr('name')]), 2, function() {
                    xGame.confirm.hide();
                    that.buyItem(id);
                }, function() {
                    xGame.confirm.hide();
                },lang('buy'),lang('close'));
                
            });
        },
        
        /*商店关闭方法*/
        shopDomClose: function() {
            this.shopDom.undelegate('#shop_hide_btn', 'touchend');
            this.shopDom.find('ul').undelegate('.pop_btn_small', 'touchend');
            this.shopDom.remove();
            this.shopDom = null;
        },
        
        /*获得商店数据*/
        showShop: function(){
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        
                        that.shopDom = xGame.loadPopTmpl('overseastradeshop');
                        that.shopDomBind();
                        that.buildShopList(res.list);
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('finish_buy_goods'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_goods_list_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'overseastrade',
                'm': 'item_list',
            },
            Func);
            Func = null;
        },
        
        /*组装商店数据*/
        buildShopList: function(list) {
            var html = '';
            for (var i = 0;i < list.length;i ++) {
                html += '<li>';
                html += '<p class="' + (list[i].buy_type == 1 ? 'silver' : 'gold') + '">' + list[i].buy_value + '</p>';
                html += '<p class="get silver">' + this.getWan(list[i].get) + '</p>';
                if (parseInt(list[i].need_vip) > parseInt(player.info.vip)) {
                    html += '<button class="btn pop_btn_small_disable">' + lang('vip_open', [list[i].need_vip]) + '</button>';
                } else {
                    html += '<button class="btn pop_btn_small" gid="' + list[i].id + '" name="' + list[i].name + '">' + lang('buy') + '</button>';
                }
                html += '</li>';
            }
            this.shopDom.find('ul').html(html);
            html = null;
        },
        
        /*商店购买方法*/
        buyItem: function(id){
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        player.updateinfo({
                            'silver':parseInt(res.all_silver), 
                            'gold':parseInt(res.all_gold)
                        });
                        that.shopDomClose();
                        that.showBtnByStatus(1);
                        xGame.toast.show(lang('after_buy_go_trade'));
                        
                        //只有第一次显示引导
                        if (xGame.xsGet('overseastrade_guide') == undefined) {
                            xGame.xsSet('overseastrade_guide','true');
                            guide.create('touchend', $('#ot_start'));
                        }
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('you_had_buy_goods'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_goods_info_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('vip_lv_too_low'));
                        break;
                    case -5:
                        xGame.toast.show(lang('silver_not_enough'));
                        break;
                    case -6:
                        xGame.toast.show(lang('gold_not_enough'));
                        break;
                    case -7:
                        xGame.toast.show(lang('no_enough_trade_times'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            
            loadContents({
                'c': 'overseastrade',
                'm': 'buy_item',
                'id': id
            },
            Func);
            Func = null;
            
        },
        
        /*组队pop绑定*/
        teamDomBind: function() {
            var that = this;
            that.teamDom.undelegate('#team_hide_btn', 'touchend').delegate('#team_hide_btn', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.teamDomClose();
            });
            
            that.teamDom.find('ul').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var type = $(this).attr('type');
                if (type == 'join') {
                    that.joinTeam($(this).attr('tid'), $(this).attr('site'), $(this).attr('place'), $(this).parent().parent());
                } else if (type == 'quit') {
                    that.quitTeam($(this).attr('place'), $(this).parent().parent());
                } else if (type == 'gold') {
                    xGame.confirm.show(lang('gold_trade'), lang('gold_trade_ask'), 2, function() {
                        xGame.confirm.hide();
                        that.singleTrade();
                    }, function() {
                        xGame.confirm.hide();
                    });
                }
            });
            
            that.teamDom.undelegate('#page_home', 'touchend').delegate('#page_home', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.tpage == 1){
                    console.log('已经是第一页');
                    return false;
                }
                
                that.showTrade(1);
            });
            
            that.teamDom.undelegate('#page_prev', 'touchend').delegate('#page_prev', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showTrade(that.tpage - 1);
            });
            
            that.teamDom.undelegate('#page_next', 'touchend').delegate('#page_next', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showTrade(that.tpage + 1);
            });
            
            that.teamDom.undelegate('#page_end', 'touchend').delegate('#page_end', 'touchend',
            function() {
                if(xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                
                if (that.tpage == that.tpages){
                    console.log('已经是最后一页');
                    return false;
                }
                
                that.showTrade(that.tpages);
            });
            
        },
        
        /*组队pop关闭方法*/
        teamDomClose: function() {
            this.teamDom.undelegate('#team_hide_btn', 'touchend');
            this.teamDom.find('ul').undelegate('.pop_btn_small', 'touchend');
            this.teamDom.undelegate('#page_home', 'touchend');
            this.teamDom.undelegate('#page_prev', 'touchend');
            this.teamDom.undelegate('#page_next', 'touchend');
            this.teamDom.undelegate('#page_end', 'touchend');
          
            clearInterval(this.refreshTeamThread);
            this.refreshTeamThread = null;
            
            this.teamDom.remove();
            this.teamDom = null;
        },
        
        /*发起贸易方法*/
        showTrade: function(page){
            if (page <= 0 ) {
                console.log('没有上一页');
                return false;
            } else if (page > this.tpages) {
                console.log('没有下一页');
                return false;
            }
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        if (page == undefined) {//刚进来,5s刷新
                            that.teamDom = xGame.loadPopTmpl('overseastradeteam');
                            that.teamDomBind();
                        
                            that.refreshTeamThread = setInterval(function() {
                                that.refreshTeamList();
                            }, 5000);
                            
                        }
                        
                        that.tpage = parseInt(res.page);
                        that.tpages = parseInt(res.allpage);
                        that.showTeamPage(that.tpage, that.tpages);
                        that.myTeamId = parseInt(res.teamid);
                        that.buildTeamList(res.list, res.price);
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_goods_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            if (page == undefined) {
            
                loadContents({
                    'c': 'overseastrade',
                    'm': 'get_list_by_country'
                },
                Func);
            
            } else {
                loadContents({
                    'c': 'overseastrade',
                    'm': 'get_list_by_country',
                    'p': page
                },
                Func);
            }
            Func = null;
            
        },
        
        /*刷新贸易队伍方法，只有成功，并且没有loading*/
        refreshTeamList: function(){
            if (this.refreshTeamThread == null) {
                return false;
            }
        
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            loadContents({
                'c': 'overseastrade',
                'm': 'get_list_by_country',
                'p': that.tpage
            },
            function(res) {
                xGame.unlock();
                if (res.status == 1) {
                    that.myTeamId = res.teamid;
                    that.buildTeamList(res.list, res.price);
                    if (res.trade == 1) {//判断组队成功后，强制刷新第一页
                        that.showShipByPage(1);
                    }
                }
            }, true);
        },
        
        /*加入队伍方法*/
        joinTeam: function(id, side, place, obj){
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            
            var site = side == 'left' ? 1 : 2;
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        if (res.price == 0) {//队伍满了可以直接开始
                            //更改按钮状态
                            that.showBtnByStatus(3);
                            that.tradeTimes = res.times;
                            that.showTradeTimes(that.tradeTimes);
                            that.teamDomClose();
                            //刷新船只,默认刷新第一页
                            that.showShipByPage(1);
                            xGame.toast.show(lang('your_trade_begin'));
                            that.myStatus = 1;//手动设置为贸易中
                            //设置teamid
                            that.myTeamId = res.team_id;
                            
                            xGame.updateCd('overseastrade', res.endtime);
                        } else {//自己变成退出，将旁边的改成元宝
                            that.teamDom.find('.pop_btn_small').attr('class', 'btn pop_btn_small_disable');
                            that.myTeamId = res.team_id;
                            var html = '';
                            //自己
                            html += '<span class="' + side + '"><p class="lv">Lv.' + player.info.level + '</p><em class="avatar ' + (player.info.gender == 1 ? 'male' : 'female') + '"></em><b class="' + side + ' good' + res.commodity + '"></b><p>' + player.info.nickname + '</p><button class="btn pop_btn_small" type="quit" place="' + place + '">' + lang('exit') + '</button></span>';
                            //隔壁
                            html += '<span class="' + (side == 'left' ? 'right' : 'left') + '"><em>' + lang('wait') + '</em><p class="gold">' + res.price + lang('gold') + '</p><button class="btn pop_btn_small" type="gold">' + lang('gold_trade') + '</button></span>';
                            obj.html(html);
                            html = null;
                            //xGame.toast.show('加入成功！');
                        }
                        
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('have_not_buy_goods'));
                        break;
                    case -3:
                        xGame.toast.show(lang('times_not_enough_to_trade'));
                        break;
                    case -4:
                        xGame.toast.show(lang('have_team_already'));
                        break;
                    case -5:
                        xGame.toast.show(lang('get_team_info_fail'));
                        break;
                    case -6:
                        xGame.toast.show(lang('join_site_has_person'));
                        break;
                    case -7:
                        xGame.toast.show(lang('not_your_country_m'));
                        break;
                    case -8:
                        xGame.toast.show(lang('canshu_lost'));
                        break;
                    case -9:
                        xGame.toast.show(lang('this_site_cannot_join'));
                        break;
                    case -10:
                        xGame.toast.show(lang('team_is_trading'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'overseastrade',
                'm': 'join_team',
                'id' : id,
                'site' : site,
                'page' : that.tpage,
                'place' : place
            },
            Func);
            Func = null;
            
        },
        
        /*退出队伍方法*/
        quitTeam: function(place, obj){
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        //要把旁边的和自己改成空的
                        var html = '';
                        html += '<span class="left"><em>' + lang('wait') + '</em><button class="btn pop_btn_small" type="join" tid="0" site="left" place="' + place + '">' + lang('join') + '</button></span>';
                        html += '<span class="right"><em>' + lang('wait') + '</em><button class="btn pop_btn_small" type="join" tid="0" site="right" place="' + place + '">' + lang('join') + '</button></span>';
                        obj.html(html);
                        html = null;
                        that.myTeamId = 0;
                        that.teamDom.find('.pop_btn_small_disable').attr('class', 'btn pop_btn_small');
                        //xGame.toast.show('退出成功！');
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_team_info_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('trade_had_began'));
                        break;
                    case -5:
                        xGame.toast.show(lang('quit_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'overseastrade',
                'm': 'goout',
            },
            Func);
            Func = null;
            
        },
        
        /*单人方法*/
        singleTrade: function(){
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        player.updateinfo({
                            'gold':parseInt(res.all_gold)
                        });
                        //更改按钮状态
                        that.showBtnByStatus(3);
                        that.tradeTimes = res.times;
                        that.showTradeTimes(that.tradeTimes);
                        that.teamDomClose();
                        //刷新船只,默认刷新第一页
                        that.showShipByPage(1);
                        xGame.toast.show(lang('your_trade_begin'));
                        that.myStatus = 1;//手动设置为贸易中
                        //设置teamid
                        that.myTeamId = res.team_id;
                        
                        xGame.updateCd('overseastrade', res.endtime);
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('have_not_join_a_team'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_team_info_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('team_is_trading'));
                        break;
                    case -5:
                        xGame.toast.show(lang('gold_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'overseastrade',
                'm': 'trade',
            },
            Func);
            Func = null;
            
        },
        
        showTeamPage: function(num1, num2) {
            this.teamDom.find('#page_num').html(num1 + '/' + num2);
        },
        
        /*组建队伍dom*/
        buildTeamList: function(list, price) {
            var html = '';
            for (var i = 1;i < 5;i ++) {
                html += '<li>';
                html += this.buildTeamInfo(list[i], price, i);
                html += '</li>';
            }
            this.teamDom.find('ul').html(html);
            html = null;
        },
        
        buildTeamInfo: function(data, price, place){
            var str = '';
            var d = ['left', 'right'];
            var sex = '';
            var user = ['member1', 'member2'];
            for (var i = 0; i < 2; i ++) {
                if (data == undefined ) {//全是空的,tid = 0
                    str += '<span class="' + d[i] + '"><em>' + lang('wait') + '</em><button class="btn pop_btn_small' + (this.myTeamId == 0 ? '' : '_disable') + '" type="join" tid="0" site="' + d[i] + '" place="' + place + '">' + lang('join') + '</button></span>';
                } else {
                    sex = data[user[i] + '_gender'] == 1 ? 'male' : 'female';
                    if (data[user[i]] == 0 && this.myTeamId != data.id) {//有一方是空的,并且不是自己的队伍，tid要获得值
                        str += '<span class="' + d[i] + '"><em>' + lang('wait') + '</em><button class="btn pop_btn_small' + (this.myTeamId == 0 ? '' : '_disable') + '" type="join" tid="' + data.id + '" site="' + d[i] + '" place="' + place + '">' + lang('join') + '</button></span>';
                    } else if (data[user[i]] == 0 && this.myTeamId == data.id) {//有一方是空的,并且是自己的队伍，显示元宝强行贸易
                        str += '<span class="' + d[i] + '"><em>' + lang('wait') + '</em><p class="gold">' + price + lang('gold') + '</p><button class="btn pop_btn_small" type="gold">' + lang('gold_trade') + '</button></span>';
                    } else if (data[user[i]] != player.info.uid) {//别人的队伍
                        str += '<span class="' + d[i] + '"><p class="lv">Lv.' + data[user[i] + '_level'] + '</p><em class="avatar ' + sex + '"></em><b class="' + d[i] + ' good' + data[user[i] + '_commodity'] + '"></b><p>' + data[user[i] + '_name'] + '</p></span>';
                    } else if (data[user[i]] == player.info.uid) {//自己
                        str += '<span class="' + d[i] + '"><p class="lv">Lv.' + data[user[i] + '_level'] + '</p><em class="avatar ' + sex + '"></em><b class="' + d[i] + ' good' + data[user[i] + '_commodity'] + '"></b><p>' + data[user[i] + '_name'] + '</p><button class="btn pop_btn_small" type="quit" place="' + place + '">' + lang('exit') + '</button></span>';
                    } 
                }
            }
            return str;
        },
        
        /*显示贸易记录*/
        showTradeHistory: function(page, callback) {
            if (page != undefined) {
                if (page <= 0 ) {
                    console.log('没有上一页');
                    return false;
                }
            }
        
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        
                        if (res.list.length == 0 && page != undefined) {
                            that.hpage = res.page;
                            that.hpages = res.page;
                            console.log('没有下一页');
                        } else {
                            that.buildHistoryList(res.list);
                            if (page != undefined) {
                                that.hpage = res.page;
                            } else {
                                that.hpage = 1;
                                that.historyDom.css('display','block');
                            }
                            that.showHistoryPage(that.hpage);
                        }
                        
                        if (callback != undefined) {
                            callback();
                        }
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            if (page != undefined) {
                loadContents({
                    'c': 'overseastrade',
                    'm': 'get_trade_log',
                    'p': page,
                },
                Func);
            } else {
                loadContents({
                    'c': 'overseastrade',
                    'm': 'get_trade_log',
                    'p': '1',
                },
                Func);
            }
            Func = null;
        },
        
        /*组装贸易记录*/
        buildHistoryList: function(list) {
            var html = '';
            var other = '';//队友的字符串
            var me = '';//自己的字符串
            var isSingle = false;//队友的uid，没有的话就是0
            for (var i = 0;i < list.length;i ++) {
                html += '<li class="t' + list[i].type + '">';
                if (list[i].member1 != 0 && list[i].member2 != 0) {
                    isSingle = false;
                    other = list[i].member1 == player.info.uid ? 'member2' : 'member1';
                    me = list[i].member1 == player.info.uid ? 'member1' : 'member2';
                } else {
                    isSingle = true;//代表只有一个人
                    other = list[i].member1 == 0 ? 'member2' : 'member1';
                }
                
                if (list[i].type == 1) {//单人贸易信息
                    html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_begin_trading');
                } else if (list[i].type == 2) {//多人贸易信息
                    html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_and_someone_trading', [list[i][other], list[i][other + '_name']]);
                } else if (list[i].type == 3) {//抢夺成功
                    if (!isSingle) {//有队友
                        html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_rob_2_and_get', [list[i][me], list[i][me + '_name'], list[i][other], list[i][other + '_name'], this.getWan(list[i].get)]);
                        if (list[i][me + '_report'] != 0 && list[i][other + '_report'] != 0) {//两个都不是空的
                            html += '<em class="viewbattle viewbattle1" viewid="' + list[i][me + '_report'] + '">' + lang('battle_report') + '1</em>';
                            html += '<em class="viewbattle viewbattle2" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '2</em>';
                        } else {
                            html += '<em class="viewbattle" viewid="' + (list[i][other + '_report'] == 0 ? list[i][me + '_report'] : list[i][other + '_report']) + '">' + lang('battle_report') + '</em>';
                        }
                    } else {//只有自己
                        html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_rob_1_and_get', [list[i][other], list[i][other + '_name'], this.getWan(list[i].get)]);
                        html += '<em class="viewbattle" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '</em>';
                    }
                } else if (list[i].type == 4) {//抢夺失败
                    if (!isSingle) {//有队友
                        html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_rob_2_fail', [list[i][me], list[i][me + '_name'], list[i][other], list[i][other + '_name']]);
                        if (list[i][me + '_report'] != 0 && list[i][other + '_report'] != 0) {//两个都不是空的
                            html += '<em class="viewbattle viewbattle1" viewid="' + list[i][me + '_report'] + '">' + lang('battle_report') + '1</em>';
                            html += '<em class="viewbattle viewbattle2" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '2</em>';
                        } else {
                            html += '<em class="viewbattle" viewid="' + (list[i][other + '_report'] == 0 ? list[i][me + '_report'] : list[i][other + '_report']) + '">' + lang('battle_report') + '</em>';
                        }
                    } else {//只有自己
                        html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_rob_1_fail', [list[i][other], list[i][other + '_name']]);
                        html += '<em class="viewbattle" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '</em>';
                    }
                } else if (list[i].type == 5) {//防守成功
                    if (!isSingle) {//有队友
                        html += '<em class="t">' + list[i].dateline + '</em><em class="n" uid="' + list[i]['attack_uid'] + '">' + list[i]['attack_nickname'] + '</em>' + lang('you_2_defense', [list[i][other], list[i][other + '_name']]);
                        if (list[i][me + '_report'] != 0 && list[i][other + '_report'] != 0) {//两个都不是空的
                            html += '<em class="viewbattle viewbattle1" viewid="' + list[i][me + '_report'] + '">' + lang('battle_report') + '1</em>';
                            html += '<em class="viewbattle viewbattle2" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '2</em>';
                        } else {
                            html += '<em class="viewbattle" viewid="' + (list[i][other + '_report'] == 0 ? list[i][me + '_report'] : list[i][other + '_report']) + '">' + lang('battle_report') + '</em>';
                        }
                    } else {//只有自己
                        html += '<em class="t">' + list[i].dateline + '</em><em class="n" uid="' + list[i]['attack_uid'] + '">' + list[i]['attack_nickname'] + '</em>' + lang('you_1_defense');
                        html += '<em class="viewbattle" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '</em>';
                    }
                } else if (list[i].type == 6) {//防守失败
                    if (!isSingle) {//有队友
                        html += '<em class="t">' + list[i].dateline + '</em><em class="n" uid="' + list[i]['attack_uid'] + '">' + list[i]['attack_nickname'] + '</em>' + lang('someone_rob_you_2_and_get', [list[i][other], list[i][other + '_name'], this.getWan(list[i][me + '_get'])]);
                        html += '<em class="viewbattle viewbattle1" viewid="' + list[i][me + '_report'] + '">' + lang('battle_report') + '1</em>';
                        html += '<em class="viewbattle viewbattle2" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '2</em>';
                    } else {//只有自己
                        html += '<em class="t">' + list[i].dateline + '</em><em class="n" uid="' + list[i]['attack_uid'] + '">' + list[i]['attack_nickname'] + '</em>' + lang('someone_rob_you_1_and_get', [this.getWan(list[i][other + '_get'])]);
                        html += '<em class="viewbattle" viewid="' + list[i][other + '_report'] + '">' + lang('battle_report') + '</em>';
                    }
                } else if (list[i].type == 7) {//贸易完成
                    //贸易成功，恭喜您获得
                    if (!isSingle) {//有队友
                        html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_2_finish_trade', [list[i][other], list[i][other + '_name'], this.getWan(list[i][me + '_get'])]);
                    } else {//只有自己
                        html += '<em class="t">' + list[i].dateline + '</em>' + lang('you_1_finish_trade', [this.getWan(list[i][other + '_get'])]);
                    }
                }
                
                html += '</li>';
            }
            this.historyDom.find('ul').html(html);
            html = null;
        },
        
        /*显示贸易记录页码*/
        showHistoryPage: function(num) {
            this.historyDom.find('#ot_hnum').html(num);
        },
        
        /*贸易记录关闭方法*/
        historyDomClose: function() {
            this.historyDom.css('display', 'none');
        },
        
        /*显示*/
        showShipInfo: function(data, isSingle) {
            //console.log(data);
            var that = this;
            var html = '';
            var html1 = '';
            var html2 = '';
            if (isSingle) {//如果是单人
                var single = data.member1 != 0 ? 'member1' : 'member2';
                var single_name = single + '_name';
                var single_commodity = single + '_commodity';
                single_commodity = data[single_commodity]
                html1 += '<li><span class="l">' + lang('trade_owner') + '</span><span class="n" uid="' + data[single] + '">Lv.' + data[single + '_level'] + '&nbsp;' + data[single_name] + '</span></li>';
                //html1 += '<li><span class="l"></span><span></span></li>';
                html2 += '<li><span class="l">' + lang('goods_type') + '</span><span><em style="color:' + helper.getItemColor(single_commodity) + '">' + that.goodsData[single_commodity] + '</em></span></li>';
                single = single_name = single_commodity = null;
            } else {//双人
                html1 += '<li><span class="l">' + lang('trade_owner') + '</span><span class="n" uid="' + data['member1'] + '">Lv.' + data['member1_level'] + '&nbsp;' + data['member1_name'] + '</span></li>';
                html1 += '<li><span class="l"></span><span class="n" uid="' + data['member2'] + '">Lv.' + data['member2_level'] + '&nbsp;' + data['member2_name'] + '</span></li>';
                
                html2 += '<li><span class="l">' + lang('goods_type') + '</span><span>';
                html2 += '<em style="color:' + helper.getItemColor(data['member1_commodity']) + '">' + that.goodsData[data['member1_commodity']] + '</em>';
                html2 += '&nbsp;+&nbsp;<em style="color:' + helper.getItemColor(data['member2_commodity']) + '">' + that.goodsData[data['member2_commodity']] + '</em>';
                html2 += '</span></li>';
            }
            html += html1;
            html += '<li><span class="l">' + lang('under_country') + '</span><span>' + data.country_name + '</span></li>';
            html += html2;
            html += '<li><span class="l">' + lang('rob_get') + '</span><span class="silver">x' + that.getWan(data.get) + '</span></li>';
            this.shipInfoDom.find('ul').html(html);
            if (that.myTeamId != data.id) {
                if(this.myStatus == 1 || this.robTimes == 0) {//贸易中或者抢劫次数为0就按钮灰掉
                    this.shipInfoDom.find('#ot_rob').attr('class', 'btn pop_btn_middle_disable');
                } else {
                    this.shipInfoDom.find('#ot_rob').attr('class', 'btn pop_btn_middle');
                }
                this.shipInfoDom.find('#ot_rob').attr('robid', data.id).css('display','block');
            } else {
                this.shipInfoDom.find('#ot_rob').css('display','none');
            }
            this.shipInfoDom.css('display','block');
            
            
            html1 = html2 = null;
            html = that = null;
        },
        
        /*打劫方法*/
        robFunc: function(id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        player.updateinfo({
                            'silver':parseInt(res.all_silver)
                        });
                        if (res.win == 1) {
                            xGame.toast.show(lang('rob_success_get', [that.getWan(res.get)]));
                        } else {
                            xGame.toast.show(lang('rob_fail_msg'));
                        }
                        that.robTimes = res.robtimes;
                        that.showRobTimes(that.robTimes);
                        that.shipInfoDom.css('display','none');
                        //刷新船只,默认刷新当前页
                        that.showShipByPage(that.page, function() {
                            that.showTradeHistory(null, function() {
                                xGame.doAnimate2(overseastradepop.historyDom.find('ul').find('li').first(), 'changcolor', 100, 7);
                            });
                        });
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('no_country_no_func'));
                        break;
                    case 3:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('rob_times_not_enough'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_team_info_fail'));
                        break;
                    case -4:
                        xGame.toast.show(lang('team_no_trade'));
                        break;
                    case -5:
                        xGame.toast.show(lang('team_trade_over'));
                        break;
                    case -6:
                        xGame.toast.show(lang('team_info_error'));
                        break;
                    case -7:
                        xGame.toast.show(lang('get_goods_info_fail'));
                        break;
                    case -8:
                        xGame.toast.show(lang('team_is_robbed'));
                        break;    
                    case -9:
                        xGame.toast.show(lang('can_not_rob_self'));
                        break;
                    case -10:
                        xGame.toast.show(lang('you_are_trading_x_rob'));
                        break;
                    case -11:
                        xGame.toast.show(lang('overseastradepop_try_later'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'overseastrade',
                'm': 'rob',
                'id': id,
            },
            Func);
            Func = null;
            
        },

        // 购买打劫次数
        buyRobTimes: function () {
            
            var that = this;

            if (parseInt(that.robBuyTimes) <= 0) {
                xGame.toast.show(lang('overseastradepop_err2'));
                return false;
            }

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                switch (parseInt(res.status)) {
                    case 1:
                        that.robTimes = res.robtimes;
                        that.robBuyCost = res.gold;
                        that.robBuyTimes = res.times;
                        player.updateinfo({
                            'gold': res.all_gold
                        });
                        that.showRobTimes(that.robTimes);
                        xGame.confirm.show(lang('overseastradepop_rob_title'), lang('overseastradepop_rob_content', [that.robBuyCost, that.robBuyTimes, that.robTimes]), 2, function () {
                            that.buyRobTimes();
                        }, function () {
                            xGame.confirm.hide();
                        });
                        break;
                    case -1:
                        xGame.toast.show(lang('overseastradepop_err1'));
                        break;
                    case -2:
                        xGame.toast.show(lang('overseastradepop_err2'));
                        break;
                    case -3:
                        xGame.toast.show(lang('overseastradepop_err3'));
                        break;
                    case -4:
                        xGame.toast.show(lang('gold_not_enough_for_buying'));
                        break;
                    case -5:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -6:
                        xGame.toast.show(lang('vip_lv_too_low'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'overseastrade',
                'm': 'buy'
            }, render);
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#overseastradepop_hidebtn').unbind('touchend');
                this.e.find('#rule_dom').undelegate('#rule_hide_btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.mainDom) {
                this.mainDom.undelegate('#ot_rule', 'touchend');
                this.mainDom.undelegate('#ot_buy.pop_btn_middle', 'touchend');
                this.mainDom.undelegate('#ot_start.pop_btn_middle', 'touchend');
                this.mainDom.undelegate('#ot_prev.page_btn', 'touchend');
                this.mainDom.undelegate('#ot_next.page_btn', 'touchend');
                this.mainDom.undelegate('#ot_history', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
            if (this.historyDom) {
                this.historyDom.undelegate('.n', 'touchend');
                this.historyDom.undelegate('.viewbattle', 'touchend');
                this.historyDom.undelegate('#ot_history_hide', 'touchend');
                this.historyDom.undelegate('#ot_hprev', 'touchend');
                this.historyDom.undelegate('#ot_hnext', 'touchend');
                this.historyDom.remove();
                this.historyDom = null;
            }
            if (this.shipDom){
                this.shipDom.undelegate('div.ship', 'touchend');
                this.shipDom.remove();
                this.shipDom = null;
            }
            if (this.shipInfoDom){
                this.shipInfoDom.undelegate('.n', 'touchend');
                this.shipInfoDom.undelegate('#shipinfo_hide', 'touchend');
                this.shipInfoDom.undelegate('#ot_rob.pop_btn_middle', 'touchend');
                this.shipInfoDom.remove();
                this.shipInfoDom = null;
            }
        },
        
        hide: function() {

            xGame.unlock();

            this.isshow = false;
            xGame.updateCd('overseastrade', 0);
            
            if (this.refreshTeamThread == null) {
                clearInterval(this.refreshTeamThread);
                this.refreshTeamThread = null;//自动刷新队伍
            }
            
            if (this.refreshShipThread == null) {
                clearInterval(this.refreshShipThread);
                this.refreshShipThread = null;
            }
            
            this.page = null;
            this.pages = null;
            
            this.tpage = null;
            this.tpages = null;
            
            this.hpage = null;
            this.hpages = null;
            
            this.tradeTimes = null;
            this.robTimes = null;
            this.shipData = null;
            this.goodsData = null;
            this.myTeamId = null;
            
            this.myStatus = null;
            this.robBuyCost = 0;
            this.robBuyTimes = 0;
            
            if (this.shopDom != null) {
                this.shopDomClose();
            }
            
            if (this.teamDom != null) {
                this.teamDomClose();
            }
            this.unbindAll();            
          
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            player.reloadInfo();
        }
    };
})();