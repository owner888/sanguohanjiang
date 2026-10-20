/* 地图 */
(function() {
    mapWorldClass = function() {
        this.touchObj = null;
        this.first;//目前世界的first状态，相当于是否播放世界动画的开关
    };
    mapWorldClass.prototype = {
        init: function() {
            var that = this;
            //2013-9-11 特殊处理，因为有可能刚好reloadinfo了,所以这里写死
            if (xGame.guide == true && miscpop.in_array(guide.now, [911, 931, 1613])) {
                player.info.missionsite = 2;
            }
            if (player.info.missionsite == 1) {
                that.loadWorldMap();
            } else if (player.info.missionsite == 2) {
                that.loadShiliMap();
            } else if (player.info.missionsite == 3) {
                that.loadGuanqiaMap();
            }

            that = null;
        },

        initMapPage: function() {
            var that = this;

            var dom_footer = $('#footer');
            dom_footer.find('#menu_world').hide();
            dom_footer.find('#menu_back').hide();
            dom_footer.find('.guanqia_page_btn').hide();
            dom_footer = null;

            var dom_world = $('#scroller');
            /* 从其他地方进入地图，需要生成容器dom */
            if (dom_world.find('#mapworld_page').length == 0) {
                dom_world.html($.template('mapworld_tmpl')); //默认是隐藏状态
                xGame.createCloud();

                /* 进地图显示当前位置 */
                $('#world').undelegate('.nowplace', 'webkitAnimationEnd').delegate('.nowplace', 'webkitAnimationEnd',
                function() {
                    $(this).unbind('webkitAnimationEnd');
                    $(this).remove();
                });
            }

            $('#worldloading').removeClass('black').hide();

            dom_world = null;
            that = null;
        },

        loadWorldMap: function(world_id) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            player.info.missionsite = 1;

            var initRender = function(res) {
                var menu_passreward = $('#header').find('#menu_newpassreward');
                if (menu_passreward) {
                    menu_passreward.find('.popo').hide();
                    menu_passreward.hide();
                }

                that.initMapPage();
                var dom_wrap = $('#map_wrap');
                //保证前端先更新不报错
                if ((res.enter_world != undefined && res.enter_world == 1) || res.enter_world == undefined) {
                    dom_wrap.html($.template('mapworld_bigmap_tmpl'));
                } else {
                    dom_wrap.html($.template('mapworld_bigmap_tmpl' + res.enter_world));
                }
                var dom_page = dom_wrap.find('.bigmap');

                var lastenemy = null;
                var html = '';
                // 现代化语义重构：优先读取 res.factions 集合，杜绝脆弱的 res[i] 数字下标探测
                var factionList = res.factions || [];
                if (factionList.length === 0) {
                    for (var k = 1; k <= 50; k++) {
                        if (res[k] && typeof res[k] === 'object' && res[k].id != null) {
                            factionList.push(res[k]);
                        }
                    }
                }

                for (var f = 0; f < factionList.length; f++) {
                    var faction = factionList[f];
                    if (!faction || faction.status == 0) {
                        continue;
                    }
                    var fid = faction.id;
                    lastenemy = fid;
                    var localCfg = map_ini['world'] && map_ini['world'][fid];
                    var posX = localCfg ? localCfg.x : (faction.x || 0);
                    var posY = localCfg ? localCfg.y : (faction.y || 0);
                    var shiliName = localCfg ? localCfg.name : (faction.name || '');

                    html += '<div id="worldshili' + fid + '" class="btn shili ' + (faction.pass_status == 1 ? 'done' : '') + '" style="left:' + getSLeft(posX) + 'px;top:' + getSTop(posY) + 'px" l="' + fid + '"><p>' + shiliName + '</p></div>';
                }
                dom_page.append(html);

                dom_page.undelegate('.shili');
                dom_page.undelegate('.shili', 'touchstart').delegate('.shili', 'touchstart', function() {
                    mapWorld.touchObj = this.id;
                });

                dom_page.undelegate('.shili', 'click').delegate('.shili', 'click', function() {
                    if(xGame.guide == false && this.id != mapWorld.touchObj) {
                        //console.log('目标错误');
                        mapWorld.touchObj = null;
                        return false;
                    }
                    mapWorld.touchObj = null;
                    var shili = $(this);
                    if (!shili.hasClass('close')) {
                        mapWorld.loadShiliMap(shili.attr('l'));
                        shili = null;
                    } else {
                        alert('当前未开启！');
                    }
                });
                
                $('#mapworld_page').show();
                
                //2013-10-29 新的世界地图
                /* 处理显示上下关卡 */
                if (!xGame.xsGet(player.info.uid + 'guide_hide_page', true)) {
                    that.displayWorldPage(res.next_status, res.enter_world);
                }
                that.first = res.first;
                that.showNextWorld(res.next_status, res.enter_world);
                
                xGame.iscrollRefresh();

                /* 处理最近的一个该打的 */
                var lastenemy_dom = null;
                if (lastenemy && res.next_status != 1) {
                    lastenemy_dom = dom_page.find('#worldshili' + lastenemy);
                    if (lastenemy_dom != null && lastenemy_dom.length > 0) { //最近一个加箭头
                        lastenemy_dom.append('<span id="lastenemy_span"></span>');
                    }
                }
                
                lastenemy_dom = null;
                that.resultDisplay();

                xGame.unlock();
                html = null;
                dom_wrap = null;
                dom_page = null;
                initRender = null;
                //that = null;
                
                 //2013-7-22 如果是刚击败张角，回到张世界地图
                if (xGame.guide == true && guide.now == 1804) {
                    guide.show(1805);
                }
            };
            //当没有参数的时候，默认是上次的世界
            if (world_id == undefined) {
                loadContents({
                    'c': 'map',
                    'm': 'index'
                },
                initRender, false,
                function() {
                    xGame.loadWorld('city');
                });
                initRender = null;

            } else {//有参数的时候就是翻页
                loadContents({
                    'c': 'map',
                    'm': 'index',
                    'world': world_id
                },
                initRender, false,
                function() {
                    xGame.loadWorld('city');
                });
                initRender = null;
            }
        },
        
        //传入下一关的状态，让我好判断该如何放故事
        showNextWorld: function(next_status, enter_world) {
            //当first==1并且next_status == 1,表示是在前一个世界，先引导让玩家点击左右按钮
            if (this.first == 1 && next_status == 1) {
                if (xGame.menustatus == false) {//如果是关闭状态的，引导打开
                    guide.create('touchend', $('#menu_control'), false, function () {
                        $('body').append('<div id="guide_rect" class="guide_rect" style="width:100%;height:100%;left:0px;top:0px;"></div>');
                        var menuRender = function() {
                            guide.create('touchend', $('#guanqia_next'));
                        };
                        guide.checkMenuAndDo(menuRender);
                    });
                } else {
                    guide.create('touchend', $('#guanqia_next'));
                }
            //当first==1并且next_status == 0,表示是在新一个世界，引导玩家看动画
            } else if (this.first == 1 && next_status == 0) {
                this.showWorldStory(enter_world);
            }
        },
        
        showWorldStory: function(enter_world) {
            $('#header').css('display', 'none');
            $('#footer').css('display', 'none');
            $('#world').append($.template('world' + enter_world + '_story_tmpl'));
            var story_dom = $('#world' + enter_world + '_story_page');
            
            var nextRender = function() {
                story_dom.remove();
                story_dom = null;
                $('.bigmap' + enter_world).removeClass('shake_a');
                $('#footer').css('display', 'block');
                $('#header').css('display', 'block');
            };
            
            for(var i = 1;i < 5;i ++) {
                story_dom.find('#w' + enter_world + '_t' + i).unbind('webkitTransitionEnd').one('webkitTransitionEnd', function() {
                    $(this).next().addClass('noblur');
                });
            }
            story_dom.find('#w' + enter_world + '_t5').unbind('webkitTransitionEnd').one('webkitTransitionEnd', function() {
                setTimeout(function() {
                    story_dom.find('#w' + enter_world + '_start').unbind('webkitTransitionEnd').one('webkitAnimationEnd', function() {
                        $(this).attr('class', 'shine');
                        story_dom.unbind('touchend').one('touchend', function() {
                            nextRender();
                            nextRender = null;
                        });
                    }).addClass('shake');
                    story_dom.addClass('shake_a');
                    $('.bigmap' + enter_world).addClass('shake_a');
                }, 300);
            });
            
            xGame.doAnimate(story_dom.find('#w' + enter_world + '_text'), 'newhero_scalein', '0.5s',function() {
                if (xGame.menustatus == true) {
                    xGame.menuToggle();
                }
                story_dom.find('#w' + enter_world + '_t1').addClass('noblur');
            });
        },
        
        //显示世界的页码
        displayWorldPage: function(next_status, world_id) {

            var prevWorld = null;
            var nextWorld = null;
            var that = this;
            
            world_id = parseInt(world_id);
            nextWorld = map_ini['worldname'][world_id + 1];
            
            if (world_id > 1){
                prevWorld = map_ini['worldname'][world_id - 1];
            }
            

            if (prevWorld) {
                $('#footer').find('#guanqia_prev').text(prevWorld.name).unbind('touchend').bind('touchend',
                function(e) {
                    mapWorld.loadWorldMap(prevWorld.id);
                    $('#footer').find('#guanqia_prev').text(prevWorld.name).unbind('touchend')
                    e.preventDefault();
                    e.stopPropagation();
                }).show();
            }
            if (nextWorld) {
                if (next_status == 0) {
                    $('#footer').find('#guanqia_next').addClass('close').text(nextWorld.name).unbind('touchend').show();
                } else {
                    $('#footer').find('#guanqia_next').removeClass('close').text(nextWorld.name).unbind('touchend').bind('touchend',
                    function(e) {
                        mapWorld.loadWorldMap(nextWorld.id);
                         $('#footer').find('#guanqia_next').removeClass('close').text(nextWorld.name).unbind('touchend');
                        e.preventDefault();
                        e.stopPropagation();
                    }).show();
                }
            } 
        },
        getShiliMapImage: function(missionlevel) {
            if (missionlevel <= 6) {
                return mapAssetUrl(staticUrl + 'img/map/' + missionlevel + '.jpg');
            } else {
                return mapAssetUrl(staticUrl + 'img/map/' + missionlevel + '.jpg');
            }
        },

        getGuanqiaMapImage: function(missionlevel, missionstage) {
            if (missionlevel <= 6) {
                return mapAssetUrl(staticUrl + 'img/map/' + missionlevel + '_' + missionstage + '.jpg');
            } else {
                return mapAssetUrl(staticUrl + 'img/map/' + missionlevel + '_' + missionstage + '.jpg');
            }
        },

        getCityNameImage: function(missionlevel) {
            if (missionlevel <= 6) {
                return mapAssetUrl(staticUrl + 'img/map/guanqia_name' + missionlevel + '.png');
            } else {
                return mapAssetUrl(staticUrl + 'img/map/guanqia_name' + missionlevel + '.png');
            }
        },

        /* 第一关的NPC头像特殊处理 */
        get1NPCImage: function(missionstage, isboss, type) {
            return mapAssetUrl(staticUrl+'img/map/mapnpc_1_'+missionstage+'_'+(isboss == 1 ? 'boss' : 'enemy')+''+type+'.png');
        },

        loadShiliMap: function(missionlevel) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            if (!missionlevel) {
                missionlevel = player.info.missionlevel;
            }
            var initRender = function(res) {
                if (res.status != 1) {
                    xGame.toast.show(lang('you_have_no_shili'));
                    $('#worldloading').removeClass('black').hide();
                    xGame.unlock();
                    initRender= null;
                    return;
                }
                //更新玩家当前所在地方
                player.info.missionlevel = missionlevel;
                player.info.missionsite = 2;

                var menu_passreward = $('#header').find('#menu_newpassreward');
                if (menu_passreward) {
                    if (xGame.menustatus) {
                        menu_passreward.show();
                        if (res.popo == 1) {
                            menu_passreward.find('.popo').show();
                        } else {
                            menu_passreward.find('.popo').hide();
                        }
                    }
                }

                that.initMapPage();
                var dom_wrap = $('#map_wrap');
                dom_wrap.html($.template('mapworld_shilimap_tmpl'));
                var dom_page = dom_wrap.find('.shilimap');

                // 用 longhand 而非 background shorthand：shorthand 会把 background-size
                // 重置为 auto，宽布局下 game.css 的 100% 100% 拉伸会被内联样式覆盖
                dom_page.css('background-image', 'url(' + that.getShiliMapImage(player.info.missionlevel) + ')');
                dom_page.css('background-repeat', 'no-repeat');

                $('#footer').find('#menu_world').unbind('touchend').one('touchend',
                function(e) {
                    mapWorld.loadWorldMap();
                    e.preventDefault();
                    e.stopPropagation();
                    that = null;
                }).show();

                var lastenemy = null;
                var lastenemy_id = 0;
                var html = '';
                var shili_map_ini = map_ini['shili'][player.info.missionlevel];
                var nowstage = {};
                var style = '';

                var stageList = res.stages || (res.list ? Object.values(res.list) : []);
                for (var s = 0; s < stageList.length; s++) {
                    var stageItem = stageList[s];
                    if (!stageItem || stageItem.openstatus == 0) {
                        continue;
                    }
                    var stageIdx = stageItem.stage;
                    lastenemy = stageIdx;
                    lastenemy_id = stageItem.id;
                    //英雄集结和宋朝最后一个势力强制最后一个设置为null，为了没有箭头
                    if ((stageIdx == 73 || stageIdx == 80) && stageItem.status == 1) {
                        lastenemy = null;
                    }
                    if (shili_map_ini && shili_map_ini[stageIdx]) { //本地
                        nowstage = shili_map_ini[stageIdx];
                        html += '<div id="guanqia' + nowstage.stage + '" class="btn guanqia pictype' + nowstage.type + ' guanqia' + nowstage.stage + '" style="left:' + getSLeft(nowstage.x) + 'px;top:' + getSTop(nowstage.y) + 'px" s="' + nowstage.stage + '">';
                        html += '<div class="bg ' + (nowstage.reverse == 1 ? 'reverse': '') + '"></div>';
                        if (parseInt(player.info.missionlevel) <= 14) {
                            html += '<p style="background-image:url(' + that.getCityNameImage(player.info.missionlevel) + ');background-position-y:-' + ((nowstage.stage - 1) * 22) + 'px;"></p>';
                        }
                        html += (stageItem.status == 1 ? '<div class="win"></div>': '');
                        html += '</div>';
                    } else {
                        html += '';
                    }
                }
                shili_map_ini = null;
                nowstage = null;
                style = null;
                dom_page.append(html);

                dom_page.undelegate('.guanqia');
                dom_page.undelegate('.guanqia', 'touchstart').delegate('.guanqia', 'touchstart', function() {
                    mapWorld.touchObj = this.id;
                });
                dom_page.undelegate('.guanqia', 'click').delegate('.guanqia', 'click',
                function() {
                    if(xGame.guide == false && this.id != mapWorld.touchObj) {
                        mapWorld.touchObj = null;
                        return false;
                    }
                    mapWorld.touchObj = null;
                    var shili = $(this);
                    if (!shili.hasClass('close')) {
                        mapWorld.loadGuanqiaMap(player.info.missionlevel, shili.attr('s'));
                        shili = null;
                    } else {
                        alert('当前未开启！');
                    }
                    that = null;
                });

                /* 处理最近的一个该打的 */
                var lastenemy_dom = null;
                
                /*2013-7-17更新 真龙说打过的关卡最后一个势力不要箭头，有其他用处*/
                if (res.next_status == 1) {
                    lastenemy = null;
                }
                
                if (lastenemy) {
                    lastenemy_dom = dom_page.find('#guanqia' + lastenemy);
                    if (lastenemy_dom != null && lastenemy_dom.length > 0 && (player.info.level > 16 || xGame.guide == false)) { //最近一个加箭头
                        if (miscpop.in_array(lastenemy_id,[78, 79, 80, 81, 82, 85, 87])) {
                            lastenemy_dom.append('<span id="lastenemy_span" class="gq_ls_' + lastenemy_id + '"></span>');
                        } else {
                            lastenemy_dom.append('<span id="lastenemy_span"></span>');
                        }
                    }
                }

                $('#mapworld_page').show();
                xGame.iscrollRefresh();
                //非引导期间，底图滑动
                lastenemy_dom = null;

                /* 处理显示上下关卡 */
                if (!xGame.xsGet(player.info.uid + 'guide_hide_page', true)) {
                    that.displayShiliPage(res.next_status);
                }
                //显示当前位置
                if (map_ini['world'][missionlevel].name) {
                    $('#world').append('<p class="nowplace">' + map_ini['world'][missionlevel].name + '</p>')
                }

                that.resultDisplay();

                xGame.unlock();
                html = null;
                dom_wrap = null;
                dom_page = null;
                initRender = null;

                //新手引导判断
                if (xGame.guide == true) {
                    if (guide.now == 0) {
                        guide.showNpc(lang('welcome_msg'), function() {
                            guide.showNpc(lang('liaohua_msg'), function() {
                                guide.show(1);
                            }, 2);
                        });
                    } else if (guide.now == 911) {
                        guide.show(601);
                    } else if (guide.now == 931) {
                        guide.show(1324);
                    } else {
                        xGame.showGuide([1613]);
                    }
                }
                
                //2013-7-22 如果是刚击败张角，掉宝箱，弹提示
                if (xGame.guide == true && guide.now == 1803) {
                    var newguanqiaid = xGame.xsGet(player.info.uid + 'shilidone', true);
                    if (newguanqiaid) {
                        var passShiliFunc = function() {
                            /* 宝箱 */
                            miscpop.passShiliReward(newguanqiaid,
                            function() {
                                guide.show(1804);
                            });
                            xGame.xsRemove(player.info.uid + 'shilidone', true);
                        };
                        //if(xGame.xsExist('tryuid')) {//未绑定
                            //miscpop.bindPopConfirm(passShiliFunc);
                        //} else {
                            passShiliFunc();
                        //}
                    }
                }

                // 引导续接
                if (guide.step) {
                    guide.show(guide.step);
                    guide.step = 0;
                }
            };
            
            loadContents({
                'c': 'map',
                'm': 'get_scene_list',
                'l': missionlevel
            },
            initRender, false,
            function() {
                xGame.loadWorld('city');
            });
            initRender = null;

        },

        loadGuanqiaMap: function(missionlevel, missionstage) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            if (missionlevel == null) {
                missionlevel = player.info.missionlevel;
            }
            if (missionstage == null) {
                missionstage = player.info.missionstage;
            }

            var initRender = function(res) {
                if (res.status != '1') {
                    xGame.toast.show(lang('have_not_open_checkpoint'));
                    xGame.unlock();
                    return false;
                }
                var menu_passreward = $('#header').find('#menu_newpassreward');
                if (menu_passreward) {
                    if (xGame.menustatus) {
                        if (res.popo == 1) {
                            menu_passreward.find('.popo').show();
                        } else {
                            menu_passreward.find('.popo').hide();
                        }
                        menu_passreward.show();
                    }
                }

                if (missionlevel && missionstage) {
                    player.info.missionlevel = missionlevel;
                    player.info.missionstage = missionstage;
                    player.info.missionsite = 3;
                }

                that.initMapPage();
                var dom_wrap = $('#map_wrap');
                dom_wrap.html($.template('mapworld_guanqiamap_tmpl'));
                var dom_page = dom_wrap.find('.guanqiamap');

                dom_page.css('background-image', 'url(' + that.getGuanqiaMapImage(player.info.missionlevel, player.info.missionstage) + ')');

                $('#footer').find('#menu_back').unbind('touchend').one('touchend', function(e) {
                    mapWorld.loadShiliMap(player.info.missionlevel);
                    e.preventDefault();
                    e.stopPropagation();
                    that = null;
                }).show();
                
                var lastenemy = null;
                var html = '';
                for (var i in res.list) {
                    if (res.list[i].openstatus == 0) { //4-3-3的显示
                        if (lastenemy <= 4 && res.list[i].missionid <= 4) {
                            html += that.buildMission(res.list[i], (player.info.missionlevel == 1 ? true : false));
                        } else if (lastenemy > 4 && lastenemy <= 7 && res.list[i].missionid > 4 && res.list[i].missionid <= 7) {
                            html += that.buildMission(res.list[i], (player.info.missionlevel == 1 ? true : false));
                        } else if (lastenemy > 7 && res.list[i].missionid > 7) {
                            html += that.buildMission(res.list[i], (player.info.missionlevel == 1 ? true : false));
                        }
                        continue;
                    }
                    html += that.buildMission(res.list[i], (player.info.missionlevel == 1 ? true : false));
                    if (res.list[i].status != 1) {
                        lastenemy = res.list[i].missionid;
                    }
                }
                dom_page.append(html);

                // 打张梁失败
                if (xGame.guide == false && player.info.missionlevel == 1 && player.info.missionstage == 2 && res.list[20].openstatus == 1 && res.list[20].status == 0) {
                    xGame.xsSet(player.info.uid + 'gozhangliang', 1, true);
                }
                
                dom_page.undelegate('.enemy');
                dom_page.undelegate('.enemy', 'touchstart').delegate('.enemy', 'touchstart', function() {
                    mapWorld.touchObj = this.id;
                });

                dom_page.undelegate('.enemy', 'click').delegate('.enemy', 'click', function() {
                    if(xGame.guide == false && this.id != mapWorld.touchObj) {
                        mapWorld.touchObj = null;
                        return false;
                    }
                    mapWorld.touchObj = null;
                
                    var enemy = $(this);
                    miscpop.enemyInfo(enemy.attr('missionid'), enemy.attr('openstatus'));
                    enemy = null;

                });

                $('#mapworld_page').show();
                xGame.iscrollRefresh();

                /* 处理显示上下关卡 */
                if (!xGame.xsGet(player.info.uid + 'guide_hide_page', true)) {
                    that.displayGuanqiaPage(res.next_status);
                }
                //显示当前位置
                if (map_ini['shili'][missionlevel][missionstage] && map_ini['shili'][missionlevel][missionstage].name) {
                    $('#world').append('<p class="nowplace">' + map_ini['shili'][missionlevel][missionstage].name + '</p>')
                }

                if (xGame.guide == false) {
                    that.resultDisplay();
                }
                
                /* 处理最近的一个该打的 */
                var lastenemy_dom = null;
                if (lastenemy) {
                    lastenemy_dom = dom_page.find('#mission' + lastenemy);
                }

                var newCommerFunc = function() {
                    // 移动后引导续接
                    if (guide.step) {
                        guide.show(guide.step);
                        guide.step = 0;
                    }
                    //新手引导判断
                    //地图上选择小兵
                    xGame.showGuide([0, 1, 4, 7, 103, 106, 109, 203, 206, 209, 303, 601, 604, 607, 614, 617, 703, 706, 709, 712, 715]);
                };
                if (lastenemy_dom != null && lastenemy_dom.length > 0 && (player.info.level > 16 || xGame.guide == false)) { //最近一个加箭头
                    lastenemy_dom.append('<span id="lastenemy_span"></span>');
                }

                // 是否有升级
                var islevelup = xGame.xsGet(player.info.uid + 'levelup', true);
                if (islevelup) {
                    xGame.xsRemove(player.info.uid + 'levelup', true);
                    miscpop.createLevelUp(function () {
                        
                        if (lastenemy_dom != null && lastenemy_dom.length > 0) { //处理角色被挡
                            var lastenemy_offset = lastenemy_dom.offset();
                            if (lastenemy_offset.top > 416) { //移动
                                guide.move2bottom(newCommerFunc);
                            } else {
                                newCommerFunc();
                            }
                            lastenemy_offset = null;
                            lastenemy_dom = null;
                            newCommerFunc = null; //新手引导判断函数
                        } else {
                            // 引导续接
                            if (guide.step) {
                                guide.show(guide.step);
                                guide.step = 0;
                            }
                        }

                        xGame.showGuide([306, 1422]); // 击败周仓，和20级后继续征战

                        switch (parseInt(player.info.level)) {
                            case 20:
                                guide.show(1701);
                                break;
                            case 30:
                                guide.show(1501);
                                break;
                            case 31:
                                miscpop.showComment();
                                break;
                            case 40:
                                guide.show(1504);
                                break;
                            case 50:
                                if (player.info.paychecks == 1) {
                                    miscpop.showNewinvitecode();
                                } else {
                                    miscpop.showInvitecode();
                                }
                                break;
                            default :
                                break;
                        }

                        mapWorld.resultDisplay();
                    });
                } else {
                    mapWorld.resultDisplay();

                    if (lastenemy_dom != null && lastenemy_dom.length > 0) { //处理角色被挡
                        var lastenemy_offset = lastenemy_dom.offset();
                        if (lastenemy_offset.top > 416) { //移动
                            guide.move2bottom(newCommerFunc);
                        } else {
                            newCommerFunc();
                        }
                        lastenemy_offset = null;
                        lastenemy_dom = null;
                        newCommerFunc = null; //新手引导判断函数
                    } else {
                        // 引导续接
                        if (guide.step) {
                            guide.show(guide.step);
                            guide.step = 0;
                        }
                    }

                    xGame.showGuide([306, 1324, 1422]); // 击败周仓，和20级后继续征战

                    // 第一次打张梁失败
                    if (xGame.guide && guide.now == 722) {
                        guide.show(1409);
                    }
                }

                xGame.unlock();
                html = null;
                dom_wrap = null;
                dom_page = null;
                initRender = null;

            };

            loadContents({
                'c': 'map',
                'm': 'get_mission_list',
                'l': missionlevel,
                's': missionstage
            },
            initRender, false,
            function() {
                xGame.loadWorld('city');
            });
            initRender = null;
        },

        buildMission: function(data, firstShili) {
            if(firstShili == true) {
                var isboss = 1;
                var npctype = 1;
                if(data.type == 1 || data.type == 2) {//判断BOSS
                    isboss = 0;
                }
                if(data.status == 1) {//判断是否哭
                    npctype = 2;
                } else if(data.openstatus == 0) {
                    npctype = 3;
                }
                var imgurl = this.get1NPCImage(player.info.missionstage, isboss, npctype);

                var html = '<div id="mission' + data.missionid + '" class="btn enemy solderenemy" missionid="' + data.missionid + '" openstatus="' + data.openstatus + '" style="left:' + getSLeft(data.x) + 'px;top:' + getSTop(data.y) + 'px;background-image:url(' + imgurl + ');">' + (data.name == '' ? '': '<p>' + data.name + '</p>') + '</div>';
                return html;
            } else {
                var enemyclass = '';
                if (data.type == 1 || data.type == 2) {
                    enemyclass = 'solderenemy';
                } else {
                    enemyclass = 'bossenemy';
                }
                if (data.status == 1) {
                    enemyclass += ' cry';
                } else if (data.openstatus == 0) {
                    enemyclass += ' close';
                }

                var html = '<div id="mission' + data.missionid + '" class="btn enemy ' + enemyclass + '" missionid="' + data.missionid + '" openstatus="' + data.openstatus + '" style="left:' + getSLeft(data.x) + 'px;top:' + getSTop(data.y) + 'px">' + (data.name == '' ? '': '<p>' + data.name + '</p>') + '</div>';
                return html; 
            }
        },

        /* 战斗结果处理 */
        resultDisplay: function() {
            var that = this;
            //是否失败
            var islose = xGame.xsGet(player.info.uid + 'islose', true);
            if (islose) {

                var zhanglianglose = xGame.xsGet(player.info.uid + 'zhanglianglose', true);

                if (zhanglianglose) {
                    xGame.xsRemove(player.info.uid + 'islose', true);
                    xGame.xsRemove(player.info.uid + 'zhanglianglose', true);
                    guide.show(1901);
                    return false;
                }

                // 如果第一次打张梁失败
                if (xGame.guide && guide.now == 722) {
                    xGame.xsRemove(player.info.uid + 'islose', true);
                    xGame.xsRemove('lastBattle', true);
                    return false;
                }

                var losepage = xGame.loadPopTmpl('loseguide');
                losepage.find('#hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    losepage.undelegate('.losepop_btns', 'touchend');
                    losepage.undelegate('.retry', 'touchend');
                    losepage.find('#hidebtn').unbind('touchend');
                    e.preventDefault();
                    setTimeout(function() {
                        xGame.xsRemove('lastBattle', true);
                        losepage.remove();
                        losepage = null;
                    },
                    300);
                });
                losepage.undelegate('.losepop_btns', 'touchend').delegate('.losepop_btns', 'touchend',
                function(e) {
                    var gopop = $(this).attr('pop');
                    var level = parseInt($(this).attr('level'));

                    if (player.info.level < level) {
                        xGame.toast.show(level + lang('lv_open'));
                    } else {
                        if (gopop) {
                            xGame.showpop(gopop);
                        }
                    }
                    e.preventDefault();
                });
                
                losepage.undelegate('.retry', 'touchend').delegate('.retry', 'touchend',
                function() {
                    losepage.undelegate('.losepop_btns', 'touchend');
                    losepage.find('#hidebtn').unbind('touchend');
                    losepage.undelegate('.retry', 'touchend');

                    var data = xGame.xsGet('lastBattle', true);
                    var arr = data.split(',');
                    data = {'l':arr[0],'s':arr[1],'id':arr[2]};
                    losepage.remove();
                    losepage = null;
                    miscpop.enterBattle(data);
                });

                // 40级前战斗失败引导玩家点击强化按钮
                if (xGame.guide == false && player.info.level <= 40) {
                    guide.create('touchend', $('#loseguidepop .strong'), true);
                    losepage.bind('touchend', function () {
                        guide.hide();
                    });
                }

                losepage.show();
                /*var html = $.template('loseguide_tmpl');
                xGame.confirm.show(lang('dabuguo'), html, 1, function() {
                        xGame.confirm.hide(function() {
                            mapWorld.resultDisplay();
                        });
                    }
                );*/
                xGame.xsRemove(player.info.uid + 'islose', true);
                return;
            }
            //是否获得道具
            var isgetitem = xGame.xsGet(player.info.uid + 'getitem', true);
            if (isgetitem) {
                miscpop.itemfly2bag(JSON.parse(isgetitem),
                function() {
                    mapWorld.resultDisplay();
                });
                xGame.xsRemove(player.info.uid + 'getitem', true);
                return;
            }
            
            /* generalinfo = {status:1,generalid:12,name:"吕蒙", image:12} */
            //是否有招募到了武将
            var general = xGame.xsGet(player.info.uid + 'getgeneral', true);
            if (general) {
                var generalinfo = JSON.parse(general);

                if (xGame.guide) {
                    if (parseInt(generalinfo.generalid) == 3 || parseInt(generalinfo.generalid) == 4 || parseInt(generalinfo.generalid) == 5) {
                        miscpop.newhero(generalinfo,
                        function() {
                            /* 新手引导 */
                            if (parseInt(generalinfo.generalid) == 3) { //张梁
                                xGame.showGuide([725]);
                            } else if (parseInt(generalinfo.generalid) == 4) { //张宝

                                var noInGuide = xGame.xsGet(player.info.uid + 'no_in_guide', true);

                                // 不在引导期间不弹引导
                                if (!noInGuide) {
                                    var guideErr = xGame.xsGet(player.info.uid + 'guide_err', true);
                                    
                                    if (!guideErr) {
                                        guide.show(1601);
                                    } else {    // 如果玩家在20级引导后没有继续征战，而是进入了点将台界面，则在招募张梁后不进行引导
                                        guide.now = 1614;
                                        xGame.xsRemove(player.info.uid + 'guide_err', true);
                                    }
                                }

                            } else if (parseInt(generalinfo.generalid) == 5) { //张角
                                var noInGuide = xGame.xsGet(player.info.uid + 'no_in_guide', true);

                                if (!noInGuide) {
                                    guide.show(1801);
                                }
                            }
                        });

                        xGame.toast.show(lang('zhaomudao_xx', [generalinfo.name]));
                        xGame.xsRemove(player.info.uid + 'getgeneral', true);
                        return;
                    }
                    
                } else {
                    miscpop.newhero(generalinfo,
                    function() {
                        mapWorld.resultDisplay();
                    });

                    xGame.toast.show(lang('zhaomudao_xx', [generalinfo.name]));
                    xGame.xsRemove(player.info.uid + 'getgeneral', true);
                    return;
                }
            }
            //是否开启新关卡
            var newguanqia = xGame.xsGet(player.info.uid + 'guanqiadone', true);
            if (newguanqia) {
                xGame.confirm.show(lang('next_checkpoint'), lang('open_checkpoint'), 1,
                function() {
                    xGame.confirm.hide(function() {
                        mapWorld.loadShiliMap(newguanqia);
                        //guide.point($('#map_wrap').find('.guanqia'+player.info.missionstage), null, true);
                    });
                });
                xGame.xsRemove(player.info.uid + 'guanqiadone', true);
                return;
            }
            //是否开启新势力
            var newguanqiaid = xGame.xsGet(player.info.uid + 'shilidone', true);
            if (newguanqiaid) {
                var passShiliFunc = function() {
                    //2013-7-22 当击败张角时，不显示这个弹窗
                    if (xGame.guide == true && guide.now == 1803) {
                        return false;
                    }
                    var isAlldone = xGame.xsGet(player.info.uid + 'shilialldone');
                    if(isAlldone == 'solo') {
                        miscpop.passShiliReward(newguanqiaid, null, true);
                    } else {
                    /* 宝箱 */
                    var str = '';
                    if (newguanqiaid == 14) {
                        str = lang('mapworld_newworld');
                    } else {
                        str = lang('open_shili');
                    }
                    miscpop.passShiliReward(newguanqiaid,
                    function() {
                        xGame.confirm.show(lang('new_shili'), str, 1,
                        function() {
                                xGame.confirm.hide(function() {
                                    mapWorld.loadWorldMap();
                                });
                            });
                        });
                    }
                    xGame.xsRemove(player.info.uid + 'shilidone', true);
                };
                //if(xGame.xsExist('tryuid')) {//未绑定
                    //miscpop.bindPopConfirm(passShiliFunc);
                    //return false;
                //} else {
                    passShiliFunc();
                //}
                return;
            }

        },

        displayGuanqiaPage: function(next_status) {
            var prevGuanqia = null,
            nextGuanqia = null;
            var that = this;
            player.info.missionstage = parseInt(player.info.missionstage);
            var shiliCfg = map_ini['shili'] && map_ini['shili'][player.info.missionlevel];
            if (player.info.missionstage > 1 && shiliCfg) {
                var k1 = player.info.missionstage - 1;
                prevGuanqia = shiliCfg[k1];
            }
            if (shiliCfg) {
                var k2 = player.info.missionstage + 1;
                nextGuanqia = shiliCfg[k2];
            }

            if (prevGuanqia) {
                var prevStage = prevGuanqia.stage;
                var prevName = prevGuanqia.name;
                $('#footer').find('#guanqia_prev').unbind('touchend').bind('touchend', function(e) {
                    mapWorld.loadGuanqiaMap(player.info.missionlevel, prevStage);
                    e.preventDefault();
                    e.stopPropagation();
                }).text(prevName).show();
            }
            if (nextGuanqia) {
                var nextStage = nextGuanqia.stage;
                var nextName = nextGuanqia.name;
                if (parseInt(next_status) == 0) {
                    $('#footer').find('#guanqia_next').addClass('close').unbind('touchend').text(nextName).show();
                } else {
 
                    $('#footer').find('#guanqia_next').removeClass('close').unbind('touchend').bind('touchend', function(e) {
                        mapWorld.loadGuanqiaMap(player.info.missionlevel, nextStage);
                        e.preventDefault();
                        e.stopPropagation();
                    }).text(nextName).show();
                }
            } else {

                $('#footer').find('#guanqia_next').removeClass('close').unbind('touchend').bind('touchend', function(e) {
                    mapWorld.loadShiliMap(player.info.missionlevel);
                    e.preventDefault();
                    e.stopPropagation();
                }).text(lang('return_shili')).show();
            }
        },
                }).text(lang('back')).show();
            }
        },

        displayShiliPage: function(next_status) {

            var prevShili = null,
            nextShili = null;
            var that = this;
            player.info.missionlevel = parseInt(player.info.missionlevel);
            if (player.info.missionlevel > 1) {
                //暂时前端写死,南宋势力不显示英雄集结
                if (player.info.missionlevel != 15) {
                    var k1 = player.info.missionlevel - 1;
                    prevShili = map_ini['world'][k1];
                }
            }
            //暂时前端写死,英雄集结不显示宋朝势力,宋朝势力不显示接下来的势力
            //2014-06-04 添加新势力隋唐，宋朝要显隋唐势力，隋唐不显示接下来的势力
            if (player.info.missionlevel != 14 && player.info.missionlevel != 16) {
                var k2 = player.info.missionlevel + 1;
                nextShili = map_ini['world'][k2];
            }
            if (prevShili) {
                $('#footer').find('#guanqia_prev').text(prevShili.name).unbind('touchend').bind('touchend',
                function(e) {
                    mapWorld.loadShiliMap(prevShili.id);
                    e.preventDefault();
                    e.stopPropagation();
                }).show();
            }
            if (nextShili) {
                if (parseInt(next_status) == 0) {
                    $('#footer').find('#guanqia_next').addClass('close').text(nextShili.name).unbind('touchend').show();
                } else {
                    $('#footer').find('#guanqia_next').removeClass('close').text(nextShili.name).unbind('touchend').bind('touchend',
                    function(e) {
                        mapWorld.loadShiliMap(nextShili.id);
                        e.preventDefault();
                        e.stopPropagation();
                    }).show();
                }
            } else {
                $('#footer').find('#guanqia_next').removeClass('close').text(lang('back')).unbind('touchend').bind('touchend',
                function(e) {
                    mapWorld.loadWorldMap();
                    e.preventDefault();
                    e.stopPropagation();
                }).show();
            }

        },

        clear: function() {
            xGame.unlock();
            this.touchObj = null;
            xGame.clearCloud();
            $('#footer').find('#menu_world').hide();
            $('#footer').find('#menu_back').hide();
            $('#footer').find('.guanqia_page_btn').hide();
            this.first = null;
        }
    };
})();