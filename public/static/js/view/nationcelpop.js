/* 国家庆典 */
(function() {
    nationcelPopClass = function() {
        this.e;
        this.isshow = false;
        this.anim = null;
        this.leftDom;
        this.rightDom;
        this.rewardScroller1 = null;
        this.rewardScroller2 = null;
        this.leftRewardDom = null;
        this.rewardDom = null;
        this.giftData = {}; //奖励数据，以id为键
        this.resData; //返回数据
        this.isTimeout = [];
        this.nationcelScroll;
        this.nationcelListDom;
        this.rank = [];
        this.rankName = [];
    };
    nationcelPopClass.prototype = {
        init: function() {
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.e = xGame.loadPopTmpl('nationcel');
                        that.fillGroup(res.member_list);
                        that.fillData(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            loadContents({
                    'c': 'act_celebration',
                    'm': 'index'
                },
                Func, true); //加载数据
            Func = null;
        },

        fillGroup: function(list) { //获取身份列表和身份等级
            var that = this;
            for (var i = 0; i < list.length; i++) {
                that.rank[i] = list[i].gold;
                that.rankName[i + 1] = list[i].name;
            }
            that.rankName[0] = '' + lang('bianhu') + '';
        },

        fillData: function(res) {
            var that = this;
            that.resData = res;
            this.isEnd = res.end; //判断活动是否结束
            that.e.find('.content').find('#nationcelpop_hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    nationcelpop.hide();
                    e.preventDefault();
                });
            xGame.updateCd('nationcel', that.resData.cd_time);
            that.leftDom = that.e.find("#nationcel_left");
            that.rightDom = that.e.find('#nationcel_right');
            that.leftRewardDom = that.e.find('#nationcel_list_scroller');
            /* 填充元宝总数 */
            that.e.find('#nationcel_gold_num').html(res.all_gold);
            /* 填充左侧数据 */
            that.playerTopList(that.resData.country_list);
            that.leftReward(that.resData.member_list);
            /*填充右侧奖品以及数据*/
            that.fillRightData(that.resData.list);

            //填充身份
            var index = that.getNameColor(that.resData.name);
            this.leftDom.find('#nationcel_rank_name').html(that.rankName[index]).css('color', helper.getItemColor(that.getNameColor(that.resData.name)));

            if (index == that.rank.length) {
                index--;
            }

            var rank = parseInt(that.rank[index]);
            var myGold = parseInt(that.resData.my_gold);

            if (myGold > rank) {
                myGold = rank;
            }

            var next_rank = Math.floor(myGold / rank * 100);
            this.leftDom.find('#nationcel_next_rank').html(myGold + '/' + rank);
            this.leftDom.find('#nationcel_rank_bar').width(next_rank + '%');

            if (next_rank > 0 && next_rank < 100) {
                this.leftDom.find('#nationcel_rank_bar .light').show();
            } else {
                this.leftDom.find('#nationcel_rank_bar .light').hide();
            }

            that.pos = 0;
            that.bindAll();

            that.e.show();
            $('#worldloading').hide();
        },
        //玩家排名列表
        playerTopList: function(list) {
            var html = '';
            var that = this;

            //构建列表
            for (var i = 0; i < list.length; i++) {
                //判断奖励物资类型
                var gettype = list[i].type;
                var reward = list[i].num;
                var total_gold = helper.convertWan(list[i]["gold"]);
                //构建列表，判断是否是自己
                html += '<li uid="' + i + '" class="nationcel_user_list_li "><em class="top" style = "color: ' + helper.getItemColor(that.getNameColor(list[i].name)) + ';">' + list[i].name + '</em><em class="nickname" uid = "' + list[i].uid + '">' + list[i].nickname + '</em>';
                html += '<em class="reward rtype' + 1 + '" ></em>';
                html += '<em class="reward text">x' + total_gold + '</em>';
            }
            that.leftDom.find('#nationcelpop_list').html(html);

            if (that.rewardScroller1 == null) {
                that.rewardScroller1 = new iScroll('nationcel_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            that.isTimeout.push(setTimeout(function() {
                that.rewardScroller1.refresh();
            }, 200));
        },
        leftReward: function(list) {
            var html = '';
            var that = this;
            var num1 = 0;
            var num2 = 0;
            var flag1 = false;
            var flag2 = false;
            //构建列表
            for (var i = 0; i < list.length; i++) {
                if (that.getNameColor(list[i].name) == 0) {
                    continue;
                }
                html += '<li uid="' + i + '" fid="' + list[i].id + '" id="nationcelgift_l' + list[i].id + '" >';
                var color = helper.getItemColor(that.getNameColor(list[i].name));
                var name = list[i].name;
                html += '' + lang('nationcelpop_rank_name', [color, name]) + '';
                html += this.buildRewardHtml(list[i], 3);
                if (list[i].is_re == 1) {
                    html += ' <button class="btn pop_btn_small" >' + lang('receive') + '</button>';
                    if (flag1 == false) {
                        flag1 = true;
                        num1 = i;
                    }
                } else if (list[i].is_re == 0) {
                    html += ' <button class="btn pop_btn_small_disable" >' + lang('not_reached') + '</button>';
                    if (flag2 == false) {
                        flag2 = true;
                        num2 = i;
                    }
                } else {
                    html += ' <button class="btn pop_btn_small_disable" >' + lang('warena_have_fetch') + '</button>';
                }
                html += "</li>";
            }
            that.leftRewardDom.html(html);
            /* 创建旋转门 domid, 单页宽度 */
            that.myScroll = xGame.carousel('nationcelpop_carousel', 450);
            if (flag1) {
                that.myScroll.scrollTo(num1 * -300, 0);
            } else if (flag2) {
                that.myScroll.scrollTo(num2 * -300, 0);
            }
            if (flag1 == false && flag2 == false) {
                that.myScroll.scrollTo(6 * -300, 0);
            }


        },
        fillRightData: function(list) {
            var html = '';
            var that = this;
            //构建列表
            for (var i = 0; i < list.length; i++) {
                html += '<li uid="' + i + '">';
                if (list[i].is_re == 0) {
                    html += "<div class='not_reached'></div>";
                } else if (list[i].is_re == 1 || list[i].is_re == 2) {
                    html += "<div class='have_reached'></div>";
                }
                html += "<div class='title'>" + lang('nationcelpop_gift_level', [helper.convertWan(list[i].gold)]) + "</div>";
                html += this.buildRewardHtml(list[i], 5);
                html += "</li>";
            }
            that.rightDom.find('#nationcelpop_reward_list').html(html);
            if (that.rewardScroller2 == null) {
                that.rewardScroller2 = new iScroll('nationcel_reward_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            that.rewardScroller2.refresh();
            that.isTimeout.push(setTimeout(function() {}, 200));
        },
        getNameColor: function(name) {

            var quality = 0;

            for (var i = 0, len = this.rankName.length; i < len; i++) {
                if (name == this.rankName[i]) {
                    quality = i;
                    break;
                }
            }

            return quality;
        },

        buildRewardHtml: function(data, num) {
            var html = '';
            var gettype = 0;
            for (var i = 1; i <= num; i++) {
                gettype = parseInt(data['type' + i]);

                if (gettype == 8) { //装备
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" mid="' + i + '">';
                    html += '<span class="item" style="background-image: url(' + xGame.itemurl(data['info' + i].id) + ');"></span>';
                    html += '<b class=' + (data['info' + i].name.length == 5 ? '"indent"' : '""') + 'style="color: ' + helper.getItemColor(data['info' + i].quality) + '">' + data['info' + i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 12) { //武将
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" mid="' + i + '">';
                    html += '<span class="avatar" style="background-image: url(' + xGame.avatar(data['info' + i].image) + ');"></span>';
                    html += '<b style="color: ' + helper.getItemColor(data['info' + i].quality) + '">' + data['info' + i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 26) { //坐骑
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" mid="' + i + '">';
                    html += '<span  style="background-position:' + helper.getHorsePostion('small', data['info' + i].id) + ';"></span>';
                    html += '<b>x' + data['num' + i] + '</b>';
                    html += '</div>';
                } else if (gettype >= 17 && gettype <= 25) { //精华
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" mid="' + i + '">';
                    html += '<span  class="essence" style="background-image:url(' + xGame.essence(data['info' + i].id) + ');"></span>';
                    html += '<b>x' + data['num' + i] + '</b>';
                    html += '</div>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '" >';
                    html += '<span></span>';
                    html += '<b>x' + helper.convertWan(data['num' + i]) + '</b>';
                    html += '</div>';
                }
            }

            return html;
        },

        getReward: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var id = that.resData.my_rank.rank - 1;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.leftDom.find('#nationcel_reward_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                        that.showResultMsg(that.resData.rank_list[id], res.all_gold, res.all_silver);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                    'c': 'act_tanabata',
                    'm': 'rank_reward',
                },
                Func);
            Func = null;
        },

        bindAll: function() {
            var that = this;
            that.leftDom.undelegate('#nationcel_reward_btn.pop_btn_middle', 'touchend').delegate('#nationcel_reward_btn.pop_btn_middle', 'touchend',
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
            if (that.isEnd == 0) { //活动是否结束
                that.rightDom.undelegate('#nationcel_start', 'touchend').delegate('#nationcel_start', 'touchend',
                    function() {
                        if (xGame.checkLock(true) || $(this).hasClass('disable')) { //不设置属性
                            return false;
                        }
                        that.startLottery();
                    });
            }
            that.e.find('#nationcelpop_reward_list').undelegate('li div', 'click').delegate('li div', 'click', function(e) {
                if ($(this).hasClass('rtype12')) {
                    var index = ($(this).parent().attr('uid')); // 取得武将在所有奖励中的索引
                    var fid = ($(this).attr('mid')); // 取得奖励的索引
                    miscpop.showHeroInfo(that.resData.list[index]['info' + mid]);
                } else if ($(this).hasClass('rtype8')) {
                    var index = ($(this).parent().attr('uid')); // 取得装备在所有奖励中的索引
                    var fid = ($(this).attr('mid')); // 取得奖励的索引
                    miscpop.showItemInfo(that.resData.list[index]['info' + mid]);
                } else if ($(this).hasClass('rtype26')) {
                    var index = ($(this).parent().attr('uid')); // 取得奖励的索引
                    var fid = ($(this).attr('mid')); // 取得奖励的索引
                    miscpop.showMarchInfo(that.resData.list[index]['info' + mid]);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#nationcelpop_carousel').undelegate('li div', 'click').delegate('li div', 'click', function(e) {
                var index = ($(this).parent().attr('uid'));
                var mid = ($(this).attr('mid'));
                if ($(this).hasClass('rtype12')) {
                    miscpop.showHeroInfo(that.resData.member_list[index]['info' + mid]);
                } else if ($(this).hasClass('rtype8')) {
                    miscpop.showItemInfo(that.resData.member_list[index]['info' + mid]);
                } else if ($(this).hasClass('rtype26')) {
                    miscpop.showMarchInfo(that.resData.member_list[index]['info' + mid]);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#nationcel_right').undelegate('#nationcel_bird_click', 'touchend').delegate('#nationcel_bird_click', 'touchend', function(e) {
                miscpop.showMarchInfo(that.resData.mount);
                e.preventDefault();
                e.stopPropagation();
            });
            that.e.find('#nationcel_des_btn').unbind('touchend').bind('touchend', function() { //玩法说明显示
                that.e.find('#nationcel_info').show();
            });
            that.e.find('#nationcel_info_hidden, #nationcel_info .mask').unbind('touchend').bind('touchend', function() { //玩法说明隐藏
                that.e.find('#nationcel_info').hide();
            });
            that.e.find('#nationcel_list_scroller').undelegate('.pop_btn_small', 'click').delegate('.pop_btn_small', 'click', //领取奖励
                function() {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    that.receiveGift(parseInt($(this).parent().attr('uid')), parseInt($(this).parent().attr('fid')));
                    $(this).removeClass('pop_btn_small').addClass('pop_btn_small_disable').html(lang('warena_have_fetch'));
                });
        },
        receiveGift: function(uid, id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        if (uid != that.resData.member_list.length - 1) {
                            that.myScroll.scrollTo((uid + 1) * -300, 0, 300);
                        }
                        if (that.anim) {
                            that.hideAnim();
                        }
                        if (that.clearAnim) {
                            clearTimeout(that.clearAnim);
                        }

                        that.e.find('.valentine_reward_box').remove();

                        if (that.deleteThread) {
                            clearTimeout(that.deleteThread);
                        }

                        that.deleteThread = null;

                        player.updateinfo({
                            'gold': res.all_gold,
                            'act': res.all_act,
                            'silver': res.all_silver
                        });
                        var reward = that.resData.member_list;
                        for (var i = 0; i < reward.length; i++) {
                            if (reward[i].id == id) {
                                that.rewardDown(reward[i]);
                            }

                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                    'c': 'act_celebration',
                    'm': 'get_my_reward',
                    'reward_id': id
                },
                Func);
            Func = null;
        },

        rewardDown: function(data) {
            var html = '';
            var gettype = 0;
            html += '<div class="nationcel_reward_box">';


            for (var i = 1; i <= 3; i++) {
                gettype = parseInt(data['type' + i]);
                if (gettype == 8) { //装备
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-image: url(' + xGame.itemurl(data['info' + i].id) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data['info' + i].quality) + '">' + data['info' + i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 12) { //武将
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="avatar" style="background-image: url(' + xGame.avatar(data['info' + i].image) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data['info' + i].quality) + '">' + data['info' + i].name + '</b>';
                    html += '</span>';
                } else if (gettype >= 17 && gettype <= 25) { //精华
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em  class="essence" style="background-image:url(' + xGame.essence(data['info' + i].id) + ');"></em>';
                    html += '<b>x' + data['num' + i] + '</b>';
                    html += '</span>';
                } else if (gettype == 26) { //坐骑
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em  style="background-position:' + helper.getHorsePostion('small', data['info' + i].id) + ';"></em>';
                    html += '<b>x' + data['num' + i] + '</b>';
                    html += '</span>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '"><em></em>x' + data['num' + i] + '</span>';
                }
            }

            html += '</div>';
            this.e.find('#nationcelpop_wrap').append(html);

            this.deleteThread = setTimeout(function() {

                if (nationcelpop.e) {
                    nationcelpop.e.find('.nationcel_reward_box').remove();
                }

            }, 3000);

            html = null;
        },
        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.leftDom) {
                this.leftDom.undelegate('#nationcel_reward_btn.pop_btn_middle', 'touchend');
                this.leftDom.undelegate('.nickname', 'click');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('#nationcel_start', 'touchend');
                this.rightDom.remove();
                this.rightDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('nationcel', 0);

            if (this.rewardScroller1 != null) {
                this.rewardScroller1.destroy();
                this.rewardScroller1 = null;
            }
            if (this.rewardScroller2 != null) {
                this.rewardScroller2.destroy();
                this.rewardScroller2 = null;
            }
            if (this.nationcelScroll) {
                this.nationcelScroll.destroy();
                delete this.nationcelScroll;
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
