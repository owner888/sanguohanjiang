/* 国战地图 */
(function() {
    countrymapWorldClass = function() {
        this.anim;//简单的小动画
        this.touchObj;
    };
    countrymapWorldClass.prototype = {
        init: function() {

            this.loadMap();
            this.anim = null;
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
                dom_world.html($.template('mapworld_tmpl')); //默认是隐藏状态
                //xGame.createCloud();
                $('#worldloading').removeClass('black').hide();
                /* 进地图显示当前位置 */
                $('#world').undelegate('.nowplace', 'webkitAnimationEnd').delegate('.nowplace', 'webkitAnimationEnd',
                function() {
                    $(this).unbind('webkitAnimationEnd');
                    $(this).remove();
                });
            }

            dom_world = null;
            that = null;
        },

        loadMap: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var initRender = function(res) {
                if (res.status == '-1') {
                    xGame.toast.show(lang('no_country_to_map'));
                    xGame.unlock();
                    xGame.loadWorld('city');
                    return false;
                }if (res.status == '3') {
                    xGame.confirm.show('<b style="color:#724100;">' + lang('notice') + '</b>', lang('country_battle_leave_dnot_enter'), 1,
                    function() {
                        xGame.confirm.hide(function() {
                            xGame.updateCd('leavecountry', 0);
                        });
                    });
                    xGame.updateCd('leavecountry', res.cd);
                    xGame.unlock();
                    xGame.loadWorld('country');
                    return false;
                } else if (res.status != 1) {
                    xGame.toast.show(lang('can_not_to_country_map'));
                    xGame.unlock();
                    xGame.loadWorld('city');
                    return false;
                }

                that.initMapPage();
                var dom_wrap = $('#map_wrap');
                dom_wrap.html($.template('mapworld_countrymap_tmpl'));
                var dom_page = dom_wrap.find('.countrymap');

                var lastenemy = null;
                var html = '';
                var flag = '';
                for (var i = 0; i < res.list.length; i++) {
                    var _city = res.list[i];
                    if (_city.occupy == 1) {
                        flag = '<span>' + _city.occupy_name + '</span>';
                    } else if (_city.occupy == 2) {
                        flag = '<em class="atking c1"></em>';
                    } else {
                        flag = '';
                    }
                    html += '<div id="worldshili' + _city.id + '" class="btn shili ' + (_city.size == 1 ? '' : 'small ') + (_city.open == 1 ? '': 'closed') + '" style="left:' + getSLeft(map_ini['cbattle_world'][_city.id].x) + 'px;top:' + getSTop(map_ini['cbattle_world'][_city.id].y) + 'px" l="' + _city.id + '"><p class="l' + _city.id + '"></p>' + flag + '</div>';

                }
                html += '<div class="btn shili" id="countrywarnotice"></div>';
                dom_page.append(html);
                that.animStart();
                dom_page.undelegate('.shili', 'touchstart').delegate('.shili', 'touchstart', function() {
                    countrymapWorld.touchObj = this.id;
                });
                dom_page.undelegate('.shili', 'click').delegate('.shili', 'click',
                function(e) {
                    var shili = $(this);
                    if($(this).attr('id') == 'countrywarnotice') {//国战战况战报
                        xGame.showpop('countrywarnotice');
                    } else {
                        if(this.id != countrymapWorld.touchObj) {
                            //console.log('目标错误');
                            countrymapWorld.touchObj = null;
                            return false;
                        }
                        countrymapWorld.touchObj = null;
                        if (!shili.hasClass('close')) {
                            miscpop.countrymapCityInfo(shili.attr('l'));
                            shili = null;
                        } else {
                            alert(lang('current_colse'));
                        }
                    }
                });

                $('#mapworld_page').show();
                xGame.iscrollRefresh();

                xGame.unlock();
                html = null;
                dom_wrap = null;
                dom_page = null;
                initRender = null;
                that = null;

            };
            loadContents({
                'c': 'siege',
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

        animStart: function() {
            var obj = $('#map_wrap').find('.atking');
            //console.log(obj);
            if (obj.length == 0) {
                return false;
            }
            this.clearAnim();
            var t = 1;

            this.anim = setInterval(function() {
                t ++;
                t = t > 4 ? 1 : t;
                obj.attr('class','atking c' + t);
            },500);
        },

        clearAnim: function() {
            if (this.anim != null) {
                clearInterval(this.anim);
            }
            this.anim = null;
        },

        unbindAll: function () {
            var dom_page = $('#map_wrap').find('.countrymap');
            if (dom_page) {
                dom_page.undelegate('.shili', 'touchstart');
                dom_page.undelegate('.shili', 'click');
            }
        },
        
        clear : function() {
            xGame.unlock();
            this.touchObj = null;
            this.unbindAll();
            this.clearAnim();
            if (cityWorld) {
                cityWorld.unlock();
            }
        }
    };
})();