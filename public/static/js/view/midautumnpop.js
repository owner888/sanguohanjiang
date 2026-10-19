/* 中秋节 love */
(function() {
    midautumnPopClass = function() {
        this.e = null;
        this.isshow = false;
        this.infoScroller = null;   //右下侧排行和好友列表
        this.loveScroller = null;   //爱心成就列表
        this.nowLove = 0;           //当前爱心数
        this.nowMooncake = 0;       //当前月饼数
        this.giftScroller = null;   //我的礼物列表
        this.nowMode = 1;           //购买月饼界面当前选中id
        this.ranklist = null;       //排行榜列表
        this.friendslist = null;    //好友列表
        this.giftlist = null;       //我的礼物列表
        this.buylist = null;        //购买月饼数据
        this.achlist = null;        //爱心成就列表
        this.vip = 0;               //当前vip
        this.canbuy = 0;            //可购买月饼数量
        this.ownMooncake = 0;       //当前收到的小月饼总数
        this.leftMooncake = 0;      //当前剩余的小月饼数
        this.moonPrice = 0;         //单个月饼价格
    };

    midautumnPopClass.prototype = {
        
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initFirst(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };

            loadContents({
                'c': 'act_moon',
                'm': 'index'
            },
            initRender);
            initRender = null;
        },

        initFirst: function(res) {
            this.e = xGame.loadPopTmpl('midautumn');
            
            //刷新本地数据
            this.ranklist = res.rank_list;
            this.buylist = res.buy_list;
            this.vip = res.vip;
            this.canbuy = res.buymoon;
            this.nowMooncake = res.mine_moon;
            this.moonPrice = res.moon_price;

            //活动时间
            this.e.find('.open_time').text(res.timestr);

            //更新按钮状态，默认为排行
            this.e.find('#rank_btn').removeClass('pressed').addClass('pressed');
            this.e.find('#friends_btn').removeClass('pressed');

            //更新爱心和月饼数量
            this.e.find('#love_num').text(res.love);
            this.e.find('#mooncake_num').text(res.mine_moon);

            //更新爱心成就和我的礼物右上角感叹号
            if (res.achimg == 1) {
                this.e.find('#love_achieve_btn').find('.popo').show();
            } else {
                this.e.find('#love_achieve_btn').find('.popo').hide();
            }

            if (res.giftimg == 1) {
                this.e.find('#gift_btn').find('.popo').show();
            } else {
                this.e.find('#gift_btn').find('.popo').hide();
            }

            //刷新排行榜/好友列表
            this.refreshScroller(this.ranklist);

            this.bindAll();

            this.e.show();
            $('#worldloading').hide();
            this.isshow = true;
        },

        refreshScroller: function(list) {

            var html = this.getHtml(list);
            this.e.find('#midautumn_bottom_list').html(html).height(list.length * 63 + 'px');
            html = null;

            if (this.infoScroller == null) {
                this.infoScroller = new iScroll('midautumn_bottom_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            this.infoScroller.refresh();

            //主界面赠送按钮
            var that = this;
            this.e.find('#midautumn_bottom_list').find('.givebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }
                
                var uid = $(this).attr('uid');
                var name = $(this).attr('name');
                var give_dom = that.e.find('#midautumn_give_dom');
                give_dom.find('.friendname').text(name);
                give_dom.find('.friendname').attr({'uid': uid});
                give_dom.css('display', 'block');
                e.preventDefault();
            });

            //主界面排行列表、好友列表项点击
            this.e.find('#midautumn_bottom_list').find('.name').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.showpop('info', parseInt($(this).attr('uid')));
                e.preventDefault();
            });
        },
        
        bindAll: function() {
            var that = this;

            //关闭按钮
            this.e.find('#midautumn_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            //主界面购买月饼按钮
            this.e.find('#add_mooncake_btn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.initBuyMooncake();
                that.e.find('#buy_mooncake_dom').css('display', 'block');
                e.preventDefault();
            });

            //购买月饼界面购买按钮
            this.e.find('#buy_mooncake_btn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock()) {
                    return false;
                }
                var needGold = that.buylist[that.nowMode].gold;
                if (parseInt(player.info.gold) < parseInt(needGold)) {
                    xGame.toast.show(lang('gold_not_enough'));
                    xGame.unlock();
                    return false;
                }
                var initRender = function(res) {

                    switch (parseInt(res.status)) {
                        case 1:
                            player.updateinfo({
                                'gold': parseInt(res.gold)
                            });
                            xGame.toast.show(lang('midautumn_buy_success', [(parseInt(res.mine_moon) - parseInt(that.nowMooncake))]));
                            that.nowMooncake = res.mine_moon;
                            that.canbuy = res.buymoon;
                            if (player.info.vip >= 5) {
                                that.e.find('#can_buy_num').text(lang('midautumn_unlimited'));
                            } else {
                                that.e.find('#can_buy_num').text(that.canbuy);
                            }
                            that.e.find('.midautumn_right').find('#mooncake_num').text(that.nowMooncake);
                            break;
                        default :
                            xGame.toast.show(res.msg);
                            $('#worldloading').hide();
                            break;
                    }
                    xGame.unlock();
                };

                loadContents({
                    'c': 'act_moon',
                    'm': 'buy_moon',
                    'id': that.nowMode
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });

            //购买月饼界面关闭按钮
            this.e.find('#buy_mooncake_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#buy_mooncake_dom').css('display', 'none');
                e.preventDefault();
            });

            //玩法说明按钮
            this.e.find('#rule_btn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                    that.achieveTimer = null;
                    that.e.find('.reward_box').remove();
                }

                that.e.find('#midautumn_info').css('display', 'block');
                e.preventDefault();
            });

            //关闭玩法说明
            this.e.find('#midautumn_info').find('.mask').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#midautumn_info').css('display', 'none');
                e.preventDefault();
            });

            this.e.find('#midautumn_info').find('.hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#midautumn_info').css('display', 'none');
                e.preventDefault();
            });

            //爱心成就按钮
            this.e.find('#love_achieve_btn').unbind('touchend').bind('touchend', function(e){

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                    that.achieveTimer = null;
                    that.e.find('.reward_box').remove();
                }

                if (xGame.checkLock()) {
                    return false;
                }

                var initRender = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.e.find('#love_achieve_dom').css('display', 'block');
                            that.nowLove = parseInt(res.love);
                            that.achlist = res.ach_list;
                            that.refreshLoveScroller();
                            break;
                        default :
                            xGame.toast.show(res.msg);
                            $('#worldloading').hide();
                            break;
                    }
                    xGame.unlock();
                };

                loadContents({
                    'c': 'act_moon',
                    'm': 'get_ach_list'
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });

            //爱心成就界面关闭按钮
            this.e.find('#love_achieve_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#love_achieve_dom').css('display', 'none');
                e.preventDefault();
            });

            //主界面我的礼物按钮
            this.e.find('#gift_btn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock()) {
                    return false;
                }

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                    that.achieveTimer = null;
                    that.e.find('.reward_box').remove();
                }

                if (that.e.find('#my_gift_dom').is(':hidden')) {

                    var initRender = function(res) {
                        switch (parseInt(res.status)) {
                            case 1:
                                that.e.find('#my_gift_dom').show();
                                that.e.find('.bottom_info').hide();
                                that.e.find('.top_info').hide();
                                that.e.find('.midautumn_left').find('.open_time').hide();
                                that.giftlist = res.msg_list;

                                that.ownMooncake = res.friend_all;
                                that.leftMooncake = res.friend_moon;

                                //刷新左侧礼物信息列表
                                that.refreshGiftScroller();
                                //刷新右侧月饼信息
                                that.e.find('#my_gift_dom').find('.all_mooncake').text(that.ownMooncake);
                                that.e.find('#my_gift_dom').find('.left_mooncake').text(that.leftMooncake);
                                break;
                            default :
                                xGame.toast.show(res.msg);
                                $('#worldloading').hide();
                                break;
                        }
                        xGame.unlock();
                    };

                    loadContents({
                        'c': 'act_moon',
                        'm': 'get_give_info'
                    },
                    initRender);
                    initRender = null;
                } else {
                    that.e.find('#my_gift_dom').hide();
                    that.e.find('.bottom_info').show();
                    that.e.find('.top_info').show();
                    that.e.find('.midautumn_left').find('.open_time').show();
                    xGame.unlock();
                }

                e.preventDefault();
            });

            //我的礼物界面关闭按钮
            this.e.find('#my_gift_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                    that.achieveTimer = null;
                    that.e.find('.reward_box').remove();
                }

                that.e.find('#my_gift_dom').hide();
                that.e.find('.bottom_info').show();
                that.e.find('.top_info').show();
                that.e.find('.midautumn_left').find('.open_time').show();
                e.preventDefault();
            });

            //主界面排行按钮
            this.e.find('#rank_btn').unbind('touchend').bind('touchend', function(e){

                if ($(this).hasClass('pressed') || xGame.checkLock(true)) {
                    return false;
                }
                
                that.refreshScroller(that.ranklist);

                $(this).removeClass('pressed').addClass('pressed');
                that.e.find('#friends_btn').removeClass('pressed');
                e.preventDefault();
            });

            //主界面好友按钮
            this.e.find('#friends_btn').unbind('touchend').bind('touchend', function(e){
               
                if ($(this).hasClass('pressed') || xGame.checkLock(true)) {
                    return false;
                }

                var initRender = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.friendslist = res.friend_list;
                            that.refreshScroller(that.friendslist);

                            that.e.find('#friends_btn').removeClass('pressed').addClass('pressed');
                            that.e.find('#rank_btn').removeClass('pressed');

                            break;
                        default :
                            xGame.toast.show(res.msg);
                            $('#worldloading').hide();
                            break;
                    }
                    xGame.unlock();
                };

                loadContents({
                    'c': 'act_moon',
                    'm': 'get_friend_list'
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });

            //赠送界面赠送按钮
            this.e.find('.give_btn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                var num = parseInt($(this).attr('num'));
                var friendid = that.e.find('#midautumn_give_dom').find('.friendname').attr('uid');
                var type = that.e.find('#midautumn_give_dom').find('.checkbox').attr('select');
                
                if (type != 1 && parseInt(that.nowMooncake) < num) {
                    var need = num - that.nowMooncake;
                    xGame.confirm.show(lang('midautumn_buy_title'), lang('midautumn_buy_text', [that.moonPrice * need, need]), 2,
                        function() {
                            that.onGiveClick(num, friendid, 1);
                            xGame.confirm.hide();
                        },
                        function() {
                            xGame.confirm.hide();
                        },
                        lang('buy'), lang('close'));
                } else {
                    that.onGiveClick(num, friendid, type);
                }

                e.preventDefault();
            });
          
            //赠送界面勾选按钮
            this.e.find('.checkbox').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                var is_select = $(this).attr('select');

                if (is_select == 1) {
                    $(this).attr({'select': 0});
                    that.e.find('.selectimg').css('display', 'none');
                } else {
                    $(this).attr({'select': 1});
                    that.e.find('.selectimg').css('display', 'block');
                }
                e.preventDefault();
            });

            //赠送界面关闭按钮
            this.e.find('#midautumn_give_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('.reward_box').remove();

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                }

                that.e.find('#midautumn_give_dom').css('display', 'none');
                that.e.find('.checkbox').attr({'select': 0});
                that.e.find('.selectimg').css('display', 'none');
                e.preventDefault();
            });

            //吃一个按钮
            this.e.find('.my_gift_dom').find('#eat_one_btn').unbind('touchend').bind('touchend', function(e){
                
                if (xGame.checkLock()) {
                    return false;
                }

                var initRender = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.leftMooncake = res.friend_moon;

                            if (that.leftMooncake <= 0) {
                                that.leftMooncake = 0;
                                that.e.find('#gift_btn').find('.popo').hide();
                            }

                            that.e.find('#my_gift_dom').find('.left_mooncake').text(that.leftMooncake);

                            var rewardclass = that.getrewardStr(parseInt(res.reward_list[0].type));

                            var html = '<span class="reward ' + rewardclass + '">x' + res.reward_list[0].params + '</span>';
                
                            xGame.toast.show(lang('get') + html);
                            break;
                        default :
                            xGame.toast.show(res.msg);
                            $('#worldloading').hide();
                            break;
                    }
                    xGame.unlock();
                };

                loadContents({
                    'c': 'act_moon',
                    'm': 'eat_moon',
                    'num': 1
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });

            //吃十个按钮
            this.e.find('.my_gift_dom').find('#eat_ten_btn').unbind('touchend').bind('touchend', function(e){
                
                if (xGame.checkLock()) {
                    return false;
                }

                var initRender = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            that.leftMooncake = res.friend_moon;

                            if (that.leftMooncake <= 0) {
                                that.leftMooncake = 0;
                                that.e.find('#gift_btn').find('.popo').hide();
                            }

                            that.e.find('#my_gift_dom').find('.left_mooncake').text(that.leftMooncake);
                            that.showMessage(res.reward_list);
                            xGame.unlock();
                            break;
                        default :
                            xGame.toast.show(res.msg);
                            xGame.unlock();
                            $('#worldloading').hide();
                            break;
                    }
                    
                };

                loadContents({
                    'c': 'act_moon',
                    'm': 'eat_moon',
                    'num': 50
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });
        },

        onGiveClick: function(num, friendid, type) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }
            
            var initRender = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        if (parseInt(player.info.vip) < 5 && type == 1 && that.nowMooncake < num) {
                            that.canbuy = res.buymoon;
                        }

                        that.nowLove = res.love;
                        that.nowMooncake = res.mine_moon;

                        that.e.find('#love_num').text(that.nowLove);
                        that.e.find('#mooncake_num').text(that.nowMooncake);

                        player.updateinfo({'gold': parseInt(res.gold)});

                        if (res.reward_list.length == 1) {
                            var rewardclass = that.getrewardStr(parseInt(res.reward_list[0].type));
                            var html = '';
                            html += '<span class="reward loveheart">x' + num + '</span>';
                            html += '<span class="reward ' + rewardclass + '">x' + res.reward_list[0].params + '</span>';
                    
                            xGame.toast.show(lang('get') + html);
                        } else {
                            var loveheart = {
                                'type': 29,
                                'params': num
                            };
                            res.reward_list.unshift(loveheart);
                            that.showMessage(res.reward_list);
                        }

                        that.ranklist = res.rank_list;

                        if (that.e.find('#rank_btn').hasClass('pressed')) {
                            that.refreshScroller(that.ranklist);
                        }

                        if (res.achimg == 0) {
                            that.e.find('#love_achieve_btn').find('.popo').hide();
                        } else {
                            that.e.find('#love_achieve_btn').find('.popo').show();
                        }

                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'act_moon',
                'm': 'give_moon',
                'fuid': friendid,
                'num': num,
                'type': type
            },
            initRender);
            initRender = null;
        },

        getrewardStr: function(type) {
            var str = '';

            switch(type) {
                case 1:
                    str = 'gold';
                    break;
                case 2:
                    str = 'silver';
                    break;
                case 3:
                    str = 'gem2';
                    break;
                case 4:
                    str = 'gem3';
                    break;
                case 5:
                    str = 'osoul';
                    break;
                case 6:
                    str = 'jade';
                    break;
                case 7:
                    str = 'prestige';
                    break;
                case 9:
                    str = 'add_gem_part';
                    break;
                case 10:
                    str = 'tfcard';
                    break;
                case 11:
                    str = 'gem1';
                    break;
                case 13:
                    str = 'act';
                    break;
                case 14:
                    str = 'refinestone';
                    break;
                case 15:
                    str = 'colorstone';
                    break;
                case 16:
                    str = 'bloodstone';
                    break;
                case 17:
                case 18:
                case 19:
                case 20:
                case 21:
                case 22:
                case 23:
                case 24:
                case 25:
                case 26:
                    str = 'essence';
                    break;
                case 27:
                    str = 'roseicon';
                    break;
                case 28:
                    str = 'diamondicon';
                    break;
                default:
                    break;
            }

            return str;
        },

        getHtml: function(list) {
            var html = '';

            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                html += '<li>';
                html += '<em class="rank">' + (i + 1) + '</em>';
                html += '<span class="name"  uid="' + (item.uid ? item.uid : item.fuid) + '">' + item.nickname + '</span>';
                html += '<span class="img"><em class="love_num">' + item.love + '</em></span>';
                html += '<span class="givebtn" uid="' + (item.uid ? item.uid : item.fuid) + '" name="' + item.nickname + '"><em class="givebtn_label">'+ lang('midautumn_give') +'</em></span>';
                html += '</li>';
            }

            return html;
        },

        refreshLoveScroller: function() {

            var that = this;
            var html = this.getLoveHtml(this.achlist);
            this.e.find('#love_achieve_list').html(html);
            html = null;

            if (this.loveScroller == null) {
                this.loveScroller = new iScroll('love_achieve_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            this.loveScroller.refresh();
            this.loveScroller.scrollTo(0, 0);

            //爱心成就界面领取奖励按钮
            this.e.find('#love_achieve_list').find('.love_reward_btn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock()) {
                    return false;
                }

                if ($(this).hasClass('pop_btn_middle_disable')) {
                    xGame.unlock();
                    return false;
                }

                var uid = $(this).attr('aid');

                var initRender = function(res) {
                    switch (parseInt(res.status)) {
                        case 1:
                            player.updateinfo({
                                'gold': parseInt(res.all_gold),
                                'sliver': parseInt(res.all_silver)
                            });
                            that.achlist[parseInt(uid) - 1].reward = 2;
                            var achitem = that.achlist[parseInt(uid) - 1];
                            that.e.find('#reward' + uid).replaceWith('<span class="love_received"></span>');

                            var html = '';

                            var rewardlist = that.getRewardType(achitem);

                            for (var i = 0; i < rewardlist.length; i++) {
                                var item = rewardlist[i];
                                var rewardclass = that.getrewardStr(parseInt(item.type));

                                html += '<span class="reward ' + rewardclass + '">x' + item.params + '</span>';
                            }

                            xGame.toast.show(lang('get') + html);

                            //遍历列表判断是否显示爱心成就叹号提示
                            var showpop = false;
                            for (var i = 0; i < that.achlist.length; i++) {
                                if (that.achlist[i].reward == 1) {
                                    showpop = true;
                                    break;
                                }
                            }
                            if (!showpop) {
                                that.e.find('#love_achieve_btn').find('.popo').hide();
                            } else {
                                that.e.find('#love_achieve_btn').find('.popo').show();
                            }
                            break;
                        default :
                            xGame.toast.show(res.msg);
                            $('#worldloading').hide();
                            break;
                    }
                    xGame.unlock();
                };

                loadContents({
                    'c': 'act_moon',
                    'm': 'get_reward',
                    'id': uid
                },
                initRender);
                initRender = null;

                e.preventDefault();
            });

            this.e.find('#love_achieve_dom').show();
        },

        //领取爱心成就时 奖励类型和数量
        getRewardType: function(list) {
            var rewardarray = [];
            for (var i = 1; i <= 3; i++) {
                var item = {
                    'type': list['gettype' + i],
                    'params': list['getvalue' + i]
                };

                rewardarray.push(item);
            }

            return rewardarray;
        },

        getLoveHtml: function(list) {
            var html = '';

            // 构建奖励列表
            for (var i = 0, len = list.length; i < len; i++) {
                var item = list[i];
                html += '<li class="love_achieve_item">';
                html += '<span class="des">' + lang('midautumn_need_love', [item.achlove]) + '</span>';
                html += '<div class="love_achieve_reward">';

                for (var j = 1; j <= 3; j++) {
                    type = parseInt(item['gettype' + j]);

                    if (type == 8) { // 装备
                        html += '<span class="reward type'+ type +'" style="color:'+ helper.getItemColor(item['info' + j].quality) +'"><em class="item" style="background-image:url('+ xGame.itemurl(item['info' + j].id) +')"></em>' + item['info' + j].name + '</span>';
                    } else if (type == 11) { // 坐骑
                        html += '<span class="reward type'+ type +'" style="color:'+ helper.getItemColor(item['info' + j].quality) +'"><em class="item" style="background-position:'+ helper.getHorsePostion('buff', item['info' + j].id) +'">' + item['info' + j].name + '</span>';
                    } else if (type == 13) { // 武将
                        html += '<span class="reward type'+ type +'"><em class="item" style="background-image:url('+ xGame.avatar(item['info' + j].id) +')"></em>' + item['info' + j].name + '</span>';
                    } else if (type >= 21 && type <= 26) { // 精华
                        html += '<span class="reward essence" style="background-image:url('+ xGame.essence(item['info' + j].id) +');color:'+ helper.getItemColor(item['info' + j].quality) +'">x' + this.getWan(item['info' + j].value) + '</span>';
                    } else {
                        html += '<span class="reward type'+ type +'"><span class="img"></span>x' + this.getWan(item['getvalue' + j]) + '</span>';
                    }
                }

                html += '</div>';

                switch (parseInt(item.reward)) {
                    case 0:
                        html += '<button class="love_reward_btn pop_btn_middle_disable">' + lang('not_reached') + '</button>';
                        break;
                    case 1:
                        html += '<button class="love_reward_btn pop_btn_middle" id="reward'+ item.id +'" aid="'+ item.id +'">' + lang('warena_fetch') + '</button>';
                        break;
                    case 2:
                        html += '<span class="love_received"></span>';
                    default :
                        break;
                }

                html += '</li>';
            }

            return html;
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

         refreshGiftScroller: function() {

            var html = this.getGiftHtml(this.giftlist);
            this.e.find('#my_gift_list').html(html).height(this.giftlist.length * 34 + 'px');
            html = null;

            if (this.giftScroller == null) {
                this.giftScroller = new iScroll('my_gift_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            this.giftScroller.refresh();

            this.e.find('#my_gift_list').find('.desc_name').unbind('touchend').bind('touchend', function(e){
                
                if ($(this).attr('uid') == 0) {
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
                e.preventDefault();
            });
        },

        getGiftHtml: function(list) {
            var html = '';

            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                html += '<li class="my_gift_item" uid="' + item.fuid + '">';
                var msgarray = item.msg_str.split(item.nickname);

                html += '<div>';
                html += '<span class="img gifttype' + item.type + '"></span>';
                html +='<span class="desc_time">' + msgarray[0] + '</span>';
                if (item.fuid == 0) {
                    html += '<span class="desc_name" uid="' + item.fuid + '" style="color:white">' + item.nickname + '</span>';
                } else {
                    html += '<span class="desc_name" uid="' + item.fuid + '">' + item.nickname + '</span>';
                }
                
                html += '<span class="desc_msg">' + msgarray[1] + '</span></div>';
                html += '</li>';
            }

            return html;
        },
       
        initBuyMooncake: function() {
            var html = '';
            var n = 0;
            var that = this;
            
            //当前等级
            this.e.find('#curr_level').text('VIP' + this.vip);
            //可购买月饼数量
            if (player.info.vip >= 5) {
                this.e.find('#can_buy_num').text(lang('midautumn_unlimited'));
            } else {
                this.e.find('#can_buy_num').text(this.canbuy);
            }
            //勾选项
            for (var i = 1; i <= 3; i++) {

                var item = this.buylist[i];
                html += '<li uid="' + item.id + '"' + (n == 0 ? ' class="selected"': '') + '><em></em>' + lang('midautumn_buy_msg', [item.gold, item.moonnum]);
                html += '</li>';

                if (n == 0) {//默认第一种购买方式
                    this.nowMode = item.id;
                }

                n++;
            }

            this.e.find('#buy_mooncake_list').html(html);
            this.e.find('#buy_mooncake_list').children('li').unbind('touchend').bind('touchend', function() {

                that.e.find('#buy_mooncake_list').children('li').each(function() {
                    $(this).removeClass('selected');
                });

                that.nowMode = $(this).attr('uid');
                $(this).addClass('selected');
            });
        },

        // 奖励掉落
        showMessage: function(rewards) {
            var html = '';
            var that = this;
            var t = 0;
            var i = 0;

            this.e.find('.reward_box').remove();

            if (this.achieveTimer != null) {
                clearTimeout(this.achieveTimer);
            }

            this.e.find('.content').append('<div class="reward_box"></div>');

            function loop() {

                var reward = rewards[i];

                var top = 10 + 40 * (Math.floor(i / 3));

                t++;

                if (Math.floor(i % 3) == 0) {
                    t = 0;
                }

                var left = 10 + 150 * t;

                if (reward.type == 8) {//装备
                    html = '<span class="reward_down type'+ reward.type +'" style="color:'+ helper.getItemColor(reward.quality) +';left:' + left + 'px;top:' + top + 'px;"><em class="img" style="background-image:url('+ xGame.itemurl(reward.id) +')"></em>' + reward.name + 'x'+reward.params+'</span>';
                } else if (reward.type == 12) {//武将
                    html = '<span class="reward_down type'+ reward.type +'"><em class="img" style="background-image:url('+ xGame.avatar(reward.general.image) +';left:' + left + 'px;top:' + top + 'px);"></em>' + reward.general.name + 'x'+reward.params+'</span>';
                } else if (reward.type == 26) {//坐骑
                    html = '<span class="reward_down type'+ reward.type +'" style="color:'+ helper.getItemColor(reward.info.quality) +';left:' + left + 'px;top:' + top + 'px;"><em class="img" style="background-position:'+ helper.getHorsePostion('buff', reward.info.id) +'"></em>' + reward.info.name + 'x'+reward.params+'</span>';
                } else {
                    var size = 40;
                    if (reward.type == 27 || reward.type == 28) {
                        size = 50;
                    } else if (reward.type == 5 || reward.type == 29) {
                        size = 36;
                    }
                    html = '<span class="reward_down" style="left:' + left + 'px;top:' + top + 'px;"><em class="img reward type'+ reward.type +'" style="background-size: ' + size + 'px;"><em class="img"></em></em>x' + reward.params + '</span>';
                }

                that.e.find('.reward_box').append(html);

                i++;

                if (i >= rewards.length) {
                    that.achieveTimer = setTimeout(function() {

                        if (that.e.css('display') == 'block') {
                            that.e.find('.reward_box').remove();
                        }

                    }, 3000);
                } else {
                    that.achieveTimer = setTimeout(loop, 70);
                }
            }

            loop();
        },

        unbindAll: function() {

            if (this.e) {
                this.e.find('#midautumn_bottom_list').find('.givebtn').unbind('touchend');
                this.e.find('#midautumn_bottom_list').find('li').unbind('touchend');
                this.e.find('#midautumn_hidebtn').unbind('touchend');
                this.e.find('#add_mooncake_btn').unbind('touchend');
                this.e.find('#buy_mooncake_btn').unbind('touchend');
                this.e.find('#buy_mooncake_hidebtn').unbind('touchend');
                this.e.find('#rule_btn').unbind('touchend');
                this.e.find('#midautumn_info').find('.mask').unbind('touchend');
                this.e.find('#midautumn_info').find('.hidebtn').unbind('touchend');
                this.e.find('#love_achieve_btn').unbind('touchend');
                this.e.find('#love_achieve_hidebtn').unbind('touchend');
                this.e.find('#gift_btn').unbind('touchend');
                this.e.find('#my_gift_hidebtn').unbind('touchend');
                this.e.find('#rank_btn').unbind('touchend');
                this.e.find('#friends_btn').unbind('touchend');
                this.e.find('.give_btn').unbind('touchend');
                this.e.find('.checkbox').unbind('touchend');
                this.e.find('#midautumn_give_hidebtn').unbind('touchend');
                this.e.find('.my_gift_dom').find('#eat_one_btn').unbind('touchend');
                this.e.find('.my_gift_dom').find('#eat_ten_btn').unbind('touchend');
                this.e.find('#love_achieve_list').find('.love_reward_btn').unbind('touchend');

                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.unbindAll();

            if (this.infoScroller) {
                this.infoScroller.destroy();
                this.infoScroller = null;
            }

            if (this.loveScroller) {
                this.loveScroller.destroy();
                this.loveScroller = null;
            }

            if (this.giftScroller) {
                this.giftScroller.destroy();
                this.giftScroller = null;
            }
          
            if (this.giftScroller) {
                this.giftScroller.destroy();
                this.giftScroller = null;
            }

            if (this.achieveTimer) {
                clearTimeout(this.achieveTimer);
                this.achieveTimer = null;
            }
            
            this.nowMode = 1;
            this.ranklist = null;
            this.friendslist = null;
            this.giftlist = null;
            this.buylist = null;
            this.achlist = null;
            this.vip = 0;
            this.canbuy = 0;

            this.nowMode = 1;
            this.nowLove = 0;
            this.nowMooncake = 0;
            this.leftMooncake = 0;
            this.isshow = false;
        },
    };
})();