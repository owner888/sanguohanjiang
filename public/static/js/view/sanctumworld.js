/* 四象圣域 */
(function() {
    sanctumWorldClass = function() {
        this.e;
        this.isshow = false;
        this.fourBeastDom;
        this.beastStageDom;
        this.price;
        this.beastid;//四个神兽
        this.stage;//当前层数
        this.infoDom;//介绍弹窗
        this.currentData;//当前怪物数据
        this.atkTimes;
        
        this.mapScroller;
        this.beastScroller;
        this.beasttime; 
    };
    sanctumWorldClass.prototype = {
        init: function(returndata, isreturn) {
            var that = this;
            //这里只需要初始化参数，显示上次的关卡，专门为攻略和战斗回来后用的
            if (returndata && returndata.beastid && returndata.stage) {
                that.prepareBeastPage();
                that.showBeastWay(returndata.beastid, returndata.stage);
                //如果有当前数据(只有在看攻略的时候才返回)
                if (returndata.currentData) {
                    that.buildInfo(returndata.currentData);
                }
            } else {
                var render = function(res) {
                    if (res.status == 1) {
                        //isreturn 代表时候是看完攻略后回来的（攻略回来需要特殊处理）
                        if (isreturn) {
                            that.build4BeastPage(res);
                        } else {
                            that.prepareBeastPage(); //准备界面
                            that.build4BeastPage(res);
                            //自动时如果返回一通关，调用通关方法
                            if (returndata && returndata.clear) {
                                that.clearBeast();
                            }
                        }

                    } else if (res.status == 2) {
                        xGame.toast.show(lang('sanctumworld_lv_140'));
                        xGame.loadWorld('city');
                    }else {
                        xGame.toast.show(lang('champions_get_info_fail'));
                        xGame.loadWorld('city');
                    }
                };
                loadContents({
                    'c': 'sanctum',
                    'm': 'index'
                },
                render, false,
                function() {
                    xGame.loadWorld('city');
                }); //加载数据
                render = null;
            }

        },

        prepareBeastPage: function() {
            var that = this;

            if (that.e != null) { //已经准备好
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('sanctumworld_tmpl'));

            that.e = $('#sanctumworld');
            that.fourBeastDom = that.e.find('#four_beast');
            that.beastStageDom = that.e.find('#beast_dom');
            that.infoDom = that.e.find('#beast_info_dom');

            that.e.find('#four_beast_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.loadWorld('city');
                e.preventDefault();
            });

            that.bindAll();
            $('#worldloading').removeClass('black').hide();
        },

        build4BeastPage: function(res) {
            var that = this;
            var html = '';
            for (var i = 1;i < 6;i ++) {
                html += '<li class="beast beast' + i + (res.list[i].pass == 1 ? ' pass"' : '') + '" bid="' + i + '"></li>'
            }
            this.fourBeastDom.find('#four_beast_srcoll').html(html);
           
            if (this.beastScroller == null) {
                this.beastScroller = new iScroll('four_beast', {
                    bounce : false,
                    momentum : false,
                    hScrollbar : false,
                    vScrollbar : false
                });
            }

            this.beasttime = setTimeout(function(){
                that.beastScroller.refresh();
                var worldOffset = $('#world').offset();
                var scrollerOffset = that.e.find('#four_beast_srcoll').offset();
                that.beastScroller.scrollTo((worldOffset.width - scrollerOffset.width) / 2, (worldOffset.height - scrollerOffset.height) / 2);
            },10);
            html = null;
            this.e.attr('class', 'pop_page bg1');
        },

        bindAll: function() {
            var that = this;

            that.fourBeastDom.undelegate('.beast', 'click').delegate('.beast', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showBeastWay($(this).attr('bid'));
            });

            that.beastStageDom.undelegate('li.show', 'touchend').delegate('li.show', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) {
                    return false;
                }
                that.showBeastWay(that.beastid, $(this).attr('sid'));
            });

            that.beastStageDom.find('#rs').undelegate('b', 'touchend').delegate('b', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if ($(this).attr('type') == 'show') {
                    $(this).attr('type', 'hide');
                    $(this).html(lang('sanctumworld_show'));
                    that.beastStageDom.attr('class', 'hide');
                } else {
                    $(this).attr('type', 'show');
                    $(this).html(lang('sanctumworld_hide'));
                    that.beastStageDom.attr('class', 'show');
                }
            });

            that.beastStageDom.undelegate('#beast_way_hide', 'touchend').delegate('#beast_way_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                //如果后面是空的，在返回的时候生成后面
                if (that.fourBeastDom.find('#four_beast_srcoll').html() == '') {
                    if (that.beastScroller) {
                        that.beastScroller.destroy();
                        that.beastScroller = null;
                    }
                    if (that.beasttime) {
                        clearTimeout(that.beasttime);
                        that.beasttime = null;
                    } 
                    that.init(null, true);
                } else {
                    that.e.attr('class', 'pop_page bg1');
                }
            });

            that.beastStageDom.undelegate('.beast_div', 'touchend').delegate('.beast_div', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showInfoDom($(this).attr('bid'));
            });

            that.beastStageDom.undelegate('#go_gem', 'touchend').delegate('#go_gem', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.loadWorld('gem');
            });

			that.beastStageDom.undelegate('#daliy_reward', 'touchend').delegate('#daliy_reward', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
				miscpop.sanctumRewardbox();
            });

            that.beastStageDom.undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.showBuyConfirm(lang('sanctumworld_buy_times'));
            });

            that.infoDom.undelegate('#beast_info_hide', 'touchend').delegate('#beast_info_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.infoDom.css('display','none');
                that.infoDom.find('#reward_dom').removeClass();
            });

            that.infoDom.undelegate('#gl_btn', 'touchend').delegate('#gl_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.showpop('strategy', {
                    "name": $(this).attr('name'),
                    "c": "sanctum",
                    "m": "get_mission_report_list",
                    "id": that.currentData.mission.id
                });
            });

            that.infoDom.undelegate('#attack_btn.pop_btn_middle', 'touchend').delegate('#attack_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.atkFunc();
            });

            that.infoDom.undelegate('#rush_btn', 'touchend').delegate('#rush_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.showpop('force', {
                    'type': 'sanctum',
                    'five_sub': that.currentData.mission.five_sub,
                    'ten_sub': that.currentData.mission.ten_sub,
                    'fifty_sub': that.currentData.mission.fifty_sub,
                    'hundred_sub': that.currentData.mission.hundred_sub,
                    'atkFunc': function(times) {
                        sanctumWorld.atkFunc(times);
                    },
                });
            });

        },

        showBuyConfirm: function(msg, needRefresh) {
            var that = this;
            xGame.confirm.show(msg, lang('sanctumworld_buy_times_ask', [that.price, that.atkTimes]), 2,
                function() {
                    that.buyTimes();
                },
                function() {
                    //需要再请求一次获得最新的扫荡价格
                    if (needRefresh) {
                        that.showInfoDom(that.currentData.mission.id);
                    }
                    xGame.confirm.hide();
                },
                lang('buy'), lang('close'));
        },

        //攻击功能
        atkFunc: function(num) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            //只有普通攻击时出现
            if (that.atkTimes <= 0 && num == undefined) {
                that.showBuyConfirm(lang('sanctumworld_times_n_enough'), true);
                xGame.unlock();
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        //普通攻击
                        if (num == undefined) {
                            miscpop.enterBattleFromSanctum(res);
                        } else {//扫荡
                            that.beastStageDom.find('#left_times').html(res.info.times);
                            that.atkTimes = parseInt(res.info.times);
                            that.beastStageDom.find('#own_gem').html(res.info.all_chip);

                            res.info.from = 'sanctum';
                            forcepop.getReward(res.info, num);
                            //更新本地数据
                            that.currentData.mission.five_sub = res.info.five_sub;
                            that.currentData.mission.ten_sub = res.info.ten_sub;
                            that.currentData.mission.fifty_sub = res.info.fifty_sub;
                            that.currentData.mission.hundred_sub = res.info.hundred_sub;
                        }
                        break;
                    case -8:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -6:
                        xGame.toast.show(lang('no_time_to_start'));
                        break;
                    case -5:
                        xGame.toast.show(lang('sanctumworld_unable_rush'));
                        break;
                    case -4:
                        xGame.toast.show(lang('checkpoints_close'));
                        break;
                    case -3:
                        xGame.toast.show(lang('sanctumworld_get_stage_h_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_guan_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            if (num == undefined) {
                loadContents({
                    'c': 'sanctum',
                    'm': 'action',
                    'id': that.currentData.mission.id
                },
                Func);
            } else {
                loadContents({
                    'c': 'sanctum',
                    'm': 'action',
                    'id': that.currentData.mission.id,
                    'num': num
                },
                Func);
            }
            Func = null;

        },

        //显示怪物信息
        showInfoDom: function(id) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.buildInfo(res);
                        break;
                    case -5:
                        xGame.toast.show(lang('checkpoints_close'));
                        break;
                    case -4:
                        xGame.toast.show(lang('sanctumworld_get_stage_h_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_guan_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('canshu_lost'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'sanctum',
                'm': 'get_mission_info',
                'id': id
            },
            Func);
            Func = null;
        },

        buildInfo: function(data) {
            if (data.mop_up == 1) {
                this.infoDom.find('#rush_btn').css('display','block');
                this.infoDom.find('#beast_killed').css('display','block');
            } else {
                this.infoDom.find('#rush_btn').css('display','none');
                this.infoDom.find('#beast_killed').css('display','none');
            }

            if (data.mission.attack == 1) {
                this.infoDom.find('#attack_btn').attr('class', 'pop_btn_middle btn');
            } else {
                this.infoDom.find('#attack_btn').attr('class', 'pop_btn_middle_disable btn');
            }
            this.currentData = data;
            this.infoDom.find('#gl_btn').attr('name', data.mission.name);
            this.infoDom.find('#beast_type').attr('class','type t' + data.mission.city);
            this.infoDom.find('#beast_flag').attr('class','flag f' + data.mission.type);

            // 是否首杀
            if (parseInt(data.mission.firstkill) == 1) {
                this.infoDom.find('#gem_num').html(data.mission.first_chip_num);
                this.infoDom.find('.first_kill').show();
            } else {
                this.infoDom.find('#gem_num').html(data.mission.chip_num);
                this.infoDom.find('.first_kill').hide();
            }

            data.mission.gem = parseInt(data.mission.gem);

            if (data.mission.gem > 0) {
                this.infoDom.find('#reward_dom').addClass('c_i_bj' + data.mission.gem_quality);
                this.infoDom.find('#reward_gem').css('background-image','url(' + xGame.gem(data.mission.gem_type,data.mission.gem_quality) + ')');
                this.infoDom.find('#p1').html('<b style="color:' + helper.getItemColor(data.mission.gem_quality) + '">' + data.mission.gem_name + ' Lv.' + data.mission.gem_level + '</b>' + '  <em>' + (data.mission.getrate == 0 ? '': (data.mission.getrate == 1 ? lang('xiao_gailv') : lang('da_gailv'))) + '</em>');
                this.infoDom.find('#p2').html(helper.getTalent(data.mission.gem_type) + ': <em>+' + data.mission.gem_value + '</em>');
                this.infoDom.find('#reward_dom').css('display','block');
            } else {
                this.infoDom.find('#reward_dom').css('display','none');
            }
            this.infoDom.find('#beast_name').html(data.mission.name + '(Lv.' + data.mission.level + ')');

            this.infoDom.css('display','block');
        },

        buyTimes: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        player.updateinfo({
                            "gold": res.all_gold
                        });
                        that.beastStageDom.find('#left_times').html(res.times);
                        $('#now_arenatimes').html(res.times);
                        that.atkTimes = parseInt(res.times);
                        break;
                    case -2:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'sanctum',
                'm': 'buy_times',
                'type': 1
            },
            Func);
            Func = null;
        },

        //stage 不传就是上次进入的地方
        showBeastWay: function(id, stage) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.beastid = id;
                        that.stage = res.stage;
                        that.buildBeast(res);
                        if (stage != undefined) {
                            that.beastStageDom.find('li.selected').removeClass('selected');
                            that.beastStageDom.find('#stage' + stage).addClass('selected');
                        }
                        break;
                    case -4:
                        xGame.toast.show(lang('get_guan_info_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('sanctumworld_stage_not_open'));
                        break;
                    case -2:
                        xGame.toast.show(lang('sanctumworld_get_stage_h_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            if (stage == undefined) {
                loadContents({
                    'c': 'sanctum',
                    'm': 'select_map',
                    'l': id
                },
                Func);
            } else {
                loadContents({
                    'c': 'sanctum',
                    'm': 'select_map',
                    'l': id,
                    's': stage
                },
                Func);
            }
            Func = null;
        },

        //生成怪物
        buildBeast: function(data) {
            var that = this;

            this.beastStageDom.find('#own_gem').html(data.gem);
            this.beastStageDom.find('#left_times').html(data.times);
            this.atkTimes = parseInt(data.times);
            this.beastStageDom.find('#beast_name').html(data.name);
            this.price = data.price;

            data.stage = parseInt(data.stage);
            data.all_stage = parseInt(data.all_stage);

            var html = '';
            var beast_type;
            var beast_class;
            var lastPos = {};
            var len = data.list.length;
            var domid = '';
            var num = 0;
            for (var i = 0; i < len; i++) {
                if (data.list[i].open != 3) {
                    data.list[i].type = parseInt(data.list[i].type);
                    data.list[i].x = getSLeft(data.list[i].x);
                    data.list[i].y = getSTop(data.list[i].y);
                    beast_type = data.list[i].type > 2 ? ('boss' + data.list[i].open) : data.list[i].open;
                    beast_class = 'beast_' + this.beastid + '_' + beast_type;
                    domid = 'beast_' + data.list[i].id;
                    html += '<div id="' + domid + '" class="beast_div ' + beast_class + (data.list[i].type > 2 ? ' isboss': '') + '" bid="' + data.list[i].id + '" style="left:' + data.list[i].x + 'px;top:' + data.list[i].y + 'px;"><span>' + lang('guan_num', [data.list[i].missionid]) + '</span></div>';
                    lastPos.x = data.list[i].x;
                    lastPos.y = data.list[i].y;
                    if (data.list[i].open == 2) {
                        num += 1;
                    }
                }
            }
            this.beastStageDom.find('#beast_way').find('ul').html(html);
            if (num != 10) {
                this.beastStageDom.find('#beast_way').find('#' + domid).append('<span class="lastenemy_span"></span>');
            }

            html = null;
            beast_type = null;
            beast_class = null;
            domid = null;
            num = null;

            var html2 = '';
            var cn = '';
            var mask = '';
            for (var i = 5;i >= 1;i --) {
                if (i <= data.all_stage) {
                    cn = 'show';
                    mask = '';
                } else {
                    cn = 'hide';
                    mask = '<span class="mask"></span>';
                }
                if (data.stage == i) {
                    cn += ' selected';
                }
                html2 += '<li id="stage' + i + '" sid="' + i + '" class="' + cn + '">' + mask + '</li>';
            }
            html2 += '<b type="show">' + lang('sanctumworld_hide') + '</b>';
            this.beastStageDom.find('#rs').html(html2).attr('class', 'rs beast' + this.beastid);
            this.beastStageDom.removeClass();
            html2 = null;
            mask = null;
            cn = null;
            that.e.attr('class', 'pop_page bg2');
            if (this.mapScroller == null) {

                this.mapScroller = new iScroll('beast_way', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            that.mapScroller.refresh();
            this.beastStageDom.find('#beast_way').find('ul').attr('class', 'beast' + this.beastid + '_s' + this.stage);

            var obj = $('body');
            var width = isIPhone5 ? 1136 : 1024;
            var height = isIPhone5 ? 852 : 768;
            var left = (lastPos.x > obj.width() / 2) ? ( - width + obj.width()) : 0;
            var top = (lastPos.y > obj.height() / 2) ? ( - height + obj.height()) : 0;

            this.mapScroller.scrollTo(left, top, 0);

        },

        //显示通关动画
        clearBeast: function() {
            var that = this;

            that.e.find('#sanctumworld_complete').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                $(this).css('display', 'none');
            });

        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#four_beast_hide').unbind('touchend');
                this.e.find('#sanctumworld_complete').unbind('webkitAnimationEnd');
            }
            if (this.fourBeastDom) {
                this.fourBeastDom.undelegate('.beast', 'touchend');
                this.fourBeastDom.remove();
                this.fourBeastDom = null;
            }
            if (this.beastStageDom) {
                this.beastStageDom.undelegate('li.show', 'touchend');
                this.beastStageDom.find('#rs').undelegate('b', 'touchend');
                this.beastStageDom.undelegate('#beast_way_hide', 'touchend');
                this.beastStageDom.undelegate('.beast_div', 'touchend');
                this.beastStageDom.undelegate('#go_gem', 'touchend');
                this.beastStageDom.undelegate('#daliy_reward', 'touchend');
                this.beastStageDom.undelegate('.pop_btn_small', 'touchend');
                this.beastStageDom.remove();
                this.beastStageDom = null;
            }
            if (this.infoDom) {
                this.infoDom.undelegate('#beast_info_hide', 'touchend');
                this.infoDom.undelegate('#gl_btn', 'touchend');
                this.infoDom.undelegate('#attack_btn.pop_btn_middle', 'touchend');
                this.infoDom.undelegate('#rush_btn', 'touchend');
                this.infoDom.remove();
                this.infoDom = null;
            }
        },

        clear: function() {

            xGame.unlock();

            if (this.mapScroller != undefined) {
                this.mapScroller.destroy();
                delete this.mapScroller;
            }

            this.unbindAll();

            this.price = null;
            this.beastid = null;
            this.stage = null;
            this.currentData = null;
            this.atkTimes = null;
            if (this.e) {
                this.e.remove();
                this.e = null;
            }
            if (this.beastScroller) {
                this.beastScroller.destroy();
                this.beastScroller = null;
            }
            if (this.beasttime) {
                clearTimeout(this.beasttime);
                this.beasttime = null;
            }
        },
    };
})();