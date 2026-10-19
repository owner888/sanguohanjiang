/* 抽奖 */
(function() {
    lotteryPopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom;
        this.rightDom;
        this.rewardDom;
        this.rewardScroller;

        this.giftData; //奖励数据，以id为键
        this.leftTimes; //剩余总次数

        this.resData; //返回数据

        this.freeData; //免费数据

        this.pos; //当前的位置

        this.reachLimit = false; // 当日购买次数达到上限
        this.isTimeout = [];
    };
    lotteryPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                case 1:
                    that.fillData(res.list, res.log.info, res.buy_times, res.buy_times_limit);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    break;
                }
            };
            loadContents({
                'c': 'lottery',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        fillData: function(list, log, buy_times, buy_times_limit) {
            var that = this;

            that.e = xGame.loadPopTmpl('lottery');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                lotterypop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.leftDom = that.e.find('#lottery_left').find('#lottery_left_bottom');
            that.rightDom = that.e.find('#lottery_right');
            that.rewardDom = that.e.find('#lottery_zc_reward');

            that.freeData = {
                1 : 1,
                2 : 2,
                3 : 3,
                4 : 7,
                5 : 15,
                6 : 30
            };

            var sid, day = parseInt(log.day);
            for (var j = 1; j <= 6; j++) {
                if (day >= that.freeData[j]) {
                    sid = j;
                }
            }
            that.e.find('#lottery_left').find('ul').find('#lottery_free' + sid).addClass('orange');

            /*填充规则以及数据*/
            that.leftDom.find('#lx_day').html(lang('login_day_by_day', [log.day]));
            // 今日可购买次数
            var left_buy_times = parseInt(buy_times_limit) - parseInt(buy_times);
            that.leftDom.find('#lotterypop_times_can_buy').children('span').html(left_buy_times+'/'+buy_times_limit);
            // 今日剩余次数
            that.leftTimes = parseInt(log.total_num);
            that.leftDom.find('#lotterypop_times_left').children('span').html(that.leftTimes);
            if(buy_times == buy_times_limit) {//当日购买次数已达上限
                that.reachLimit = true;
                that.leftDom.find('#lotterypop_times_buy_btn').attr('class', 'btn pop_btn_middle_disable');
            }

            /*填充右侧奖品以及数据*/
            that.fillRightData(list);

            if (that.leftTimes == 0) {
                that.rightDom.find('#lottery_start').addClass('lottery_start_btn_disable');
            } else {
                that.rightDom.find('#lottery_start').addClass('lottery_start_btn');
            }

            that.pos = 0;
            that.bindAll();

            that.e.show();
            $('#worldloading').hide();
        },

        fillRightData: function(list) {
            this.giftData = {};
            var dom = this.rightDom.find('ul');
            var html = '';
            for (var i = 0; i <= 7; i++) {
                /*填充数据*/
                this.giftData[list[i]['id']] = list[i];
                this.giftData[list[i]['id']].pos = i;
                /*填充dom*/
                html += '<li class="pos'+ i + '" id="lottery_pos'+ i + '"><span id="lottery_img">' + this.getImageSrc(list[i]) + '</span><span id="lottery_name">' + this.getName(list[i]) + '</span></li>'
            }
            dom.html(html);
        },
        
        getImageSrc: function(data) {
            var imgSrc = '';
            switch (parseInt(data.gettype)) {
                case 1:
                    imgSrc = '<img src="' + staticUrl + 'img/common/header_money.png" class="smi"/>';
                    break;
                case 2:
                    imgSrc = '<img src="' + staticUrl + 'img/common/header_gold.png" class="smi"/>';
                    break;
                case 3:
                    imgSrc = '<img src="' + staticUrl + 'img/common/header_junling.png" class="smi"/>';
                    break;
                case 4:
                    imgSrc = '<img src="' + xGame.avatar(data.gimage) + '" class="ho"/>';
                    break;
                case 5:
                    imgSrc = '<img src="' + staticUrl + 'img/pop/pop_gem2.png" class="smi"/>';
                    break;
                case 6:
                    imgSrc = '<img src="' + staticUrl + 'img/pop/pop_gem3.png" class="smi"/>';
                    break;
                case 7:
                    imgSrc = '<img src="' + xGame.itemurl(data.getvalue) + '" class="ho"/>';
                    break;
                case 8:
                    imgSrc = '<img src="' + staticUrl + 'img/pop/worshippop_type4.png" class="smi"/>';
                    break;
            }
            return imgSrc;
        },
        
        getName: function(data) {
            var name = '';
            switch (parseInt(data.gettype)) {
                case 1:
                    name = lang('silver') + data.getvalue;
                    break;
                case 2:
                    name = lang('gold') + data.getvalue;
                    break;
                case 3:
                    name = lang('act') + ' x' + data.getvalue;
                    break;
                case 4:
                    name = data.gname;
                    break;
                case 5://黄宝石
                    name = lang('yellow_jade') + ' x' + data.getvalue;
                    break;
                case 6://紫宝石
                    name = lang('purple_jade') + ' x' + data.getvalue;
                    break;
                case 7://装备
                    name = data.itemname;
                    break;
                case 8://突飞卡
                    name = lang('tfcard') + ' x' + data.getvalue;
                    break;
            }
            return name;
        },

        bindAll: function() {
            var that = this;
            that.leftDom.undelegate('#lotterypop_times_buy_btn', 'touchend').delegate('#lotterypop_times_buy_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if(that.reachLimit == true) {
                    xGame.toast.show(lang('today_times_limited'));
                } else {
                    that.buyTimes();
                }
            });
            
            that.rightDom.undelegate('button.lottery_start_btn', 'touchend').delegate('button.lottery_start_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.startLottery();
            });

            that.rightDom.undelegate('a#lotterp_refresh_btn', 'touchend').delegate('a#lotterp_refresh_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.refresh();
            });
            
            that.rightDom.undelegate('#show_zc_reward', 'touchend').delegate('#show_zc_reward', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.showZcReward();
            });
            
            that.rewardDom.undelegate('#lottery_back', 'touchend').delegate('#lottery_back', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.hideZcReward();
            });
            
            that.rewardDom.undelegate('.libtn.pop_btn_small', 'touchend').delegate('.libtn.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getReward($(this).attr('gid'));
            });
        },

        refresh: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                if (parseInt(res.status) == 1) {
                    player.updateinfo({
                        'gold': res.gold
                    });
                    that.resData = res;
                    that.creatList();
                } else if (parseInt(res.status) == -1) {
                    xGame.goPay(lang('no_gold_for_refresh'));
                    xGame.unlock();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    xGame.unlock();
                }
            }
            loadContents({
                'c': 'lottery',
                'm': 'refresh'
            }, Func);
            Func = null;
        },

        startLottery: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('no_user_data'));
                    xGame.unlock();
                    break;
                case - 2 : xGame.toast.show(lang('lottery_times_is_0'));
                    xGame.unlock();
                    break;
                case 1:
                    that.leftTimes -= 1;
                    that.leftDom.find('#lotterypop_times_left').children('span').html(res.total_num);
                    if (that.leftTimes <= 0) {
                        that.rightDom.find('#lottery_start').removeClass('lottery_start_btn').addClass('lottery_start_btn_disable');
                    }
                    that.resData = res;
                    that.lotteryAnimate(that.pos, parseInt(that.giftData[res.info.id].pos));
                    that.pos = that.giftData[res.info.id].pos;

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    xGame.unlock();
                    break;
                }
            };
            loadContents({
                'c': 'lottery',
                'm': 'action'
            }, Func);
            Func = null;
        },

        lotteryAnimate: function(pos, lastPos) {
            //console.log('最后的位置是' + lastPos);
            var that = this;
            var yRoundtimes = 1,
            //中间匀速至少得转几圈
            stimes = 1,
            //开始加速跑了几个
            ytimes = 0,
            //匀速跑了几个
            etimes = 3,
            //开始减速跑了
            speed = 50,
            //起始速度
            yEndPos = (pos + stimes) > 7 ? (pos + stimes - 8) : (pos + stimes),
            //匀速yRoundtimes圈后的位置
            lastSomePos,
            //匀速停止时应该到的位置，也就是目标往后倒 etimes 步
            yEndTimes,
            //匀速到最后5个还得跑几步,也就是到倒数5步的步数
            thread = null; //递归的方法
            //console.log('起始的位置是 ' + pos);
            //console.log('匀速' + yRoundtimes + '圈后的位置是 ' + yEndPos);

            /*计算匀速结束规定圈数后还得跑几次才能到目标位置*/
            lastSomePos = (lastPos - etimes) <= 0 ? (8 - etimes + lastPos) : (lastPos - etimes);
            //console.log('离目标倒退 ' + etimes + '个的坐标是 ：' + lastSomePos);
            yEndTimes = (lastSomePos <= yEndPos) ? (8 - yEndPos + lastSomePos) : (lastSomePos - yEndPos);
            //console.log('匀速到最后 ' + etimes + '个还得跑---- ' + yEndTimes);

            /*结束的减速方法*/
            var end = function() {
                //console.log('减速开始时pos = ' + pos);
                that.rightDom.attr('class', 'lottery_right pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    if (etimes == 0) {
                        //console.log('减速结束时pos = ' + pos);
                        that.showResultMsg();
                        yRoundtimes = stimes = ytimes = etimes = speed = yEndPos = lastSomePos = yEndTimes = yEndPos = null;
                        return;
                    }
                    etimes--;
                    pos++;
                    pos = pos > 7 ? 0 : pos;
                    speed += 100;
                    thread = arguments.callee;
                    that.rightDom.attr('class', 'lottery_right pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };

            /*匀速转圈方法*/
            var yunsu = function() {
                //console.log('匀速开始时的pos = '+pos);
                that.rightDom.attr('class', 'lottery_right pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    ytimes++;
                    pos++;
                    pos = pos > 7 ? 0 : pos;
                    //console.log('pos = '+ pos);
                    if (yRoundtimes <= 0) {
                        yEndTimes--;
                        if (yEndTimes == 0) {
                            end();
                            return;
                        }
                    }

                    if (ytimes > 7) {
                        ytimes = 0;
                        yRoundtimes--;
                    }
                    //console.log('ytimes = '+ ytimes);
                    that.rightDom.attr('class', 'lottery_right pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };
            /*开始的加速方法*/
            var start = function() {
                //console.log('加速开始时的pos = '+pos);
                that.rightDom.attr('class', 'lottery_right pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    stimes--;
                    pos++;
                    pos = pos > 7 ? 0 : pos;
                    if (stimes == 0) {
                        //console.log('加速结束时的pos = '+pos);
                        yunsu();
                        return;
                    }
                    speed -= 25;
                    that.rightDom.attr('class', 'lottery_right pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };
            
            start();

        },

        showResultMsg: function() {
            var that = this;
            var info = that.resData.info;
            switch (parseInt(info.gettype)) {
            case 1:
                player.updateinfo({
                    'silver':
                    parseInt(info.all_silver)
                });
                xGame.toast.show(lang('c_you_get_silver', [info.getvalue]));
                break;
            case 2:
                player.updateinfo({
                    'gold':
                    parseInt(info.all_gold)
                });
                xGame.toast.show(lang('c_you_get_gold', [info.getvalue]));
                break;
            case 3:
                player.updateinfo({
                    'act':
                    parseInt(info.all_act)
                });
                xGame.toast.show(lang('c_you_get_act', [info.getvalue]));
                break;
            case 4:
                miscpop.newhero(that.resData.generalinfo);
                if (typeof CD['chat'] != 'undefined') {
                    CD['chat'].read();//手动请求一次，为了早点广播
                }
                break;
            case 5://黄宝石
                xGame.toast.show(lang('c_you_get_gem2', [info.getvalue]));
                break;
            case 6://紫宝石
                xGame.toast.show(lang('c_you_get_gem3', [info.getvalue]));
                break;
            case 7://装备
                xGame.toast.show(lang('c_you_get_item', [info.itemname]));
                break;
            case 8://突飞卡
                xGame.toast.show(lang('c_you_get_tfcard', [info.getvalue]));
                break;
            }
            xGame.unlock();
            //that.creatList(callback);
        },

        creatList: function() {
            var that = this;
            that.rightDom.find('ul').addClass('fade_in').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                that.fillRightData(that.resData.list);
                that.rightDom.find('ul').removeClass('fade_in').addClass('fade_out').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                function() {
                    xGame.unlock();
                    that.rightDom.find('ul').removeClass('fade_out')
                });
            });
        },

        buyTimes: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.goPay(lang('gold_not_enough_for_buying'));
                    break;
                case - 2 : xGame.toast.show(lang('buy_fail_try_later'));
                    break;
                case - 3 : xGame.toast.show(lang('today_times_limited'));
                    break;
                case 1:
                    player.updateinfo({
                        'gold':
                        parseInt(res.gold)
                    });
                    var left_buy_times = parseInt(res.buy_times_limit) - parseInt(res.buy_times);
                    that.leftDom.find('#lotterypop_times_can_buy').children('span').html(left_buy_times+'/'+res.buy_times_limit);
                    that.leftDom.find('#lotterypop_times_left').children('span').html(res.total_num);
                    if (that.leftTimes == 0) {
                        that.rightDom.find('#lottery_start').removeClass('lottery_start_btn_disable').addClass('lottery_start_btn');
                    }
                    if(res.buy_times == res.buy_times_limit) {//当日购买次数已达上限
                        that.reachLimit = true;
                        that.leftDom.find('#lotterypop_times_buy_btn').attr('class', 'btn pop_btn_middle_disable');
                    }
                    that.leftTimes += 1;
                    //xGame.toast.show('购买成功！');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'lottery',
                'm': 'buy_lottery_num'
            }, Func);
            Func = null;
        },
        
        showZcReward: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case - 1 : 
                        xGame.toast.show(lang('lotterypop_getreward_fail'));
                        break;
                    case 1:
                        that.rewardDom.find('#register_day').html(res.regday);
                        that.rewardDom.find('#total_days').html(res.joinday + lang('day'));
                        
                        that.showHistory(res.history);
                        that.buildReward(res);
                        
                        if (that.rewardScroller == null) {
                            that.rewardScroller = new iScroll('lottery_zc_scroller', {
                                bounce: false,
                                momentum: false,
                                hScrollbar: false,
                                vScrollbar: false
                            });
                        }
                        
                        that.rightDom.css('display', 'none');
                        that.rewardDom.css('display', 'block');
                        that.rewardScroller.refresh();
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'rolegift',
                'm': 'index'
            }, Func);
            Func = null;
        },
        
        hideZcReward: function() {
            this.rightDom.css('display', 'block');
            this.rewardDom.css('display', 'none');
        },
        
        showHistory: function(data) {
            var reward = '';
            //只有黄宝石和紫宝石
            for (var i = 2;i < 4;i ++) {
                reward += '<span class="gem' + i + '">x' + data['jade' + i] + '</span>';
            }
            this.rewardDom.find('#can_receive').html(reward);
            reward = null;
        },
        
        buildReward: function(res) {
            var html = '';
            var button = '';
            for (var j = 0;j < res.list.length;j ++) {
                if (res.list[j].get == 0) {
                    button = '<button class="btn libtn pop_btn_small_disable" gid="' + res.list[j].id + '">' + lang('not_reached') + '</button>';
                } else if (res.list[j].get == 1) {
                    button = '<button class="btn libtn pop_btn_small" gid="' + res.list[j].id + '">' + lang('receive') + '</button>';
                } else if (res.list[j].get == 2) {
                    button = '<button class="btn libtn pop_btn_small_disable" gid="' + res.list[j].id + '">' + lang('warena_have_fetch') + '</button>';
                }
                html += '<li>' + lang('lotterypop_reward_li',[res.list[j].day, '<span class="li_t' + res.list[j].type + '">x' + res.list[j].num + button + '</span>']) + '</li>';
            }
            this.rewardDom.find('ul').html(html);
            
            html = null;
            button =null;
        },
        
        getReward: function(gid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case - 6 : 
                        xGame.toast.show(lang('champions_get_info_fail'));
                        break;
                    case - 5 : 
                        xGame.toast.show(lang('warena_fetch_reward_error'));
                        break;
                    case - 4 : 
                        xGame.toast.show(lang('beatengod_add_receive_history_fail'));
                        break;
                    case - 3 : 
                        xGame.toast.show(lang('lotterypop_unknown_type'));
                        break;
                    case - 2 : 
                        xGame.toast.show(lang('beatengod_get_box_reward_not_reach'));
                        break;
                    case - 1 : 
                        xGame.toast.show(lang('lotterypop_getreward_fail'));
                        break;
                    case 1:
                        //黄宝石，紫宝石
                        if (res.add.type == 1 || res.add.type == 2) {
                            xGame.toast.show(lang('beatengod_got_reward_gem', [parseInt(res.add.type) + 1, res.add.num]));
                        } else {
                            xGame.toast.show(lang('reward_success'));
                        }
                        that.showHistory(res.history);
                        that.buildReward(res);
                        
                        if (that.rewardScroller != null) {
                            that.rewardScroller.refresh();
                        }
                        
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'rolegift',
                'm': 'get_reward',
                'id': gid
            }, Func);
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.leftDom) {
                this.leftDom.undelegate('#lotterypop_times_buy_btn', 'touchend');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('button.lottery_start_btn', 'touchend');
                this.rightDom.undelegate('a#lotterp_refresh_btn', 'touchend');
                this.rightDom.undelegate('#show_zc_reward', 'touchend');
                this.rightDom.find('ul').unbind('webkitAnimationEnd');
                this.rightDom.remove();
                this.rightDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.undelegate('#lottery_back', 'touchend');
                this.rewardDom.undelegate('.libtn.pop_btn_small', 'touchend');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;

            if (this.rewardScroller != null) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            
            this.giftData = this.leftTimes = this.resData = this.freeData = this.pos = null;

            this.reachLimit = false;
            this.unbindAll();
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
        },
    };
})();