/* 首充礼包*/
(function() {
    firstpaygiftPopClass = function() {
        this.e;
        this.e2;
        this.isshow = false;
        this.rewardDom;
        this.btnDom;
        this.myScroller;
        this.rewardData;//奖励数据
        this.deleteThread;//删除线程
        this.pos;//抽奖的结束位置
        this.lotteryDom;
        this.giftData = {}; //奖励数据，以id为键
        this.giftResult; //存取抽奖结果
        this.listItemMarginB = 0; //奖励列表的下边距
        this.gettype; //奖励领取状态
        this.isTimeout = [];
    };

    firstpaygiftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                
                if (res.status == '1') {
                    that.initPage(res);
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                    return false;
                }
            };
            
            //加载数据
            loadContents({
                'c': 'first_recharge',
                'm': 'index'
            },
            Func);
            Func = null;
        },
        
        initPage: function(res) {
            
            this.e = xGame.loadPopTmpl('firstpaygift');

            this.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                firstpaygiftpop.hide();
                e.preventDefault();
            });
            
            this.rewardDom = this.e.find('#firstpaygift_list');
            this.btnDom = this.e.find('#firstpaygiftpop_wrap').find('.bg');
            this.rewardData = res.list;
            this.buildList(this.rewardData);
            this.bindAll();

            this.e.show();
            $('#worldloading').hide();
        },
        
        buildList: function(list) {
            var that = this;
            var html = '';
            var len = list.length;
            var firstitem_key = -1;

            for (var i = 0; i < len;i ++) {
                if (parseInt(list[i].status) == 1) {
                    //可领取
                    firstitem_key = firstitem_key == -1 ? i : firstitem_key; 
                    html += '<li id="firstpaygift_l' + list[i].id + '">';
                    html += '<button class="btn pop_btn_middle" gid="' + list[i].id + '"><b>' + lang('receive') + '</b></button>';
                } else if(parseInt(list[i].status) == 2) {
                    //已领取，按钮变灰
                    firstitem_key = firstitem_key == -1 ? i : firstitem_key; 
                    html += '<li id="firstpaygift_l' + list[i].id + '">';
                    html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '">' + lang('warena_have_fetch') + '</button>';
                    if(i == 0){
                        that.gettype = 2;
                        that.e.find('#firstpaygiftpop_wrap').css('display', 'none');
                        that.tmLottery(true);
                    }
                } else {
                    //未达成，按钮变灰
                    html += '<li id="firstpaygift_l' + list[i].id + '">';
                    html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '">' + lang('not_reached') + '</button>';
                }

                html +='<div class="title">' + list[i].name + '</div>';
                html += that.buildRewardHtml(list[i]);
                html += '</li>';
            }
            that.rewardDom.html(html);            
            html = null;
            
            that.myScroller = new iScroll('firstpaygift_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.listItemMarginB = parseInt(that.rewardDom.children('li').css('margin-bottom'));
            if(firstitem_key && firstitem_key != -1) {//
                var max = (125 * list.length - 230) * -1;
                var my = (125 * firstitem_key) * -1;
                console.log('scrollto ' + my);
                if(my < max) {
                    that.rewardDom.children('li:last-child').css('margin-bottom', max - my);
                }
                that.myScroller.scrollTo(0, my);
            }
            that.myScroller.refresh();
        },
        
        bindAll: function() {
            var that = this;

            //奖励列表显示处理动画
            that.rewardDom.unbind('touchend').one('touchend', function () {
                that.rewardDom.children('li:last-child').css('margin-bottom', that.listItemMarginB + 'px');
                that.myScroller.refresh();
            });

            // 领取奖励
            that.rewardDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.getGift(parseInt($(this).attr('gid')));
                $(this).attr('class', 'btn pop_btn_middle_disable');
                $(this).html(lang('warena_have_fetch'));
            });

            //查看装备信息
            that.rewardDom.undelegate('.reward2', 'click').delegate('.reward2', 'click',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showItemInf(that.rewardData[$(this).attr('gid') - 1].equip, true);
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.find('#tmlotterypop_item_info_dom').unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                $(this).css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#tmlotterypop_item_info_main').unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(e.target.id == 'tmlotterypop_info_hide') {
                    that.e.find('#tmlotterypop_item_info_dom').css('display', 'none');
                }
                e.preventDefault();
                e.stopPropagation();
            });

            // 天命转盘
            that.btnDom.undelegate('#tm_lottery_btn', 'touchend').delegate('#tm_lottery_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                $('#ajaxloading').show();
                that.tmLottery();

            });
            // 充值
            that.btnDom.undelegate('#first_pay_btn', 'touchend').delegate('#first_pay_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                firstpaygiftpop.hide();
                xGame.enterPay();
            });
        },
        
        buildRewardHtml: function(data) {
            var html = '';
            for (var i = 1; i < 5;i ++) {
                if (data['gettype' + i] == 2) {
                    html += '<div class="reward reward' + i + ' rtype' + data['gettype' + i] + '" gid="' + data.id + '">';
                    html += '<span class="horse" style="background:url(' + xGame.itemurl(data['equip'].image) + ') 0 0 no-repeat; background-size: 55px;"></span>';
                    html += '<b class="item_str" style="color: ' + helper.getItemColor(data['equip'].quality) + '">' + data['equip'].str + '</b>';
                    html += '</div>';
                } else {
                    html +='<div class="reward reward' + i + ' rtype' + data['gettype' + i] + '">';
                    html +='<span></span>';
                    html +='<b>x' + data['getvalue' + i] + '</b>';
                    html +='</div>';
                }
            }
            return html;
        },
        
        getGift: function(gid) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case 1:                        
                        player.reloadInfo();
                        that.rewardDown(that.rewardData[gid - 1], gid - 1);                        
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'first_recharge',
                'm': 'get_reward',
                'id': gid
            },
            Func);
            Func = null;
        },
        
        rewardDown: function(data, gid) {
            var that = this;
            var html = '';

            html += '<div class="firstpaygift_reward_box">';
            for (var i = 1;i < 5; i ++) {
                if(data['gettype' + i] == '2') {
                    html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '">';
                    html += '<em style="background:url(' + xGame.itemurl(data['equip'].image) + ') 0 0 no-repeat; background-size: 40px;"></em>';
                    html += '<span style="color:' + helper.getItemColor(data['equip'].quality) + '">' + data['equip'].str + '</span>';
                    html += '</span>';
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + data['gettype' + i] + '"><em></em>x' + data['getvalue' + i] + '</span>';
                }
            }
            html += '</div>';
            that.e.find('#firstpaygiftpop_wrap').append(html);
            
            var i = 0;
            var domRewardBox = that.e.find('.firstpaygift_reward_box');
            domRewardBox.unbind('webkitAnimationEnd').bind('webkitAnimationEnd',function(){
                if(i == 3) {
                    that.isTimeout.push(setTimeout(function() {
                        domRewardBox.remove();
                        domRewardBox = null;
                        xGame.unlock();
                        if(gid == 0 && that.e2 == null) {
                            that.tmLottery(false, true);
                            $('#ajaxloading').show();
                        }
                    },1000));
                } else {
                    i++;
                }
            });            
            html = null;
        },

        // 天命转盘
        tmLottery: function(type, type2) {//type 表示已领奖没转盘 type2表示已转盘没领奖
            var that = this;

            that.e2 = xGame.loadPopTmpl('tmlottery');

            that.e2.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.tmLotteryHide();
                if(that.gettype == 2) {
                    that.hide();
                }
                e.preventDefault();
            });

            that.lotteryDom = that.e2.find('.tmlottery');

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    if(type2 == true && res.lottery == 0) {
                        console.log('不弹出转盘');
                        that.tmLotteryHide();
                        $('#ajaxloading').hide();
                    } else {
                        //填充转盘数据
                        that.fillData(res.list);
                        if(type != true) {
                            xGame.islock = true;
                            xGame.doAnimate(that.e2.find('.tmlotterypop_content'), 'tmlottery_scalein', '0.6s', function() {
                                xGame.unlock();
                            });
                        }
                        that.e2.find('#tmlotterypop_wrap').show();
                        $('#ajaxloading').hide();

                        // 绑定开始按钮
                        if(parseInt(res.lottery) == 0) {
                            that.lotteryDom.find('.tmlottery_tips2').show();
                        }
                        if(parseInt(res.lottery) == 1) {
                            that.lotteryDom.find('.tmlottery_tips2').hide();
                            that.lotteryDom.undelegate('#tmlottery_start', 'touchend').delegate('#tmlottery_start', 'touchend',
                            function() {
                                if (xGame.checkLock(true) || $(this).hasClass('disable')) { //不设置属性
                                    return false;
                                }
                                that.startLottery();

                                that.lotteryDom.find('#tmlottery_start').attr('class', 'btn disable');
                            });
                        } else {
                            that.lotteryDom.find('#tmlottery_start').attr('class', 'btn disable');
                        }
                        
                        //绑定弹出武将和装备信息
                        that.lotteryDom.undelegate('li', 'click').delegate('li', 'click',
                        function() {
                            if (xGame.checkLock(true)) { //不设置属性
                                return false;
                            }
                            var gid = $(this).attr('gid');
                            var data = that.giftData[gid];
                            if(data.gettype == 2) {                            
                                that.showItemInf(data)
                            } else {
                                that.showHeroInf(data)
                            }
                            data = null;
                        });

                        that.e2.undelegate('#tmlotterypop_info_dom', 'touchend').delegate('#tmlotterypop_info_dom', 'touchend',
                        function(e) {
                            if (xGame.checkLock(true)) { //不设置属性
                                return false;
                            }
                            $(this).css('display', 'none');
                            e.preventDefault();
                            e.stopPropagation();
                        });
                        that.e2.find('#tmlotterypop_info_main').unbind('touchend').bind('touchend', function (e) {
                            if (xGame.checkLock(true)) {
                                return false;
                            }
                            if(e.target.id == 'tmlotterypop_info_hide') {
                                that.e2.find('#tmlotterypop_info_dom').css('display', 'none');
                            }
                            e.preventDefault();
                            e.stopPropagation();
                        });

                        that.e2.find('#tmlotterypop_item_info_dom').unbind('touchend').bind('touchend', function (e) {
                            if (xGame.checkLock(true)) {
                                return false;
                            }
                            $(this).css('display', 'none');
                            e.preventDefault();
                            e.stopPropagation();
                        });

                        that.e2.find('#tmlotterypop_item_info_main').unbind('touchend').bind('touchend', function (e) {
                            if (xGame.checkLock(true)) {
                                return false;
                            }
                            if(e.target.id == 'tmlotterypop_info_hide') {
                                that.e2.find('#tmlotterypop_item_info_dom').css('display', 'none');
                            }
                            e.preventDefault();
                            e.stopPropagation();
                        });
                    }
                    break;
                default:
                    xGame.toast.show(res.msg);
                    break;
                }
            };
            loadContents({
                'c': 'first_recharge',
                'm': 'lottery'
            },
            Func, true, that.tmLotteryHide); //加载数据
            Func = null;
        },

        fillData: function(list) {
            var html = '';
            for (var i = 0; i <= 11; i++) {
                this.giftData[list[i].id] = list[i];
                this.giftData[list[i].id].pos = i;
                /*填充dom*/
                if(list[i].gettype == 2) {  //装备
                    html += '<li class="item pos'+ i + '" id="newyearlottery_pos'+ i + '" gid="' + list[i].id + '"><img src="' + xGame.itemurl(list[i].image) + '" class="ho"/><span id="item_name" style="color:' + helper.getItemColor(list[i].quality) + '">' + list[i].str + '</span></li>';
                } else {                    //将领
                    html += '<li class="avatar pos'+ i + '" id="newyearlottery_pos'+ i + '" gid="' + list[i].id + '"><img src="' + xGame.avatar(list[i].image) + '" class="ho"/><span id="avatar_name">' + list[i].name + '</span></li>';
                }
            }
            this.e2.find('.tmlottery_right_ul').html(html).fadeIn(300);
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
            html += '<p class="hero_talent hero_infop"><span>' + data.talent + '</span>：' + data.talent_desc + '</p>';
            html += '<p class="hero_skill_title">' + lang('springshoppop_skill') + '</p>';
            html += '<p class="hero_skill hero_infop"><span>' + data.skill + '</span>：' + data.skill_desc + '</p>';
            html += '<div id="tmlotterypop_info_hide"></div>';
            
            this.e2.find('#tmlotterypop_info_main').html(html);
            html = null;
            this.e2.find('#tmlotterypop_info_dom').css('display', 'block');
        },

        showItemInf: function (data, type) {
            
            var html = '';
            html += '<div class="itembox c_i_bj' + data.quality + '">';
            html += '<span style="background:url(' + xGame.itemurl(data.id) + ') center center no-repeat;"></span>';
            html += '<em class="needlevel">' + lang('need_lv') + data.needlevel + '</em>';
            html += '</div>';
            html += '<div class="iteminfo">';
            html += '<h4 style="color:' + helper.getItemColor(data.quality) + '">' + data.str + '</h4>';
            html += '<p class="vocation">' + lang('mountpop_job') + '：' + helper.getItemVocation(data.type) + '</p>';
            html += '<p class="powerup">' + helper.getItemPowerName(data.type) + '：' + data.power + '</p>';
            html += '</div>';
            html += '<div id="tmlotterypop_info_hide"></div>';
	    
            if(type == true) {                
                this.e.find('#tmlotterypop_item_info_main').html(html);
                this.e.find('#tmlotterypop_item_info_dom').css('display', 'block');
            } else {                
                this.e2.find('#tmlotterypop_item_info_main').html(html);
                this.e2.find('#tmlotterypop_item_info_dom').css('display', 'block');
            }
            html = null;
        },


        startLottery: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 1:
                    that.giftResult = res.info;

                    that.lotteryAnimate(0, parseInt(that.giftData[res.info.id].pos));//播动画                  
                    break;
                default:
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                    break;
                }
            };
            loadContents({
                'c': 'first_recharge',
                'm': 'action'
            },
            Func);
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
            speed = 30,
            //起始速度
            yEndPos = (pos + stimes) > 11 ? (pos + stimes - 12) : (pos + stimes),
            //匀速yRoundtimes圈后的位置
            lastSomePos,
            //匀速停止时应该到的位置，也就是目标往后倒 etimes 步
            yEndTimes,
            //匀速到最后5个还得跑几步,也就是到倒数5步的步数
            thread = null; //递归的方法
            //console.log('起始的位置是 ' + pos);
            //console.log('匀速' + yRoundtimes + '圈后的位置是 ' + yEndPos);

            /*计算匀速结束规定圈数后还得跑几次才能到目标位置*/
            lastSomePos = (lastPos - etimes) <= 0 ? (12 - etimes + lastPos) : (lastPos - etimes);
            //console.log('离目标倒退 ' + etimes + '个的坐标是 ：' + lastSomePos);
            yEndTimes = (lastSomePos <= yEndPos) ? (12 - yEndPos + lastSomePos) : (lastSomePos - yEndPos);
            //console.log('匀速到最后 ' + etimes + '个还得跑---- ' + yEndTimes);

            /*结束的减速方法*/
            var end = function() {
                //console.log('减速开始时pos = ' + pos);
                that.lotteryDom.find('#tmlottery_right').attr('class', 'tmlottery_right pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    if (etimes == 0) {
                        //console.log('减速结束时pos = ' + pos);
                        that.showResultMsg();
                        yRoundtimes = stimes = ytimes = etimes = speed = yEndPos = lastSomePos = yEndTimes = yEndPos = null;
                        return;
                    }
                    etimes--;
                    pos++;
                    pos = pos > 11 ? 0 : pos;
                    speed += 100;
                    thread = arguments.callee;
                    that.lotteryDom.find('#tmlottery_right').attr('class', 'tmlottery_right pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };

            /*匀速转圈方法*/
            var yunsu = function() {
                //console.log('匀速开始时的pos = '+pos);
                that.lotteryDom.find('#tmlottery_right').attr('class', 'tmlottery_right pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    ytimes++;
                    pos++;
                    pos = pos > 11 ? 0 : pos;
                    //console.log('pos = '+ pos);
                    if (yRoundtimes <= 0) {
                        yEndTimes--;
                        if (yEndTimes == 0) {
                            end();
                            return;
                        }
                    }

                    if (ytimes > 11) {
                        ytimes = 0;
                        yRoundtimes--;
                    }
                    //console.log('ytimes = '+ ytimes);
                    that.lotteryDom.find('#tmlottery_right').attr('class', 'tmlottery_right pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };
            /*开始的加速方法*/
            var start = function() {
                //console.log('加速开始时的pos = '+pos);
                that.lotteryDom.find('#tmlottery_right').attr('class', 'tmlottery_right pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    stimes--;
                    pos++;
                    pos = pos > 11 ? 0 : pos;
                    if (stimes == 0) {
                        //console.log('加速结束时的pos = '+pos);
                        yunsu();
                        return;
                    }
                    speed -= 25;
                    that.lotteryDom.find('#tmlottery_right').attr('class', 'tmlottery_right pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };
            
            start();

        },

        // 处理抽奖结果
        showResultMsg: function() { 
            var that = this;
            xGame.unlock();

            if(that.giftResult.gettype == 2) {
                var str;
                str = '<em style="color:' + helper.getItemColor(that.giftResult.quality) + '">' + that.giftResult.str + '</em>'
                xGame.toast.show(lang('c_you_get_item', [str]));
            } else if (that.giftResult.gettype == 1){
                if(that.giftResult.jadetype && that.giftResult.jadetype == 3) {     // 当抽到已有的将的时返回紫宝石
                    xGame.toast.show(lang('firstpaygiftpop_gem3', [that.giftResult.jadenum]));
                } else {
                    miscpop.newhero(that.giftResult);
                }
            }
            player.reloadInfo();
        },

        tmLotteryHide: function() {
            xGame.unlock();
            this.pos = null;

            this.e2.remove();
            this.e2 = null;
        },

        unbindAll: function () {
            if (this.rewardDom) {
                this.rewardDom.undelegate('.pop_btn_middle', 'click');
                this.rewardDom.unbind('touchend');
                this.rewardDom.undelegate('.reward2', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.e) {
                this.e.find('#tmlotterypop_item_info_dom').unbind('touchend');
                this.e.find('#tmlotterypop_item_info_main').unbind('touchend');
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('.firstpaygift_reward_box').unbind('webkitAnimationEnd');
                this.e.remove();
                this.e = null;
            }
            if (this.btnDom) {
                this.btnDom.undelegate('#tm_lottery_btn', 'touchend');
                this.btnDom.undelegate('#first_pay_btn', 'touchend');
                this.btnDom.remove();
                this.btnDom = null;
            }
            if (this.e2) {
                this.e2.find('.hidebtn').unbind('touchend');
                this.e2.find('#tmlotterypop_info_main').unbind('touchend');
                this.e2.find('#tmlotterypop_item_info_dom').unbind('touchend');
                this.e2.find('#tmlotterypop_item_info_main').unbind('touchend');
                this.e2.undelegate('#tmlotterypop_info_dom', 'touchend');
                this.e2.remove();
                this.e2 = null;
            }
            if (this.lotteryDom) {
                this.lotteryDom.undelegate('#tmlottery_start', 'touchend');
                this.lotteryDom.undelegate('li', 'click');
                this.lotteryDom.remove();
                this.lotteryDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.giftData = {};
            this.giftResult = null;
            this.listItemMarginB = 0;
            this.gettype = null;
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller = null;
            }

            if (this.deleteThread) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }
            for (var i  = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            this.lastDay = null;
            this.rewardData = null;
            this.unbindAll();
        },
    };
})();