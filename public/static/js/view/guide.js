/* 背包 */
(function() {
    guideClass = function() {
        this.now = 0;
        this.fullmask = null;
        this.step = 0;  // 引导续接的步数
        this.action = {     // 引导步骤801-900：将领界面，901-1000：任务界面，1001-1100：点将台界面，1101-1200：布阵界面，1201-1300：校场界面
            1 : function() { //首次进入，曲阳
                $('#header').find('.rightside_wrap').hide();
                // 容灾校正：确保引导处于势力地图(missionsite=2)，避免在大地图找不到.guanqia1而卡死遮罩
                if (($('#mapworld_page').find('.guanqia1').length == 0 || player.info.missionsite != 2) && typeof mapWorld !== 'undefined' && mapWorld.loadShiliMap) {
                    player.info.missionsite = 2;
                    mapWorld.loadShiliMap(1);
                }
                guide.showNpc(lang('guide1'), function () {
                    guide.createFullmask();
                    guide.anim2bottom(function() {
                        guide.cleanFullmask();
                        guide.create('click', $('#mapworld_page').find('.guanqia1'));
                    });
                });
                if (player.missionThread != null) {
                    clearInterval(player.missionThread);
                    player.missionThread = null;
                }
            },
            2 : function() { //第一个小兵
                guide.move2bottom(function() {
                    guide.create('click', $('#mapworld_page').find('#mission1'));
                });
            },
            3 : function() { //小兵信息弹窗里的攻击
                $('#enemypop').css('opacity', '0');
                guide.showNpc(lang('guide2'), function () { // 廖化
                    $('#enemypop').css('opacity', '1');
                    guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                }, 2);
            },
            4 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            5 : function() { //第2个小兵
                guide.showNpc(lang('guide3'), function () {        // 蔡文姬
                    guide.showNpc(lang('guide4'), function () {    // 刘备
                        guide.showNpc(lang('guide5'), function () { // 蔡文姬
                            guide.showNpc(lang('guide6'), function () { // 张飞
                                guide.showNpc(lang('guide7'), function () { // 关羽
                                    guide.showNpc(lang('guide8'), function () { // 蔡文姬
                                        guide.move2bottom(function() {
                                            guide.create('click', $('#mapworld_page').find('#mission2'));
                                        });
                                    })
                                }, 5);
                            }, 4);
                        });
                    }, 3);
                });
            },
            6 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            7 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'), false);
            },
            8 : function() {
                guide.showNpc(lang('guide13'), function () {    // 蔡文姬
                    guide.showNpc(lang('guide14'), function() {     // 关羽
                        guide.showNpc(lang('guide15'), function () { // 蔡文姬
                            //点将领按钮
                            guide.create('touchend', $('#menu_list').find('.menu_hero'));
                        });
                    }, 5);
                });
            },// 跳至801
            101 : function() { //第3个小兵
                guide.move2bottom(function() {
                    guide.create('click', $('#mapworld_page').find('#mission3'));
                });
            },
            102 : function() { //小兵信息弹窗里的攻击
                $('#enemypop').css('opacity', '0');
                guide.showNpc(lang('guide101'), function () {   // 廖化
                    guide.showNpc(lang('guide102'), function () {   // 刘备
                        guide.showNpc(lang('guide103'), function () {   // 张飞
                            guide.showNpc(lang('guide104'), function () {   // 廖化
                                $('#enemypop').css('opacity', '1');
                                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                            }, 2);
                        }, 4);
                    }, 3);
                }, 2);
            },
            103 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            104 : function() { //第4个小兵
                guide.showNpc(lang('guide105'), function () {    // 蔡文姬
                    guide.showNpc(lang('guide106'), function () {   // 张飞
                        guide.showNpc(lang('guide107'), function () {   // 廖化
                            guide.move2bottom(function() {
                                guide.create('click', $('#mapworld_page').find('#mission4'));
                            });
                        }, 2);
                    }, 4);
                });
            },
            105 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            106 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'), false);
            },
            107 : function() { //第5个小兵
                guide.createFullmask();
                guide.move2bottom(function() {
                    guide.anim2top(function() {
                        guide.cleanFullmask();
                        guide.create('click', $('#mapworld_page').find('#mission5'), false, null, false, true);
                    });
                });
            },
            108 : function() { //小兵信息弹窗里的攻击
                $('#enemypop').css('opacity', '0');
                guide.showNpc(lang('guide108'), function () {    // 刘备
                    guide.showNpc(lang('guide109'), function () {    // 邓茂
                        guide.showNpc(lang('guide110'), function () {    // 张飞
                            $('#enemypop').css('opacity', '1');
                            guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                        }, 4);
                    }, 6);
                }, 3);
            },
            109 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            110 : function() { //点将领按钮
                guide.showNpc(lang('guide111'), function () {    // 蔡文姬
                    guide.showNpc(lang('guide112'), function () {   // 刘备
                        guide.create('touchend', $('#menu_list').find('.menu_hero'));
                    }, 3);
                });
            },  // 跳至811
            201 : function() { //第6个小兵
                guide.showNpc(lang('guide201'), function () {   // 蔡文姬
                    guide.create('click', $('#mapworld_page').find('#mission6'));
                });
            },
            202 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            203 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            204 : function() { //第7个小兵
                guide.create('click', $('#mapworld_page').find('#mission7'));
            },
            205 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            206 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            207 : function() { //第8个小兵
                guide.create('click', $('#mapworld_page').find('#mission8'));
            },
            208 : function() { //小兵信息弹窗里的攻击
                $('#enemypop').css('opacity', '0');
                guide.showNpc(lang('guide202'), function () {    // 刘备
                    guide.showNpc(lang('guide203'), function () {   // 张飞
                        guide.showNpc(lang('guide204'), function () {   // 关羽
                            guide.showNpc(lang('guide205'), function () {   // 张飞
                                $('#enemypop').css('opacity', '1');
                                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                            }, 4);
                        }, 5);
                    }, 4);
                }, 3);
            },
            209 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            210 : function() { //点将领按钮
                guide.showNpc(lang('guide206'), function () {    // 蔡文姬
                    guide.create('touchend', $('#menu_list').find('.menu_hero'));
                });
            },  // 跳至821
            301 : function() { //第9个小兵
                guide.create('click', $('#mapworld_page').find('#mission9'));
            },
            302 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            303 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            304 : function() { //第10个,打BOSS
                guide.showNpc(lang('guide301'), function () {   // 蔡文姬
                    guide.showNpc(lang('guide303'), function () {   // 刘备
                            guide.create('click', $('#mapworld_page').find('#mission10'));
                    }, 3);
                });
            },
            305 : function() { //小兵信息弹窗里的攻击
                $('#enemypop').css('opacity', '0');
                guide.showNpc(lang('guide305'), function () {    // 关羽
                    guide.showNpc(lang('guide306'), function () {    // 周仓
                        $('#enemypop').css('opacity', '1');
                        guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                    }, 6);
                }, 5);
            },
            306 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            307 : function() {
                guide.showNpc(lang('guide307'), function () {    // 周仓
                    guide.showNpc(lang('guide308'), function () {        // 蔡文姬
                        guide.showNpc(lang('guide309'), function () {    // 周仓
                            guide.show(308);
                        }, 6);
                    });
                }, 6);
            },
            308 : function () { // 弹出招募奖励窗口
                var general = xGame.xsGet(player.info.uid + 'getgeneral', true);
                if (general) {
                    var generalinfo = JSON.parse(general);
                    miscpop.newhero(generalinfo, function() {
                        guide.show(309);
                    });
                    xGame.toast.show(lang('zhaomudao_xx', [generalinfo.name]));
                    xGame.xsRemove(player.info.uid + 'getgeneral', true);
                } else {
                    guide.show(309);
                }
            },
            309 : function() { //回城按钮
                player.currentSite = 1;
                $('#mission_wrap').find('ul').css('top', '0px');
                guide.showNpc(lang('guide310'), function () {    // 蔡文姬
                    guide.showNpc(lang('guide311'), function () {   // 张飞
                        guide.showNpc(lang('guide312'), function () {    // 刘备
                            guide.showNpc(lang('guide314'), function() {  // 蔡文姬
                                guide.showNpc(lang('guide315'), function () {   // 蔡文姬
                                    var menuRender = function() {
                                        mapWorld.resultDisplay(); //处理其他事件
                                        guide.create('touchend', $('#menu_city'), false, function() {
                                            $('#header').find('.rightside2_wrap').hide();
                                            $('body').removeClass('commonhide');
                                            xGame.loadWorld('city');
                                            guide.show(401);
                                            player.reloadInfo(); //防止没有获得第一个任务目标的奖励
                                        });
                                    };
                                    guide.checkMenuAndDo(menuRender);
                                });
                            });
                        }, 3);
                    }, 4);
                });
            },
            401 : function() { //领取任务奖励（击败周仓），跳至901
                $('#header .leftside_wrap').removeClass('hide'); // 用于引导续接
                guide.showNpc(lang('guide401'), function () {    // 蔡文姬
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                    }
                });
            },
            404 : function() { //点击点将台，跳至1001
                guide.showNpc(lang('guide402'), function () {
                    guide.showNpc(lang('guide403'), function () {
                        guide.create('click', $('#muster_building'));
                    });
                });
            },
            408 : function() { //去布阵
                guide.showNpc(lang('guide404'), function() {
                    guide.show(409);
                });
            },
            409 : function() { //点击布阵，跳至1101
                if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                    guide.create('touchend', $('#menu_control'), false, function () {
                        $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                        var menuRender = function() {
                            guide.create('touchend', $('#menu_list').find('.menu_team'));
                        };
                        guide.checkMenuAndDo(menuRender);
                    });
                } else {
                    guide.create('touchend', $('#menu_list').find('.menu_team'));
                }
            },
            501 : function() { //点击校场，跳至1201
                guide.showNpc(lang('guide501'), function () {
                    guide.create('click', $('#practice_building'));
                });
            },
            514 : function() { //点任务
                guide.showNpc(lang('guide502'), function () {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                    }
                });
            },
            601 : function() {
                guide.updatemissionsite(2);
                guide.create('click', $('#mapworld_page').find('.guanqia2'));
            },
            602 : function() {
                guide.showNpc(lang('guide601'), function() {
                    guide.create('click', $('#mapworld_page').find('#mission1'));
                });

                if ($('#skip_guide_btn').length == 0) {
                    guide.skipGuide();
                }
            },
            603 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            604 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            605 : function() { //第2个
                guide.create('click', $('#mapworld_page').find('#mission2'));
            },
            606 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            607 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            608 : function() { //点将领按钮
                guide.showNpc(lang('guide602'), function () {
                    guide.create('touchend', $('#menu_list').find('.menu_hero'));
                });
            },  // 跳至831
            612 : function() { //第3个
                guide.create('click', $('#mapworld_page').find('#mission3'));
            },
            613 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            614 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            615 : function() { //第4个
                guide.create('click', $('#mapworld_page').find('#mission4'));
            },
            616 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            617 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            618 : function() { //点将领按钮
                guide.showNpc(lang('guide603'), function () {
                    guide.create('touchend', $('#menu_list').find('.menu_hero'));
                });
            },  // 跳至841
            701 : function() { //第5个
                guide.showNpc(lang('guide701'), function () {
                    guide.create('click', $('#mapworld_page').find('#mission5'));
                });
            },
            702 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            703 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            704 : function() { //第6个
                guide.create('click', $('#mapworld_page').find('#mission6'));
            },
            705 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            706 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            707 : function() { //第7个
                guide.create('click', $('#mapworld_page').find('#mission7'));
            },
            708 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            709 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            710 : function() { //第8个
                guide.create('click', $('#mapworld_page').find('#mission8'));
            },
            711 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            712 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            713 : function() { //第9个
                guide.create('click', $('#mapworld_page').find('#mission9'));
            },
            714 : function() { //小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            715 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            716 : function() { //点将领按钮
                guide.create('touchend', $('#menu_list').find('.menu_hero'));
            },  // 跳至851
            720 : function() { //BOSS 张梁
                guide.create('click', $('#mapworld_page').find('#mission10'));
            },
            721 : function() { //小兵信息弹窗里的攻击
                $('#enemypop').css('opacity', '0');
                guide.showNpc(lang('guide702'), function () {
                    $('#enemypop').css('opacity', '1');
                    guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                });
            },
            722 : function() { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },  // 跳至1409

            723 : function () { //BOSS 张梁
                guide.create('click', $('#mapworld_page').find('#mission10'));
            },
            724 : function () {//小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            725 : function () { //战斗结果信息弹窗里的关闭按钮
                guide.create('touchend', $('#battleend_wrap').find('.confirm_btn'));
            },
            726 : function() { //张梁
                mapWorld.resultDisplay(); //处理其他事件
                guide.showNpc(lang('guide703'), function() {
                    var menuRender = function() {
                        guide.create('touchend', $('#menu_city'), false,
                        function() {
                            guide.show(1301);
                        });
                    };
                    guide.checkMenuAndDo(menuRender);

                });
            },

            // 801 - 900：将领
            801 : function() { //手滑动
                //点击版
                //guide.create('touchend', $('#heropop').find('#hero_dress').find('.pop_btn_middle'));
                //滑动版
                var heropop_wrap = $('#heropop_wrap');
                guide.create('touchend', $('#hero_dress .btn'));
                guide.showRepeat(heropop_wrap, lang('guide801'), 180, 420);
                heropop_wrap = null;
            },
            802 : function() { //点关闭
                guide.hideFinger();
                $('#guide_repeat_wrap').remove();
                var hidebtn = $('#systempop #system_hidebtn');
                guide.create('touchend', hidebtn, false, function() {
                    guide.show(101);
                });
                hidebtn = null;
            },
            811 : function() { //滑动
                var heropop_wrap = $('#heropop_wrap');
                heropop.displayFirstItem('.unequip');
                guide.create('touchend', $('#hero_dress .btn'));
                guide.showRepeat(heropop_wrap, lang('guide801'), 180, 420);
                heropop_wrap = null;
            },
            812 : function() { //点关闭
                guide.hideFinger();
                $('#guide_repeat_wrap').remove();
                var hidebtn = $('#systempop #system_hidebtn');
                guide.create('touchend', hidebtn, false,
                function() {
                        guide.show(201);
                });
                hidebtn = null;
            },
            821 : function() { //拖动披风
                var heropop_wrap = $('#heropop_wrap');
                heropop.displayFirstItem('.unequip');
                guide.create('touchend', $('#hero_dress .btn'));
                guide.showRepeat(heropop_wrap, lang('guide801'), 180, 420);
                heropop_wrap = null;
            },
            822 : function() { //点关闭
                guide.hideFinger();
                $('#guide_repeat_wrap').remove();
                var hidebtn = $('#systempop #system_hidebtn');
                guide.create('touchend', hidebtn, false,
                function() {
                        guide.show(301);
                });
                hidebtn = null;
            },
            831 : function() { //将领里选周仓
                var hero = $('#hero_list_scroller').find('.heroitem')[1];
                guide.create('click', $(hero), false, null, false, true);
            },
            832 : function() { //滑动
                var heropop_wrap = $('#heropop_wrap');
                heropop.displayFirstItem('.unequip');
                guide.create('touchend', $('#hero_dress .btn'));
                heropop_wrap = null;
            },
            833 : function() { //点关闭
                guide.hideFinger();
                $('#guide_repeat_wrap').remove();
                var hidebtn = $('#systempop #system_hidebtn');
                guide.create('touchend', hidebtn, false, function() {
                        guide.show(612);
                });
                hidebtn = null;
            },
            841 : function() { //将领里选周仓
                var hero = $('#hero_list_scroller').find('.heroitem')[1];
                guide.create('click', $(hero), false, null, false, true);
            },
            842 : function() { //滑动
                var heropop_wrap = $('#heropop_wrap');
                heropop.displayFirstItem('.unequip');
                guide.create('touchend', $('#hero_dress .btn'));
                heropop_wrap = null;
            },
            843 : function() { //点关闭
                guide.hideFinger();
                $('#guide_repeat_wrap').remove();
                var hidebtn = $('#systempop #system_hidebtn');
                heropop.displayFirstItem();
                guide.create('touchend', hidebtn, false, function() {
                    guide.show(701);
                });
                hidebtn = null;
            },
            851 : function() { //将领里选周仓
                var hero = $('#hero_list_scroller').find('.heroitem')[1];
                guide.create('click', $(hero), false, null, false, true);
            },
            852 : function() { //滑动
                var heropop_wrap = $('#heropop_wrap');
                heropop.displayFirstItem('.unequip');
                guide.create('touchend', $('#hero_dress .btn'));
                heropop_wrap = null;
                guide.autoEquip();
            },
            853 : function() { //点关闭
                guide.hideFinger();
                $('#guide_repeat_wrap').remove();
                var hidebtn = $('#systempop #system_hidebtn');
                heropop.displayFirstItem();
                guide.create('touchend', hidebtn, false, function() {
                    guide.show(720);
                });
                hidebtn = null;
            },
            861: function () {
                guide.create('touchend', $('#systempop .strong'), false, null, false, true);
            },

            // 901 - 1000：任务
            901 : function () { // 领取击败周仓奖励
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li0 .pop_btn_middle'));
            },
            902 : function() {
                $('#missionpop_hide').show();
                guide.create('touchend', $('#missionpop_hide'), false, function () {
                    guide.show(404);
                });
            },
            911 : function() { // 任务：击败张梁
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li0 .goto'));
            },
            921 : function () { // 领取击败张梁奖励
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li0 .pop_btn_middle'));
            },
            922 : function () {
                $('#missionpop_hide').show();
                guide.create('touchend', $('#missionpop_hide'), false, function () {
                    guide.show(1304);
                });
            },
            931 : function() { // 任务：击败张宝
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li0 .goto'));
            },

            // 1001 - 1100：点将台
            1001 : function() { //选择周仓
                var dom = $('#herolist_carousel').find('.heroitem')[1];
                guide.create('click', $(dom), false, null, null, true);
            },
            1002 : function() { //让周仓出征
                guide.create('touchend', $('#musterpop_battlebtn'));
            },
            1003 : function() { //关闭点将台
                guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                    guide.show(408);
                });
            },
            1011 : function() { //选择张梁
                var dom = $('#herolist_carousel').find('.heroitem')[2];
                guide.create('click', $(dom), false, null, null, true);
            },
            1012 : function() { //让张梁出征
                guide.create('touchend', $('#musterpop_battlebtn'));
            },
            1013 : function() { //关闭点将台
                guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                    guide.show(1308);
                });
            },

            // 1101 - 1200：布阵
            1101 : function() { //布阵升级
                guide.create('touchend', $('#matrix_lvup_btn'), false, null, false, true);
            },
            1102 : function() { //布阵手移动
                var teampop_wrap = $('#teampop_wrap');
                var hidebtn = $('#systempop #system_hidebtn');
                hidebtn.hide();
                guide.showNpc(lang('guide1101'), function () {
                    guide.animFinger(teampop_wrap, 'team');
                    teampop_wrap = null;
                });
                hidebtn = null;
            },
            1103 : function() { //关闭布阵
                //teampop.updateTeam();
                guide.hideFinger();
                var hidebtn = $('#systempop #system_hidebtn');
                hidebtn.show();
                guide.create('touchend', hidebtn, false, function() {
                    guide.show(501);
                });
                hidebtn = null;
            },
            1111 : function() { //布阵升级
                guide.create('touchend', $('#matrix_lvup_btn'), false, null, false, true);
            },
            1112 : function() { //布阵手移动
                var teampop_wrap = $('#teampop_wrap');
                var hidebtn = $('#systempop #system_hidebtn');
                hidebtn.hide();
                guide.animFinger(teampop_wrap, 'team2');
                teampop_wrap = null;
                hidebtn = null;
            },
            1113 : function() { //布阵手移动
                //teampop.updateTeam();
                guide.hideFinger();
                guide.showNpc(lang('guide1102'),
                function() {
                    guide.animFinger($('#teampop_wrap'), 'team3');
                });
            },
            1114 : function() { //关闭布阵
                //teampop.updateTeam();
                guide.hideFinger();
                var hidebtn = $('#systempop #system_hidebtn');
                hidebtn.show();
                guide.create('touchend', hidebtn, false, function() {
                    guide.show(1323);
                });
                hidebtn = null;
            },

            // 1201 - 1300：校场
            1201 : function() { //选择周仓
                var nexthero = $('#left_dom').find('.hero')[1];
                if (nexthero) {
                    guide.create('touchend', $(nexthero), false, function() {
                        guide.show(1202);
                    },
                    null, true);
                } else {
                    guide.show(1203);
                }
            },
            1202 : function() { //训练周仓
                guide.create('touchend', $('#start_practice'));
            },
            1203 : function() {
                //引导里，第一次0元宝
                $('#tufei_cost').html('0' + lang('gold'));
                if (practicepop && practicepop.leap) {
                    practicepop.leap = 0;
                }
                guide.showNpc(lang('guide1201'), function() {
                    guide.show(1204);
                });
            },
            1204 : function() { //校场里，点击突飞
                $('#tufei_cost').html('0' + lang('gold'));
                if (practicepop && practicepop.leap) {
                    practicepop.leap = 0;
                }

                guide.showRepeat($('#practicepop_wrap'), lang('guide1202'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1205 : function() { //校场里，点击突飞
                $('#tufei_cost').html('0' + lang('gold'));
                if (practicepop && practicepop.leap) {
                    practicepop.leap = 0;
                }

                guide.showRepeat($('#practicepop_wrap'), lang('guide1203'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1206 : function() { //校场里，点击突飞
                $('#tufei_cost').html('0' + lang('gold'));
                if (practicepop && practicepop.leap) {
                    practicepop.leap = 0;
                }

                guide.showRepeat($('#practicepop_wrap'), lang('guide1204'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1207 : function() { //校场里，点击突飞
                $('#tufei_cost').html('0' + lang('gold'));
                if (practicepop && practicepop.leap) {
                    practicepop.leap = 0;
                }

                guide.showRepeat($('#practicepop_wrap'), lang('guide1205'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1208 : function() { //校场里，点击突飞
                $('#tufei_cost').html('0' + lang('gold'));
                if (practicepop && practicepop.leap) {
                    practicepop.leap = 0;
                }

                guide.showRepeat($('#practicepop_wrap'), lang('guide1206'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1209 : function() { //关闭校场
                guide.destoryRepeat($('#practicepop_wrap'));
                guide.showNpc(lang('guide1207'), function () {
                    guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                        guide.show(514);
                    });
                });
            },
            1211 : function () { // 训练张梁
                var nexthero = $('#left_dom').find('.hero')[2];
                if (nexthero) {
                    guide.create('touchend', $(nexthero), false, function() {
                        guide.show(1212);
                    },
                    null, true);
                } else {
                    guide.show(1213);
                }
            },
            1212 : function () { // 开始训练
                guide.create('touchend', $('#start_practice'));
            },
            1213 : function () {
                guide.showNpc(lang('guide1208'), function () {
                    guide.show(1214);
                });
            },
            1214 : function() { //校场里，点击突飞

                guide.showRepeat($('#practicepop_wrap'), lang('guide1202'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1215 : function() { //校场里，点击突飞

                guide.showRepeat($('#practicepop_wrap'), lang('guide1203'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1216 : function() { //校场里，点击突飞

                guide.showRepeat($('#practicepop_wrap'), lang('guide1204'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1217 : function() { //校场里，点击突飞

                guide.showRepeat($('#practicepop_wrap'), lang('guide1205'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1218 : function() { //校场里，点击突飞

                guide.showRepeat($('#practicepop_wrap'), lang('guide1206'), 300, 260);
                guide.create('touchend', $('#go_leap'), false,
                function() {
                    guide.createFullmask();
                });
            },
            1219 : function() { //关闭校场
                guide.destoryRepeat($('#practicepop_wrap'));
                guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                    guide.show(1318);
                });
            },

            // 击败张梁回城后
            1301 : function () {    // 跳至911
                guide.showNpc(lang('guide1301'), function () {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                    }
                });
            },
            1304 : function() { //点击点将台，跳至1011
                guide.create('click', $('#muster_building'));
            },
            1308 : function () { // 点击校场，跳至1211
                guide.showNpc(lang('guide1302'), function () {
                    guide.create('click', $('#practice_building'));
                });
            },
            1318 : function() { //点击布阵，跳至1111
                guide.showNpc(lang('guide1303'), function () {
                    guide.create('touchend', $('#menu_list').find('.menu_team'));
                });
            },
            1323 : function() {
                guide.showNpc(lang('guide1304'), function () {    // 蔡文姬
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                    }
                });
            },
            1324 : function () {
                guide.updatemissionsite(2);
                guide.create('click', $('#mapworld_page').find('.guanqia3'));
            },
            1325 : function() { // 打开等级礼包
                guide.showNpc(lang('guide1305'), function () {
                    guide.create('touchend', $('#header_levelgift_btn_wrap'), false, null, null, true);
                });

                xGame.xsRemove(player.info.uid + 'guide_hide_page', true);
                $('#header').find('.rightside2_wrap').css('display', 'none'); //之前用了.show()，现在是为了显示出来但同时又不影响其他css
                $('#header').find('.rightside_wrap').show();
                xGame.guideSthShow();
            },
            1326 : function () {
                guide.showNpc(lang('guide1306'), function () {
                    guide.create('click', $('#levelgift_list > li:eq(0)').find('.pop_btn_middle'));
                });
            },
            1327 : function () {
                guide.showNpc(lang('guide1307'), function () {
                    guide.create('touchend', $('#levelgiftpop').find('#hidebtn'), false, function () {
                        guide.show(1328);
                    });
                });
            },
            1328 : function() {
                $('#header').find('.rightside2_wrap').css('display', '');
                guide.create('touchend', $('#menu_control'), false,
                function() {
                        if(typeof Platform != 'undefined' && Platform.id == 'h5') {//H5版本提示添加到主屏
                            miscpop.showAddToScreen();
                            miscpop.showStandaloneTips();
                        } else {
                            guide.create('click', $('#mapworld_page').find('#mission1'), false, null, false, true);
                        }
                });
                guide.showNpc(lang('guide1308'));
            },
            1329 : function () {
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
            },
            // 1401 : function() { //20级触发，回城，点出征
            //     miscpop.hide('enemypop');
            //     guide.showNpc(lang('guide1401'), function() {
            //         if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
            //             guide.create('touchend', $('#menu_control'), false, function () {
            //                 $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
            //                 var menuRender = function() {
            //                     mapWorld.resultDisplay(); //处理其他事件
            //                     guide.show(1409);
            //                 };
            //                 guide.checkMenuAndDo(menuRender);
            //             });
            //         } else {
            //             guide.show(1409);
            //         }
            //     });
            // },
            1409 : function() {
                xGame.xsSet('guide', '1');
                xGame.guide = true;
                mapWorld.resultDisplay(); //处理其他事件
                guide.showNpc(lang('guide1408'), function() {
                    guide.create('touchend', $('#menu_list').find('.menu_hero'));
                });
            },
            1410 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1409'), 400, 490);
                //xGame.toast.show('请强化当前武器10次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1411 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1410'), 400, 490);
                //xGame.toast.show('请强化当前武器9次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1412 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1411'), 400, 490);
                //xGame.toast.show('请强化当前武器8次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1413 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1412'), 400, 490);
                //xGame.toast.show('请强化当前武器7次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1414 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1413'), 400, 490);
                //xGame.toast.show('请强化当前武器6次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1415 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1414'), 400, 490);
                //xGame.toast.show('请强化当前武器5次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1416 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1415'), 400, 490);
                //xGame.toast.show('请再次强化（还差4次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1417 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1416'), 400, 490);
                //xGame.toast.show('请再次强化（还差3次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1418 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1417'), 400, 490);
                //xGame.toast.show('请再次强化（还差2次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1419 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1418'), 400, 490);
                //xGame.toast.show('请再次强化（最后一次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'), false, function () {
                    guide.destoryRepeat($('#strongpop_wrap'));
                });
            },
            1420 : function () { //点强化
                guide.create('click', $('#strongpop').find('.strongpop_listitem:eq(1)'), false, function () {
                    guide.showRepeat($('#strongpop_wrap'), lang('guide1419'), 400, 490);
                    //xGame.toast.show('请强化当前防具10次！');
                    guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
                }, false, true);
            },
            1421 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1410'), 400, 490);
                //xGame.toast.show('请强化当前武器9次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1422 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1411'), 400, 490);
                //xGame.toast.show('请强化当前武器8次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1423 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1412'), 400, 490);
                //xGame.toast.show('请强化当前武器7次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1424 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1413'), 400, 490);
                //xGame.toast.show('请强化当前武器6次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1425 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1414'), 400, 490);
                //xGame.toast.show('请强化当前武器5次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1426 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1415'), 400, 490);
                //xGame.toast.show('请再次强化（还差4次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1427 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1416'), 400, 490);
                //xGame.toast.show('请再次强化（还差3次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1428 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1417'), 400, 490);
                //xGame.toast.show('请再次强化（还差2次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1429 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1418'), 400, 490);
                //xGame.toast.show('请再次强化（最后一次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'), false,
                function() {
                    guide.destoryRepeat($('#strongpop_wrap'));
                });
            },
            1430 : function() { //关闭强化
                guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                    guide.show(1431);
                });
            },
            1431 : function() { //点任务
                guide.showNpc(lang('guide1420'), function () {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                    }
                });
            },
            1432 : function() { // 领取强化装备奖励
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li2 .pop_btn_middle'));
            },
            1433 : function () {
                $('#missionpop_hide').show();
                guide.create('touchend', $('#missionpop_hide'), false, function () {
                    guide.show(1434);
                });
            },
            1434 : function () {
                guide.showNpc(lang('guide1421'), function () {
                    guide.show(723);
                });
            },
            1501 : function () { //30级触发
                xGame.xsSet('guide', '1');
                xGame.guide = true;
                miscpop.hide('enemypop');
                guide.showNpc(lang('guide1501'), function () {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#header_levelgift_btn_wrap'));
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#header_levelgift_btn_wrap'));
                    }
                });

                if ($('#skip_guide_btn').length == 0) {
                    guide.skipGuide();
                }
            },
            1502 : function () {
                guide.create('click', $('#levelgift_l30 > .pop_btn_middle'));
            },
            1503 : function () {
                guide.showNpc(lang('guide1502'), function () {
                    xGame.xsRemove('guide');
                    xGame.xsRemove('guidenow');
                    xGame.guide = false;
                });
            },

            // 40级触发
            1504 : function() {
                xGame.xsSet('guide', '1');
                xGame.guide = true;
                miscpop.hide('enemypop');
                guide.showNpc(lang('guide1503'), function () {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#menu_list').find('.menu_muster'));
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#menu_list').find('.menu_muster'));
                    }
                });

                if ($('#skip_guide_btn').length == 0) {
                    guide.skipGuide();
                }
            },
            1505 : function () {
                guide.create('touchend', $('#systempop .foster'), false, null, false, true);
            },
            1506 : function() { //引导结束
                guide.showNpc(lang('guide1504'), function () {
                    guide.showNpc(lang('guide1510'), function () {
                        xGame.xsRemove('guide');
                        xGame.xsRemove('guidenow');
                        xGame.guide = false;
                    });
                });
            },
            1601 : function() {
                mapWorld.resultDisplay(); //处理其他事件
                if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                    guide.showNpc(lang('guide1601'),
                    function() {
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#menu_city'), false,
                                function() {
                                    guide.show(1602);
                                });
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    });
                } else {
                    guide.showNpc(lang('guide1601'),
                    function() {
                        var menuRender = function() {
                            guide.create('touchend', $('#menu_city'), false,
                            function() {
                                guide.show(1602);
                            });
                        };
                        guide.checkMenuAndDo(menuRender);
                    });
                }
            },
            1602 : function() { //领取任务奖励（击败张宝）
                if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                    guide.create('touchend', $('#menu_control'), false, function () {
                        $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                        var menuRender = function() {
                            guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                        };
                        guide.checkMenuAndDo(menuRender);
                    });
                } else {
                    guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                }
            },
            1603 : function () {
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li0 .pop_btn_middle'));
            },
            1604 : function() {
                $('#missionpop_hide').show();
                guide.create('touchend', $('#missionpop_hide'), false, function () {
                    guide.show(1605);
                });
            },
            1605 : function() { //点击点将台
                    guide.create('click', $('#muster_building'));
            },
            1606 : function() { //选择张宝
                var dom = $('#herolist_carousel').find('.heroitem')[3];
                guide.create('click', $(dom), false, null, null, true);
            },
            1607 : function() { //让张宝出征
                guide.create('touchend', $('#musterpop_battlebtn'));
            },
            1608 : function() { //关闭点将台
                guide.create('touchend', $('#systempop #system_hidebtn'), false,
                function() {
                    guide.show(1609);
                });
            },
            1609 : function() { //点击布阵
                guide.create('touchend', $('#menu_list').find('.menu_team'));
            },
            1610 : function() { //点击升级
                guide.create('touchend', $('#matrix_lvup_btn'), false, null, false, true);
            },
            1611 : function() { //布阵手移动
                var teampop_wrap = $('#teampop_wrap');
                var hidebtn = $('#systempop #system_hidebtn');
                hidebtn.hide();
                guide.animFinger(teampop_wrap, 'team4');
                teampop_wrap = null;
                hidebtn = null;
            },
            1612 : function() { //关闭布阵
                guide.hideFinger();
                var hidebtn = $('#systempop #system_hidebtn');
                hidebtn.show();
                guide.create('touchend', hidebtn, false, function() {
                    guide.show(1613);
                });
                hidebtn = null;
            },
            1613 : function() { //继续征战
                guide.showNpc(lang('guide1602'), function() {
                    guide.updatemissionsite(2);
                    guide.create('touchend', $('#menu_map'), false, function() {
                        player.info.missionsite = 2;
                    });
                });
            },
            1614 : function() {
                $("#lastenemy_span").remove();
                guide.create('click', $('#mapworld_page').find('.guanqia4'));
            },
            // 20级触发，砸金蛋
            1701 : function () {

                if (xGame.guide == false) {
                    xGame.xsSet('guide', '1');
                    xGame.guide = true;
                    xGame.xsSet(player.info.uid + 'no_in_guide', '1', true);  // 用于判断打败张宝后是否有引导
                }

                guide.showNpc(lang('guide1701'), function() {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#header_activity_btn_wrap'));
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#header_activity_btn_wrap'));
                    }
                });

                if ($('#skip_guide_btn').length == 0) {
                    guide.skipGuide();
                }
            },
            1702 : function () {
                guide.create('touchend', $('#activitypop').find('#go_egg'));
            },
            1703 : function () {
                guide.showNpc(lang('guide1702'), function () {
                    guide.showNpc(lang('guide1703'));
                });
                var noInGuide = xGame.xsGet(player.info.uid + 'no_in_guide', true);

                if (noInGuide) {
                    xGame.xsRemove('guide');
                    xGame.guide = false;
                }
            },

            1801 : function() {//点击主线任务，其他的在主线任务里面处理 
                guide.showNpc(lang('guide1801'), function() {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#mission_wrap'), false, null, false, true);
                    }
                });
            },
            1802 : function () {
                $('#missionpop_hide').hide();
                guide.create('click', $('#missionpop_li0 .pop_btn_middle'));
            },
            1803 : function() {
                $('#missionpop_hide').show();
                guide.create('touchend', $('#missionpop_hide'), false, function () {
                    guide.showNpc(lang('guide1802'), function() {
                        guide.create('touchend', $('#menu_back'));
                    }, false, false);
                });
            },
            1804 : function() {
                guide.showNpc(lang('guide1803'), function() {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#menu_world'));
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#menu_world'));
                    }
                });
            },
            1805 : function() {
                guide.showNpc(lang('guide1804'), function() {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#menu_city'), false, function () {
                                    guide.show(1806);
                                });
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#menu_city'), false, function () {
                            guide.show(1806);
                        });
                    }
                });
            },
            1806 : function () { // 点将台
                guide.create('click', $('#muster_building'));
            },
            1807 : function () { // 选择张角
                var dom = $('#herolist_carousel').find('.heroitem[image="20"]');
                guide.create('click', dom, false, null, null, true);
            },
            1808 : function() { //让周仓出征
                guide.create('touchend', $('#musterpop_battlebtn'));
            },
            1809 : function() { //关闭点将台
                guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                    guide.create('touchend', $('#menu_list').find('.menu_team'));
                    xGame.guide = false;
                    xGame.xsRemove('guide');
                    xGame.xsRemove('guidenow');
                    xGame.xsRemove(player.info.uid + 'guide_hide_page', true);
                    $('#footer .bottom_page_wrap').css('display', '');
                });
            },
            // 非引导期间打张梁失败
            1901 : function() {
                xGame.xsSet('guide', '1');
                xGame.guide = true;
                mapWorld.resultDisplay(); //处理其他事件
                guide.showNpc(lang('guide1408'), function() {
                    if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                        guide.create('touchend', $('#menu_control'), false, function () {
                            $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                            var menuRender = function() {
                                guide.create('touchend', $('#menu_list').find('.menu_strong'));
                            };
                            guide.checkMenuAndDo(menuRender);
                        });
                    } else {
                        guide.create('touchend', $('#menu_list').find('.menu_strong'));
                    }
                });
            },
            1902 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1409'), 400, 490);
                //xGame.toast.show('请强化当前武器10次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1903 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1410'), 400, 490);
                //xGame.toast.show('请强化当前武器9次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1904 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1411'), 400, 490);
                //xGame.toast.show('请强化当前武器8次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1905 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1412'), 400, 490);
                //xGame.toast.show('请强化当前武器7次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1906 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1413'), 400, 490);
                //xGame.toast.show('请强化当前武器6次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1907 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1414'), 400, 490);
                //xGame.toast.show('请强化当前武器5次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1908 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1415'), 400, 490);
                //xGame.toast.show('请再次强化（还差4次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1909 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1416'), 400, 490);
                //xGame.toast.show('请再次强化（还差3次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1910 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1417'), 400, 490);
                //xGame.toast.show('请再次强化（还差2次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1911 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1418'), 400, 490);
                //xGame.toast.show('请再次强化（最后一次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'), false, function () {
                    guide.destoryRepeat($('#strongpop_wrap'));
                });
            },
            1912 : function () { //点强化
                guide.create('click', $('#strongpop').find('.strongpop_listitem:eq(1)'), false, function () {
                    guide.showRepeat($('#strongpop_wrap'), lang('guide1419'), 400, 490);
                    //xGame.toast.show('请强化当前防具10次！');
                    guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
                }, false, true);
            },
            1913 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1410'), 400, 490);
                //xGame.toast.show('请强化当前武器9次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1914 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1411'), 400, 490);
                //xGame.toast.show('请强化当前武器8次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1915 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1412'), 400, 490);
                //xGame.toast.show('请强化当前武器7次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1916 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1413'), 400, 490);
                //xGame.toast.show('请强化当前武器6次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1917 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1414'), 400, 490);
                //xGame.toast.show('请强化当前武器5次！');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1918 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1415'), 400, 490);
                //xGame.toast.show('请再次强化（还差4次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1919 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1416'), 400, 490);
                //xGame.toast.show('请再次强化（还差3次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1920 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1417'), 400, 490);
                //xGame.toast.show('请再次强化（还差2次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'));
            },
            1921 : function() { //点强化
                guide.showRepeat($('#strongpop_wrap'), lang('guide1418'), 400, 490);
                //xGame.toast.show('请再次强化（最后一次）');
                guide.create('touchend', $('#strongpop_wrap').find('#strongpop_strongbtn'), false,
                function() {
                    guide.destoryRepeat($('#strongpop_wrap'));
                });
            },
            1922 : function() { //关闭强化
                guide.create('touchend', $('#systempop #system_hidebtn'), false, function() {
                    guide.show(1923);
                });
            },
            1923 : function () { //BOSS 张梁
                guide.showNpc(lang('guide1421'), function () {
                    guide.create('click', $('#mapworld_page').find('#mission10'));
                });
            },
            1924 : function () {//小兵信息弹窗里的攻击
                guide.create('touchend', $('#enemypop_wrap').find('#attack_normal_btn'));
                xGame.xsRemove('guide');
                xGame.guide = false;
            },
        };
    };
    guideClass.prototype = {

        checkMenuAndDo: function(render) {
            if (xGame.menustatus == false) {
                xGame.menuToggle();
                setTimeout(render, 400);
            } else {
                render();
            }
        },

        //根据配置显示引导
        show: function(id) {
            if (!id) {
                this.now++;
            } else {
                this.now = id;
            }
            xGame.xsSet('guidenow', this.now);
            this.cleanFullmask(); //清除可能存在的全屏mask

            if (this.action[this.now] != undefined) {
                var callaction = this.action[this.now];
                callaction();
                this.updateStep();
            } else {
                //console.log('引导完毕或引导不存在');
            }
        },

        create: function(eventName, dom, hidemask, callback, arrowInParent, isright) {
            guide.hide();
            if (dom == null || dom.length <= 0) {
                console.log('箭头指向没有找到目标');
                return false;
            }
            var offset = dom.offset();

            // 如果指向每日活动
            if (dom.attr('id') == 'header_activity_btn_wrap') {
                offset.top += 13;
            }

            //创建遮罩框
            //hidemask = hidemask == null ? true : hidemask;
            if (hidemask != true) {
                this.makeMask(offset);
            }
            //创建箭头
            if (arrowInParent == true) {
                var parentDom = dom.parent();
                this.makeArrow(offset, parentDom, isright);
            } else {
                this.makeArrow(offset, null, isright);
            }
            
            var fired = false;
            var fireTrigger = function(e) {
                if (fired) return false;
                fired = true;
                if (e) {
                    try { e.preventDefault(); e.stopPropagation(); } catch(err) {}
                }
                $('#guide_target').unbind('click touchend');
                $('#guide_arrow').unbind('click touchend');
                dom.trigger(eventName, []);
                if (eventName !== 'click') {
                    dom.trigger('click', []);
                }
                if (eventName !== 'touchend') {
                    dom.trigger('touchend', []);
                }
                guide.hide();
                if (callback) {
                    var cb = callback;
                    callback = null;
                    cb();
                }
                return false;
            };

            $('#guide_target').unbind('click touchend').bind('click touchend', fireTrigger);
            $('#guide_arrow').unbind('click touchend').bind('click touchend', fireTrigger);
            this.cleanFullmask(); //清除可能存在的全屏mask

        },

        //简单的指向引导 arrowInParent : for 拖动区域
        point: function(dom, callback, inScroller) {
            callback = callback || null;
            if (inScroller) {
                this.createSimplePoint(dom, callback, inScroller);
            } else {
                this.create(dom, false, callback);
            }
        },

        createSimplePoint: function(dom, callback, inScroller, eventName) {
            if (dom == null || dom.length <= 0) {
                console.log('箭头指向没有找到目标');
                return false;
            }
            if (inScroller) { //如果在滑动区域内
                var offset = {
                    "left": parseInt(dom.css('left').replace('px', '')),
                    "top": parseInt(dom.css('top').replace('px', '')),
                    "width": dom.width(),
                    "height": dom.height()
                };
            } else {
                var offset = dom.offset();
            }
            //创建箭头
            if (inScroller == true) {
                var parentDom = dom.parent();
                this.makeArrow(offset, parentDom);
            } else {
                this.makeArrow(offset);
            }
            
            var pointFired = false;
            var onPointTrigger = function(e) {
                if (pointFired) return false;
                pointFired = true;
                if (e) {
                    try { e.preventDefault(); e.stopPropagation(); } catch(err) {}
                }
                dom.unbind('click touchend');
                $('#guide_arrow').unbind('click touchend');
                guide.hide();
                if (eventName) {
                    dom.trigger(eventName, []);
                    if (eventName !== 'click') dom.trigger('click');
                    if (eventName !== 'touchend') dom.trigger('touchend');
                }
                if (callback) {
                    var cb = callback;
                    callback = null;
                    cb();
                }
                return false;
            };

            dom.unbind('click touchend').bind('click touchend', onPointTrigger);
            if (eventName) {
                $('#guide_arrow').unbind('click touchend').bind('click touchend', onPointTrigger);
            }
            this.cleanFullmask(); //清除可能存在的全屏mask
        },

        makeMask: function(offset) {
            var body_dom = $('body');
            var body = body_dom.offset();

            var html = '';
            html += '<div id="guide_rect" class="guide_rect" style="width:' + body.width + 'px;height:' + body.height + 'px;left:0px;top:0px;"></div>';
            html += '<div id="guide_target" class="guide_rect" style="width:' + offset.width + 'px;height:' + offset.height + 'px;left:' + offset.left + 'px;top:' + offset.top + 'px;z-index:91;"></div>';
            
            body_dom.append(html);

            body = null;
            body_dom = null;
        },

        makeArrow: function(offset, parentDom, isright) {
            if (isright == true) {
                var left = offset.left + offset.width;
            } else {
                var left = offset.left - 235;
            }
            var top = offset.top + (offset.height - 106) / 2;
            var arrow = '<div id="guide_arrow" class="guide_arrow ' + (isright == true ? ' right': '') + '" style="left:' + left + 'px;top:' + top + 'px"></div>';
            if (parentDom != null && parentDom.length > 0) {
                parentDom.append(arrow);
                parentDom = null;
            } else {
                $('body').append(arrow);
            }
        },

        move2top: function(callback) {
            $('#scroller').css({
                '-webkit-transform': 'translate3d(0px, 0px, 0px) scale(1)'
            });
            if (callback) {
                callback();
            }
        },

        move2bottom: function(callback) {
            var body_height = $('body').height();
            var height = isIPhone5 ? 852 : 768;
            if (body_height > 0 && body_height < height) {
                var moveposition = height - body_height;
                $('#scroller').css({
                    '-webkit-transform': 'translate3d(0px, -' + moveposition + 'px, 0px) scale(1)'
                });
                moveposition = null;
                body_height = null;
            }
            if (callback) {
                callback();
            }
        },

        anim2top: function(callback) {
            var body_height = $('body').height();
            var height = isIPhone5 ? 852 : 768;
            if (body_height < height) {
                $('#scroller').animate({
                    "translate3d": '0px, 0px, 0px'
                },
                300, 'ease-in', callback);
            } else {
                guide.move2top(callback);
            }
            body_height = null;
        },

        anim2bottom: function(callback) {
            var body_height = $('body').height();
            var height = isIPhone5 ? 852 : 768;
            if (body_height > 0 && body_height < height) {
                var moveposition = height - body_height;
                $('#scroller').animate({
                    "translate3d": '0px, -' + moveposition + 'px, 0px'
                },
                300, 'ease-in', callback);
                moveposition = null;
                body_height = null;
            } else if (body_height >= height) {
                callback();
            }
        },

        animFinger: function(dom, type) {
            var finger = $('<div id="guide_finger" class="guide_finger_' + type + '"></div>');
            dom.append(finger);
            /*
            var fingerDestory = function() {
                finger.unbind('webkitAnimationEnd');
                finger.remove();
                finger = null;
            };
            finger.bind('webkitAnimationEnd', fingerDestory).bind('touchend', fingerDestory);*/
        },

        hideFinger: function() {
            $('#guide_finger').remove();
        },

        showNpc: function(word, callback, npcid) {
            npcid = npcid || '';
            var npc_page = $('#guide_npc_page');
            if (npcid != '') {
                npc_page.find('.npc').attr('class', 'npc npc' + npcid);
            } else {
                npc_page.find('.npc').attr('class', 'npc');
            }
            npc_page.find('.word').html(word);
            var fired = false;
            var touchRender = function(e) {
                if (fired) return false;
                fired = true;
                if (e) {
                    try { e.preventDefault(); e.stopPropagation(); } catch(err) {}
                }
                var page = $('#guide_npc_page');
                page.hide();
                page.unbind('click touchend');
                if (callback) {
                    var cb = callback;
                    callback = null;
                    cb();
                }
                return false;
            };
            npc_page.show();
            setTimeout(function() {
                $('#guide_npc_page').unbind('click touchend').bind('click touchend', touchRender);
            }, 300);
            guide.cleanFullmask();
        },

        /* 开场剧情 */
        story: function() {

            loginWorld && loginWorld.clear();
            roleWorld && roleWorld.clear();
            xGame.nowWorld = '';

            $('#world').append($.template('guidestory_tmpl'));
            var story_dom = $('#guidestory_page');
            var p1 = story_dom.find('.p1');
            var p2 = story_dom.find('.p2');

            var nextRender = function() {
                story_dom.remove();
                p1 = null;
                p2 = null;
                story_dom = null;
                xGame.guideSthHide(); //隐藏部分功能
                xGame.loadWorld('map');
                xGame.updateCd('updatePlayer', null);
            };

            p1.show();
            setTimeout(function() {
                p1.addClass('noblur');
                setTimeout(function() {
                    p2.show();
                    setTimeout(function() {
                        p2.addClass('noblur');
                        setTimeout(function() {
                            nextRender();
                            nextRender = null;

                        },
                        3500);
                    },
                    200);
                },
                2000);
            },
            200);

        },

        createFullmask: function() {
            var dom_mask = $('#fullmask');
            if(dom_mask.length == 0) {
                console.log('打开了全屏遮罩');
                $('body').append('<div id="fullmask"></div>');
            }
        },

        cleanFullmask: function() {
            $('#fullmask').remove();
            console.log('关闭了全屏遮罩');
        },

        showRepeat: function(dom, text, left, top) {
            var repeat_wrap = dom.find('#guide_repeat_wrap');
            if (repeat_wrap.length <= 0) {
                dom.append('<div id="guide_repeat_wrap" style="left:' + left + 'px;top:' + top + 'px;">' + text + '</div>');
            } else {
                repeat_wrap.css({
                    "left": left + 'px',
                    "top": top + 'px'
                }).html(text);
            }
        },

        destoryRepeat: function(dom) {
            var repeat_wrap = dom.find('#guide_repeat_wrap');
            if (repeat_wrap.length > 0) {
                repeat_wrap.remove();
            }
            repeat_wrap = null;
        },

        updatemissionsite: function(site) {
            if (!site) {
                site = 2;
            }
            player.info.missionsite = site;
            loadContents({
                'c': 'member',
                'm': 'updatemissionsite',
                'site': site
            },
            function(res) {
                if (res.status == 1) {
                    player.info.missionsite = site;
                }
            },
            true);
        },

        /* 记录用户引导的位置 */
        updateStep: function(win, tufei) {
            
            // 不是强制引导
            if (guide.now >= 1501) {
                return false;
            }

            // 获取攻击敌人弹窗
            var enemy = $('#enemypop')[0];
            var step = this.now;

            if (typeof enemy != 'undefined') {
                step = this.now - 1;
            }

            var battleend = $('#battleendpop');

            if (win && win > 0) {  // 如果战斗赢了
                if (battleend.length > 0) {  // 如果战斗结束，显示战斗结果弹窗，则引导续接跳过此步
                    step = this.now + 1;
                } else {    // 如果在战斗中，则引导续接跳至下下步
                    step = this.now + 2;
                }
            } else if (win && win <= 0) {
                if (battleend.length > 0) {
                    step = this.now - 2;
                } else {
                    step = this.now - 1;
                }
            }

            if (tufei) {
                step ++;
            }

            // 强制引导是否结束
            if (guide.now == 1329) {
                var stop = 1;
            } else {
                var stop = 0;
            }

            loadContents({
                'c': 'member',
                'm': 'updateguidestep',
                'step': step,
                'stop': stop
            },
            null, true);
        },
        autoEquip: function() {
            var obj = $('.moveitem.right1');
            heropop.elePositions = {
                't': 'right',
                'p': obj.attr('rp'),
                'eid': obj.attr('eid')
            };
            heropop.showItemUnequip(obj);
            $('body').unbind('touchstart').one('touchstart', function(e) {
                $('#heropop').find('#hero_dress').find('.pop_btn_middle').trigger('touchend');
                e.preventDefault();
                e.stopPropagation();
            });
            obj = null;
        },
        // 引导续接
        loadGuide:function () {
            var that = this;
            var render = function (res) {

                // 后端引导记录异常或强制引导结束
                if (!res.step || res.stop == 1) {
                    xGame.loadWorld('city');
                    xGame.xsRemove('guide');
                    xGame.xsRemove('guidenow');
                    return;
                }

                var step = parseInt(res.step);
                var stop = parseInt(res.stop);

                xGame.xsSet('guide', '1');
                xGame.xsSet('guidenow', step);
                xGame.guide = true;
                guide.now = step;

                var $leftsideWrap = $('#header .leftside_wrap');
                $leftsideWrap.addClass('hide');
                $('#header .header_gift_btn').hide();
                $('#header .rightside_wrap').hide();
                $('#header .rightside2_wrap').hide();
                $('#chatpop_small').hide();
                $('#footer .bottom_page_wrap').hide();

                that.createFullmask();

                function map() {
                    xGame.loadWorld('map');
                    $('#menu_friends').hide();
                    $('#menu_map').hide();
                    $('#menu_city').show();
                }

                function city() {
                    xGame.loadWorld('city');
                    $('#menu_city').hide();
                    $('#menu_map').show();
                    $('#menu_friends').show();
                }

                // 如果是刚进地图，处于引导开始阶段
                if (step == 0) {
                    map();
                } else if (step > 602) {   // 第二关后显示跳过引导
                    guide.skipGuide();
                }

                // 征战界面
                if ((step >= 1 && step <= 400) || (step >= 601 && step <= 900) || (step >= 1401 && step <= 1500)) {

                    // 如果进入第二个关卡（打张梁）
                    if ((step >= 601 && step <= 900) || (step >= 1401 && step <= 1500)) {
                        $leftsideWrap.removeClass('hide');
                    }

                    if (step >= 801 && step <= 900) {   // 将领界面
                        map();
                        setTimeout(function () {
                            xGame.showpop('hero', step);
                        }, 1000);
                    } else if (step >= 1410 && step <= 1430) {
                        map();
                        setTimeout(function () {
                            xGame.showpop('strong', step);
                        }, 1000);
                    } else if (step >= 1432 && step <= 1433) {
                        map();
                        setTimeout(function () {
                            xGame.showpop('mission', step);
                        }, 1000);
                    } else {
                        that.step = step;
                        map();
                    }
                // 主城界面
                } else if ((step >= 401 && step <= 600) || (step >= 901 && step <= 1400)) {
                    $leftsideWrap.removeClass('hide');

                    if (step >= 901 && step <= 1000) {  // 任务
                        city();
                        setTimeout(function () {
                            xGame.showpop('mission', step);
                        }, 1000);
                    } else if (step >= 1001 && step <= 1100) {  // 点将台
                        city();
                        setTimeout(function () {
                            xGame.showpop('muster', step);
                        }, 1000);
                    } else if (step >= 1101 && step <= 1200) {  // 布阵
                        city();
                        setTimeout(function () {
                            xGame.showpop('team', step);
                        }, 1000);
                    } else if (step >= 1201 && step <= 1300) {  // 校场
                        city();
                        setTimeout(function () {
                            xGame.showpop('practice', step);
                        }, 1000);
                    } else if (step >= 1324 && step <= 1329) {  // 前去第3关卡，击败张宝
                        
                        if (step >= 1325) {
                            xGame.guideSthShow();
                        }

                        if (step == 1326 || step == 1327) {
                            map();
                            setTimeout(function () {
                                xGame.showpop('levelgift', step);
                            }, 1000);
                        } else {
                            that.step = step;
                            map();
                        }

                    } else {
                        that.step = step;
                        city();
                    }
                }
            };

            loadContents({
                'c': 'member',
                'm': 'get_guidestep'
            }, render, true);
        },

        // 跳过引导
        skipGuide: function () {
            
            var that = this;
            var skipBtnDom = $('<em class="skip_guide_btn" id="skip_guide_btn"></em>');
            var guideNpc = $('#guide_npc_wrap');
            guideNpc.append(skipBtnDom);

            // 绑定跳过引导
            skipBtnDom.unbind('click touchend').bind('click touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.confirm.show(lang('skipguide_title'), lang('skipguide_text'), 2, function () {
                    that.now = 1329;
                    that.updateStep();
                    that.hide();
                    xGame.xsRemove('guide');
                    xGame.xsRemove('guidenow');
                    xGame.xsRemove(player.info.uid + 'guide_hide_page', true);
                    xGame.guide = false;
                    $('#guide_npc_page').hide();
                    xGame.guideSthShow();
                    $('#header .rightside2_wrap').css('display', '');

                    if (xGame.nowWorld == 'map') {
                        xGame.loadWorld('map');
                    }

                    var enemypop = $('#enemypop');
                    if (enemypop[0]) {
                        enemypop.css('opacity', '');
                    }
                    
                    $('#footer .bottom_page_wrap').css('display', '');
                    xGame.closePops();
                    xGame.confirm.hide();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
                e.stopPropagation();
            });
        },

        hide: function() {
            this.cleanFullmask();

            var body_dom = $('body');
            body_dom.find('.guide_arrow').remove();
            body_dom.find('#guide_rect').remove();
            body_dom.find('#guide_target').remove();
            /*
            for (var i = 1; i <= 4; i++) {
                body_dom.find('#guide_rect' + i).remove();
            }
            */
        },
    };
})();
