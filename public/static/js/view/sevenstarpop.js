/*七星灯*/
(function() {
    sevenstarPopClass = function() {
        this.e = null;
        this.headDom = null; // 主界面头部
        this.leftDom = null; // 主界面左部
        this.rightDom = null; // 主界面右部
        this.infoDom = null; // 玩法说明窗口
        this.ranklistDom = null; // 点灯排行dom
        this.isshow = false;
        this.pageData = null; // 主界面的data
        this.ranklistData = null; // 点灯排行data
        this.currIndex = 0; // 当前武将
        this.animover = true; // 移动是否结束
        this.currpoint = null; // 现在的鼠标在的位置
        this.ismoving = false; // 是否在移动
        this.ranklistScroller = null; // 排行列表data
        this.onerewardData = null; // 点一个灯奖励的类型
        this.sevenrewardData = null; // 点七个灯奖励的data
        this.canvaslist = []; // 存取单个点灯的canvas
        this.achieveTimer = null; // 点灯奖励掉落
        this.rewardTimer = null; // 领奖奖励掉落
        this.oneown = 0; // 第一个武将是否拥有
        this.twoown = 0; // 第二个武将是否拥有
        this.showTimer = null; // 掉落点亮七盏灯的延迟
    };

    sevenstarPopClass.prototype = {
        init: function() {
            var that = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.pageData = res;
                        that.fillData();
                        break;
                    default:
                        $('#worldloading').hide();
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            loadContents({
                'c': 'act_sevenstar',
                'm': 'index',
                'd': 'lc'
            },
            Func);
            Func = null;
        },

        //填充主窗口数据
        fillData: function() {
            this.isshow = true;
            this.e = xGame.loadPopTmpl('sevenstar');
            this.headDom = this.e.find('#sevenstar_head');
            this.leftDom = this.e.find('#sevenstar_left');
            this.rightDom = this.e.find('#sevenstar_right');
            this.infoDom = this.e.find('#sevenstar_info_dom');
            this.ranklistDom = this.e.find('#sevenstar_ranklist_dom');

            this.headDom.find('.acttime').text(this.pageData.date);
            this.headDom.find('.deitysoul').text(this.pageData.god_soul);
            this.headDom.find('.purplegem').text(this.pageData.get3);
            this.leftDom.find('.deitysoulnum').text(this.pageData.list[this.currIndex].price);
            this.checkCallupStatus(this.pageData.list[this.currIndex].info.own);
            this.oneown = this.pageData.list[0].info.own;
            this.twoown = this.pageData.list[1].info.own;

            if (parseInt(this.pageData.one) == 0) {
                this.rightDom.find('#sevenstar_lightupone').removeClass('pop_btn_middle').addClass('freelightupbtn').text(lang('sevenstar_freelightup'));
                this.rightDom.find('.one_lamp').text('');
            } else {
                this.rightDom.find('#sevenstar_lightupone').text(lang('sevenstar_lightupone'));
                this.rightDom.find('.one_lamp').text('x' + this.pageData.one);
            }
            this.rightDom.find('.seven_lamp').text('x' + this.pageData.all);

            if (!this.pageData.seven) {
                this.rightDom.find('#sevenstar_lightupall').removeClass('pop_btn_middle').addClass('disable');
            }

            for (var i = 0, len = this.pageData.list.length; i < len; i++) {

                var html = '';
                var hero = this.pageData.list[i].info;

                html += '<div class="heroimg" id="sevenstar_img' + i + '" uid="' + i + '" style="background-image:url(' + xGame.hero(hero.info.image, 1) + ')"></div>';

                this.leftDom.append(html);
            }

            for (var i = 0, len = this.pageData.site; i < len; i++) {
                var j = i + 1
                this.doAnimation3(j);
            };


            this.bindAll();

            $('#worldloading').hide();
        },

        //武将向左滑动
        slidetoleft: function() {
            var prev = next = 0;
            var that = this;

            if (this.pageData.list.length == 2 && this.currIndex == 1) {
                return;
            }

            if (!this.animover) {
                return;
            }

            this.animover = false;

            setTimeout(function() {
                that.animover = true;
            }, 1000);

            switch (this.currIndex) {
                case 0:
                    prev = 2;
                    next = 1;
                    break;
                case 1:
                    prev = 0;
                    next = 2;
                    break;
                case 2:
                    prev = 1;
                    next = 0;
                    break;
                default:
                    break;
            }

            var curr = this.leftDom.find('#sevenstar_img' + this.currIndex);
            xGame.doAnimate(curr, 'hero_RightToLeft1', '0.35s', function() {
                curr.css({
                    'opacity': 0.5,
                    'z-index': 1,
                    'left': '25px',
                    'top': '70px',
                    '-webkit-transform': 'scale(0.7)'
                });
            });
            var pre = this.leftDom.find('#sevenstar_img' + prev);
            xGame.doAnimate(pre, 'hero_LeftToRight3', '0.35s', function() {
                pre.css({
                    'opacity': 0.5,
                    'z-index': 1,
                    'left': '210px',
                    'top': '70px',
                    '-webkit-transform': 'scale(0.7)'
                });
            });

            var nex = this.leftDom.find('#sevenstar_img' + next);
            xGame.doAnimate(nex, 'hero_RightToLeft2', '0.35s', function() {
                nex.css({
                    'opacity': 1,
                    'z-index': 10,
                    'left': '100px',
                    'top': '35px',
                    '-webkit-transform': 'scale(1.2)'
                });
            });

            this.currIndex = next;

            if (next == 1) {
                this.checkCallupStatus(this.twoown);
            } else if (next == 0) {
                this.checkCallupStatus(this.oneown);
            }
            
            this.leftDom.find('.deitysoulnum').text(this.pageData.list[this.currIndex].price);


            this.leftDom.find('.scroller_btn_left').hide();
            this.leftDom.find('.scroller_btn_right').show();
        },

        //武将向右滑动
        slidetoright: function() {
            var prev = next = 0;
            var that = this;

            if (this.pageData.list.length == 2 && this.currIndex == 0) {
                return;
            }

            if (!this.animover) {
                return;
            }

            this.animover = false;

            setTimeout(function() {
                that.animover = true;
            }, 1000);

            switch (this.currIndex) {
                case 0:
                    prev = 1;
                    next = 2;
                    break;
                case 1:
                    prev = 2;
                    next = 0;
                    break;
                case 2:
                    prev = 0;
                    next = 1;
                    break;
                default:
                    break;
            }

            var curr = this.leftDom.find('#sevenstar_img' + this.currIndex);
            xGame.doAnimate(curr, 'hero_LeftToRight1', '0.35s', function() {
                curr.css({
                    'opacity': 0.5,
                    'z-index': 1,
                    'left': '170px',
                    'top': '70px',
                    '-webkit-transform': 'scale(0.7)'
                });
            });
            var pre = this.leftDom.find('#sevenstar_img' + prev);
            xGame.doAnimate(pre, 'hero_RightToLeft3', '0.35s', function() {
                pre.css({
                    'opacity': 0.5,
                    'z-index': 1,
                    'left': '100px',
                    'top': '70px',
                    '-webkit-transform': 'scale(0.7)'
                });
            });

            var nex = this.leftDom.find('#sevenstar_img' + next);
            xGame.doAnimate(nex, 'hero_LeftToRight2', '0.35s', function() {
                nex.css({
                    'opacity': 1,
                    'z-index': 10,
                    'left': '95px',
                    'top': '35px',
                    '-webkit-transform': 'scale(1.2)'
                });
            });

            this.currIndex = next;

            
            if (next == 1) {
                this.checkCallupStatus(this.twoown);
            } else if (next == 0) {
                this.checkCallupStatus(this.oneown);
            }
            this.leftDom.find('.deitysoulnum').text(this.pageData.list[this.currIndex].price)

            this.leftDom.find('.scroller_btn_left').show();
            this.leftDom.find('.scroller_btn_right').hide();
        },

        //判断召唤的状态
        checkCallupStatus: function(status) {
            var btn = this.leftDom.find('#sevenstar_callup');
            if (status) {
                btn.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                btn.text(lang('hero_had_buy'));
            } else {
                btn.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                btn.text(lang('sevenstar_callupGod'));
            }
        },

        //召唤神将
        calluphero: function() {
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        miscpop.newhero(res.info);
                        if (that.currIndex == 0) {
                            that.oneown = 1;
                        } else {
                            that.twoown = 1;
                        }
                        that.headDom.find('.deitysoul').text(res.god_soul);
                        that.checkCallupStatus(1);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'act_sevenstar',
                'm': 'call_godgeneral',
                'd': 'lc',
                'id': this.pageData.list[this.currIndex].info.info.id
            },
            Func);
            Func = null;
        },

        getRanklistInfo: function() {
            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.ranklistData = res;
                        that.fillranklistData();
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'act_sevenstar',
                'm': 'get_rank_list',
                'd': 'lc',
            },
            Func);
            Func = null;
        },

        //填充点灯排行的数据
        fillranklistData: function() {
            var list = this.ranklistData.list;
            var html = '';

            for (var i = 0, len = list.length; i < len; i++) {
                var item = list[i];
                var gettype1 = parseInt(item.type1);
                var gettype2 = parseInt(item.type2);

                if (i < 3) {
                    html += '<li class="rank_highitem ' + (item.uid == player.info.uid ? 'red' : '') + '"><span class="rank_status">' + item.rank + '</span><span class="rank_name" uid="' + item.uid + '">' + item.nickname + '</span><span class="rank_num">' + item.times + '</span>';
                    html += '<div class="rank_reward"><span class="rank_reward1  rtype' + gettype1 + '">x' + item.num1 + '</span>';
                    if (gettype2 == 31) {
                        html += '<div class="rank_reward2"><span class="reward rtype' + gettype2 + '"></span><em class="icon"></em></div></div></li>';
                    } else if (gettype2 == 26) { //
                        html += '<div class="rank_reward2"><span uid="' + i + '" class="reward rtype' + gettype2 + '" style="background-position:' + helper.getHorsePostion('small', item.value2.id) + ';"></span><b class="num">x' + item.num2 + '</b></div></div></li>';
                    } else if (gettype2 == 32) {
                        html += '<div class="rank_reward2"><span uid="' + i + '" class="reward rtype' + gettype2 + '" style="background-image:url(' + xGame.gem(item.value2.gem_type, item.value2.gem_quality) + ')"></span><b class="num">x' + item.num2 + '</b></div></div></li>';
                    } else {
                        html += '<div class="rank_reward2"><span class="reward rtype' + gettype2 + '"></span><b class="num">x' + item.num2 + '</b></div></div></li>';
                    }
                } else {
                    html += '<li class="rank_item ' + (item.uid == player.info.uid ? 'red' : '') + '"><span class="rank_status">' + list[i].rank + '</span><span class="rank_name" uid="' + item.uid + '">' + list[i].nickname + '</span><span class="rank_num">' + list[i].times + '</span>';
                    html += '<div class="rank_reward"><span class="rank_reward1 rtype' + gettype1 + '">x' + item.num1 + '</span><span class="rank_reward2 rtype' + gettype2 + '">x' + item.num2 + '</span></div></li>';
                }
            }

            this.ranklistDom.find('#sevenstar_ranklist').html(html);
            html = '';

            var rankRewardBtn = this.ranklistDom.find('#sevenstar_rank_rbtn');
            var isget = parseInt(this.ranklistData.isget);
            var cd = this.ranklistData.cd;
            var reward = this.ranklistData.own_reward;
            if (cd <= 0) {
                switch (isget) {
                    case 0:
                        rankRewardBtn.removeClass('pop_btn_middle pop_btn_middle_disable').addClass('pop_btn_middle_disable').html(lang('warena_fetch'));
                        break;
                    case 1:
                        rankRewardBtn.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
                        break;
                    case 2:
                        rankRewardBtn.removeClass('pop_btn_middle pop_btn_middle_disable').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
                        break;
                    default:
                        break;
                }
            } else {
                xGame.updateCd('sevenstar', cd);
            }

            if (reward.rank) {
                if (reward.rank > 3) {
                    html += '<span class="rank_reward1 rtype' + reward.type1 + '">x' + reward.num1 + '</span><span class="rank_reward2 rtype' + reward.type2 + '">x' + reward.num2 + '</span>';
                } else {
                    html += '<span class="rank_reward1  rtype' + reward.type1 + '">x' + reward.num1 + '</span>';
                    if (reward.type2 == 31) {
                        html += '<div class="rank_reward2 highrank_reward"><span class="reward rtype' + reward.type2 + '"></span><em class="icon"></em></div>';
                    } else if (reward.type2 == 32) {
                        html += '<div class="rank_reward2 highrank_reward"><span uid="-1" class="reward rtype' + reward.type2 + '" style="background-image:url(' + xGame.gem(reward.value2.gem_type, reward.value2.gem_quality) + ')";></span><b class="num">x' + reward.num2 + '</b></div>';
                    } else if (reward.type2 == 26) {
                        html += '<div class="rank_reward2 highrank_reward"><span class="reward rtype' + reward.type2 + '" style="background-position:' + helper.getHorsePostion('small', reward.value2.id) + ';"></span><b class="num">x' + reward.num2 + '</b></div></div></li>';
                    } else {
                        html += '<div class="rank_reward2"><span class="reward rtype' + reward.type2 + '"></span><b class="num">x' + reward.num2 + '</b></div>';
                    }
                }
                this.ranklistDom.find('#sevenstar_now_reward').removeClass('noget').html(html);
            } else {
                this.ranklistDom.find('#sevenstar_now_reward').addClass('noget').html(lang('beatengod_not_reach'));
            }
            html = null;

            if (this.ranklistScroller == null) {
                this.ranklistScroller = new iScroll('sevenstar_rank_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.ranklistScroller.refresh();
        },

        //获取点灯的数据
        getlightupinfo: function(num) {
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.updatelightedup(res);
                        if (num == 1) {
                            that.onerewardData = res;
                            if (res.site == 0) {
                                res.site = 7;
                            }
                            if (that.rightDom.find('#sevenstar_lightupone').hasClass('freelightupbtn')) {
                                that.rightDom.find('#sevenstar_lightupone').removeClass('freelightupbtn').addClass('pop_btn_middle').text(lang('sevenstar_lightupone'));
                            }
                            that.rightDom.find('.one_lamp').text('x' + that.onerewardData.one);
                            that.rightDom.find('.seven_lamp').text('x' + that.onerewardData.all);
                            that.doAnimation1(res.site, res.reset);
                        } else {
                            that.sevenrewardData = res;
                            if (!that.rightDom.find('#sevenstar_lightupone').hasClass('freelightupbtn')) {
                                that.rightDom.find('.one_lamp').text('x' + that.sevenrewardData.one);
                            }
                            that.rightDom.find('.seven_lamp').text('x' + that.sevenrewardData.all);     
                            that.doAnimation1(0, res.reset);
                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        xGame.unlock();
                        break;
                }

            };
            if (num == 1) {
                loadContents({
                    'c': 'act_sevenstar',
                    'm': 'action_light',
                    'd': 'lc',
                    'id': 1
                },
                Func);
            } else {
                loadContents({
                    'c': 'act_sevenstar',
                    'm': 'action_light',
                    'd': 'lc',
                    'id': 7
                },
                Func);
            }
            Func = null;
        },

        //获取领奖信息
        getrewardInfo: function() {
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.rewardDown(res);
                        that.ranklistDom.find('#sevenstar_rank_rbtn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'act_sevenstar',
                'm': 'get_rank_reward',
                'd': 'lc',
            },
            Func);
            Func = null;
        },

        //领奖
        rewardDown: function(data) {
            var that = this;
            var html = '<div class="reward_box" id="sevenstar_reward_box">';
            var item = null;
            var type = 0;
            item = data.reward;

            for (var i = 1; i < 3; i++) {
                type = parseInt(item['type' + i + '']);

                switch (type) {
                    case 2: // 银币
                    case 3: // 黄宝石
                    case 4: // 紫宝石
                    case 5: // 原魂
                    case 6: // 玉石
                    case 9: // 玉石
                    case 10: // 土匪
                    case 27: // 玫瑰花
                    case 13: // 钻石
                    case 28: //钻石
                        html += '<span class="reward_down pos' + i + ' type' + type + '"><em class="pic"></em>x' + item['num' + i + ''] + '</span>';
                        break;
                    case 26: // 马
                        html += '<span class="reward_down pos' + i + ' type' + type + '"><em class="pic" style="background-position:' + helper.getHorsePostion('buff', item.value2) + '"></em>x' + item['num' + i + ''] + '</span>';
                        break;
                    case 32: //宝石
                        html += '<span class="reward_down pos' + i + ' type' + type + '"><em class="pic" style="background-image: url(' + xGame.gem(item.value2.type, item.value2.quality) + ')"></em>x ' + item['num' + i + ''] + ' </span>';
                        break; //
                    default:
                        break;
                }
            }

            html += '</div>';

            if (that.ranklistDom.find('#sevenstar_reward_box').length > 0) {
                that.ranklistDom.find('#sevenstar_reward_box').remove();
            }

            if (that.rewardTimer) {
                clearTimeout(that.rewardTimer);
            }

            that.ranklistDom.append(html);

            that.rewardTimer = setTimeout(function() {

                if (that.ranklistDom) {
                    var dom = that.ranklistDom.find('#sevenstar_reward_box');

                    if (dom.length > 0) {
                        dom.remove();
                        that.rewardTimer = null;
                    }

                    dom = null;
                }
            }, 3000);

            html = null;
            item = null;
            type = null;
        },

        //展示一个灯的奖励
        showonelampreward: function() {
            var type = parseInt(this.onerewardData.reward[0].type);
            var rewardclass = this.getrewardStr(type);
            var html = "";

            if (type == 29) {
                html = '<span class="reward ' + rewardclass + '" style="background-image:url(' + xGame.forgeMaterial(this.onerewardData.reward[0].value) + ')">x' + this.onerewardData.reward[0].num + '</span>';
            } else {
                html = '<span class="reward ' + rewardclass + '">x' + this.onerewardData.reward[0].num + '</span>';
            }

            if (this.onerewardData.reward.length == 2) {
                var type2 = parseInt(this.onerewardData.reward[1].type);
                var rewardclass2 = this.getrewardStr(type2);
                html += '<span class="reward ' + rewardclass2 + '">x' + this.onerewardData.reward[1].num + '</span>'
            }
            xGame.toast.show(lang('get') + html);

            $('.toast_msg').css('top', '290px');
        },

        //展示七个灯的奖励
        showsevenlampreward: function() {
            var item = this.sevenrewardData.reward;
            var that = this;

            var rewardBox = that.e.find('.reward_box');

            if (rewardBox.length > 0) {
                rewardBox.remove();
            }

            var html = '';
            var type = 0;

            html += '<div class="reward_box">';

            for (var i = 0; i < item.length; i++) {
                type = parseInt(item[i].type);

                if (type == 8) { // 装备
                    html += '<span class="reward_down pos' + (i + 1) + ' type' + type + '"><em class="pic" style="background-image:url(' + xGame.itemurl(item[i]['num'].id) + ')"></em>' + item[i]['info'].name + '</span>';
                } else if (type == 26) { // 坐骑
                    html += '<span class="reward_down pos' + (i + 1) + ' type' + type + '"><em class="pic" style="background-position:' + helper.getHorsePostion('buff', item[i]['info'].id) + '"></em>' + item[i]['info'].name + '</span>';
                } else if (type == 12) { // 武将
                    html += '<span class="reward_down pos' + (i + 1) + ' type' + type + '"><em class="pic" style="background-image:url(' + xGame.avatar(item[i].value) + ')"></em>' + item[i]['info'].name + '</span>';
                } else if (type >= 17 && type <= 25) { // 精华
                    html += '<span class="reward_down pos' + (i + 1) + '" style="color:' + helper.getItemColor(item[i]['info'].quality) + '"><em class="pic reward essence" style="background-image:url(' + xGame.essence(item[i]['info'].id) + ')"></em>x' + item[i]['info'].num + '</span>';
                } else if (type == 29) { // 精华
                    html += '<span class="reward_down pos' + (i + 1) + '" ><em class="img ' + that.getrewardStr(type) + '" style="background-image:url(' + xGame.forgeMaterial(item[i].value) + ')"></em>x' + item[i].num + '</span>';
                } else {
                    html += '<span class="reward_down pos' + (i + 1) + ' type' + type + '"><em class="img ' + that.getrewardStr(type) + '">x' + item[i]['num'] + '</em></span>';
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

        //获取奖励的str
        getrewardStr: function(type) {
            var str = '';

            switch (type) {
                case 1: //元宝
                    str = 'add_gold';
                    break;
                case 2: //银币
                    str = 'add_silver';
                    break;
                case 3: //黄宝石
                    str = 'add_get2';
                    break;
                case 4: //紫宝石
                    str = 'add_get3';
                    break;
                case 5: //原魂
                    str = 'add_soul';
                    break;
                case 6: //水晶
                    str = 'add_jade';
                    break;
                case 7: //声望
                    str = 'add_prestige';
                    break;
                case 9: //玉石碎片
                    str = 'add_gem_part';
                    break;
                case 10: //突飞卡
                    str = 'add_tfcard';
                    break;
                case 11: //蓝宝石
                    str = 'add_get1';
                    break;
                case 13: //军令
                    str = 'add_junling';
                    break;
                case 14: //精炼石
                    str = 'add_refinestone';
                    break;
                case 15: //五彩石
                    str = 'add_colorstone';
                    break;
                case 16: //上古血玉
                    str = 'add_bloodstone';
                    break;
                case 17:
                case 18:
                case 19:
                case 20:
                case 21:
                case 22:
                case 23:
                case 24:
                case 25:
                case 26:
                    str = 'essence';
                    break;
                case 27:
                    str = 'roseicon';
                    break;
                case 28:
                    str = 'diamondicon';
                    break;
                case 29:
                    str = 'forgeMaterial';
                    break;
                case 30:
                    str = 'deitysoul';
                    break;
                default:
                    break;
            }

            return str;
        },

        //更新点灯后的数据状态
        updatelightedup: function(res) {
            this.rightDom.find('#sevenstar_lightupall').removeClass('disable').addClass('pop_btn_middle');

            if (!res.seven) {
                this.rightDom.find('#sevenstar_lightupall').removeClass('pop_btn_middle').addClass('disable');
            }
            this.headDom.find(".purplegem").text(res.get3);
            this.headDom.find('.deitysoul').text(res.god_soul);
        },

        //特效
        doAnimation1: function(id, reset) {
            var that = this;

            var posList = [{
                'left': 85,
                'top': 130
            }, {
                'left': 250,
                'top': 144
            }, {
                'left': 363,
                'top': 89
            }, {
                'left': 280,
                'top': 15
            }, {
                'left': 94,
                'top': 0
            }, {
                'left': -5,
                'top': 62
            }, {
                'left': 181,
                'top': 31
            }];
            var posData = posList;
            if (id != 0) {
                posData = [posList[id - 1]];
            }

            var mc = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {
                    left: 0,
                    top: 0,
                    width: that.rightDom.find('#sevenstar_sevenlamp').width(),
                    height: that.rightDom.find('#sevenstar_sevenlamp').height()
                },
                img: staticUrl + 'img/pop/sevenstarpop_aniamtion1.png',
                'imgOffset': posData,
                'width': 110,
                'height': 110,
                'scale': 1,
                'loop': false,
                'endDestroy': true,
                'callback': function() { //回调函数
                },
                "anim": [{
                    rect: [0, 0],
                    interval: 100
                }, {
                    rect: [110, 0],
                    interval: 100
                }, {
                    rect: [220, 0],
                    interval: 100
                }, {
                    rect: [330, 0],
                    interval: 100
                }, {
                    rect: [0, 110],
                    interval: 100
                }, {
                    rect: [110, 110],
                    interval: 100,
                    callback: function() {
                        if (id == 0) {
                            that.rightDom.find('.toplamp').show();
                        } else {
                            that.rightDom.find('.lamp' + id + '').find('.toplamp').show();
                        }

                        that.doAnimation2(id, reset);
                    },
                }, {
                    rect: [220, 110],
                    interval: 100
                }, {
                    rect: [330, 110],
                    interval: 100
                }, ]
            });

            mc.appendTo(that.rightDom.find('#sevenstar_sevenlamp'));
            mc.play();
            mc = null;
        },

        doAnimation2: function(id, reset) {
            var that = this;
            var isloop = false;

            var posList = [{
                'left': 85,
                'top': 137
            }, {
                'left': 250,
                'top': 151
            }, {
                'left': 363,
                'top': 96
            }, {
                'left': 280,
                'top': 22
            }, {
                'left': 94,
                'top': 7
            }, {
                'left': -5,
                'top': 69
            }, {
                'left': 181,
                'top': 38
            }];
            var posData = posList;
            if (id != 0) {
                posData = [posList[id - 1]];
                isloop = true;
            }

            if (id == 7 && reset) {
                isloop = false;
            }

            var mc = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {
                    left: 0,
                    top: 0,
                    width: that.rightDom.find('#sevenstar_sevenlamp').width(),
                    height: that.rightDom.find('#sevenstar_sevenlamp').width()
                },
                'img': staticUrl + 'img/pop/sevenstarpop_aniamtion2.png',
                'imgOffset': posData,
                'width': 110,
                'height': 110,
                'scale': 1,
                'loop': isloop,
                'endDestroy': true,
                'callback': function() { //回调函数
                    if (id == 0) {
                        that.rightDom.find('.toplamp').hide();
                        that.showTimer = setTimeout(function() {
                            xGame.unlock();
                        }, 500);
                    }
                    if (reset && id == 7) {
                        that.rightDom.find('.toplamp').hide();
                        xGame.unlock();
                        for (var i = 0, len = that.canvaslist.length; i < len; i++) {
                            that.canvaslist[i].stop();
                            that.canvaslist[i].destroy();
                        };
                    }
                },
                'anim': [{
                    rect: [0, 0],
                    interval: 100
                }, {
                    rect: [110, 0],
                    interval: 100
                }, {
                    rect: [220, 0],
                    interval: 100
                }, {
                    rect: [330, 0],
                    interval: 100
                }, {
                    rect: [0, 110],
                    interval: 100
                }, {
                    rect: [110, 110],
                    interval: 100
                }, {
                    rect: [220, 110],
                    interval: 100
                }, {
                    rect: [330, 110],
                    interval: 100
                }, {
                    rect: [0, 220],
                    interval: 100
                }, {
                    rect: [110, 220],
                    interval: 100
                }, {
                    rect: [220, 220],
                    interval: 100
                }, {
                    rect: [330, 220],
                    interval: 100
                }, {
                    rect: [0, 330],
                    interval: 100
                }, {
                    rect: [110, 330],
                    interval: 100
                }, {
                    rect: [220, 330],
                    interval: 100
                }, {
                    rect: [330, 330],
                    interval: 100,
                }]
            });

            mc.appendTo(that.rightDom.find('#sevenstar_sevenlamp'));
            if (id != 0 && id != 7) {
                this.canvaslist.push(mc);
            }
            mc.play();
            if (id == 0) {
                that.showsevenlampreward();
            } else {
                that.showonelampreward();
                if (id != 7) {
                    xGame.unlock();
                }
            }
            mc = null;
        },

        doAnimation3: function(id) {
            var that = this;

            that.rightDom.find('.lamp' + id + '').find('.toplamp').show();

            var posList = [{
                'left': 85,
                'top': 137
            }, {
                'left': 250,
                'top': 151
            }, {
                'left': 363,
                'top': 96
            }, {
                'left': 280,
                'top': 22
            }, {
                'left': 94,
                'top': 7
            }, {
                'left': -5,
                'top': 69
            }, {
                'left': 181,
                'top': 38
            }];
            var posData = posList;
            posData = [posList[id - 1]];

            var mc = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {
                    left: 0,
                    top: 0,
                    width: that.rightDom.find('#sevenstar_sevenlamp').width(),
                    height: that.rightDom.find('#sevenstar_sevenlamp').width()
                },
                'img': staticUrl + 'img/pop/sevenstarpop_aniamtion2.png',
                'imgOffset': posData,
                'width': 110,
                'height': 110,
                'scale': 1,
                'loop': true,
                'endDestroy': true,
                'callback': function() { //回调函数

                },
                'anim': [{
                    rect: [0, 0],
                    interval: 100
                }, {
                    rect: [110, 0],
                    interval: 100
                }, {
                    rect: [220, 0],
                    interval: 100
                }, {
                    rect: [330, 0],
                    interval: 100
                }, {
                    rect: [0, 110],
                    interval: 100
                }, {
                    rect: [110, 110],
                    interval: 100
                }, {
                    rect: [220, 110],
                    interval: 100
                }, {
                    rect: [330, 110],
                    interval: 100
                }, {
                    rect: [0, 220],
                    interval: 100
                }, {
                    rect: [110, 220],
                    interval: 100
                }, {
                    rect: [220, 220],
                    interval: 100
                }, {
                    rect: [330, 220],
                    interval: 100
                }, {
                    rect: [0, 330],
                    interval: 100
                }, {
                    rect: [110, 330],
                    interval: 100
                }, {
                    rect: [220, 330],
                    interval: 100
                }, {
                    rect: [330, 330],
                    interval: 100,
                }]
            });

            mc.appendTo(that.rightDom.find('#sevenstar_sevenlamp'));
            if (id != 0 && id != 7) {
                this.canvaslist.push(mc);
            }
            mc.play();
            mc = null;
        },
        
        //在cd为零时更改
        cdEnd: function() {
            if (this.ranklistData) {
                var rankRewardBtn = this.ranklistDom.find('#sevenstar_rank_rbtn');
                rankRewardBtn.removeClass('pop_btn_middle_disable').addClass('pop_btn_middle').html(lang('warena_fetch'));
            }
        },


        bindAll: function() {
            var that = this;

            //主界面关闭按钮
            this.e.find('#sevenstar_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            //玩法说明
            this.leftDom.find('.info_btn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.show();
                e.preventDefault();
            });

            //将开始移动
            this.e.undelegate('.heroimg', 'touchstart').delegate('.heroimg', 'touchstart', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (e.touches.length == 1) {
                    that.ismoving = false;
                    var touch = e.touches[0];
                    that.currpoint = touch.pageX;
                }
                e.preventDefault();
            });

            //将移动
            this.e.undelegate('.heroimg', 'touchmove').delegate('.heroimg', 'touchmove', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (e.touches.length == 1) {
                    var id = $(this).attr('uid');
                    var touch = e.touches[0];
                    var offset = touch.pageX - that.currpoint;
                    if (offset > 5) { //向右划
                        that.ismoving = true;
                        that.slidetoright();
                    } else if (offset < -5) { //向左划
                        that.ismoving = true;
                        that.slidetoleft();
                    }
                }
                e.preventDefault();
            });

            //弹出将的信息
            this.leftDom.undelegate('.heroimg', 'touchend').delegate('.heroimg', 'touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (!that.ismoving) {
                    var id = $(this).attr('uid');
                    miscpop.showHeroInfo(that.pageData.list[id].info.info);
                }
                e.preventDefault();
            });

            //向左翻
            this.leftDom.find('.scroller_btn_left').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.slidetoleft();
                e.preventDefault();
            });

            //向右翻
            this.leftDom.find('.scroller_btn_right').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.slidetoright();
                e.preventDefault();
            });

            //召唤神将
            this.leftDom.find('#sevenstar_callup').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).hasClass('pop_btn_middle_disable')) {
                    return false;
                }

                xGame.confirm.show(lang('sevenstar_callupGod'), lang('sevenstar_callup_ask', [that.pageData.list[that.currIndex].price, that.pageData.list[that.currIndex].info.info.name]), 2, function() {
                    xGame.confirm.hide();
                    that.calluphero();
                }, function() {
                    xGame.confirm.hide();
                }, lang('buy'), lang('close'));

                e.preventDefault();
            });

            //点灯排行
            this.leftDom.find('.lightup_rank').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.getRanklistInfo();
                that.ranklistDom.show();
            });

            //点灯排行弹出马的信息
            this.ranklistDom.find('#sevenstar_ranklist').undelegate('.rtype26', 'click').delegate('.rtype26', 'click', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var index = parseInt($(this).attr('uid'));
                miscpop.showMarchInfo(that.ranklistData.list[index].value2);
            })

            //获得奖励弹出马的信息
            this.ranklistDom.find('.reward_info').undelegate('.rtype26', 'click').delegate('.rtype26', 'click', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                miscpop.showMarchInfo(that.ranklistData.own_reward.value2);
            })

            //点一个灯
            this.rightDom.find('#sevenstar_lightupone').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.getlightupinfo(1);
            });

            //点七个灯
            this.rightDom.find('#sevenstar_lightupall').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                if ($(this).hasClass('disable')) {
                    xGame.unlock();
                    return false;
                }
                that.getlightupinfo(7);
            });

            //关闭玩法说明按钮
            this.infoDom.find('#sevenstart_info_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.infoDom.hide();
                e.preventDefault();
            });

            //关闭玩法说明mask
            this.infoDom.find('.mask').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.infoDom.hide();
                e.preventDefault();
            });

            //点灯排行关闭按钮
            this.ranklistDom.find('#sevenstart_ranklist_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.ranklistDom.hide();
                if (that.ranklistScroller) {
                    that.ranklistScroller.destroy();
                    that.ranklistScroller = null;
                }
                e.preventDefault();
            });

            //弹出武将
            this.ranklistDom.undelegate('.rank_name', 'click').delegate('.rank_name', 'click', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });

            //弹出宝石信息
            this.ranklistDom.undelegate('.rtype32', 'click').delegate('.rtype32', 'click', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var uid = parseInt($(this).attr('uid'));
                if (uid < 0) {
                    miscpop.showGemInfo(that.ranklistData.own_reward.value2);
                } else {
                    miscpop.showGemInfo(that.ranklistData.list[uid].value2);
                }

            });

            //领奖
            this.ranklistDom.find('#sevenstar_rank_rbtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).hasClass('pop_btn_middle_disable')) {
                    return false;
                }
                that.getrewardInfo();
            });

        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#sevenstar_hidebtn').unbind('touchend');
                this.e.undelegate('.heroimg', 'touchstart');
                this.e.undelegate('.heroimg', 'touchmove');
                this.e.remove();
                this.e = null;
            }

            if (this.leftDom) {
                this.leftDom.find('.info_btn').unbind('touchend');
                this.leftDom.undelegate('.heroimg', 'touchend');
                this.leftDom.find('.scroller_btn_left');
                this.leftDom.find('.scroller_btn_right').unbind('touchend');
                this.leftDom.find('#sevenstar_callup').unbind('touchend');
                this.leftDom.find('.lightup_rank').unbind('touchend');
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.find('#sevenstar_lightupone').unbind('touchend');
                this.rightDom.find('#sevenstar_lightupall').unbind('touchend');
                this.rightDom = null;
            }
            if (this.infoDom) {
                this.infoDom.find('#sevenstart_info_hidebtn').unbind('touchend');
                this.infoDom.find('.mask').unbind('touchend');
                this.infoDom = null;
            }
            if (this.ranklistDom) {
                this.ranklistDom.find('#sevenstar_ranklist').undelegate('.rtype26', 'click');
                this.ranklistDom.find('.reward_info').undelegate('.rtype26', 'click');
                this.ranklistDom.find('#sevenstart_ranklist_hidebtn').unbind('touchend');
                this.ranklistDom.undelegate('.rank_name', 'click');
                this.ranklistDom.undelegate('.rtype32', 'click');
                this.ranklistDom.find('.sevenstar_rank_rbtn').unbind('touchend');
                this.ranklistDom = null;
            }


        },

        hide: function() {

            this.isshow = false;
            xGame.updateCd('sevenstar', 0);

            if (this.ranklistScroller) {
                this.ranklistScroller.destroy();
                this.ranklistScroller = null;
            }
            this.isshow = false;
            this.pageData = null;
            this.ranklistData = null;
            this.currIndex = 0;
            this.animover = true;
            this.currpoint = null;
            this.ismoving = false;
            this.ranklistScroller = null;
            this.onerewardData = null;
            this.sevenrewardData = null;
            this.canvaslist = [];
            this.oneown = 0;
            this.twoown = 0;
            if (this.achieveTimer) {
                clearTimeout(this.achieveTimer);
                this.achieveTimer = null;
            }
            if (this.rewardTimer) {
                clearTimeout(this.rewardTimer);
                this.rewardTimer = null;
            }
            if (this.showTimer) {
                clearTimeout(this.showTimer);
                this.showTimer = null;
            }
            this.headDom = null;
            this.unbindAll();
        }
    }

})();