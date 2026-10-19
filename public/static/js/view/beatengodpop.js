/* 暴打名将 */
(function() {
    beatengodPopClass = function() {
        this.e;
        this.isshow = false;
        this.mainDom;//主要的dom
        this.rankDom;
        this.beatDom;
        this.boxDom;
        this.rankScroller;
        this.rewardScroller;
        this.boxRewardData;
        this.heroinfo;/* 点击的某个武将的信息 */
        this.anim;
        
        this.mc1;//木锤
        this.mc2;//金锤

        this.timeoutArr = [];
    };
    beatengodPopClass.prototype = {
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
                        beatengodpop.myToast(lang('beatengod_get_box_reward_info_fail'));
                        break;
                    case -2:
                        beatengodpop.myToast(lang('get_user_info_fail'));
                        break;
                    case -1:
                        beatengodpop.myToast(lang('activity_closed'));
                        break;
                    default:
                        beatengodpop.myToast(lang('undefined_error'));
                        break;
                }

                if (res.status != 1) {
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'beatengod',
                'm': 'index',
            },
            initRender, true);

            initRender = null;
            
        },
        
        myToast: function(text) {
            xGame.toast.show(text, 'beatengod');
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('beatengod');
            that.mainDom = that.e.find('#beatengod_main');
            that.rankDom = that.e.find('#beatengod_rank');
            that.beatDom = that.e.find('#beatengod_beat_dom');
            that.boxDom = that.e.find('#beatengod_box_dom');

            that.e.find('#beatengod_hide').unbind('touchend').bind('touchend',
            function(e) {
                beatengodpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.bindAll();

            res.own_chip = parseInt(res.own_chip);
            that.showScore(res.own_chip, true);
            
            
            if (res.cd <= 0) {
                that.mainDom.find('#beatengod_cd').addClass('end');
                that.showRankList();
            } else {
                xGame.updateCd('beatengod', res.cd);
            }
            that.beatDom.find('#beatengod_beat_1').html('<b>x' + res.item[0].price + '</b>');
            that.beatDom.find('#beatengod_beat_2').html('<b>x' + res.item[1].price + '</b>')
            
            that.boxRewardData = res.reward;
            
            var html = '';
            var num = 0;
            for(var i = res.reward.length - 1;i >= 0;i --) {
                html += '<li>';
                html += '<span class="num">' + res.reward[i]['number'] + '</span>';
                if (res.reward[i].open == 0) {//未达成
                    html += '<button class="box_btn btn pop_btn_small_disable" boxid="' + res.reward[i].id + '" datakey="' + i + '">' + lang('not_reached') + '</button>';
                    //html += '<span class="box"></span>';
                } else if (res.reward[i].open == 1) {//可领取
                    html += '<button class="box_btn btn pop_btn_small" boxid="' + res.reward[i].id + '" datakey="' + i + '">' + lang('receive') + '</button>';
                    //html += '<span class="box"></span>';
                } else if (res.reward[i].open == 2) {//已领取
                    html += '<button class="box_btn btn pop_btn_small_disable" boxid="' + res.reward[i].id + '" datakey="' + i + '">' + lang('warena_have_fetch') + '</button>';
                    //html += '<span class="box open" boxid="' + res.reward[i].id + '"></span>';
                }
                html += '<span class="box ' + (res.reward[i].open == 2 ? 'open' : '') + '" boxid="' + res.reward[i].id + '" datakey="' + i + '"></span>';
                
                if (i == res.reward.length - 1) {
                    html += '<span class="progressbar top"></span>';
                    if (res.own_chip >= res.reward[i]['number']) {
                        html += '<span class="progressbar2 top"></span>';
                        num += 1;
                    }
                } else if (i == 0) {
                    html += '<span class="progressbar bottom"></span>';
                    if (res.own_chip >= res.reward[i]['number']) {
                        html += '<span class="progressbar2 bottom"></span>';
                        num += 1;
                    }
                } else {
                    html += '<span class="progressbar"></span>';
                    if (res.own_chip >= res.reward[i]['number']) {
                        html += '<span class="progressbar2"></span>';
                        num += 1;
                    }
                }
                
                html += '</li>';
            }
            //不是没有，也不是没满
            if (num != 0 && num != res.reward.length) {
                html += '<span id="bar_light" style="bottom:' + (num * 80 - 3) + 'px"></span>';
            }
            
            that.mainDom.find('#beatengod_reward_list').html(html + '<div class="lizi"></div>');
            
            html = null;
            
            that.rewardScroller = new iScroll('beatengod_reward_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            
            //如果超过4个就显示箭头
            if (res.reward.length > 4) {
                that.animStart();
                that.rewardScroller.options.onScrollEnd = function(){
                    if (Math.abs(that.rewardScroller.y) < 10) {
                        that.mainDom.find('#beatengod_jt').css('display', 'none');
                    } else {
                        that.mainDom.find('#beatengod_jt').css('display', 'block');
                    }
                };
            }
            
            that.e.show();
            $('#worldloading').hide();
            
            that.rewardScroller.scrollTo(0, -80 * res.reward.length + 349);
            that.rewardScroller.refresh();
            that.timeoutArr.push(setTimeout(function() {
                if(that.mainDom) {
                    that.mainDom.find('.lizi').show();
                }
            }, 1000));
            
            xGame.doAnimate2(that.mainDom.find('#beatengod_btn1'), 'changcolor', 100, 7,null);
        },
        
        animStart: function() {
            var obj = this.mainDom.find('#beatengod_jt');
            if (obj.length == 0) {
                return false;
            }
            this.clearAnim();
            var t = 1;
            obj.attr('class','j1');
           
            this.anim = setInterval(function() {
                t ++;
                t = t > 5 ? 1 : t;
                obj.attr('class','j' + t);
            },150);
        },
        
        clearAnim: function() {
            if (this.anim != null) {
                clearInterval(this.anim);
            }
            this.anim = null;
        },
        
        bindAll: function(){
            var that = this;

            that.e.undelegate('#beatengod_info_hide', 'touchend').delegate('#beatengod_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#beatengod_info_dom').css('display','none');
            });
            
            that.mainDom.undelegate('#beatengod_info', 'touchend').delegate('#beatengod_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#beatengod_info_dom').css('display','block');
            });
            
            that.mainDom.undelegate('#beatengod_btn1', 'touchend').delegate('#beatengod_btn1', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showRewardConfirm();
            });
            
            that.mainDom.undelegate('#beatengod_btn2', 'touchend').delegate('#beatengod_btn2', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showRankList();
            });
            
            that.mainDom.find('#hero_dom').undelegate('.hero', 'touchend').delegate('.hero', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.heroinfo = {
                    id: $(this).attr('heroid'),
                    left: parseInt($(this).css('left')),
                    top: parseInt($(this).css('top'))
                };
                that.showBeatDom();
                if (!$(this).hasClass('selected')) {
                    that.mainDom.find('.selected').removeClass('selected');
                    $(this).addClass('selected');
                }
                that.boxDom.css('display', 'none');
            });
            
            that.mainDom.undelegate('.box', 'click').delegate('.box', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var datakey = $(this).attr('datakey');
                that.showBoxDom(datakey);
            });
            
            that.mainDom.undelegate('.box_btn.pop_btn_small', 'touchend').delegate('.box_btn.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveBoxReward($(this));
            });
            
            that.rankDom.undelegate('#beatengod_rank_hide', 'touchend').delegate('#beatengod_rank_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hideRankList();
            });
            
            that.rankDom.undelegate('.user', 'click').delegate('.user', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
            
            that.rankDom.undelegate('#beatengod_rank_rbtn.pop_btn_middle', 'touchend').delegate('#beatengod_rank_rbtn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveRankReward($(this));
            });
            
            that.beatDom.undelegate('#beatengod_beat_hide', 'touchend').delegate('#beatengod_beat_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.animStart();
                that.mainDom.find('.lizi').show();
                that.beatDom.css('display', 'none');
            });
            
            that.beatDom.undelegate('.beat_btn', 'touchend').delegate('.beat_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.beatFunc($(this).attr('tid'));
            });
            
            that.boxDom.undelegate('#beatengod_box_hide', 'touchend').delegate('#beatengod_box_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.boxDom.css('display', 'none');
            });
            
        },
        
        showScore: function(num, firstshow) {
            this.mainDom.find('#beatengod_score').html(num);
            if(firstshow != true) {//如果不是第一次更新积分，则刷新右边列表以便获得最新的可领取状态
                this.scoreTreeUpdate(num);
            }
        },
        
        receiveBoxReward: function(obj) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        
                        obj.html(lang('warena_have_fetch')).removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                        obj.parent().find('.box').addClass('open');
                        
                        that.showBoxDom(obj.attr('datakey'));
                        that.boxDom.addClass('first_receive').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                            that.boxDom.attr('class', 'had_receive');
                        });
                        
                        //设置为已打开
                        that.boxRewardData[obj.attr('datakey')].open = 2;
                        
                        player.updateinfo({
                            'gold' : res.all_gold,
                            'act' : res.all_act,
                            'silver' : res.all_silver
                        });
                        
                        break;
                    case -6:
                        beatengodpop.myToast(lang('beatengod_add_receive_history_fail'));
                        break;
                    case -5:
                        beatengodpop.myToast(lang('reward_had_receive'));
                        break;
                    case -4:
                        beatengodpop.myToast(lang('beatengod_get_box_reward_not_reach'));
                        break;
                    case -3:
                        beatengodpop.myToast(lang('beatengod_get_box_reward_info_fail'));
                        break;
                    case -2:
                        beatengodpop.myToast(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        beatengodpop.myToast(lang('activity_closed'));
                        break;
                    default:
                        beatengodpop.myToast(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'beatengod',
                'm': 'get_box_reward',
                'id': obj.attr('boxid'),
            },
            Func);

            Func = null;
        },
        
        showRewardConfirm: function() {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        xGame.confirm.show('', 
                        lang('beatengod_score_msg1') + lang('beatengod_huodong_had_pay', [res.gold]) + '<br/>' + lang('beatengod_huodong_had_score', [res.get]), 
                        1,
                        function() {
                            if (res.isget != 0) {
                                beatengodpop.receiveReward();
                            }
                        },
                        null,
                        lang('beatengod_receive_score'), null, true);
                        xGame.confirm.message_wrap.addClass('beatengod');
                        //如果不可以领取
                        if (res.isget == 0) {
                            xGame.confirm.confirm_btn.addClass('disable');
                        }
                        xGame.confirm.hide_btn.unbind('touchend').bind('touchend',
                        function() {
                            xGame.confirm.message_wrap.removeClass('beatengod');
                            xGame.confirm.confirm_btn.removeClass('disable');
                            xGame.confirm.hide();
                        });
                    
                        break;
                    case -2:
                        beatengodpop.myToast(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        beatengodpop.myToast(lang('activity_closed'));
                        break;
                    default:
                        beatengodpop.myToast(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'beatengod',
                'm': 'get_pay_chip_info'
            },
            Func);

            Func = null;
        
        },
        
        receiveReward: function() {
            var that = this;

            var Func = function(res) {
                if(res.status == 1) {
                    beatengodpop.myToast(lang('beatengod_got_score', [res.add_chip]));
                    that.showScore(res.all_chip);
                    xGame.confirm.content.find('.t2').eq(1).html(res.all_pay_chip);
                    xGame.confirm.confirm_btn.addClass('disable').html(lang('warena_have_fetch'));
                    //xGame.confirm.hide();
                } else if(res.status == -1) {
                    beatengodpop.myToast(lang('activity_closed'));
                } else if(res.status == -3) {
                    beatengodpop.myToast(lang('beatengod_no_unfetch_score'));
                } else {
                    beatengodpop.myToast(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'beatengod',
                'm': 'get_pay_chip'
            },
            Func);

            Func = null;
        },
        
        receiveRankReward: function(obj) {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    beatengodpop.myToast(lang('beatengod_got_reward_gem', [res.reward.type, res.reward.value]));
                    obj.attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch'));
                    obj = null;
                } else {
                    var error_msg = '';
                    switch(res.status) {
                        case -1: error_msg = lang('activity_closed');break;
                        case -2: error_msg = lang('activity_cannot_receive');break;
                        case -4: error_msg = lang('beatengod_fetch_reward_not_in_band');break;
                        case -5: error_msg = lang('had_receive');break;
                        default: error_msg = lang('undefined_error');break;
                    }
                    beatengodpop.myToast(error_msg);
                }
            };
            loadContents({
                'c': 'beatengod',
                'm': 'get_rank_reward'
            },
            Func);

            Func = null;
        },
        
        showRankList: function() {
            var that = this;

            var Func = function(res) {
                if(res.status == 1) {
                    var myreward = null;//我的奖励信息，通过在奖励排行里判断UID获取

                    //step 1: 组织列表
                    var html = '';
                    for(var i = 0; i < res.list.length; i ++) {
                        html += '<li ' + (res.list[i].uid == player.info.uid ? 'style="color:red;"' : '') + '>';
                        html += '<em class="e1">' + res.list[i].rank + '</em>';
                        html += '<em class="e2 user" uid="' + res.list[i].uid + '">' + res.list[i].name + '</em>';
                        html += '<em class="e3">' + res.list[i].own_chip + '</em>';
                        html += '<span class="e4 gem' + res.list[i].type + '">x' + res.list[i].value + '</span>';
                        html += '</li>';
                        if(res.list[i].uid == player.info.uid) {
                            myreward = res.list[i];
                        }
                    }
                    that.rankDom.find('#beatengod_rank_list').html(html);
                    that.rankScroller = new iScroll('beatengod_rank_scroller', {
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                    html = null;
                    that.timeoutArr.push(setTimeout(function() {
                        beatengodpop.rankScroller.refresh();
                    }, 500));
                    
                    //step 2: 组织我的奖励
                    that.showRankReward(myreward);
                    
                    //step 3: 按钮状态
                    if (res.isget == 0) {// 没有奖励
                        that.rankDom.find('#beatengod_rank_rbtn').attr('class', 'btn pop_btn_middle_disable');
                    } else if (res.isget == 1) {//可领取
                        that.rankDom.find('#beatengod_rank_rbtn').attr('class', 'btn pop_btn_middle').html(lang('receive'));
                    } else if (res.isget == 2) {// 已领取
                        that.rankDom.find('#beatengod_rank_rbtn').attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch'));
                    }
                    
                    that.rankDom.css('display', 'block');
                } else {
                    beatengodpop.myToast(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'beatengod',
                'm': 'get_rank_list'
            },
            Func);

            Func = null;
        },
        
        showRankReward: function(myreward) {
            if (myreward != undefined) {
                this.rankDom.find('#beatengod_rank_reward').html('x' + myreward.value).attr('class', 'gem' + myreward.type);
            } else {
                this.rankDom.find('#beatengod_rank_reward').html(lang('beatengod_not_reach'));
            }
        },
        
        hideRankList: function() {
            if (this.rankScroller != null) { //先干掉原本的滑动
                this.rankScroller.destroy();
                this.rankScroller = null;
            }
            this.rankDom.css('display', 'none');
        },
        
        showBeatDom: function() {
            this.beatDom.css('display', 'block');
            this.clearAnim();
            this.mainDom.find('.lizi').hide();
        },
        
        beatFunc: function(id){
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        //播完动画后处理结果信息
                        that.beatenAnim(id, function() {
                            xGame.unlock();
                            that.showScore(res.number);
                            player.updateinfo({
                                'gold' : res.all_gold,
                                'act' : res.all_act,
                                'silver' : res.all_silver
                            });

                            //组织奖励信息
                            var rewardclass = '';
                            switch(res.gettype) {
                                case '1' : rewardclass = 'silver';break;
                                case '2' : rewardclass = 'act';break;
                                case '3' : rewardclass = 'tfcard';break;
                                case '4' : rewardclass = 'gem2';break;
                                case '5' : rewardclass = 'gem3';break;
                                case '6' : rewardclass = 'jade';break;
                                case '7' : rewardclass = 'osoul';break;
                                case '8' : rewardclass = 'prestige';break;
                            }
                            beatengodpop.myToast(lang('beatengod_got_score_and_other', [res.add_chip, '<span class="reward ' + rewardclass + '"></span>', res.getvalue]));

                            //that.beatDom.css('display', 'none');
                        });
                        break;
                    case -5:
                        beatengodpop.myToast(lang('warena_reduce_gold_error'));
                        xGame.unlock();
                        break;
                    case -4:
                        beatengodpop.myToast(lang('get_reward_info_fail'));
                        xGame.unlock();
                        break;
                    case -3:
                        //beatengodpop.myToast(lang('gold_not_enough'));
                        xGame.goPay(lang('gold_not_enough'));
                        xGame.unlock();
                        break;
                    case -2:
                        beatengodpop.myToast(lang('beatengod_get_item_info_fail'));
                        xGame.unlock();
                        break;
                    case -1:
                        beatengodpop.myToast(lang('activity_closed'));
                        xGame.unlock();
                        break;
                    default:
                        beatengodpop.myToast(lang('undefined_error'));
                        xGame.unlock();
                        break;
                }
            };
             
            loadContents({
                'c': 'beatengod',
                'm': 'beaten',
                'id': id,
            },
            Func);

            Func = null;
        },
        
        showBoxDom: function(datakey) {
            
            var reward = this.boxRewardData[datakey];
            if(!reward) {
                return false;
            }
            this.boxDom.find('#beatengod_box_title').html(lang('beatengod_score_reward', [reward.number]));
            var html = '';
            for(var i = 1; i <= 3; i ++) {
                if (reward['type'+i] != 0) {
                    var rewardclass = '';//奖励类型的css class
                    switch(reward['type'+i]) {//reward['type1']
                        case '1' : rewardclass = 'silver';break;
                        case '2' : rewardclass = 'act';break;
                        case '3' : rewardclass = 'tfcard';break;
                        case '4' : rewardclass = 'gem2';break;
                        case '5' : rewardclass = 'gem3';break;
                        case '6' : rewardclass = 'jade';break;
                        case '7' : rewardclass = 'osoul';break;
                        case '8' : rewardclass = 'item';break;
                        case '9' : rewardclass = 'prestige';break;
                    }
                    if (rewardclass == 'item') {
                        html += '<p class="' + rewardclass + '" style="color:'+ helper.getItemColor(reward['quality' + i]) +';background-image:url('+ xGame.itemurl(reward['itemid' + i]) +')">' + reward['value'+i] + '</p>';
                    } else {
                        html += '<p class="' + rewardclass + '">x' + reward['value'+i] + '</p>';
                    }
                }
            }
            this.boxDom.find('#beatengod_box_info').html(html);
            this.boxDom.show();
            if (reward.open == 2) {
                this.boxDom.attr('class', 'had_receive');
            } else {
                this.boxDom.attr('class', '');
            }
            html = null;
        },

        /* 更新积分树 */
        scoreTreeUpdate: function(num) {
            var that = this;
            var index = 0;
            $('#beatengod_reward_list li').each(function() {
                var _num = parseInt($(this).find('.num').html());
                if(num >= _num) {
                    //step 1: 进度条更新
                    var newclass = '';
                    var datakey = $(this).find('.box').attr('datakey');
                    if(datakey == 0) {//第一条奖励信息
                        newclass = 'bottom';
                    } else if(datakey == that.boxRewardData.length - 1) {//最后一条奖励信息
                        newclass = 'top';
                    }
                    $(this).append('<span class="progressbar2 ' + newclass + '"></span>');
                    //step 2: 按钮状态
                    if (that.boxRewardData[datakey].open != 2) {
                        $(this).find('button').attr('class', 'box_btn btn pop_btn_small').html(lang('receive'));
                    }
                    //step 3: 更新光
                    index += 1;
                }
            });
            if (that.mainDom.find('#bar_light').length == 0) {
                that.mainDom.find('#beatengod_reward_list').append('<span id="bar_light" style="bottom:' + (index * 80 - 3) + 'px;"></span>');
            } else {
                that.mainDom.find('#bar_light').css('bottom', (index * 80 - 3) + 'px');
            }
            index = null;
        },

        beatenAnim: function(id, callback) {
            var that = this;
            //that.heroinfo 武将位置
            //id: 1木槌 2金锤
            var pos = {
                x: this.heroinfo.left - 82,
                y: this.heroinfo.top - 76
            }

            if (this['mc' + id] == null) {
            //创建动画底图
                this['mc' + id] = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/beatengodpop_beat_anim' + id + '.png'),
                "width": 320,
                "height": 300,
                "x": pos.x,
                "y": pos.y,
                "loop": false,
                    "endDestory": false,
                    "callback": function() {
                        that['mc' + id].obj.hide();
                        callback();
                    },
                "frames": [{
                    rect: [0, 0],
                    interval: 40
                },
                {
                    rect: [320, 0],
                    interval: 40
                },
                {
                    rect: [640, 0],
                    interval: 40
                },
                {
                    rect: [960, 0],
                    interval: 40
                },
                {
                    rect: [1280, 0],
                    interval: 40
                },
                {
                    rect: [1600, 0],
                    interval: 40
                },
                {
                    rect: [0, 300],
                    interval: 40
                },
                {
                    rect: [320, 300],
                    interval: 40
                },
                {
                    rect: [640, 300],
                    interval: 40
                },
                {
                    rect: [960, 300],
                    interval: 40
                },
                {
                    rect: [1280, 300],
                    interval: 40
                },
                {
                    rect: [1600, 300],
                    interval: 40
                }
                ]
            });
            //开始播放
                this['mc' + id].appendTo(this.mainDom.find('#hero_dom'));
                this['mc' + id].play();
            } else {
                this['mc' + id].callback = function() {
                    that['mc' + id].obj.hide();
                    callback();
                };
                this['mc' + id].obj.css({left: pos.x + 'px', top: pos.y + 'px'});
                this['mc' + id].replay();
            }
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#beatengod_hide').unbind('touchend');
                this.e.undelegate('#beatengod_info_hide', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.mainDom) {
                this.mainDom.undelegate('#beatengod_info', 'touchend');
                this.mainDom.undelegate('#beatengod_btn1', 'touchend');
                this.mainDom.undelegate('#beatengod_btn2', 'touchend');
                this.mainDom.find('#hero_dom').undelegate('.hero', 'touchend');
                this.mainDom.undelegate('.box', 'click');
                this.mainDom.undelegate('.box_btn.pop_btn_small', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
            if (this.rankDom) {
                this.rankDom.undelegate('#beatengod_rank_hide', 'touchend');
                this.rankDom.undelegate('.user', 'click');
                this.rankDom.undelegate('#beatengod_rank_rbtn.pop_btn_middle', 'touchend');
                this.rankDom.remove();
                this.rankDom = null;
            }
            if (this.beatDom) {
                this.beatDom.undelegate('#beatengod_beat_hide', 'touchend');
                this.beatDom.undelegate('.beat_btn', 'touchend');
                this.beatDom.remove();
                this.beatDom = null;
            }
            if (this.boxDom) {
                this.boxDom.undelegate('#beatengod_box_hide', 'touchend');
                this.boxDom.remove();
                this.boxDom = null;
            }
        },

        hide: function() {

            xGame.unlock();
            this.isshow = false;
            this.boxRewardData = null;
            this.heroinfo = null;
            this.heroinfo = null;
            
            if (CD['beatengod'] != undefined) {
                xGame.updateCd('beatengod', 0);
            }
            this.clearAnim();
            
            this.hideRankList();
            
            if (this.rewardScroller != null) { //先干掉原本的滑动
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            if(this.mc1) {
                this.mc1.destory();
                this.mc1 = null;
            }
            if(this.mc2) {
                this.mc2.destory();
                this.mc2 = null;
            }
            for (var i = 0; i < this.timeoutArr.length; i++) {
                clearTimeout(this.timeoutArr[i]);
            }
            this.timeoutArr.length = 0;
	    this.unbindAll();
        },
    };
})();