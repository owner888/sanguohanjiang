/* 过关斩将 */
(function() {
    breakthroughWorldClass = function() {
        this.e;
        this.isshow = false;

        this.breakthroughScroller;
        this.rewardScroller;
        this.achievementScroller;

        this.ltDom;
        this.fbDom;
        this.rcDom;
        this.achievementDom;
        this.boxDom;

        this.boxType;
        this.playerPosition;
        this.playerInfo;
        this.achieveTimer = null;
        this.rewardData;    //兑换页面的奖励
        this.preRewardData;    //预览页面的奖励
        this.nowPlayerId;//当前过人id;
        this.times = 0;
        this.price1 = 0;
    };
    breakthroughWorldClass.prototype = {
        init: function() {
            var that = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        that.initFirst(res);    
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        xGame.loadWorld('city');
                        break;
                }
            };
            
            loadContents({
                'c': 'pass_general',
                'm': 'index'
            },
            initRender, false,
            function() {
                xGame.loadWorld('city');
            }); //加载数据

            initRender = null;
        },

        initFirst: function(res) {
            var that = this;

            if(that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('breakthroughworld_tmpl'));
            that.e = dom_world.find('#breakthroughworld_page');
            that.e.find('#breakthroughworld_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                xGame.loadWorld('city');
                e.preventDefault();
                e.stopPropagation();
            });
            dom_world = null;

            that.ltDom = that.e.find('#breakthrough_lt');
            that.fbDom = that.e.find('#breakthrough_footballer');
            that.rcDom = that.e.find('#breakthrough_rc');
            that.achievementDom = that.e.find('#achievement_dom');
            that.boxDom = that.e.find('#breakthrough_box_dom');

            that.playerInfo = res.pass_info;
            that.playerPosition = {
                1: {
                    left: 105,
                    top: 400
                },
                2: {
                    left: 305,
                    top: 400
                },
                3: {
                    left: 490,
                    top: 400
                },
                4: {
                    left: 650,
                    top: 400
                },
                5: {
                    left: 195,
                    top: 250
                },
                6: {
                    left: 380,
                    top: 250
                },
                7: {
                    left: 584,
                    top: 250
                },
                8: {
                    left: 230,
                    top: 113
                },
                9: {
                    left: 380,
                    top: 113
                },
                10: {
                    left: 535,
                    top: 113
                },
                11: {
                    left: 374,
                    top: -11
                }
            };

            var html = '';
            html += '<div id="footballer_box">';
            for(var i = 10; i >= 0; i--) {
                html += '<div class="player_hero" id="hero' + that.playerInfo[i].id + '" hid="' + that.playerInfo[i].id + '">';
                html +=     '<p class="palyer_shadow"></p>';
                html +=     '<p class="player_img" style="background-image:url(' + xGame.hero(that.playerInfo[i].image, 1) + ');margin-left:' + (hero_ini[that.playerInfo[i].image]['mx'] + 80) + 'px;margin-top:' + hero_ini[that.playerInfo[i].image]['my'] + 'px"></p>';

                html +=     '<em class="breached' + (that.playerInfo[i].show_status == 1 ? ' had_receive' : '') + '"></em>';
                html += '</div>'; 
            }


            that.nowPlayerId = res.pass_id == 12 ? 1 : res.pass_id;

            html +=     '<div id="now_battle" style="left:' + that.playerPosition[that.nowPlayerId].left + 'px;top: '+ that.playerPosition[that.nowPlayerId].top + 'px">';
            html +=         '<p id="now_ball"><em class="ball"><em></p>';
            html +=         '<div id="animation_ball"></div>';
            html +=      '</div>';
            html += '</div>'
            that.fbDom.html(html);

            that.boxType = res.reward_info;

            var html = '';
            for(var i = 0; i < res.reward_info.length; i++) {
                var boxType = that.boxType[i].box_id == 4 ? 1 : 2;
                html += '<div class="reward_box' + boxType + '" id="breakthrough_box' + that.boxType[i].box_id + '">';
                html +=     '<p class="' + (that.boxType[i].reward_status == 3 ? 'open ' : '') +'preview_reward" id="breakthrough_box' + that.boxType[i].box_id + '_img" boxid="' + that.boxType[i].box_id + '"><em></em></p>';
                if(that.boxType[i].reward_status == 2) {
                    var text = lang('receive');
                } else if(that.boxType[i].reward_status == 3) {
                    var text = lang('warena_have_fetch');
                } else {
                    var text = lang('not_reached');
                }
                html +=     '<button class="btn ' + (that.boxType[i].reward_status == 2 ? 'pop_btn_small' : 'pop_btn_small_disable') + '">' + text + '</button>';
                html += '</div>';
            }

            that.rcDom.html(html);
            that.rewardInfo();

            //活动时间和金币
            that.e.find('#breakthroughworld_page_time em').html(res.time);
            that.e.find('#breakthroughworld_page_time #gold').html(player.info.gold);

            if(res.pass_id == 12) {
                that.fbDom.find('#now_battle').find('#now_ball').hide();
            }

            that.showPopo(res.achieve_popo);

            that.breakthroughScroller = new iScroll ('breakthrough_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.breakthroughScroller.refresh();

            if(res.pass_id < 8 && isIPhone5) {
                that.breakthroughScroller.scrollTo(0, -128);
            }

            that.priceRefresh(res.price_list);
            that.bindAll();

            $('#worldloading').removeClass('black').hide();

        },

        priceRefresh: function(price_list) {
            var that = this;

            //普通过人
            var price1 = price_list[0];
            that.times = price1.times;
            that.price1 = price1.price <= 0 ? 0 : price1.price;
            if(price1.cd == 0 && price1.times != 0) { //免费
                that.e.find('#breakthrough_btn1').html(lang('breakthrough_free',[price1.times]));
            } else if(price1.times == 0) {
                that.e.find('#breakthrough_btn1').removeClass('free').html(lang('breakthrough_break') + '<em class="spend_gold">x' + price1.price + '</em>');
            } else {
                xGame.updateCd('breakthrough', price1.cd);
            }

            //强制过人
            var price2 = price_list[1];
            that.e.find('#breakthrough_btn2').find('.spend_gold').html('x' +price2.price);
        },

        rewardInfo: function() {
            var that = this;

            for(var i = 0; i < 4; i++) {
                var btnDom = that.rcDom.find('#breakthrough_box' + that.boxType[i].box_id).find('.btn');
                if(that.boxType[i].reward_status == 2) {
                    btnDom.removeClass('pop_btn_small_disable').addClass('pop_btn_small box_btn');
                    btnDom.attr('boxid', that.boxType[i].box_id).html(lang('receive'));
                    var arrowDom = '<div id="guide_arrow" class="guide_arrow"></div>';
                    that.rcDom.find('#breakthrough_box' + that.boxType[i].box_id).append(arrowDom);
                } else if(that.boxType[i].reward_status == 3) {
                    that.rcDom.find('#breakthrough_box' + (i + 1) + '_img').addClass('open');
                    btnDom.attr('boxid', that.boxType[i].box_id).html(lang('warena_have_fetch'));
                } else if(that.boxType[i].reward_status == 1) {
                    that.rcDom.find('#breakthrough_box' + (i + 1) + '_img').removeClass('open');
                    btnDom.attr('boxid', that.boxType[i].box_id).html(lang('not_reached'));
                    btnDom.attr('class','btn pop_btn_small_disable');
                }
            }
        },

        bindAll: function() {
            var that = this;

            //领取过关奖励按钮
            that.rcDom.undelegate('.box_btn.pop_btn_small, #guide_arrow', 'touchend').delegate('.box_btn.pop_btn_small, #guide_arrow', 'touchend', function() {
                if (xGame.checkLock()) { //不设置属性
                    return false;
                }
                var box = that.rcDom.find('.box_btn.pop_btn_small');
                that.getReward(box);
            });
            //预览过关奖励
            that.rcDom.find('.preview_reward').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) { //不设置属性
                    return false;
                }
                var boxid = parseInt($(this).attr('boxid'));
                that.preReward(boxid);

                e.preventDefault();
                e.stopPropagation();

            });            
            //奖励关闭按钮 小的
            that.e.find('#breakthrough_box_hide').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }                

                that.boxDom.hide();

                e.preventDefault();
                e.stopPropagation();
            });

            //奖励关闭按钮 大的
            this.e.find('#reward_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.rewardScroller.destroy();
                that.rewardScroller = null;
                that.e.find('#breakthrough_reward').hide();

                e.preventDefault();
                e.stopPropagation();
            });


            //奖券兑换按钮
            this.e.find('#breakthrough_achieve_reword').unbind('click').bind('click', function(e) {
                if (xGame.checkLock()) { //不设置属性
                    return false;
                }
                that.boxDom.hide();
                that.showLottery();
                e.preventDefault();
                e.stopPropagation();

            });

            // 奖券兑换中的奖励项
            this.e.find('#breakthrough_lottery').undelegate('.img', 'click').delegate('.img', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }
                var parent = $(this).parent();
                if (parent.hasClass('type12') || parent.hasClass('type26')) {
                    var index = $('#breakthrough_lottery_carousel .box').index(parent); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(that.rewardData[index].general);
                } else if (parent.hasClass('type8')) {
                    var index = $('#breakthrough_lottery_carousel .box').index(parent); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(that.rewardData[index].info);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            // 奖励预览中的奖励项
            this.e.find('#breakthrough_carousel').undelegate('.box', 'click').delegate('.box', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }

                that.e.find('#breakthrough_carousel').find('.box').removeClass('selected');
                $(this).addClass('selected');

                if ($(this).hasClass('type12') || $(this).hasClass('type26')) {
                    var index = $('#breakthrough_carousel .box').index($(this)); // 取得武将在所有奖励中的索引
                    miscpop.showHeroInfo(that.preRewardData[index].general);
                } else if ($(this).hasClass('type8')) {
                    var index = $('#breakthrough_carousel .box').index($(this)); // 取得装备在所有奖励中的索引
                    miscpop.showItemInfo(that.preRewardData[index].info);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            //奖券兑换关闭按钮
            this.e.find('#lottery_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.lotteryScroller.destroy();
                that.lotteryScroller = null;
                that.e.find('#breakthrough_lottery').css('display', 'none');

                e.preventDefault();
                e.stopPropagation();
            });

            //进球成就按钮
            this.e.find('#breakthrough_ball_achieve').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) { //不设置属性
                    return false;
                }
                that.boxDom.hide();
                that.getAchievementData();
                e.preventDefault();
                e.stopPropagation();

            });

            // 进球成就的关闭按钮
            that.achievementDom.find('#achievement_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.achieveTimer != null) {
                    clearTimeout(that.achieveTimer);
                }
                that.achieveTimer = null;

                that.achievementDom.find('.reward_box').remove();

                that.achievementDom.hide();
                e.preventDefault();
            });

            // 进球成就的领取按钮
            that.achievementDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                var id = parseInt($(this).attr('aid'));
                that.getAchievementReward(id);
                e.preventDefault();
            });

            //玩法说明按钮
            this.e.find('#breakthrough_info_btn').unbind('touchend').bind('touchend', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#breakthrough_info').css('display', 'block');
                that.boxDom.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            this.e.find('#breakthrough_info').find('.mask').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#breakthrough_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            //玩法说明关闭按钮
            this.e.find('#info_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#breakthrough_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            //踢球按钮
            this.e.find('.btn.play_ball').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                var id = $(this).attr('pid');

                that.boxDom.hide();
                that.requestPlayBall(id);
                e.preventDefault();
                e.stopPropagation();
            });

        },

        requestPlayBall: function(type) {
            var that = this;

            var render = function (res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.showPopo(res.achieve_popo);
                        that.doAniamtion1(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        xGame.unlock();
                        break;
                }
            };

            loadContents({
                'c': 'pass_general',
                'm': 'action',
                'id': that.nowPlayerId,
                'type': type
            }, render);

            render = null;            
        },

        //过人
        playBall: function(res) {
            var that = this;

            var goldDom = that.e.find('#breakthroughworld_page_time').find('#gold');
            if(res.all_gold != player.info.gold) {
                goldDom.addClass('flash').html(res.all_gold);

                setTimeout(function() {
                    goldDom.removeClass('flash');
                }, 100);
            }

            that.priceRefresh(res.price_list);

            if(res.is_succ == 1) { //过人成功
                that.fbDom.find('#hero' + (that.nowPlayerId) + '>em').addClass('breached first_receive').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                    $(this).addClass('class', 'breached had_receive');
                });
                if(res.pass_id == 12) {
                    that.fbDom.find('#now_battle').find('#now_ball').hide();
                }
            } else { //过人失败
                switch(parseInt(res.pass_id)) {
                    case 1:
                        var num = 5;
                        break;
                    case 5:
                        var num = 8;
                        break;
                    case 8:
                        var num = 11;
                        break;
                    case 11:
                        var num = 12;
                        break;
                    default:
                        var num = 0;
                        return;
                        break;
                }
                for(var i = res.pass_id; i < num; i++) {
                    that.fbDom.find('#hero' + i).find('.breached').removeClass('breached');
                }
            }

            if(res.reward.type == 12 || res.reward.type == 26){
                miscpop.newhero(res.reward.general);
            } else {
                var className = that.getType(res.reward.type);
                var name = 'x' + res.reward.value;
                var color = '#FFFFFF';
                xGame.toast.show('<span class="reward_crit rc_breakthrough' + res.is_succ + '">' + lang('breakthrough_reward', [className, name, color]) + '</span>');
            }

            if(res.pass_id != that.nowPlayerId && res.pass_id != 12) {
                var x = that.playerPosition[res.pass_id].left - that.playerPosition[that.nowPlayerId].left;
                var y = that.playerPosition[res.pass_id].top - that.playerPosition[that.nowPlayerId].top;
                var rotation = ((res.pass_id == 1 || res.pass_id == 5 || res.pass_id == 8 || res.pass_id == 11) ? 'rotation_reverse' : 'rotation');
                that.fbDom.find('#now_ball').find('.ball').addClass(rotation);
                helper.animate(that.fbDom.find('#now_ball').find('.ball'), {'left':  x + 'px', 'top': y + 'px'}, 500, 'Cubic.easeOut', function(){
                    that.fbDom.find('#now_ball').find('.ball').removeClass(rotation);
                    that.fbDom.find('#now_battle').css({
                        'left': that.playerPosition[res.pass_id].left + 'px',
                        'top': that.playerPosition[res.pass_id].top + 'px'
                    });

                    that.fbDom.find('#now_ball').find('.ball').css({
                    'left': '0px',
                    'top': '0px',
                    });

                    xGame.unlock(); 
                });
            } else {
                xGame.unlock(); 
            }

            that.nowPlayerId = res.pass_id == 12 ? 1 : res.pass_id;
            //更新宝箱
            that.boxType = res.reward_info;
            that.rewardInfo();
        },

        // 获取成就数据
        getAchievementData: function () {
            
            var that = this;

            var render = function (res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.showAchievement(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        xGame.unlock();
                        break;
                }
            };

            loadContents({
                'c': 'pass_general',
                'm': 'achievement_reward'
            }, render);

            render = null;
        },

        // 显示成就
        showAchievement: function (res) {
            
            var that = this;
            var item = null;
            var html = '';
            var type = 0;
            var index = 0; // 用于进度条
            var nowNum = parseInt(res.num);
            var percent = 0;
            that.achieveList = res.list;

            // 构建奖励列表
            for (var i = 0, len = res.list.length; i < len; i++) {
                
                item = res.list[i];
                html += '<li class="achieve_item">';
                html += '<span class="des">' + item.desc + '</span>';
                html += '<div class="achieve_reward">'

                for (var j = 1; j <= 3; j++) {
                    type = parseInt(item['type' + j]);

                    if (type == 8) { // 装备
                        html += '<span class="reward type'+ type +'" style="color:'+ helper.getItemColor(item['info' + j].quality) +'"><em class="item" style="background-image:url('+ xGame.itemurl(item['info' + j].id) +')"></em>' + item['info' + j].name + '</span>';
                    } else if (type == 11) { // 坐骑
                        html += '<span class="reward type'+ type +'" style="color:'+ helper.getItemColor(item['info' + j].quality) +'"><em class="item" style="background-position:'+ helper.getHorsePostion('buff', item['info' + j].id) +'">' + item['info' + j].name + '</span>';
                    } else if (type == 13) { // 武将
                        html += '<span class="reward type'+ type +'"><em class="item" style="background-image:url('+ xGame.avatar(item['info' + j].id) +')"></em>' + item['info' + j].name + '</span>';
                    } else if (type >= 21 && type <= 26) { // 精华
                        html += '<span class="reward essence" style="background-image:url('+ xGame.essence(item['info' + j].id) +');color:'+ helper.getItemColor(item['info' + j].quality) +'">x' + that.getWan(item['info' + j].value) + '</span>';
                    } else {
                        html += '<span class="reward type'+ type +'"><span class="img"></span>x' + that.getWan(item['value' + j]) + '</span>';
                    }
                }

                html += '</div>';

                switch (parseInt(item.open)) {
                    case 0:
                        html += '<button class="reward_btn pop_btn_middle_disable">' + lang('not_reached') + '</button>';
                        break;
                    case 1:
                        html += '<button class="reward_btn pop_btn_middle" id="reward'+ item.id +'" aid="'+ item.id +'">' + lang('warena_fetch') + '</button>';
                        break;
                    case 2:
                        html += '<span class="received"></span>';
                    default :
                        break;
                }

                html += '</li>';
            }

            that.achievementDom.find('#achievement_list').html(html);
            that.achievementDom.find('#complete_num').html(nowNum);

            html = '';

            // 进度条
            for (var i = 0, len = res.num_list.length; i < len; i++) {
                item = parseInt(res.num_list[i]);
                html += '<li class="num">' + item + '</li>';

                if (item <= nowNum) {
                    index = i + 1;
                }
            }

            that.achievementDom.find('#progress_phase').html(html);

            if (index == 0) {
                percent = 0;
            } else {
                percent = index * 10;
                percent += (nowNum - res.num_list[index - 1]) / (res.num_list[index] - res.num_list[index - 1]) * 10;
            }

            if (percent < 100 && percent > 0) {
                that.achievementDom.find('#now_progress').width(percent + '%').html('<span class="progress_light"></span>');
            } else if (percent == 0) {
                that.achievementDom.find('#now_progress').width('0%').html('');
            } else {
                that.achievementDom.find('#now_progress').width('100%').html('');
            }
            
            that.achievementDom.show();

            that.achievementScroller = new iScroll('breakthrough_achievement_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            xGame.unlock();
        },

        // 领取过人成就奖励
        getAchievementReward: function (id) {
            
            var that = this;

            var render = function (res) {

                switch (parseInt(res.status)) {
                    case 1:
                        player.updateinfo({
                            'gold': res.all_gold,
                            'silver': res.all_silver
                        });

                        that.showPopo(res.achieve_popo);
                        that.achievementDom.find('#reward' + id).replaceWith('<span class="received"></span>');

                        if (that.achieveTimer != null) {
                            clearTimeout(that.achieveTimer);
                            that.achieveTimer = null;
                        }

                        that.achieveRewardDown(that.achieveList[id - 1]);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'pass_general',
                'm': 'action_achievement_reward',
                'aid': id
            }, render);

            render = null;
        },

        // 是否显示感叹号
        showPopo: function (achieve_popo) {

            var that = this;
            
            if (achieve_popo != null) {
                if (parseInt(achieve_popo) == 1) {
                    that.e.find('#breakthrough_ball_achieve').children('.popo').show();
                } else {
                    that.e.find('#breakthrough_ball_achieve').children('.popo').hide();
                }
            }

        },

        // 奖励掉落
        achieveRewardDown: function(item) {
            
            var that = this;
            
            var rewardBox = that.achievementDom.find('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var html = '';
            var type = 0;

            html += '<div class="reward_box">';

            for (var i = 1; i <= 3; i ++) {
                type = parseInt(item['type' + i]);

                if (type == 8) { // 装备
                    html += '<span class="reward_down pos' + i + ' type'+ type +'" style="color:'+ helper.getItemColor(item['info' + i].quality) +'"><em class="pic" style="background-image:url('+ xGame.itemurl(item['info' + i].id) +')"></em>' + item['info' + i].name + '</span>';
                } else if (type == 11) { // 坐骑
                    html += '<span class="reward_down pos' + i + ' type'+ type +'" style="color:'+ helper.getItemColor(item['info' + i].quality) +'"><em class="pic" style="background-position:'+ helper.getHorsePostion('buff', item['info' + i].id) +'"></em>' + item['info' + i].name + '</span>';
                } else if (type == 13) { // 武将
                    html += '<span class="reward_down pos' + i + ' type'+ type +'"><em class="pic" style="background-image:url('+ xGame.avatar(item['info' + i].id) +')"></em>' + item['info' + i].name + '</span>';
                } else if (type >= 21 && type <= 26) { // 精华
                    html += '<span class="reward_down pos' + i + '" style="color:'+ helper.getItemColor(item['info' + i].quality) +'"><em class="pic reward essence" style="background-image:url('+ xGame.essence(item['info' + i].id) +')"></em>x' + item['info' + i].value + '</span>';
                } else {
                    html += '<span class="reward_down pos' + i + '"><em class="pic reward type'+ type +'"><em class="img"></em></em>x' + item['value' + i] + '</span>';
                }
            }

            html += '</div>';
            that.achievementDom.append(html);
            
            that.achieveTimer = setTimeout(function() {
                
                if (that.achievementDom.css('display') == 'block') {
                    that.achievementDom.find('.reward_box').remove();
                }

            }, 3000);
            
            html = null;
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        showLottery: function() {
            var that = this;

            var render = function (res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.rewardData = res.list;
                        var html = that.getHtml(res.list, 'lottery');
                        that.e.find('.carousel_content').html(html);
                        that.e.find('.mylottery').html(''+res.lottery_num);
                        that.lotteryScroller = xGame.carousel('breakthrough_lottery_carousel', 750);
                        that.e.find('#breakthrough_lottery').show();
                        //兑换按钮
                        that.e.find('#breakthrough_lottery_carousel').find('.lotteryenablebtn').unbind('click').bind('click', function(e) {
                            if (xGame.checkLock(true)) {
                                return false;
                            }

                            var lotteryid = $(this).attr('uid');
                            xGame.confirm.show(lang('kemaripop_lottery_act_title'), lang('kemaripop_lottery_act', [that.rewardData[lotteryid-1].lottery_num]), 2, function () {
                                var render = function(res) {
                                    switch(parseInt(res.status)) {
                                        case 1:
                                            that.updateLotteryInfo(res);
                                            break;
                                        default:
                                            xGame.toast.show(res.msg);
                                            break;
                                    }
                                    xGame.confirm.hide();
                                };

                                loadContents({
                                    'c': 'pass_general',
                                    'm': 'action_lottery',
                                    'id': lotteryid
                                }, render);
                                render = null;
                            }, function () {
                                xGame.confirm.hide();
                            })
                        });
                        break;
                    default :
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'pass_general',
                'm': 'lottery_index'
            }, render);
            render = null;
        },

        updateLotteryInfo: function(res) {
            //显示获得奖励
            this.showMessage(res.reward);
            //刷新兑换按钮
            for (var i = 0; i < res.list.length; i++) {
                var item = res.list[i];
                if (item.lottery_num > res.lottery_num) {
                   this.e.find('#breakthrough_lottery_carousel').find('#btn'+parseInt(item.id)).removeClass('lotteryenablebtn').addClass('lotterydisbtn');
                } else {
                   this.e.find('#breakthrough_lottery_carousel').find('#btn'+parseInt(item.id)).removeClass('lotterydisbtn').addClass('lotteryenablebtn');
                }
            }
            var that = this;
            //兑换按钮
            this.e.find('#breakthrough_lottery_carousel').find('.lotterydisbtn').unbind('click');
            this.e.find('#breakthrough_lottery_carousel').find('.lotteryenablebtn').unbind('click').bind('click', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var lotteryid = $(this).attr('uid');
                xGame.confirm.show(lang('kemaripop_lottery_act_title'), lang('kemaripop_lottery_act', [that.rewardData[lotteryid-1].lottery_num]), 2, function () {
                    var render = function(res) {
                        switch(parseInt(res.status)) {
                            case 1:
                                that.updateLotteryInfo(res);
                                break;
                            default:
                                xGame.toast.show(res.msg);
                                break;
                        }
                        xGame.unlock();
                        xGame.confirm.hide();
                    };

                    loadContents({
                        'c': 'pass_general',
                        'm': 'action_lottery',
                        'id': lotteryid
                    }, render);
                    render = null;
                }, function () {
                    xGame.confirm.hide();
                })
            });
            //刷新兑奖券数量
            this.e.find('.mylottery').html(''+res.lottery_num);
        },

        // 显示玩家获得的奖励
        showMessage: function (reward) {
            
            var type = parseInt(reward.type);

            switch (type) {
                case 1:     // 元宝
                    xGame.toast.show(lang('acttreasurepop_msg1', [reward.value]));
                    break;
                case 2:     // 银币
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
                    xGame.toast.show(lang('acttreasurepop_msg7', [helper.getItemColor(reward.equip.quality), reward.equip.name]));
                    break;
                case 9:     // 宝石碎片
                    xGame.toast.show(lang('acttreasurepop_msg8', [reward.value]));
                    break;
                case 10:    // 突飞卡
                    xGame.toast.show(lang('acttreasurepop_msg9', [reward.value]));
                    break;
                case 12:    // 武将
                case 26:    // 神将
                    if(reward.general.genrealid == undefined) {
                         var heroType = reward.type == 12 ? lang('breakthrough_herotype12') : lang('breakthrough_herotype26');
                        xGame.toast.show(lang('breakthrough_reward26', [heroType, reward.general.type, reward.general.value, res.reward.general.name]));
                    } else {
                        miscpop.newhero(reward.general);
                    }
                    break;
                case 13:     // 军令
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
                case 27:    //奖券
                    xGame.toast.show(lang('kemaripop_msg', [reward.value]));
                    break;
                default :
                    xGame.toast.show(lang('acttreasurepop_err_msg6'));
                    break;
            }
        },

        getHtml: function (list, type) {
            var html = '<li>';
            var n = 0;
            var num = 0;
            var classname = '';
            if (type == 'reward') {
                num = 12;
            } else if (type == 'lottery') {
                num = 8;
            }

            for (var i = 0, len = list.length; i < len; i++) {

                var item = list[i];
                
                // 下一页
                if (n > 0 && n % num == 0) {
                    html += '</li><li>';
                }

                html += '<div class="box type'+ item.type +'">';

                if (type == 'lottery') {
                    if (parseInt(item.type) == 12 || parseInt(item.type) == 26) {//武将
                        html += '<span class="img" style="background-image:url(' + xGame.avatar(item.general.image) + ')"></span><em class="value">' + item.general.name + '</em>';
                    } else if (parseInt(item.type) == 8) { //装备
                        html += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.image) +');"></span><em class="value" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.info.name + '</em>';
                    } else {
                        html += '<span class="img"></span>';
                        html += '<em class="value">x' + item.value + '</em>';
                    }
                    html += '<em class="number">' + item.lottery_num + '</em>';
                } else {
                if (parseInt(item.type) == 12 || parseInt(item.type) == 26) {//武将
                    html += '<span class="img" style="background-image:url(' + xGame.avatar(item.general.image) + ')"><em class="name">' + item.name + '</em></span>';
                } else if (parseInt(item.type) == 8) { //装备
                    html += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.image) +');"><em class="name" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.name + '</em></span>';
                } else if (parseInt(item.type) >= 17 && parseInt(item.type) <= 25) {//精华
                    html += '<span class="img" style="background-image:url('+ xGame.essence(item.info.id) +');"><em class="name" style="color:'+ helper.getItemColor(item.info.quality) +';">'+ item.info.name +'</em></span>';
                } else {
                    html += '<span class="img"></span>';
                    html += '<em class="name">' + item.name + '</em>';
                }

                }
                if (type == 'lottery') {
                    if (parseInt(item.is_exch) != 0) {
                        html += '<button class="lotteryenablebtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('acttreasurepop_exchange')+'</button>';
                    } else {
                        html += '<button class="lotterydisbtn" id="btn'+item.id+'" uid="'+item.id+'">'+lang('acttreasurepop_exchange')+'</button>';
                    }
                }

                html += '</div>';
                n++;
            }

            html += '</li>';

            return html;
        },

        getReward: function(obj) {
            var that = this;
            var id = parseInt(obj.attr('boxid'));
            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        var imgId = (id == 4 ? 3 : 2);
                        that.rcDom.find('#guide_arrow').remove();
                        that.doAniamtion2(res, imgId, obj, id);
                        break;
                    default:
                        xGame.unlock();
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            
            loadContents({
                'c': 'pass_general',
                'm': 'action_reward',
                'id': id
            },
            Func, true); //加载数据

            Func = null;
        },

        preReward: function(id) {
            var that = this;
            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        var html = '';
                        if(id == 4) {
                            that.preRewardData = res.reward;
                            var html = that.getHtml(res.reward, 'reward');
                            that.e.find('.carousel_content').html(html);
                            that.rewardScroller = xGame.carousel('breakthrough_carousel', 750);
                            that.e.find('#breakthrough_reward').show();
                        } else {
                            for(var i = 0; i < res.reward.length; i++) {
                                html += '<p class="' + that.getType(res.reward[i].type) + '">' + res.reward[i].name + 'x' + res.reward[i].value + '</p>';
                            }
                            that.boxDom.find('#breakthrough_box_info').html(html);
                            that.boxDom.find('#breakthrough_box_title').html(res.info);
                            that.boxDom.show();                            
                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            };
            
            loadContents({
                'c': 'pass_general',
                'm': 'reward_info',
                'id': id
            },
            Func, true); //加载数据

            Func = null;
        },

        getType: function(type) {
            var className;
            switch(parseInt(type)) {
            case 8://装备
            case 12://武将
            case 26://神将
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
            case 27://奖券
                className = 'add_lottery';
                break;
            }

            return className;
        },

        
        rewardDown: function(data, type) {
            var that = this;
            var html = '';
            var getClass;
            html += '<div class="breakthrough_reward_box">';

            for (var i = 0;i < data.length; i ++) {
                var info = data[i];
                var j = (data.length == 1 ? 2 : (i + 1));
                getClass = that.getType(info.type);
                html += '<span class="' + getClass + ' reward_down pos' + j + '">' + info.name + 'x' + info.value + '</span>';
            }

            html += '</div>';
            that.e.find('#breakthrough_reward_box_aniamtion').append(html);

            that.deleteThread = setTimeout(function() {
                var animateDom = that.e.find('#breakthrough_reward_box_aniamtion');
                if (animateDom) {
                    animateDom.find('.breakthrough_reward_box').remove();
                    animateDom.hide();
                    animateDom.removeClass();
                    xGame.unlock();
                }
                if(type != undefined && type == 4) {
                    that.fbDom.find('.breached').removeClass('breached');
                    that.fbDom.find('#now_battle').css({
                        'left': that.playerPosition[1].left + 'px',
                        'top': that.playerPosition[1].top + 'px'
                    });
                    that.breakthroughScroller.scrollTo(0, -128);
                     var render = function (res) {

                        switch (parseInt(res.status)) {
                            case 1:
                                that.boxType = res.reward_info;
                                that.nowPlayerId = res.pass_id == 12 ? 1 : res.pass_id;
                                that.fbDom.find('#now_battle').find('#now_ball').show();
                                that.showPopo(res.achieve_popo);
                                that.priceRefresh(res.price_list);
                                that.rewardInfo();
                                break;
                            default :
                                xGame.toast.show(res.msg);
                                break;
                        }
                        xGame.unlock();
                    };

                    loadContents({
                        'c': 'pass_general',
                        'm': 'index'
                    }, render);

                    render = null;
                }
            }, 2000);
           
            html = null;
        },

        //过人动画
        doAniamtion1: function(res) {
            var that = this;
         
            var mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/breakthroughpop_animation1.png'),
                "width": 120,
                "height": 90,
                "scale": 1,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    //回调函数                    
                    that.playBall(res);          
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 100
                },
                {
                    rect: [120, 0],
                    interval: 100
                },
                {
                    rect: [240, 0],
                    interval: 100
                },
                {
                    rect: [360, 0],
                    interval: 110
                },
                {
                    rect: [480, 0],
                    interval: 110
                },
                {
                    rect: [600, 0],
                    interval: 110
                },
                {
                    rect: [720, 0],
                    interval: 120
                }]
            });

            mc.appendTo(this.fbDom.find('#animation_ball'));
            mc.play();
            mc = null;
        },

        //领取过人宝箱
        doAniamtion2: function(res, imgId, obj, type) {
            var that = this;
         
            var mc = new movieClipClass({
                "img":'static/img/pop/breakthroughpop_animation' + imgId + '.png',
                "width": 600,
                "height": 600,
                "scale": 1,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    obj.html(lang('warena_have_fetch')).removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                    obj.parent().find('.preview_reward').addClass('open');
                    that.e.find('#breakthrough_reward_box_aniamtion').addClass('show' + imgId);
                    that.e.undelegate('#breakthroughworld_page').delegate('#breakthroughworld_page', 'touchend', function() {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        that.e.find('#breakthrough_reward_box_aniamtion').hide();
                    });

                    that.rewardDown(res.reward, type);
                },
                "frames": [{
                    rect: [0, 0],
                    interval: 80
                },
                {
                    rect: [600, 0],
                    interval: 80
                },
                {
                    rect: [1200, 0],
                    interval: 80
                },
                {
                    rect: [1800, 0],
                    interval: 80
                },
                {
                    rect: [0, 600],
                    interval: 80
                },
                {
                    rect: [600, 600],
                    interval: 80
                },
                {
                    rect: [1200, 600],
                    interval: 80
                },
                {
                    rect: [1800, 600],
                    interval: 80
                },
                {
                    rect: [0, 1200],
                    interval: 80
                },
                {
                    rect: [600, 1200],
                    interval: 80
                },
                {
                    rect: [1200, 1200],
                    interval: 80
                },
                {
                    rect: [1800, 1200],
                    interval: 80
                }]
            });

            that.e.find('#breakthrough_reward_box_aniamtion').show();
            mc.appendTo(that.e.find('#breakthrough_reward_box_aniamtion'));
            mc.play();
            mc = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.unbind('touchmove');
                this.e.find('#breakthroughworld_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        clear: function() {
            xGame.unlock();
            xGame.updateCd('breakthrough', 0);

            if (this.e == undefined) {
                return false;
            }
            if (this.breakthroughScroller != null) {
                this.breakthroughScroller.destroy();
            }
            this.breakthroughScroller = null;
            if (this.achievementScroller != null) {
                this.achievementScroller.destroy();
            }
            this.achievementScroller = null;

            this.ltDom = null;
            this.fbDom = null;
            this.rcDom = null;
            this.achievementDom = null;
            this.boxDom = null;

            this.boxType = null;
            this.playerPosition = null;
            this.playerInfo = null;
            this.achieveTimer = null;
            this.rewardData = null;    //兑换页面的奖励
            this.preRewardData = null;    //预览页面的奖励
            this.nowPlayerId = null;//当前过人id;
            this.times = 0;
            this.price1 = 0;

            this.unbindAll();
        }
    };
})();