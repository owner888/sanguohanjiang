/* 春节抽奖 */
(function() {
    newyearlotteryPopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom;
        this.rightDom;

        this.rewardScroller = null;

        this.giftData = {}; //奖励数据，以id为键

        this.resData; //返回数据

        this.pos; //当前的位置
        this.box; //当前boxid
        this.lastExtreme; // 上次是否至尊

        this.lotteryList; // 保存转盘奖品

        this.isEnd;    //活动是否结束

        this.isTimeout = [];
    };
    newyearlotteryPopClass.prototype = {
        init: function() {
            var that = this;
            
            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    that.fillData(res);
                    break;               
                default:
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                    break;
                }
            };
            loadContents({
                'c': 'springlottery',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        fillData: function(res) {
            var that = this;

            that.resData = res;

            that.lotteryList = that.resData.list;
            this.isEnd = res.end; //判断活动是否结束

            that.e = xGame.loadPopTmpl('newyearlottery');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                newyearlotterypop.hide();
                e.preventDefault();
            });

            that.leftDom = that.e.find('#newyearlottery_left');
            that.rightDom = that.e.find('#newyearlottery_right');
            
            /* 填充左侧数据 */
            that.playerTopList(that.resData.userlist);
            that.leftReward(that.resData.cd, that.resData.is_cd, that.resData.my_rank);

            /*填充右侧奖品以及数据*/
            that.fillRightData(parseInt(that.resData.times), res.costgold, res.lottery);
            that.rightDom.find('.lottery_times').html(res.times + ' ' +lang('times'));
            
            this.lastExtreme = that.resData.extreme;    //保存上一次奖盘状态
            
            if(that.resData.extreme == 0) {     // 不是至尊抽奖
                this.rightDom.attr('class', 'newyearlottery_right box1');
                this.rightDom.find('.newyearlottery_tops1').show();
                that.box = 1;
            } else {
                this.rightDom.attr('class', 'newyearlottery_right box2');
                this.rightDom.find('.newyearlottery_tops1').hide();
                that.box = 2;
            }

            if(this.isEnd == 1) {                
                this.rightDom.find('.newyearlottery_tops1').hide();
            }

            //填充活动时间
            this.rightDom.find('.newyearlottery_time').find('em').html(res.time);

            that.pos = 0;
            that.bindAll();

            that.e.show();
            $('#worldloading').hide();
        },

        //玩家排名列表
        playerTopList: function(list) {
            var html ='';
            var that = this;
            for(var i = 0; i < list.length; i++){
                //判断奖励物资类型
                var className = that.getListImg(list[i].type);
                var reward = (list[i]["reward"] >= 10000 ? list[i]["reward"] / 10000 + lang('10k') : list[i]["reward"]);

                var color = '';
                if(list[i].info && list[i].info.quality && className != 'essence') {
                    color = helper.getItemColor(list[i].info.quality);
                    reward = list[i].info.name;
                }

                //构建列表，判断是否是自己
                html += '<li class="newyearlottery_user_list_li '+(list[i]["uid"] == player.info.uid ? 'yellow' : '')+'"><em class="top">' + (i + 1) + '</em><em class="nickname" uid="' + list[i]["uid"] + '">' + list[i]["nickname"] + '</em><em class="times">' + list[i]["times"] + '</em><span class="' + className + ' list_reward" style="color:' + color;
                if(className == 'essence') {
                    html += ';background-image: url(' + xGame.essence(list[i].info.id) + ');';
                }
                html += '">' + reward + '</span></li>';

            }
            that.leftDom.find('#newyearlotterypop_list').html(html);

            if(that.rewardScroller == null) {
                that.rewardScroller = new iScroll('newyearlottery_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });       
            }

            that.isTimeout.push(setTimeout(function() {
                that.rewardScroller.refresh();
            }, 200));
        },

        getListImg: function(type) {
            var className;
            switch(parseInt(type)) {
            case 8://装备
            case 11://坐骑
            case 13://武将
                className = 'text';
                break;
            case 1://元宝
                className = 'add_gold';
                break;
            case 2://银币
                className = 'add_silver';
                break;
            case 3://黄宝石
                className = 'add_get2';
                break;
            case 4://紫宝石
                className = 'add_get3';
                break;
            case 5://原魂
                className = 'add_soul';
                break;
            case 6://水晶
                className = 'add_jade';
                break;
            case 7://声望
                className = 'add_prestige';
                break;
            case 9://玉石碎片
                className = 'add_gem_part';
                break;
            case 10://突飞卡
                className = 'add_tfcard';
                break;
            case 12://蓝宝石
                className = 'add_get1';
                break;
            case 14://军令
                className = 'add_junling';
                break;
            case 15://精炼石
                className = 'add_refinestone';
                break;
            case 16://五彩石
                className = 'add_colorstone';
                break;
            case 17://上古血玉
                className = 'add_bloodstone';
                break;
            case 18://装备精华
                className = 'essence';
                break;
            case 19://锻造材料
                className = 'forge_material';
                break;
            }

            return className;
        },

        //玩家礼包领取
        leftReward: function(cd, is_cd, my_rank) {
            var that = this;
            var rewardContent = that.leftDom.find('.newyearlottery_reward_centent');
            var rewardBtn = that.leftDom.find('#newyearlottery_reward_btn');
            var html = '';

            var className = that.getListImg(my_rank.type);
            if (className == 'essence') {
                className = 'essence" style="background-image:url(' + xGame.essence(my_rank.essence_id) + ')';
            } else if (className == 'forge_material') {
                className = 'forge_material" style="background-image:url(' + xGame.forgeMaterial(my_rank.essence_id) + ')';
            }

            if(parseInt(is_cd) == 1) { //昨天没有上榜
                if(that.isEnd == 1) {//活动已结束
                        rewardBtn.removeClass('btn_middle').addClass('disable').html(lang('had_end'));
                } else {

                    html += '<span class="reward_title">' + lang('qingminpop_current_rank') + my_rank['rank'] + '</span>';
                    xGame.updateCd('newyearlottery', cd);
                    if(parseInt(my_rank['rank']) <= 30) {
                        html += '<div><span class="reward_title">' + lang('qingminpop_current_reward') + '</span><span class="new_reward_number ' + className + '" style="'+ (my_rank['quality'] ? 'color:' + helper.getItemColor(my_rank['quality']) : '') +'">' + (my_rank['name'] || 'x' + my_rank['my_reward']) + '</span></div>';
                    } else {
                        if(parseInt(player.info.vip) < 2) {
                            html += '<span class="no_reward_number vip2_open">' + lang('vip2_open') + '</span>';
                        } else {                            
                            html += '<span class="no_reward_number">' + lang('beatengod_not_reach') + '</span>';
                        }
                    }
                }
            } else if(parseInt(is_cd) == 0) {   //昨天上榜了
                rewardBtn.html(lang('warena_fetch'));
                html += '<span class="reward_title">' + lang('newyearlotterypop_yesterday_top')+ ':&nbsp;&nbsp;' + my_rank['rank'] + '</span><div><span class="reward_title">' + lang('newyearlotterypop_yesterday_reward') + ':</span><span class="new_reward_number ' + className + '" style="'+ (my_rank['quality'] ? 'color:' + helper.getItemColor(my_rank['quality']) : '') +'">' + (my_rank['name'] || 'x' + my_rank['my_reward']) + '</span></div>';
            }
            rewardContent.html(html);

        },

        fillRightData: function(times, costgold, lottery, extreme) {
            if(extreme == 1){
                lottery = 1;
            }
            var dom = this.rightDom.find('.newyearlottery_right_ul');
            var html = '';

            if(this.isEnd == 1) {//活动已结束
                var html1 = lang('had_end');
                this.rightDom.find('#newyearlottery_start').removeClass('free').addClass('disable').html(html1);
            } else {
                var html1 = '<em>' + lang('newyearlotterypop_start_lottery') + '</em>';
                if(lottery == 1){
                    html1 += '<b class="newyearlotterypop_free">' + lang('sacredtreepop_free_this_time') + '</b>';
                    this.rightDom.find('#newyearlottery_start').html(html1).addClass('free');
                }else {
                    html1 += '<b class="newyearlotterypop_gold">x' + costgold + '</b>';
                    this.rightDom.find('#newyearlottery_start').removeClass('free').html(html1);
                }
            }

            if(typeof extreme == 'undefined' || this.lastExtreme != extreme) {
                this.giftData = {};
                for (var i = 0; i <= 12; i++) {
                    /*填充数据*/
                    this.giftData[this.lotteryList[i]['id']] = this.lotteryList[i];
                    this.giftData[this.lotteryList[i]['id']].pos = i;
                    /*填充dom*/
                    html += '<li class="pos'+ i + '" id="newyearlottery_pos'+ i + '"><span id="newyearlottery_img">' + this.getImageSrc(this.lotteryList[i]) + '</span><span id="newyearlottery_gift_name">' + this.getName(this.lotteryList[i]) + '</span></li>';
                }
                dom.html(html);
            }

        },
        
        getImageSrc: function(data) {
            var imgSrc = '';
            switch (parseInt(data.gettype)) {
                case 1://元宝
                    imgSrc = '<img src="' + staticUrl + 'img/common/header_gold.png" class="smi"/>';
                    break;
                case 2://银币
                    imgSrc = '<img src="' + staticUrl + 'img/common/header_money.png" class="smi"/>';
                    break;
                case 3://黄宝石
                    imgSrc = '<img src="' + staticUrl + 'img/pop/pop_gem2.png" class="smi"/>';
                    break;
                case 4://紫宝石
                    imgSrc = '<img src="' + staticUrl + 'img/pop/pop_gem3.png" class="smi"/>';
                    break;
                case 5://原魂
                    imgSrc = '<img src="' + staticUrl + 'img/pop/original_soul.png" class="soul"/>';
                    break;
                case 6://水晶
                    imgSrc = '<img src="' + staticUrl + 'img/common/stone_icon.png" class="smi"/>';
                    break;
                case 7://声望
                    imgSrc = '<img src="' + staticUrl + 'img/common/prestige_icon.png" class="smi"/>';
                    break;
                case 8://装备
                    imgSrc = '<img src="' + xGame.itemurl(data.image) + '" class="ho"/>';
                    break;
                case 9://魔石碎片
                    imgSrc = '<img src="' + staticUrl + 'img/pop/gemworld_part.png" class="smi"/>';
                    break;
                case 10://突飞卡
                    imgSrc = '<img src="' + staticUrl + 'img/pop/worshippop_type4.png" class="smi"/>';
                    break;
                case 11://坐骑
                    imgSrc = '<span class="smi horse" style="background-position:' + helper.getHorsePostion('small', data.image) +'"></span>';
                    break;
                case 12://蓝宝石
                    imgSrc = '<img src="' + staticUrl + 'img/pop/pop_gem1.png" class="smi"/>';
                    break;
                case 13://武将
                    imgSrc = '<img src="' + xGame.avatar(data.image) + '" class="ho"/>';
                    break;
                case 14://军令
                    imgSrc = '<img src="' + staticUrl + 'img/common/header_junling.png" class="smi"/>';
                    break;
                case 15://精炼石
                    imgSrc = '<img src="' + ximg(staticUrl + 'img/pop/refinestone.png') +'" class="smi"/>';
                    break;
                case 16://五彩石
                    imgSrc = '<img src="' + ximg(staticUrl + 'img/pop/colorstone.png') + '" class="smi"/>';
                    break;
                case 17://上古血玉
                    imgSrc = '<img src="' + ximg(staticUrl + 'img/pop/bloodstone.png') + '" class="smi"/>';
                    break;
                case 18://装备精华
                    imgSrc = '<img src="' + xGame.essence(data.essence_id) + '" class="smi"/>';
                    break;
                case 19://锻造材料
                    imgSrc = '<img src="' + xGame.forgeMaterial(data.materials_id) + '" class="smi"/>';
                    break;
            }
            return imgSrc;
        },
        
        getName: function(data) {
            var name = '';
            switch (parseInt(data.gettype)) {
                case 1:
                    name = data.getvalue;
                    break;
                case 2:
                    name = data.getvalue > 100000 ? 'x' + (data.getvalue / 10000) + lang('10k') : data.getvalue;
                    break;
                case 3://黄宝石
                    name =  'x' + data.getvalue;
                    break;
                case 4://紫宝石
                    name = 'x' + data.getvalue;
                    break;
                case 5://原魂
                    name = 'x' + data.getvalue;
                    break;
                case 6://水晶
                    name = 'x' + data.getvalue;
                    break;
                case 7://声望
                    name = 'x' + data.getvalue;
                    break;
                case 8://装备
                    name = '<em style="color:' + helper.getItemColor(data.quality) + '">' + data.str + '</em>';
                    break;
                case 9://魔石碎片
                    name = 'x' + data.getvalue;
                    break;
                case 10://突飞卡
                    name = 'x' + data.getvalue;
                    break;
                case 11://坐骑
                    name = '<em style="color:' + helper.getItemColor(data.quality) + '">' + data.str + '</em>';
                    break;
                case 12://蓝宝石
                    name = 'x' + data.getvalue;
                    break;
                case 13://武将
                    name = '<em style="color:' + helper.getItemColor(data.quality) + '">' + data.str + '</em>';
                    break;
                case 14://军令
                    name = 'x' + data.getvalue;
                    break;
                case 15://精炼石
                    name = 'x' + data.getvalue;
                    break;
                case 16://五彩石
                    name = 'x' + data.getvalue;
                    break;
                case 17://上古血玉
                    name = 'x' + data.getvalue;
                    break;
                case 18://装备精华
                    name = '<em style="color:' + helper.getItemColor(data.quality) + '">' + data.name + 'x' + data.num + '</em>';
                    break;
                case 19://锻造材料
                    name = '<em style="color:' + helper.getItemColor(data.quality) + '">' + data.name + 'x' + data.num + '</em>';
                    break;
            }
            return name;
        },

        bindAll: function() {
            var that = this;
            that.leftDom.undelegate('#newyearlottery_reward_btn.btn_middle', 'touchend').delegate('#newyearlottery_reward_btn.btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                } 
                that.getReward();
            });
            
            that.leftDom.undelegate('.nickname', 'click').delegate('.nickname', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                } 
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
            
            if(that.isEnd == 0) { //活动是否结束
                that.rightDom.undelegate('#newyearlottery_start', 'touchend').delegate('#newyearlottery_start', 'touchend',
                function() {
                    if (xGame.checkLock(true) || $(this).hasClass('disable')) { //不设置属性
                        return false;
                    }
                    that.startLottery();
                });           
            }
        },
       
        startLottery: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 1:

                    that.lotteryAnimate(that.pos, parseInt(that.giftData[res.info.id].pos), res.extreme, res.times, res.costgold, res);//播动画

                    that.leftReward(res.cd, res.is_cd, res.my_rank);//更新左边领取奖励
                    player.updateinfo({
                        'gold':
                        parseInt(res.info.all_gold)
                    });

                    that.rightDom.find('.lottery_times').html(res.times + ' ' +lang('times'));
                    that.playerTopList(res.userlist);                    
                    break;
                default:
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                    break;
                }
            };
            loadContents({
                'c': 'springlottery',
                'm': 'action'
            },
            Func);
            Func = null;
        },

        lotteryAnimate: function(pos, lastPos, extreme, times, costgold, resultData) {
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
            yEndPos = (pos + stimes) > 12 ? (pos + stimes - 13) : (pos + stimes),
            //匀速yRoundtimes圈后的位置
            lastSomePos,
            //匀速停止时应该到的位置，也就是目标往后倒 etimes 步
            yEndTimes,
            //匀速到最后5个还得跑几步,也就是到倒数5步的步数
            thread = null; //递归的方法
            //console.log('起始的位置是 ' + pos);
            //console.log('匀速' + yRoundtimes + '圈后的位置是 ' + yEndPos);

            /*计算匀速结束规定圈数后还得跑几次才能到目标位置*/
            lastSomePos = (lastPos - etimes) <= 0 ? (13 - etimes + lastPos) : (lastPos - etimes);
            //console.log('离目标倒退 ' + etimes + '个的坐标是 ：' + lastSomePos);
            yEndTimes = (lastSomePos <= yEndPos) ? (13 - yEndPos + lastSomePos) : (lastSomePos - yEndPos);
            //console.log('匀速到最后 ' + etimes + '个还得跑---- ' + yEndTimes);

            /*结束的减速方法*/
            var end = function() {
                //console.log('减速开始时pos = ' + pos);
                that.rightDom.attr('class', 'newyearlottery_right box'+that.box+' pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    if (etimes == 0) {
                        //console.log('减速结束时pos = ' + pos);
                        that.showResultMsg(resultData.info);// 在旧数据里提示
                        //更新转盘
                        xGame.islock = true;

                        var wait = that.lastExtreme == 0 ? 300 : 2000; //区别普通与至尊的等待时间
                        that.isTimeout.push(setTimeout(function() {
                            xGame.unlock();

                            that.resData = resultData;
                            that.lotteryList = resultData.list;
                            that.pos = lastPos;// that.giftData 上一次的数据

                            that.fillRightData(times, costgold, resultData.lottery, extreme);// 更新表盘和数据
                            that.lastExtreme = extreme;

                            if(extreme == 0) {
                                if(that.box == 2) {
                                    that.rightDom.attr('class', 'newyearlottery_right box1');
                                } else {
                                    that.rightDom.removeClass('box2').addClass('box1');
                                }
                                that.rightDom.find('.newyearlottery_tops1').fadeIn(300);
                                that.box = 1;
                            } else {
                                that.rightDom.attr('class', 'newyearlottery_right box2');
                                that.rightDom.find('.newyearlottery_tops1').fadeOut(100);
                                that.box = 2;
                            }
                        }, wait));

                        yRoundtimes = stimes = ytimes = etimes = speed = yEndPos = lastSomePos = yEndTimes = yEndPos = null;
                        return;
                    }
                    etimes--;
                    pos++;
                    pos = pos > 12 ? 0 : pos;
                    speed += 100;
                    thread = arguments.callee;
                    that.rightDom.attr('class', 'newyearlottery_right box'+that.box+' pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };

            /*匀速转圈方法*/
            var yunsu = function() {
                //console.log('匀速开始时的pos = '+pos);
                that.rightDom.attr('class', 'newyearlottery_right box'+that.box+' pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    ytimes++;
                    pos++;
                    pos = pos > 12 ? 0 : pos;
                    //console.log('pos = '+ pos);
                    if (yRoundtimes <= 0) {
                        yEndTimes--;
                        if (yEndTimes == 0) {
                            end();
                            return;
                        }
                    }

                    if (ytimes > 12) {
                        ytimes = 0;
                        yRoundtimes--;
                    }
                    //console.log('ytimes = '+ ytimes);
                    that.rightDom.attr('class', 'newyearlottery_right box'+that.box+' pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };

            /*开始的加速方法*/
            var start = function() {
                //console.log('加速开始时的pos = '+pos);

                that.rightDom.attr('class', 'newyearlottery_right box'+that.box+' pos' + pos);
                that.isTimeout.push(setTimeout(function() {
                    stimes--;
                    pos++;
                    pos = pos > 12 ? 0 : pos;
                    if (stimes == 0) {
                        //console.log('加速结束时的pos = '+pos);
                        yunsu();
                        return;
                    }
                    speed -= 25;
                    that.rightDom.attr('class', 'newyearlottery_right box'+that.box+' pos' + pos);
                    that.isTimeout.push(setTimeout(arguments.callee, speed));
                }, speed));
            };            
            start();
        },

        showResultMsg: function(info) {
            var that = this;
            switch (parseInt(info.gettype)) {
            case 1:
                player.updateinfo({
                    'gold':
                    parseInt(info.all_gold)
                });
                xGame.toast.show(lang('c_you_get_gold', [info.getvalue]));
                break;
            case 2:
                player.updateinfo({
                    'silver':
                    parseInt(info.all_silver)
                });
                xGame.toast.show(lang('c_you_get_silver', [info.getvalue]));
                break;
            case 3://黄宝石
                xGame.toast.show(lang('c_you_get_gem2', [info.getvalue]));
                break;
            case 4://紫宝石
                xGame.toast.show(lang('c_you_get_gem3', [info.getvalue]));
                break;
            case 5://原魂
                xGame.toast.show(lang('c_you_get_soul', [info.getvalue]));
                break;
            case 6://玉石
                xGame.toast.show(lang('c_you_get_stone', [info.getvalue]));
                break;
            case 7://声望
                xGame.toast.show(lang('c_you_get_prestige', [info.getvalue]));
                break;
            case 8://装备
                xGame.toast.show(lang('c_you_get_item', [info.getvalue.name]));
                break;
            case 9://宝石碎片
                xGame.toast.show(lang('c_you_get_gemworld_part', [info.getvalue]));
                break;
            case 10://突飞卡
                xGame.toast.show(lang('c_you_get_tfcard', [info.getvalue]));
                break;
            case 11://坐骑
                xGame.toast.show(lang('c_you_get_mount', [info.getvalue.name]));
                break;
            case 12://蓝宝石
                xGame.toast.show(lang('c_you_get_gem1', [info.getvalue]));
                break;
            case 13://武将
                miscpop.newhero(info.getvalue);
                break;
            case 14://军令
                xGame.toast.show(lang('c_you_get_act', [info.getvalue]));
                player.updateinfo({
                    'act':
                    parseInt(info.all_act)
                });
                break;
            case 15://精炼石
                xGame.toast.show(lang('acttreasurepop_msg14', [info.getvalue]));
                break;
            case 16://五彩石
                xGame.toast.show(lang('acttreasurepop_msg15', [info.getvalue]));
                break;
            case 17://上古血玉
                xGame.toast.show(lang('acttreasurepop_msg16', [info.getvalue]));
                break;
            case 18://装备精华
                xGame.toast.show(lang('acttreasurepop_msg18', [info.getvalue.name, xGame.essence(info.getvalue.id), info.getvalue.value]));
                break;
            case 19://锻造材料
                xGame.toast.show(lang('acttreasurepop_msg18', [info.getvalue.name, xGame.forgeMaterial(info.getvalue.id), info.getvalue.num]));
                break;
            }
            xGame.unlock();
        },
        
        getReward: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {


                switch (parseInt(res.status)) {
                case 1:                
                    that.lotteryList = res.list;
                    that.showResultMsg(res.info);
                    that.leftReward(res.cd, res.is_cd, res.my_rank);
                    break;
                default:
                    xGame.toast.show(res.msg);
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'springlottery',
                'm': 'get_reward',
            },
            Func);
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.leftDom) {
                this.leftDom.undelegate('#newyearlottery_reward_btn.btn_middle', 'touchend');
                this.leftDom.undelegate('.nickname', 'click');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('#newyearlottery_start', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;

            xGame.updateCd('newyearlottery', 0);
            
            if (this.rewardScroller != null) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            
            this.box = null;
            this.giftData = null;
            this.leftDom = null;
            this.rightDom = null;
            this.resData = null;
            this.pos = null;
            this.lotteryList = null;
            this.lastExtreme = null;
            this.isEnd = null;
            this.unbindAll();
            
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;            
        },
    };
})();