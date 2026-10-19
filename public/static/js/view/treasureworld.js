/* 地图 */
(function() {
    treasureWorldClass = function() {
        this.touchObj;
    };
    treasureWorldClass.prototype = {
        init: function() {

            this.loadGuanqiaMap();

        },

        initMapPage: function() {
            var that = this;

            $('#header').show();

            var footer = $('#footer');
            footer.find('#menu_map').hide();
            footer.find('#menu_city').show();
            footer.show();

            var dom_world = $('#scroller');
            /* 从其他地方进入地图，需要生成容器dom */
            if (dom_world.find('#mapworld_page').length == 0) {
                dom_world.empty().append($.template('mapworld_tmpl')); //默认是隐藏状态
                //xGame.createCloud();
                $('#worldloading').removeClass('black').hide();
                /* 进地图显示当前位置 */
                $('#world').undelegate('.nowplace', 'webkitAnimationEnd').delegate('.nowplace', 'webkitAnimationEnd',
                function() {
                    $(this).unbind('webkitAnimationEnd');
                    $(this).remove();
                });
            } else {
                $('#worldloading').removeClass('black').hide();
            }

            dom_world = null;
            that = null;
        },

        loadGuanqiaMap: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var initRender = function(res) {
                if (res.status == '-1') {
                    xGame.toast.show(lang('lv_36'));
                    xGame.unlock();
                    xGame.loadWorld('city');
                    return false;
                } else if (res.status != '1') {
                    xGame.toast.show(lang('enter_treasure_fail') + res.status);
                    xGame.unlock();
                    xGame.loadWorld('city');
                    return false;
                }

                that.initMapPage();
                var dom_wrap = $('#map_wrap');
                dom_wrap.html($.template('mapworld_guanqiamap_tmpl'));
                var dom_page = dom_wrap.find('.guanqiamap');

                dom_page.css('background-image', 'url(' + staticUrl + 'img/map/treasure_bg.jpg)');

                var lastenemy = 0;
                var html = '';
                for (var i in res.list) {
                    if (res.list[i].openstatus == 0) { //4-3-3的显示
                        continue;
                    }
                    html += that.buildMission(res.list[i]);
                    if (res.list[i].killed != 1) {
                        lastenemy = res.list[i].id;
                    }
                }
                dom_page.append(html);
                
                dom_page.undelegate('.enemy', 'touchstart').delegate('.enemy', 'touchstart', function() {
                    treasureWorld.touchObj = this.id;
                });
                dom_page.undelegate('.enemy', 'click').delegate('.enemy', 'click',
                function(e) {
                    var shili = $(this);
                    if(this.id != treasureWorld.touchObj) {
                        //console.log('目标错误');
                        treasureWorld.touchObj = null;
                        return false;
                    }
                    treasureWorld.touchObj = null;
                
                    var enemy = $(this);
                    /*
                    if(enemy.attr('openstatus') == '0') {
                        xGame.toast.show('您还不能攻击此将，请先打败前面的敌人！');
                        xGame.unlock();
                        return false;
                    }
                    */
                    miscpop.show({
                        'treasureEnemyInfo': enemy.attr('missionid')
                    }); //弹窗
                    enemy = null;

                });

                $('#mapworld_page').show();
                xGame.iscrollRefresh();

                //显示当前位置
                $('#world').append('<p class="nowplace">' + lang('treasureworld') + '</p>');

                /* 处理最近的一个该打的 */
                var lastenemy_dom = null;
                if (lastenemy) {
                    lastenemy_dom = dom_page.find('#mission' + lastenemy);
                    if (lastenemy_dom != null && lastenemy_dom.length > 0) { //最近一个加箭头
                        lastenemy_dom.append('<span id="lastenemy_span"></span>');
                        lastenemy_dom = null;
                    }
                }

                if (lastenemy_dom != null && lastenemy_dom.length > 0) { //处理角色被挡
                    var lastenemy_offset = lastenemy_dom.offset();
                    if (lastenemy_offset.top > 416) { //移动
                        guide.move2bottom();
                    }
                    lastenemy_offset = null;
                    lastenemy_dom = null;
                }

                xGame.unlock();
                html = null;
                dom_wrap = null;
                dom_page = null;
                initRender = null;
                that = null;

            };
            //initRender({status:1,list:{1:{id:1,name:"怪物1",x:314,y:500,background:1,killed:0,openstatus:1},2:{id:2,name:"怪物2",x:460,y:89,background:1,killed:0,openstatus:1},3:{id:3,name:"怪物3",x:358,y:402,background:1,killed:0,openstatus:1},4:{id:4,name:"怪物4",x:546,y:380,background:1,killed:0,openstatus:0},}});
            loadContents({
                'c': 'island',
                'm': 'index'
            },
            initRender);
            initRender = null;

        },

        buildMission: function(data) {
            var enemyclass = '';

            if (data.type == 1 || data.type == 2) {
                enemyclass = 'solderenemy';
            } else {
                enemyclass = 'bossenemy';
            }
            if (data.killed == 1) {
                enemyclass += ' cry';
            }
            var html = '<div id="mission' + data.id + '" class="btn enemy ' + enemyclass + '" missionid="' + data.id + '" openstatus="' + data.openstatus + '" style="left:' + getSLeft(data.x) + 'px;top:' + getSTop(data.y) + 'px">' + (data.name == '' ? '': '<p>' + data.name + '</p>') + '</div>';
            return html;
        },

        unbindAll: function () {
            var dom_page = $('#map_wrap').find('.guanqiamap');
            if (dom_page) {
                dom_page.undelegate('.enemy', 'touchstart');
                dom_page.undelegate('.enemy', 'click');
            }
        },

        clear: function() {
            xGame.unlock();
            this.unbindAll();
            this.touchObj = null;
        },
    };
})();