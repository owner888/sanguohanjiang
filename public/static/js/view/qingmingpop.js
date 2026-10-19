/* 清明祭祀 */
(function() {
    qingmingPopClass = function() {
        this.e;
        this.isshow = false;
        this.mainDom;
        this.rankDom;
        this.rewardDom;
        this.rankScroller;
        this.rewardScroller;
        
        this.freeTime;
        this.price;
        this.isTimeout = null;
    };
    qingmingPopClass.prototype = {
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
                    case -2:
                        xGame.toast.show(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
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
                'c': 'chingming',
                'm': 'index',
            },
            initRender, true);
            initRender = null;
            
        },
        
        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('qingming');
            that.mainDom = that.e.find('#qingming_main');
            that.rankDom = that.e.find('#qingming_rank_dom');
            that.rewardDom = that.e.find('#qingming_reward_dom');
            //祭神的时候修改dom
            if (player.info.chingming_type == 1) {
                that.e.addClass('new');
                that.e.find('#qingming_reward_b').html(lang('newqingmingpop_chengjiu'));
                that.e.find('#qingming_rank_b').html(lang('newqingmingpop_rank'));
                that.e.find('.qingming_rank_tab').find('.e3').html(lang('newqingmingpop_times'));
                that.mainDom.find('.p1').html(lang('newqingmingpop_p1'));
                that.mainDom.find('.p2').html(lang('newqingmingpop_p2'));
                that.mainDom.find('#qingming_gold_b').find('b').html(lang('newqingminpop_js_100'));
            }
            
            that.e.find('.content').find('#qingming_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                qingmingpop.hide();
                e.preventDefault();
            });
            
            that.freeTime = parseInt(res.next_times);
            that.price = parseInt(res.price);
            that.showBtn();
            //这三个不变
            that.mainDom.find('#had').html(res.times + lang('times'));
            that.mainDom.find('#left').html(res.next_times + lang('times'));
            that.mainDom.find('#qingming_gold_b').find('em').html('x' + res.hundred);
            
            if (parseInt(res.cd) > 0) {
                xGame.updateCd('qingming', res.cd);
            } else {
                that.cdEnd();
                that.showRankList();
            }
            that.bindAll();
            
            that.e.show();
            $('#worldloading').hide();
            
            xGame.doAnimate2(that.mainDom.find('#qingming_reward_b '), 'changcolor', 100, 7,null);
        },
        
        cdEnd: function() {
            this.mainDom.find('#qingming_cd').addClass('end').html('');
            if (this.mainDom.find('#qingming_free_b').hasClass('enable')) {
                this.mainDom.find('#qingming_free_b').attr('class', 'btn disable');   
            } else {
                this.mainDom.find('#qingming_free_b').attr('class', 'btn pop_btn_middle_disable');
            }
            this.rankDom.find('#qingming_rank_rbtn').attr('class', 'btn pop_btn_middle').html(lang('receive'));
        },
        
        //根据剩余次数显示按钮，祭祖100次显示盛大祭祖
        showBtn: function() {
            if (this.freeTime == 0) {
                if (player.info.chingming_type == 1) {//祭神
                    this.mainDom.find('#qingming_free_b').html('<b>' + lang('newqingminpop_big_jizu') + '<em>' + lang('qingminpop_free') + '</em></b>').attr('class', 'btn pop_btn_middle');
                } else {
                    this.mainDom.find('#qingming_free_b').html('<b>' + lang('qingminpop_big_jizu') + '<em>' + lang('qingminpop_free') + '</em></b>').attr('class', 'btn pop_btn_middle');
                }
            } else {
                if (player.info.chingming_type == 1) {//祭神
                    this.mainDom.find('#qingming_free_b').html('<b>' + lang('newqingminpop_juzu') + '<em>x' + this.price + '</em></b>').attr('class', 'btn enable');
                } else {
                    this.mainDom.find('#qingming_free_b').html('<b>' + lang('qingminpop_juzu') + '<em>x' + this.price + '</em></b>').attr('class', 'btn enable');
                }
            }
            
            if (this.freeTime == 100) {
                this.mainDom.find('#qingming_gold_b').attr('class', 'btn enable');
            } else {
                this.mainDom.find('#qingming_gold_b').attr('class', 'btn disable');
            }
        },
        
        bindAll: function(){
            var that = this;

            that.e.undelegate('#qingming_info_hide', 'touchend').delegate('#qingming_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#qingming_info_dom').css('display','none');
            });
            
            that.mainDom.undelegate('#qingming_info_b', 'touchend').delegate('#qingming_info_b', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.mainDom.find('.reward_box').remove();
                that.e.find('#qingming_info_dom').css('display','block');
            });
            
            that.mainDom.undelegate('#qingming_reward_b', 'touchend').delegate('#qingming_reward_b', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.mainDom.find('.reward_box').remove();
                that.showReward();
            });
            
            that.mainDom.undelegate('#qingming_rank_b', 'touchend').delegate('#qingming_rank_b', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.mainDom.find('.reward_box').remove();
                that.showRankList();
            });
            
            that.mainDom.undelegate('#qingming_gold_b.enable', 'touchend').delegate('#qingming_gold_b.enable', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.mainDom.find('.reward_box').remove();
                that.actionFunc(1);
            });
            
            that.mainDom.undelegate('#qingming_free_b', 'touchend').delegate('#qingming_free_b', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('disable') || $(this).hasClass('pop_btn_middle_disable')) {
                    return false;
                }
                that.mainDom.find('.reward_box').remove();
                that.actionFunc(0);
            });
            
            that.rewardDom.undelegate('#qingming_reward_hide', 'touchend').delegate('#qingming_reward_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hideReward();
            });
            
            that.rewardDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveReward($(this));
            });
            
            that.rankDom.undelegate('#qingming_rank_hide', 'touchend').delegate('#qingming_rank_hide', 'touchend',
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
            
            that.rankDom.undelegate('#qingming_rank_rbtn.pop_btn_middle', 'touchend').delegate('#qingming_rank_rbtn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveRankReward($(this));
            });
            
            
            
        },
        
        showReward: function() {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        var html = '';
                        var rewardclass = '';//奖励类型的css class
                        for (var i = 0;i < res.list.length;i ++) {
                            html += '<li>';
                            if (player.info.chingming_type == 1) {
                                html += '<p class="t">' + lang('newqingminpop_jizu%times', [res.list[i].times]) + '</p>';
                            } else {
                                html += '<p class="t">' + lang('qingminpop_jizu%times', [res.list[i].times]) + '</p>';
                            }
                            var color = '';
                            for(var j = 1;j < 4;j ++) {
                                if (res.list[i]['type' + j] != 0) {
                                    switch(res.list[i]['type' + j]) {//reward['type1']
                                        case '1' : rewardclass = 'gem2';break;
                                        case '2' : rewardclass = 'gem3';break;
                                        case '3' : rewardclass = 'jade';break;
                                        case '4' : rewardclass = 'osoul';break;
                                        case '5' : rewardclass = 'tfcard';break;
                                        case '6' : rewardclass = 'silver';break;
                                        case '7' : rewardclass = 'act';break;
                                        case '8' : rewardclass = 'item';break;
                                    }
                                    if (res.list[i]['type' + j] == 8) {
                                        color = res.list[i]['value' + j + '_quality'] == undefined ? 'red' : helper.getItemColor(res.list[i]['value' + j + '_quality']);
                                        html += '<em class="e' + j + ' ' + rewardclass + '" style="color: ' +color + ';">' + res.list[i]['value' + j] + '</em>'
                                    } else {
                                        html += '<em class="e' + j + ' ' + rewardclass + '">x' + res.list[i]['value' + j] + '</em>'
                                    }
                                }
                            }
                            if (res.list[i].open == 0) {
                                html += '<button class="btn pop_btn_middle_disable" qid="' + res.list[i].id + '">' + lang('not_reached') + '</button>';
                            } else if (res.list[i].open == 1) {
                                html += '<button class="btn pop_btn_middle" qid="' + res.list[i].id + '">' + lang('receive') + '</button>';
                            } else if (res.list[i].open == 2) {
                                html += '<span class="flag had_receive"></span>';
                            }
                            html += '</li>';
                        }
                        that.rewardDom.find('ul').html(html);
                        html = null;
                        rewardclass = null;
                        
                        that.rewardDom.css('display', 'block');
                        
                        that.rewardScroller = new iScroll('qingming_reward_scroller', {
                            bounce: false,
                            momentum: false,
                            hScrollbar: false,
                            vScrollbar: false
                        });
                        
                        that.rewardScroller.refresh();
                        
                        break;
                    case -3:
                        xGame.toast.show(lang('get_reward_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'chingming',
                'm': 'get_achievement_list'
            },
            Func);
            Func = null;
        
        },
        
        receiveReward: function(obj) {
            var that = this;

            var Func = function(res) {
                if(res.status == 1) {
                    player.updateinfo({
                        'gold' : res.all_gold,
                        'act' : res.all_act,
                        'silver' : res.all_silver
                    });
                    
                    //组织奖励信息
                    var html = '';
                    var rewardclass = '';
                    for (var i = 1;i < 4;i ++) {
                        if (res.reward['type' + i] != 0) {
                            if (res.reward['type' + i] != 8) {
                                switch(res.reward['type' + i]) {
                                    case '1' : rewardclass = 'gem2';break;
                                    case '2' : rewardclass = 'gem3';break;
                                    case '3' : rewardclass = 'jade';break;
                                    case '4' : rewardclass = 'osoul';break;
                                    case '5' : rewardclass = 'tfcard';break;
                                    case '6' : rewardclass = 'silver';break;
                                    case '7' : rewardclass = 'act';break;
                                }
                                html += '<span class="reward ' + rewardclass + '">x' + res.reward['value' + i] + '</span>';
                            } else {
                                html += '<span class="reward">' + res.reward['value' + i].itemname + '</span>';
                            }
                        }
                    }
                    xGame.toast.show(lang('get') + html);
                    
                    $('<span class="flag first_receive"></span>').appendTo(obj.parent()).one('webkitAnimationEnd', function() {
                        $(this).attr('class', 'flag had_receive');
                    });
                    obj.remove();
                    
                } else if(res.status == -1) {
                    xGame.toast.show(lang('activity_closed'));
                } else if(res.status == -2) {
                    xGame.toast.show(lang('get_activity_info_fail'));
                } else if(res.status == -3) {
                    xGame.toast.show(lang('get_chengjiu_info_fail'));
                } else if(res.status == -4) {
                    xGame.toast.show(lang('beatengod_get_box_reward_not_reach'));
                } else if(res.status == -5) {
                    xGame.toast.show(lang('get_user_info_fail'));
                } else if(res.status == -6) {
                    xGame.toast.show(lang('yuandan_error'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'chingming',
                'm': 'get_achievement_reward',
                'id': obj.attr('qid')
            },
            Func);
            Func = null;
        },
        
        receiveRankReward: function(obj) {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    var cn = '';
                    if (res.reward.type == 1) {
                        cn = 'gem3';
                    } else if (res.reward.type == 2) {
                        cn = 'gem2';
                    } else if (res.reward.type == 5) {
                        cn = 'osoul';
                    }
                    xGame.toast.show(lang('qingminpop_got_reward_wuzi', [cn, res.reward.value]));
                    obj.attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch'));
                    obj = null;
                } else {
                    var error_msg = '';
                    switch(res.status) {
                        case -1: error_msg = lang('activity_closed');break;
                        case -2: error_msg = lang('activity_cannot_receive');break;
                        case -3: error_msg = lang('get_activity_info_fail');break;
                        case -4: error_msg = lang('beatengod_not_reach');break;
                        case -5: error_msg = lang('reward_had_receive');break;
                        case -6: error_msg = lang('get_reward_info_fail');break;
                        case -7: error_msg = lang('reward_get_fail');break;
                        default: error_msg = lang('undefined_error');break;
                    }
                    xGame.toast.show(error_msg);
                }
            };
            loadContents({
                'c': 'chingming',
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
                        html += '<em class="e2 user" uid="' + res.list[i].uid + '">' + res.list[i].nickname + '</em>';
                        html += '<em class="e3">' + res.list[i].times + '</em>';
                        if (res.list[i].type == 1) {
                            html += '<span class="e4 gem3">x' + res.list[i].value + '</span>';
                        } else if (res.list[i].type == 2) {
                            html += '<span class="e4 gem2">x' + res.list[i].value + '</span>';
                        } else if (res.list[i].type == 5) {
                            html += '<span class="e4 osoul">x' + res.list[i].value + '</span>';
                        }
                        
                        html += '</li>';
                        if(res.list[i].uid == player.info.uid) {
                            myreward = res.list[i];
                        }
                    }
                    that.rankDom.find('ul').html(html);
                    that.rankScroller = new iScroll('qingming_rank_scroller', {
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                    html = null;
                    
                    that.rankDom.css('display', 'block');
                    that.rankScroller.refresh();
                    
                    //step 2: 组织我的奖励
                    that.showRankReward(myreward);
                    
                    //step 3: 按钮状态
                    if (res.isget == 0) {// 没有奖励
                        that.rankDom.find('#qingming_rank_rbtn').attr('class', 'btn pop_btn_middle_disable');
                    } else if (res.isget == 1) {//可领取
                        that.rankDom.find('#qingming_rank_rbtn').attr('class', 'btn pop_btn_middle').html(lang('receive'));
                    } else if (res.isget == 2) {// 已领取
                        that.rankDom.find('#qingming_rank_rbtn').attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch'));
                    }
                    
                    
                } else if (res.status == -1){
                    xGame.toast.show(lang('activity_closed'));
                } else {
                    xGame.toast.show('undefined_error');
                }
            };
            loadContents({
                'c': 'chingming',
                'm': 'get_rank_list'
            },
            Func);
            Func = null;
        },
        
        showRankReward: function(myreward) {
            var html = '';
            if (myreward != undefined) {
                if (myreward.type == 1) {
                    html += '<em class="gem3">x' + myreward.value + '</em>';
                } else if (myreward.type == 2) {
                    html += '<em class="gem2">x' + myreward.value + '</em>';
                } else if (myreward.type == 5) {
                    html += '<em class="osoul">x' + myreward.value + '</em>';
                }
                this.rankDom.find('#qingming_rank_reward').html(lang('qingminpop_current_rank') + '<em>' + myreward.rank + '</em><br/>' + lang('qingminpop_current_reward') + html).attr('class', 'reach');
            } else {
                if(parseInt(player.info.vip) < 2) {
                    this.rankDom.find('#qingming_rank_reward').addClass('vip2_open').html(lang('vip2_open'));
                } else {
                    this.rankDom.find('#qingming_rank_reward').html(lang('beatengod_not_reach'));
                }
            }
        },
        
        hideRankList: function() {
            if (this.rankScroller != null) { //先干掉原本的滑动
                this.rankScroller.destroy();
                this.rankScroller = null;
            }
            this.rankDom.css('display', 'none');
        },
        
        hideReward: function() {
            if (this.rewardScroller != null) { //先干掉原本的滑动
                this.rewardScroller.destroy();
                this.rankrewardScrollerScroller = null;
            }
            this.rewardDom.css('display', 'none');
        },
        
        //祭祖方法，unlock特殊处理
        actionFunc: function(type){
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                if (res.status == 1) {
                    player.updateinfo({
                        'gold' : res.all_gold,
                        'act' : res.all_act,
                        'silver' : res.all_silver
                    });
                    
                    that.freeTime = parseInt(res.next_times);
                    that.showBtn();
                    //这三个不变
                    that.mainDom.find('#had').html(res.times + lang('times'));
                    that.mainDom.find('#left').html(res.next_times + lang('times'));

                    if(type == 1) {//解决祭祖百次的bug
                        player.taskaction(5, true);
                    }

                    that.rewardDown(res, res.free);
                    
                    html = null;
                    rewardclass = null;
                } else {
                    xGame.unlock();
                    switch (parseInt(res.status)) {
                        case -7:
                            if (player.info.chingming_type == 1) {//祭神
                                xGame.toast.show(lang('newqingminpop_jizu_fail'));
                            } else {
                                xGame.toast.show(lang('qingminpop_jizu_fail'));
                            }
                            break;
                        case -6:
                            xGame.toast.show(lang('get_reward_info_fail'));
                            break;
                        case -5:
                            xGame.goPay(lang('gold_not_enough'));
                            break;
                        case -4:
                            if (player.info.chingming_type == 1) {//祭神
                                xGame.toast.show(lang('newqingminpop_can_not_100'));
                            } else {
                                xGame.toast.show(lang('qingminpop_can_not_100'));
                            }
                            break;
                        case -3:
                            xGame.toast.show(lang('get_activity_info_fail'));
                            break;
                        case -2:
                            xGame.toast.show(lang('activity_end'));
                            break;
                        case -1:
                            xGame.toast.show(lang('activity_closed'));
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                }
            };
             
            loadContents({
                'c': 'chingming',
                'm': 'action',
                'type': type,
            },
            Func);
            Func = null;
        },
        
        rewardDown: function(res, isFree) {
            var that = this;
            
            var html = '';//一个时专用
            var arr = {};//多个时专用
            var rewardclass = '';
            //组织奖励信息
            for (var i = 0;i < res.reward.length;i ++) {
                switch(res.reward[i].type) {
                    case '1' : rewardclass = 'gem2';break;
                    case '2' : rewardclass = 'gem3';break;
                    case '3' : rewardclass = 'jade';break;
                    case '4' : rewardclass = 'osoul';break;
                    case '5' : rewardclass = 'tfcard';break;
                    case '6' : rewardclass = 'silver';break;
                    case '7' : rewardclass = 'act';break;
                }
                html += '<span class="reward ' + rewardclass + '">x' + res.reward[i].value + '</span>';
                
                arr[res.reward[i].type] = '<span class="reward_down pos' + res.reward[i].type + ' ' + rewardclass + '">x' + res.reward[i].value + '</span>';
            }
            if (res.reward.length == 1) {
                if (isFree == 1) {
                    xGame.toast.show(lang('get') + html + '<b class="ten"></b>');
                } else {
                    xGame.toast.show(lang('get') + html);
                }
                xGame.unlock();
                html = null;
                arr = null;
                rewardclass = null;
            } else {
                html = null;
                rewardclass = null;
                var obj = $('<div class="reward_box"></div>');
                var auto = function(i) {
                    if (i == 8) {
                        arr = null;
                        xGame.unlock();
                        return false;
                    }
                    if (arr[i] != undefined) {
                        obj.append(arr[i]);
                    }
                    this.isTimeout = setTimeout(function() {
                        auto(i + 1);
                    },100);
                };
                auto(1);
                obj.appendTo(that.mainDom);
            }
            
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('#qingming_hide').unbind('touchend');
                this.e.undelegate('#qingming_info_hide', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.mainDom) {
                this.mainDom.undelegate('#qingming_info_b', 'touchend');
                this.mainDom.undelegate('#qingming_reward_b', 'touchend');
                this.mainDom.undelegate('#qingming_rank_b', 'touchend');
                this.mainDom.undelegate('#qingming_gold_b.enable', 'touchend');
                this.mainDom.undelegate('#qingming_free_b', 'touchend');
                this.mainDom.remove();
                this.mainDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.undelegate('#qingming_reward_hide', 'touchend');
                this.rewardDom.undelegate('.pop_btn_middle', 'touchend');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.rankDom) {
                this.rankDom.undelegate('#qingming_rank_hide', 'touchend');
                this.rankDom.undelegate('.user', 'click');
                this.rankDom.undelegate('#qingming_rank_rbtn.pop_btn_middle', 'touchend');
                this.rankDom.remove();
                this.rankDom = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;
            
            if (CD['qingming'] != undefined) {
                xGame.updateCd('qingming', 0);
            }
            
            this.hideRankList();
            this.hideReward();
            
            this.unbindAll();
           
            this.freeTime = null;
            this.price = null;
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            
        }
    };
})();