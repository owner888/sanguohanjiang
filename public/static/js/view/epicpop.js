/* 天下史诗 */
(function() {
    epicPopClass = function() {
        this.e;
        this.isshow = false;

        this.mainDom;
        this.choseDom;

        this.totalRTimes;//总刷新次数
        this.totalLTimes;//总锁定次数
        this.rprice;//刷新价格
        this.lprice;//锁定价格
        this.listData;//史诗列表数据
        this.listScroller;//史诗滑动

        this.siteList;//锁定数组

        this.needAsk;//是否需要询问
        this.epicid;//当前选择的图
        this.forcePrice = null; // 强行领取的价格

        this.unlock_buy_times = 1;
        this.isTimeout = [];
    };
    epicPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    case -3:
                        xGame.toast.show(lang('get_expostulation_list_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

                if (res.status != 1) {
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'epic',
                'm': 'index',
            },
            initRender, true);
            initRender = null;
        },

        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('epic');
            that.mainDom = that.e.find('#epic_main');
            that.choseDom = that.e.find('#epic_list');
            that.e.find('.content').find('#epicpop_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                epicpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            
            //次数
            that.totalRTimes = parseInt(res.member.refreshtimes);
            that.totalLTimes = parseInt(res.member.locktimes);
            that.showRefreshTimes(that.totalRTimes);
            that.showLockTimes(that.totalLTimes);
            that.showReputation();

            that.epicid = parseInt(res.member.epicid);

            //史诗数据
            that.listData = res.list;
            //价格
            that.rprice = res.refreshprice;
            that.lprice = res.lockprice;

            if (res.member.epicid == 0) {//选择史诗
                that.showChose();
                that.disableBtns();
            } else {//已经有史诗
                that.showEpic(res.member.epicid, res.get_price);
            }

            if(res.times) {
                that.unlock_buy_times = parseInt(res.times);
            }
            that.listScroller = null;

            that.siteList = {};

            that.buildParts(res.member);

            that.bindAll();

            that.e.show();
            $('#worldloading').hide();

        },

        //构建史诗碎片
        buildParts: function(data) {
            this.needAsk = false;
            var html = '';
            var class_name = '';
            for (var i = 1;i < 6;i ++) {
                data['site' + i] = parseInt(data['site' + i]);
                //设置本地保存的位置值
                this.siteList[i] = data['site' + i + '_lock'];

                //初始 文字提示
                if (data['site' + i] == 0) {
                    class_name = '';
                    html += '<li id="epic_part' + i + '" site="' + i + '" class="' + class_name + '">';
                    html += '<span class="epic_part">' + lang('epicpop_refresh_get') + '</span>';
                } else {//有图片
                    class_name = 'epic_part_li';
                    //如果是锁定状态，加入seleted样式
                    if (data['site' + i + '_lock'] == 1) {
                        class_name += ' selected';
                    } else if (data['site' + i + '_lock'] == 2) {//提示状态
                        class_name += ' notice';
                        this.needAsk = true;
                    }
                    //显示匹配的标志
                    if (data['site' + i] == ((this.epicid - 1) * 5 + i)) {
                        class_name += ' fit';
                    }
                    html += '<li id="epic_part' + i + '" site="' + i + '" class="' + class_name + '">';
                    html += '<span class="fit"></span>';
                    html += '<span class="mask"></span>';
                    html += '<span class="epic_part epic_img' + Math.ceil(data['site' + i] / 5) + ' epic_part' + (parseInt(data['site' + i]) % 5) + '"></span>';
                }
                html += '<span class="border"></span>';
                html += '<span class="box"></span>';
                html += '</li>';
            }
            this.mainDom.find('#epic_parts').html(html);
            html = null;
        },

        showChose: function() {
            this.mainDom.find('#epic_enter').attr('class', 'chose');
        },

        disableBtns: function() {
            this.mainDom.find('#gold_receive').attr('class', 'btn pop_btn_middle_disable');
            this.mainDom.find('#epic_refresh').attr('class', 'btn pop_btn_middle_disable');
            this.mainDom.find('#gold_receive_price').html('');
        },

        showEpic: function(id, price) {
            this.mainDom.find('#epic_enter').attr('class', 'epic_img' + id);
            this.mainDom.find('#gold_receive').attr('class', 'btn pop_btn_middle');
            this.mainDom.find('#epic_refresh').attr('class', 'btn pop_btn_middle');
            if (price != 0) {
                this.mainDom.find('#gold_receive_price').html('x' + price);
                this.forcePrice = price;
            }
        },

        bindAll: function(){
            var that = this;

            that.mainDom.undelegate('#epic_info_btn', 'touchend').delegate('#epic_info_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#epic_info_dom').css('display', 'block');
            });

            that.e.undelegate('#epic_info_hide', 'touchend').delegate('#epic_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#epic_info_dom').css('display', 'none');
            });

            that.mainDom.undelegate('#epic_enter.chose', 'touchend').delegate('#epic_enter.chose', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                e.stopPropagation();
                that.isTimeout.push(setTimeout(function() {
                    that.showEpicList();
                }, 420));
            });

            that.choseDom.undelegate('#epic_list_hide', 'touchend').delegate('#epic_list_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hideEpicList();
            });

            that.choseDom.undelegate('.chose_btn', 'click').delegate('.chose_btn', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var obj = $(this);
                xGame.confirm.show(lang('notice'), lang('epicpop_chose_ask'), 2,
                    function() {
                        that.selectFunc(obj.attr('eid'), obj.attr('price'), obj.offset());
                        xGame.confirm.hide();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('ok'), lang('close'));

            });

            that.mainDom.undelegate('#epic_refresh.pop_btn_middle', 'touchend').delegate('#epic_refresh.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.needAsk) {
                    xGame.confirm.show(lang('notice'), lang('epicpop_refresh_ask'), 2,
                    function() {
                        that.refreshFunc();
                        xGame.confirm.hide();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('refresh'), lang('close'));
                    that.needAsk = false;
                } else {
                    that.refreshFunc();
                }
            });

            that.mainDom.undelegate('#gold_receive.pop_btn_middle', 'touchend').delegate('#gold_receive.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show('', lang('epicpop_force_get', [that.forcePrice]), 2, function () {
                    xGame.confirm.hide();
                    that.forceFunc();
                }, function () {
                    xGame.confirm.hide();
                });
            });

            that.mainDom.undelegate('#buy_refresh_btn', 'touchend').delegate('#buy_refresh_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.confirm.show(lang('epicpop_buy_rtime'), lang('epicpop_buy_refresh_ask', [that.rprice, that.totalRTimes]), 2,
                    function() {
                        that.buyRefreshFunc();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
            });

            that.mainDom.undelegate('#buy_lock_btn', 'touchend').delegate('#buy_lock_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var allprice = that.unlock_buy_times * that.lprice;
                xGame.confirm.show(lang('epicpop_buy_ltime'), lang('epicpop_buy_lock_ask', [allprice, that.totalLTimes, that.unlock_buy_times]), 2,
                    function() {
                        that.buyLockFunc();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));

            });

            that.mainDom.undelegate('.epic_part_li', 'touchend').delegate('.epic_part_li', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).hasClass('notice')) {
                    $(this).removeClass('notice');
                    that.needAsk = false;
                }

                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selected');
                    that.siteList[$(this).attr('site')] = 0;
                } else {
                    $(this).addClass('selected');
                    that.siteList[$(this).attr('site')] = 1;
                }

            });
        },

        showRefreshTimes: function(num) {
            this.mainDom.find('#refresh_times').html(num);
        },

        showLockTimes: function(num) {
            this.mainDom.find('#lock_times').html(num);
        },

        showReputation: function() {
            this.e.find('#epic_current_prestige').html(player.info.reputation);
        },

        showEpicList: function() {
            if (this.choseDom.find('#epic_list_ul').html() == '') {
                var html = '';
                for (var i = 0;i < this.listData.length;i ++) {
                    html += '<li>';
                    html += '<span class="epic_img epic_img' + this.listData[i].id + '"></span>';
                    html += '<span eid="' + this.listData[i].id + '" class="chose_btn" price="' + this.listData[i].price + '"></span>';
                    html += '<span class="reward">+' + this.listData[i].reward + '</span>';
                    html += '</li>';
                }
                this.choseDom.find('#epic_list_ul').html(html);
                html = null;
            }

            this.listScroller = new iScroll('epic_list_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            this.choseDom.css('display', 'block');
            this.listScroller.refresh();
        },

        hideEpicList: function() {
            if (this.listScroller != null) { //先干掉原本的滑动
                this.listScroller.destroy();
            }
            this.choseDom.css('display', 'none');
        },

        //选择史诗
        selectFunc: function(id, price, data){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {

                if (res.status == 1) {
                    that.hideEpicList();
                    var offset = that.mainDom.offset();
                    var imgDom = $('<div class="temp_epic epic_img' + id + '" style="left:' + (data.left - offset.left) + 'px;top:' + (data.top - offset.top) + 'px;"></div>');
                    imgDom.appendTo(that.mainDom);
                    helper.animate(imgDom, {
                        'left'   : '16px',
                        'top'    : '290px',
                        'width'  : '890px',
                        'height' : '132px'
                    },
                    500, 'Cubic.easeOut', function() {
                        offset = null;
                        imgDom.remove();
                        imgDom = null;
                        that.showEpic(res.epicid, price);
                        xGame.unlock();
                    });
                    that.epicid = parseInt(res.epicid);
                } else {
                    switch (parseInt(res.status)) {
                        case -6:
                            xGame.toast.show(lang('epicpop_chose_fail'));
                            break;
                        case -5:
                            xGame.toast.show(lang('epicpop_get_epic_info_fail'));
                            break;
                        case -4:
                            xGame.toast.show(lang('epicpop_had_chose'));
                            break;
                        case -3:
                            xGame.toast.show(lang('get_user_info_fail'));
                            break;
                        case -2:
                            xGame.toast.show(lang('canshu_lost'));
                            break;
                        case -1:
                            xGame.toast.show(lang('lv_not_enough'));
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                    xGame.unlock();
                }
            };

            loadContents({
                'c': 'epic',
                'm': 'select_epic',
                'id': id,
            },
            Func);
            Func = null;
        },

        //刷新史诗
        refreshFunc: function(){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {

                if (res.status == 1) {
                    player.taskaction(14);
                    that.totalRTimes = parseInt(res.member.refreshtimes);
                    that.totalLTimes = parseInt(res.member.locktimes);
                    that.showRefreshTimes(that.totalRTimes);
                    that.showLockTimes(that.totalLTimes);
                    player.updateinfo({
                        'reputation': res.all_reputation
                    });
                    that.showReputation();

                    if (res.complete == 1) {
                        for (var i = 1;i < 6;i ++) {
                            res.member['site' + i] = (that.epicid - 1) * 5 + i;
                            res.member['site' + i + '_lock'] = 0;
                        }
                        that.buildParts(res.member);
                        that.disableBtns();
                        that.isTimeout.push(setTimeout(function() {
                            that.mainDom.find('#epic_light').css('display','block').one('webkitAnimationEnd', function() {
                                that.mainDom.find('#epic_light').css('display','none');
                                xGame.toast.show(lang('smashpop_you_get', [res.add_reputation]));
                                that.isTimeout.push(setTimeout(function() {
                                    that.showChose();
                                    that.mainDom.find('#epic_parts').fadeOut(500, function(){
                                        $(this).show();
                                        for (var i = 1;i < 6;i ++) {
                                            res.member['site' + i] = 0;
                                        }
                                        that.buildParts(res.member);
                                        xGame.unlock();
                                    })

                                },1000));
                            });
                        }, 1000));
                    } else {
                        xGame.unlock();
                        that.buildParts(res.member);
                    }


                } else {
                    switch (parseInt(res.status)) {
                        case -10:
                            xGame.toast.show(lang('epicpop_get_part_info_fail'));
                            break;
                        case -9:
                            xGame.toast.show(lang('epicpop_modify_refresh_fail'));
                            break;
                        case -8:
                            xGame.toast.show(lang('epicpop_modify_lock_fail'));
                            break;
                        case -7:
                            xGame.toast.show(lang('epicpop_get_part_list_fail'));
                            break;
                        case -6:
                            var allprice = that.unlock_buy_times * that.lprice;
                            xGame.confirm.show(lang('epicpop_no_lock_times'), lang('epicpop_buy_lock_ask', [allprice, that.totalLTimes, that.unlock_buy_times]), 2,
                            function() {
                                that.buyLockFunc();
                            },
                            function() {
                                xGame.confirm.hide();
                            },
                            lang('buy'), lang('close'));
                            break;
                        case -5:
                            xGame.confirm.show(lang('epicpop_no_refresh_times'), lang('epicpop_buy_refresh_ask', [that.rprice, that.totalRTimes]), 2,
                            function() {
                                that.buyRefreshFunc();
                            },
                            function() {
                                xGame.confirm.hide();
                            },
                            lang('buy'), lang('close'));
                            break;
                        case -4:
                            xGame.toast.show(lang('epicpop_all_locked'));
                            break;
                        case -3:
                            xGame.toast.show(lang('epicpop_not_chose_yet'));
                            break;
                        case -2:
                            xGame.toast.show(lang('get_user_info_fail'));
                            break;
                        case -1:
                            xGame.toast.show(lang('lv_not_enough'));
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                    xGame.unlock();
                }
            };

            loadContents({
                'c': 'epic',
                'm': 'refresh',
                'l': that.transferToString(),
            },
            Func);
            Func = null;
        },

        transferToString: function() {
            var arr = [];
            for (var i = 1;i < 6;i ++) {
                if (this.siteList[i] == 1) {
                    arr.push(i);
                }
            }
            return arr.toString();
        },

        //强行领取
        forceFunc: function(){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:

                        that.totalRTimes = parseInt(res.member.refreshtimes);
                        that.totalLTimes = parseInt(res.member.locktimes);
                        that.showRefreshTimes(that.totalRTimes);
                        that.showLockTimes(that.totalLTimes);

                        player.updateinfo({
                            "gold": res.all_gold,
                            'reputation': res.reputation
                        });
                        that.showReputation();
                        that.showChose();
                        that.disableBtns();
                        that.buildParts(res.member);

                        xGame.toast.show(lang('smashpop_you_get', [res.add]));

                        break;
                    case -5:
                        //xGame.toast.show(lang('gold_not_enough'));
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -4:
                        xGame.toast.show(lang('epicpop_get_epic_info_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('epicpop_not_chose_yet'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
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
                'c': 'epic',
                'm': 'force',
            },
            Func);
            Func = null;
        },

        //购买锁定次数
        buyLockFunc: function(){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        player.updateinfo({
                            "gold": res.all_gold
                        });
                        that.totalLTimes = res.locktimes;
                        that.showLockTimes(res.locktimes);
                        $('#buy_epicltimes').html(res.locktimes);
                        break;
                    case -3:
                        //xGame.toast.show(lang('gold_not_enough'));
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
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
                'c': 'epic',
                'm': 'buy_lock_times',
                'num': that.unlock_buy_times
            },
            Func);
            Func = null;
        },

        //购买刷新次数
        buyRefreshFunc: function(){
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        player.updateinfo({
                            "gold": res.all_gold
                        });
                        that.totalRTimes = res.refreshtimes;
                        that.showRefreshTimes(res.refreshtimes);
                        $('#buy_epicrtimes').html(res.refreshtimes);
                        break;
                    case -3:
                        //xGame.toast.show(lang('gold_not_enough'));
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
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
                'c': 'epic',
                'm': 'buy_refresh_times',
            },
            Func);
            Func = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('#epicpop_hidebtn').unbind('touchend');
                this.e.undelegate('#epic_info_hide', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.mainDom) {
                this.mainDom.undelegate('#epic_info_btn', 'touchend');
                this.mainDom.undelegate('#epic_enter.chose', 'touchend');
                this.mainDom.undelegate('#epic_refresh.pop_btn_middle', 'touchend');
                this.mainDom.undelegate('#gold_receive.pop_btn_middle', 'touchend');
                this.mainDom.undelegate('#buy_refresh_btn', 'touchend');
                this.mainDom.undelegate('#buy_lock_btn', 'touchend');
                this.mainDom.undelegate('.epic_part_li', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
            if (this.choseDom) {
                this.choseDom.undelegate('#epic_list_hide', 'touchend');
                this.choseDom.undelegate('.chose_btn', 'click');
                this.choseDom.remove();
                this.choseDom = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.isshow = false;
            
            this.epicid = null;

            this.totalRTimes = null;
            this.totalLTimes = null;
            this.rprice = null;
            this.lprice = null;
            this.listData = null;
            this.forcePrice = null;

            if (this.listScroller != null) {
                this.listScroller.destroy();
                this.listScroller =null;
            }

            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;

            this.siteList = null;
            this.unbindAll();

        },
    };
})();