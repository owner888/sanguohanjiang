/* 年兽 */
(function() {
    nianWorldClass = function() {
        this.e;
        this.indexdata = null;
        this.oldworld = 'city';
        this.rank_now = 0;
        this.rankScroller = null;
        this.sideScroller = null;
        this.hasRankInfo = false;
        this.isTimeout = [];
    };
    nianWorldClass.prototype = {
        init: function(oldworld) {
            var that = this;

            if (xGame.lastWorld == 'battle' || xGame.lastWorld == 'nian') {
                that.oldworld = 'city';
            } else {
                that.oldworld = xGame.lastWorld;
            }

            //开始构建页面数据
            var initRender = function(res) {
                res.status = parseInt(res.status);
                that.indexdata = res;
                switch (res.status) {
                    /* 显示战斗准备页面 */
                    case 1:
                        that.showPrepare();
                         break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        that.showPrepare(true);
                        break;
                    case -2:
                        that.showPrepare(true);
                        that.rank(1, function() {
                            that.e.find('#rank_tab1').removeClass('down');
                            that.e.find('#nian_rank').show();
                            that.e.find('#nian_mask').show();
                        });
                        break;
                    case -3:
                        that.showPrepare(true);
                        that.achieve(4);
                        break;
                }
            };
             
            loadContents({
                'c': 'newyearboss',
                'm': 'index'
            },
            initRender);
            initRender = null;

        },

        /* 准备界面 */
        showPrepare: function(isPreparing) {
            var that = this;
            
            if (that.e == null) {
                var dom_world = $('#world');
                dom_world.append($.template('nian_tmpl'));

                that.e = dom_world.find('#nian');
                that.e.children('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    xGame.loadWorld(that.oldworld);
                    e.preventDefault();
                });
                dom_world = null;
            }
            
            if (isPreparing) {
                //没数据的时候
                that.e.find('#c_gold_up').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');       // 元宝鼓舞
                that.e.find('#c_silver_up').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');     // 银币鼓舞
                that.e.find('#action').removeClass('pop_btn_big').addClass('pop_btn_big_disable');        // 开战
                that.e.find('#nian_rank').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');       // 年兽排行
                
                that.e.find('.gold').html('0');     // 元宝鼓舞花费元宝
                that.e.find('.silver').html('0');   // 银币鼓舞花费银币
                that.e.find('.promote').children('em').html('100%');    // 当前战斗力
                that.e.find('#action').html(lang('not_open'));
                xGame.updateCd('nian', 0);
                xGame.updateCd('nianButton', 0);
            } else {
                //有数据的时候
                that.e.find('#c_gold_up').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                that.e.find('#c_silver_up').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                that.e.find('#action').removeClass('pop_btn_big_disable').addClass('pop_btn_big');
                that.e.find('#nian_rank').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');

                that.e.find('.gold').html(that.indexdata.upcost.gold);
                that.e.find('.silver').html(that.indexdata.upcost.silver);
                that.e.find('.promote').children('em').html(that.indexdata.powerup + '%');
                
                xGame.updateCd('nian', that.indexdata.countdown);
                xGame.updateCd('nianButton', that.indexdata.cd);
                
            }
            
            //生成伤害榜
            that.e.find('#nowrank').html(that.indexdata.myrank == undefined ? 0 : that.indexdata.myrank);
            that.e.find('#nowhit').html(that.getWan(that.indexdata.myhit == undefined ? 0 : that.indexdata.myhit));
            that.e.find('#gold_support').html(that.indexdata.mypowerup == undefined ? 0 : that.indexdata.mypowerup);
            that.e.find('#dodge_num').html(that.indexdata.mydodge == undefined ? 0 : that.indexdata.mydodge);
            
            var html = '';

            for (var i = 0; i < 5; i++) {
                if (that.indexdata.top[i]) {
                    html += '<li><p class="t user' + (that.indexdata.top[i].uid == player.info.uid ? ' myrank' : '') + '" uid="' + that.indexdata.top[i].uid + '">' + that.indexdata.top[i].nickname + '</p><p class="p">' + that.getWan(that.indexdata.top[i].hit) + '</p></li>';
                } else {
                    html += '<li><p class="t">' + lang('no_word') + '</p><p class="p">0</p></li>';
                }
            }
            that.e.find('#hitlist').html(html);
            that.bindAll();
            $('#worldloading').removeClass('black').hide();

        },
        
        bindAll: function() {
            var that = this;
            
            that.e.undelegate('#cboss_rule_btn', 'touchend').delegate('#cboss_rule_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#nian_mask').css('display','block');
                that.e.find('#nian_rule').css('display','block');
            });
            
            that.e.undelegate('#cboss_rule_hide', 'touchend').delegate('#cboss_rule_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#nian_mask').css('display','none');
                that.e.find('#nian_rule').css('display','none');
            });
            
            that.e.undelegate('.inspire.pop_btn_middle', 'touchend').delegate('.inspire.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var type = this.id;
                that.attackUp(type);
            });
            
            that.e.find('.pop_btn_big').unbind('touchend').bind('touchend',
            function() {
                that.goBattleCheck();
            });

            // 讨伐成就
            that.e.find('#nian_achievement_btn').unbind('touchend').bind('touchend',
            function () {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.achieve(4);
                that.e.find('#nian_ach_rank').removeClass('down').siblings('.pop_btn_small').addClass('down');
            });

            // 讨伐成就的关闭按钮
            that.e.find('#nian_achievement').undelegate('.hidebtn', 'touchend').delegate('.hidebtn', 'touchend',
            function () {
                $(this).parent().css('display', 'none');
                that.e.find('#nian_mask').hide();
                if (that.sideScroller) {
                    that.sideScroller.destroy();
                    that.sideScroller = null;
                }
            });

            // 讨伐成就的伤害排名、伤害输出、金币鼓舞、闪避次数按钮
            that.e.find('#nian_achievement').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend', 
            function () {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                $(this).removeClass('down').siblings('.pop_btn_small').addClass('down');
                var btn_index = that.e.find('#nian_achievement').find('.pop_btn_small').index(this);
                that.achieve(btn_index + 1);
            });

            // 年兽排行
            that.e.find('#nian_rank_btn').unbind('touchend').bind('touchend',
            function () {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.rank(1, function() {
                    that.e.find('#nian_rank').show();
                    that.e.find('#nian_mask').show();
                });
            });
            // 年兽排行的伤害输出、金币鼓舞、闪避次数按钮
            that.e.undelegate('.rank_tab', 'touchend').delegate('.rank_tab', 'touchend', 
            function () {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var btn_index = $(this).data('tab');
                that.rank(btn_index);
            });
            // 年兽排行里查看资料
            that.e.find('#nian_rank').undelegate('.nian_rank_nickname', 'click').delegate('.nian_rank_nickname', 'click',
            function(e) {
                e.preventDefault();
                e.stopPropagation();
                xGame.showpop('info', parseInt($(this).data('uid')));
            });

            that.e.find('#nian_rank').undelegate('#nian_rank_fetch.pop_btn_middle', 'touchend').delegate('#nian_rank_fetch.pop_btn_middle', 'touchend',
            function(e) {
                e.preventDefault();
                e.stopPropagation();
                that.getRankReward(that.rank_now, $(this));
            });
            // 年兽排行的关闭按钮
            that.e.undelegate('#nian_rank_hide_btn', 'touchend').delegate('#nian_rank_hide_btn', 'touchend', function(e) {
                //e.preventDefault();
                //e.stopPropagation();
                that.e.find('#rank_tab'+that.rank_now).addClass('down');
                that.e.find('#nian_rank').hide();
                that.e.find('#nian_mask').hide();
                that.e.find('#nian_rank_list').empty();
                that.rankScroller.destroy();
                that.rankScroller = null;
                that.rank_now = 0;
                return false;
            });

            //年兽排行里，查看奖励列表
            that.e.undelegate('#nian_rank_reward', 'touchend').delegate('#nian_rank_reward', 'touchend',
            function (e) {
                that.e.find('#nian_rank_reward_info').show();
                
                if (!that.hasRankInfo) {
                    that.getRankList();
                }

                return false;
            });
            //年兽排行里，奖励列表 关闭
            that.e.undelegate('#nian_rank_rewardlist_hide_btn', 'touchend').delegate('#nian_rank_rewardlist_hide_btn', 'touchend',
            function (e) {
                that.e.find('#nian_rank_reward_info').hide();
                return false;
            });
            
            // 年兽排行里查看资料
            that.e.find('#hitlist').undelegate('.user', 'touchend').delegate('.user', 'touchend',
            function(e) {
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });
        },

        // 创建讨伐成就模板
        achieve: function (type) {
            
            var that = this;

            function initRender(res) {
                
                switch (res.status) {
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case 1:
                        createTemplate(res);
                        break;
                }
            }

            function createTemplate(res) {
                var html = '';
                var gettype = 0;
                var getvalue = {};

                if (that.sideScroller == null) {
                    if (res.list.length > 3) {
                        that.sideScroller = new iScroll('nian_ach_scroller', {
                            snap : 'li',
                            bounce: false,
                            momentum: false,
                            hScrollbar: false,
                            vScrollbar: false
                        });
                    }
                }

                for (var i = 0, len = res.list.length; i < len; i++) {
                    gettype = res.list[i].gettype;
                    getvalue = res.list[i].getvalue;
                    html += '<li id="'+ res.list[i].id +'"><p class="condition">' + res.list[i].name + '</p><p class="reward"><span class="type' + gettype + '">';
                    if (gettype == 13) {    // 武将
                        html += '<img src="' + xGame.avatar(getvalue.image) + '"/><em>'+ getvalue.name +'</em>';
                    } else if (gettype == 11) {     // 坐骑
                        html += '<em class="horse_pic" style="background-position:' + helper.getHorsePostion('buff', getvalue.id) + ';"></em><em class="horse_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else if (gettype == 8) {      // 装备
                        html += '<em class="equip_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else {
                        html += 'x' + getvalue;
                    }
                    html += '</span></p>';

                    if (res.list[i].status == 0) {
                        html += '<button class="btn pop_btn_middle_disable">' + lang('not_reached') + '</button>';
                    } else if (res.list[i].status == 1) {
                        html += '<button class="btn pop_btn_middle">' + lang('receive') + '</button>';
                    } else if (res.list[i].status == 2) {
                        html += '<button class="btn pop_btn_middle_disable">' + lang('warena_have_fetch') + '</button>';
                    }

                    html += '</li>';
                }

                that.e.find('#nian_achievement').find('.scroller_content').html(html);
                that.e.find('#nian_achievement').css('display', 'block');
                that.e.find('#nian_mask').show();


                // 讨伐成就的领取奖励按钮
                that.e.find('#nian_ach_scroller').find('.pop_btn_middle').unbind('click').bind('click',
                function () {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }

                    var id = $(this).parent().attr('id');
                    var $btn = $(this);
                    that.getAchieveReward(id, $btn);
                });
                if (that.sideScroller) {
                    that.sideScroller.scrollTo(0, 0);
                    that.isTimeout.push(setTimeout(function() {
                        that.sideScroller.refresh();
                    }, 100));
                }
            }

            loadContents({
                'c': 'newyearboss',
                'm': 'get_achievement_list',
                'type': type
            },
            initRender);
            initRender = null;
        },

        getAchieveReward: function (id, btn) {
            var that = this;

            function initRender(res) {
                switch (res.status) {
                    case 1:
                        getReward(res);
                        break;
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_chengjiu_info_fail'));
                        break;
                    case -3: 
                        xGame.toast.show(lang('get_chengjiu_info_fail'));
                        break;
                    case -4: 
                        xGame.toast.show(lang('nian_achievement_got'));
                        break;
                    case -5: 
                        xGame.toast.show(lang('nian_achievement_get_fail'));
                        break;
                    case -6: 
                        xGame.toast.show(lang('nian_achievement_wait'));
                        break;
                }
            }

            function getReward(res) {

                var gettype = res.info.gettype;
                var getvalue = res.info.getvalue;

                switch (parseInt(gettype)) {
                    case 1:
                        xGame.toast.show(lang('receive_get_gold', [getvalue]));
                        player.updateinfo({'gold': parseInt(res.all_gold)});
                        break;
                    case 2:
                        xGame.toast.show(lang('you_get_silver'));
                        player.updateinfo({'silver': parseInt(res.all_silver)});
                        break;
                    case 3:
                        xGame.toast.show(lang('you_get_gem', [getvalue, 2]));
                        break;
                    case 4:
                        xGame.toast.show(lang('you_get_gem', [getvalue, 3]));
                        break;
                    case 5:
                        xGame.toast.show(lang('get_original_soul', [getvalue]));
                        break;
                    case 6:
                        xGame.toast.show(lang('you_get_jade', [getvalue]));
                        break;
                    case 7:
                        xGame.toast.show(lang('smashpop_you_get', [getvalue]));
                        break;
                    case 8:
                        xGame.toast.show(lang('c_you_get_item', [getvalue.name]));
                        break;
                    case 9:
                        xGame.toast.show(lang('sanctumworld_receive_gemp', [getvalue]));
                        break;
                    case 10:
                        xGame.toast.show(lang('c_you_get_tfcard', [getvalue]));
                        break;
                    case 11:
                        xGame.toast.show(lang('nian_achievement_get_mount', [helper.getItemColor(getvalue.quality), getvalue.name]));
                        break;
                    case 12:
                        xGame.toast.show(lang('you_get_gem', [getvalue, 1]));
                        break;
                    case 13:
                        xGame.toast.show(lang('c_you_get_silver', [getvalue]));
                        miscpop.newhero(getvalue);
                        break;
                }
                btn.attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch'));
            }

            loadContents({
                'c': 'newyearboss',
                'm': 'get_achievement_reward',
                'id': id
            },
            initRender);
            initRender = null;
        },

        rank: function (type, next) {
            var that = this;

            if(type == that.rank_now) {
                return false;
            }
            if(that.rank_now != 0) {// old
                that.e.find('#rank_tab'+that.rank_now).addClass('down');
            }
            that.rank_now = type;
            that.e.find('#rank_tab'+that.rank_now).removeClass('down');

            if(that.rankScroller == null) {
                that.rankScroller = new iScroll('nian_rank_scroller', {
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: true
                });
            }

            function initRender(res) {
                var html = '',
                    rewardhtml = '',
                    gettype = 0,
                    getvalue = {},
                    fetchbtn = that.e.find('#nian_rank_fetch'),
                    rewardcls = '',
                    rewardquality = 1,
                    rewardimg = '',
                    rewardname = '',
                    $reward = that.e.find('#nian_rank_rewardbox'),
                    $nianRank = that.e.find('#nian_rank'),
                    $item = $nianRank.find('#nian_rank_title').children('.item');

                //排名类型
                $item.html(lang('nian_rank_tab'+type));

                //排名列表
                for (var i = 0, len = res.list.length; i < len; i++) {
                    var atk = type == 1 ? that.getWan(res.list[i].hit) : (type == 2 ? res.list[i].powerup + lang('times') : res.list[i].dodge  + lang('times'));

                    if (player.info.uid == res.list[i].uid) {
                        html += '<li class="nian_rank_list_li myrank">';
                    } else {
                        html += '<li class="nian_rank_list_li">';
                    }
                    html += '<span class="rank">' + res.list[i].rank + '</span><span class="nian_rank_nickname" data-uid="' + res.list[i].uid + '">' + res.list[i].nickname + '</span>';
                    html +=     '<span class="atk">' + atk + '</span></li>';
                }
                $nianRank.find('#nian_rank_list').html(html);
                
                that.rankScroller.scrollTo(0, 0);
                that.isTimeout.push(setTimeout(function() {
                    that.rankScroller.refresh();
                }, 100));

                xGame.updateCd('nianRankButton', 0);
                
                //排名奖励领取状态
                if(res.isget == 1) {//可领取
                    fetchbtn.attr('class', 'btn pop_btn_middle').html(lang('receive')).show();
                } else if(res.isget == 2) {//已领取
                    fetchbtn.attr('class', 'btn pop_btn_middle_disable').html(lang('warena_have_fetch')).show();
                } else {//不可领取
                    fetchbtn.attr('class', 'btn pop_btn_middle_disable cd').show();
                    if (parseInt(res.cd) != 0 && parseInt(res.reward.type) != 0) {
                        xGame.updateCd('nianRankButton', res.cd);
                    }
                }

                if ((parseInt(res.myrank)) != 0) {
                    that.e.find('#nian_rank_rewardbox').find('#myrank').html(lang('current_rank', [res.myrank]));
                } else {
                    that.e.find('#nian_rank_rewardbox').find('#myrank').html('');
                    fetchbtn.html(lang('nian_no_rank'));
                }

                //排名奖励
                gettype = res.reward.type;
                getvalue = res.reward.value;
                $reward.find('.rewarditem').remove();
                if(gettype == 0) {//无奖励
                    rewardhtml = '<div class="rewarditem c_i_bj1"><p class="no_reward">'+lang('no')+'</p></div>';
                } else {//有奖励
                    if(gettype == 1) {
                        rewardcls = 'gold';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 2) {
                        rewardcls = 'silver';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 3) {
                        rewardcls = 'gem2';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 4) {
                        rewardcls = 'gem3';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 5) {
                        rewardcls = 'osoul';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 6) {
                        rewardcls = 'jade';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 7) {
                        rewardcls = 'prestige';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 8) {
                        rewardcls = 'item';
                        rewardimg = xGame.itemurl(getvalue.id);
                        rewardquality = getvalue.quality;
                        rewardname = getvalue.name;
                    } else if(gettype == 9) {// 魔石碎片
                        rewardcls = 'gempart';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 10) {
                        rewardcls = 'tfcard';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 11) {// 坐骑
                        rewardcls = 'horse';
                        rewardname = getvalue.name;
                    } else if(gettype == 12) {
                        rewardcls = 'gem1';
                        rewardname = 'x' + getvalue;
                    } else if(gettype == 13) {// 武将
                        rewardcls = 'general';
                        rewardimg = xGame.avatar(getvalue.image);
                        rewardname = getvalue.name;
                    }

                    rewardhtml = '<div class="rewarditem c_i_bj1">';
                    if(gettype == 8 || gettype == 13) {
                        rewardhtml += '<div class="itempic ' + rewardcls + '" style="background:url(' + rewardimg + ') 50% 10px no-repeat;background-size: 80px;"></div>';
                    } else if(gettype == 11) {
                        rewardhtml += '<div class="itempic ' + rewardcls + '" style="background-position:' + helper.getHorsePostion('buff', getvalue.id) + ';"></div>';
                    } else {
                        rewardhtml += '<div class="itempic ' + rewardcls + '"></div>';
                    }
                        rewardhtml += '<p class="name" style="color:' + helper.getItemColor(rewardquality) + '">' + rewardname + '</p>';
                    rewardhtml += '</div>';
                }
                $reward.append(rewardhtml);

                if(next) {
                    next();
                }
                
            };

            loadContents({
                'c': 'newyearboss',
                'm': 'get_rank',
                'type': type
            },
            initRender);
            initRender = null;
        },

        getRankReward: function (type, btn) {
            var that = this;

            function initRender(res) {
                switch (res.status) {
                    case 1:
                        getReward(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }
            }

            function getReward(res) {

                var gettype = res.reward.type;
                var getvalue = res.reward.value;

                switch (parseInt(gettype)) {
                    case 1:
                        xGame.toast.show(lang('receive_get_gold', [getvalue]));
                        player.updateinfo({'gold': parseInt(res.all_gold)});
                        break;
                    case 2:
                        xGame.toast.show(lang('you_get_silver'));
                        player.updateinfo({'silver': parseInt(res.all_silver)});
                        break;
                    case 3:
                        xGame.toast.show(lang('you_get_gem', [getvalue, 2]));
                        break;
                    case 4:
                        xGame.toast.show(lang('you_get_gem', [getvalue, 3]));
                        break;
                    case 5:
                        xGame.toast.show(lang('get_original_soul', [getvalue]));
                        break;
                    case 6:
                        xGame.toast.show(lang('you_get_jade', [getvalue]));
                        break;
                    case 7:
                        xGame.toast.show(lang('smashpop_you_get', [getvalue]));
                        break;
                    case 8:
                        xGame.toast.show(lang('c_you_get_item', [getvalue.name]));
                        break;
                    case 9:
                        xGame.toast.show(lang('sanctumworld_receive_gemp', [getvalue]));
                        break;
                    case 10:
                        xGame.toast.show(lang('c_you_get_tfcard', [getvalue]));
                        break;
                    case 11:
                        xGame.toast.show(lang('nian_achievement_get_mount', [helper.getItemColor(getvalue.quality), getvalue.name]));
                        break;
                    case 12:
                        xGame.toast.show(lang('you_get_gem', [getvalue, 1]));
                        break;
                    case 13:
                        xGame.toast.show(lang('c_you_get_silver', [getvalue]));
                        miscpop.newhero(getvalue);
                        break;
                }

                btn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                btn = null;
            }

            loadContents({
                'c': 'newyearboss',
                'm': 'get_rank_reward',
                'type': type
            },
            initRender);
            initRender = null;
        },

        getWan: function(num) {
            var num = parseInt(num);
            if(num <= 999999) {
                return num+'';
            } else if(num > 99999999) {//亿
                return (parseInt(num) / 100000000).toFixed(2) + lang('nian_yi');
            } else {//万
                return Math.round(parseInt(num) / 10000) + lang('10k');
            }
        },

        attackUp: function(type) {
            var that = this;
            var render = function(res) {
                switch (res.status) {
                case 1:
                    xGame.toast.show((type == 'c_gold_up' ? lang('gold') : lang('silver')) + lang('plus_success', [res.powerup]));
                    that.e.find('.promote').children('em').html(res.powerup + '%');
                    if (type == 'c_gold_up') {
                        player.updateinfo({
                            "gold": parseInt(res.all_gold)
                        });
                        that.e.find('#gold_support').html(res.mypowerup);
                    } else {
                        player.updateinfo({
                            "silver": parseInt(res.all_silver)
                        });
                    }
                    break;
                default:
                    xGame.toast.show(res.msg);
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':'newyearboss',
                'm': 'powerup',
                'gold': (type == 'c_gold_up' ? 1 : 0)
            },render);
            render = null;
        },

        getRankList: function () {
            var that = this;

            function initRender(res) {

                that.hasRankInfo = true;
                var html1 = '',tabtype = 0, getvalue = {};
                for (var i = 0, len = res.list.length; i < len; i++) {
                    tabtype = res.list[i].tab1;
                    getvalue = res.list[i].value1;
                    html1 += '<li class="nian_rewardlist_item type' + tabtype + '">';

                    if (tabtype == 13) {
                        html1 += '<em>'+ getvalue.name +'</em>';
                    } else if (tabtype == 11) {
                        html1 += '<em class="horse_pic" style="background-position:' + helper.getHorsePostion('buff', getvalue.id) + ';"></em><em class="horse_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else if (tabtype == 8) {
                        html1 += '<em class="equip_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else {
                        html1 += 'x' + that.getWan(getvalue);
                    }

                    html1 += '</li>';
                }

                that.e.find('#nian_rank_reward_info').find('.reward_list1').html(html1);

                var html2 = '',tabtype = 0, getvalue = {};
                for (var i = 0, len = res.list.length; i < len; i++) {
                    tabtype = res.list[i].tab2;
                    getvalue = res.list[i].value2;
                    html2 += '<li class="nian_rewardlist_item type' + tabtype + '">';

                    if (tabtype == 13) {
                        html2 += '<em>'+ getvalue.name +'</em>';
                    } else if (tabtype == 11) {
                        html2 += '<em class="horse_pic" style="background-position:' + helper.getHorsePostion('buff', getvalue.id) + ';"></em><em class="horse_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else if (tabtype == 8) {
                        html2 += '<em class="equip_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else {
                        html2 += 'x' + that.getWan(getvalue);
                    }

                    html2 += '</span></li>';
                }

                that.e.find('#nian_rank_reward_info').find('.reward_list2').html(html2);

                var html3 = '',tabtype = 0, getvalue = {};
                for (var i = 0, len = res.list.length; i < len; i++) {
                    tabtype = res.list[i].tab3;
                    getvalue = res.list[i].value3;
                    html3 += '<li class="nian_rewardlist_item type' + tabtype + '">';

                    if (tabtype == 13) {
                        html3 += '<em>'+ getvalue.name +'</em>';
                    } else if (tabtype == 11) {
                        html3 += '<em class="horse_pic" style="background-position:' + helper.getHorsePostion('buff', getvalue.id) + ';"></em><em class="horse_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else if (tabtype == 8) {
                        html3 += '<em class="equip_name" style="color:' + helper.getItemColor(getvalue.quality) + '">' + getvalue.name + '</em>';
                    } else {
                        html3 += 'x' + that.getWan(getvalue);
                    }

                    html3 += '</span></li>';
                }

                that.e.find('#nian_rank_reward_info').find('.reward_list3').html(html3);
            }

            loadContents({
                'c':'newyearboss',
                'm': 'get_rank_reward_list'
            },
            initRender);
            initRender = null;         
        },

        goBattleCheck: function() {
            var that = this;

            if (that.indexdata.cd > 0) {
                xGame.confirm.show(lang('notice'), lang('buy_life_ask', [that.indexdata.cdcost]), 2,
                function() {
                    xGame.confirm.hide(function() {
                        miscpop.enterBattleFromNian(1);
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            } else {
                miscpop.enterBattleFromNian(0);
            }
        },

        unbindAll: function() {
            this.e.children('.hidebtn').unbind('touchend');
            this.e.find('.pop_btn_big').unbind('touchend');
            this.e.find('#nian_achievement_btn').unbind('touchend');
            this.e.find('#nian_rank_btn').unbind('touchend');
            this.e.find('#nian_ach_scroller').find('.pop_btn_middle').unbind('click');
            this.e.undelegate('#cboss_rule_btn', 'touchend');
            this.e.undelegate('#cboss_rule_hide', 'touchend');
            this.e.undelegate('.inspire.pop_btn_middle', 'touchend');
            this.e.find('#nian_achievement').undelegate('.hidebtn', 'touchend');
            this.e.find('#nian_achievement').undelegate('.pop_btn_small', 'touchend');
            this.e.undelegate('.rank_tab', 'touchend');
            this.e.find('#nian_rank').undelegate('.nian_rank_nickname', 'click');
            this.e.find('#nian_rank').undelegate('#nian_rank_fetch.pop_btn_middle', 'touchend');
            this.e.undelegate('#nian_rank_hide_btn', 'touchend');
            this.e.undelegate('#nian_rank_reward', 'touchend');
            this.e.undelegate('#nian_rank_rewardlist_hide_btn', 'touchend');
            this.e.find('#hitlist').undelegate('.user', 'touchend');
        },

        clear: function() {
            xGame.unlock();
            this.indexdata = null;
            this.oldworld = '';
            this.hasRankInfo = false;
            
            xGame.updateCd('nianButton', 0);
            xGame.updateCd('nian', 0);
            xGame.updateCd('nianRankButton', 0);
            
            if(this.rankScroller) {
                this.rankScroller.destroy();
                this.rankScroller = null;
                this.rank_now = 0;
            }
	    
    	    if(this.sideScroller) {
    	    	this.sideScroller.destroy();
                this.sideScroller = null;
    	    }

            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }

            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
        },
    };
})();