/* 英雄王座 */
(function() {
    herothroneWorldClass = function() {
        this.e = null;

        this.topListDom;
        this.floorDom;

        this.freeTimes;
        this.buyTimes;

        this.rewardData;
        this.myStage;//当前已经闯过的关卡数
        this.price;
        
        this.myScroller;
        this.maxStage;
        this.rewardScroller = null;
    };

    herothroneWorldClass.prototype = {
        init: function(gotoFloor) {

            var that = this;

            if (gotoFloor) {
                that.startFuc();
                return false;
            }

            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        if (that.e == null) {
                            //初始化数据
                            that.initPage();
                            $('#worldloading').removeClass('black').hide();
                        }

                        if (res.buy == 1) {
                            that.topListDom.find('#lits_dom_right').find('.l3').html('<button class="btn pop_btn_small">' + lang('buy') + '</button>');
                        }

                        that.price = res.price;

                        that.buildTopList(res);
                        that.e.attr('class', 'pop_page top_list');

                        that.e.addClass('floor' + Math.min((Math.floor(parseInt(res.info.laststage) / 20) + 1), 5));
                        that.myScrollerRefresh();
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case 3:
                        that.price = res.price;
                        that.startFuc();
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

                if (res.status != 1 && res.status != 3) {
                    $('#worldloading').hide();
                    xGame.loadWorld('city');
                }
            };
            loadContents({
                'c': 'herothrone',
                'm': 'index',
            },
            initRender, true);
            initRender = null;

        },

        initPage: function(res) {
            if (this.e != null) {
                return false;
            }

            $('#header').hide();
            $('#footer').hide();

            var dom_world = $('#world');

            dom_world.append($.template('herothroneworld_tmpl'));

            this.e = $('#herothroneworld_page');
            this.topListDom = this.e.find('#top_list');
            this.floorDom = this.e.find('#floor');

            this.e.find('#herothroneworld_back').unbind('touchend').bind('touchend',
            function(e) {
                xGame.loadWorld('city');
                e.preventDefault();
            });

            this.rewardScroller = new iScroll('herothrone_reward_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.bindAll();

            dom_world = null;

        },

        bindAll: function() {
            var that = this;

            //显示规则
            that.topListDom.undelegate('#view_rule', 'touchend').delegate('#view_rule', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#rule_dom').css('display','block');
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.undelegate('#rule_hide_btn', 'touchend').delegate('#rule_hide_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#rule_dom').css('display','none');
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.undelegate('#reward_hide_btn', 'touchend').delegate('#reward_hide_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#reward_dom').css('display','none');
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.undelegate('#herothronewfloor_back', 'touchend').delegate('#herothronewfloor_back', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.goBack();
                e.preventDefault();
                e.stopPropagation();
            });

            that.topListDom.undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.confirm.show(lang('buy'), lang('buy_1times_ask',[that.price]), 2,
                function() {
                    that.buyTimesFuc();
                },
                function() {
                    xGame.confirm.hide();
                });
                e.preventDefault();
                e.stopPropagation();
            });


            that.topListDom.undelegate('#start_btn.pop_btn_middle', 'touchend').delegate('#start_btn.pop_btn_middle', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.startFuc();
                e.preventDefault();
                e.stopPropagation();
            });

            that.floorDom.find('#box_dom').undelegate('.box', 'click').delegate('.box', 'click',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showBoxInfo($(this).attr('bid'));
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.undelegate('#boxinfo_hide', 'touchend').delegate('#boxinfo_hide', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#boxinfo_dom').css('display','none');
                e.preventDefault();
                e.stopPropagation();
            });

            that.floorDom.undelegate('#end_btn', 'touchend').delegate('#end_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.confirm.show(lang('end_battle'), lang('end_battle_ask'), 2,
                function() {
                    xGame.confirm.hide();
                    that.endFuc();
                },
                function() {
                    xGame.confirm.hide();
                });
                e.preventDefault();
                e.stopPropagation();
            });

            that.floorDom.find('#herothrone_team').bind('touchend', function (e) {
             
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.showpop('team');
                e.preventDefault();
            });

            that.floorDom.undelegate('#buy_btn', 'touchend').delegate('#buy_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.confirm.show(lang('buy'), lang('buy_1times_ask',[that.price]), 2,
                function() {
                    that.buyTimesFuc();
                },
                function() {
                    xGame.confirm.hide();
                });
                e.preventDefault();
                e.stopPropagation();
            });

            that.floorDom.undelegate('#continue_btn', 'touchend').delegate('#continue_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.battleFunc();
                e.preventDefault();
                e.stopPropagation();
            });

            that.floorDom.undelegate('#battle_btn.pop_btn_middle', 'touchend').delegate('#battle_btn.pop_btn_middle', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.battleFunc();
                e.preventDefault();
                e.stopPropagation();
            });
        },

        showBoxInfo: function(id) {
            var obj = this.e.find('#boxinfo_dom');
            obj.find('#box_name').html(lang('guan_box', [id]));
            obj.find('#box_reward').html(this.buildReward(this.rewardData[id]));
            obj.css('display','block');
            obj = null;
        },

        buildReward: function(data) {
            //console.log(data);
            var html = '';
            for (var i = 1;i < 4;i ++) {
                if (data['gettype' + i] != 0) {
                    switch (parseInt(data['gettype' + i])) {
                        case 1://银币
                            html += '<em class="wuzi silver">' + lang('silver') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 2://玉石
                            html += '<em class="wuzi jade">' + lang('jade') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 3://装备
                            html += '<em style="color:' + helper.getItemColor(data['getvalue' + i].quality) + ';background-image:url('+ xGame.itemurl(data['getvalue' + i].id) +');">' + data['getvalue' + i].name + ' x 1</em>';
                            break;
                        case 4://装备碎片
                            html += '<em style="color:' + helper.getItemColor(data['getvalue' + i].quality) + ';background-image:url('+ xGame.itemurl(data['getvalue' + i].id) +');">' + data['getvalue' + i].name + ' x 1</em>';
                            break;
                        case 5://卷轴
                            html += '<em style="color:' + helper.getItemColor(data['getvalue' + i].quality) + ';background-image:url('+ staticUrl + 'img/pop/talent_item' + data['getvalue' + i].type +'.png)">' + data['getvalue' + i].name + ' x 1</em>';
                            break;
                        case 6://卷轴碎片
                            html += '<em style="color:' + helper.getItemColor(data['getvalue' + i].quality) + ';background-image:url('+ staticUrl + 'img/pop/talent_item' + data['getvalue' + i].type +'.png)">' + data['getvalue' + i].name + ' x 1</em>';
                            break;
                        case 7://声望
                            html += '<em class="wuzi reputation">' + lang('reputation') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 8:// 原魂
                            html += '<em class="wuzi original_soul">' + lang('original_soul') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 9:// 突飞卡
                            html += '<em class="wuzi tfcard">' + lang('tfcard') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 10:// 紫宝石
                            html += '<em class="wuzi purple_jade">' + lang('purple_jade') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 11:// 精炼石
                            html += '<em class="wuzi refinestone">' + lang('refinestone') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 12:// 五彩石
                            html += '<em class="wuzi colorstone">' + lang('colorstone') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 13:// 上古血玉
                            html += '<em class="wuzi bloodstone">' + lang('bloodstone') + ':' + data['getvalue' + i] + '</em>';
                            break;
                        case 14:// 经验马
                            html += '<em style="color:'+ helper.getItemColor(data['getvalue' + i].quality) +';"><b class="horse" style="background-position:'+ helper.getHorsePostion('buff', data['getvalue' + i].id) +';"></b>' + data['getvalue' + i].name + ' x ' + data['getnum' + i] + '</em>';
                        default:
                            break;
                    }
                    html += '<br />'
                }
            }
            return html;
        },

        //结束战斗方法
        endFuc: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.goBack();
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('current_battle_not_open'));
                        break;
                    case -3:
                        xGame.toast.show(lang('end_battle_success'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             loadContents({
                'c': 'herothrone',
                'm': 'end_battle',
            },
            Func);
             Func = null;
        },

        //开战界面方法
        startFuc: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        if (that.e == null) {
                            //初始化数据
                            that.initPage();
                            $('#worldloading').removeClass('black').hide();
                        }

                        that.price = res.price;

                        if (res.buy == 1) {
                            that.floorDom.find('#b1').append('<button class="btn pop_btn_small" id="buy_btn">' + lang('buy') + '</button>');
                        }

                        if (res.info.status == 1) {
                            that.floorDom.find('#b3').html('<button class="btn pop_btn_middle" id="battle_btn">' + lang('battle_start') + '</button>');
                        } else if (res.info.status == 2) {
                            that.floorDom.find('#b3').html('<button class="btn" id="continue_btn"><em>x' + res.info.price + '</em></button>');
                        }

                        //如果有奖励信息，显示奖励
                        if(res.get_reward && res.get_reward.length > 0) {
                            that.e.find('#reward_dom').find('.stage_num').html(lang('herothrone_stage', [res.info.laststage]));
                            that.e.find('#reward_dom').find('.stage_now').find('span').html(res.info.stage);
                            var html = '';
                            for(var i = 0; i < res.get_reward.length; i++) {
                                if(res.get_reward[i].gettype == 1) {
                                    html += '<span class="wuzi silver">'+ lang('silver') +'：'+ res.get_reward[i].getvalue +'</span>';
                                    player.updateinfo({'silver':res.all_silver});
                                } else if(res.get_reward[i].gettype == 2) {
                                    html += '<span class="wuzi jade">'+ lang('jade') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 3) {
                                    html += '<span class="item" style="background-image:url('+ xGame.itemurl(res.get_reward[i].getvalue.itemid) +');">'+ res.get_reward[i].getvalue.itemname +' * 1</span>';
                                } else if(res.get_reward[i].gettype == 4) {
                                    html += '<span class="item" style="background-image:url('+ xGame.itemurl(res.get_reward[i].getvalue.itemid) +');">'+ res.get_reward[i].getvalue.itemname +' * 1</span>';
                                } else if(res.get_reward[i].gettype == 5) {
                                    html += '<span class="item" style="background-image:url('+ staticUrl + 'img/pop/talent_item' + res.get_reward[i].getvalue.itemtype +'.png)">'+ res.get_reward[i].getvalue.itemname +' * 1</span>';
                                } else if(res.get_reward[i].gettype == 6) {
                                    html += '<span class="item" style="background-image:url('+ staticUrl + 'img/pop/talent_item' + res.get_reward[i].getvalue.itemtype +'.png)">'+ res.get_reward[i].getvalue.itemname +' * 1</span>';
                                } else if(res.get_reward[i].gettype == 7) {
                                    html += '<span class="wuzi reputation">'+ lang('reputation') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 8) {
                                    html += '<span class="wuzi original_soul">'+ lang('original_soul') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 9) {
                                    html += '<span class="wuzi tfcard">'+ lang('tfcard') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 10) {
                                    html += '<span class="wuzi purple_jade">'+ lang('purple_jade') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 11) {
                                    html += '<span class="wuzi refinestone">'+ lang('refinestone') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 12) {
                                    html += '<span class="wuzi colorstone">'+ lang('colorstone') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if(res.get_reward[i].gettype == 13) {
                                    html += '<span class="wuzi bloodstone">'+ lang('bloodstone') +'：'+ res.get_reward[i].getvalue +'</span>';
                                } else if (res.get_reward[i].gettype == 14) {
                                    html += '<span class="item" style="color:'+ helper.getItemColor(res.get_reward[i].getvalue.quality) +';"><b class="horse" style="background-position:'+ helper.getHorsePostion('buff', res.get_reward[i].getvalue.id) +';"></b>'+ res.get_reward[i].getvalue.itemname + ' * ' + res.get_reward[i].getnum +'</span>';
                                }
                            }
                            that.e.find('#reward_dom').find('.reward_list').html(html);
                            that.e.find('#reward_dom').show();

                            that.rewardScroller.refresh();
                            that.rewardScroller.scrollTo(0, 0);
                        }

                        that.buildFloor(res);
                        that.e.attr('class', 'pop_page floor');

                        that.e.addClass('floor' + Math.min((Math.floor(that.myStage / 20) + 1), 5));
                        that.myScrollerRefresh();
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('player_not_exist'));
                        break;
                    case -2:
                        xGame.toast.show(lang('no_time_to_start'));
                        break;
                    case -3:
                        xGame.toast.show(lang('no_guan_info'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                if (res.status != 1) {
                    $('#worldloading').hide();
                    xGame.loadWorld('city');
                }
            };

            loadContents({
                'c': 'herothrone',
                'm': 'start',
            },
            Func);
            Func = null;

        },

        //开战方法
        battleFunc: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        //这个数据是获得金币银币
                        miscpop.enterBattleFromThrone(res, 'herothrone' + Math.min((Math.floor(that.myStage / 20) + 1), 5));
                        player.updateinfo({
                            'gold' : res.info.all_gold,
                            'silver' : res.info.all_silver
                        });
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('not_begin'));
                        break;
                    case -3:
                        xGame.toast.show(lang('gold_not_enough'));
                        break;
                    case -4:
                        xGame.toast.show(lang('must_end_last_first'));
                        break;
                    case -5:
                        xGame.toast.show(lang('get_guan_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             loadContents({
                'c': 'herothrone',
                'm': 'action',
            },
            Func);
            Func =null;
        },

        //购买次数方法
        buyTimesFuc: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        player.updateinfo({'gold':res.all_gold});
                        that.buyTimes = parseInt(res.buytimes);
                        that.freeTimes = parseInt(res.times);
                        that.fillTimes(1);
                        xGame.toast.show(lang('buy_success'));
                        break;
                    case 2:
                        xGame.confirm.hide();
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.confirm.hide();
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -2:
                        xGame.confirm.hide();
                        xGame.toast.show(lang('today_times_limited'));
                        break;
                    case -3:
                        xGame.confirm.hide();
                        xGame.toast.show(lang('gold_not_enough'));
                        break;
                    default:
                        xGame.confirm.hide();
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             loadContents({
                'c': 'herothrone',
                'm': 'buy_times',
            },
            Func);
            Func = null;
        },

        //构建前十界面
        buildTopList: function(res) {
            this.fillTopList(res.list);
            this.fillMyInfo(res.info);
        },

        //返回按钮方法
        goBack: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        if (res.buy == 1) {
                            that.topListDom.find('#lits_dom_right').find('.l3').html('<button class="btn pop_btn_small">' + lang('buy') + '</button>');
                        }

                        that.price = res.price;
                        that.e.find('#boxinfo_dom').css('display','none');
                        that.buildTopList(res);
                        that.e.attr('class', 'pop_page top_list');

                        that.e.addClass('floor' + Math.min((Math.floor(parseInt(res.info.laststage) / 20) + 1), 5));
                        that.myScrollerRefresh();
                        break;
                    case 2:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             loadContents({
                'c': 'herothrone',
                'm': 'go_back',
            },
            Func);
            Func = null;
        },

        fillTopList: function(data) {
            var html = '';
            for (var i = 0;i < 10;i ++){
                if (data[i] != undefined) {
                    html += '<li class="player_list" uid="'+ data[i].uid +'">【' + data[i].servername + '】' + data[i].nickname + '<em>' + lang('guan_num' ,[data[i].stage]) + '</em></li>';
                } else {
                    html += '<li>&nbsp;&nbsp;' + lang('no_word') + '</li>';
                }
            }
            this.topListDom.find('#lits_dom_center').find('ul').html(html);
            html = null;
        },

        fillMyInfo: function(data) {
            this.topListDom.find('#lits_dom_right').find('#my_history').html(lang('guan_num' ,[data.laststage]));

            this.freeTimes = parseInt(data.times);
            this.buyTimes = parseInt(data.buytimes);

            this.fillTimes(data.status);
        },

        //构建战斗层
        buildFloor: function(data) {
            this.buyTimes = parseInt(data.info.buytimes);
            this.freeTimes = parseInt(data.info.times);
            this.fillTimes(data.info.status);

            this.myStage = parseInt(data.info.stage);
            
            this.fillBox(data.reward);
            this.fillFloorHero(data.mission);
        },

        fillTimes: function(status) {
            this.floorDom.find('#times').html((this.freeTimes + this.buyTimes) + lang('times'));
            this.topListDom.find('#lits_dom_right').find('#td_left_times').html((this.freeTimes + this.buyTimes) + lang('times'));
            
            if ((this.freeTimes + this.buyTimes) > 0 || ((this.freeTimes + this.buyTimes) == 0 && status == 1)) {
                this.topListDom.find('#start_btn').attr('class', 'btn pop_btn_middle');
            } else {
                this.topListDom.find('#start_btn').attr('class', 'btn pop_btn_middle_disable');
            }
            
        },

        //继续战斗的价格
        fillPrice: function() {
            this.floorDom.find('#continue_btn').find('em').html('x10');
        },

        fillBox: function(data) {
            this.rewardData = {};
            var html = '';
            var len = data.length;
            var class_name = '';
            for (var i = 0;i < len;i ++) {
                this.rewardData[data[i].id] = data[i];
                if (i == 0) {
                    class_name = ' left';
                } else if (i == len - 1){
                    class_name = ' right';
                    this.maxStage = parseInt(data[i].id);
                } else {
                    class_name = ' middle';
                }
                if (this.myStage < data[i].id) {
                    html += '<li class="closed' + class_name + '">';
                } else {
                    html += '<li class="opened' + class_name + '">';
                }
                html += '<span class="box" bid="' + data[i].id + '"></span>'
                html += '</li>';
            }
            
            var expbarWidth = 900 * this.myStage / 100;
            
            var style = '';
            style += 'width: ' + expbarWidth + 'px;';
            
            if (expbarWidth > 900) {
                var scale = expbarWidth / 900;
                style += 'background-size: 100%;';
            }
            
            html = '<div class="exp_bar" style="' + style + '"></div>' + html;
            
            this.floorDom.find('#box_dom').width(114 * 2 + 90 * (len - 2)).html(html);
            
            if (this.myScroller == null) {
                this.myScroller = new iScroll('herothrone_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            
            html = null;
        },
        
        myScrollerRefresh: function() {
            if (this.myScroller != null) {
                this.myScroller.refresh();
                //保证界面上永久能看到一个完整的空格
                if (this.myStage > 90) {
                    //console.log('this.myStage = ' + this.myStage);
                    var num = Math.ceil((this.myStage - 90) / 10);
                    //不足两个格子了，最接移动到最后
                    if (this.maxStage - this.myStage < 20) {
                        num = Math.ceil((this.maxStage - 20 - 90) / 10);
                        this.myScroller.scrollTo(-90 * num - 84,0);
                    } else {
                        this.myScroller.scrollTo(-90 * num,0);
                    }
                }
            }
        },
        
        fillFloorHero: function(list) {
            var html1 = '';//大石头
            var html2 = '';//小石头
            var cn = '';
            for (var i = 0;i < list.length;i ++) {
                if (list[i].open == 0) {
                    html1 += '<div class="big pos' + (i + 1 )+ '"><p>' + lang('guan_num', [list[i].id]) + '</p></div>';
                } else {
                    if (list[i].open == 1) {
                        cn = 'pk';
                    } else if (list[i].open == 2) {
                        cn = 'win';
                    }
                    html1 += '<div class="big pos' + (i + 1 )+ '"><div class="flag ' + cn + '"></div><div class="heroimg" style="background-image:url(' + xGame.hero(list[i].image, 1) + ');margin-left:' + (hero_ini[list[i].image]['mx'] + 80) + 'px"></div><p>' + lang('guan_num', [list[i].id]) + '</p></div>';
                }
            }
            for (var i = 1;i < 9;i ++) {
                html1 += '<div class="small pos' + i + '"></div>';
            }

            this.floorDom.find('#floor_dom').html(html1 + html2);
            html1 = html2 = null;
        },

        fixHero: function() {
            var left30 = [3,5];//靠左的img margin-left:30px
            var left70 = [2];//靠左的img margin-left:30px

        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#herothroneworld_back').unbind('touchend');
                this.e.undelegate('#rule_hide_btn', 'touchend');
                this.e.undelegate('#reward_hide_btn', 'touchend');
                this.e.undelegate('#herothronewfloor_back', 'touchend');
                this.e.undelegate('#boxinfo_hide', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.topListDom) {
                this.topListDom.undelegate('#view_rule', 'touchend');
                this.topListDom.undelegate('.pop_btn_small', 'touchend');
                this.topListDom.undelegate('#start_btn.pop_btn_middle', 'touchend');
                this.topListDom.remove();
                this.topListDom = null;
            }
            if (this.floorDom) {
                this.floorDom.find('#box_dom').undelegate('.box', 'click');
                this.floorDom.undelegate('#end_btn', 'touchend');
                this.floorDom.find('#herothrone_team').unbind('touchend');
                this.floorDom.undelegate('#buy_btn', 'touchend');
                this.floorDom.undelegate('#continue_btn', 'touchend');
                this.floorDom.undelegate('#battle_btn.pop_btn_middle', 'touchend');
                this.floorDom.remove();
                this.floorDom = null;
            }
        },

        clear: function() {
            xGame.unlock();
            if (this.e != null) {
                
                if (this.myScroller != null) {
                    this.myScroller.destroy();
                    this.myScroller = null;
                }

                this.maxStage =null;
		
                if (this.rewardScroller != null) {
                    this.rewardScroller.destroy();
                }
                this.rewardScroller = null;
                
                this.topListDom = null;
                this.floorDom = null;

                this.freeTimes = null;
                this.buyTimes = null;

                this.rewardData = null;
                this.myStage = null;
                this.price = null;
                this.unbindAll();
            }
        }
    };
})();