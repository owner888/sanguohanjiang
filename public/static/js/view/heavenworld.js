// 通天塔
(function () {
    heavenWorldClass = function () {
        this.e = null;
        this.isshow = false;
        this.heavenScroll = null;
        this.initData = null;
        this.resource = null;       // 玩家拥有的资源
        this.monsterInfo = null;    // 左侧的怪物等信息
        this.rightWrap = null;      // 右侧的滑动层
        this.nowMissionIndex = 0;   // 当前查看的层级排序
        this.currentMid = 0;        // 当前怪物的ID
        this.fightIndex = 0;        // 当前要攻打的关卡的排序
        this.liHeight = 0;
        this.material = null;       // 掉落材料
        this.breakpoint = [];       // 显示怪物层的中断点
    };

    heavenWorldClass.prototype = {
        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                
                var status = parseInt(res.status);

                if (status == 1) {
                    that.initFirst(res);
                } else {

                    switch (status) {
                        case -1:
                            xGame.toast.show(lang('equiprefinepop_level'));
                            break;
                        case -2:
                            xGame.toast.show(lang('heavenworld_err1'));
                            break;
                        case -3:
                            xGame.toast.show(lang('heavenworld_err2'));
                            break;
                        case -4:
                            xGame.toast.show(lang('heavenworld_err3'));
                            break;
                        default :
                            xGame.toast.show(lang('undefined_error'));
                            break;
                    }
                    xGame.loadWorld('city');
                }
            };

            loadContents({
                'c': 'heaven',
                'm': 'index'
            }, render, false, function () {
                xGame.loadWorld('city');
            });
        },

        initFirst: function (res) {
            
            var that = this;
            that.initData = res;
            that.fightIndex = res.index;
            that.nowMissionIndex = res.index;
            that.breakpoint = [50, 100, 150];

            if (that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('heavenpop_tmpl'));
            that.e = dom_world.find('#heavenpop');
            that.monsterInfo = that.e.find('#monster_info');
            that.rightWrap = that.e.find('#right_wrap');
            that.material = that.monsterInfo.find('#material');
            that.resource = that.e.find('#resource');

            that.e.find('#heaven_hide').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.loadWorld('city');
                e.preventDefault();
            });

            dom_world = null;

            that.heavenScroll = new iScroll('heavenpop_scroller_wrap', {
                snap: 'li',
                bounce: false,
                hScroll: false,
                vScroll: true,
                hScrollbar: false,
                vScrollbar: false
            });

            that.buildRight(res.list);
            that.buildLeft(res.list[res.index]);
            that.buildTop(res.resource);

            // 是否显示扫荡按钮
            if (res.list[res.index].mop_up == 1) {
                that.monsterInfo.find('#rush_atk').css('display', 'block');
            } else {
                that.monsterInfo.find('#rush_atk').css('display', 'none');
            }

            that.bindAll();

            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },

        bindAll: function () {
            
            var that = this;

            // 每日奖励按钮
            that.e.find('#heaven_reward_btn').unbind('touchend').bind('touchend', function (e) {

                miscpop.heavenRewardbox();
                e.preventDefault();
            });

            // 装备精炼按钮
            that.e.find('#heaven_refine_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.loadWorld('city');
                xGame.showpop('equiprefine');
                e.preventDefault();
            });

            // 购买次数
            that.monsterInfo.find('#buy_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.buyTimes();
                e.preventDefault();
            });

            // 扫荡按钮
            that.monsterInfo.undelegate('#rush_atk', 'touchend').delegate('#rush_atk', 'touchend',function(e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getMopPrice();
                e.preventDefault();
            });

            // 查看怪物信息
            that.rightWrap.undelegate('.monster.open', 'touchend').delegate('.monster.open', 'touchend', function() {
                var missionIndex = $(this).attr('missionindex');
                if (missionIndex && missionIndex != that.nowMissionIndex) {
                    that.nowMissionIndex = missionIndex;
                    that.viewMission(missionIndex);
                }
            });

        },

        // 构建玩家的资源列表
        buildTop: function (resource) {

            var that = this;
            var html = '';

            html += '<li class="stone refinestone">' + resource.refinestone + '</li>';
            html += '<li class="stone colorstone">' + resource.colorstone + '</li>';
            html += '<li class="stone bloodstone">' + resource.bloodstone + '</li>';

            that.resource.html(html);
        },

        // 构建右侧的怪物列表
        buildRight: function (list) {

            var that = this;
            var html = '';
            var len = that.fightIndex + 1;

            // 如果当前打到40层，则只显示前50层，以此类推
            for (var i = 0, len1 = that.breakpoint.length; i < len1; i++) {
                
                if (len < that.breakpoint[i]) {
                    len = that.breakpoint[i];
                    break;
                } else if (len >= that.breakpoint[len1 - 1]) {
                    len = list.length;
                } else {
                    continue;
                }
            }
            
            for (var i = len - 1; i >= 0; i--) {
                var data = list[i];

                html += '<li missionindex="' + i + '" class="monster ' + (data.openstatus == 1 ? 'open': '') + '" id="heavenmission' + data.id + '">';
                html += '<em class="mission_mask"></em>';
                html += '<span class="img" style="background-image:url(' + xGame.avatar(data.image) + ')">' + '</span>';
                html += '<div class="info">';
                html += '<span class="floor">' + lang('heavenworld_floor', [data.id]) + '：' + data.name +'</span>';
                html += '<span class="mission_level">' + lang('level') + '：' + data.level + '（' + helper.getEnemyType(data.type) + '）' + '</span>';

                html += '</div>';
                html += '<span class="locked"></span>';

                if (data.killed == 1) {
                    html += '<span class="killed"></span>';
                }

                if (parseInt(data.firstkill) == 1) {
                    html += '<span class="first_kill"></span>';
                }

                html += '</li>';
            }

            that.rightWrap.find('.scroller_content').html(html);
            that.rightWrap.find('#heavenmission' + list[that.fightIndex].id).addClass('now');

            that.liHeight = that.rightWrap.find('.monster:first').height() + parseInt(that.rightWrap.find('.monster:first').css('margin-bottom'));

            that.rightWrap.find('#heavenpop_scroller').css({'height': len * that.liHeight + 'px'});

            that.heavenScroll.refresh();
            that.heavenScroll.scrollTo(0, (that.fightIndex + 1 - len) * that.liHeight);

            that.monsterInfo.find('.times').html(that.initData.times); //剩余次数

        },

        /* 查看某一层的怪物 */
        viewMission: function(missionIndex) {
            var that = this;
            var missiondata = that.initData.list[missionIndex];

            that.rightWrap.find('.monster.now').removeClass('now');
            that.rightWrap.find('#heavenmission' + missiondata.id).addClass('now');
            that.buildLeft(missiondata);
                
            if (missiondata.mop_up == 1) {
                that.monsterInfo.find('#rush_atk').css('display', 'block');
            } else {
                that.monsterInfo.find('#rush_atk').css('display', 'none');
            }
        },

        // 计算材料掉落概率
        count: function (num) {
            var num = parseInt(num);

            if (num <= 30 && num > 2) {
                return lang('heavenworld_probability2');
            } else if (num <= 2 && num > 1) {
                return lang('heavenworld_probability3');
            } else if (num <= 1) {
                return lang('heavenworld_probability4');
            }
        },

        /* 根据某一层的信息 构建右方信息界面 */
        buildLeft: function(data) {
            var that = this;
            var html = '';
            that.currentMid = data.id;

            that.monsterInfo.find('#avatar').css({'background-image': 'url(' + xGame.avatar(data.image, true) + ')'});
            that.monsterInfo.find('.name').html(data.name);
            that.monsterInfo.find('.level').html('Lv.' + data.level);
            that.monsterInfo.find('.floor').html(lang('heavenworld_floor', [data.id]));
            that.monsterInfo.find('.type').html('【' + helper.getEnemyType(data.type) + '】');

            if (parseInt(data.firstkill) == 1) {

                switch (parseInt(data.first_type)) {
                    case 1:
                        html += '<li class="refinestone">' + lang('refinestone') + 'x' + data.first_value + '</li>';
                        break;
                    case 2:
                        html += '<li class="colorstone">' + lang('colorstone') + 'x' + data.first_value + '</li>';
                        break;
                    case 3:
                        html += '<li class="bloodstone">' + lang('bloodstone') + 'x' + data.first_value + '</li>';
                        break;
                    default:
                        break;
                }
            } else {
                // 精炼石
                if (parseInt(data.refinestone) != 0) {
                    html += '<li class="refinestone">' + lang('refinestone') + 'x' + data.refinestone + '<em class="probability">' + lang('heavenworld_probability1') + '</em></li>';
                }

                // 五彩石
                if (parseInt(data.colorstone) != 0) {
                    html += '<li class="colorstone">' + lang('colorstone') + 'x' + data.colorstone + '<em class="probability">' + that.count(data.color_getrate) + '</em></li>';
                }

                // 上古血玉
                if (parseInt(data.bloodstone) != 0) {
                    html += '<li class="bloodstone">' + lang('bloodstone') + 'x' + data.bloodstone + '<em class="probability">' + that.count(data.blood_getrate) + '</em></li>';
                }

                var essenceId = parseInt(data.essence.id);
                var essenceNum = parseInt(data.essence.num);

                if (essenceNum != 0) {
                    if (essenceId >= 1 && essenceId <= 6) { // 武器精华
                        html += '<li class="equip_essence" style="color:'+ helper.getItemColor(data.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('equip_ess') + 'x' + essenceNum + '<em class="probability">' + lang('heavenworld_probability4') + '</em></li>';
                    } else if (essenceId >= 7 && essenceId <= 12) { // 防具精华
                        html += '<li class="defense_essence" style="color:'+ helper.getItemColor(data.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('defense_ess') + 'x' + essenceNum + '<em class="probability">' + lang('heavenworld_probability4') + '</em></li>';
                    } else if (essenceId >= 13 && essenceId <= 18) { // 饰品精华
                        html += '<li class="acc_essence" style="color:'+ helper.getItemColor(data.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('acc_ess') + 'x' + essenceNum + '<em class="probability">' + lang('heavenworld_probability4') + '</em></li>';
                    }
                }
            }

            that.material.html(html);

            // 攻击按钮
            if (parseInt(data.id) > parseInt(that.initData.list[that.fightIndex].id)) {
                that.monsterInfo.find('#attack_btn').attr('class', 'pop_btn_middle_disable attack_btn').unbind('touchend');
            } else {
                that.monsterInfo.find('#attack_btn').attr('class', 'pop_btn_middle attack_btn').unbind('touchend').bind('touchend',
                function() { //进攻

                    if (xGame.checkLock()) {
                        return false;
                    }

                    that.fight(data.id);
                });
            }
            
            // 攻略按钮
            that.monsterInfo.find('#strategy_btn').unbind('touchend').bind('touchend', function() {

                $('#worldloading').removeClass('black');
                
                xGame.showpop('strategy', {
                    "name": data.name,
                    "c": "heaven",
                    "m": "get_report_list",
                    "id": data.id
                });
            });
        },

        // 战斗
        fight: function(missionid) {
            var that = this;

            if (that.initData.times <= 0) {
                that.buyTimes();
                return false;
            }

            var fightRender = function(res) {

                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        miscpop.enterBattleFromHeaven(res, missionid);
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('heavenworld_err1'));
                        break;
                    case -3:
                        xGame.toast.show(lang('times_not_enough'));
                        break;
                    case -4:
                        xGame.toast.show(lang('heavenworld_err3'));
                        break;
                    case -5:
                        xGame.toast.show(lang('heavenworld_err2'));
                        break;
                    case -6:
                        xGame.toast.show(lang('heavenworld_err6'));
                        break;
                    case -7:
                        xGame.toast.show(lang('heavenworld_err7'));
                        break;
                    case -8:
                        xGame.toast.show(lang('heavenworld_err8'));
                        break;
                    case -9:
                        xGame.toast.show(lang('heavenworld_err9'));
                        break;
                    case -10:
                        xGame.toast.show(lang('heavenworld_err10'));
                        break;
                    case -11:
                        xGame.toast.show(lang('heavenworld_err11'));
                        break;
                    case -12:
                        xGame.toast.show(lang('heavenworld_err12'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'heaven',
                'm': 'pk',
                'id': missionid
            },
            fightRender);

        },

        // 购买次数
        buyTimes: function () {
            
            var that = this;

            var render = function (res) {
                
                xGame.unlock();

                if (parseInt(res.status) == 1) {
                    xGame.confirm.show(lang('notice'), lang('heavenworld_buy_ask', [res.price, that.initData.times]), 2,
                    function() {

                        if (xGame.checkLock()) {
                            return false;
                        }
                        that.buytimesProcess();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };

            loadContents({
                'c': 'heaven',
                'm': 'get_price'
            }, render);
        },

        // 确定购买次数
        buytimesProcess: function () {
            var that = this;

            var buyRender = function(res) {

                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        that.initData.times = parseInt(res.times);
                        player.updateinfo({
                            'gold': parseInt(res.gold)
                        });
                        that.monsterInfo.find('.lefttimes').children('.times').html(res.times); //剩余次数
                        $('#buy_times').html(res.times);
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -3:
                        xGame.toast.show(lang('heavenworld_err1'));
                        break;
                    case -4:
                        xGame.toast.show(lang('heavenworld_err5'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'heaven',
                'm': 'buy'
            },
            buyRender);
        },

        // 扫荡
        getMopPrice: function() {
            var that = this;
            
            var Func = function(res) {

                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        xGame.showpop('force', {
                            'type': 'heaven',
                            'five_sub': res.info.five_sub,
                            'ten_sub': res.info.ten_sub,
                            'fifty_sub': res.info.fifty_sub,
                            'hundred_sub': res.info.hundred_sub,
                            'atkFunc': function(times) {
                                that.atkFunc(times);
                            },
                        });
                        break;
                    case -6:
                        xGame.toast.show(lang('get_hero_price_fail'));
                        break;
                    case -5:
                        xGame.toast.show(lang('heavenworld_err1'));
                        break;
                    case -4:
                        xGame.toast.show(lang('heavenworld_err13'));
                        break;
                    case -3:
                        xGame.toast.show(lang('heavenworld_err2'));
                        break;
                    case -2:
                        xGame.toast.show(lang('heavenworld_err3'));
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'heaven',
                'm': 'get_mopup_price',
                'id': that.currentMid
            }, Func);
        },

        //扫荡攻击
        atkFunc: function(num) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.monsterInfo.find('.times').html(res.times); 
                        that.initData.times = parseInt(res.times);
                        res.info.from = 'heaven';
                        forcepop.getReward(res.info, num);
                        that.buildTop(res.resource);
                        player.updateinfo({
                            'gold': parseInt(res.gold)
                        });
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('heavenworld_err1'));
                        break;
                    case -3:
                        xGame.toast.show(lang('heavenworld_err3'));
                        break;
                    case -4:
                        xGame.toast.show(lang('heavenworld_err2'));
                        break;
                    case -5:
                        xGame.toast.show(lang('heavenworld_err14'));
                        break;
                    case -6:
                        xGame.toast.show(lang('heavenworld_err15'));
                        break;
                    case -7:
                        xGame.toast.show(lang('equiprefinepop_refine_msg4'));
                        break;
                    case -8:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -9:
                        xGame.toast.show(lang('deduct_gold_fail'));
                        break;
                    case -10:
                        xGame.toast.show(lang('heavenworld_err9'));
                        break;
                    case -11:
                        xGame.toast.show(lang('heavenworld_err10'));
                        break;
                    case -12:
                        xGame.toast.show(lang('heavenworld_err16'));
                        break;
                    case -13:
                        xGame.toast.show(lang('heavenworld_err17'));
                        break;
                    case -14:
                        xGame.toast.show(lang('get_hero_price_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'heaven',
                'm': 'mop_up',
                'id': that.currentMid,
                'times': num
            }, Func);
            
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#heaven_hide').unbind('touchend');
                this.e.find('#heaven_reward_btn').unbind('touchend');
                this.e.find('#heaven_refine_btn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.monsterInfo) {
                this.monsterInfo.find('#buy_btn').unbind('touchend');
                this.monsterInfo.undelegate('#rush_atk', 'touchend');
                this.monsterInfo.find('#attack_btn').unbind('touchend');
                this.monsterInfo.find('#strategy_btn').unbind('touchend');
                this.monsterInfo.remove();
                this.monsterInfo = null;
            }
            if (this.rightWrap) {
                this.rightWrap.undelegate('.monster.open', 'click');
                this.rightWrap.remove();
                this.rightWrap = null;
            }
        },

        clear: function () {
            
            xGame.unlock();

            if (this.e != null) {

                if (this.heavenScroll != null) {
                    this.heavenScroll.destroy();
                    this.heavenScroll = null;
                }
                
                this.isshow = false;
                this.resource = null;
                this.liHeight = 0;
                this.initData = null;
                this.nowMissionIndex = 0;
                this.currentMid = 0;
                this.material = null;
                this.fightIndex = 0;
                this.breakpoint = [];
            }
            this.unbindAll();

            player.reloadInfo();
        }
    };
})();