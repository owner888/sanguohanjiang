// 平乱副本
(function() {
    pacifyPopClass = function() {
        this.isshow = false;
        this.e = null;
        this.threeMilitaryDom = null; //副本入口界面
        this.militaryDom = null; //副本怪列表
        this.beastDom = null; //怪的信息显示框
        this.threeMilitaryData = null; //副本入口界面的后台返回数据
        this.beastsData = null; //怪的列表
        this.theBeastData = null; //被点击的怪的信息
        this.rushRewardData = null; //扫荡返回
        this.rushData = null; //获取扫荡次数，及其对应的价格
        this.nowMilitary = null; //当前副本
        this.nowDifficulty = null; //当前难度
        this.nowBeastId = null; //当前怪物id
        this.mapScroller = null; //怪物列表的scroll
        this.beastScrolle = null; //有概率的获奖信息
        this.maptime = null;
        this.beastTime = null;
        this.nowfirst_reward = null; //首杀奖励
        this.nowprobable_reward = null; //有概率获取奖励
        this.nowBeastname = null;  //现在怪物名字
        this.one_sub = 0; //分别扫荡次数
        this.five_sub = 0;
        this.ten_sub = 0;
        this.nowtimes = null; //当前扫荡次数
        this.canBuy = 0; //剩余购买次数
        this.free_times = 0; //剩余攻打次数
        this.buyTimesData = null; //买次数的数据
        this.price = null;
        this.markDifficulty = null;
        this.fadeTime = null;
        this.firstBossKill = null;
    };
    pacifyPopClass.prototype = {
        init : function(data) {
            var that = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var response = function(res) {

                switch(parseInt(res.status)) {
                    case 1:
                        that.threeMilitaryData = res;
                        that.fillData(data);
                        break;
                    default:
                        $('#worldloading').hide();
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            //这个是base的一个关于Ajax的对象
            loadContents({
                'c' : 'copies',
                'm' : 'index',
                'd' : 'newequip'
            }, response);
            response = null;
        },

        fillData : function(data) {
            this.isshow = true;
            this.e = xGame.loadPopTmpl('pacify');
            this.threeMilitaryDom = this.e.find("#three_military_dom");
            this.militaryDom = this.e.find('#pacify_military_dom');
            this.beastDom = this.e.find('#beast_info_dom');
            this.fillMilitaryData();
            this.bindAll();
            $('#worldloading').hide();

            if (data && data.callback) {
                this.threeMilitaryDom.hide();
                data.callback();
            } else {
                this.threeMilitaryDom.show();
            }
        },

        fillMilitaryData : function() {
            var datas = this.threeMilitaryData.list;
            var html = '';

            for (var i = 0, len = datas.length; i < len; i++) {
                var data = datas[i].id;
                if (datas[i].succ) {
                    html += '<li class="threeMilitary_item military' + data + '" bid="' + data + '"><span class="click_enter">' + lang('click_enter') + '</span><span class="pass" ></span></li>';
                } else {
                    html += '<li class="threeMilitary_item military' + data + '" bid="' + data + '"><span class="click_enter">' + lang('click_enter') + '</span></li>';
                }
            }

            this.threeMilitaryDom.find('.three_military_list').html(html);
            html = null;
        },

        fillBeastsData : function(id, difficulty) {
            this.nowMilitary = id;

            if (parseInt(id) == 1) {
                this.militaryDom.find('#beast_way_scroller').attr('class', 'beast_way_scroller bg1');
            } else if (parseInt(id) == 2) {
                this.militaryDom.find('#beast_way_scroller').attr('class', 'beast_way_scroller bg2');
            } else if (parseInt(id) == 3) {
                this.militaryDom.find('#beast_way_scroller').attr('class', 'beast_way_scroller bg3');
            }

            if (this.militaryDom.find('#pacify_menu_control').hasClass('control_open')) {
                this.militaryDom.find('#pacify_menu_control').removeClass('control_open').addClass('control_close');
                this.militaryDom.find('.pacify_difficulty_list').show();
                this.militaryDom.find('.pacify_common_list').show();
            }

            this.checkdifficulty(difficulty);
            this.militaryDom.show();
        },

        //获取怪物列表的信息，difficulty:选择的副本难度，1-3
        getBeastInfo : function(id, difficulty) {
            var that = this;
            var response = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        ;
                        that.beastsData = res;
                        that.fillBeastsData(id, difficulty);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };

            loadContents({
                'c' : 'copies',
                'm' : 'get_monster_list',
                'd' : 'newequip',
                'id' : id
            }, response);
            response = null;
        },

        // difficulty:选择的副本难度，1-3
        checkdifficulty : function(difficulty) {
            var datas = this.beastsData.list;
            var k = 0;
            var len = 0;
            for (var i = 0, len = datas.length; i < len; i++) {
                if (datas[i].status == 1) {
                    k++;
                }
            }

            if (k == 1) {
                this.militaryDom.find('#pacify_difficulty_hard').addClass('unreached');
                this.militaryDom.find('#pacify_difficulty_hero').addClass('unreached');
            } else if (k == 2) {
                this.militaryDom.find('#pacify_difficulty_simple').addClass('unchoice');
                this.militaryDom.find('#pacify_difficulty_hero').addClass('unreached');
            } else if (k == 3) {
                this.militaryDom.find('#pacify_difficulty_simple').addClass('unchoice');
                this.militaryDom.find('#pacify_difficulty_hard').addClass('unchoice');
            }

            if (difficulty != null && k >= difficulty) {

                this.militaryDom.find('.item_btn').each(function (i) {
                    var $this = $(this);

                    if (i < k) {
                        if (i == difficulty - 1) {
                            $this.removeClass('unchoice');
                        } else {
                            $this.addClass('unchoice');
                        }
                    }
                });

                k = difficulty;
            }

            this.nowDifficulty = k;
            this.buildBeastWay(datas[k - 1].list);
        },

        //构建怪物列表
        buildBeastWay : function(data) {
            if (xGame.checkLock()) {
                return false;
            }
            var html = '';
            var that = this;
            var mark = 0;
            var pass_type = 0;
            var name = '';
            var len = 0;

            for (var i = 0, len = data.length; i < len; i++) {
                var infor = data[i];
                if (infor.pass_type == 2) {
                    pass_type = 1;
                } else {
                    pass_type = infor.pass_type;
                }
                var beast_class = 'beast_' + infor.copies_id + '_' + infor.killed + '_' + pass_type;

                var show = parseInt(infor.openstatus);
                if (show) {
                    show = "show";
                }
                if (show && !parseInt(infor.killed)) {
                    mark = infor.monster_id;
                }
                if (infor.pass_type != 1) {
                    name = infor.name;
                } else {
                    name = "";
                }
                html += '<li id="beast_' + infor.monster_id + '" class="beast_div ' + beast_class + ' ' + show + ' " bid="' + infor.monster_id + '" style="left:' + infor.x + 'px;top:' + infor.y + 'px;"><span>' + name + '</span></li>';
            }

            this.militaryDom.find('#pacify_beasts_way').find('#beast_way_scroller').html(html);
            html = null;

            if (mark) {
                this.militaryDom.find('#pacify_beasts_way').find('#beast_' + mark).append('<span class="lastenemy_span"></span>');
            }

            this.militaryDom.find('.pacify_act').find('span').text(this.beastsData.info.act + '/' + player.info.act_limit);

            var num = this.getWan(this.beastsData.info.gold);

            this.militaryDom.find('.pacify_gold').find('span').text(num);

            if (this.mapScroller == null) {
                this.mapScroller = new iScroll('pacify_beasts_way', {
                    bounce : false,
                    momentum : false,
                    hScrollbar : false,
                    vScrollbar : false
                });
            }
            this.maptime = setTimeout(function() {
                that.mapScroller.refresh();
            }, 200);

            if (this.markDifficulty != this.nowDifficulty) {
                this.showPass();
            } else {
                if (this.markDifficulty == 3 && that.firstBossKill) {
                    that.e.find('#pacify_hard_move').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                        that.e.find('#pacify_hard_move').css('display', 'none');
                        that.firstBossKill = null;
                    });

                }

                this.militaryDom.find('.show').fadeIn('3');
            }
            this.fadeTime = setTimeout(function() {
                xGame.unlock();
            }, 100);
        },

        showPass : function() {
            var that = this;
            if (this.markDifficulty == 1 && that.firstBossKill) {
                that.e.find('#pacify_common_move').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                    that.e.find('#pacify_common_move').css('display', 'none');
                    that.createMilitaryName();
                    that.markDifficulty = this.nowDifficulty;
                    that.militaryDom.find('.show').fadeIn('50');
                });

            } else if (this.markDifficulty == 2 && that.firstBossKill) {
                that.e.find('#pacify_simple_move').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                    that.e.find('#pacify_simple_move').css('display', 'none');
                    that.createMilitaryName();
                    that.markDifficulty = this.nowDifficulty;
                    that.militaryDom.find('.show').fadeIn('50');
                });
            } else if (this.markDifficulty == 3 && that.firstBossKill) {
                that.e.find('#pacify_hard_move').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function() {
                    that.e.find('#pacify_hard_move').css('display', 'none');
                    that.createMilitaryName();
                    that.markDifficulty = this.nowDifficulty;
                    that.militaryDom.find('.show').fadeIn('50');
                });
            } else {
                that.createMilitaryName();
                that.markDifficulty = this.nowDifficulty;
                that.militaryDom.find('.show').fadeIn('50');
            }
            that.firstBossKill = null;
        },

        createMilitaryName : function() {
            if (this.nowDifficulty == 1) {
                var html = '<div class="pacify_military_name">' + this.threeMilitaryData.list[parseInt(this.nowMilitary) - 1].name + '——' + lang('materialinfopop_diff1') + '</div>';
            } else if (this.nowDifficulty == 2) {
                var html = '<div class="pacify_military_name">' + this.threeMilitaryData.list[parseInt(this.nowMilitary) - 1].name + '——' + lang('materialinfopop_diff2') + '</div>';
            } else if (this.nowDifficulty == 3) {
                var html = '<div class="pacify_military_name">' + this.threeMilitaryData.list[parseInt(this.nowMilitary) - 1].name + '——' + lang('materialinfopop_diff3') + '</div>';
            }
            this.militaryDom.append(html);
        },

        //显示某个怪的信息
        showBeastInfo : function() {
            var that = this;
            var response = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        that.theBeastData = res;
                        that.fillBeastinfo();
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            //这个是base的一个关于Ajax的对象
            loadContents({
                'c' : 'copies',
                'm' : 'get_monster_info',
                'd' : 'newequip',
                'id' : that.nowMilitary,
                'diff_id' : that.nowDifficulty,
                'monster_id' : that.nowBeastId
            }, response);
            response = null;
        },

        getWan : function(num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        //清除
        removeDifficulty : function() {
            var that = this;
            that.militaryDom.find('#pacify_difficulty_simple').removeClass('unchoice');
            that.militaryDom.find('#pacify_difficulty_hard').removeClass('unchoice unreached');
            that.militaryDom.find('#pacify_difficulty_hero').removeClass('unchoice unreached');
        },

        //详细构造某个怪的信息
        fillBeastinfo : function() {
            var that = this;
            var datas = this.theBeastData.info;
            this.nowfirst_reward = datas.first_reward;
            this.nowprobable_reward = datas.reward;
            this.nowBeastname = datas.name;
            this.free_times = datas.free_times;
            this.canBuy = datas.vip_times;
            this.price = datas.price;
            var rewards = datas.reward;
            var imgid = datas.image;
            var pass_type = parseInt(datas.pass_type);
            var info_box = this.beastDom.find('.info_box');
            var beast_box = this.beastDom.find('.beast_box');
            this.beastDom.show();
            info_box.find('.herolv span').text(datas.name + '(Lv.' + datas.monster_level + ')');
            info_box.find('.lefttimes').text(lang('pacify_left_times', [datas.free_times]));
            info_box.find('.reputation em').text(lang('reputation') + '：');
            info_box.find('.reputation span').text('+' + datas.reputation);
            this.beastDom.find('#pacify_gl_btn').text(lang('raiders'));
            this.beastDom.find('#pacify_attack_btn').text(lang('attack'));
            this.beastDom.find('#pacify_rush_btn').text(lang('pacify_sweep'));
            beast_box.find('.firstkilled').show();
            beast_box.find('.beast_killed').show();
            beast_box.find('#pacify_rush_btn').show();
            this.beastDom.find('.bg').css('background-image', 'url(' + staticUrl + 'img/pop/pacify_beast_' + imgid + '.png)');

            var first_kill = parseInt(datas.first_kill);
            var mop_up = parseInt(datas.mop_up);
            if (!first_kill) {
                info_box.find('.firstkilled').hide();
            } else {
                beast_box.find('.beast_killed').hide();
            }
            if (!mop_up) {
                beast_box.find('#pacify_rush_btn').hide();
            }

            beast_box.find('#beast_flag').removeClass();
            if (pass_type == 1) {
                beast_box.find('#beast_flag').addClass('flag f1');
            } else if (pass_type == 2) {
                beast_box.find('#beast_flag').addClass('flag f2');
            } else if (pass_type == 3 || pass_type == 4 || pass_type == 5) {
                beast_box.find('#beast_flag').addClass('flag f3');
            }

            var html = '';
            var len = 0;

            var item = datas.first_reward;
            if (item) {
                var type = item.type;
                if (type == 3) {
                    html += '<li style="background-image:url(' + xGame.forgeMaterial(item.materials_id) + ')"><span style="color:' + helper.getItemColor(item.quality) + '">' + item.name + 'x' + item.value + '</span><em>（' + item.rate_info + '）</em></li>';
                }
                if (type == 1 || type == 2) {
                    html += '<li style="background-image:url(' + xGame.itemurl(item.id) + ')"><span style="color:' + helper.getItemColor(item.quality) + '">' + item.name + 'x' + item.value + '</span><em>（' + item.rate_info + '）</em></li>';
                }
            }

            for (var i = 0, len = rewards.length; i < len; i++) {
                var reward = rewards[i];
                html += '<li style="background-image:url(' + xGame.forgeMaterial(reward.materials_id) + ')"><span style="color:' + helper.getItemColor(reward.quality) + '">' + reward.name + '</span><em>（' + reward.rate_info + '）</em></li>';
            }

            this.beastDom.find('.equip_box').find('#pacify_probable_list').html(html);
            html = null;

            if (this.beastScroller == null) {
                this.beastScroller = new iScroll('pacify_probable_scroll', {
                    bounce : false,
                    momentum : false,
                    hScrollbar : false,
                    vScrollbar : false
                });
            }
            this.beastTime = setTimeout(function() {
                that.beastScroller.refresh();
            }, 600);

            html = null;
        },

        //购买次数确认
        showBuyConfirm : function(msg) {
            var that = this;
            xGame.confirm.show(msg, lang('pacify_buy_times_ask', [that.price, that.free_times, that.canBuy]), 2, function() {
                that.buyTimes();
            }, function() {
                xGame.confirm.hide();
            }, lang('buy'), lang('close'));
        },

        //购买次数
        buyTimes : function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.buyTimesData = res;
                        player.updateinfo({
                            "gold" : res.gold
                        });
                        var gold = that.getWan(res.gold);
                        $('#now_arenatimes').html(res.free_times);
                        $('#now_viptimes').html(res.vip_times);
                        $('#buy_arenatimes_price').html(res.price);
                        that.free_times = res.free_times;
                        that.canBuy = res.vip_times;
                        that.price = res.price;
                        that.militaryDom.find('.pacify_gold').find('span').text(gold);
                        that.beastDom.find('.info_box').find('.lefttimes').text(lang('pacify_left_times', [that.free_times]));
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            loadContents({
                'c' : 'copies',
                'm' : 'buy_times',
                'd' : 'newequip',
                'id' : that.nowMilitary,
                'diff_id' : that.nowDifficulty,
                'monster_id' : that.nowBeastId
            }, Func);
            Func = null;
        },

        //购买军令
        buyActConfirm : function() {
            var that = this;
            var actRender = function(res) {
                if (res.price) {
                    xGame.confirm.show(lang('buy_act'), lang('nuy_act_ask', [res.price, player.info.act]), 2, function() {
                        var actRender2 = function(res) {
                            if (res.status == 1) {
                                player.updateinfo({
                                    "gold" : res.gold,
                                    "act" : res.act
                                });
                                that.militaryDom.find('.pacify_act').find('span').text(res.act + '/' + player.info.act_limit);
                                var gold = that.getWan(res.gold);
                                that.militaryDom.find('.pacify_gold').find('span').text(gold);
                                $('#buy_act_price').html(res.price);
                                $('#buy_act_num').html(res.act);
                            } else if (res.status == -1) {
                                xGame.toast.show(lang('gold_not_enough'));
                            } else {
                                xGame.toast.show(lang('buy_act_fail'));
                            }
                            actRender2 = null;
                        };
                        loadContents({
                            'c' : 'shop',
                            'm' : 'buy_act',
                            't' : 1
                        }, actRender2);
                    }, function() {
                        xGame.confirm.hide();
                    }, lang('buy'), lang('close'));

                } else {
                    xGame.toast.show(lang('can_not_buy_now'));
                }
                actRender = null;
            };
            loadContents({
                'c' : 'shop',
                'm' : 'buy_act'
            }, actRender);
        },

        //攻击
        atkFunc : function(num) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            that.nowtimes = num;
            if (parseInt(that.free_times) <= 0 && that.nowtimes == undefined) {
                xGame.unlock();
                that.showBuyConfirm(lang('pacify_times_n_enough'));
                return false;
            }
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        //普通攻击
                        num = that.nowtimes;
                        if (num == undefined) {
                            miscpop.enterBattleFromPacify(res);
                            that.militaryDom.find('.pacify_act').find('span').text(res.info.act + '/' + player.info.act_limit);
                            var num = that.getWan(res.info.gold);
                            that.militaryDom.find('.pacify_gold').find('span').text(num);
                            player.updateinfo({
                                'gold' : parseInt(res.info.gold),
                                'act' : parseInt(res.info.act)
                            });
                            that.e.hide();
                        } else {//扫荡
                            that.rushRewardData = res;
                            that.militaryDom.find('.pacify_act').find('span').text(that.rushRewardData.info.act + '/' + player.info.act_limit);
                            var number = that.getWan(that.rushRewardData.info.gold);
                            that.militaryDom.find('.pacify_gold').find('span').text(number);
                            that.rushRewardData.from = "pacify";
                            that.beastDom.find('.info_box').find('.lefttimes').text(lang('pacify_left_times', [that.rushRewardData.info.free_times]));
                            that.free_times = that.rushRewardData.info.free_times;
                            that.price = that.rushRewardData.info.one_sub;
                            that.canBuy = that.rushRewardData.info.vip_times;
                            sweeppop.getReward(that.rushRewardData, num);
                        }
                        break;
                    case -7:
                        that.buyActConfirm();
                        break;
                    case -11:
                        that.buyActConfirm();
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                that.nowtimes = null;
            };
            if (num == undefined) {
                loadContents({
                    'c' : 'copies',
                    'm' : 'pk',
                    'd' : 'newequip',
                    "id" : that.nowMilitary,
                    "diff_id" : that.nowDifficulty,
                    "monster_id" : that.nowBeastId
                }, Func);
            } else {
                loadContents({
                    'c' : 'copies',
                    'm' : 'mop_up',
                    'd' : 'newequip',
                    "id" : that.nowMilitary,
                    "diff_id" : that.nowDifficulty,
                    "monster_id" : that.nowBeastId,
                    'times' : num
                }, Func);
            }
            Func = null;
        },

        //获取扫荡价格
        getRushPrice : function() {
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.rushData = res;
                        that.one_sub = parseInt(that.rushData.info.one_sub);
                        that.five_sub = parseInt(that.rushData.info.five_sub);
                        that.ten_sub = parseInt(that.rushData.info.ten_sub);
                        that.canBuy = parseInt(that.rushData.vip_times);
                        that.showRush();
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            }
            loadContents({
                'c' : 'copies',
                'm' : 'get_mopup_price',
                'd' : 'newequip',
                "id" : that.nowMilitary,
                "diff_id" : that.nowDifficulty,
                "monster_id" : that.nowBeastId,
            }, Func);
            Func = null;
        },

        //扫荡
        showRush : function() {
            var that = this;
            xGame.showpop('sweep', {
                'type' : 'pacify',
                'one_sub' : that.one_sub,
                'five_sub' : that.five_sub,
                'ten_sub' : that.ten_sub,
                'vip_times' : that.canBuy,
                'atkFunc' : function(times) {
                    that.atkFunc(times);
                },
            });
        },

        bindAll : function() {
            var that = this;
            this.e.find('#pacify_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                e.preventDefault();
            });

            this.threeMilitaryDom.undelegate('.threeMilitary_item', 'touchend').delegate('.threeMilitary_item', 'touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }

                that.threeMilitaryDom.hide();
                that.getBeastInfo($(this).attr('bid'));
                xGame.unlock();
                e.preventDefault();
            });

            //返回按钮
            this.militaryDom.find('#pacify_military_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var now = "bg" + that.nowMilitary;
                that.removeDifficulty();
                that.militaryDom.find('#beast_way_scroller').removeClass(now);
                that.markDifficulty = null;
                that.militaryDom.find('.pacify_military_name').unbind('webkitAnimationEnd');
                that.militaryDom.find('.pacify_military_name').remove();
                that.hide();
                that.init();
                e.preventDefault();
            });
            //布阵
            this.militaryDom.find('#pacify_common_team').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                xGame.showpop("team");
                e.preventDefault();
                xGame.unlock();
            });
            //背包
            this.militaryDom.find('#pacify_common_pack').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                xGame.showpop("pack");
                e.preventDefault();
                xGame.unlock();
            });

            //菜单图标控制
            this.militaryDom.find('#pacify_menu_control').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                if (that.militaryDom.find('#pacify_menu_control').hasClass('control_open')) {
                    that.militaryDom.find('#pacify_menu_control').removeClass('control_open').addClass('control_close');
                    xGame.doAnimate(that.militaryDom.find('.pacify_common_list'), 'fadeInRight', '0.35s');
                    that.militaryDom.find('.pacify_common_list').show();
                    xGame.doAnimate(that.militaryDom.find('#pacify_military_hidebtn'), 'fadeInUp', '0.35s');
                    that.militaryDom.find('#pacify_military_hidebtn').show();
                    xGame.doAnimate(that.militaryDom.find('.pacify_difficulty_list'), 'fadeInRight', '0.35s');
                    that.militaryDom.find('.pacify_difficulty_list').show();
                    xGame.unlock();
                    e.preventDefault();
                } else {
                    that.militaryDom.find('#pacify_menu_control').addClass('control_open').removeClass('control_close');
                    xGame.doAnimate(that.militaryDom.find('.pacify_common_list'), 'fadeOutRight', '0.35s', function() {
                        that.militaryDom.find('.pacify_common_list').hide();
                    });
                    xGame.doAnimate(that.militaryDom.find('.pacify_difficulty_list'), 'fadeOutRight', '0.35s', function() {
                        that.militaryDom.find('.pacify_difficulty_list').hide();
                    });
                    xGame.doAnimate(that.militaryDom.find('#pacify_military_hidebtn'), 'fadeOutDown', '0.35s', function() {
                        that.militaryDom.find('#pacify_military_hidebtn').hide();
                    });
                    xGame.unlock();
                    e.preventDefault();
                }
            });

            //难度控制
            this.militaryDom.find('#pacify_difficulty_simple').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                if ($(this).hasClass('unchoice')) {
                    that.militaryDom.find('.show').fadeOut('50', function() {
                        that.militaryDom.find('#pacify_difficulty_simple').removeClass('unchoice');
                        if (that.nowDifficulty == 2) {
                            that.militaryDom.find('#pacify_difficulty_hard').addClass('unchoice');
                        } else if (that.nowDifficulty == 3) {
                            that.militaryDom.find('#pacify_difficulty_hero').addClass('unchoice');
                        }
                        that.nowDifficulty = 1;
                        xGame.unlock();
                        that.buildBeastWay(that.beastsData.list[0].list);
                    });
                } else {
                    xGame.unlock();
                }
                e.preventDefault();
            });
            this.militaryDom.find('#pacify_difficulty_hard').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }

                if ($(this).hasClass('unchoice')) {
                    //淡入效果
                    that.militaryDom.find('.show').fadeOut('50', function() {
                        that.militaryDom.find('#pacify_difficulty_hard').removeClass('unchoice');
                        if (that.nowDifficulty == 1) {
                            that.militaryDom.find('#pacify_difficulty_simple').addClass('unchoice');
                        } else if (that.nowDifficulty == 3) {
                            that.militaryDom.find('#pacify_difficulty_hero').addClass('unchoice');
                        }
                        that.nowDifficulty = 2;
                        xGame.unlock();
                        that.buildBeastWay(that.beastsData.list[1].list);
                    });
                } else {

                    if ($(this).hasClass('unreached')) {
                        xGame.toast.show(lang('pacify_tips1'));
                    }

                    xGame.unlock();
                }
                e.preventDefault();
            });
            this.militaryDom.find('#pacify_difficulty_hero').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                if ($(this).hasClass('unchoice')) {
                    that.militaryDom.find('.show').fadeOut('50', function() {
                        that.militaryDom.find('#pacify_difficulty_hero').removeClass('unchoice');
                        if (that.nowDifficulty == 1) {
                            that.militaryDom.find('#pacify_difficulty_simple').addClass('unchoice');
                        } else if (that.nowDifficulty == 2) {
                            that.militaryDom.find('#pacify_difficulty_hard').addClass('unchoice');
                        }
                        that.nowDifficulty = 3;
                        xGame.unlock();
                        that.buildBeastWay(that.beastsData.list[2].list);
                    });
                } else {

                    if ($(this).hasClass('unreached')) {
                        xGame.toast.show(lang('pacify_tips2'));
                    }
                    
                    xGame.unlock();
                }
                e.preventDefault();
            });

            //增加军令按钮
            this.militaryDom.find('#add_act_btn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.buyActConfirm();
            });

            this.militaryDom.find('#beast_way_scroller').undelegate('.beast_div', 'touchend').delegate('.beast_div', 'touchend', function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.nowBeastId = $(this).attr('bid');
                that.showBeastInfo();
                xGame.unlock();
                e.preventDefault();
            });

            this.militaryDom.undelegate('.pacify_military_name', 'webkitAnimationEnd').delegate('.pacify_military_name', 'webkitAnimationEnd', function() {
                $(this).unbind('webkitAnimationEnd');
                $(this).remove();
            });

            //怪
            this.beastDom.find('#beast_info_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.beastDom.hide();
                //修复可能出现的滑条的bug
                clearTimeout(that.beastTime);
                that.beastTime = null;
                if (that.beastScroller != null) {
                    that.beastScroller.destroy();
                    that.beastScroller = null;
                }
                e.preventDefault();
            });

            //攻略
            that.beastDom.find('#pacify_gl_btn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.showpop('strategy', {
                    "c" : "copies",
                    "m" : "get_report_list",
                    'd' : 'newequip',
                    'name' : that.nowBeastname,
                    "id" : that.nowMilitary,
                    "diff_id" : that.nowDifficulty,
                    "monster_id" : that.nowBeastId
                });
                e.preventDefault();
            });

            //攻击
            that.beastDom.find('#pacify_attack_btn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.nowtimes) {
                    that.nowtimes = null;
                }
                that.atkFunc();
                e.preventDefault();
            });

            //扫荡
            that.beastDom.find('#pacify_rush_btn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.getRushPrice();
            });

        },
        unbindAll : function() {
            if (this.threeMilitaryDom) {
                this.threeMilitaryDom.undelegate('.threeMilitary_item', 'touchend');
                this.threeMilitaryDom = null;
            }
            if (this.militaryDom) {
                this.militaryDom.find('#pacify_military_hidebtn').unbind('touchend');
                this.militaryDom.find('#pacify_common_team').unbind('touchend');
                this.militaryDom.find('#pacify_common_pack').unbind('touchend');
                this.militaryDom.find('#pacify_menu_control').unbind('touchend');
                this.militaryDom.find('#pacify_difficulty_simple').unbind('touchend');
                this.militaryDom.find('#pacify_difficulty_hard').unbind('touchend');
                this.militaryDom.find('#pacify_difficulty_hero').unbind('touchend');
                this.militaryDom.find('#beast_way_scroller').undelegate('.beast_div', 'touchend');
                this.militaryDom.find('#add_act_btn').unbind('touchend');
                this.militaryDom = null;
            }
            if (this.beastDom) {
                this.beastDom.find('#beast_info_hidebtn').unbind('touchend');
                this.beastDom.find('#pacify_gl_btn').unbind('touchend');
                this.beastDom.find('#pacify_attack_btn').unbind('touchend');
                this.beastDom.find('#pacify_rush_btn').unbind('touchend');
                this.beastDom = null;
            }
            if (this.e) {
                this.e.find('#pacify_hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide : function() {
            this.unbindAll();
            this.threeMilitaryData = null;
            this.beastsData = null;
            this.rushRewardData = null;
            this.beastsData = null;
            this.rushData = null;
            this.nowMilitary = null;
            this.nowDifficulty = null;
            this.nowBeastId = null;
            this.nowfirst_reward = null;
            this.nowprobable_reward = null;
            this.nowBeastname = null;
            this.buyTimesData = null;
            this.markDifficulty = null;
            this.nowtimes = 0;
            this.canBuy = 0;
            this.one_sub = 0;
            this.five_sub = 0;
            this.ten_sub = 0;
            this.free_times = 0;
            this.isshow = false;
            this.price = null;

            if (this.mapScroller != null) {
                this.mapScroller.destroy();
                this.mapScroller = null;
            }
            if (this.beastScroller != null) {
                this.beastScroller.destroy();
                this.beastScroller = null;
            }
            if (this.maptime != null) {
                clearTimeout(this.maptime);
                this.maptime = null;
            }
            if (this.beastTime != null) {
                clearTimeout(this.beastTime);
                this.beastTime = null;
            }
            if (this.fadeTime != null) {
                clearTimeout(this.fadeTime);
                this.fadeTime = null;
            }
        }
    }
})();
