/* 推塔 */
(function() {
    towerWorldClass = function() {
        this.e;
        this.isshow = false;

        this.nowstar = ''; //当前星路名字
        this.nowstarid = 0; //当前星路ID
        this.starwayDom;
        this.towerDom;

        this.towerData = null; //塔内信息
        this.towerScroller = null; //塔的scroller
        this.nowMissionid = 0; //当前任务ID
        this.lastMissionid = 0; //最近的任务ID
        this.lastMissionk = 0;

        this.currentMid;//当前关卡id
        this.starwayScroller;
        this.isTimeout = [];
    };
    towerWorldClass.prototype = {
        init: function(returndata) {
            var that = this;

            if (returndata && returndata.starid) {
                that.initStarTower(returndata.starid, true);
            } else {
                var render = function(res) {
                    if (res.status == 1) {
                        that.prepareStarPage(); //准备界面
                        that.buildStarPage(res);
                        if (returndata && returndata.clearstar == true) { //星域通关
                            that.clearStar();
                        }
                    } else if (res.status == -1) {
                        xGame.toast.show(lang('lv51_to_open_tower'));
                        xGame.loadWorld('city');
                    } else {
                        xGame.toast.show(lang('can_not_enter_tower'));
                        xGame.loadWorld('city');
                    }
                };
                loadContents({
                    'c': 'tower',
                    'm': 'get_scene_list'
                },
                render, false,
                function() {
                    xGame.loadWorld('city');
                }); //加载数据 
                render = null;
            }

        },

        refreshStars: function() {
            var that = this;

            var render = function(res) {
                if (res.status == 1) {
                    that.buildStarPage(res);
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'get_scene_list'
            },
            render); //加载数据
            render = null;
        },

        prepareStarPage: function() {
            var that = this;

            if (that.e != null) { //已经准备好
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('towerpop_tmpl'));

            that.e = $('#towerpop');
            that.starwayDom = that.e.find('#starway');
            that.towerDom = that.e.find('#towerdom');
            that.e.find('#starway_hide').unbind('touchend').bind('touchend',
            function(e) {

                xGame.loadWorld('city');
                xGame.xsRemove('nowtowermissionid', true);//去掉标志
                e.preventDefault();

            });
            that.e.find('#tower_hide').bind('touchend',
            function() {
                that.hideTowerPage();
            });

			that.towerDom.undelegate('#rush_atk', 'touchend').delegate('#rush_atk', 'touchend',
                function() {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    that.getMopPrice();
            });
			
            that.starwayScroller = new iScroll ('starway_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

			$('#worldloading').removeClass('black').hide();

        },

        getMopPrice: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        xGame.showpop('force', {
                            'type': 'tower',
                            'five_sub': res.info.five_sub,
                            'ten_sub': res.info.ten_sub,
                            'fifty_sub': res.info.fifty_sub,
                            'hundred_sub': res.info.hundred_sub,
                            'atkFunc': function(times) {
                                towerWorld.atkFunc(times);
                            },
                        });
                        break;
                    case -7:
                        xGame.toast.show(lang('towerworld_get_price_fail'));
                        break;
                    case -6:
                        xGame.toast.show(lang('get_activity_info_fail'));
                        break;
                    case -5:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case -4:
                        xGame.toast.show(lang('sanctumworld_unable_rush'));
                        break;
                    case -3:
                        xGame.toast.show(lang('towerworld_get_map_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_guan_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'get_mopup_price',
                'id': that.currentMid
            }, Func);
            Func = null;
        },

        //攻击功能
        atkFunc: function(num) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:
                        that.towerDom.find('.tower_lefttimes').children('em').html(res.times);
                        that.towerData.times = parseInt(res.times);
                        res.info.from = 'tower';
                        forcepop.getReward(res.info, num);
                        player.updateinfo({
                            'gold': parseInt(res.gold)
                        });
                        break;
                    case 3:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case 2:
                        xGame.toast.show(lang('bag_is_full'));
                        break;
                    case -12:
                       xGame.toast.show(lang('towerworld_get_price_fail'));
                        break;
                    case -11:
                        xGame.toast.show(lang('add_soul_fail'));
                        break;
                    case -10:
                        xGame.toast.show(lang('get_soul_info_fail'));
                        break;
                    case -9:
                        xGame.toast.show(lang('deduct_gold_fail'));
                        break;
                    case -8:
                        xGame.toast.show(lang('deduct_gold_fail'));
                        break;
                    case -7:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case -6:
                        xGame.toast.show(lang('checkpoints_close'));
                        break;
                    case -5:
                        xGame.toast.show(lang('checkpoints_close'));
                        break;
                    case -4:
                        xGame.toast.show(lang('towerworld_get_map_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_guan_info_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_activity_info_fail'));
                        break;
                    case -1:
                        xGame.toast.show(lang('lv_not_enough'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'mop_up',
                'id': that.currentMid,
                'times': num
            }, Func);
            Func = null;
            
        },
        
        buildStarPage: function(res) {
            var that = this;

            //添加关卡
            var html = '';
            for (var i in res.scene) {
                html += that.buildStarlist(res.scene[i]);
            }
            that.starwayDom.html(html);

            //绑定事件
            that.starwayDom.undelegate('.hole', 'touchend').delegate('.hole', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var starid = $(this).attr('starid');
                if (starid) {
                    that.initStarTower(starid);
                } else {
                    xGame.toast.show(lang('please_chose_tower'));
                    return false;
                }
            });

        },

        buildStarlist: function(data) {
            var html = '';
            if (data.openstatus == 1) {
                html += '<div class="holebg" id="holebg' + data.id + '"></div><div class="hole btn" id="holebtn' + data.id + '" starid="' + data.id + '"><div class="startname"><p></p></div>' + (data.killed == 1 ? '<div class="doneflag"></div>': '') + '</div>';
            }
            return html;
        },

        /* 进入星塔 */
        initStarTower: function(starid, isfirst) {
            var that = this;

            var starRender = function(res) {
                var errormsg = '';
                if (res.status == -1) {
                    errormsg = lang('lv61_to_enter');
                } else if (res.status == -2 || res.status == -3 || res.status == -5) {
                    errormsg = lang('get_tower_info_fail');
                } else if (res.status == -4) {
                    errormsg = lang('current_tower_not_open');
                } else if (res.status != 1) {
                    errormsg = lang('can_not_enter_current_tower');
                } else {

                    res.listlength = res.list.length; //先存一下数组长度
                    //将数组转为用的对象
                    res.list = that.arr2obj(res.list);

                    that.towerData = res;
                    that.nowstarid = starid;
                    that.nowstar = that.getStarname(parseInt(starid));
                    if (isfirst == true) {
                        that.prepareStarPage(); //准备界面
                        that.e.find('#tower_hide').one('touchend',
                        function() {
                            that.refreshStars();
                        });
                    }
                    that.prepareTowerPage();
                    that.buildTowerPage();

                }
                if (errormsg != '') {
                    xGame.toast.show(errormsg);
                    if (isfirst == true) {
                        xGame.loadWorld('city');
                    }
                    return false;
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'get_mission_list',
                's': starid
            },
            starRender); //加载数据
            starRender = null;
        },

        arr2obj: function(array) {
            var that = this;

            var obj = {};
            for (var i = 0; i < array.length; i++) {
                array[i]['k'] = i;
                obj[array[i].id] = array[i];
            }

            return obj;
        },

        getStarname: function(starid) {
            var name = '';
            switch (starid) {
            case 1:
                name = lang('star1');
                break;
            case 2:
                name = lang('star2');
                break;
            case 3:
                name = lang('star3');
                break;
            case 4:
                name = lang('star4');
                break;
            case 5:
                name = lang('star5');
                break;
            case 6:
                name = lang('star6');
                break;
            case 7:
                name = lang('star7');
                break;
            }
            return name;
        },

        /* 构建推塔页面 */
        prepareTowerPage: function() {
            var that = this;

            //绑定界面其他按钮事件
            that.towerDom.find('#tower_reward_btn').unbind('touchend').bind('touchend',
            function() { //每日奖励
                miscpop.towerRewardbox();
            });
            that.towerDom.find('#tower_soul_btn').unbind('touchend').bind('touchend',
            function() { //去将魂
                xGame.loadWorld('soul');
                xGame.xsRemove('nowtowermissionid', true);
            });
            that.towerDom.find('#tower_buy_btn').unbind('touchend').bind('touchend',
            function() { //购买次数
                that.buytimesConfirm();
            });

            that.towerScroller = new iScroll('towerpop_scroller_wrap', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: true
            });

            that.e.find('#starway_scroller').css('display', 'none');
            that.e.find('#starway_hide').css('display', 'none');
            that.towerDom.css('display', 'block');
            that.e.find('#tower_hide').css('display', 'block');
        },

        buildTowerPage: function() {
            var that = this;

            var html = '';
            var local_missionid = 0;//上次所在的任务ID
            if(xGame.xsGet('nowtowermissionid', true)) {
                local_missionid = xGame.xsGet('nowtowermissionid', true);
            }
            for (var i in that.towerData.list) {
                var _nowmission = that.towerData.list[i];
                html += that.buildTowerMission(_nowmission);

                if (local_missionid) {
                    if(local_missionid == _nowmission.id) {
                        that.lastMissionid = _nowmission.id;//最近的任务ID
                        that.lastMissionk = _nowmission.k;
                    }
                } else if(_nowmission.openstatus == 1) {
                    that.lastMissionid = _nowmission.id;//最近的任务ID
                    that.lastMissionk = _nowmission.k;
                }
            }
            that.towerDom.find('#towerpop_scroller').children('ul').html(html);
            that.towerDom.find('#towerpop_scroller').css({
                'height': that.towerData.listlength * 121 + 'px'
            });
            that.isTimeout.push(setTimeout(function() {
                that.towerScroller.refresh();
                //滑动到当前位置
                if (that.lastMissionk > 2) {
                    var moveHeight = (parseInt(that.lastMissionk) - 2) * 121;
                    /*加上一个li的高度就不会滑动时闪一下了*/
                    that.towerScroller.scrollTo(0, (-moveHeight + 119));
                    //$(that.towerScroller.scroller).animate({"translate3d":'0px, -' + moveHeight + 'px, 0px'}, 300, 'ease-in');
                }
                that.isTimeout.push(setTimeout(function() {
                    that.towerDom.find('#towerpop_scroller_cover').css('display', 'none');
                },
                150));
            },
            100));

            //绑定左侧列表事件
            that.towerDom.undelegate('.startowers.open', 'click').delegate('.startowers.open', 'click',
            function() {
                var missionid = $(this).attr('missionid');
                if (missionid && missionid != that.nowMissionid) {
                    that.viewMission(missionid);
                }
            });

            //右侧数据
            that.viewMission(that.lastMissionid);
            that.towerDom.find('.tower_lefttimes').children('em').html(that.towerData.times); //剩余次数
            //领奖按钮
            /*
            if(that.towerData.get_reward == 1) {
                that.towerDom.find('#tower_reward_btn').hide();
            } else {
                that.towerDom.find('#tower_reward_btn').show();
            }*/

        },

        refreshTowerPage: function() {
            var that = this;

            var refreshRender = function(res) {
                var errormsg = '';
                if (res.status == -1) {
                    errormsg = lang('lv61_to_enter');
                } else if (res.status == -2 || res.status == -3 || res.status == -5) {
                    errormsg = lang('get_tower_info_fail');
                } else if (res.status == -4) {
                    errormsg = lang('current_tower_not_open');
                } else if (res.status != 1) {
                    errormsg = lang('can_not_enter_current_tower');
                } else {

                    res.listlength = res.list.length; //先存一下数组长度
                    //将数组转为用的对象
                    res.list = that.arr2obj(res.list);

                    that.towerData = res;
                    that.buildTowerPage();
                }
                if (errormsg != '') {
                    xGame.toast.show(errormsg);
                    return false;
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'get_mission_list',
                's': that.nowstarid
            },
            refreshRender); //加载数据
            refreshRender = null;
        },

        /* 构建每层塔 */
        buildTowerMission: function(data) {
            var that = this;

            var html = '<li missionid="' + data.id + '" class="startowers ' + (data.openstatus == 1 ? 'open': '') + '" id="towermission' + data.id + '">';
            html += '<em></em>';
            html += '<span class="img" style="background:url(' + xGame.avatar(data.image) + ') 0 0 no-repeat;"></span>';
            html += '<span class="name">' + that.nowstar + '</span>';
            html += '<span class="title">' + lang('checkpoints') + data.id + '：' + data.name + '</span>';
            html += '<span class="info">' + lang('level') + '：' + data.level + ' (' + helper.getEnemyType(data.type) + ')</span>';
            html += '<span class="locked"></span>';
            if (data.killed == 1) {
                html += '<span class="killed"></span>';
            }

            if (parseInt(data.firstkill) == 1) {
                html += '<span class="firstkill"></span>';
            }
            html += '</li>';
            return html;
        },

        /* 查看某一层 */
        viewMission: function(missionid) {
            var that = this;

            var missiondata = that.towerData.list[missionid];

            if (missionid != that.towerData.now.id) {
                var missionRender = function(res) {
                    if (res.status == 1) {
                        that.towerDom.find('.startowers.now').removeClass('now');
                        $('#towermission' + missionid).addClass('now');
                        that.buildRight(missiondata, res.mission);
                        //保存当前missionid，用于战斗以后返回
                        xGame.xsSet('nowtowermissionid', missionid, true);

                        that.currentMid = res.mission.id;
                        if (res.mop_up == 1) {
                            that.towerDom.find('#rush_atk').css('display', 'block');
                        } else {
                            that.towerDom.find('#rush_atk').css('display', 'none');
                        }

                    } else {
                        xGame.toast.show(lang('can_not_view_this_floor'));
                    }
                };
                loadContents({
                    'c': 'tower',
                    'm': 'get_mission_info',
                    'id': missionid
                },
                missionRender); //加载数据
                missionRender = null;
            } else {
                xGame.xsRemove('nowtowermissionid', true);//清理最后选择的星路位置
                that.towerDom.find('.startowers.now').removeClass('now');
                $('#towermission' + missionid).addClass('now');

                that.buildRight(missiondata, that.towerData.now);
                 that.currentMid = missiondata.id;
                if (missiondata.killed == 1 && missiondata.firstkill == 0) {
                    that.towerDom.find('#rush_atk').css('display', 'block');
                } else {
                    that.towerDom.find('#rush_atk').css('display', 'none');
                }
            }
        },

        /* 根据某一层的信息 构建右方信息界面 */
        buildRight: function(data, floorInfo) {
            var that = this;
            var hero_soul = floorInfo.hero_soul;
            var soulnum = floorInfo.soul;

            that.nowMissionid = data.id;
            //avatar
            that.towerDom.find('#towerpop_info_wrap').children('.bigavatar').css({
                'background': 'url(' + xGame.avatar(data.image, true) + ') 0 0 no-repeat'
            });
            //name
            that.towerDom.find('#towerpop_info_wrap').children('.name').html(lang('checkpoints') + data.id + '：' + data.name);
            //level
            that.towerDom.find('#towerpop_info_wrap').children('.level').html(lang('level') + '：' + data.level + ' (' + helper.getEnemyType(data.type) + ')');

            // 是否首杀
            if (parseInt(floorInfo.firstkill) == 1) {
                that.towerDom.find('#towerpop_info_wrap').children('.soul').html(lang('original_soul') + ' +' + floorInfo.first_soul_num + lang('shousha'));
            } else {
                that.towerDom.find('#towerpop_info_wrap').children('.soul').html(lang('original_soul') + ' +' + soulnum);
            }

            if (hero_soul.id > 0) {
                //将魂图片
                that.towerDom.find('#towerpop_info_wrap').find('#gsoul_pic').attr('class','img q' + hero_soul.quality);
                that.towerDom.find('#towerpop_info_wrap').find('#gsoul_pic').html('<img src="http://img2.hanjiangsanguo.com/hjimg/pop/soul_' + hero_soul.image + '.png' + '" />');
                that.towerDom.find('#towerpop_info_wrap').find('#gsoul_name').css({
                    'color': helper.getItemColor(parseInt(hero_soul.quality))
                }).html(hero_soul.name);
                that.towerDom.find('#towerpop_info_wrap').find('#gsoul_power').html(helper.getSoulTypeName(parseInt(hero_soul.type)) + ' +' + hero_soul.addition);
                that.towerDom.find('#towerpop_info_wrap').children('.info').show();
            } else {
                that.towerDom.find('#towerpop_info_wrap').children('.info').hide();
            }

            //按钮
            if (parseInt(data.id) > parseInt(that.towerData.now.id)) {
                that.towerDom.find('#tower_attack_btn').attr('class', 'pop_btn_middle_disable attack').unbind('touchend');
            } else {
                that.towerDom.find('#tower_attack_btn').attr('class', 'pop_btn_middle attack').unbind('touchend').bind('touchend',
                function() { //进攻
                    that.fight(data.id);
                });
            }

            that.towerDom.find('#tower_strategy_btn').unbind('touchend').bind('touchend',
            function() { //策略
                xGame.showpop('strategy', {
                    "name": data.name,
                    "c": "tower",
                    "m": "get_report_list",
                    "id": data.id
                });
            });
        },

        /* 购买次数 价格确认框 */
        buytimesConfirm: function() {
            var that = this;

            var priceRender = function(res) {
                if (res.status == 1 && res.price > 0) {
                    xGame.confirm.show(lang('notice'), lang('tower_ask', [res.price, that.towerData.times]), 2,
                    function() {
                        that.buytimesProcess();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
                } else {
                    xGame.toast.show(lang('can_not_buy_times_yet'));
                    return false;
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'get_price'
            },
            priceRender);
            priceRender = null;
        },

        /* 开始购买次数 */
        buytimesProcess: function() {
            var that = this;

            var buyRender = function(res) {
                if (res.status == 1) { //购买成功
                    $('#buy_towertimes_price').html(res.next);
                    that.towerData.times = parseInt(res.times);
                    player.updateinfo({
                        'gold': parseInt(res.gold)
                    });
                    that.towerDom.find('.tower_lefttimes').children('em').html(res.times); //剩余次数
                    $('#buy_towertimes').html(res.times);
                } else if (res.status == 2) {
                    xGame.goPay(lang('gold_not_enough_for_buying'));
                } else {
                    xGame.toast.show(lang('buy_fail_try_later'));
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'buy'
            },
            buyRender);
            buyRender = null;
        },

        fight: function(missionid) {
            var that = this;

            if (that.towerData.times <= 0) {
                that.buytimesConfirm();
                return false;
            }

            var fightRender = function(res) {
                var errormsg = '';
                if (res.status == 2) {
                    errormsg = lang('your_bag_is_full');
                } else if (res.status == 3) {
                    errormsg = lang('you_have_no_tower_times');
                } else if (res.status == -6) {
                    errormsg = lang('checkpoint_not_open');
                } else if (res.status != 1) {
                    errormsg = lang('wait_for_atk_checkpoint');
                }

                if (errormsg != '') { //有错误
                    xGame.toast.show(errormsg);
                } else {
                    miscpop.enterBattleFromTower(res, missionid);
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'pk',
                'id': missionid
            },
            fightRender);
            fightRender = null;

        },

        /* 通关星域 */
        clearStar: function() {
            var that = this;

            that.e.find('#towerpop_complete').css('display', 'block').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                $(this).css('display', 'none');
                towerWorld.hideTowerPage();
                towerWorld.refreshStars();
            });

        },

        _clearTowerData: function() {
            if (this.towerDom) {
                this.towerDom.undelegate('.startowers', 'click');
            }
            this.nowstar = '';
            this.nowstarid = 0;
            this.towerData = null;
            this.nowMissionid = 0;
            this.lastMissionid = 0;
            this.lastMissionk = 0;
            if (this.towerScroller) {
                this.towerScroller.destroy();
                delete this.towerScroller;
                this.towerDom.find('#towerpop_scroller_cover').css('display', 'block');
            }
        },

        hideTowerPage: function() {
            xGame.xsRemove('nowtowermissionid', true);
            this.e.find('#starway_scroller').css('display', 'block');
            this.e.find('#starway_hide').css('display', 'block');
            this.towerDom.css('display', 'none');
            this.e.find('#tower_hide').css('display', 'none');
            this._clearTowerData();
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#starway_hide').unbind('touchend');
                this.e.find('#tower_hide').unbind('touchend');
                this.e.find('#towerpop_complete').unbind('webkitAnimationEnd');
                this.e.remove();
                this.e = null;
            }
            if (this.towerDom) {
                this.towerDom.undelegate('#rush_atk', 'touchend');
                this.towerDom.find('#tower_reward_btn').unbind('touchend');
                this.towerDom.find('#tower_soul_btn').unbind('touchend');
                this.towerDom.find('#tower_buy_btn').unbind('touchend');
                this.towerDom.find('#tower_attack_btn').unbind('touchend');
                this.towerDom.find('#tower_strategy_btn').unbind('touchend');
                this.towerDom.undelegate('.startowers.open', 'click');
                this.towerDom.undelegate('.startowers', 'click');
                this.towerDom.remove();
                this.towerDom = null;
            }
            if (this.starwayDom) {
                this.starwayDom.undelegate('.hole', 'touchend');
                this.starwayDom.remove();
                this.starwayDom = null;
            }
        },

        clear: function() {
            xGame.unlock();
            if(this.e) {
                this._clearTowerData();
                this.currentMid = null;
            }

            if (this.starwayScroller != null) {
                this.starwayScroller.destroy();
            }
            this.starwayScroller = null;
            this.unbindAll();
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            player.reloadInfo();
        },
    };
})();