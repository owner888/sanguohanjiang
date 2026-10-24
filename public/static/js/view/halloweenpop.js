/* 万圣节 */
(function() {
    halloweenPopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom;
        this.rightDom;
        this.rewardData;
        this.achieveTimer = null;

        //canvas画布大小
        this.canvasWidth;
        this.canvasHeight;
        //动画间距
        this.anmiaLeft;
        this.anmiaTop;
    };
    halloweenPopClass.prototype = {
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
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            
            loadContents({
                'c': 'act_halloween',
                'm': 'index',
            },
            initRender, true);

            initRender = null;
            
        },

        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('halloween');
            that.e.find('.content').find('#halloween_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                e.preventDefault();
            });

            //填充左侧数据
            that.leftDom = that.e.find('.halloweenpop_left');

            that.leftDom.find('.halloweenpop_candytimes').html('x' + res.candy);
            that.leftDom.find('.halloweenpop_acttime').html(res.date);

            //填充右侧数据
            that.rightDom = that.e.find('.halloweenpop_right');
            //初始南瓜状态
            for(var i = 0; i < 9; i++) {
                that.rightDom.find('#pum' + res.list[i].id).addClass(res.list[i].status == 1 ? 'crush' : 'show');
            }
            that.rightDom.find('.halloweenpop_hammertimes').html(res.hammer);
            that.rightDom.find('#ham1').find('.halloweenpop_hamgold').html('x' + res.buy_hammer_price);
            that.rightDom.find('#ham2').find('.halloweenpop_hamgold').html('x' + res.all_price);
            res.all_button_status == 0 ? that.rightDom.find('#ham2').addClass('disable') : '';

            that.canvasWidth = that.rightDom.find('#animation_pumpkin').width();
            that.canvasHeight = that.rightDom.find('#animation_pumpkin').height();
            that.anmiaLeft = that.rightDom.find('.halloweenpop_pumpkin').width();
            that.anmiaTop = that.rightDom.find('.halloweenpop_pumpkin').height();
            
            that.bindAll();

            that.e.show();
            $('#worldloading').hide();
        },

        bindAll: function(){
            var that = this;

            //玩法介绍            
            that.e.undelegate('#halloweenpop_info_hide, #halloweenpop_info_dom>.mask', 'touchend').delegate('#halloweenpop_info_hide, #halloweenpop_info_dom>.mask', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#halloweenpop_info_dom').css('display','none');
            });
            
            that.e.undelegate('#halloweenpop_info', 'touchend').delegate('#halloweenpop_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#halloweenpop_info_dom').css('display','block');
            });

            //吃糖果
            that.leftDom.undelegate('#eat_candy', 'touchend').delegate('#eat_candy', 'touchend', function() {                
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.eatCandy();
            });

            /* 奖章商城按钮 */
            that.leftDom.find('#halloweenpop_exchange_btn').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showInterlistPage();
            });

            /* 查看详情 */
            that.e.find('.carousel_content').undelegate('.box span', 'click').delegate('.box span', 'click', function (e) {                
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).parent().hasClass('type12')) {
                    var index = $('#halloweenpop_reward_dom .box').index($(this).parent()); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(that.rewardData[index].info);
                } else if ($(this).parent().hasClass('type8')) {
                    var index = $('#halloweenpop_reward_dom .box').index($(this).parent()); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(that.rewardData[index].info);
                }else if ($(this).parent().hasClass('type26')) {
                    var index = $('#halloweenpop_reward_dom .box').index($(this).parent()); // 取得坐骑在所有奖励中的索引
                    miscpop.showMarchInfo(that.rewardData[index].info);
                }else if ($(this).parent().hasClass('type31')) {
                    var index = $('#halloweenpop_reward_dom .box').index($(this).parent()); // 取得将魂在所有奖励中的索引
                    miscpop.showSoulInfo(that.rewardData[index].info);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            /*兑换按钮*/
            that.e.find('.halloweenpop_carousel').undelegate('.changeBtn.pop_btn_small', 'click').delegate('.changeBtn.pop_btn_small', 'click', function (e) {                
                if (xGame.checkLock(true)) {
                    return false;
                }
                var obj = $(this);
                var id = obj.attr("pid");
                var render = function(res) { 
                    switch(parseInt(res.status)) {
                        case 1:
                            that.showResultMsg(res.reward);
                          
                            for (var i = 0; i < that.rewardData.length; i++) {
                                if(that.rewardData[i].medal > res.medal ) {
                                     that.e.find('.halloweenpop_carousel').find('#item'+parseInt(that.rewardData[i].id)).removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                                }
                            }

                            if(that.rewardData[id - 1].type == 12){     //兑换武将之后，显示已招募。
                                obj.removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                                obj.parent().find('.isexist').addClass('show');
                            }

                            that.e.find('#halloweenpop_mesal_int').html(res.medal);
                            break;
                        default:
                            xGame.toast.show(res.msg);
                            break;
                    }
                };

                loadContents({
                    'c': 'act_halloween',
                    'm': 'action_medal',
                    'id': id
                }, render);
                render = null;
                e.preventDefault();
                e.stopPropagation();
            });

            /*砸南瓜*/
            that.rightDom.undelegate('.halloweenpop_pumpkin.show', 'touchend').delegate(".halloweenpop_pumpkin.show", 'touchend', function() {                
                if (xGame.checkLock()) {
                    return false;
                }
                if(!$(this).hasClass('crush')) {
                    var id = $(this).attr('pid');

                    that.rcushPum(id);
                }
            });

            /* 砸九次*/
            that.rightDom.undelegate('#ham2', 'touchend').delegate("#ham2", 'touchend', function() {                
                if ($(this).hasClass('disable') || xGame.checkLock()) {
                    return false;
                }
                that.rightDom.find('.halloweenpop_pumpkin').removeClass('crush').addClass('show');
                that.allPum();
            });

            /* 买锤子 */
            that.rightDom.undelegate('#ham1', 'touchend').delegate("#ham1", 'touchend', function() {                
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.buyHam();
            });
        },

        // 买锤子
        buyHam: function() {
            var that = this;
            var Func = function(res) {
                if(res.status == 1) {
                    that.rightDom.find('.halloweenpop_hammertimes').html(res.hammer);
                    player.updateinfo({
                        'gold':
                        parseInt(res.gold)
                    });
                }else {
                    xGame.toast.show(res.msg);
                }
            };
            loadContents({
                'c': 'act_halloween',
                'm': 'buy_hammer'
            },
            Func); 

            Func = null;
        },


        // 砸九次
        allPum: function() {
            var that = this;
            var Func = function(res) {
                if(res.status == 1) {
                    if (that.achieveTimer != null) {
                        clearTimeout(that.achieveTimer);
                        that.achieveTimer = null;
                    }
                    player.updateinfo({
                        'gold':
                        parseInt(res.gold)
                    });
                    //动画
                    that.rightDom.find('.halloweenpop_pumpkin').removeClass('show');
                    that.doAniamtion(0, 1, res.reward)

                    //更新数据
                    for (var i = 0; i < res.reward.length; i++) {
                        switch(parseInt(res.reward[i].type)) {
                        case 1 :     // 元宝
                            player.updateinfo({
                                'gold':
                                parseInt(player.info.gold) + parseInt(res.reward[i].value)
                            });
                            break;
                        case 2 :     // 银币
                            player.updateinfo({
                                'silver':
                                parseInt(player.info.silver) + parseInt(res.reward[i].value)
                            });
                            break;
                        case 13 :     // 军令
                            player.updateinfo({
                                'act':
                                parseInt(player.info.act) + parseInt(res.reward[i].value)
                            });
                            break;
                        case 29 :      //锤子
                            var oldnum = parseInt(that.rightDom.find('.halloweenpop_hammertimes').html());
                            that.rightDom.find('.halloweenpop_hammertimes').html(oldnum + parseInt(res.reward[i].value));
                            break;
                        }
                    };

                }else {
                    xGame.unlock();
                    xGame.toast.show(res.msg);
                }
            };
            loadContents({
                'c': 'act_halloween',
                'm': 'action_all_pumpkin'
            },
            Func); 

            Func = null;
        },

        // 奖励掉落
        achieveRewardDown: function(item) {
            
            var that = this;
            
            var rewardBox = that.e.find('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var html = '';
            var type = 0;

            html += '<div class="reward_box">';

            for (var i = 0; i < item.length; i ++) {
                type = parseInt(item[i].type);

                if (type == 8) { // 装备
                    html += '<span class="reward_down pos' + (i + 1) + ' type'+ type +'" style="color:'+ helper.getItemColor(item[i]['info'].quality) +'"><em class="pic" style="background-image:url('+ xGame.itemurl(item[i]['info'].id) +')"></em>' + item[i]['info'].name + '</span>';
                } else if (type == 26) { // 坐骑
                    html += '<span class="reward_down pos' + (i + 1) + ' type'+ type +'" style="color:'+ helper.getItemColor(item[i]['info'].quality) +'"><em class="pic" style="background-position:'+ helper.getHorsePostion('buff', item[i]['info'].id) +'"></em>' + item[i]['info'].name + '</span>';
                } else if (type == 12) { // 武将
                    html += '<span class="reward_down pos' + (i + 1) + ' type'+ type +'"><em class="pic" style="background-image:url('+ xGame.avatar(item[i]['info'].id) +')"></em>' + item[i]['info'].name + '</span>';
                } else if (type >= 17 && type <= 25) { // 精华
                    html += '<span class="reward_down pos' + (i + 1) + '" style="color:'+ helper.getItemColor(item[i]['info'].quality) +'"><em class="pic reward essence" style="background-image:url('+ xGame.essence(item[i]['info'].id) +')"></em>x' + item[i]['info'].value + '</span>';
                } else {
                    html += '<span class="reward_down pos' + (i + 1) + ' type'+ type + '"><em class="img ' + that.getType(type) + '">x' + item[i]['value'] + '</em></span>';
                }
            }

            html += '</div>';
            that.e.append(html);
            
            that.achieveTimer = setTimeout(function() {
                
                if (that.e.css('display') == 'block') {
                    that.e.find('.reward_box').remove();
                }

            }, 2500);
            
            html = null;
        },

        getType: function(type) {
            var className;
            switch(parseInt(type)) {
            case 8://装备
            case 12://武将
            case 26://坐骑
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
            case 11://蓝宝石
                className = 'add_get1';
                break;
            case 13://军令
                className = 'add_junling';
                break;
            case 14://精炼石
                className = 'add_refinestone';
                break;
            case 15://五彩石
                className = 'add_colorstone';
                break;
            case 16://上古血玉
                className = 'add_bloodstone';
                break;
            case 17://装备精华
            case 18:
            case 19:
            case 20:
            case 21:
            case 22:
            case 23:
            case 24:
            case 25:
                className = 'essence';
                break;
            case 27://玫瑰
                className = 'add_rose';
                break;
            case 28://钻石
                className = 'add_diamond';
                break;
            case 29://锤子
                className = 'add_hammer';
                break;
            case 30://奖章
                className = 'add_medal';
                break;
            case 31://将魂
                className = 'soul';
                break;
            }

            return className;
        },

        //砸南瓜
        rcushPum: function(id) {
            var that = this;
            var Func = function(res) {
                if(res.status == 1) {
                    that.rightDom.find('#pum' + id).removeClass('show');
                    // 动画
                    that.doAniamtion(id, res.reset, res.reward);

                    //砸九次状态
                    res.all_button_status == 1 ? that.rightDom.find('#ham2').removeClass('disable') : that.rightDom.find('#ham2').addClass('disable');
                    //锤子数量
                    that.rightDom.find('.halloweenpop_hammertimes').html(res.hammer);

                }else {
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'act_halloween',
                'm': 'action_pumpkin',
                'id': id
            },
            Func); 

            Func = null;
        },

        //砸南瓜动画
        doAniamtion: function(id, reset, reward) {//id 为0就是砸九次
            var that = this;

            if(id == 0) {
                var posData = [];
                for(var i = 9; i >=1; i--) {
                    var j = {'left': ((i-1) % 3) * that.anmiaLeft,'top': (Math.ceil(i / 3) - 1) * that.anmiaTop}
                    posData.push(j);
                }
            } else {
                var posData = [{'left': ((id-1) % 3) * that.anmiaLeft, 'top': (Math.ceil(id / 3) - 1) * that.anmiaTop}]
            }
            var mc = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 0, top: 0, width: that.canvasWidth, height: that.canvasHeight},
                "img": staticUrl + 'img/pop/halloweenpop_aniamtion.png',
                'imgOffset': posData,
                "width": 230,
                "height": 155,
                "scale": 1,
                "loop": false,
                "endDestroy": true,
                "callback": function() {//回调函数
                    that.rightDom.find('#animation_pumpkin').css({'z-index':'0'});

                    if(id == 0){
                        that.achieveRewardDown(reward);
                        that.rightDom.find('.halloweenpop_pumpkin').addClass('crush');
                    } else {
                        that.showResultMsg(reward);
                        that.rightDom.find('#pum' + id).addClass('crush');
                        xGame.unlock();
                    }
                    
                    if(reset) {
                        setTimeout(function() {
                            that.rightDom.find('.halloweenpop_pumpkin').removeClass('crush').addClass('show');
                            xGame.unlock();
                        }, 2500);
                    }
                    posData = null;
                },
                "anim": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [230, 0],
                    interval: 50
                },
                {
                    rect: [460, 0],
                    interval: 50
                },
                {
                    rect: [690, 0],
                    interval: 50
                },
                {
                    rect: [920, 0],
                    interval: 50
                },
                {
                    rect: [1150, 0],
                    interval: 50
                },
                {
                    rect: [0, 155],
                    interval: 50
                },
                {
                    rect: [230, 155],
                    interval: 50
                },
                {
                    rect: [460, 155],
                    interval: 50
                },
                {
                    rect: [690, 155],
                    interval: 50
                },
                {
                    rect: [920, 155],
                    interval: 50
                },
                {
                    rect: [1150, 155],
                    interval: 50
                },{
                    rect: [0, 310],
                    interval: 50
                },
                {
                    rect: [230, 310],
                    interval: 50
                },
                {
                    rect: [460, 310],
                    interval: 50
                },
                {
                    rect: [690, 310],
                    interval: 50
                },
                {
                    rect: [920, 310],
                    interval: 50
                }]
            });

            mc.appendTo(that.rightDom.find('#animation_pumpkin'));
            that.rightDom.find('#animation_pumpkin').css({'z-index':'10'});
            mc.play();
            mc = null;
        },

        /* 显示奖章商城 */
        showInterlistPage: function() {
            var that = this;
            var Func = function(res) {
                if(res.status == 1) {
                    
                    that.rewardData = res.list;
                    that.infoPageLoaded = true;

                    var infoPage = that.e.find('#halloweenpop_reward_dom');

                    infoPage.find('#halloweenpop_halloween_hidebtn').unbind('touchend').bind('touchend', function(e) {
                        if (xGame.checkLock(true)) { //不设置属性
                            return false;
                        }
                        infoPage.hide();
                    });
                    that.buildInterList(res.list,res.medal);
                    infoPage.show();
                }else {
                    xGame.toast.show(res.msg);
                }
            };
            loadContents({
                'c': 'act_halloween',
                'm': 'medal_index',
            },
            Func); 

            Func = null;
        },
        buildInterList: function (list,medal) {
            this.e.find('#halloweenpop_mesal_int').html(medal);
            var html = '<li>'; 
            var n = 0;
            var m = 0;

            for (var i = 0, len = list.length; i < len; i++) {

                var item = list[i];
                
                if (m > 0 && m % 6 == 0) {
                    html += '</li><li>';
                }
                html += '<div class="box type'+ item.type +'">';
                    var q = 1;
                    if(item.info && item.info.quality) {
                        q = item.info.quality;
                    }
                    html += '<div class="box_bg c_i_bj' + q + '"></div>';
                if (parseInt(item.type) == 12) {//武将
                    html += '<span class="img" style="background-image:url(' + xGame.avatar(item.info.image) + ')"></span>';
                } else if (parseInt(item.type) == 8) { //装备
                    html += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.id) +');"></span>';
                } else if (parseInt(item.type) >= 17 && parseInt(item.type) <= 25) {//精华
                    html += '<span class="img" style="background-image:url('+ xGame.essence(item.info.id) +');"><em class="name">'+ item.info.name +'</em></span>';
                } else if(parseInt(item.type) == 26) { //坐骑
                    html += '<span class="img" style="background-position:' + helper.getHorsePostion('small', item.info.id) + ';"></span>';

                } else if(parseInt(item.type) == 31) { //将魂
                    html += '<span class="img" style="background-image: url(static/img/pop/soul_' + item.info.image + '.png' + ');"></span>';

                } else {
                    html += '<span class="img"></span>';     
                } if(parseInt(item.type) == 8){ 
                    html += '<em class="re_value1" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.info.name + '</em>';
                } else if(parseInt(item.type) == 26 || parseInt(item.type) == 12 || parseInt(item.type) == 31){
                    html += '<em class="re_value1" style="color:'+ helper.getItemColor(item.info.quality)+'">' + item.info.name + '</em>';
                } else{
                    html += '<em class="re_value1">x' + item.value + '</em>';
                }
                html += '<em class="num">' + item.medal + '</em>';
                if(parseInt(item.type) == 12) {
                    html += '<span class="isexist' + (item.info.is_exist == 1 ? ' show' : '') + '"></span>';
                }
                if(parseInt(item.type) == 12){

                    if(item.info.is_exist == 1 || item.is_exch == 0) {

                        html += '<button class="changeBtn btn pop_btn_small_disable" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                    }else{
                        html += '<button class="changeBtn btn pop_btn_small" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                    }
                }else{
                html += '<button class="changeBtn btn ' + (item.is_exch == 1 ? 'pop_btn_small' : 'pop_btn_small_disable') + '" id="item'+item.id+'" pid="'+item.id+'">' +lang('acttreasurepop_today_exchange') + '</button>';
                }
                html += '</div>';
                m++;
            }
            html += '</li>';
            this.e.find('#change_halloween_con ul' ).html(html);
            this.recoverScroller = xGame.carousel('change_halloween_con', 649, Math.ceil(m/6));
        },

        //吃糖
        eatCandy: function() {
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.leftDom.find('.halloweenpop_candytimes').html('x' + res.candy);
                        that.showResultMsg(res.reward);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            
            loadContents({
                'c': 'act_halloween',
                'm': 'action_candy',
            },
            Func);

            Func = null;
        },

        // 显示玩家获得的奖励
        showResultMsg: function (reward) {
            
            var type = parseInt(reward.type);

            switch (type) {
                case 1:     // 元宝
                    player.updateinfo({
                        'gold':
                        parseInt(player.info.gold) + parseInt(reward.value)
                    });
                    xGame.toast.show(lang('acttreasurepop_msg1', [reward.value]));
                    break;
                case 2:     // 银币
                    player.updateinfo({
                        'silver':
                        parseInt(player.info.silver) + parseInt(reward.value)
                    });
                    xGame.toast.show(lang('acttreasurepop_msg2', [reward.value]));
                    break;
                case 3:     // 黄宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [2, reward.value, lang('yellow_jade')]));
                    break;
                case 4:     // 紫宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [3, reward.value, lang('purple_jade')]));
                    break;
                case 11:    // 蓝宝石
                    xGame.toast.show(lang('acttreasurepop_msg3', [1, reward.value, lang('blue_jade')]));
                    break;
                case 5:     // 原魂
                    xGame.toast.show(lang('acttreasurepop_msg4', [reward.value]));
                    break;
                case 6:     // 玉石
                    xGame.toast.show(lang('acttreasurepop_msg5', [reward.value]));
                    break;
                case 7:     // 声望
                    xGame.toast.show(lang('acttreasurepop_msg6', [reward.value]));
                    break;
                case 8:     // 装备
                    xGame.toast.show(lang('acttreasurepop_msg7', [helper.getItemColor(reward.info.quality), reward.info.name]));
                    break;
                case 9:     // 宝石碎片
                    xGame.toast.show(lang('acttreasurepop_msg8', [reward.value]));
                    break;
                case 10:    // 突飞卡
                    xGame.toast.show(lang('acttreasurepop_msg9', [reward.value]));
                    break;
                case 12:    // 武将
                    miscpop.newhero(reward.info);
                    break;
                case 13:     // 军令
                    xGame.toast.show(lang('c_you_get_act', [info.getvalue]));
                    player.updateinfo({
                        'act':
                        parseInt(player.info.act) + parseInt(reward.value)
                    });
                    xGame.toast.show(lang('acttreasurepop_msg12', [reward.value]));
                    break;
                case 14:     // 精炼石
                    xGame.toast.show(lang('acttreasurepop_msg14', [reward.value]));
                    break;
                case 15:     // 五彩石
                    xGame.toast.show(lang('acttreasurepop_msg15', [reward.value]));
                    break;
                case 16:     // 上古血玉
                    xGame.toast.show(lang('acttreasurepop_msg16', [reward.value]));
                    break;
                case 17:    //武器精华（白）
                case 18:    //防具精华（白）
                case 19:    //饰品精华（白）
                case 20:    //武器精华（绿）
                case 21:    //防具精华（绿）
                case 22:    //饰品精华（绿）
                case 23:    //武器精华（蓝）
                case 24:    //防具精华（蓝）
                case 25:    //饰品精华（蓝）
                    xGame.toast.show(lang('acttreasurepop_msg18', [reward.info.name, xGame.essence(reward.info.id), reward.info.value]));
                    break;

                case 26://坐骑
                    xGame.toast.show(lang('c_you_get_mount', [reward.info.name]));
                break;

                case 27://玫瑰
                    xGame.toast.show(lang('acttreasurepop_msg27', [reward.value]));
                    break;
                case 28://钻石
                    xGame.toast.show(lang('acttreasurepop_msg29', [reward.value]));
                    break;
                case 29://锤子
                    xGame.toast.show(lang('acttreasurepop_msg30', [reward.value]));
                    //更新锤子数量
                    var oldnum = parseInt(this.rightDom.find('.halloweenpop_hammertimes').html());
                    this.rightDom.find('.halloweenpop_hammertimes').html(oldnum + parseInt(reward.value));
                    break;
                case 30://奖章
                    xGame.toast.show(lang('acttreasurepop_msg31', [reward.value]));
                    break;
                case 31://将魂
                    xGame.toast.show(lang('acttreasurepop_soul', [helper.getItemColor(reward.info.quality),reward.info.name]));
                    break;
            }
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#halloween_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
 
        },

        hide: function() {

            xGame.unlock();
            this.isshow = false;
            this.leftDom = null;
            this.rightDom = null;
            this.rewardData = null;
            this.achieveTimer = null;

            //canvas画布大小
            this.canvasWidth = null;
            this.canvasHeight = null;
            //动画间距
            this.anmiaLeft = null;
            this.anmiaTop = null;
	    this.unbindAll();
        },
    };
})();