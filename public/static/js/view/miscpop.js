/* 杂项弹窗 */
(function() {
    miscPopClass = function() {
        this.e;
        this.clearflower = false;
        this.forceScroller;
        this.htmlData;
        this.SZF_cardlist = {//允许使用的充值卡面值列表
            mobile: [10, 20, 30, 50, 100, 300, 500],
            unicom: [20, 30, 50, 100, 300, 500],
            telcom: [20, 30, 50, 100],
        };
    };
    miscPopClass.prototype = {

        show: function(data) {
            var that = this;

            for (var i in data) {
                switch (i) {
                case 'enemyInfo':
                    that.enemyInfo(data[i]);
                    break;
                case 'treasureEnemyInfo':
                    that.treasureEnemyInfo(data[i]);
                    break;
                case 'battleResultFromTreasure':
                    that.battleResultFromTreasure(data[i]);
                    break;
                case 'battleResult':
                    that.battleResult(data[i]);
                    break;
                case 'showRewardbox':
                    that.showRewardbox(data[i]);
                    break;
                case 'battleResultFromBoss':
                    that.battleResultFromBoss(data[i]);
                    break;
                }
            }

        },

        showRewardbox: function(data, type, sharedata) {
            var rawardbox_wrap = $('#rewardbox_wrap');

            var showResult = function() {
                setTimeout(function() {
                    rawardbox_wrap.find('.box').removeClass('open');
                    rawardbox_wrap.hide();
                    if (type == undefined) {//旧版本，慢慢会被废弃
                        miscpop.battleResult(data);
                    } else {
                        if(type == 'battle') {//普通战斗
                            miscpop.battleResult(data, sharedata);
                        } else if (type == 'throne') {//如果是王座
                            miscpop.battleResultFromThrone(data, data.info.reward, true);
                        } else if (type == 'beauty') {
                            miscpop.battleResultFromBeauty(data);
                        }
                    }

                },
                400);
            };

            var downDone = function() {
                //箭头
                rawardbox_wrap.find('.arrow').fadeIn();
                //噗
                var movieclip = new movieClipClass({
                    "img": staticUrl + 'img/battle/reward_box_shadow_anim.png',
                    "width": 480,
                    "height": 100,
                    "x": -130,
                    "y": 330,
                    "loop": false,
                    "endDestory": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 50
                    },
                    {
                        rect: [0, 100],
                        interval: 50
                    },
                    {
                        rect: [0, 200],
                        interval: 50
                    },
                    {
                        rect: [0, 300],
                        interval: 50
                    },
                    {
                        rect: [0, 400],
                        interval: 50
                    },
                    {
                        rect: [0, 500],
                        interval: 50
                    },
                    {
                        rect: [0, 600],
                        interval: 50
                    },
                    {
                        rect: [0, 700],
                        interval: 50
                    },
                    {
                        rect: [0, 800],
                        interval: 50
                    },
                    ],
                });
                rawardbox_wrap.append(movieclip.obj);
                movieclip.play();

            };

            var openBox = function() {
                var movieclip = new movieClipClass({
                    "img": staticUrl + 'img/battle/resutl_box_stars.png',
                    "width": 261,
                    "height": 233,
                    "x": -30,
                    "y": 200,
                    "loop": false,
                    "endDestory": true,
                    "callback": showResult,
                    "frames": [{
                        rect: [0, 0],
                        interval: 60
                    },
                    {
                        rect: [261, 0],
                        interval: 60
                    },
                    {
                        rect: [522, 0],
                        interval: 60
                    },
                    {
                        rect: [783, 0],
                        interval: 60
                    },
                    {
                        rect: [1044, 0],
                        interval: 60
                    },
                    {
                        rect: [1305, 0],
                        interval: 60
                    },
                    {
                        rect: [1566, 0],
                        interval: 60
                    }],
                });
                rawardbox_wrap.append(movieclip.obj);
                movieclip.play();
            };

            rawardbox_wrap.find('.box').unbind('webkitAnimationEnd').bind('webkitAnimationEnd', downDone).unbind('touchend').bind('touchend',
            function(e) {
                $(this).unbind('webkitAnimationEnd').unbind('touchend').addClass('open');
                rawardbox_wrap.find('.arrow').hide();
                openBox();
                e.preventDefault();
                e.stopPropagation();
            });
            rawardbox_wrap.show();

        },

        itemfly2bag: function(getitem, callback) {
            var right = ($.os.ipad == true ? 1024 : (isIPhone5 ? 1136 : 960)) / 2 - 60;
            var bottom = $('body').height() / 2 - 60;
            if (getitem.url != undefined) {
                var item_dom = $('<div class="flyitem" style="z-index:100;background:url(' + getitem.url + ') 50% 50% no-repeat;right:' + right + 'px;bottom:' + bottom + 'px;color:' + helper.getItemColor(getitem.quality) + ';"></div>');
            } else {
                var item_dom = $('<div class="flyitem" style="z-index:100;background:url(' + xGame.itemurl(getitem.itemid) + ') 50% 50% no-repeat;right:' + right + 'px;bottom:' + bottom + 'px;color:' + helper.getItemColor(getitem.quality) + ';"></div>');
            }
            $('body').append(item_dom);

            xGame.doAnimate(item_dom, 'fadeInScale', '0.5s',
            function() {
                setTimeout(function() {
                    /*
                    var offset = $('#menu_list').find('.menu_hero').offset();
                    var _right = ($.os.ipad == true ? 1024 : 960) - offset.left - 100;
                    item_dom.animate({"right":_right, "bottom":0}, 600, 'ease-out', function() {
                        item_dom.remove();
                        item_dom = null;
                        if(callback) {
                            callback();
                            callback = null;
                        }
                    });
                    */

                    xGame.doAnimate(item_dom, 'itemfly2bag' + (xGame.menustatus == false ? 'menuhide': ''), '0.6s',
                    function() {
                        item_dom.remove();
                        item_dom = null;
                        if (callback) {
                            callback();
                            callback = null;
                        }
                    });
                },
                200);
            });

            right = null;
            bottom = null;
        },

        createBombFlower2: function() {
            var that = this;

            var newFlower = function(type, callback) {
                if (that.clearflower == true) {
                    that.clearflower = false;
                    newFlower = null;
                    return;
                }
                var cfg = {};
                if (type == 1) {
                    cfg = {
                        "x": 0,
                        "y": -100
                    };
                } else if (type == 2) {
                    cfg = {
                        "x": 80,
                        "y": 40
                    };
                } else {
                    cfg = {
                        "x": 220,
                        "y": -80
                    };
                }

                var mc = new movieClipClass({
                    "img": staticUrl + 'img/pop/bombflower' + type + '.png',
                    "width": 260,
                    "height": 260,
                    "x": cfg.x,
                    "y": cfg.y,
                    "loop": false,
                    "endDestory": true,
                    "callback": callback,
                    "frames": [{
                        rect: [0, 0],
                        interval: 80
                    },
                    {
                        rect: [260, 0],
                        interval: 80
                    },
                    {
                        rect: [520, 0],
                        interval: 80
                    },
                    {
                        rect: [0, 260],
                        interval: 80
                    },
                    {
                        rect: [260, 260],
                        interval: 80
                    },
                    {
                        rect: [520, 260],
                        interval: 80
                    },
                    ]
                });
                mc.appendTo($('#battleresult_wrap'));
                mc.play();
            }
            setTimeout(function() {
                newFlower(1,
                function() {
                    newFlower(2,
                    function() {
                        newFlower(3,
                        function() {
                            newFlower = null;
                        })
                    });
                });
            },
            500);
        },

        createBombFlower: function() {
            var that = this;

            if (that.clearflower == true) {
                that.clearflower = false;
                return;
            }

            var mc = new movieClipClass({
                "img": staticUrl + 'img/battle/battle_result_bomb.png',
                "width": 150,
                "height": 150,
                "x": 160,
                "y": -70,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 60
                },
                {
                    rect: [150, 0],
                    interval: 60
                },
                {
                    rect: [300, 0],
                    interval: 60
                },
                {
                    rect: [450, 0],
                    interval: 60
                },
                {
                    rect: [600, 0],
                    interval: 60
                },
                {
                    rect: [0, 150],
                    interval: 60
                },
                {
                    rect: [150, 150],
                    interval: 60
                },
                {
                    rect: [300, 150],
                    interval: 60
                },
                {
                    rect: [450, 150],
                    interval: 60
                },
                {
                    rect: [600, 150],
                    interval: 60
                },
                ]
            });
            mc.appendTo($('#battleresult_wrap'));
            mc.play();
            xGame.doAnimate($('#battleresult_wrap').find('.flag'), 'title_shake', '0.4s',
            function() {
                //mc.play();
                mc = null;
            });
        },

        destoryAllBombFlower: function() {
            this.clearflower = true;
        },

        enemyInfo: function(missionid, openstatus) {
            var that = this;
            var times = 1;
            var initRender = function(res) {

                /*
                if(res.status == -3) {
                    that = null;
                    initRender = null;

                    xGame.toast.show('您还不能攻击此将，请先打败前面的敌人！');
                    xGame.unlock();
                    return false;
                }
                */

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                var that_dom = xGame.loadPopTmpl('enemy');
                var unbindFunc = function (that_dom) {
                    that_dom.find('.content').find('.hidebtn').unbind('touchend');
                    that_dom.find('#force_btn').unbind('touchend');
                    that_dom.find('#attack_force_btn').unbind('touchend');
                    that_dom.find('#attack_normal_btn').unbind('touchend');
                    that_dom.find('#attack_normal_btn').unbind('touchend');
                    that_dom.find('#strategy_btn').unbind('touchend');
                    var force_dom = $('#force');
                    force_dom.find('.result').find('.attack_btns').unbind('touchend');
                    force_dom.find('.list').find('span').unbind('touchend');
                    force_dom.find('.force_hidebtn').unbind('touchend');
                    force_dom = null;
                    that_dom = null;
                };

                that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    miscpop.hide('enemypop');
                    e.preventDefault();
                    e.stopPropagation();
                    unbindFunc(that_dom);
                    unbindFunc = null;
                });

                /* 第二步 构建数据列表等 */
                var _avatarUrl = '';
                if (res.info.image > 0) { //可收复
                    _avatarUrl = 'url(' + xGame.avatar(res.info.image) + ') 50% 50% no-repeat';
                } else {
                    if (res.info.type > 2) { //大将
                        _avatarUrl = 'url(' + mapAssetUrl(staticUrl + 'img/map/boss1.png') + ') 50% 50% no-repeat';
                    } else { //小兵
                        _avatarUrl = 'url(' + mapAssetUrl(staticUrl + 'img/map/enemy1.png') + ') 50% 50% no-repeat';
                    }
                }
                that_dom.find('.avatarbox').children('.avatar').css({
                    'background': _avatarUrl
                });

                that_dom.find('.isboss').addClass('npc' + res.info.type);
                //that_dom.find('.avatarbox').children('p').text(res.info.name);
                that_dom.find('.herolv').children('span').text(res.info.name + '(Lv.' + res.info.level + ')');
                that_dom.find('.lefttimes').children('span').text(parseInt(res.info.maxtimes) == 0 ? lang('unlimited') : (res.info.nowmaxtimes + lang('times')));
                that_dom.find('.headexp').children('span').text('+' + (res.info.first == 1 ? (res.info.firstkillexp + lang('shousha')) : res.info.exp));
                if (res.info.add_type == 1) {
                    that_dom.find('.heroexp').children('b').text(lang('general_exp'));
                } else {
                    that_dom.find('.heroexp').children('b').text(lang('reputation'));
                }
                that_dom.find('.heroexp').children('span').text('+' + (res.info.first == 1 ? (res.info.gfirstkillexp + lang('shousha')) : res.info.gexp));
                if (res.info.recruitrate > 0) { //可招募
                    if (res.info.own_general == 0) {
                        that_dom.find('.enjoin_tips').show();
                    } else {
                        that_dom.find('.own_general').show();
                    }
                } else {
                    if (res.info.own_general == 1) {
                        that_dom.find('.own_general').show();
                    }
                }
                var lostitem_text = '';
                if (res.info.gettype > 0) {
                    if (res.info.gettype == 1 || res.info.gettype == 2 || res.info.gettype == 5 || res.info.gettype == 6) { //道具
                        lostitem_text = res.info.getitemname;
                    } else if (res.info.gettype == 3) { //银币
                        lostitem_text = res.info.getvalue + '' + lang('silver');
                    } else if (res.info.gettype == 4) { //元宝
                        lostitem_text = res.info.getvalue + ''  + lang('gold');
                    }
                }
                if (lostitem_text != '') {
                    that_dom.find('.lostitems').show();
                    that_dom.find('.lostitems').children('span').text(lostitem_text);
                    that_dom.find('.lostitems').children('em').text(res.info.getrate == 0 ? '': (res.info.getrate == 1 ? lang('xiao_gailv') : lang('da_gailv')));
                    if (res.info.quality && res.info.quality > 0) {
                        that_dom.find('.lostitems').children('span').css({
                            'color': helper.getItemColor(res.info.quality)
                        });
                    }
                } else {
                    that_dom.find('.lostitems').hide();
                }

                //判断显示扫荡按钮
                if (res.mop_up == 1) {
                    //显示扫荡按钮
                    that_dom.find('#force_btn').show();
                    that_dom.find('#force_btn').unbind('touchend').bind('touchend',
                    function(e) {
                        unbindFunc(that_dom);
                        unbindFunc = null;
                        e.preventDefault();
                        e.stopPropagation();
                        var force_dom = $('#force');
                        force_dom.data('times', 5);//将价格和次数保存在DOM里，方便查询
                        force_dom.data('price', res.info.five_sub);
                        force_dom.show();
                        miscpop.htmlData = '';
                        /*2013-9-24
                        ios7的时候先生成iscroll的话前几下就渲染不出来了，不知道为什么。
                        所以在高度超过时再生成，其实也可以节省效率
                        miscpop.forceScroller = new iScroll('force_scroller', {
                            momentum: false,
                            hScrollbar: false,
                            vScrollbar: true
                        });
                        miscpop.forceScroller.refresh();
                        */
                        //界面数据
                        force_dom.find('.type1').attr('price', res.info.five_sub);
                        force_dom.find('.type1').find('.gold').html(res.info.five_sub + '' + lang('gold'));
                        force_dom.find('.type2').attr('price', res.info.ten_sub);
                        force_dom.find('.type2').find('.gold').data('gold', res.info.ten_sub).html(res.info.ten_sub + '' + lang('gold'));
                        force_dom.find('.type3').attr('price', res.info.fifty_sub);
                        force_dom.find('.type3').find('.gold').data('gold', res.info.fifty_sub).html(res.info.fifty_sub + '' + lang('gold'));
                        force_dom.find('.type4').attr('price', res.info.hundred_sub);
                        force_dom.find('.type4').find('.gold').data('gold', res.info.hundred_sub).html(res.info.hundred_sub + '' + lang('gold'));
                        //关闭按钮
                        force_dom.find('.force_hidebtn').unbind('touchend').bind('touchend',
                        function(e) {
                            force_dom.find('.force_hidebtn').unbind('touchend');
                            miscpop.hide('enemypop');
                            if (miscpop.forceScroller != null) {
                                miscpop.forceScroller.destroy();
                            }
                            miscpop.forceScroller = null;
                            miscpop.htmlData = null;
                            force_dom.hide();
                            force_dom = null;
                            e.preventDefault();
                            e.stopPropagation();
                        });
                        //选中按钮
                        force_dom.find('.list').find('span').unbind('touchend').bind('touchend', function(e) {
                            force_dom.find('span').removeClass('select');
                            $(this).addClass('select');
                            force_dom.data('times', $(this).attr('value'));
                            force_dom.data('price', $(this).attr('price'));
                            e.preventDefault();
                            e.stopPropagation();
                        });
                        //攻击按钮
                        force_dom.find('.result').find('.attack_btns').unbind('touchend').bind('touchend',
                        function(e) {
                            var times = parseInt(force_dom.data('times'));
                            var force_price = parseInt(force_dom.data('price'));
                            console.log(times, force_price);
                            if(force_price > 0) {
                                xGame.confirm.show(lang('notice'), lang('force_confirm', [force_price]), 2,
                                function() {
                                    xGame.confirm.hide();
                                    miscpop.continuousPlay({
                                        l: player.info.missionlevel,
                                        s: player.info.missionstage,
                                        id: missionid,
                                        times: times,
                                    });
                                },
                                function() {
                                    xGame.confirm.hide();
                                },
                                lang('ok'), lang('close'));
                            } else {
                                miscpop.continuousPlay({
                                    l: player.info.missionlevel,
                                    s: player.info.missionstage,
                                    id: missionid,
                                    times: times,
                                });
                            }
                            
                            e.preventDefault();
                            e.stopPropagation();
                        });
                    });
                } else {
                    //扫荡按钮隐藏
                    that_dom.find('#force_btn').hide();
                }
                if (openstatus == 1) { //正常状态
                    //强攻
                    if (res.info.maxtimes != 0 && res.info.nowmaxtimes == 0) {
                        that_dom.find('#attack_normal_btn').hide();
                        that_dom.find('#attack_force_btn').show();
                        that_dom.find('#attack_force_cost').text(res.info.coin + '' + lang('gold')).show();
                        that_dom.find('#attack_force_btn').unbind('touchend').bind('touchend',
                        function(e) {
                            miscpop.enterBattle({
                                l: player.info.missionlevel,
                                s: player.info.missionstage,
                                id: missionid,
                                gold: res.info.coin
                            });
                            e.preventDefault();
                            e.stopPropagation();
                        });
                    } else {
                        that_dom.find('#attack_normal_btn').unbind('touchend').bind('touchend',
                        function(e) {
                            miscpop.enterBattle({
                                l: player.info.missionlevel,
                                s: player.info.missionstage,
                                id: missionid
                            });
                            e.preventDefault();
                            e.stopPropagation();
                        });
                    }
                } else { //无法进攻
                    that_dom.find('#attack_normal_btn').unbind('touchend').attr({
                        'class': 'btn pop_btn_middle_disable attack_btns'
                    }).bind('touchend',
                    function() {
                        xGame.toast.show(lang('please_battle_front'));
                    });
                }
                that_dom.find('#strategy_btn').unbind('touchend').bind('touchend',
                function() {
                    xGame.showpop('strategy', {
                        "name": res.info.name,
                        "c": "map",
                        "m": "report",
                        "l": player.info.missionlevel,
                        "s": player.info.missionstage,
                        "id": missionid
                    });
                });
                /* 第四步 展示弹窗 */
                that_dom.show();
                //that_dom = null;
                //that = null;
                initRender = null;

                xGame.unlock();

                /* 新手引导 */
                //小兵信息弹窗里的攻击
                xGame.showGuide([2, 5, 101, 104, 107, 201, 204, 207, 301, 304, 602, 605, 612, 615, 701, 704, 707, 710, 713, 720, 723, 1328, 1923]);

            };
            loadContents({
                'c': 'map',
                'm': 'mission',
                'l': player.info.missionlevel,
                's': player.info.missionstage,
                'id': missionid
            },
            initRender);
        },

        /* 进入战斗 */
        enterBattle: function(data) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                /*
                -1  军令不足
                -2  cd时间未清除
                -3  没有找到地图记录
                -4  场景未开启
                -5  关卡未开启
                -6  没有找到关卡数据
                -7  元宝不足
                */
                if (res.status) {
                    if (res.status == -1) { //没有军令
                        xGame.buyActConfirm();
                        //xGame.toast.show("您没有军令了，请先购买！");
                        xGame.unlock();
                        miscpop.hide('enemypop');
                        return;
                    } else if (res.status == -2) { //清除CD
                        if (xGame.guide == true && xGame.xsGet(player.info.uid + 'firstbattlecd') != '1' && 0) {
                            xGame.xsSet(player.info.uid + 'firstbattlecd', '1');
                            guide.showNpc(lang('clear_cd_ask'),
                            function() {
                                guide.createSimplePoint($('#header').find('#battle_timer'), null, false, 'touchend');
                            });
                        } else {
                            xGame.toast.show(lang('please_clear_battle_cd'));
                            guide.createSimplePoint($('#header').find('#battle_timer'), null, false, 'touchend');
                            setTimeout(function() {
                                $('#guide_arrow').remove();
                            },
                            2000);
                        }
                        xGame.unlock();
                        miscpop.hide('enemypop');
                        return;
                    } else if (res.status == -7) {
                        xGame.unlock();
                        xGame.goPay(lang('your_gold_is_not_enough'));
                        return;
                    } else {
                        xGame.unlock();
                        var error_msg = '';
                        switch (res.status) {
                        case - 3 : error_msg = lang('current_map_exist');
                            break;
                        case - 4 : error_msg = lang('current_shili_close');
                            break;
                        case - 5 : error_msg = lang('battle_front_first');
                            break;
                        case - 6 : error_msg = lang('current_no_data');
                            break;
                        }
                        if (error_msg != '') {
                            miscpop.hide('enemypop');
                            xGame.toast.show(error_msg);
                            return;
                        }
                    }
                }

                player.taskaction(6);

                $('#worldloading').addClass('black').show();

                /* 开始进入战斗界面 */
                miscpop.hide('enemypop');

                setTimeout(function() {
                    xGame.loadBattle(res,
                    function() {
                        if (battleWorld.resultdata.win > 0 && battleWorld.resultdata.gettype > 0) {
                            miscpop.showRewardbox(battleWorld.resultdata, 'battle', res.share);
                        } else {
                            miscpop.battleResult(battleWorld.resultdata, res.share);
                        }
                    },
                    res.info.background, null, true);//最后一个参数是不允许跳过战斗（主线推图的前50级，不允许）
                },
                500);
                //重新获取用户数据，用于更新主线任务
                player.reloadInfo();

                // 更新引导续接步数，如果赢了，则引导续接的时候直接进入下下步，即点击敌人头像
                if (xGame.guide) {
                    guide.updateStep(res.info.win);
                }

            };
            loadContents({
                'c': 'map',
                'm': 'action',
                'l': data.l,
                's': data.s,
                'id': data.id,
            },
            render);
            xGame.xsSet('lastBattle', data.l + ',' + data.s + ',' + data.id, true);
            //设置当前战斗的地方，用来做再战
            xGame.xsSet(player.info.uid + 'enterbattle_data', JSON.stringify(data), true);
        },

        /* 刷图 */
        continuousPlay: function(data) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                /*
                -1  军令不足
                -2  cd时间未清除
                -3  没有找到地图记录
                -4  场景未开启
                -5  关卡未开启
                -6  没有找到关卡数据
                -7  元宝不足
                -8  首杀不能刷图
                -9  vip等级不够
                */
                if (res.status) {
                    if (res.status == -1) { //没有军令
                        xGame.buyActConfirm();
                        xGame.unlock();
                        return;
                    } else if (res.status == -2) { //清除CD
                        if (xGame.guide == true && xGame.xsGet(player.info.uid + 'firstbattlecd') != '1') {
                            xGame.xsSet(player.info.uid + 'firstbattlecd', '1');
                            guide.showNpc(lang('clear_cd_ask'),
                            function() {
                                guide.createSimplePoint($('#header').find('#battle_timer'), null, false, 'touchend');
                            });
                        } else {
                            xGame.toast.show(lang('please_clear_battle_cd'));
                            guide.createSimplePoint($('#header').find('#battle_timer'), null, false, 'touchend');
                            setTimeout(function() {
                                $('#guide_arrow').remove();
                            },
                            2000);
                        }
                        miscpop.hide('enemypop');
                        return;
                    } else if (res.status == -7) {//元宝不足
                        xGame.goPay(lang('your_gold_is_not_enough'));
                        xGame.unlock();
                        return;
                    } else {
                        var error_msg = '';
                        switch (res.status) {
                        case - 3 : error_msg = lang('current_map_exist');
                            break;
                        case - 4 : error_msg = lang('current_shili_close');
                            break;
                        case - 5 : error_msg = lang('battle_front_first');
                            break;
                        case - 6 : error_msg = lang('current_no_data');
                            break;
                        case - 8 : error_msg = lang('first_not_battle');
                            break;
                        case - 9 : error_msg = lang('need_vip_level');
                            break;
                        }
                        if (error_msg != '') {
                            miscpop.hide('enemypop');
                            xGame.toast.show(error_msg);
                            return;
                        }
                    }
                }

                //刷图成功（显示战斗结果）
                if (res.info.levelup && res.info.levelup == 1) { //升级
                    xGame.xsSet(player.info.uid + 'levelup', 1, true);
                }
                if (res.info.generalstatus && res.info.generalstatus == 1) { //招募到了将领
                    miscpop.newhero(res.info.generalinfo);
                }
                var html = '';
                if (res.info.add_type == 1) {
                    html += lang('enemypop_cleanup_message', [data.times, res.info.exp, lang('general'), res.info.gexp]);
                } else {
                    html += lang('enemypop_cleanup_message2', [data.times, res.info.exp, lang('reputation'), res.info.gexp]);
                }
                if(res.info.itemname != '') {
                    html +='<em class="equip" style="color:' + helper.getItemColor(res.info.quality) + '">'+ res.info.itemname + 'x' + parseInt(res.info.get_number)+'</em></li>';
                }

                //2013-9-24
                //这里用append的话在ios7下其实没有重新渲染，看上去图片放大了而没有插入dom，用html可以重新渲染
                var scroll = $('#force_list_scroller');
                miscpop.htmlData += html;
                var height = scroll.height();
                
                scroll.html(miscpop.htmlData);
                
                if (height >= 214) {
                    if (miscpop.forceScroller == null) {
                        miscpop.forceScroller = new iScroll('force_scroller', {
                            momentum: false,
                            hScrollbar: false,
                            vScrollbar: true
                        });
                    }
                    miscpop.forceScroller.refresh();
                    height = scroll.height();
                    miscpop.forceScroller.scrollTo(0, 214 - height, 800);
                }
               
                //修改界面上的元宝消耗
                var dom = $('#force');
                if(data.times == 5) { dom.data('price', res.info.five_sub); }
                dom.find('.type1').attr('price', res.info.five_sub);
                dom.find('.type1').find('.gold').html(res.info.five_sub + '' + lang('gold'));
                if(data.times == 10) { dom.data('price', res.info.ten_sub); }
                dom.find('.type2').attr('price', res.info.ten_sub);
                dom.find('.type2').find('.gold').html(res.info.ten_sub + '' + lang('gold'));
                if(data.times == 50) { dom.data('price', res.info.fifty_sub); }
                dom.find('.type3').attr('price', res.info.fifty_sub);
                dom.find('.type3').find('.gold').html(res.info.fifty_sub + '' + lang('gold'));
                if(data.times == 100) { dom.data('price', res.info.hundred_sub); }
                dom.find('.type4').attr('price', res.info.hundred_sub);
                dom.find('.type4').find('.gold').html(res.info.hundred_sub + '' + lang('gold'));
                //重新获取用户数据，用于更新主线任务
                xGame.unlock();
                player.reloadInfo();
            };
            loadContents({
                'c': 'map',
                'm': 'action',
                'l': data.l,
                's': data.s,
                'id': data.id,
                'times': data.times
            },
            render);
        },

        battleResult: function(data, sharedata) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            if (data.gettype > 0 || data.itemtype == 2) {
                that_dom.find('#battleend_main').attr('class', 'main type3');
                var str = lang('all_hero_exp') + '<p></p>';
                if (data.add_type == 2) {
                    str = lang('reputation') + '：<p style="background-image:url(' + staticUrl + 'img/common/prestige_icon.png);"></p>';
                }
                that_dom.find('#battleend_main').html('<div class="get1">' + lang('zhugong_exp') + '<p></p></div><div class="get2">' + str + '</div>' + '<div class="getitem">' + '    <p>' + lang('get') + '：<span></span></p>' + '    <div class="itembox"></div>' + '</div>');
            } else {
                that_dom.find('#battleend_main').attr('class', 'main type2');
                var str = lang('all_hero_exp') + '<p></p>';
                if (data.add_type == 2) {
                    str = lang('reputation') + '：<p style="background-image:url(' + staticUrl + 'img/common/prestige_icon.png);"></p>';
                }
                that_dom.find('#battleend_main').html('<div class="get1">' + lang('zhugong_exp') + '<p></p></div><div class="get2">' + str + '</div>');
            }

            /* 填充界面 */
            var flagclass = data.win > 0 ? 'flagwin' + data.win: 'flaglose' + Math.abs(data.win);
            that_dom.find('.flag').addClass(flagclass);
            if (data.win < 0) {
                that_dom.find('.flagbg').addClass('lose');
            } else if (data.win == 0) {
                that_dom.find('.flagbg').addClass('equal');
            }

            that_dom.find('.get1').children('p').text('+' + data.exp);
            that_dom.find('.get2').children('p').text('+' + data.gexp);

            var itemtext = '';
            if (data.gettype == 3) { //获得银币
                itemtext = lang('get_silver') + '<span>+' + data.getvalue + '</span>';
                that_dom.find('.itembox').addClass('c_i_bj1').html('<img src="' + staticUrl + 'img/common/header_gold.png" />');
            } else if (data.gettype == 4) { //获得元宝
                itemtext = lang('get_gold') + '<span>+' + data.getvalue + '</span>';
                that_dom.find('.itembox').addClass('c_i_bj1').html('<img src="' + staticUrl + 'img/common/header_money.png" />');
            } else if (data.gettype == 1 || data.gettype == 2) {
                itemtext = lang('get') + '：<span style="color:' + helper.getItemColor(data.quality) + '">' + data.itemname + '</span>';
                that_dom.find('.itembox').addClass('c_i_bj' + data.quality).html('<img src="' + xGame.itemurl(data.itemid) + '" />');
                if (data.gettype == 1 && data.itemid > 0) {
                    var getitem = JSON.stringify({
                        "itemid": data.itemid,
                        "itemname": data.itemname,
                        "quality": data.quality,
                        "itemurl": xGame.itemurl(data.itemid)
                    });
                    xGame.xsSet(player.info.uid + 'getitem', getitem, true);
                }
            } else if (data.itemtype == 2) { //天赋卷轴
                itemtext = lang('get') + '：<span style="color:' + helper.getItemColor(data.quality) + '">' + data.itemname + '</span>';
                that_dom.find('.itembox').addClass('c_i_bj' + data.quality).html('<img src="' + staticUrl + 'img/pop/talent_item' + data.itemid + '.png' + '" />');
                var getitem = JSON.stringify({
                    "itemname": data.itemname,
                    "quality": data.quality,
                    "url": staticUrl + 'img/pop/talent_item' + data.itemid + '.png'
                });
                xGame.xsSet(player.info.uid + 'getitem', getitem, true);
            }

            if (itemtext != '') { //有道具框
                that_dom.find('.getitem').children('p').html(itemtext);
            } else { //只有经验
                that_dom.find('#battleend_main').attr('class', 'main type2');
            }

            var doneRender = function(e) {
                that_dom.find('.replay_btn').off();
                that_dom.find('.confirm_btn').off();
                that_dom.find('.share_btn').unbind('touchend');
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower(); //清理烟花
                xGame.xsRemove(player.info.uid + 'enterbattle_data', true); //清理用于再战的缓存

                if (data.win < 0) { //失败
                    xGame.xsSet(player.info.uid + 'islose', '1', true);

                    if (xGame.guide == false && player.info.missionlevel == 1 && player.info.missionstage == 2) {
                        var zhangliang = xGame.xsGet(player.info.uid + 'gozhangliang', true);

                        if (zhangliang) {
                            xGame.xsRemove(player.info.uid + 'gozhangliang', true);
                            xGame.xsSet(player.info.uid + 'zhanglianglose', 1, true);
                        }   
                    }
                }

                if (data.levelup && data.levelup == 1) { //升级
                    xGame.xsSet(player.info.uid + 'levelup', 1, true);
                    if(data.levelReward) {
                        xGame.xsSet(player.info.uid + 'levelReward', JSON.stringify(data.levelReward), true);
                    }
                    if(data.levelFunc) {
                        xGame.xsSet(player.info.uid + 'levelFunc', JSON.stringify(data.levelFunc), true);
                    }
                }
                if (data.generalstatus && data.generalstatus == 1) { //招募到了将领
                    xGame.xsSet(player.info.uid + 'getgeneral', JSON.stringify(data.generalinfo), true);
                }
                if ((xGame.guide == true && guide.now >= 1809 && data.openstatus == 1) || (xGame.guide == false && data.openstatus == 1)) { //不在新手引导 才跳转
                    xGame.xsSet(player.info.uid + 'guanqiadone', data.missionlevel, true); //设置关卡通过，地图加载完成后将显示
                } else if (data.openstatus == 2) {
                    //普通通关
                    xGame.xsSet(player.info.uid + 'shilidone', data.get_level, true); //设置势力通过
                    //2013-07-09 增加完全通关的处理
                    if(data.missionlevel == 0 && data.missionstage == 0) {
                        xGame.xsSet(player.info.uid + 'shilialldone', 'solo');
                    }
                }
                xGame.loadWorld('map');
                doneRender = null;
                that_dom = null;
                e.preventDefault();e.stopPropagation();
            };
            /*
            var newFightRender = function() {
                miscpop.hide('battleresultpop');
                that.destoryAllBombFlower();//清理烟花
                battleWorld && battleWorld.clearForContinue();//清理

                var enterbattle_data = null;
                var local_enterbattle_string = xGame.xsGet('enterbattle_data', true);
                if(local_enterbattle_string) {
                    enterbattle_data = JSON.parse(local_enterbattle_string);
                    local_enterbattle_string = null;
                }
                if(enterbattle_data) {
                    miscpop.enterBattle({l:enterbattle_data.l, s:enterbattle_data.s, id:enterbattle_data.id});
                    enterbattle_data = null;
                } else {
                    xGame.toast.show('战斗数据有误，无法再战！');
                }
            };*/

            //that_dom.find('#hidebtn').one('touchend', doneRender);
            //that_dom.find('.confirm_btn').one('touchend', newFightRender);
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.report > 0 && data.win > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    var _tmp = xGame.xsGet(player.info.uid + 'enterbattle_data', true);
                    if (_tmp) {
                        _tmp = JSON.parse(_tmp);
                        _tmp.c = 'map';
                        _tmp.m = 'send_to_chat';
                        _tmp.report = data.report;
                        _tmp.win = data.win;

                        miscpop.shareBattle(_tmp);
                    } else {
                        xGame.toast.show(lang('can_not_share_report'));
                    }
                    $(this).addClass('disable').unbind('touchend');
                });
                if(xGame.guide == false) {
                    /* 社交分享啊啊啊 */
                    that.socialShareDisplay(sharedata, 'battle');
                }
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }
            /* 新手引导 */
            //结果窗里的关闭按钮
            xGame.showGuide([3, 6, 102, 105, 108, 202, 205, 208, 302, 305, 603, 606, 613, 616, 702, 705, 708, 711, 714, 721, 724]);
            // 更新引导步骤
            if (xGame.guide) {
                guide.updateStep(data.win);
            }

        },

        /* 进入战斗 */
        enterBattleFromMine: function(res, touid) {

            $('#worldloading').addClass('black').show();
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromMine(res, touid);
                },
                'pvp');
            },
            500);

        },

        battleResultFromMine: function(data, touid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips"></p>');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').children('p').html(lang('rob_mine_from', [data.info.name]) + '<em style="background: url(' + staticUrl + 'img/common/header_money.png)  0 50% no-repeat;padding-left: 50px;display:inline-block;">' + data.info.get_silver + '</em>');
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').children('p').html(lang('you_mine_fail'));
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').children('p').html(lang('you_mine_fail'));
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');

                xGame.loadWorld('mine', {
                    win: data.info.win
                });
                /*
                if(data.info.win > 0) {
                    minepop.getMineDataByPage(minepop.page);
                }
                */
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'mine',
                        m: 'send_to_chat',
                        touid: touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
                if(data.share) {
                    that.socialShareDisplay(data.share, 'mine');
                }
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }

        },

        /* 进入战斗 */
        enterBattleFromCountryMine: function(res, touid) {

            $('#worldloading').addClass('black').show();
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromCountryMine(res, touid);
                },
                'pvp');
            },
            500);

        },

        battleResultFromCountryMine: function(data, touid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips"></p>');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').children('p').html(lang('countrymine_rob_from', [data.info.name]) + '<em class="add_jade">' + data.info.jade + '</em>' + (data.info.event_type == 2 ? '<em class="red">(' + data.info.event_value + '%)</em>' : ''));
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').children('p').html(lang('you_mine_fail'));
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').children('p').html(lang('you_mine_fail'));
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');

                xGame.loadWorld('countrymine', data.info);
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'countrymine',
                        m: 'send_to_chat',
                        touid: touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
                if(data.share) {
                    that.socialShareDisplay(data.share, 'countrymine');
                }
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            
            if (data.info.win > 0 && data.info.event_status == 1) {
                miscpop.showCountrymineSp(data.info.event_name, data.info.event_type, data.info.event_value, data.info.jade, data.info.event_add, data.info.all_gold, function() {
                    that_dom.show();
                    that_dom = null;
                });
            } else {
                that_dom.show();
                that_dom = null;
            }
            

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }

        },
        
        showCountrymineSp: function(name, type, num, jade,add_jade, all_gold, callback) {
            var dom = xGame.loadPopTmpl('countryminesp');
            dom.find('#sp_name').html(name);
            num = parseInt(num);
            var cn = '';
            if (type != 2) {
                if (type == 1) {
                    cn = 'gem3';
                } else if (type == 3) {
                    cn = 'gold';
                    player.updateinfo({
                        "gold": all_gold,
                    });
                }
                dom.find('#sp_reward').html('x' + num).attr('class', cn);
            } else {
                dom.find('#sp_reward').html(lang('countrymine_jade_times', [add_jade, (num - 100) + '%']));
            }
            
            dom.find('button').unbind('touchend').one('touchend', function() {
                dom.remove();
                dom = null;
                if (typeof callback == 'function') {
                    callback();
                }
            });
            
            dom.find('#mine_sp').show();
            xGame.doAnimate(dom.find('#mine_sp'), 'newhero_scalein', '0.5s',function() {
                xGame.unlock();
            });
            
            cn = null;
        },
        
        /* 进入战斗 */
        enterBattleFromArena: function(res, touid) {

            /* 开始进入战斗界面 */
            arenapop.e.hide();

            $('#worldloading').addClass('black').show();

            var _old_world = xGame.nowWorld;
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromArena(res, _old_world, touid);
                },
                'pvp');
            },
            500);
        },

        battleResultFromArena: function(data, oldworld, touid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips"></p>');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                if (parseInt(data.info.rank) != 1) {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg1', [data.info.rank]));
                } else {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg2', [data.info.rank]));
                }
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').children('p').html(lang('you_lose_msg'));
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').children('p').html(lang('you_lose_msg'));
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
               
                arenapop.e.show();
                xGame.loadWorld(oldworld);
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'arena',
                        m: 'send_to_chat',
                        touid: touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
                /* 社交分享啊啊啊 */
                that.socialShareDisplay(data.share, 'arena');
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 进入战斗 */
        enterBattleFromTower: function(res, missionid) {

            /* 开始进入战斗界面 */
            $('#worldloading').addClass('black').show();

            var nowstartid = 0; //用来返回到当前的星域
            if (towerWorld && towerWorld.nowstarid) {
                nowstartid = towerWorld.nowstarid;
            }
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromTower(res, nowstartid, missionid);
                },
                'tower');
            },
            500);

        },

        battleResultFromTower: function(data, nowstartid, missionid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type5');
            var _tmpl = '<div class="get1">' + lang('get_o_soul') + '<p></p></div>';
            if (data.info.hero_soul.id > 0) {
                _tmpl += '<div class="getitem"><p>' + lang('get_soul') + '<span></span></p><div class="itembox"></div></div>';
            }
            that_dom.find('#battleend_main').html(_tmpl);

            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }
            that_dom.find('.flag').addClass(flagclass);

            //原魂
            if (parseInt(data.info.firstkill) == 1) {
                that_dom.find('#battleend_main').find('.get1').children('p').html('+' + data.info.first_soul_num);
            } else {
                that_dom.find('#battleend_main').find('.get1').children('p').html('+' + data.info.reward_soul);
            }

            //将魂
            if (data.info.hero_soul.id > 0) {
                that_dom.find('#battleend_main').find('.getitem').children('p').children('span').css('color', helper.getItemColor(data.info.hero_soul.quality)).html(data.info.hero_soul.name);
                that_dom.find('.itembox').addClass('c_i_bj' + data.info.hero_soul.quality).html('<img src="' + staticUrl + 'img/pop/soul_' + data.info.hero_soul.image + '.png" />');
            }

            var doneRender = function() {
                miscpop.hide('battleendpop');
               
                var returndata = null;
                if (data.clear != 0) {
                    returndata = {
                        clearstar: true
                    };
                } else {
                    returndata = {
                        starid: nowstartid
                    };
                }
                xGame.loadWorld('tower', returndata);

            };
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'tower',
                        m: 'send_to_chat',
                        id: missionid,
                        //外部传入
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 进入战斗 */
        enterBattleFromHeaven: function(res, missionid) {

            /* 开始进入战斗界面 */
            $('#worldloading').addClass('black').show();

            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromHeaven(res, missionid);
                },
                'heaven');
            },
            500);

        },

        battleResultFromHeaven: function(data, missionid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type5');
            var _tmpl = '<div class="get1">' + lang('heavenworld_battleend') + '<ul class="material"></ul></div>';
            that_dom.find('#battleend_main').html(_tmpl);

            var html = '';

            if (parseInt(data.info.firstkill) == 1) {

                switch (parseInt(data.info.first_type)) {
                    case 1:
                        html += '<li class="refinestone">' + lang('refinestone') + 'x' + data.info.first_value + '</li>';
                        break;
                    case 2:
                        html += '<li class="colorstone">' + lang('colorstone') + 'x' + data.info.first_value + '</li>';
                        break;
                    case 3:
                        html += '<li class="bloodstone">' + lang('bloodstone') + 'x' + data.info.first_value + '</li>';
                        break;
                    default:
                        break;
                }
            } else {
                // 精炼石
                if (parseInt(data.info.refinestone) != 0) {
                    html += '<li class="refinestone">' + lang('refinestone') + 'x' + data.info.refinestone + '</li>';
                }

                // 五彩石
                if (parseInt(data.info.colorstone) != 0) {
                    html += '<li class="colorstone">' + lang('colorstone') + 'x' + data.info.colorstone + '</li>';
                }

                // 上古血玉
                if (parseInt(data.info.bloodstone) != 0) {
                    html += '<li class="bloodstone">' + lang('bloodstone') + 'x' + data.info.bloodstone + '</li>';
                }

                var essenceId = parseInt(data.info.essence.id);
                var essenceNum = parseInt(data.info.essence.num);

                if (essenceNum != 0) {
                    if (essenceId >= 1 && essenceId <= 6) { // 武器精华
                        html += '<li class="equip_essence" style="color:'+ helper.getItemColor(data.info.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('equip_ess') + 'x' + essenceNum + '</li>';
                    } else if (essenceId >= 7 && essenceId <= 12) { // 防具精华
                        html += '<li class="defense_essence" style="color:'+ helper.getItemColor(data.info.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('defense_ess') + 'x' + essenceNum + '</li>';
                    } else if (essenceId >= 13 && essenceId <= 18) { // 饰品精华
                        html += '<li class="acc_essence" style="color:'+ helper.getItemColor(data.info.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('acc_ess') + 'x' + essenceNum + '</li>';
                    }
                }
            }

            that_dom.find('.material').html(html);

            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }
            that_dom.find('.flag').addClass(flagclass);

            // //原魂
            // that_dom.find('#battleend_main').find('.get1').children('p').html('+' + data.info.reward_soul);

            // //将魂
            // if (data.info.hero_soul.id > 0) {
            //     that_dom.find('#battleend_main').find('.getitem').children('p').children('span').css('color', helper.getItemColor(data.info.hero_soul.quality)).html(data.info.hero_soul.name);
            //     that_dom.find('.itembox').addClass('c_i_bj' + data.info.hero_soul.quality).html('<img src="' + staticUrl + 'img/pop/soul_' + data.info.hero_soul.image + '.png" />');
            // }

            var doneRender = function() {
                miscpop.hide('battleendpop');
               
                xGame.loadWorld('heaven');
            };

            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', function () {
                doneRender();
            });
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'heaven',
                        m: 'send_to_chat',
                        id: missionid,
                        //外部传入
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 进入战斗 */
        enterBattleFromZhulu: function(res, touid) {

            /* 开始进入战斗界面 */
            zhulupop.e.hide();

            $('#worldloading').addClass('black').show();

            var _old_world = xGame.nowWorld;
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromZhulu(res, _old_world, touid);
                },
                'pvp');
            },
            500);
        },

        battleResultFromZhulu: function(data, oldworld, touid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips"></p>');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                if (parseInt(data.info.rank) != 1) {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg1', [data.info.rank]));
                } else {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg2', [data.info.rank]));
                }
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').children('p').html(lang('you_lose_msg'));
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').children('p').html(lang('you_lose_msg'));
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function(e) {
                miscpop.hide('battleendpop');
                
                zhulupop.e.show();
                xGame.loadWorld(oldworld);
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'compete',
                        m: 'send_to_chat',
                        touid: touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
                /* 社交分享啊啊啊 */
                that.socialShareDisplay(data.share, 'compete');
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 金银洞将领info */
        treasureEnemyInfo: function(missionid) {
            var that = this;

            var initRender = function(res) {

                if (res.status == 4) { //提示重进金银洞
                    xGame.confirm.show(lang('jinyindong'), lang('re_enter_jyd'), 1,
                    function() {
                        xGame.confirm.hide(function() {
                            miscpop.hide('treasureenemypop');
                            xGame.loadWorld('treasure');
                        });
                    });
                    that = null;
                    initRender = null;
                    xGame.unlock();
                    return false;
                } else if (res.status != 1) {
                    xGame.toast.show(lang('can_not_get_hero_error') + res.status);
                    that = null;
                    initRender = null;
                    xGame.unlock();
                    return false;
                }

                /* 第一步，把界面先载入，渠道DOM，并绑定treasureenemy好基础事件 */
                var that_dom = xGame.loadPopTmpl('treasureenemy');
                var unbindFunc = function (that_dom) {
                    that_dom.find('.content').find('.hidebtn').unbind('touchend');
                    that_dom.find('#attack_normal_btn').unbind('touchend');
                    that_dom.find('#strategy_btn').unbind('touchend');
                    that_dom = null;
                };
                that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    miscpop.hide('treasureenemypop');
                    e.preventDefault();
                    e.stopPropagation();
                    unbindFunc(that_dom);
                    unbindFunc = null;
                });

                /* 第二步 构建数据列表等 */
                var _avatarUrl = 'url(' + xGame.avatar(res.info.image) + ') 50% 50% no-repeat';

                that_dom.find('.avatarbox').children('.avatar').css({
                    'background': _avatarUrl
                });
                that_dom.find('.avatarbox').children('p').text(res.info.name);
                that_dom.find('.herolv').children('span').text('Lv.' + res.info.monsterlevel);
                that_dom.find('.reward_silver').children('span').text('+' + res.info.silver);
                that_dom.find('.reward_gold').children('span').text('+' + res.info.gold);
                that_dom.find('#attack_act_cost').html(res.info.act + lang('act'));

                that_dom.find('#attack_normal_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.enterBattleFromTreasure({
                        id: missionid
                    });
                    unbindFunc(that_dom);
                    unbindFunc = null;
                });
                that_dom.find('#strategy_btn').unbind('touchend').bind('touchend',
                function(e) {
                    xGame.showpop('strategy', {
                        "name": res.info.name,
                        "c": "island",
                        "m": "get_report_list",
                        "id": missionid
                    });
                    e.preventDefault();
                    e.stopPropagation();
                });

                /* 第四步 展示弹窗 */
                that_dom.show();
                //that_dom = null;

                that = null;
                initRender = null;
                xGame.unlock();

            };
            //initRender({status:1,info:{id:1,name:"怪物1",monsterlevel:100,image:40,silver:100000,gold:0,act:1}});
            loadContents({
                'c': 'island',
                'm': 'get_mission',
                'id': missionid
            },
            initRender);
        },

        /* 进入战斗 金银洞 */
        enterBattleFromTreasure: function(data) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                /*
                4  军令不足
                2  任务未开启
                1  成功
                */
                var error_msg = '';
                if (res.status == 4) {
                    error_msg = lang('act_can_not_atk');
                } else if (res.status == 2) {
                    error_msg = lang('hero_closed');
                } else if (res.status == -1) {
                    error_msg = lang('please_clear_battle_cd');
                } else if (res.status == 3) {
                    error_msg = lang('atk_fail_lv_not_match');
                } else if (res.status != 1) {
                    error_msg = lang('atk_fail_error') + res.status;
                }
                if (error_msg != '') {
                    miscpop.hide('treasureenemypop');
                    xGame.unlock();
                    if (res.status == 4) {
                        xGame.buyActConfirm();
                    } else {
                        xGame.toast.show(error_msg);
                    }
                    return;
                }

                player.taskaction(9);

                /* 更新player数据 */
                var updatedata = {};
                updatedata.battlecd = res.info.cd;
                updatedata.act = res.info.act;
                updatedata.gold = parseInt(player.info.gold) + parseInt(res.info.reward_gold);
                updatedata.silver = parseInt(player.info.silver) + parseInt(res.info.reward_silver);

                player.updateinfo(updatedata);
                xGame.updateCd('battle', updatedata.battlecd);
                updatedata = null;

                $('#worldloading').addClass('black').show();

                miscpop.hide('treasureenemypop');
                setTimeout(function() {
                    xGame.loadBattle(res,
                    function() {
                        miscpop.battleResultFromTreasure(res, data.id);
                    },
                    'treasure');
                },
                500);

            };
            //render({"info":{"id":1,"background":3,"act":50,"reward_gold":100,"reward_silver":10000,"cd":"300","win":3},"status":1,"battle":{"leftteam":{"2":{"heroid":"5450","name":"\u5f20\u6881","herotype":"1","level":"23","allblood":1406,"image":"27","talent":"\u575a\u5f3a","skill":"\u4f1a\u5fc3\u4e00\u51fb"},"3":{"heroid":"5747","name":"\u5f20\u71d5","herotype":"1","level":"27","allblood":2290,"image":"45","talent":"\u608d\u52c7","skill":"\u7834\u6012\u4e4b\u51fb"},"4":{"heroid":"5460","name":"\u5f20\u89d2","herotype":"2","level":"23","allblood":1871,"image":"20","talent":"\u6559\u7956","skill":"\u5e7b\u5f71\u96f7\u7f5a"},"8":{"heroid":"5447","name":"\u5468\u4ed3","herotype":"1","level":"23","allblood":2105,"image":"4","talent":"\u608d\u52c7","skill":"\u6012\u51fb"},"9":{"heroid":"5458","name":"\u5f20\u5b9d","herotype":"2","level":"23","allblood":1361,"image":"24","talent":"\u6d1e\u5f7b","skill":"\u4e09\u53c9\u96f7\u5149"}},"rightteam":{"2":{"heroid":"5167","name":"\u5ed6\u5316","herotype":"1","level":"27","allblood":1380,"image":"1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb"},"4":{"heroid":"5183","name":"\u5f20\u6881","herotype":"1","level":"25","allblood":1280,"image":"27","talent":"\u575a\u5f3a","skill":"\u4f1a\u5fc3\u4e00\u51fb"},"6":{"heroid":"5169","name":"\u5468\u4ed3","herotype":"1","level":"24","allblood":1230,"image":"4","talent":"\u608d\u52c7","skill":"\u6012\u51fb"},"7":{"heroid":"5309","name":"\u5f20\u89d2","herotype":"2","level":"25","allblood":1280,"image":"20","talent":"\u6559\u7956","skill":"\u5e7b\u5f71\u96f7\u7f5a"},"9":{"heroid":"5192","name":"\u5f20\u5b9d","herotype":"2","level":"20","allblood":1030,"image":"24","talent":"\u6d1e\u5f7b","skill":"\u4e09\u53c9\u96f7\u5149"}},"process":[{"launch":"left","attackor":2,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":220,"power":25}]},{"launch":"right","attackor":2,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":552,"power":50}]},{"launch":"left","attackor":3,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":687,"power":25}]},{"launch":"right","attackor":4,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":497,"power":25}]},{"launch":"left","attackor":4,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":519,"power":50}]},{"launch":"right","attackor":6,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[3],"hurt":[{"pos":3,"blood":322,"power":50}]},{"launch":"left","attackor":8,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":243,"power":75}]},{"launch":"right","attackor":7,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":395,"power":75}]},{"launch":"left","attackor":9,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":543,"power":0}]},{"launch":"right","attackor":9,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[3],"hurt":[{"pos":3,"blood":266,"power":75}]},{"launch":"left","attackor":2,"attackpower":75,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":220,"power":100}]},{"launch":"right","attackor":2,"attackpower":0,"skill":1,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":854,"power":0}]},{"launch":"left","attackor":3,"attackpower":100,"skill":0,"hit":0,"attacktype":0,"beattackors":[9],"hurt":[{"pos":9,"blood":902,"power":50}]},{"launch":"right","attackor":4,"attackpower":75,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":497,"power":100}]},{"launch":"left","attackor":4,"attackpower":0,"skill":1,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":761,"power":0}]},{"launch":"right","attackor":7,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":395,"power":25}]},{"launch":"left","attackor":8,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":243,"power":25}]},{"launch":"right","attackor":9,"attackpower":75,"skill":0,"hit":0,"attacktype":0,"beattackors":[3],"hurt":[{"pos":3,"blood":266,"power":125}]},{"launch":"left","attackor":9,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[9],"hurt":[{"pos":9,"blood":128,"power":0}]},{"launch":"left","attackor":3,"attackpower":25,"skill":1,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":454,"power":0}]},{"launch":"right","attackor":7,"attackpower":75,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":87,"power":0}]},{"launch":"left","attackor":8,"attackpower":75,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":512,"power":100}]},{"launch":"left","attackor":9,"attackpower":75,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":503,"power":125}]},{"launch":"left","attackor":3,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":265,"power":0}]}],"assess":2,"leftresult":{"3":{"heroid":"5747","name":"\u5f20\u71d5","herotype":"1","level":"27","allblood":2290,"image":"45","talent":"\u608d\u52c7","skill":"\u7834\u6012\u4e4b\u51fb","blood":1436},"8":{"heroid":"5447","name":"\u5468\u4ed3","herotype":"1","level":"23","allblood":2105,"image":"4","talent":"\u608d\u52c7","skill":"\u6012\u51fb","blood":2105},"9":{"heroid":"5458","name":"\u5f20\u5b9d","herotype":"2","level":"23","allblood":1361,"image":"24","talent":"\u6d1e\u5f7b","skill":"\u4e09\u53c9\u96f7\u5149","blood":1361}},"rightresult":[],"left_role":{"name":"\u8bd7\u8bd7","level":"37","gender":"2"},"right_role":{"name":"\u6885\u6dfc","level":"36","gender":"2"}}});
            loadContents({
                'c': 'island',
                'm': 'pk',
                'id': data.id
            },
            render);

        },

        battleResultFromTreasure: function(data, treasurehero_id) {
            var that = this;
            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                that_dom.find('#battleend_main').attr('class', 'main type1').html('<div class="get1">' + lang('get_un_get') + '</div>');

                flagclass = 'flagwin' + data.info.win;
                if (data.info.reward_gold > 0) {
                    that_dom.find('#battleend_main').find('.get1').children('p').attr('class', 'gold').html(data.info.reward_gold);
                }
                if (data.info.reward_silver > 0) {
                    that_dom.find('#battleend_main').find('.get2').children('p').attr('class', 'silver').html(data.info.reward_silver);
                }
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');

            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                
                xGame.loadWorld('treasure');
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0 && data.info.win > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'island',
                        m: 'send_to_chat',
                        id: treasurehero_id,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }
        },

        /* 进入战斗 BOSS */
        enterBattleFromNian: function(now) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();
                //var res = {"status":1,"info":{"win":-3},"battle":{"leftteam":{"7":{"heroid":"1934","name":"\u8521\u6587\u59ec","herotype":"2","level":"50","allblood":3053,"power":0,"image":"5","talent":"\u5dfe\u5e3c","skill":"\u51fa\u585e\u66f2"}},"rightteam":{"5":{"heroid":"1","name":"\u5211\u5929","herotype":0,"level":"37","allblood":"100.00","power":0,"image":"worldboss1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb"}},"process":[{"round":1,"launch":"left","attackor":7,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[5],"hurt":[{"pos":5,"blood":2960,"power":25,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":5,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":3053,"power":0,"self":0,"faint":0}]}],"assess":-3,"leftresult":[],"rightresult":{"5":{"heroid":"1","name":"\u5211\u5929","herotype":0,"level":"37","allblood":100,"power":50,"image":"worldboss1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb","blood":"100.00"}}}}
                switch (res.status) {
                case 1:
                    /* 开始进入战斗界面 */

                    if (now == 1) {
                        player.updateinfo({
                            "gold": parseInt(res.info.all_gold)
                        });
                    }

                    $('#worldloading').addClass('black').show();

                    setTimeout(function() {
                        xGame.loadBattle(res,
                        function() {
                            miscpop.battleResultFromNian(res);
                        },
                        'newyearboss');
                    },
                    500);

                    break;
                case -1:
                    xGame.toast.show(lang('activity_closed'));
                    break;
                case -2:
                    xGame.toast.show(lang('nian_attack_countdown'));
                    break;
                case -3:
                    xGame.toast.show(lang('nian_cd_not_clear'));
                case -4:
                    xGame.toast.show(lang('nian_die'));
                    break;
                case -5:
                    xGame.toast.show(lang('no_gold_buy_life'));
                    break;
                case -6:
                    xGame.toast.show(lang('nian_attack_not_countdown'));
                    break;
                }
            };
            loadContents({
                'c': 'newyearboss',
                'm': 'battle',
                'now': now
            },
            render);

        },

        battleResultFromNian: function(data) {

            //xGame.loadWorld("nian");
            var that = this;
            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('.getinfobox').hide();
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('you_kill_boss') + '</p>');
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld('nian');
            };
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            that_dom.find('.share_btn').addClass('disable');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }
        },

        /* 进入战斗 BOSS */
        enterBattleFromBoss: function(now) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();
                //var res = {"status":1,"info":{"win":-3},"battle":{"leftteam":{"7":{"heroid":"1934","name":"\u8521\u6587\u59ec","herotype":"2","level":"50","allblood":3053,"power":0,"image":"5","talent":"\u5dfe\u5e3c","skill":"\u51fa\u585e\u66f2"}},"rightteam":{"5":{"heroid":"1","name":"\u5211\u5929","herotype":0,"level":"37","allblood":"100.00","power":0,"image":"worldboss1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb"}},"process":[{"round":1,"launch":"left","attackor":7,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[5],"hurt":[{"pos":5,"blood":2960,"power":25,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":5,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":3053,"power":0,"self":0,"faint":0}]}],"assess":-3,"leftresult":[],"rightresult":{"5":{"heroid":"1","name":"\u5211\u5929","herotype":0,"level":"37","allblood":100,"power":50,"image":"worldboss1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb","blood":"100.00"}}}}

                switch (res.status) {
                case 1:
                    /* 开始进入战斗界面 */

                    if (now == 1) {
                        player.updateinfo({
                            "gold": parseInt(res.info.all_gold)
                        });
                    }

                    $('#worldloading').addClass('black').show();

                    setTimeout(function() {
                        xGame.loadBattle(res,
                        function() {
                            miscpop.battleResultFromBoss(res);
                        },
                        'worldboss');
                    },
                    500);

                    break;
                case 2:
                    bossWorld.indexdata.countdown = res.info.cd;
                    xGame.toast.show(lang('re_life'));
                    break;
                case 3:
                    xGame.goPay(lang('no_gold_buy_life'));
                    break;
                case 5:
                    xGame.toast.show(lang('boss_is_killed'));
                    break;
                default:
                    xGame.toast.show(lang('enter_battle_fail'));
                    break;
                }
            };
            loadContents({
                'c': 'worldboss',
                'm': 'battle',
                'now': now
            },
            render);

        },

        battleResultFromBoss: function(data) {
            var that = this;
            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('.getinfobox').hide();
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('you_kill_boss') + '</p>');
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                that_dom.find('.confirm_btn').off();
                that_dom.find('.replay_btn').off();
                miscpop.hide('battleendpop');
                xGame.loadWorld('boss');
                that_dom = null;
            };
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            that_dom.find('.share_btn').addClass('disable');

            /* 第四步 展示弹窗 */
            that_dom.show();

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }
        },

        //进入国家boss
        enterBattleFromCountryBoss: function(now) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                switch (res.status) {
                    case 1:
                        /* 开始进入战斗界面 */

                        if (now == 1) {
                            player.updateinfo({
                                "gold": parseInt(res.info.all_gold)
                            });
                        }

                        $('#worldloading').addClass('black').show();

                        setTimeout(function() {
                            xGame.loadBattle(res,
                            function() {
                                if (res.info.win > 0) {
                                    //防止反复请求该接口，res.rewardInfo在countryBossReward这个方法里面设置
                                    if (res.rewardInfo == undefined) {
                                        miscpop.countryBossReward(res);
                                    } else {
                                        miscpop.battleResultFromCountryBoss(res, res.rewardInfo);
                                    }
                                } else {
                                    miscpop.battleResultFromCountryBoss(res);
                                }
                            },
                            'countryboss');
                        },
                        500);

                        break;
                    case 2:
                        countrybossWorld.indexdata.countdown = res.info.cd;
                        xGame.toast.show(lang('re_life'));
                        break;
                    case 3:
                        xGame.goPay(lang('no_gold_buy_life'));
                        break;
                    case 4:
                        xGame.toast.show(lang('boss_is_killed'));
                        var obj = {};
                        obj.info = {};
                        obj.info.win = 3;//随便一个大于0的数字
                        obj.no_replay = true;
                        countrybossWorld.showPrepare(true);
                        miscpop.countryBossReward(obj);
                        break;
                    case 5:
                        xGame.toast.show(lang('activity_end'));
                        xGame.updateCd('cBossButton', 0);
                        xGame.updateCd('cBoss', 0);
                        break;
                    default:
                        xGame.toast.show(lang('enter_battle_fail'));
                        break;
                }
            };
            loadContents({
                'c': 'countryboss',
                'm': 'battle',
                'now': now
            },
            render);

        },

        //国家boss 领取奖励
        countryBossReward: function(data){
            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                switch (res.status) {
                    case 1:
                        //当有奖励的时候将奖励的数据作为第二个参数传进去
                        data.rewardInfo = res.reward;
                        miscpop.battleResultFromCountryBoss(data, res.reward);
                        break;
                    case 2:
                        //已领取或者未参加
                        xGame.toast.show(lang('no_reward'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'countryboss',
                'm': 'reward',
            },
            render);
        },

        battleResultFromCountryBoss: function(data, rewardInfo) {
            var that = this;
            that.clearflower = false;
            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('.getinfobox').hide();
            /* 填充界面 */
            var flagclass;
            var tpl = '';
            if (rewardInfo != undefined) {
                if (rewardInfo.jadenum > 0) {
                    tpl += '<div class="box">';
                    tpl += '<p class="rank">' + lang('current_rank',[rewardInfo.rank]) + (rewardInfo.lasthit == 1 ? '<em style="color:red;">(+50%)</em>' : '') + '</p>';
                    tpl += '<div class="itembox c_i_bj' + (parseInt(rewardInfo.jadetype) + 2) + '" ><span class="gem' + rewardInfo.jadetype + '"></span><b>x' + rewardInfo.jadenum + '</b></div>';
                    tpl += '</div>';
                }
                if (rewardInfo.itemnum > 0) {
                    tpl += '<div class="box isitem">';
                    var num = 'x' + rewardInfo.itemnum;
                    tpl += '<p>' + lang('get') + ':<em style="color:' + helper.getItemColor(rewardInfo.quality) + '">' + rewardInfo.itemname + num + '</em></p>';
                    tpl += '<div class="itembox c_i_bj' + rewardInfo.quality + '" ><span style="background-image:url(' + xGame.itemurl(rewardInfo.itemid) + ');"></span><b>' + num + '</b></div>';
                    tpl += '</div>';
                    num = null;
                }
                if (rewardInfo.lasthit == 1) {
                    tpl += '<div class="last"></div>';
                }
            }
            if (data.info.win > 0) {
                flagclass = 'flagwin4';
                that_dom.find('#battleend_main').attr('class', 'main type7').html(tpl);
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            } else {
                flagclass = 'flaglose4';
                that_dom.find('.flagbg').addClass('lose');
                //当输了并且有奖励的时候显示奖励，其他情况显示输了的文字
                if (rewardInfo != undefined) {
                    that_dom.find('#battleend_main').attr('class', 'main type7').html(tpl);
                } else {
                    that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
                }
            }
            that_dom.find('.flag').addClass(flagclass);

            //区分一下，国家boss直接过来的
            //console.log(data.no_replay);
            if (data.no_replay) {
                that_dom.find('.replay_btn').addClass('disable');
                that_dom.find('.confirm_btn').html(lang('receive')).unbind('touchend').one('touchend', function() {
                    miscpop.hide('battleendpop');
                });
            } else {//战斗结果过来的
                var doneRender = function() {
                    miscpop.hide('battleendpop');
                    //如果有奖励直接回主城，如果没有奖励还回国家boss
                    if (data.info.win > 0) {
                        xGame.loadWorld('country');
                    } else {
                        xGame.loadWorld('countryboss');
                    }
                };
                that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
                that_dom.find('.replay_btn').unbind('touchend').one('touchend',
                function() {
                    miscpop.hide('battleendpop');
                    that.destoryAllBombFlower();
                    battleWorld.refight();
                });
            }
            that_dom.find('.share_btn').addClass('disable');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }
        },

        /* 进入战报 */
        viewBattleFromMap: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();
                    $('#enemypop').hide();

                    setTimeout(function() {
                        xGame.loadBattle(res, null, res.info.background,
                        function() {
                            xGame.loadWorld('map');
                            strategypop.e.show();
                            $('#enemypop').show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'map',
                'm': 'report_detail',
                'id': id
            },
            render);

        },

        /* 金银洞进入战报 */
        viewBattleFromTreasure: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();
                    $('#treasureenemypop').hide();

                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'treasure',
                        function() {
                            xGame.loadWorld('treasure');
                            strategypop.e.show();
                            $('#treasureenemypop').show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'island',
                'm': 'get_report_info',
                'id': id
            },
            render);

        },

        /* 跨服远征进入战报 */
        viewBattleFromWorldarena: function(id) {

            var render = function(res) {

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    worldarenapop.e.hide();
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'worldarena',
                        function() {
                            worldarenapop.e.show();
                            xGame.loadWorld('city');
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'worldarena',
                'm': 'get_report_info',
                'id': id
            },
            render);

        },

        /* 神将之门进入战报 */
        viewBattleFromGeneraltask: function(id, gid, page) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'generaltask',
                        function() {
                            xGame.loadWorld('generaltask', {
                                'generalid': gid,
                                'page': page
                            });
                            strategypop.e.show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'generaltask',
                'm': 'get_report',
                'id': id
            },
            render);
        },

        /* 海外贸易进入战报 */
        viewBattleFromOverseastrade: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    overseastradepop.e.hide();
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'overseastrade',
                        function() {
                            xGame.loadWorld('city');
                            overseastradepop.e.show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'overseastrade',
                'm': 'get_report_info',
                'id': id
            },
            render);
        },

        /* 推塔进入战报 */
        viewBattleFromTower: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();

                    var nowstartid = 0; //用来返回到当前的星域
                    if (towerWorld && towerWorld.nowstarid) {
                        nowstartid = towerWorld.nowstarid;
                    }
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'tower',
                        function() {
                            xGame.loadWorld('tower', {
                                starid: nowstartid
                            });
                            strategypop.e.show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'tower',
                'm': 'get_report_info',
                'id': id
            },
            render);

        },

        /* 通天塔进入战报 */
        viewBattleFromHeaven: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();

                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'heaven',
                        function() {
                            xGame.loadWorld('heaven');
                            strategypop.e.show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'heaven',
                'm': 'get_report_info',
                'id': id
            },
            render);

        },

        /* 推塔奖励框 */
        towerRewardbox: function() {
            var that = this;

            var initRender = function(res) {

                if (res.status == 2) {
                    xGame.toast.show(lang('today_had_receive'));
                    //$('#tower_reward_btn').hide();
                    return false;
                } else if (res.status == 3) {
                    xGame.toast.show(lang('no_battle_reward'));
                    return false;
                } else if (res.status != 1) {
                    xGame.toast.show(lang('no_reward_try_again'));
                    return false;
                }

                var that_dom = xGame.loadPopTmpl('towerreward');
                var unbindFunc = function (that_dom) {
                    that_dom.find('.content').find('.hidebtn').unbind('touchend');
                    that_dom.undelegate('.addtype', 'touchend');
                    that_dom = null;
                };
                that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    miscpop.hide('towerrewardpop');
                    e.preventDefault();
                    e.stopPropagation();
                    unbindFunc(that_dom);
                    unbindFunc = null;
                    
                });

                that_dom.find('.nowtower').html(lang('stage_mission', [res.info.stage, res.info.mission]));
                that_dom.find('.nowsoul').html(' x' + res.base_reward + ((res.effect != 0 && res.effect != undefined) ? '<em style="color:red;"> ( ' + res.effect + '% ' + lang('workshop_country_addition') + ')</em>' : ''));
                that_dom.find('#addtype1').html('<em></em><p class="price">' + lang('cost_get', [res.normal_reward.gold, res.normal_reward.multiple]) + '</p>').attr('soulnum', parseInt(res.base_reward) * parseFloat(res.normal_reward.multiple)); //第1个加倍
                that_dom.find('#addtype2').html('<em></em><p class="price">' + lang('cost_get', [res.best_reward.gold, res.best_reward.multiple]) + '</p>').attr('soulnum', parseInt(res.base_reward) * parseFloat(res.best_reward.multiple)); //第2个加倍
                that_dom.undelegate('.addtype', 'touchend').delegate('.addtype', 'touchend',
                function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if ($(this).hasClass('now')) {
                        $(this).removeClass('now');
                    } else {
                        that_dom.find('.addtype').removeClass('now');
                        $(this).addClass('now');
                    }
                });

                that_dom.find('#fetch_btn').attr('soulnum', res.base_reward).unbind('touchend').bind('touchend',
                function(e) { //领取按钮
                    e.preventDefault();
                    e.stopPropagation();
                    //选择的
                    var selectDom = that_dom.find('.addtype.now');
                    if (selectDom.length > 0) {
                        var addtype = selectDom.attr('addtype');
                        var fetchnums = parseFloat(selectDom.attr('soulnum'));
                        //默认的
                    } else {
                        var addtype = 3; //默认类型
                        var fetchnums = parseInt($(this).attr('soulnum')); //默认该得到的原魂数量
                    }

                    var fetchRender = function(res) {
                        if (res.status == 2) {
                            xGame.toast.show(lang('had_receive'));
                            return false;
                        } else if (res.status == 4) {
                            xGame.goPay(lang('gold_plus_reward'));
                            return false;
                        } else if (res.status != 1) {
                            xGame.toast.show(lang('reward_get_fail'));
                            return false;
                        } else {
                            player.updateinfo({
                                "gold": parseInt(res.gold)
                            });
                            xGame.toast.show(lang('get_original_soul', [fetchnums]));
                            miscpop.hide('towerrewardpop');
                            //$('#tower_reward_btn').hide();
                        }
                    };
                    loadContents({
                        'c': 'tower',
                        'm': 'get_reward',
                        'type': addtype
                    },
                    fetchRender);
                });

                /* 展示弹窗 */
                that_dom.show();
                xGame.unlock();
            };
            loadContents({
                'c': 'tower',
                'm': 'reward_info'
            },
            initRender);
        },
		
        /* 通天塔奖励框 */
        heavenRewardbox: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var initRender = function(res) {

                xGame.unlock();

                if (res.status == 2) {
                    xGame.toast.show(lang('today_had_receive'));
                    return false;
                } else if (res.status == 3) {
                    xGame.toast.show(lang('no_battle_reward'));
                    return false;
                } else if (res.status == -1) {
                    xGame.toast.show(lang('lv_not_enough'));
                    return false;
                } else if (res.status == -2) {
                    xGame.toast.show(lang('heavenworld_err1'));
                    return false;
                } else if (res.status == -3) {
                    xGame.toast.show(lang('heavenworld_err2'));
                    return false;
                } else if (res.status == -4) {
                    xGame.toast.show(lang('heavenworld_err3'));
                    return false;
                } else if (res.status != 1) {
                    xGame.toast.show(lang('no_reward_try_again'));
                    return false;
                }

                var that_dom = xGame.loadPopTmpl('heavenreward');
                var unbindFunc = function (that_dom) {
                    that_dom.find('.content').find('.hidebtn').unbind('touchend');  
                    that_dom.undelegate('.addtype', 'touchend');
                    that_dom.find('#fetch_btn').unbind('touchend');
                };
                var deleteThread = null;
                that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend', function(e) {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    miscpop.hide('heavenrewardpop');

                    if (deleteThread != null) {
                        clearTimeout(deleteThread);
                        deleteThread = null;
                    }

                    e.preventDefault();
                    e.stopPropagation();
                    unbindFunc(that_dom);
                    unbindFunc = null;
                    
                });

                function rewardDown(data) {
                    
                    var html = '';
                    var gettype = 0;
                    var i = 1;
                    html += '<div class="heavenreward_reward_box">';

                    if (parseInt(data.refinestone) != 0) {
                        html += '<span class="reward_down pos' + i +'"><em class="refinestone"></em>x' + data.refinestone + '</span>';
                        i++;
                    }

                    if (parseInt(data.colorstone) != 0) {
                        html += '<span class="reward_down pos'+ i +'"><em class="colorstone"></em>x' + data.colorstone + '</span>';
                        i++;
                    }

                    if (parseInt(data.bloodstone) != 0) {
                        html += '<span class="reward_down pos'+ i +'"><em class="bloodstone"></em>x' + data.bloodstone + '</span>';
                    }

                    html += '</div>';
                    $('#heavenrewardpop_wrap').append(html);
                    
                    deleteThread = setTimeout(function() {

                        miscpop.hide('heavenrewardpop');

                    }, 3000);
                    
                    html = null;
                };

                function getReward(refinestone, colorstone, bloodstone) {
                    
                    var html = '';

                    if (refinestone) {
                        html += '<span class="stone refinestone">' + (parseInt(refinestone) >= 100000 ? player.getWan(refinestone) : refinestone) + '</span>';
                    }

                    if (colorstone) {
                        html += '<span class="stone colorstone">' + (parseInt(colorstone) >= 100000 ? player.getWan(colorstone) : colorstone) + '</span>';
                    }

                    if (bloodstone) {
                        html += '<span class="stone bloodstone">' + (parseInt(bloodstone) >= 100000 ? player.getWan(bloodstone) : bloodstone) + '</span>';
                    }

                    return html;
                }

                that_dom.find('.nowheaven').html(lang('heavenworld_floor', [res.info.mission]) + '<em class="name">' + res.info.name + '</em>');
                that_dom.find('.nowreward').html(res.base_reward);
                that_dom.find('#addtype1').html('<em class="pigeon"></em><span class="price">' + lang('heavenworld_gold', [res.two_reward.gold]) + '</span>' + getReward(res.two_reward.refine_reward, res.two_reward.color_reward));
                that_dom.find('#addtype2').html('<em class="pigeon"></em><span class="price">' + lang('heavenworld_gold', [res.four_reward.gold]) + '</span>' + getReward(res.four_reward.refine_reward, res.four_reward.color_reward, res.four_reward.blood_reward));
                that_dom.find('#addtype3').html('<em class="pigeon"></em><span class="price">' + lang('heavenworld_gold', [res.eight_reward.gold]) + '</span>' + getReward(res.eight_reward.refine_reward, res.eight_reward.color_reward, res.eight_reward.blood_reward));
                
                that_dom.undelegate('.addtype', 'touchend').delegate('.addtype', 'touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if ($(this).hasClass('now')) {
                        $(this).removeClass('now');
                    } else {
                        that_dom.find('.addtype').removeClass('now');
                        $(this).addClass('now');
                    }
                });

                that_dom.find('#fetch_btn').unbind('touchend').bind('touchend', function(e) { //领取按钮
                    
                    if (xGame.checkLock()) {
                        return false;
                    }
                    unbindFunc(that_dom);
                    unbindFunc = null;
                    
                    e.preventDefault();
                    e.stopPropagation();
                    //选择的
                    var selectDom = that_dom.find('.addtype.now');
                    if (selectDom.length > 0) {
                        var addtype = selectDom.attr('addtype');
                    } else {
                        var addtype = 0; //默认类型
                    }

                    var fetchRender = function(res) {

                        xGame.unlock();
                        
                        if (res.status == 2) {
                            xGame.toast.show(lang('had_receive'));
                            return false;
                        } else if (res.status == 3) {
                            xGame.toast.show(lang('no_battle_reward'));
                            return false;
                        } else if (res.status == -1) {
                            xGame.toast.show(lang('lv_not_enough'));
                            return false;
                        } else if (res.status == -2) {
                            xGame.toast.show(lang('heavenworld_err1'));
                            return false;
                        } else if (res.status == -3) {
                            xGame.toast.show(lang('equiprefinepop_refine_msg4'));
                            return false;
                        } else if (res.status == -4) {
                            xGame.toast.show(lang('heavenworld_err2'));
                            return false;
                        } else if (res.status == -5) {
                            xGame.toast.show(lang('heavenworld_err3'));
                            return false;
                        } else if (res.status == -6) {
                            xGame.goPay(lang('gold_not_enough'));
                            return false;
                        } else if (res.status == -7) {
                            xGame.toast.show(lang('heavenworld_err17'));
                            return false;
                        } else if (res.status != 1) {
                            xGame.toast.show(lang('reward_get_fail'));
                            return false;
                        } else {
                            player.updateinfo({
                                "gold": parseInt(res.gold)
                            });

                            heavenWorld.buildTop(res.resource);
                            rewardDown(res.reward);
                        }
                    };
                    loadContents({
                        'c': 'heaven',
                        'm': 'get_reward',
                        'type': addtype
                    },
                    fetchRender);
                });

                /* 展示弹窗 */
                that_dom.show();
                xGame.unlock();
            };
            loadContents({
                'c': 'heaven',
                'm': 'reward_info'
            },
            initRender);
        },

		/* 四象圣域奖励框 */
        sanctumRewardbox: function() {
            var that = this;
			
			var get_msg = function(status) {
				switch(parseInt(status)) {
					case 2:
						xGame.toast.show(lang('sanctumworld_func_close'));
						break;
					case -1:
						xGame.toast.show(lang('get_user_info_fail'));
						break;
					case -2:
						xGame.toast.show(lang('sanctumworld_get_history_fail'));
						break;
					case -3:
						xGame.toast.show(lang('sanctumworld_had_receive'));
						break;
					case -4:
						xGame.toast.show(lang('sanctumworld_unreach'));
						break;
					case -5:
						xGame.toast.show(lang('get_reward_info_fail'));
						break;
					case -6:
						xGame.toast.show(lang('sanctumworld_get_fb_fail'));
						break;
					case -7:
						xGame.toast.show(lang('gold_not_enough'));
						break;
					default:
						xGame.toast.show(lang('undefined_error'));
						break;
				}
			};
			
            var initRender = function(res) {
				
				if (res.status != 1) {
                    get_msg(res.status);
                    return false;
                }

                var that_dom = xGame.loadPopTmpl('towerreward');
                that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    get_msg = null;
                    miscpop.hide('towerrewardpop');
                    e.preventDefault();e.stopPropagation();
                });

                that_dom.find('.nowtower').html(lang('stage_mission', [res.mission.stage, res.mission.id]));
                that_dom.find('.nowsoul').html(' x' + res.gem + ((res.effect != 0 && res.effect != undefined) ? '<em style="color:red;"> ( ' + res.effect + '% ' + lang('workshop_country_addition') + ')</em>' : '')).addClass('gem');
                that_dom.find('#addtype1').html('<em></em><p class="price">' + lang('cost_get', [res.normal_reward.gold, res.normal_reward.multiple]) + '</p>').attr('gemnum', parseInt(res.gem) * parseFloat(res.normal_reward.multiple)); //第1个加倍
                that_dom.find('#addtype2').html('<em></em><p class="price">' + lang('cost_get', [res.best_reward.gold, res.best_reward.multiple]) + '</p>').attr('gemnum', parseInt(res.gem) * parseFloat(res.best_reward.multiple)); //第2个加倍
                that_dom.undelegate('.addtype', 'touchend').delegate('.addtype', 'touchend',
                function(e) {
                    that_dom.find('.now').removeClass('now');
					$(this).addClass('now');
                    e.preventDefault();
                    e.stopPropagation();
                });

                that_dom.find('#fetch_btn').attr('gemnum', res.gem).unbind('touchend').bind('touchend',
                function(e) { //领取按钮
                    e.preventDefault();
                    e.stopPropagation();
                    //选择的
                    var selectDom = that_dom.find('.addtype.now');
                    if (selectDom.length > 0) {
                        var addtype = selectDom.attr('addtype');
                        var fetchnums = parseFloat(selectDom.attr('gemnum'));
                        //默认的
                    } else {
                        var addtype = 0; //默认类型
                        var fetchnums = parseInt($(this).attr('gemnum')); //默认该得到的原魂数量
                    }

                    var fetchRender = function(res) {
                        if (res.status != 1) {
							get_msg(res.status);
							return false;
						} 
						player.updateinfo({
							"gold": parseInt(res.all_gold)
						});
						xGame.toast.show(lang('sanctumworld_receive_gemp', [fetchnums]));
						sanctumWorld.beastStageDom.find('#own_gem').html(res.all_gem);
						miscpop.hide('towerrewardpop');
                    };
                    loadContents({
                        'c': 'sanctum',
                        'm': 'get_reward',
                        'type': 1,
						'multiple': addtype
                    },
                    fetchRender);
                });

                /* 展示弹窗 */
                that_dom.show();
                xGame.unlock();
            };
            loadContents({
                'c': 'sanctum',
                'm': 'get_reward',
                'type': 0
            },
            initRender);
        },

        /* 邮件里进入战报 ,type只能是message或者map,tower*/
        viewBattleFromMail: function(id, type, stage) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    if (mailpop && mailpop.isshow == true) {
                        $('#mailpop').hide();
                    }
                    setTimeout(function() {
                        var _old_world = xGame.nowWorld;
                        var bg = '';
                        if (type == 'message') {
                            bg = 'pvp'
                        } else if (type == 'tower') {
                            bg = 'tower'
                        } else if (type == 'heaven') {
                            bg = 'heaven'
                        } else if (type == 'sanctum') {
                            bg = 'sanctum' + stage;
                        } else if (type == 'map') {
                            bg = res.info.background;
                        } else if (type == 'treasure') {
                            bg = 'treasure';
                        } else if (type == 'worldarena') {
                            bg = 'worldarena';
                        } else if (type == 'countrybattle') {
                            xGame.loadWorld('countrybattle', res);
                            return;
                        } else if (type == 'champions') {
                             bg = 'champions';
                        } else if (type == 'war') {
                            bg = 'war';
                        } else if (type == 'pacify'){
                            bg = 'pacify' + stage;
                        }
                        xGame.loadBattle(res, null, bg,
                        function() {
                            xGame.loadWorld(_old_world);
                            if (mailpop && mailpop.isshow == true) {
                                $('#mailpop').show();
                            }
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            if (type == 'tower') {
                loadContents({
                    'c': type,
                    'm': 'get_report_info',
                    'id': id
                },
                render);
            } else if (type == 'heaven') {
                loadContents({
                    'c': type,
                    'm': 'get_report_info',
                    'id': id
                },
                render);
            } else if (type == 'sanctum') {
                loadContents({
                    'c': 'sanctum',
                    'm': 'get_report_info',
                    'id': id
                },
                render);
            } else if (type == 'treasure') {
                loadContents({
                    'c': 'island',
                    'm': 'get_report_info',
                    'id': id
                },
                render);
            } else if (type == 'worldarena') {
                loadContents({
                    'c': 'worldarena',
                    'm': 'get_report_info',
                    'id': id
                },
                render);
            } else if (type == 'countrybattle') {
                loadContents({
                    'c': 'siege',
                    'm': 'get_report',
                    'id': id
                },
                render);
            } else if (type == 'champions') {
                loadContents({
                    'c': 'god',
                    'm': 'get_report_info',
                    'id' : id,
                    'stage' : stage
                },
                render);
            } else if (type == 'war') {
                loadContents({
                    'c': type,
                    'm': 'get_report_info',
                    'id': id,
                    'stage': stage
                },
                render);
            } else if (type == 'pacify') {
                loadContents({
                    'c': 'copies',
                    'm': ' get_report_info',
                    'd': 'newequip',
                    'id': id
                },
                render);
            } else {
                loadContents({
                    'c': type,
                    'm': 'report_detail',
                    'id': id
                },
                render);
            }

        },

        /* 邮件领取奖励 */
        fetchRewardFromMail: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                res.status = parseInt(res.status);
                switch (res.status) {
                case 1:
                    //成功，该加钱的加钱
                    var updateinfo = {};
                    if (res.info.add_act > 0) {
                        updateinfo['act'] = res.info.all_act;
                    }
                    if (res.info.add_gold > 0) {
                        updateinfo['gold'] = res.info.all_gold;
                    }
                    if (res.info.add_silver > 0) {
                        updateinfo['silver'] = res.info.all_silver;
                    }
                    player.updateinfo(updateinfo);
                    $('#mail_fetch').hide();
                    //$('#mail_ok').show();
                    xGame.toast.show(lang('get_reward_success'));
                    break;
                case - 1 : xGame.toast.show(lang('no_mail_to_receive'));
                    break;
                case - 2 : xGame.toast.show(lang('mail_open_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('gift_not_exist'));
                    break;
                case - 4 : xGame.toast.show(lang('bag_had_receive'));
                    break;
                default:
                    xGame.toast.show(lang('receiver_reward_fail_wait'));
                    break;
                }
            };
            loadContents({
                'c':
                'message',
                'm': 'open_gift',
                'id': id
            },
            render);

        },
        createLevelUp: function(levelupCallback) {
            var levelReward = null;
            var levelFunc = null;
            var _lr = xGame.xsGet(player.info.uid + 'levelReward', true);
            var _lf = xGame.xsGet(player.info.uid + 'levelFunc', true);
            if(_lr) {
                levelReward = JSON.parse(_lr);
            }
            if(_lf) {
                levelFunc = JSON.parse(_lf);
            }

            if (levelReward == null || levelReward[1] == null || levelFunc.func_info == null) {
                xGame.xsRemove(player.info.uid + 'levelReward', true);
                xGame.xsRemove(player.info.uid + 'levelFunc', true);
                return false;
            }

            var levelupPop = xGame.loadPopTmpl('levelup');
            var openfunction = levelupPop.find('.open_function');   // 功能开启
            var reward = levelupPop.find('.reward');    // 奖励

            // 确定按钮
            levelupPop.find('#confirmbtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.xsRemove(player.info.uid + 'levelReward', true);

                if (parseInt(levelFunc.func_open) == 1) {  // 如果有开启的功能
                    miscpop.openfunction(levelFunc, levelupCallback);
                } else {
                    xGame.xsRemove(player.info.uid + 'levelFunc', true);
                    if (levelupCallback) {
                        levelupCallback();
                    }
                }

                levelReward = null;
                levelFunc = null;
                openfunction = null;
                reward = null;
                levelupPop.remove();
                levelupPop = null;

                e.preventDefault();
            });

            levelupPop.find('.level').html(lang('leveluppop_level', [player.info.level]));  // 玩家等级
            levelupPop.find('.reward').addClass('type1').html(levelReward[1]);  // 奖励

            if (levelFunc.func_mark != '') {    // 如果有要开启的功能

                if (levelFunc.func_mark != 'avatar') {
                    openfunction.html(lang('leveluppop_func', [levelFunc.func_level, levelFunc.func_name]));
                    levelupPop.find('.function_bg').addClass(levelFunc.func_mark);
                } else {
                    openfunction.html(lang('leveluppop_avatar', [levelFunc.func_level]) + levelFunc.func_info);
                    levelupPop.find('.function_bg').addClass(levelFunc.func_mark).css('background-image', 'url(' + xGame.avatar(levelFunc.imageid) + ')');
                }
            } else {
                levelupPop.find('.function_bg').addClass('levelup').html(levelFunc.func_info);
            }

        },

        openfunction: function (levelFunc, levelupCallback) {
            var openfunctionPop = xGame.loadPopTmpl('openfunction');
            var btn = openfunctionPop.find('#confirmbtn');

            //前往查看按钮
            btn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                openfunctionPop.remove();
                openfunctionPop = null;
                e.preventDefault();
                xGame.xsRemove(player.info.uid + 'levelFunc', true);

                if (levelupCallback) {
                    levelupCallback();
                }
            });

            openfunctionPop.find('.function').html(levelFunc.open.name);    // 功能名称
            openfunctionPop.find('.level').html(lang('leveluppop_level', [player.info.level])); // 功能描述
            openfunctionPop.find('.level_des').html(levelFunc.open.info); // 功能描述
            openfunctionPop.find('.function_bg').addClass(levelFunc.open.mark);
        },

        /* 主公升级 */
        createLevelUp_old: function(levelupCallback) {
            var movieclip;
            var leveluptitle;

            var cfg = {
                "x": 290,
                "y": 160
            };
            if ($.os.ipad) {
                cfg.x += 32;
            }
            var callback = function() {
                xGame.doAnimate(leveluptitle, 'levelup_fadeOutUp', '0.3s',
                function() {
                    leveluptitle.remove();
                    if (levelupCallback) {
                        levelupCallback();
                        levelupCallback = null;
                    }
                });
            }

            //创建动画底图
            var mc = new movieClipClass({
                "img": staticUrl + 'img/common/levelup_anim.png',
                "width": 380,
                "height": 250,
                "x": cfg.x,
                "y": cfg.y,
                "loop": false,
                "endDestory": true,
                "callback": callback,
                "frames": [{
                    rect: [0, 0],
                    interval: 110
                },
                {
                    rect: [380, 0],
                    interval: 110
                },
                {
                    rect: [760, 0],
                    interval: 110
                },
                {
                    rect: [0, 250],
                    interval: 110
                },
                {
                    rect: [380, 250],
                    interval: 110
                },
                {
                    rect: [760, 250],
                    interval: 110
                },
                ]
            });
            //开始播放
            var temp_dom = $('#world');
            mc.appendTo(temp_dom);
            mc.play();
            //创建title
            leveluptitle = $('<div class="levelup_title" style="width:380px;height:250px;left:' + cfg.x + 'px;top:' + cfg.y + 'px;"></div>');
            temp_dom.append(leveluptitle);
            temp_dom = null;
        },

        /* 创建通关奖励 */
        passShiliReward: function(levelid, rootcallback, alldone) {
            //2013-8-26 修复通关奖励弹窗bug 原因：手动调用和player.reloadInfo();调用时重复，导致卡死
            if ($('.passreward_box_wrap').length >= 1) {
                return false;
            }
            var left = ($.os.ipad == true ? 1024 : (isIPhone5 ? 1136 : 960)) / 2 - 120;
            var top = $('body').height() / 2 - 100;
            var box_dom = $('<div id="passreward_box_wrap" class="passreward_box_wrap"><div id="passreward_box" class="btn" style="left:' + left + 'px;top:' + top + 'px;"></div></div>');
            $('body').append(box_dom);
            box_dom.unbind('touchend').one('touchend',
            function(e) {
                e.preventDefault();
                e.stopPropagation();
                var render = function(res) {
                    //res = {"status":1,"gettype":"2","item":{"id":"1","uid":"4876","equipid":"1","gid":"0","name":"\u7269\u653b\u5929\u8d4b\u5377\u8f74","type":"6","quality":"1","needlevel":"0","level":"0","power":"0","powerup":"0","saleprice":"1000","chip":"0","number":"62"},"rewardnum":"20"};
                    if (res.status < 1) {
                        var msg = lang('receive_error_call_gm');
                        switch (res.status) {
                        case - 1 : msg = lang('no_reward');
                            break;
                        case - 2 : msg = lang('shili_uncomplete');
                            break;
                        case - 3 : msg = lang('had_receive');
                            break;
                        case - 4 : msg = lang('have_no_reward');
                            break;
                        }

                        box_dom.remove();
                        xGame.toast.show(msg);
                        return false;
                    }

                    /* 把宝箱打开 */
                    var box_callback = function() { //callback处理
                        if (box_dom) {
                            box_dom.remove();
                            box_dom = null;
                        }
                        if (!res || !res.item) {
                            res = res || {};
                            res.gettype = res.gettype || 1;
                            res.rewardnum = res.rewardnum || 1;
                            res.item = {
                                id: 101,
                                name: lang('reputation') || '通关宝赏',
                                description: '恭喜主公大破敌军，威震华夏！',
                                quality: 2
                            };
                        }
                        //debug
                        //res.item = {"id":1, "name":"小李飞刀", "quality":1, "desc":'test'};
                        //通过奖励
                        if(alldone ==  true) {
                            var themissionlevel = '';
                        } else {
                            var themissionlevel = map_ini['world'][levelid].name || '';
                        }
                        var url;
                        if (parseInt(res.gettype) == 1) {//普通道具
                            url = xGame.itemurl(res.item.id);
                        } else if(parseInt(res.gettype) == 3) {//声望
                            url = staticUrl + 'img/common/prestige_icon.png';
                            res.item = {
                                id: 0,
                                name: lang('reputation'),
                                description: lang('passreward_prestige_desc'),
                                quality: 1,
                            };
                        } else {//天赋卷轴
                            url = staticUrl + 'img/pop/talent_item' + res.item.id + '.png';
                            res.item.description = lang('make_tianfu_up');
                        }
                        /*新添加一个url用来区别卷轴*/
                        var rewarditem = {
                            "itemid": res.item.id,
                            "mapname": themissionlevel,
                            "itemname": res.item.name,
                            "itemurl": 'background:url(' + url + ') center center no-repeat',
                            "quality": res.item.quality,
                            "desc": res.item.description,
                            "color": helper.getItemColor(res.item.quality),
                            'num': res.rewardnum,
                            'url': url
                        };
                        url = null;
                        var world_dom = $('#world');
                        world_dom.append($.template('passrewardpop_tmpl', rewarditem));
                        var passrewardpop = world_dom.find('#passrewardpop');
                        passrewardpop.show();
                        xGame.doAnimate(passrewardpop.find('#passrewardpop_wrap'), 'newhero_scalein', '0.5s');

                        passrewardpop.find('.pop_btn_middle').unbind('touchend').one('touchend',
                        function(e) {
                            e.preventDefault();e.stopPropagation();
                            passrewardpop.hide();
                            word_dom = null;
                            passrewardpop.remove();
                            passrewardpop = null;
                            if (rootcallback) { //这个传递的够远--!，不知道能不能用
                                rootcallback();
                                rootcallback = null;
                            }
                            if(parseInt(res.gettype) != 3) {
                                miscpop.itemfly2bag(rewarditem);
                            }
                            player.info.newresult = 0;

                            xGame.xsRemove(player.info.uid + 'shilialldone');
                        });

                    };
                    box_dom.addClass('open');
                    var movieclip = new movieClipClass({
                        "img": staticUrl + 'img/battle/resutl_box_stars.png',
                        "width": 261,
                        "height": 233,
                        "x": -30,
                        "y": -20,
                        "loop": false,
                        "endDestory": true,
                        "callback": box_callback,
                        "frames": [{
                            rect: [0, 0],
                            interval: 60
                        },
                        {
                            rect: [261, 0],
                            interval: 60
                        },
                        {
                            rect: [522, 0],
                            interval: 60
                        },
                        {
                            rect: [783, 0],
                            interval: 60
                        },
                        {
                            rect: [1044, 0],
                            interval: 60
                        },
                        {
                            rect: [1305, 0],
                            interval: 60
                        },
                        {
                            rect: [1566, 0],
                            interval: 60
                        }],
                    });
                    box_dom.find('#passreward_box').append(movieclip.obj);
                    movieclip.play();

                    render = null;
                };
                //render({"status":1, "item":{"itemid":1, "itemname":"方天画戟", "quality":3}});
                loadContents({
                    'c': 'map',
                    'm': 'get_newresult',
                    'id': levelid
                },
                render);
            });
        },

        bindPopConfirm: function(callback, isreg) {
            var messages = isreg == true ? lang('word_msg2') : lang('word_msg1');
            xGame.confirm.show(lang('qiangulifang'), messages, 1,
            function() {
                xGame.confirm.hide(function() {
                    miscpop.bindPop(callback);
                });
            });
        },

        /* 绑定界面弹窗 */
        bindPop: function(bindAfterCallback) {
            var that = this;

            // if (!xGame.xsExist('tryuid')) {
            //     xGame.toast.show(lang('you_had_bind_account'));
            //     if (guide.now == 824) {
            //         guide.show(825); //继续引导
            //     }
            //     return false;
            // }

            xGame.clearCloud();

            var that_dom = xGame.loadPopTmpl('bind');

            var bind_u = that_dom.find('#breg_username');
            var bind_p = that_dom.find('#breg_password');
            var bind_m = that_dom.find('#breg_mobile');

            bind_u.unbind('touchend').one('touchend',
            function() {
                bind_u.val('');
            });
            bind_p.unbind('touchend').one('touchend',
            function() {
                bind_p.val('');
            });
            bind_m.unbind('touchend').one('touchend',
            function() {
                bind_m.val('');
            });
            if(xGame.guide == false) {//没有在引导期间的时候，允许放弃帐号
                that_dom.find('#hidebtn').unbind('touchend').bind('touchend', function(e) {
                    xGame.confirm.show(lang('bindpop_giveup_title'), lang('bindpop_giveup_message', [player.info.nickname]), 2,
                    function() {
                        xGame.confirm.hide();
                        miscpop.exitGame();
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('bindpop_giveup_btn'),
                    lang('bindpop_cancel_btn')
                    );
                    e.preventDefault();
                    e.stopPropagation();
                }).show();//显示按钮
            }

            that_dom.find('#rbtn_ingame').unbind('touchend').bind('touchend',
            function() {
                if (xGame.checkLock()) {
                    return false;
                }
                if (bind_u.val() == lang('4_16')) {
                    bind_u.val('');
                }
                if (bind_p.val() == lang('6_16')) {
                    bind_p.val('');
                }
                if (bind_m.val() == lang('only_find_psw')) {
                    bind_m.val('');
                }

                var render = function(res) {
                    xGame.unlock();
                    if (res.status == 1) {
                        xGame.confirm.show(lang('bind_complete'), lang('bind_complete_msg', [bind_u.val(), bind_p.val()]), 1,
                        function() {
                            xGame.confirm.hide(function() {
                                miscpop.hide('bindpop');
                                xGame.xsRemove('tryuid');
                                xGame.xsRemove('trykey');
                                var lastaccountsaved = bind_u.val() + ',' + bind_p.val();
                                xGame.xsSet('lastaccountsaved', lastaccountsaved);
                                // if (guide.now == 824) {
                                //     guide.show(825); //继续引导
                                // }
                                if (xGame.nowWorld == 'map') {
                                    xGame.createCloud();
                                }
                                if (bindAfterCallback) {
                                    bindAfterCallback();
                                }
                            });
                        });

                    } else if (res.status == -1) {
                        xGame.toast.show(lang('bind_fail'));
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('you_had_bind_account'));
                    } else if (res.status == -3) {
                        xGame.toast.show(lang('account_mate_error'));
                    } else if (res.status == -4) {
                        xGame.toast.show(lang('psw_mate_error'));
                    } else if (res.status == -5) {
                        xGame.toast.show(lang('account_is_exist'));
                    }
                    render = null;
                };

                if (helper.checkUserName(bind_u.val()) && helper.checkPassWord(bind_p.val()) && helper.checkTel(bind_m.val())) {
                    http(UCAPI + '&c=register&m=binding&', {
                        'uid': player.info.uid,
                        'u': bind_u.val(),
                        'p': bind_p.val(),
                        'mobile': bind_m.val(),
                        'v': localStorage['sg_ver'],
                        'mac': xGame.macaddr,
                        'adid': xGame.adid,
                        'devicetoken': xGame.devicetoken,
                        'channel': channelID
                    },
                    render);
                } else {
                    xGame.unlock();
                }

            });

            that_dom.show();
            that_dom = null;

        },

        openBossConfirm: function() {

            if (xGame.nowWorld == 'login' || xGame.nowWorld == 'role' || xGame.nowWorld == 'battle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'countrybattle' || xGame.nowWorld == 'boss' || xGame.nowWorld == 'gvgbattle') { //战斗里不弹
                return false;
            }
            //2013-7-18日更新，战神殿和群服争霸不要显示世界boss弹窗，容易影响玩家load战斗数据
            if ((bossWorld && bossWorld.e) || (bossbandpop && bossbandpop.e) || (iospaypop && iospaypop.e) || (rebatepop && rebatepop.e) || (countrybattleWorld && countrybattleWorld.e) || (gvgbattleWorld && gvgbattleWorld.e) || (gvgWorld && gvgWorld.e) || (championsWorld && championsWorld.e) || (showmarpop && showmarpop.e)) { //在boss弹窗里、支付弹窗里不弹
                return false;
            }
            if (xGame.guide == true && guide.now < 1434) { //初级引导期间不弹
                return false;
            }
            var _lasttime = xGame.xsGet('openboss');
            if (_lasttime && _lasttime == helper.formatDate()) { //每日提醒一次
                return false;
            }

            xGame.confirm.show(lang('world_boss'), lang('boss_battle_ask'), 2,
            function() {
                //关闭所有弹窗
                xGame.closePops();

                //进入
                xGame.confirm.hide(function() {
                    xGame.loadWorld('boss');
                });
            },
            function() {
                xGame.confirm.hide();
            });
            xGame.xsSet('openboss', helper.formatDate());
        },
        
        openNianConfirm: function() {

            if (xGame.nowWorld == 'login' || xGame.nowWorld == 'role' || xGame.nowWorld == 'battle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'countrybattle' || xGame.nowWorld == 'boss' || xGame.nowWorld == 'gvgbattle') { //战斗里不弹
                return false;
            }
            //2013-7-18日更新，战神殿和群服争霸不要显示世界boss弹窗，容易影响玩家load战斗数据
            if ((nianWorld && nianWorld.e) || (bossWorld && bossWorld.e) || (bossbandpop && bossbandpop.e) || (iospaypop && iospaypop.e) || (rebatepop && rebatepop.e) || (countrybattleWorld && countrybattleWorld.e) || (gvgbattleWorld && gvgbattleWorld.e) || (gvgWorld && gvgWorld.e) || (championsWorld && championsWorld.e) || (showmarpop && showmarpop.e)) { //在boss弹窗里、支付弹窗里不弹
                return false;
            }
            if (xGame.guide == true && guide.now < 1434) { //初级引导期间不弹
                return false;
            }
            var _lasttime = xGame.xsGet('opennewyearboss');
            if (_lasttime && _lasttime == helper.formatDate()) { //每日提醒一次
                return false;
            }

            xGame.confirm.show(lang('notice'), lang('nian_battle_ask'), 2,
            function() {
                //关闭所有弹窗
                xGame.closePops();

                //进入
                xGame.confirm.hide(function() {
                    xGame.loadWorld('nian');
                });
            },
            function() {
                xGame.confirm.hide();
            });
            xGame.xsSet('opennewyearboss', helper.formatDate());
        },

        openCbattleConfirm : function(war) {

            if (xGame.nowWorld == 'login' || xGame.nowWorld == 'role' ||  xGame.nowWorld == 'battle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'countrybattle') { //战斗里不弹
                return false;
            }
            if ((nianWorld && nianWorld.e) || (bossWorld && bossWorld.e) || (bossbandpop && bossbandpop.e) || (iospaypop && iospaypop.e) || (rebatepop && rebatepop.e) || (countrybattleWorld && countrybattleWorld.e) || (showmarpop && showmarpop.e)) { //在boss弹窗里、支付弹窗里不弹
                return false;
            }
            if (xGame.guide == true && guide.now < 1434) { //初级引导期间不弹
                return false;
            }
            var _lasttime;
            if (war == 1) {//1为准备
                _lasttime = xGame.xsGet('opencbattle');

                if (_lasttime && _lasttime == helper.formatDate()) { //每日提醒一次
                    return false;
                }

                xGame.confirm.show(lang('country_battle'), lang('country_battle_ask'), 2,
                function() {
                    //关闭所有弹窗
                    xGame.closePops();

                    //进入
                    xGame.xsSet('opencbattle', helper.formatDate());
                    xGame.confirm.hide(function() {
                        xGame.loadWorld('countrybattle');
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            } else if (war == 2){ //2为马上开始了
                _lasttime = xGame.xsGet('opencbattle_now');

                if (_lasttime && _lasttime == helper.formatDate()) { //每日提醒一次
                    return false;
                }

                xGame.confirm.show(lang('country_battle'), lang('country_battle_ask2'), 2,
                function() {
                    //关闭所有弹窗
                    xGame.closePops();

                    //进入
                    xGame.xsSet('opencbattle_now', helper.formatDate());
                    xGame.confirm.hide(function() {
                        xGame.loadWorld('countrybattle');
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            } else if (war == 3){ //3为国家管理层可以申请占领了
                _lasttime = xGame.xsGet('opencbattle_apply');

                if (_lasttime && _lasttime == helper.formatDate()) { //每日提醒一次
                    return false;
                }

                xGame.confirm.show(lang('country_battle'), lang('country_battle_ask3'), 2,
                function() {
                    //关闭所有弹窗
                    xGame.closePops();

                    //进入
                    xGame.xsSet('opencbattle_apply', helper.formatDate());
                    xGame.confirm.hide(function() {
                        xGame.loadWorld('countrymap');
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            }
        },

        openDinnerConfirm: function() {
            //已经在年夜饭里的就不显示了
            if (newyeardinnerpop != undefined && newyeardinnerpop.isshow) {
                return false;
            }

            if (xGame.nowWorld == 'login' || xGame.nowWorld == 'role' || xGame.nowWorld == 'battle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'elitebattle' || xGame.nowWorld == 'countrybattle') { //战斗里不弹
                return false;
            }
            if ((bossWorld && bossWorld.e) || (bossbandpop && bossbandpop.e) || (iospaypop && iospaypop.e) || (rebatepop && rebatepop.e) || (countrybattleWorld && countrybattleWorld.e) || (showmarpop && showmarpop.e)) { //在boss弹窗里、支付弹窗里不弹
                return false;
            }
            if (xGame.guide == true && guide.now < 1434) { //初级引导期间不弹
                return false;
            }
            var _lasttime = xGame.xsGet('openDinner');
            if (_lasttime && _lasttime == helper.formatDate()) { //每日提醒一次
                return false;
            }

            xGame.confirm.show(lang('newyear_dinner'), lang('newyear_dinner_ask'), 2,
            function() {
                //关闭所有弹窗
                xGame.closePops();

                //进入
                xGame.xsSet('newyear_dinner', helper.formatDate());
                xGame.confirm.hide(function() {
                    xGame.showpop('newyeardinner');
                });
            },
            function() {
                xGame.confirm.hide();
            });
        },

        /* 获得新武将 */
        //miscpop.newhero({"name":"赵云祥","type":2,"image":12,"wuli":100,"zhili":200,"tili":300,"skill":"sssssssssssssssssssssss","talent":"fffffffffffffffff","star":5}, function() { alert('aaaa'); } );
        newhero: function(data, callback, lagtime) {
            var that = this;

            data = data || { generalid: 10, name: '张飞', image: 'hero_10', type: 1, star: 3, wuli: 98, zhili: 45, tili: 85, skill: { name: '万夫莫敌', description: '' }, talent: { name: '神威', description: '' } };
            data.skill = data.skill || { name: '战法', description: '' };
            data.talent = data.talent || { name: '神威', description: '' };

            var lagtime = lagtime ? lagtime : 2000;
            lagtime = parseInt(data.generalid || 10) > 5 ? lagtime : 10;
            var that_dom = xGame.loadPopTmpl('newhero');

            that_dom.find('#newhero_avatar').css({
                'background': 'url(' + xGame.avatar(data.image, true) + ') 0 0 no-repeat'
            });
            that_dom.find('#newhero_type').html(helper.getItemVocation(data.type));
            if (appLanguage == 'th') {
                that_dom.find('#newhero_name').html(data.name);
            } else {
                that_dom.find('#newhero_name').html(helper.getBrName(data.name)).addClass('word' + data.name.length);
            }
            that_dom.find('#newhero_stars').css('width', (data.star * 18));
            that_dom.find('#newhero_wuli').html(data.wuli);
            that_dom.find('#newhero_zhili').html(data.zhili);
            that_dom.find('#newhero_tili').html(data.tili);
            that_dom.find('#newhero_skill').html('<span>' + data.skill.name + '</span>：' + data.skill.description);
            that_dom.find('#newhero_talent').html('<span>' + data.talent.name + '</span>：' + data.talent.description);

            var scroller = new iScroll('newhero_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
            });

            setTimeout(function() {//延迟绑定事件，防止武将一出来就被点到按钮。
                that_dom.find('#newhero_okbtn').unbind('touchend').bind('touchend',
                function(e) {
                    miscpop.hide('newheropop');
                    if (callback) {
                        callback();
                    }
                    e.preventDefault();
                    e.stopPropagation();
                });
            }, lagtime);
            that_dom.show();

            xGame.doAnimate(that_dom.find('#newheropop_wrap'), 'newhero_scalein', '0.5s');
            player.reloadInfo();
        },

        /* 国战：查看城池信息 */
        countrymapCityInfo: function(cityid) {
            var that = this;

            var initRender = function(res) {

                if (res.status < 1) {
                    xGame.toast.show(lang('no_city_info') + res.status);
                    that = null;
                    initRender = null;
                    xGame.unlock();
                    return false;
                }

                var that_dom = xGame.loadPopTmpl('cityinfo');
                var unbindFunc = function (that_dom) {
                    that_dom.find('.content').find('.hidebtn').unbind('touchend');
                    that_dom.find('#attack_btn').unbind('touchend');
                    that_dom.find('#force_attack_btn').unbind('touchend');
                    that_dom.find('#helpinfo_btn').unbind('touchend');
                    that_dom = null;
                };

                that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }

                    miscpop.hide('cityinfopop');
                    e.preventDefault();
                    e.stopPropagation();
                    unbindFunc(that_dom);
                    unbindFunc = null;
                });

                that_dom.find('.avatarbox').children('p').html(res.info.name);
                that_dom.find('.own').children('span').html(res.info.castellanname);
                that_dom.find('.fightor').children('span').html(res.info.challengername);
                that_dom.find('.battletime').children('span').html(res.info.time);
                that_dom.find('#hold_effect').children('p').html(res.info.str);

                //至尊攻城状态
                if (parseInt(res.info.force) == 1) {
                    that_dom.find('#force_attack_status').show();
                } else {
                    that_dom.find('#force_attack_status').hide();
                }

                if (res.info.open == 1) {
                    var zhanlingFn = function(cityid, sure, force) {
                        var render = function(res) {
                            res.status = parseInt(res.status);
                            if (res.status == 1) {
                                if (force) {
                                    //准备跳战斗
                                    var _now_world = xGame.nowWorld;
                                    setTimeout(function() {
                                        xGame.loadBattle(res,
                                        function() {
                                            that.battleResultFromCountrymap(res, _now_world, cityid);
                                        },
                                        'pvp');
                                    },
                                    500);
                                } else {
                                    $('#attack_btn').attr('class', 'btn pop_btn_middle_disable attack_btn').html(lang('had_apply'));
                                    if (res.msg != undefined) {
                                        xGame.confirm.show(lang('notice'), res.msg, 1,
                                        function() {
                                            xGame.confirm.hide()
                                        });
                                    } else {
                                        xGame.toast.show(lang('apply_wait_for_country_battle'));
                                    }
                                    var obj = $('#map_wrap').find('.atking');
                                    if(obj.length != 0){
                                        obj.remove();
                                        countrymapWorld.clearAnim();
                                    }
                                    $('#worldshili' + cityid).append('<em class="atking c1"></em>');
                                    countrymapWorld.animStart();
                                    miscpop.hide('cityinfopop');
                                    player.reloadInfo();
                                    unbindFunc(that_dom);
                                    unbindFunc = null;
                                }
                            } else {
                                var error_msg = '';
                                switch (res.status) {
                                case - 1 : error_msg = lang('you_have_no_country_zl');
                                    break;
                                case - 2 : error_msg = lang('your_job_to_low');
                                    break;
                                case - 3 : error_msg = lang('country_battle_lock2');
                                    break;
                                case - 4 : error_msg = lang('your_country_had_apply_zhanling_other');
                                    break;
                                case - 5 : error_msg = lang('get_city_info_fail');
                                    break;
                                case - 6 : error_msg = lang('city_not_open');
                                    break;
                                case - 7 : error_msg = lang('your_country_had_apply_zhanling_current');
                                    break;
                                case - 8 :
                                    xGame.confirm.show(lang('notice'), res.msg, 1,
                                    function() {
                                        xGame.confirm.hide()
                                    });
                                    break;
                                case - 9 : error_msg = lang('country_battle_can_not_apply');
                                    break;
                                case - 10 : error_msg = lang('country_battle_city_lock');
                                    break;
                                case - 11 : error_msg = lang('country_battle_not_force_att');
                                    break;
                                case - 12 : error_msg = lang('country_battle_city_lock_defense');
                                    break;
                                case - 13 : error_msg = lang('country_battle_force_gold_low');
                                    break;
                                case 3 :
                                    xGame.confirm.show(lang('notice'), res.msg, 2,
                                    function() {
                                        xGame.confirm.hide();
                                        zhanlingFn(cityid, 1, force);
                                    },
                                    function() {
                                        xGame.confirm.hide();
                                    });
                                default:
                                    error_msg = lang('can_not_zhanling_now');
                                    break
                                }
                                if (res.status != -8 && parseInt(res.status) != 3) {
                                    xGame.toast.show(error_msg);
                                }

                            }
                        };
                        if (force == undefined || force <= 0) {
                            force = 0;
                        } else {
                            force = 1;
                        }
                        if (sure == undefined) {
                        loadContents({
                            'c': 'siege',
                            'm': 'occupy',
                            'id': cityid,
                            'force': force
                        },
                        render);
                        } else {
                            loadContents({
                                'c': 'siege',
                                'm': 'occupy',
                                'id': cityid,
                                'sure': sure,
                                'force': force
                            },
                            render);
                        }
                    };
                    if (player.info.country == res.info.challengercid) {
                        that_dom.find('#attack_btn').unbind('touchend').attr('class', 'btn pop_btn_middle_disable attack_btn').html(lang('had_apply'));
                    } else if (res.status == 2) {
                        that_dom.find('#attack_btn').unbind('touchend').attr('class', 'btn pop_btn_middle_disable attack_btn').html(lang('zhanling'));
                    } else {
                        that_dom.find('#attack_btn').unbind('touchend').bind('touchend',
                        function(e) {
                            zhanlingFn(cityid);
                            e.preventDefault();
                            e.stopPropagation();
                        });
                    }
                    //至尊攻击按钮
                    if(parseInt(res.info.status) == 1) {
                        that_dom.find('#force_attack_btn').show();
                        that_dom.find('#force_attack_btn').unbind('touchend').bind('touchend', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            xGame.confirm.show(lang('notice'), lang('do_ensure_force', [res.price]), 2,
                            function() {
                                xGame.confirm.hide();
                                zhanlingFn(cityid, 0, 1);
                            },
                            function() {
                                xGame.confirm.hide();
                            },
                            lang('ok'), lang('close'));
                        }).html(lang('force_attack'));
                    } else {
                        that_dom.find('#force_attack_btn').unbind('').hide();
                    }
                } else {
                    that_dom.find('#attack_btn').attr('class', 'btn pop_btn_middle_disable attack_btn').html(lang('not_open'));
                }

                that_dom.find('#helpinfo_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.countrymapHelpinfo(res.info.generalname);
                });

                /* 第四步 展示弹窗 */
                that_dom.show();
                //that_dom = null;

                initRender = null;
                xGame.unlock();

            };
            loadContents({
                'c': 'siege',
                'm': 'capital_info',
                'id': cityid
            },
            initRender);
        },

        /* 国战：攻城介绍 */
        countrymapHelpinfo: function(name) {

            var that_dom = xGame.loadPopTmpl('cityhelp');

            that_dom.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.hide('cityhelppop');
                e.preventDefault();
                e.stopPropagation();
            });
            that_dom.find('#cityhelppop_name').html(name);
            that_dom.show();
        },

        /* 国战：结果页面 */
        cbattleresult: function(win, sharedata) {
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('have_not_enter_the_country'));
                    break;
                case - 2 : xGame.toast.show(lang('have_not_receive_reward'));
                    break;
                case 0:
                    xGame.toast.show(lang('reward_please_wait'));
                    break;
                case 1:
                    var that_dom = xGame.loadPopTmpl('cbattleresult');
                    /* that_dom.find('.content').find('.hidebtn').bind('touchend',
                    function() {
                        if (xGame.checkLock(true)) { //不设置属性
                            return false;
                        }
                        miscpop.hide('cbattleresultpop');
                    }); */
                    if(win == 2) {
                        that_dom.find('.flagbg').addClass('lose');
                        that_dom.find('.flag').addClass('flaglose');
                    } else {
                        that_dom.find('.flag').addClass('flagwin');
                    }

                    //载入奖励数据
                    that_dom.find('.reward_wrap').find('.junling').find('span').html(res.rule.act);
                    that_dom.find('.reward_wrap').find('.silver').find('span').html(res.rule.silver);
                    if(parseInt(player.info.level) < 91) {//91级以下不显示玉石
                        that_dom.find('.reward_wrap').find('.jade').hide();
                    } else {
                        that_dom.find('.reward_wrap').find('.jade').find('span').html(res.rule.jade);
                    }
                    that_dom.find('.reward_wrap').find('.soul').find('span').html(res.rule.soul);
                    //载入杀敌榜
                    var html = '';
                    for(var i = 0;i < res.list.length;i ++) {
                        html += '<li>' + res.list[i].nickname + '<span>' + lang('liansha_num', [res.list[i].num]) + '</span></li>';
                    }
                    that_dom.find('#cbattleresult_rl').html(html);
                    html = null;
                    //绑定名字点击事件
                    //绑定领取按钮
                    that_dom.find('#fetch_btn').unbind('touchend').bind('touchend',
                    function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var rander = function(res) {
                            switch (parseInt(res.status)) {
                            case - 1 : xGame.toast.show(lang('have_not_enter_the_country'));
                                break;
                            case - 2 : xGame.toast.show(lang('have_not_receive_reward'));
                                break;
                            case 0:
                                xGame.toast.show(lang('reward_please_wait'));
                                break;
                            case 1:
                                //player.updateinfo({'act':res.all_act,'silver':res.all_silver});
                                player.reloadInfo()
                                miscpop.hide('cbattleresultpop');
                                xGame.loadWorld('countrymap');
                                xGame.toast.show(lang('reward_success'));

                                break;
                            }
                        }
                        loadContents({
                            'c':
                            'siege',
                            'm': 'get_reward',
                            'type': 1
                        },
                        rander);
                    });
                    miscpop.socialShareDisplay(sharedata, 'cbattle');
                    that_dom.show();
                    break;
                }
            }
            loadContents({
                'c': 'siege',
                'm': 'get_reward',
                'type': 0
            },
            Func);
        },

        /* buy */
        iosbuy: function(productid) {
            var that = this;

            //先创建预定ID
            var render = function(res) {
                if (res.status == 1 && res.prepay_id > 0) {
                    xGame.xsSet('pay_prepay_id', res.prepay_id + '');
                    xGame.iosLastId = res.prepay_id;//保存订单
                } else {
                    xGame.toast.show(lang('can_not_buy_package'));
                    return false;
                }

                xGame.showWaitmsg(lang('connecting_to_appstore') + '<br/><span style="font-size:20px;">' + lang('pay_auto_close') + '</span>');

                var successFunc = function(productId, title, description, price) {
                    console.log("productId: " + productId + " title: " + title + " description: " + description + " price: " + price);
                    window.plugins.inAppPurchaseManager.makePurchase(productId, 1);
                }
                var failFunc = function(id) {
                    xGame.hideWaitmsg();
                    console.log("Invalid product id: " + id);

                    if(player.info.appreview == 0) {
                        xGame.confirm.show(lang('webkit_pay'), '您好，充值通道暂未开放', 1,
                        function() {
                            xGame.confirm.hide()
                        });
                    } else {
                        xGame.toast.show(lang('can_not_buy_now'));
                    }

                }
                window.plugins.inAppPurchaseManager.requestProductData(productid, successFunc, failFunc);

            };
            loadContents({
                'c': 'ios',
                'm': 'prepay',
                'product_id': productid
            },
            render);

        },

        /*切磋*/
        battleByUid: function(uid) {
            var that = this;

            //先创建预定ID
            var render = function(res) {
                if (res.status == -1) {
                    //没传uid
                    xGame.toast.show(lang('canshu_lost'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('get_info_fail'));
                } else if (res.status == -3) {
                    xGame.toast.show(lang('infopop_challenge'));
                } else if (res.status == 1) {
                    $('#worldloading').addClass('black').show();

                    if (infopop && infopop.e) { //关掉资料弹窗
                        infopop.hide();
                    }
                    if (strategypop && strategypop.e) { //关掉攻略弹窗
                        strategypop.hide();
                    }
                    miscpop.hide('enemypop'); //关掉将领详情弹窗
                    miscpop.hide('treasureenemypop'); //关掉金银洞将领详情弹窗

                    var _now_world = xGame.nowWorld;
                    setTimeout(function() {
                        xGame.loadBattle(res,
                        function() {
                            that.battleResultFromQC(res, uid, _now_world);
                        },
                        'pvp');
                    },
                    500);

                } else {
                    xGame.toast.show(lang('undefined_error'));
                }

            };
            loadContents({
                'c': 'challenge',
                'm': 'action',
                'uid': uid
            },
            render);
        },

        battleResultFromQC: function(data, touid, oldworld) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('battle_win') + '</p>');
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('.getinfobox').addClass('mine_lose');
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('lose_msg') + '</p>');
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld(oldworld);
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'arena',
                        m: 'send_to_chat',
                        touid: touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
                that.socialShareDisplay(data.share, 'qiecuo');
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }
        },

        battleResultFromCountrymap: function(data, oldworld, cityid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">'+ lang('apply_wait_for_country_battle') +'</p>');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('.getinfobox').addClass('mine_lose');
                //lang('lose_msg')
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('countrymap_can_not_replace') + '</p>');
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld(oldworld);
                if (data.info.win > 0) {
                    //成功占领，隐藏界面提示
                    var obj = $('#map_wrap').find('.atking');
                    if(obj.length != 0){
                        obj.remove();
                        countrymapWorld.clearAnim();
                    }
                    miscpop.hide('cityinfopop');
                    $('#worldshili' + cityid).append('<em class="atking c1"></em>');
                    countrymapWorld.animStart();
                }
            }
            //跳过动画
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            //重播
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }
            player.reloadInfo();
        },

        shareBattle: function(data) {
            var sharerender = function(res) {
                if (res.status == 1) {
                    xGame.toast.show(lang('battle_report_share_to_chat'));
                    CD['chat'].read(); //立即取战报信息
                    player.updateinfo({
                        gold: res.all_gold
                    });
                } else if (res.status == -2) {
                    xGame.toast.show(lang('gold_not_enough_for_send'));
                } else {
                    xGame.toast.show(lang('can_not_share_report'));
                }
                sharerender = null;
            };
            loadContents(data, sharerender);
        },

        /* 进入战斗 */
        enterBattleFromCountry: function(res, touid, str, job) {
            /* 开始进入战斗界面 */
            countrypop.e.hide();

            $('#worldloading').addClass('black').show();

            var _old_world = xGame.nowWorld;
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromCountry(res, _old_world, touid, str, job);
                },
                'pvp');
            },
            500);
        },

        battleResultFromCountry: function(data, oldworld, touid, str, job) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips"></p>');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                that_dom.find('#battleend_main').children('p').html(str + lang('upgrade_to_%job', [job]));
                countrypop.bulidLi(data, 1);
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').children('p').html(str + lang('battle_fail'));
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').children('p').html(str + lang('battle_fail'));
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                countrypop.showBtnByJob();
                //填充放在现实之后，iscroller刷新
                countrypop.e.show();
                countrypop.fillHasCountry(data);
                xGame.loadWorld(oldworld);
                if (data.info.win > 0) {
                    countrypop.createResult(1);
                } else {
                    countrypop.createResult(2);
                }
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'arena',
                        m: 'send_to_chat',
                        touid: touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }
        },

        /*神将之门战斗回调*/
        battleResultFromGeneraltask: function(data, gid, page, missionid, type) {
            var that = this;
            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type5');
            var _tmpl = '<div class="get1">' + lang('comment_reward_title') + '：<p></p></div>';

            that_dom.find('#battleend_main').html(_tmpl);

            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }
            that_dom.find('.flag').addClass(flagclass);
            var str = '';
            if (parseInt(type) == 1) {
                str = '(' + lang('ten_times_bet') + ')';
            } else if (parseInt(type) == 2) {
                str = '(' + lang('hundred_times_bet') + ')';
            }

            //是否首杀
            if (parseInt(data.info.firstkill) == 1) {
                that_dom.find('#battleend_main').find('.get1').addClass('type' + data.info.first_type).children('p').html('+' + data.info.first_value + lang('shousha'));
            } else {
                that_dom.find('#battleend_main').find('.get1').addClass('type' + data.info.re_type).children('p').html('+' + data.info.soul + ' ' + str);
            }

            var doneRender = function() {
                miscpop.hide('battleendpop');

                var returndata = null;
                if (data.clear != 0) {
                    returndata = {
                        clearstar: true
                    };
                } else {
                    returndata = {
                        starid: nowstartid
                    };
                }
                xGame.loadWorld('generaltask', {
                    'generalid': gid,
                    'page': page
                });
            };
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'generaltask',
                        m: 'send_to_chat',
                        id: missionid,
                        //外部传入
                        report: data.info.report,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        battleResultFromElite: function(data) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            if (that_dom == false) {
                return false;
            }
            that_dom.find('#battleend_main').attr('class', 'main type6');

            var _tmpl = '';

            if (parseInt(data.firstkill) == 1) {
                _tmpl = '<div class="get1">' + lang('hero_get_exp') + '<p>+' + data.first_exp + '</p></div>';
                _tmpl += '<div class="getitem"><p>' + lang('get') + '：<span style="color:' + helper.getItemColor(data.firstquality) + '">' + data.firstitemname + '</span></p><div class="itembox"></div></div>';
                that_dom.find('#battleend_main').html(_tmpl);
                that_dom.find('.itembox').addClass('c_i_bj' + data.firstquality);
                if (data.firstitemtype == 1) {
                    that_dom.find('.itembox').html('<img src="' + xGame.itemurl(data.firstitemid) + '" />');
                } else if (data.firstitemtype == 2) {
                    that_dom.find('.itembox').html('<img src="' + ximg(staticUrl + 'img/pop/talent_item' + data.firstitemid + '.png') + '" />');
                }
            } else {
                if (data.itemid > 0) { //获得了道具

                    _tmpl = '<div class="get1">' + lang('hero_get_exp') + '<p>+' + data.exp + '</p></div>';
                    _tmpl += '<div class="getitem"><p>' + lang('get') + '：<span style="color:' + helper.getItemColor(data.quality) + '">' + data.itemname + '</span></p><div class="itembox"></div></div>';
                    that_dom.find('#battleend_main').html(_tmpl);
                    that_dom.find('.itembox').addClass('c_i_bj' + data.quality);
                    if (data.itemtype == 1) {
                        that_dom.find('.itembox').html('<img src="' + xGame.itemurl(data.itemid) + '" />');
                    } else if (data.itemtype == 2) {
                        that_dom.find('.itembox').html('<img src="' + ximg(staticUrl + 'img/pop/talent_item' + data.itemid + '.png') + '" />');
                    }

                } else {
                    _tmpl = '<div class="get1">' + lang('hero_get_exp') + '<p>+' + (data.win == 1 ? data.exp: 0) + '</p></div>';
                    that_dom.find('#battleend_main').html(_tmpl);
                }
            }

            //胜负
            var flagclass = data.win == 1 ? 'flagwin3': (data.win == 2 ? 'flaglose3': 'flaglose0');
            that_dom.find('.flag').addClass(flagclass);
            if (data.win == 2) {
                that_dom.find('.flagbg').addClass('lose');
            } else if (data.win == 3) {
                that_dom.find('.flagbg').addClass('equal');
            }

            var doneRender = function() {

                miscpop.hide('battleendpop');
                that.destoryAllBombFlower(); //清理烟花

                if (data.levelup && data.levelup == 1) { //升级
                    xGame.xsSet(player.info.uid + 'levelup', 1, true);
                }

                //完成
                elitebattleWorld.back();
            };

            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);

            //that_dom.find('.replay_btn').addClass('disable');
            that_dom.find('.replay_btn').unbind('touchend').one('touchend', function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                elitebattleWorld.refight();
            });

            //暂时不支持分享
            that_dom.find('.share_btn').addClass('disable');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.win == 3) {
                that.createBombFlower();
            }

        },

        /* 进入战斗 */

        //var res = {"info":{"reward_status":0,"reward":[],"total_rank":"1","rank":"9","win_times":0,"times":12,"gender":"2","list":{"1":{"id":"60","uid":"3626","username":"\u9f99\u50b2\u8299","rank":"4","status":0,"gender":"1","level":"135"},"2":{"id":"19","uid":"520","username":"\u6c34\u96c1","rank":"5","status":0,"gender":"1","level":"117"},"3":{"id":"32","uid":"366","username":"\u7eff\u5982\u971c","rank":"6","status":0,"gender":"2","level":"105"},"4":{"id":"43","uid":"195","username":"\u7a0b\u5982\u66fc","rank":"7","status":0,"gender":"2","level":"92"},"5":{"id":"2","uid":"1609","username":"\u5fc6\u90aa","rank":"8","status":0,"gender":"1","level":"93"}},"cd":0,"win":-3,"top_list":{"1":{"uid":"3628","username":"\u9189\u68a6\u5bd2"},"2":{"uid":"3551","username":"\u5143\u6e05"},"3":{"uid":"328","username":"\u75f4\u9759\u8299"},"4":{"uid":"3626","username":"\u9f99\u50b2\u8299"},"5":{"uid":"520","username":"\u6c34\u96c1"}},"report":6210},"status":1,"battle":{"leftteam":{"2":{"heroid":"1658","name":"\u8bb8\u891a","herotype":"1","level":"40","allblood":4902,"power":0,"image":"3","talent":"\u864e\u75f4","skill":"\u7389\u77f3\u4ff1\u711a"},"4":{"heroid":"1876","name":"\u7530\u4e30","herotype":"2","level":"3","allblood":242,"power":0,"image":"17","talent":"\u521a\u8c0f","skill":"\u6444\u9b42\u7434\u97f3"},"6":{"heroid":"2037","name":"\u5173\u7fbd","herotype":"1","level":"61","allblood":9441,"power":0,"image":"58","talent":"\u6b66\u5723","skill":"\u6625\u79cb\u5341\u5b57\u65a9"},"7":{"heroid":"1661","name":"\u53f8\u9a6c\u61ff","herotype":"2","level":"72","allblood":8701,"power":0,"image":"16","talent":"\u67ad\u81e3","skill":"\u516d\u9053\u8f6e\u56de"}},"rightteam":{"2":{"heroid":"2144","name":"\u5173\u7fbd","herotype":"1","level":"81","allblood":24085,"power":"10","image":"58","talent":"\u6b66\u5723","skill":"\u6625\u79cb\u5341\u5b57\u65a9"},"4":{"heroid":"2132","name":"\u674e\u5e7f","herotype":"1","level":"81","allblood":15893,"power":"10","image":"13","talent":"\u7bad\u795e","skill":"\u5929\u795e\u5c04\u65e5"},"6":{"heroid":"1931","name":"\u5468\u4ed3","herotype":"1","level":"80","allblood":16081,"power":0,"image":"4","talent":"\u608d\u52c7","skill":"\u6124\u6012\u4e00\u51fb"},"7":{"heroid":"2173","name":"\u90ed\u5609","herotype":"2","level":"81","allblood":14773,"power":0,"image":"50","talent":"\u5947\u8c0b","skill":"\u98d3\u98ce\u7ede\u6740"},"9":{"heroid":"2138","name":"\u8d75\u4e91","herotype":"1","level":"81","allblood":21818,"power":0,"image":"25","talent":"\u519b\u795e","skill":"\u76d8\u9f99\u4e5d\u53d8"}},"process":[{"round":1,"launch":"left","attackor":2,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":1,"power":35,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":2,"attackpower":60,"skill":0,"hit":0,"attacktype":0,"beattackors":[2],"hurt":[{"pos":2,"blood":4902,"power":0,"self":0,"faint":0}]},{"round":1,"launch":"left","attackor":4,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":7,"power":35,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":4,"attackpower":60,"skill":0,"hit":1,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":242,"power":0,"self":0,"faint":0}]},{"round":1,"launch":"left","attackor":6,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":10,"power":25,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":6,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":1307,"power":50,"self":0,"faint":0}]},{"round":1,"launch":"left","attackor":7,"attackpower":25,"skill":0,"hit":1,"attacktype":0,"beattackors":[4],"hurt":[{"pos":4,"blood":6,"power":85,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":7,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":7052,"power":50,"self":0,"faint":0}]},{"round":2,"launch":"right","attackor":9,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":5088,"power":75,"self":0,"faint":0}]},{"round":2,"launch":"left","attackor":6,"attackpower":100,"skill":0,"hit":1,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":7,"power":75,"self":0,"faint":0}]},{"round":2,"launch":"right","attackor":2,"attackpower":85,"skill":0,"hit":1,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":1649,"power":0,"self":0,"faint":0}]},{"round":2,"launch":"right","attackor":4,"attackpower":110,"skill":0,"hit":1,"attacktype":0,"beattackors":[6],"hurt":[{"pos":6,"blood":3046,"power":0,"self":0,"faint":0}]}],"assess":-3,"leftresult":[],"rightresult":{"2":{"heroid":"2144","name":"\u5173\u7fbd","herotype":"1","level":"81","allblood":24085,"power":85,"image":"58","talent":"\u6b66\u5723","skill":"\u6625\u79cb\u5341\u5b57\u65a9","blood":24084},"4":{"heroid":"2132","name":"\u674e\u5e7f","herotype":"1","level":"81","allblood":15893,"power":110,"image":"13","talent":"\u7bad\u795e","skill":"\u5929\u795e\u5c04\u65e5","blood":15880},"6":{"heroid":"1931","name":"\u5468\u4ed3","herotype":"1","level":"80","allblood":16081,"power":75,"image":"4","talent":"\u608d\u52c7","skill":"\u6124\u6012\u4e00\u51fb","blood":16064},"7":{"heroid":"2173","name":"\u90ed\u5609","herotype":"2","level":"81","allblood":14773,"power":25,"image":"50","talent":"\u5947\u8c0b","skill":"\u98d3\u98ce\u7ede\u6740","blood":14773},"9":{"heroid":"2138","name":"\u8d75\u4e91","herotype":"1","level":"81","allblood":21818,"power":25,"image":"25","talent":"\u519b\u795e","skill":"\u76d8\u9f99\u4e5d\u53d8","blood":21818}},"left_role":{"name":"\u8f9b\u79cb","level":"100","gender":"1"},"right_role":{"name":"\u7a0b\u5982\u66fc","level":"92","gender":"2"}}};
        enterBattleFromWorldarena: function(res, touid) {

            /* 开始进入战斗界面 */
            worldarenapop.e.hide();

            $('#worldloading').addClass('black').show();

            var _old_world = xGame.nowWorld;
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromWorldarena(res, _old_world, touid);
                },
                'worldarena');
            },
            500);
        },

        battleResultFromWorldarena: function(data, oldworld, touid) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips"></p>');
            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
                if (parseInt(data.torank) == 0) {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg3'));
                } else if(parseInt(data.torank) == 1) {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg2', [data.torank]));
                } else {
                    that_dom.find('#battleend_main').children('p').html(lang('you_win_msg1', [data.torank]));
                }
                if(data.rank != '') {
                    data.rank['list'] = data.list;
                    worldarenapop.fillPlayerList(data.rank);
                }
                if(data.top != '') {
                    worldarenapop.fillAllTop3(data.top);
                }
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
                that_dom.find('#battleend_main').children('p').html(lang('you_lose_msg'));
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
                that_dom.find('#battleend_main').children('p').html(lang('you_lose_msg'));
            }
            that_dom.find('.flag').addClass(flagclass);

            //更新界面信息

            var doneRender = function() {
                miscpop.hide('battleendpop');
                worldarenapop.e.show();
                xGame.loadWorld(oldworld);
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'worldarena',
                        m: 'send_to_chat',
                        reportid: data.info.report
                    });
                    $(this).addClass('disable').unbind('touchend');
                });

                /* 社交分享啊啊啊 */
                that.socialShareDisplay(data.share, 'worldarena');

            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 英雄王座进入战斗 */
        enterBattleFromThrone: function(res, bg) {

            $('#worldloading').addClass('black').show();

            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    //如果有奖励，就掉宝箱
                    if (res.info.win > 0 && res.info.reward.length > 0) {
                        //如果含有道具，调用宝箱方法
                        if (miscpop.includeItem(res.info.reward)) {
                            miscpop.showRewardbox(res, 'throne');
                        } else {
                            miscpop.battleResultFromThrone(res, res.info.reward);
                        }
                    } else {
                        //没有奖励，只显示提示语
                        miscpop.battleResultFromThrone(res);
                    }
                },
                bg);
            },
            500);

        },

        //判断是否含有道具，有返回true,没有返回false
        includeItem: function (list) {
            if (list.length == 0) {
                return false;
            } else {
                for (var i = 0; i < list.length; i ++) {
                    //大于2代表是道具
                    if (parseInt(list[i].gettype) > 2) {
                        return true;
                    }
                }
                return false;
            }
        },

        //英雄王座结束方法，最后一个参数是只有道具的时候才会返回
        battleResultFromThrone: function(data, reward, hasItem) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');

            /* 胜负图标 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }

            //中间内容
            if (reward == undefined) {
                that_dom.find('#battleend_main').attr('class', 'main type4').html('<p class="tips">' + lang('every_10_get_reward') + '</p>');
            } else {
                var tpl = '';
                //有道具时
                if (hasItem) {
                    var small = '<div class="box" style="margin-top:10px;">';
                    var hasEquip = false;
                    var horseNum = 0;

                    for (var i = 0, len = reward.length; i < len; i++) {
                        if (reward[i].gettype == 3 || reward[i].gettype == 4 || reward[i].gettype == 5 || reward[i].gettype == 6) {
                            hasEquip = true;
                        }

                        if (reward[i].gettype == 14) {
                            horseNum++;
                        }
                    }

                    if (horseNum > 1) {
                        hasEquip = true;
                    }

                    //console.log('有奖励，有道具');
                    for (var i = 0;i < reward.length; i ++) {
                            if (reward[i].gettype == 1) {
                                small += '<em class="wuzi silver">' + lang('silver') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 2) {
                                small += '<em class="wuzi jade">' + lang('jade') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 5 || reward[i].gettype == 6) {
                                tpl += '<div class="box isitem">';
                                tpl += '<p>' + lang('get') + ':<em style="color:' + helper.getItemColor(reward[i].getvalue.quality) + '">' + reward[i].getvalue.itemname + ' x 1</em></p>';
                                tpl += '<div class="itembox c_i_bj' + reward[i].getvalue.quality + '" ><span style="background-image:url(' + staticUrl + 'img/pop/talent_item' + reward[i].getvalue.itemtype + '.png);"></span><b>x 1</b></div>';
                                tpl += '</div>';
                            } else if (reward[i].gettype == 7) {
                                small += '<em class="wuzi reputation">' + lang('reputation') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 8) {
                                small += '<em class="wuzi original_soul">' + lang('original_soul') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 9) {
                                small += '<em class="wuzi tfcard">' + lang('tfcard') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 10) {
                                small += '<em class="wuzi purple_jade">' + lang('purple_jade') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 11) {
                                small += '<em class="wuzi refinestone">' + lang('refinestone') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 12) {
                                small += '<em class="wuzi colorstone">' + lang('colorstone') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 13) {
                                small += '<em class="wuzi bloodstone">' + lang('bloodstone') + ':' + reward[i].getvalue + '</em>';
                            } else if (reward[i].gettype == 14) {

                                if (hasEquip == false) {
                                    tpl += '<div class="box isitem">';
                                    tpl += '<p>' + lang('get') + ':<em style="color:' + helper.getItemColor(reward[i].getvalue.quality) + '">' + reward[i].getvalue.itemname + ' x '+ reward[i].getnum +'</em></p>';
                                    tpl += '<div class="itembox c_i_bj' + reward[i].getvalue.quality + '" ><span class="horse" style="background-position:' + helper.getHorsePostion('small', reward[i].getvalue.id) + ';"></span><b>x '+ reward[i].getnum +'</b></div>';
                                    tpl += '</div>';
                                } else {
                                    small += '<em class="wuzi mount" style="color:' + helper.getItemColor(reward[i].getvalue.quality) + ';"><b class="horse" style="background-position:'+ helper.getHorsePostion('buff', reward[i].getvalue.id) +';"></b>' + reward[i].getvalue.itemname + ':'+ reward[i].getnum +'</em>';
                                }
                            } else {
                                tpl += '<div class="box isitem">';
                                tpl += '<p>' + lang('get') + ':<em style="color:' + helper.getItemColor(reward[i].getvalue.quality) + '">' + reward[i].getvalue.itemname + ' x 1</em></p>';
                                tpl += '<div class="itembox c_i_bj' + reward[i].getvalue.quality + '" ><span style="background-image:url(' + xGame.itemurl(reward[i].getvalue.itemid) + ');"></span><b>x 1</b></div>';
                                tpl += '</div>';
                            }
                    }
                    small += '</div>';

                    tpl = small + tpl;

                } else {
                    for (var i = 0;i < reward.length; i ++) {
                        tpl += '<div class="box">';
                        if (reward[i].gettype == 1) {
                            tpl += '<em class="wuzi silver" style="margin-top:50px;">' + lang('silver') + ':' + reward[i].getvalue + '</em>';
                        } else if (reward[i].gettype == 2) {
                            tpl += '<em class="wuzi jade" style="margin-top:50px;">' + lang('jade') + ':' + reward[i].getvalue + '</em>';
                        }
                        tpl += '</div>';
                    }

                }
                that_dom.find('#battleend_main').attr('class', 'main type7').html(tpl);

            }

            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                //0为index接口，1为start接口
                if (data.info.view == 0) {
                    xGame.loadWorld('herothrone');
                } else if (data.info.view == 1) {
                    xGame.loadWorld('herothrone', true);
                }

            };

            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            that_dom.find('.share_btn').addClass('disable');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 创建支付宝订单 */
        alipayOrder: function(productid) {
            var render = function(res) {
                if(res.status == 1) {
                    var payString = res.orderinfo + '&sign="' + res.sign + '"&sign_type="RSA"';
                    window.plugins.AlipayManagerPlugin.fetchPay(
                    function() {//支付成功
                        console.log('支付宝-支付成功');
                        xGame.toast.show(lang('pay_success_msg'));
                        player.reloadInfo();
                    },
                    function() {//支付失败
                        console.log('支付宝-支付失败');
                        xGame.confirm.show(lang('pay_fail_msg'), lang('pay_fail_msg4'), 1,
                        function() {
                            xGame.confirm.hide();
                        });
                    },
                    [payString]);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_pay_info',
                'id': productid
            },
            render); //加载数据
        },

        /* 创建91订单 */
        go91Order: function(productid) {
            var render = function(res) {
                if(res.status == 1) {
                    var successfn = function() {
                        console.log('91-支付成功');
                        xGame.toast.show(lang('pay_success_msg'));
                        setTimeout(function() {
                            player.reloadInfo();
                        }, 1500);
                    };
                    var failfn = function() {
                        console.log('91-支付失败');
                        xGame.confirm.show(lang('pay_fail_msg'), lang('pay_fail_msg4'), 1,
                        function() {
                            xGame.confirm.hide();
                        });
                    };
                    window.plugins.NdComPlatformPlugin.purchaseGold(successfn, failfn, [{
                       orderSerial: res.orderid,
                       productId: "com.zctech.sanguo91sdk",
                       productName: res.productname,//"60元宝",
                       productPrice: res.price,//1.0,
                       productOrignalPrice: res.price,//1.0,
                       payDescription: xGame.server_prefix.substring(1),
                       productCount: 1
                    }]);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'create_91_order',
                'id': productid
            },
            render); //加载数据
        },

        /* 创建PP订单 */
        goPPOrder: function(productid) {
            var render = function(res) {
                if(res.status == 1) {
                    var successfn = function() {
                        console.log('PP-支付成功');
                        xGame.toast.show(lang('pay_success_msg'));
                        setTimeout(function() {
                            player.reloadInfo();
                        }, 1500);
                    };
                    var failfn = function() {
                        console.log('PP-支付失败');
                        xGame.confirm.show(lang('pay_fail_msg'), lang('pay_fail_msg4'), 1,
                        function() {
                            xGame.confirm.hide();
                        });
                    };
                    window.plugins.PPComPlatformPlugin.purchaseGold(successfn, failfn, [{
                       orderSerial: res.orderid,
                       productName: res.productname,//"60元宝",
                       productPrice: res.price,//1.0,
                       serverPrefix: xGame.server_prefix
                    }]);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'create_pp_order',
                'id': productid
            },
            render); //加载数据
        },

        /* 创建KY订单 */
        goKYOrder: function(productid) {
            var render = function(res) {
                if(res.status == 1) {
                    var successfn = function() {
                        console.log('KY-支付成功');
                        xGame.toast.show(lang('pay_success_msg'));
                        setTimeout(function() {
                            player.reloadInfo();
                        }, 1500);
                    };
                    var failfn = function() {
                        console.log('KY-支付失败');
                        xGame.confirm.show(lang('pay_fail_msg'), lang('pay_fail_msg4'), 1,
                        function() {
                            xGame.confirm.hide();
                        });
                    };
                    window.plugins.KuaiyongPlatform.purchaseGold(successfn, failfn, [{
                       orderSerial: res.orderid,
                       productName: res.productname,//"60元宝",
                       productPrice: res.price,//1.0,
                       uid: xGame.server_prefix + '|' + player.info.uid,                                                                                     
                    }]);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'create_kuaiyong_order',
                'id': productid
            },
            render); //加载数据
        },

        /* 创建DJ订单 */
        goDJOrder: function(productid) {
            var render = function(res) {
                if(res.status == 1) {

                    window.plugins.DjPlatformPlugin.purchaseGold(function(){}, function(){}, [{
                        amount: parseInt(res.price) * 10,//这里是金币数
                        orderid: res.orderid,
                        extra: xGame.server_prefix.substring(1),
                    }]);

                } else {
                    xGame.toast.show(lang('no_pay_info'));
                }
            };
            loadContents({
                'c': 'paydj',
                'm': 'create_djpay_order',
                'id': productid
            },
            render); //加载数据
        },

        /* 创建91兼容版订单 */
        goDJNOrder: function(productid) {
            var render = function(res) {
                if(res.status == 1) {

                    window.plugins.NdComPlatformPlugin.purchaseGold(null, null, [{
                       orderSerial: res.orderid,
                       productId: "com.zhancheng.sanguo.dj",
                       productName: res.productname,//"60元宝",
                       productPrice: res.price,//1.0,
                       productOrignalPrice: res.price,//1.0,
                       payDescription: xGame.server_prefix.substring(1),
                       productCount: 1
                    }]);

                } else {
                    xGame.toast.show(lang('no_pay_info'));
                }
            };
            loadContents({
                'c': 'paydjn',
                'm': 'create_djpay_order',
                'id': productid
            },
            render); //加载数据
        },

        /* 选择越狱支付渠道 */
        otherPay: function() {

            var that_dom = xGame.loadPopTmpl('otherpay');

            //总关闭按钮
            that_dom.find('#pay_channel_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.hide('otherpaypop');
                e.preventDefault();
                e.stopPropagation();
            });
            //支付宝按钮
            that_dom.find('#alipay_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.hide('otherpaypop');
                xGame.showpop('iospay');
                e.preventDefault();
                e.stopPropagation();
            });
            //神州付按钮
            that_dom.find('#card_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.szfpay();
                e.preventDefault();
                e.stopPropagation();
            });
            that_dom.show();
            $('#worldloading').hide();
        },

        szfpay: function() {
            var that_dom = $('#otherpaypop');
            that_dom.find('#otherpaypop_wrap').addClass('card');

            //选择充值卡类型
            that_dom.find('#szf_type').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that_dom.find('#szf_type_select_list').toggle();
                e.preventDefault();
                e.stopPropagation();
            });
            //选择充值卡类型列表
            that_dom.find('#szf_type_select_list').undelegate('li', 'touchend').delegate('li', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var szf_type = $(this).attr('type');
                var szf_card = $(this).html();

                var szf_first = miscpop.SZF_cardlist[szf_type][0];//在顶部定义的
                that_dom.find('#szf_amount').attr('amount', szf_first).html(szf_first + '元');
                that_dom.find('#szf_type').attr('type', szf_type).html(szf_card);//修改DOM
                that_dom.find('#szf_type_select_list').hide();

                e.preventDefault();
                e.stopPropagation();
            });
            //充值卡金额
            that_dom.find('#szf_amount').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var szf_type = that_dom.find('#szf_type').attr('type');
                if(szf_type && szf_type != '') {//弹出选择列表框
                    miscpop.szfselectlist(szf_type);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            //神州付关闭按钮
            that_dom.find('#card_pay_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that_dom.find('#otherpaypop_wrap').removeClass('card');
                that_dom.find('#szf_id').val('');
                that_dom.find('#szf_pw').val('');
                that_dom.find('#szf_amount').attr('amount', '0');
                miscpop.hide('otherpaypop');
                e.preventDefault();
                e.stopPropagation();
            });
            //神州付确定按钮
            that_dom.find('#card_ok_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var szf_id = that_dom.find('#szf_id').val();
                var szf_pw = that_dom.find('#szf_pw').val();
                var szf_amount = that_dom.find('#szf_amount').attr('amount');
                if(szf_id.length <=0 || szf_pw.length <=0 || szf_amount <= 0) {
                    xGame.toast.show('请将充值卡类型、卡密信息输入完整！');
                    return false;
                }
                var render = function(data) {
                    if(data && data.status == 1) {//充值成功
                        xGame.toast.show(data.message);
                        setTimeout(function() {
                            player.reloadInfo();
                        }, 3000);
                        miscpop.hide('otherpaypop');
                        return false;
                    } else {
                        xGame.toast.show('充值失败，请检查充值卡密是否输入正确！');
                        return false;
                    }
                };
                 loadContents({
                    'c': 'pay',
                    'm': 'szf_order',
                    'id': szf_id,
                    'pw': szf_pw,
                    'amount': szf_amount,
                    'paychannel': 'ios'
                },
                render);
                e.preventDefault();
                e.stopPropagation();
            });
        },

        /* 选择要充值的面额 */
        szfselectlist: function(cardtype) {

            var cardlist = this.SZF_cardlist[cardtype];
            if(cardlist && cardlist.length > 0) {
                var that_dom = xGame.loadPopTmpl('szfselect');
                var html = '';
                for(var i = 0; i < cardlist.length; i++) {
                    html += '<a amount="' + cardlist[i] + '">' + cardlist[i] + '元</a>';
                }
                that_dom.find('#szfselectpop_wrap').html(html);
                that_dom.undelegate('a', 'touchend').delegate('a', 'touchend', function(e) {
                    var _amount = $(this).attr('amount');
                    setTimeout(function() {
                        $('#szf_amount').attr('amount', _amount).html(_amount + '元');
                        miscpop.hide('szfselectpop');
                    }, 300);
                    e.preventDefault();
                    e.stopPropagation();
                });
            }

        },

        /* 金银洞进入战报 */
        viewBattleDiy: function(data) {

            /* 开始进入战斗界面 */
            $('#worldloading').addClass('black').show();

            var res = {
                battle: data,
                status: 1,
                info:{
                    background: "1",
                    cd: 82,
                    exp: 0,
                    generalinfo: [],
                    generalstatus: 0,
                    get_level: 0,
                    gettype: 0,
                    getvalue: 0,
                    gexp: 0,
                    image: "42",
                    itemid: 0,
                    itemname: "",
                    levelup: 0,
                    levelupexp: 0,
                    name: "河北名士",
                    openstatus: 0,
                    report: 0,
                    type: "1",
                    win: 1
                }
            };

            setTimeout(function() {
                xGame.loadBattle(res, null, res.info.background,
                function() {
                    xGame.loadWorld('city');
                });
            },
            500);

        },

        /* 加好友*/
        add_friend: function(obj, uid) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    xGame.toast.show(lang('friends_add_sucess'));
                    obj.unbind().attr('class', 'is_friend');
                } else if (res.status == -1) {
                    xGame.toast.show(lang('friends_full'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('friends_isfriend'));
                } else if (res.status == -3) {
                    xGame.toast.show(lang('friends_add_fail'));
                } else if (res.status == -4) {
                    xGame.toast.show(lang('friends_canshu_error'));
                } else if (res.status == -5) {
                    xGame.toast.show(lang('friends_can_not_add_yourself'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'friend',
                'm': 'add_friend',
                'f': uid
            },
            render);

        },

        in_array : function(needle, arraydata) {
            if(arraydata.length > 0) {
                for(var i = 0; i < arraydata.length; i++) {
                    if(arraydata[i] == needle) {
                        return true;
                    }
                }
            }
            return false;
        },

        getTimestamp : function(str) {
            var d = str.match(/\d+/g);
            return +new Date(d[0], d[1] - 1, d[2], d[3], d[4], d[5]);
        },

        //战神殿战斗结果
        battleResultFromChampions: function(data, id, stage) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');

            var winner = '';
            var loser = '';
            data.info.win = parseInt(data.info.win);
            winner = data.info.win > 0 ? data.battle.left_role.name : data.battle.right_role.name;
            loser = data.info.win > 0 ? data.battle.right_role.name : data.battle.left_role.name;
            var msg = '';
            
            if (data.battle.up == 1) {
                that_dom.addClass('championsworld_up');
                msg = '<p class="tips">' + lang('champions_vs',[winner, loser]) + '</p>';

            } else if (data.battle.up == 2){
                that_dom.addClass('championsworld_king');
                msg = '<p class="tips">' + winner + '</p><span class="king_flag"></span>';
                setTimeout(function() {
                    $('div#battleendpop').find('span.king_flag').css('display','block');
                }, 200);
            }

            that_dom.find('#battleend_main').attr('class', 'main type4').html(msg);
            /* 填充界面 */

            msg = null;
            winner = null;
            loser = null;
            //更新界面信息

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld('champions');
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                battleWorld.refight();
            });
            //that_dom.find('.share_btn').addClass('disable');

            that_dom.find('.share_btn').unbind('touchend').bind('touchend',
            function() {
                miscpop.shareBattle({
                    'c': 'god',
                    'm': 'share_report',
                    'id': id,
                    'stage': stage,
                });
                $(this).addClass('disable').unbind('touchend');
            });
            /* social */
            that.socialShareDisplay(data.share, 'champions');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

        },
        
        //群服争霸战斗结果
        battleResultFromGvg: function(winner, islast, sharedata) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');

            var msg = '';
            
            that_dom.addClass('championsworld_up');
            if (islast) {
                that_dom.addClass('gvg');
                msg = '<p class="tips">' + lang('gvg_world_last_win',[winner]) + '</p>';
            } else {
                msg = '<p class="tips">' + lang('gvg_world_result_msg',[winner]) + '</p>';
            }
            that_dom.find('#battleend_main').attr('class', 'main type4').html(msg);
            /* 填充界面 */

            msg = null;
            //更新界面信息

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld('gvg');
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                gvgbattleWorld.refight();
            });
            that_dom.find('.share_btn').addClass('disable');
            /* 社交分享啊啊啊 */
            that.socialShareDisplay(sharedata, 'gvg');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

        },
        
        //武斗会战斗结果
        battleResultFromWar: function(data, id, stage) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');

            var winner = '';
            var loser = '';
            data.info.win = parseInt(data.info.win);
            winner = data.info.win > 0 ? data.battle.left_role.name : data.battle.right_role.name;
            loser = data.info.win > 0 ? data.battle.right_role.name : data.battle.left_role.name;
            var msg = '';
            
            if (data.battle.up == 1) {
                that_dom.addClass('warworld_up');
                msg = '<p class="tips">' + lang('war_vs',[winner, loser, data.info.integral]) + '</p>';

            } else if (data.battle.up == 2){
                that_dom.addClass('warworld_king');
                msg = '<p class="tips">' + winner + '</p><span class="king_flag"></span>';
                setTimeout(function() {
                    $('div#battleendpop').find('span.king_flag').css('display','block');
                }, 200);
            }

            that_dom.find('#battleend_main').attr('class', 'main type4').html(msg);
            /* 填充界面 */

            msg = null;
            winner = null;
            loser = null;
            //更新界面信息

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld('war');
            }
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                battleWorld.refight();
            });
            //that_dom.find('.share_btn').addClass('disable');

            that_dom.find('.share_btn').unbind('touchend').bind('touchend',
            function() {
                miscpop.shareBattle({
                    'c': 'war',
                    'm': 'share_report',
                    'id': id,
                    'stage': stage,
                });
                $(this).addClass('disable').unbind('touchend');
            });
            /* social */
            //that.socialShareDisplay(data.share, 'war');

            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

        },

        /* 四象圣域查看攻略 */
        viewBattleFromSanctum: function(id) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();
                    
                    //是攻略，stage可以从本地拿
                    var nowbeastid = 0; //用来返回到当前的星域
                    var nowstage = 0;
                    var nowData = {};
                    if (sanctumWorld && sanctumWorld.beastid && sanctumWorld.stage && sanctumWorld.currentData) {
                        nowbeastid = sanctumWorld.beastid;
                        nowstage = sanctumWorld.stage;
                        nowData = sanctumWorld.currentData;
                    }
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'sanctum' + nowbeastid,
                        function() {
                            xGame.loadWorld('sanctum', {
                                beastid: nowbeastid,
                                stage: nowstage,
                                currentData: nowData
                            });
                            strategypop.e.show();
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };
            loadContents({
                'c': 'sanctum',
                'm': 'get_report_info',
                'id': id
            },
            render);

        },
        
        /* 进入战斗 */
        enterBattleFromSanctum: function(res) {

            /* 开始进入战斗界面 */
            $('#worldloading').addClass('black').show();
            
            //是战斗，stage必须后端返回
            var nowbeastid = 0; //用来返回到当前的星域
            if (sanctumWorld && sanctumWorld.beastid && sanctumWorld.stage) {
                nowbeastid = sanctumWorld.beastid;
            }
            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromSanctum(res,{
                        beastid: nowbeastid,
                    });
                },
                'sanctum' + nowbeastid);
            },
            500);

        },

        //平乱攻略
        viewBattleFromPacify: function(reportid) {
            if (xGame.checkLock()) {
                return false;
            }
            var  oldworld  = xGame.nowWorld;

            var render = function(res) {
                 xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    strategypop.e.hide();
                    
                    var nowbeastid = 0; //用来返回到当前的星域
            
                    if (pacifypop && pacifypop.nowBeastId && pacifypop.nowDifficulty && pacifypop.nowMilitary ) {
                         nowbeastid = pacifypop.nowMilitary;
                     }
                   
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'pacify' + nowbeastid,
                        function() {
                            xGame.loadWorld(oldworld);
                            strategypop.e.show();
                        });    
                    },
                    500);

                } else {
                    xGame.toast.show(lang('can_not_view_current_report'));
                }
            };

            loadContents({
                'c': 'copies',
                'm': 'get_report_info',
                'd': 'newequip',
                'id': reportid
            },
            render);
        },

         battleResultFromSanctum: function(data, returndata) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type5');
            var _tmpl = '<div class="get1">' + lang('sanctumworld_get_gemp') + '<p class="gem_part"></p></div>';
            data.info.gem = parseInt(data.info.gem);
            if (data.info.gem > 0) {
                _tmpl += '<div class="getitem"><p>' + lang('sanctumworld_get_gem') + '<span></span></p><div class="itembox"></div></div>';
            }
            that_dom.find('#battleend_main').html(_tmpl);

            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }
            that_dom.find('.flag').addClass(flagclass);
            
            //是否首杀
            if (parseInt(data.info.firstkill) == 1) {
                that_dom.find('#battleend_main').find('.get1').children('p').html('+' + data.info.first_chip_num);
            } else {
                that_dom.find('#battleend_main').find('.get1').children('p').html('+' + data.info.chip);
            }

            //宝石
            if (data.info.gem > 0) {
                that_dom.find('#battleend_main').find('.getitem').children('p').children('span').css('color', helper.getItemColor(data.info.gem_quality)).html(data.info.gem_name);
                that_dom.find('.itembox').addClass('c_i_bj' + data.info.gem_quality).html('<img class="gem" src="'+xGame.gem(data.info.gem_type,data.info.gem_quality)+'" />');
            }

            var doneRender = function() {
                miscpop.hide('battleendpop');
                returndata.stage = data.info.stage;
                if (data.info.complete == 1) {
                    returndata = {
                        clear: true
                    };
                }
                xGame.loadWorld('sanctum', returndata);

            };
            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', doneRender);
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'sanctum',
                        m: 'send_to_chat',
                        id: returndata.beastid,
                        report: data.info.report,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

         //平乱：进入战斗
        enterBattleFromPacify:function(res){
            var oldworld  = 'city';
             /* 开始进入战斗界面 */
            $('#worldloading').addClass('black').show();
            var nowbeastid = 0;

            if (pacifypop && pacifypop.nowBeastId && pacifypop.nowDifficulty && pacifypop.nowMilitary ) {
                nowbeastid = pacifypop.nowMilitary;
            }

            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    miscpop.battleResultFromPacify(res,{
                        beastid: nowbeastid,
                    },oldworld);
                },
                'pacify' + nowbeastid);
            },
            500);
        },

        battleResultFromPacify: function(data, returndata , oldworld){
            var that = this;

            that.clearflower = false;
            
            var that_dom = xGame.loadPopTmpl('battleend');
            var rewards  = data.reward_info.reward;
            var firstreward = data.reward_info.first_reward;
            that_dom.find('#battleend_main').attr('class', 'main type5');
            var _tmpl = '<div class="get1">' + lang('pacify_get_reputation') + '<p class="reputation_part"></p></div>';
            
            if (rewards) {
                var len = rewards.length;
        
                if (len && !firstreward) {

                    if (rewards.length == 1) {

                        if (rewards[0].type == 3) {
                             _tmpl += '<div class="getitem">' + lang('eggpop_got') + '<span style="color:'+helper.getItemColor(rewards[0].quality)+'" >'+rewards[0].name+'x'+rewards[0].value+'</span><span class="pacify_reward"><em style="background-image:url(' + xGame.forgeMaterial(rewards[0].materials_id) + ')"></em></span></div>';
                        } else if (rewards[0].type ==2  || rewards[0].type == 1) {
                            _tmpl += '<div class="getitem">' + lang('eggpop_got') + '<span style="color:'+helper.getItemColor(rewards[0].quality)+'" >'+rewards[0].name+'x'+rewards[0].value+'</span><span class="pacify_reward"><em style="background-image:url(' + xGame.itemurl(rewards[0].id) + ')"></em></span></div>';
                        }
                    } else if (rewards.length > 1) {

                        _tmpl += '<div class="getitem">' + lang('eggpop_got') + '<ul class="pacify_reward_list">';

                        for (var i = 0; i < len; i++) {
                            var reward = rewards[i];
                            var type = reward.type;

                            if (type == 3) {
                                 _tmpl += '<li class="pacify_reward_item" style="background-image:url(' + xGame.forgeMaterial(reward.materials_id) + ')"><span style="color:'+helper.getItemColor(reward.quality)+'">'+reward.name+'x'+reward.value+'</span></li>';
                            } else if(type == 1 || type == 2){
                                 _tmpl += '<li class="pacify_reward_item" style="background-image:url(' + xGame.itemurl(reward.id) + ')"><span style="color:'+helper.getItemColor(reward.quality)+'">'+reward.name+'x'+reward.value+'</span></li>';
                            }     
                    };

                    _tmpl +='</ul>';
                    }
                } else if (len && firstreward){
                    _tmpl += '<div class="getitem">' + lang('eggpop_got') + '<ul class="pacify_reward_list">';
                    
                    if (firstreward.type == 3) {
                         _tmpl += '<li class="pacify_reward_item" style="background-image:url(' + xGame.forgeMaterial(firstreward.materials_id) + ')"><span style="color:'+helper.getItemColor(firstreward.quality)+'">'+firstreward.name+'x'+firstreward.value+'</span></li>';
                    } else if (firstreward.type == 1 || firstreward.type == 2){
                         _tmpl += '<li class="pacify_reward_item" style="background-image:url(' + xGame.itemurl(firstreward.id) + ')"><span style="color:'+helper.getItemColor(firstreward.quality)+'">'+firstreward.name+'x'+firstreward.value+'</span></li>';
                    } 

                    for (var i = 0; i < len; i++) {
                        var reward = rewards[i];
                        var type = reward.type;
                        
                        if (type == 3) {
                             _tmpl += '<li class="pacify_reward_item" style="background-image:url(' + xGame.forgeMaterial(reward.materials_id) + ')"><span style="color:'+helper.getItemColor(reward.quality)+'">'+reward.name+'x'+reward.value+'</span></li>';
                        } else if (type == 1 || type == 2){
                             _tmpl += '<li class="pacify_reward_item" style="background-image:url(' + xGame.itemurl(reward.id) + ')"><span style="color:'+helper.getItemColor(reward.quality)+'">'+reward.name+'x'+reward.value+'</span></li>';
                        }   
                    }
                     _tmpl +='</ul>';  
                } else if (!len && firstreward) {

                    if (firstreward.type == 3) {
                         _tmpl += '<div class="getitem">' + lang('eggpop_got') + '<span style="color:'+helper.getItemColor(firstreward.quality)+'" >'+firstreward.name+'x'+firstreward.value+'</span><span class="pacify_reward"><em style="background-image:url(' + xGame.forgeMaterial(firstreward.id) + ')"></em></span></div>';
                    } else if(firstreward.type == 1 || firstreward.type == 2){
                         _tmpl += '<div class="getitem">' + lang('eggpop_got') + '<span style="color:'+helper.getItemColor(firstreward.quality)+'" >'+firstreward.name+'x'+firstreward.value+'</span><span class="pacify_reward"><em style="background-image:url(' + xGame.itemurl(firstreward.id) + ')"></em></span></div>';
                    } 
                }
                
            }
            that_dom.find('#battleend_main').html(_tmpl);

            /* 填充界面 */
            var flagclass = '';

            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }

            that_dom.find('.flag').addClass(flagclass);
            
            if (data.reward_info.reputation){
                that_dom.find('#battleend_main').find('.get1').children('p').html('+' + data.reward_info.reputation);
            } else {
                that_dom.find('#battleend_main').find('.get1').children('p').html('+0');
            }

            var doneRender = function() {
                miscpop.hide('battleendpop');
                returndata.stage = data.info.stage;

                if (data.info.complete == 1) {
                    returndata = {
                        clear: true
                    };       
                }

                xGame.loadWorld(oldworld);

                if (pacifypop && pacifypop.e) {
                    pacifypop.e.show()
                    
                    if (data.reward_info.first_kill) {
                        pacifypop.removeDifficulty();
                        pacifypop.getBeastInfo(pacifypop.nowMilitary);
                        pacifypop.firstBossKill = data.first_succ;
                    }   
                    
                    pacifypop.beastDom.hide();
                }
                
            };
            that_dom.find('.confirm_btn').unbind('touchend').bind('touchend', doneRender);

            that_dom.find('.replay_btn').unbind('touchend').bind('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });

            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        'c': 'copies',
                        'm': 'send_to_chat',
                        'd': 'newequip',
                        'report': data.info.report,
                        'id': pacifypop.nowMilitary,
                        'diff_id': pacifypop.nowDifficulty,
                        'monster_id': pacifypop.nowBeastId,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }
        },
	
        /* 显示输入邀请码 */
        showInvitecode: function() {
            var dom = $('#invitecodepop_wrap');
            if(dom.length > 0) {//该界面已经弹出来啦
                return false;
            }
            var dom = xGame.loadPopTmpl('invitecode');

            var giveInputFn = function() {
                $('#invitecodepop').remove();
            };
            var inputFn = function(code) {
                var successFunc = function(res) {
                    if(res.status == 1) {//成功，得奖励
                        xGame.toast.show(lang('invitefriend_done', [res.reward.gold, res.reward.silver]));
                        player.updateinfo({
                            gold: parseInt(player.info.gold) + parseInt(res.reward.gold),
                            silver: parseInt(player.info.silver) + parseInt(res.reward.silver)
                        });
                        $('#invitecodepop').remove();
                        if(invitationpop && invitationpop.e) {
                            invitationpop.e.find('#invitefriend_myinvitor_btn').hide();
                        }
                    } else if(res.status == 2) {//邀请码已经过期
                        xGame.toast.show(lang('inviteitem_code_drop'));
                    } else if(res.status == 3) {//邀请码错误
                        xGame.toast.show(lang('inviteitem_code_error'));
                        $('#invitecodepop').find('#input_invitecode').val('');
                    } else if(res.status == 4) {//领过了
                        xGame.toast.show(lang('had_receive'));
                    } else if(res.status == 5) {//错误次数太多
                        xGame.toast.show(lang('inviteitem_code_too_much_error'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                };
                loadContents({
                    'c': 'invitation',
                    'm': 'change',
                    'code': code
                }, successFunc);
            };
            dom.find('.hidebtn').unbind('touchend').bind('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                giveInputFn();
            });
            dom.find('#submit_invitecode_btn').unbind('touchend').bind('touchend', function(e) {
                var input_invitecode = $('#input_invitecode').val();
                if(input_invitecode) {
                    inputFn(input_invitecode);
                } else {
                    giveInputFn();
                }
                e.preventDefault();
                e.stopPropagation();
            });
            dom.show();
            dom = null;
        },

        showComment: function() {
            if(!_inArray([3, 100, 106, 107, 153, 154], channelID)) {//就官方版本才引导
                return false;
            }
            var marketUrl = {
                3: 'https://itunes.apple.com/app/han-jiang-san-guo/id577752754?l=zh&mt=8',
                100: 'https://itunes.apple.com/app/wu-shen-guan-sheng/id594385563?l=zh&mt=8',
                106: 'https://itunes.apple.com/cn/app/han-jiang-san-guo/id739810801?ls=1&mt=8',//iPhone
                107: 'https://itunes.apple.com/cn/app/han-jiang-san-guohd/id747785885?ls=1&mt=8',//iPad
                153: 'https://itunes.apple.com/kr/app/bunnouimuseong/id614460115?mt=8',
                154: 'https://itunes.apple.com/jp/app/san-guoguroriasu/id619087844?mt=8',
            };
            xGame.confirm.show(lang('comment_title'), lang('comment_msg'), 1,
                function() {
                    var render = function(res) {
                        if (res.status == 1) {
                            player.updateinfo({
                                "gold": res.all_gold,
                            });
                            //延迟
                            setTimeout(function() {
                                xGame.confirm.show(lang('comment_reward_title'), lang('comment_reward_msg', [res.add_gold]), 1,
                                function() {
                                    xGame.confirm.hide()
                                });
                            }, 1000);
                            //去APPSTORE
                            console.log(marketUrl[channelID]);
                            if(typeof cordova != 'undefined' &&  islocal == false) {//非本地才跳转
                                location.href = marketUrl[channelID];
                            }
                        } else if(res.status == -1) {
                            xGame.toast.show(lang('had_receive'));
                        } else if(res.status == -3) {
                            xGame.toast.show(lang('comment_level_limit'));
                        } else {
                            xGame.toast.show(lang('undefined_error'));
                        }
                        render = null;
                    };
                    //render({status: 1, gold: 10000, add_gold: 50});
                    loadContents({
                        'c': 'clickreward',
                        'm': 'index'
                    },
                    render);
                },
            null, lang('comment_btn'), '', true);
        },

        //显示添加到主屏幕
        showAddToScreen: function() {
            return;//暂时屏蔽
            //浏览器和主屏幕不显示
            if (typeof window.navigator.standalone == 'undefined' || window.navigator.standalone == true) {
                return false;
            }

            var csscfg = {};
            //浏览器和主屏幕不显示
            if ($.os.ipad) {//ipad
                csscfg['left'] = '48px';
                csscfg['margin'] = '0';
            } else {//iPhone
                if (supportFullScreen) {//iOS6支持全屏，按钮位置有变化
                    if (isIPhone5) {
                        csscfg['left'] = '330px';
                        csscfg['margin'] = '0';
                    } else {
                        csscfg['left'] = '240px';
                        csscfg['margin'] = '0';
                    }
                    
                } else {//ios4-5
                    
                }
            }
            csscfg['display'] = 'block';
            $('#add_notice').css(csscfg).attr('show','true');
        },

        hideAddToScreen: function(){
            $('#add_notice').css('display','none').attr('show','false');
        },

        //显示去appstore下载
        showStandaloneTips: function() {
            //浏览器和非主屏幕不显示
            /*
            if (typeof window.navigator.standalone == 'undefined' || window.navigator.standalone == false) {
                return false;
            }*/

            $('#standalonetips').css('display','block').attr('show','true');
            $('#standalonetips').find('p').html(lang('standalonetips_msg'));
        },

        hideStandaloneTips: function(){
            $('#standalonetips').addClass('hide').unbind('webkitAnimationEnd').one('webkitAnimationEnd', function(){
                $(this).css('display','none').attr('show','false').removeClass('hide');
            });
        },

        /* 退出游戏 */
        exitGame: function() {
            xGame.xsRemove('token');
            xGame.xsRemove('addr');
            xGame.xsRemove('guide');
            xGame.xsRemove('guidenow');
            xGame.xsRemove('game_notice');
            xGame.xsRemove('chatdata');
            xGame.xsRemove('tryuid');
            xGame.xsRemove('trykey');
            xGame.xsRemove("serverlist", true);
            xGame.xsRemove('chatdata');
            xGame.xsRemove('chatlasttab');
            //2013-8-22 关卡bug修复
            xGame.xsRemove(player.info.uid + 'guide_hide_page', true);

            if (typeof gAudio != 'undefined' && gAudio != null) {
                gAudio.stopMusic();
            }
            if(typeof Platform != 'undefined' &&  Platform.id == 'pc') {
                window.location.href = Platform.loginurl;
                return false;
            } else {
                window.location.hash = '';
                xGame.xsRemove('temptoken');
                setTimeout(function() {
                    window.location.reload();
                }, 100);
            }
        },

        /* 绑定微博弹窗 */
        weiboBindPop: function(bindAfterFn) {//bindAfterFn 绑定成功后执行的回调
            /* 2026-10-19 加固：原判断 typeof window.plugins.childBrowser != 'undefined'
               有两个坑——① window.plugins 不存在时读 .childBrowser 直接抛
               "Cannot read properties of undefined"；② childBrowser 为 null 时
               typeof null == 'object' 守卫失效，cb.onLocationChange 赋值即崩。
               桌面/H5 一律走不了微博绑定，统一"插件可用才继续"（与 settingspop
               的 window.plugins && window.plugins.childBrowser 判断对齐）。 */
            if (typeof cordova == 'undefined' || !window.plugins || !window.plugins.childBrowser) {
                return;
            }
                /* 已绑定的就不再处理 */
                if(player.info.weibobind == 1) {
                    if(bindAfterFn) {
                        bindAfterFn();
                        return;
                    }
                }

                var that = this;

                gAudio.stopMusic();//由于childbrowser会触发声音，所以需要先停止

                cb = window.plugins.childBrowser;
                cb.onLocationChange = function(url) {
                    console.log(url);
                    if(url.indexOf('bind_success') > 0) {//当拿到token之后，关闭
                        cb.close();
                        if(bindAfterFn) {
                            bindAfterFn();
                        } else {
                            xGame.toast.show('恭喜您成功绑定微博，现在您可以分享战报啦！');
                        }
                    }
                };
                cb.onClose = function() { 
                    player.reloadInfo();
                    if(xGame.nowWorld == 'battle') {
                        gAudio.playbgm('battle_bgm');
                    } else {
                        gAudio.playbgm('city_bgm');
                    }
                };
                
                var weiboappid = '104155745';
                // 本地化：原 UC 回调域名已失效，改为同源回调地址（微博绑定功能未迁移）
                var callbackurl = location.origin + '/index.php?c=weibocallback&m=index';
                var statestring = xGame.server_prefix + ',' + player.info.uid;
                var weibourl =  'https://api.weibo.com/oauth2/authorize?client_id=' + weiboappid + '&state=' + statestring + '&display=mobile&redirect_uri=' + encodeURIComponent(callbackurl);
                cb.showWebPage(weibourl, {showLocationBar: false});
        },

        fetchBattleLogUrl : function(logid, logtype, next) {
            //先从dom里寻找
            var dom_link = $('#social_wrap').data('link');
            if(dom_link && dom_link != '') {
                console.log('link: ' + dom_link);
                if(next) {
                    next(dom_link); 
                }
                return;
            }

            var fetchRender = function(res) {
                if(res.status == 1) {
                    $('#social_wrap').data('link', res.link);
                    console.log('link: ' + res.link);
                    if(next) {
                        next(res.link);
                    }
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'report',
                'm': 'save',
                'id': logid,
                'type': logtype
            },
            fetchRender);
        },

        share2weibo: function(msg, uploadpic) {
            var render = function(res) {
                if(res.status == 1) {
                    xGame.toast.show('分享到微博成功！');
                } else if(res.status == -103) {
                    xGame.toast.show('您已经成功分享过本战报了！');
                } else {
                    xGame.toast.show('分享失败，请稍后重试！');
                }
            };
            loadContents({
                'c': 'weibo',
                'm': uploadpic === 'true' ? 'clientupload' : 'clientsend',
                'uid': player.info.uid,
                'message': msg
            },
            render);
        },

        //用于PC版本复制到粘贴板的pop
        openCopyPc: function(url) {
            var that = this;
            var that_dom = xGame.loadPopTmpl('copypc');
            that_dom.find('#battlelog_url').val(url);
            that_dom.find('#hide_btn').unbind('touchend').bind('touchend', function(e) {
                $('#copypcpop').remove();
                e.preventDefault();
                e.stopPropagation();
            });
            that_dom.show();

            $('#ajaxloading').show();

            ScriptQueue.AddTask(staticUrl + 'js/plugin/ZeroClipboard.js', function() {
                ZeroClipboard.setMoviePath(staticUrl + 'js/plugin/ZeroClipboard.swf');
                var clip = new ZeroClipboard.Client();
                clip.setText( '' ); // will be set later on mouseDown
                clip.setHandCursor( true );
                clip.addEventListener( 'complete', function(client, text) {
                    $('#copypcpop').remove();
                     xGame.toast.show('恭喜您，战报地址已经复制到粘贴板。');
                });
                clip.addEventListener( 'mouseDown', function(client) { 
                    clip.setText( $('#battlelog_url').val() );
                });
                
                clip.glue( 'realcopy_btn' );

                $('#ajaxloading').hide();
            });
            
        },

        socialShareDisplay: function(sharedata, sharetype) {
            if(appLanguage != 'zh-cn' || channelID != 106) {
                return;
            }

            var sharetitle = '';
            var sharewords = '';
            switch(sharetype) {
                case 'battle'://主线
                    sharetitle = '五关斩六将！';
                    sharewords = '看我在#悍将三国#中披荆斩棘，所向披靡，吓得守城将领们屁滚尿流！';
                    break;
                case 'arena'://PVP
                case 'worldarena':
                case 'qiecuo':
                case 'mine'://矿区资源
                case 'countrymine':
                    sharetitle = '你知道谁是天下第一吗？';
                    sharewords = '我在#悍将三国#中天天虐别人，嘿~这腰也不酸了，背也不疼了，一虐顶过去被虐！小贼，拿命来~';
                    break;
                case 'champions'://战神殿
                    sharetitle = '天下英雄，谁与争锋！';
                    sharewords = '#悍将三国#战神争霸战， 等你来参战！';
                    break;
                case 'cbattle'://国战
                    sharetitle = '攻城略地，天下归一！';
                    sharewords = '#悍将三国#一支穿云箭，千军万马来相见！';
                    break;
                case 'gvg'://群服
                    sharetitle = '史上最牛的战役，你见过吗？';
                    sharewords = '#悍将三国#为了兄弟、为了荣誉';
                    break;
            }
            /* 社交分享 */
            var socail_dom = $('#social_wrap');


            /* 微博暂不开放
            socail_dom.delegate('.share2weibo_btn', 'touchend', function(e) {//微博
                if(typeof cordova == 'undefined') {
                    xGame.toast.show('只有在ios客户端里才能分享到微博哟');
                    return false;
                }

                miscpop.weiboBindPop(function() {
                    miscpop.fetchBattleLogUrl(sharedata.id, sharedata.type, function(_url) {
                        miscpop.share2weibo(sharetitle + sharewords + ' ' + _url, true);
                        socail_dom.find('.share2weibo_btn').attr('class', 'btn pop_btn_middle share2weibo_btn_disable');
                    });
                });
                e.preventDefault();
                e.stopPropagation();
                
            });*/

            /* 只有最新2.8才支持的 */
            socail_dom.find('.copyborad_btn').unbind('touchend').bind('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if(typeof cordova == 'undefined' && Platform.id != 'pc') {//普通网页版本
                    xGame.toast.show('只有在ios客户端里才能复制战报地址哟');
                } else if(typeof cordova == 'undefined' && Platform.id == 'pc') {//PC版本
                    miscpop.fetchBattleLogUrl(sharedata.id, sharedata.type, function(_url) {
                        miscpop.openCopyPc(_url);
                    });
                } else if(typeof cordova != 'undefined' && typeof window.plugins.copyboardPlugin != 'undefined') {//客户端版本
                    miscpop.fetchBattleLogUrl(sharedata.id, sharedata.type, function(_url) {
                        window.plugins.copyboardPlugin.copy([_url]);
                        xGame.toast.show('恭喜，您已成功将战报地址复制到粘贴板。');
                    });
                } else {
                    xGame.toast.show('您的版本较旧，不支持复制地址，建议您尽快更新。');
                }
                return false;
            });
            socail_dom.find('.share2weixin_btn').unbind('touchend').bind('touchend', function(e) {//微信
                e.preventDefault();
                e.stopPropagation();

                if(typeof cordova == 'undefined') {
                    xGame.toast.show('只有在ios客户端里才能分享到微信哟');
                    return false;
                } else if(jBreaking == true) {
                    xGame.toast.show('您的设备已越狱，不支持分享到微信哟');
                    return false;
                }
                miscpop.fetchBattleLogUrl(sharedata.id, sharedata.type, function(_url) {
                    window.plugins.wxTimeline.sendmsg([sharetitle, sharewords, _url], function(status) {
                        if(status == true) {
                            socail_dom.find('.share2weixin_btn').attr('class', 'btn pop_btn_middle share2weixin_btn_disable');
                            xGame.toast.show('恭喜您，已成功分享到微信！');
                        }
                    }, null, null);
                });
            });
            socail_dom.show();


        },

        // 显示将领信息弹窗
        showHeroInfo: function (data) {
            
            var heroInfo = xGame.loadPopTmpl('heroinfo');
            var hidebtn = heroInfo.find('#heroinfopop_hidebtn');
            var mask = heroInfo.find('.mask');
            var scroller = null;

            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                scroller.destroy();
                scroller = null;
                heroInfo.remove();
                heroInfo = null;
                e.preventDefault();
            });

            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            heroInfo.find('.hero_avatar').css({'background': 'url('+ xGame.avatar(data.image, true) +') 0 0 no-repeat;'});
            heroInfo.find('.hero_name').html(data.name);
            heroInfo.find('.hero_type').html(helper.getItemVocation(data.type));
            heroInfo.find('.hero_stars').css({'width': (18 * parseInt(data.star)) + 'px'});
            heroInfo.find('.hero_wuli').html(lang('springshoppop_wuli', [data.wuli]));
            heroInfo.find('.hero_zhili').html(lang('springshoppop_zhili', [data.zhili]));
            heroInfo.find('.hero_tili').html(lang('springshoppop_tili', [data.tili]));
            heroInfo.find('.hero_talent_title').html(lang('springshoppop_talent'));
            heroInfo.find('.hero_talent').html('<span class="title">' + data.talent.name + '</span>：' + data.talent.description);
            heroInfo.find('.hero_skill_title').html(lang('springshoppop_skill'));
            heroInfo.find('.hero_skill .skill_content').html('<span class="title">' + data.skill.name + '</span>：' + data.skill.description);

            scroller = new iScroll('heroinfo_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
            });
        },

        // 显示装备信息弹窗
        showItemInfo: function (data) {
            
            var itemInfo = xGame.loadPopTmpl('iteminfo');
            var hidebtn = itemInfo.find('#iteminfopop_hidebtn');
            var mask = itemInfo.find('.mask');

            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                itemInfo.remove();
                itemInfo = null;
                e.preventDefault();
            });

            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            itemInfo.find('.itembox').addClass('c_i_bj' + data.quality);
            itemInfo.find('.img').css({'background': 'url('+ xGame.itemurl(data.id) +') center center no-repeat'});
            itemInfo.find('.needlevel').html(lang('need_lv') + data.needlevel);
            itemInfo.find('.name').css({'color': helper.getItemColor(data.quality)}).html(data.name);
            itemInfo.find('.vocation').html(lang('mountpop_job') + '：' + helper.getItemVocation(data.type));
            itemInfo.find('.powerup').html(helper.getItemPowerName(data.type) + '：' + data.power);
        },

        // 显示宝石信息弹窗
        showGemInfo: function (data) {
            var itemInfo = xGame.loadPopTmpl('iteminfo');
            var hidebtn = itemInfo.find('#iteminfopop_hidebtn');
            var mask = itemInfo.find('.mask');

            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }
                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                itemInfo.remove();
                itemInfo = null;
                e.preventDefault();
            });
            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });
            itemInfo.find('.itembox').addClass('c_i_bj' + data.gem_quality);
            itemInfo.find('.img').css({'background': 'url('+ xGame.gem(data.gem_type, data.gem_quality) +') center center no-repeat'});
            itemInfo.find('.needlevel').html(helper.getTalent(parseInt(data.gem_type)));
            itemInfo.find('.name').css({'color': helper.getItemColor(data.gem_quality)}).html(data.gem_name);
            itemInfo.find('.vocation').html(lang('level') + '：' + data.gem_level);
            itemInfo.find('.powerup').html(lang('properties') + '：' + helper.getTalent(parseInt(data.gem_type)) + '+' + data.gem_power);
        },
        //显示坐骑信息弹窗
        showMarchInfo: function (data) {
            var itemInfo = xGame.loadPopTmpl('marchinfo');
            var hidebtn = itemInfo.find('#marchinfopop_hidebtn');
            var mask = itemInfo.find('.mask');
            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }
                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                itemInfo.remove();
                itemInfo = null;
                e.preventDefault();
            });
            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            itemInfo.find('.itembox').addClass('c_i_bj' + data.quality);
            itemInfo.find('.img').css({'background-position': helper.getHorsePostion('small', data.id)});
            itemInfo.find('.needlevel').html(lang());
            itemInfo.find('.name').css({'color': helper.getItemColor(data.quality)}).html(data.name);
            itemInfo.find('#marchattack').html(data.attack);
            itemInfo.find('#marchwufang').html(data.wufang);
            itemInfo.find('#marchhp').html(data.hp);
            itemInfo.find('#marchlv').html(0);
            itemInfo.find('#marchcefang').html(data.attack);
            itemInfo.find('#marchcefangup').html(data.cefang);
            if(data.type!= 'undefined' && data.type == 1){          //坐骑添加新属性
                itemInfo.find('#gongji').html(lang('valentinepop_march_gongji',[data.value]));
            }else if(data.type!= 'undefined' && data.type == 2){
                itemInfo.find('#gongji').html(lang('valentinepop_march_fangyu',[data.value]));
            }

        },
        // 显示将魂信息弹窗
        showSoulInfo: function (data, isGet, callback) {
            var itemInfo = xGame.loadPopTmpl('iteminfo');
            var hidebtn = itemInfo.find('#iteminfopop_hidebtn');
            var mask = itemInfo.find('.mask');

            if (isGet == true) {
                itemInfo.addClass('getSoul');
            }

            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                itemInfo.remove();
                itemInfo = null;

                if (callback) {
                    callback();
                }
                
                e.preventDefault();
            });

            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            if (isGet != true) {
                itemInfo.find('.itembox').addClass('c_i_bj' + data.quality);
            }

            itemInfo.find('.img').css({'background-image': 'url(' + staticUrl + 'img/pop/soul_' + data.image + '.png' + ')'});
            itemInfo.find('.needlevel').html(helper.getSoulTypeName(parseInt(data.type)));
            itemInfo.find('.name').css({'color': helper.getItemColor(data.quality)}).html(data.name);
            itemInfo.find('.vocation').html(lang('level') + '：' + data.level);
            itemInfo.find('.powerup').html(lang('properties') + '：' + helper.getSoulTypeName(parseInt(data.type)) + '+' + data.power);
        },
	
        //显示宝石合成弹窗
        showMergegemInfo: function(data, callback) {
            var itemInfo = xGame.loadPopTmpl('iteminfo');
            var hidebtn = itemInfo.find('#iteminfopop_hidebtn');
            var mask = itemInfo.find('.mask');
            
            itemInfo.addClass('getSoul'); 

            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }
                hidebtn.unbind();     
                hidebtn = null;      
                mask.unbind();     
                mask = null;       
                itemInfo.remove();       
                itemInfo = null;

                if (callback) {
                    callback();
                }
                e.preventDefault();
            });

            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            itemInfo.find('.img').css({'background-image': 'url(' + xGame.gem(data.type,data.quality) + ')'});
            itemInfo.find('.needlevel').removeClass('needlevel');
            itemInfo.find('.name').css({'color': helper.getItemColor(data.quality)}).html(data.name);
            itemInfo.find('.vocation').html(lang('level') + '：' + data.level);
            itemInfo.find('.powerup').html(lang('properties') + '：' + helper.getTalent(data.type) + '+' + data.power);
        },

        // 锻造材料信息弹窗
        showMaterialItemInfo: function (data) {
            
            var itemInfo = xGame.loadPopTmpl('materialiteminfo');
            var hidebtn = itemInfo.find('#materialiteminfopop_hidebtn');
            var mask = itemInfo.find('.mask');

            hidebtn.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                itemInfo.remove();
                itemInfo = null;
                e.preventDefault();
            });

            mask.unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            itemInfo.find('.itembox').addClass('c_i_bj' + data.quality);
            itemInfo.find('.img').css({'background': 'url('+ xGame.forgeMaterial(data.id) +') center center no-repeat'});
            itemInfo.find('.name').css({'color': helper.getItemColor(data.quality)}).html(data.name);
            itemInfo.find('.des').html(data.desc);
        },

        // 显示材料掉落副本弹窗
        showMaterialInfo: function (data) {
            var materialinfo = xGame.loadPopTmpl('materialinfo');
            var hidebtn = materialinfo.find('#materialinfo_hidebtn');
            var mask = materialinfo.find('.mask');
            var listDom = materialinfo.find('#materialinfo_copy_list');
            var scroller = null;
            var html = '';
            var item = null;

            hidebtn.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.unbind();
                hidebtn = null;
                mask.unbind();
                mask = null;
                scroller.destroy();
                scroller = null;
                listDom.undelegate('.copy_btn', 'click');
                listDom = null;
                materialinfo.remove();
                materialinfo = null;
                
                e.preventDefault();
            });

            mask.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                hidebtn.trigger('touchend');
                e.preventDefault();
            });

            // 副本按钮
            listDom.delegate('.copy_btn', 'click', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);
                var copiesId = parseInt($this.attr('copies_id')); // 副本类型
                var diffId = parseInt($this.attr('diff_id')); // 副本难度

                if (pacifypop == null || pacifypop.e == null) {
                    xGame.showpop('pacify', {
                        'callback': function () {
                            pacifypop.getBeastInfo(copiesId, diffId);
                        }
                    })
                } else {
                    pacifypop.getBeastInfo(copiesId, diffId);
                }

                hidebtn.trigger('touchend');
                systempop.hide();
                e.preventDefault();
            });

            
            materialinfo.find('.box').addClass('material_quality' + data.quality);
            materialinfo.find('.img').css('background-image', 'url(' + xGame.forgeMaterial(data.type) + ')');
            materialinfo.find('.name').html(data.mname);
            materialinfo.find('.intro').html(data.mdesc);

            // 构建副本入口列表
            for (var i = 0, len = data.guide.length; i < len; i++) {
                item = data.guide[i];
                html += '<li class="btn '+ (parseInt(item.open) == 1 ? 'copy_btn' : 'copy_btn_disable') +'" copies_id="'+ item.copies_id +'" diff_id="'+ item.diff_id +'">';
                html += lang('materialinfopop_copy' + item.copies_id) + '(' + lang('materialinfopop_diff' + item.diff_id) + ')';
                html += '</li>';
            }

            listDom.html(html);

            scroller = new iScroll('materialinfo_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            item = null;
        },

        // 显示铸造装备属性弹窗
        showForgeEquipInfo: function (id) {

            var render = function (res) {
                if (parseInt(res.status) == 1) {
                    showInfoPop(res.equip_info);
                } else {
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'get_equip_attr',
                'd': 'newequip',
                'id': id,
            }, render);

            function showInfoPop(data) {
                var pop = xGame.loadPopTmpl('forgeequipinfo');
                var hidebtn = pop.find('#forgeequipinfopop_hidebtn');
                var introDom = pop.find('#forgeequipinfo_intro');
                var attributeDom = pop.find('#forgeequipinfo_attribute');
                var suitData = data.suitarr;
                var html = '';

                hidebtn.bind('touchend', function () {
                    
                    if (xGame.checkLock(true)) {
                        return false;
                    }

                    hidebtn.unbind();
                    hidebtn = null;
                    introDom = null;
                    attributeDom = null;
                    pop.remove();
                    pop = null;
                });

                introDom.find('.box').addClass('c_i_bj' + data.quality);
                introDom.find('.img').css('background-image', 'url(' + xGame.itemurl(data.equipid) + ')');
                introDom.find('.des').html(data.desc);
                introDom.find('.name').css('color', helper.getItemColor(data.quality)).html(data.name);

                if (parseInt(data.hammer) > 0) {
                    introDom.find('.hammer').addClass('hammer' + data.hammer_quality).html(data.hammer).show();
                } else {
                    introDom.find('.hammer').hide();
                }

                if (parseInt(data.level) > 0) {
                    introDom.find('.strong_level').html(data.level).show();
                } else {
                    introDom.find('.strong_level').hide();
                }

                var prop = 0;   // 1：攻击，2：防御，3：生命
                var type = parseInt(data.type);    // 获取装备的属性类型
                if (type <= 2) {
                    prop = 1;
                } else if (type <= 4) {
                    prop = 2;
                } else {
                    prop = 3;
                }

                attributeDom.find('.need_level > .des').html(data.needlevel);
                attributeDom.find('.initial_prop > .title').html(lang('initial_prop' + prop)).siblings('.des').html(data.power);
                attributeDom.find('.strong_prop > .title').html(lang('strong_prop' + prop)).siblings('.des').html(data.powerup);
                attributeDom.find('.refine_prop > .title').html(lang('refine_prop' + prop)).siblings('.des').html(data.refineadd);
                attributeDom.find('.strong_achieve > .des').html(data.stgadd);
                pop.find('.total_prop').html(lang('total_prop' + prop) + data.allpower);
                pop.find('.suit_name').html(suitData.name);

                for (var i = 1; i <= 4; i++) {
                    html += '<li class="box c_i_bj'+ suitData.quality +'">';
                    html += '<span class="img" style="background-image: url('+ xGame.itemurl(suitData['eid' + i]) +')"></span>';

                    if (suitData['open' + i] == 0) {
                        html += '<div class="mask"></div>';
                    }

                    html += '<em class="name" style="color: '+ helper.getItemColor(suitData.quality) +'">' + suitData['name' + i] + '</em>';
                    html += '</li>';
                }

                pop.find('.equip_list').html(html);
                html = '';

                for (var i = 2; i <= 4; i++) {
                    html += '<li class="add_item '+ (parseInt(data.open) >= i ? 'equip' : '') +'">';
                    html += '<div class="title">' + lang('forgeequipinfopop_equip', [i]) + '</div>';
                    html += '<p class="add_attr">' + helper.getSuitAttr(suitData['suit'+ i +'_1'], suitData['params' + i + '_1']) + '</p>';
                    html += '<p class="add_attr">' + helper.getSuitAttr(suitData['suit'+ i +'_2'], suitData['params' + i + '_2']) + '</p>';
                    html += '</li>';
                }

                pop.find('.add_list').html(html);
            }
        },

        // 从抢夺碎片界面进入战斗
        enterBattleFromBeauty: function(res) {

            /* 开始进入战斗界面 */
            $('#worldloading').addClass('black').show();

            setTimeout(function() {
                xGame.loadBattle(res,
                function() {
                    if (parseInt(res.b_info.chip_status) == 1) {
                        miscpop.showRewardbox(res, 'beauty');
                    } else {
                        miscpop.battleResultFromBeauty(res);
                    }
                },
                '');
            },
            500);

        },

        battleResultFromBeauty: function(data) {
            var that = this;

            that.clearflower = false;

            var that_dom = xGame.loadPopTmpl('battleend');
            that_dom.find('#battleend_main').attr('class', 'main type8');
            var rewardHtml = '';
            var rewardInfo = data.reward_info;
            var rewardType = parseInt(rewardInfo.type);
            var chipHtml = '';
            var chipInfo = data.b_info;
            var nowChipInfo = data.chip[parseInt(chipInfo.chip_id) - 1];

            player.updateinfo({
                'gold': data.all_gold,
                'silver': data.all_silver
            });

            switch (rewardType) {
                case 1: // 元宝
                case 2: // 银币
                case 3: // 黄宝石
                case 4: // 紫宝石
                case 5: // 原魂
                case 6: // 玉石
                case 7: // 声望
                case 9: // 宝石碎片
                case 10: // 突飞卡
                case 15: // 精炼石
                case 16: // 五彩石
                case 17: // 上古血玉
                case 31: // 玫瑰花
                case 32: // 钻石
                    rewardHtml = '<p class="reward type'+ rewardType +'">x' + rewardInfo.value + '</p>';
                    break;
                case 8: // 装备碎片
                    rewardHtml = '<p class="name" style="color: '+ helper.getItemColor(rewardInfo.info.quality) +';">' + rewardInfo.info.name + '</p>';
                    rewardHtml += '<div class="itembox c_i_bj'+ rewardInfo.info.quality +'"><span class="img" style="background-image: url('+ xGame.itemurl(rewardInfo.info.id) +');"></span></div>';
                    break;
                case 30: // 美人碎片
                    rewardHtml = '<p class="chip_name">' + rewardInfo.info.name + '</p>';
                    rewardHtml += '<div class="beauty_chip"><span class="img" style="background-image: url('+ staticUrl + 'img/pop/beautypop_girl' + rewardInfo.info.beauty_id + '_chip' + rewardInfo.info.chip_id + '.png' +');"></span></div>';
                    break;
                default :
                    break;
            }

            if (parseInt(chipInfo.chip_status) == 1) {
                chipHtml = '<p class="chip_name">' + nowChipInfo.chip_name + '</p>';
                chipHtml += '<div class="beauty_chip"><span class="img" style="background-image: url('+ staticUrl + 'img/pop/beautypop_girl' + nowChipInfo.beauty_id + '_chip' + nowChipInfo.chip_id + '.png' +');"></span></div>';
            } else {
                chipHtml = '<p class="name">' + lang('no') + '</p>';
            }

            var html1 = '<div class="left"><span class="title">' + lang('beautypop_battle_reward') + '</span>' + rewardHtml + '</div>';
            var html2 = '<div class="right"><span class="title">' + lang('beautypop_get_chip') + '</span>' + chipHtml + '</div>';
            that_dom.find('#battleend_main').html(html1 + html2);

            /* 填充界面 */
            var flagclass;
            if (data.info.win > 0) {
                flagclass = 'flagwin' + data.info.win;
            } else if (data.info.win == 0) { //平局
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('equal');
            } else {
                flagclass = 'flaglose' + Math.abs(data.info.win);
                that_dom.find('.flagbg').addClass('lose');
            }
            that_dom.find('.flag').addClass(flagclass);

            var doneRender = function() {
                miscpop.hide('battleendpop');
                xGame.loadWorld('city');

                if (beautypop && beautypop.e) {
                    beautypop.e.show();

                    if (beautypop.robChip) {
                        beautypop.robChip.show();
                    }
                }
            };

            that_dom.find('.confirm_btn').unbind('touchend').one('touchend', function () {
                doneRender();
            });
            that_dom.find('.replay_btn').unbind('touchend').one('touchend',
            function() {
                miscpop.hide('battleendpop');
                that.destoryAllBombFlower();
                battleWorld.refight();
            });
            if (data.info.report > 0) {
                that_dom.find('.share_btn').unbind('touchend').bind('touchend',
                function() {
                    miscpop.shareBattle({
                        c: 'beauty',
                        m: 'send_to_chat',
                        //外部传入
                        touid: data.info.touid,
                        report: data.info.report,
                        win: data.info.win,
                    });
                    $(this).addClass('disable').unbind('touchend');
                });
            } else {
                that_dom.find('.share_btn').addClass('disable');
            }
            /* 第四步 展示弹窗 */
            that_dom.show();
            that_dom = null;

            /* 完胜后放烟花 */
            if (data.info.win == 3) {
                that.createBombFlower();
            }

        },

        /* 显示新输入邀请码 */
        showNewinvitecode: function() {
            var dom = $('#newinvitecodepop_wrap');
            if(dom.length > 0) {//该界面已经弹出来啦
                return false;
            }
            var dom = xGame.loadPopTmpl('newinvitecode');
            var infoDom = dom.find('#newinvitecode_info_page');

            var giveInputFn = function() {
                dom.remove();
                dom = null;
                infoDom = null;
            };

            var inputFn = function(code) {
                var successFunc = function(res) {
                    if(res.status == 1) {//成功，得奖励
                        xGame.toast.show(lang('invitefriend_done', [res.reward.gold, res.reward.silver]));
                        player.updateinfo({
                            gold: parseInt(player.info.gold) + parseInt(res.reward.gold),
                            silver: parseInt(player.info.silver) + parseInt(res.reward.silver)
                        });
                        dom.remove();
                        dom = null;
                        infoDom = null;
                        if(invitationpop && invitationpop.e) {
                            invitationpop.e.find('#invitefriend_myinvitor_btn').hide();
                        }
                    } else if(res.status == 2) {//邀请码已经过期
                        xGame.toast.show(lang('inviteitem_code_drop'));
                    } else if(res.status == 3) {//邀请码错误
                        xGame.toast.show(lang('inviteitem_code_error'));
                        dom.find('#input_newinvitecode').val('');
                    } else if(res.status == 4) {//领过了
                        xGame.toast.show(lang('had_receive'));
                    } else if(res.status == 5) {//错误次数太多
                        xGame.toast.show(lang('inviteitem_code_too_much_error'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                };
                loadContents({
                    'c': 'invitation',
                    'm': 'change',
                    'code': code
                }, successFunc);
            };

            dom.find('.hidebtn').unbind('touchend').bind('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                giveInputFn();
            });
            
            dom.find('#submit_newinvitecode_btn').unbind('touchend').bind('touchend', function(e) {
                var input_invitecode = $('#input_newinvitecode').val();
                if(input_invitecode) {
                    inputFn(input_invitecode);
                } else {
                    giveInputFn();
                }
                e.preventDefault();
                e.stopPropagation();
            });

            // 玩法介绍按钮
            dom.find('#newinvitecode_info').unbind('touchend').bind('touchend', function (e) {
                infoDom.show();
                e.preventDefault();
            });

            // 玩法介绍隐藏按钮
            dom.find('#newinvitecode_info_hidebtn').unbind('touchend').bind('touchend', function (e) {
                infoDom.hide();
                e.preventDefault();
            });

            // 玩法介绍的遮罩层
            infoDom.find('.mask').unbind('touchend').bind('touchend', function (e) {
                infoDom.hide();
                e.preventDefault();
            });

            dom.show();
        },

        hide: function(pop) {
            var that = this;

            if (pop) {
                $('#' + pop).remove();
            }
        },
    };
})();