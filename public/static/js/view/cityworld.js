/* 主城 */
(function() {
    cityWorldClass = function() {
        this.allTop; //小人总开关
        this.movieclip1; //扎木头的小人
        this.movieclip2; //走路的小人
        this.islock = true;
        this.e = null;
        this.touchObj = null;
    };
    cityWorldClass.prototype = {
        init: function() {

            this.lock();

            var dom_world = $('#scroller');
            dom_world.html($.template('cityworld_tmpl'));

            this.e = $('#cityworld_page');

            //判断是否可收取
            if (player.info.ongeneral == 1) {
                this.e.find('#muster_building').children('.popo').show();
            }

            if (player.info.level >= 11) { //校场、布阵
                this.e.find('#practice_building').addClass('show');
            }

            if (player.info.level >= 26) { //矿战 演武场 继承塔
                this.e.find('#inherit_building').addClass('show');
                this.e.find('#mine_building').addClass('show');
                //判断是否可收取
                if (player.info.onmine == 1) {
                    this.e.find('#mine_building').children('.popo').show();
                }
            }
            if (player.info.level >= 31) { //贸易中心
                this.e.find('#trade_building').addClass('show');
            }
            
            if (player.info.level >= 31 && player.info.level < 150) { //酒馆
                this.e.find('#bar_building').addClass('show');
            }
            
            if(channelID == 153 || channelID == 154) {//韩文版日文版暂时不开放跨服
                if (player.info.level >= 26) {
                    this.e.find('#arena_building').addClass('show');
                }
            } else {
                if (player.info.level >= 26 && player.info.level < 61) {
                    this.e.find('#arena_building').addClass('show');
                }
                if(player.info.level >= 60) {
                    this.e.find('#war_building').addClass('show');

                    if (player.info.war_entry == 1) {
                        this.e.find('#war_building').children('.popo').show();
                    } else if(player.info.war_entry == 2) {
                        this.e.find('#war_building').children('.fighting').show();
                    }
                }
                if (player.info.level >= 61) { //跨服
                    this.e.find('#arena_building').hide();
                    this.e.find('#worldarena_building').show();
                    this.e.find('#worldarena_building').addClass('show');
                }
            }

            if (player.info.level >= 60) {
                this.e.find('#beauty_building').addClass('show');
            }
            
            if (player.info.level >= 81) { //名将图鉴
                this.e.find('#heromanual_building').addClass('show');
            }
            
            /*60级开启战神殿*/
            if (player.info.god == 1) { //战神殿
                this.e.find('#levy_building').hide();
                this.e.find('#champions_building').show();
                this.e.find('#champions_building').addClass('show');
                if (player.info.god_entry == 1) {
                    this.e.find('#champions_building').children('.popo').show();
                } else if(player.info.god_entry == 2) {
                    this.e.find('#champions_building').children('.fighting').show();
                }
            } else {
                if (player.info.level >= 16) {
                    this.e.find('#levy_building').addClass('show');
                }
            }
            
            
            if (player.info.level >= 91) { //玉石作坊
                this.e.find('#workshop_building').addClass('show');
                //判断是否可收取
                if (player.info.onworkshop == 1) {
                    this.e.find('#workshop_building').children('.popo').show();
                }
            }
            
            if (player.info.level >= 150) { //名将祭坛
                this.e.find('#bar_building').hide();
                this.e.find('#newbar_building').show();
                this.e.find('#newbar_building').addClass('show');
            }
            
            var that = this;
            this.e.undelegate('.building.show', 'touchstart').delegate('.building.show', 'touchstart', function() {
                that.touchObj = this.id;
            });
            this.e.undelegate('.building.show', 'click').delegate('.building.show', 'click', function() {
                var pop = this.id;
                if(xGame.guide == false && pop != that.touchObj) {
                    //console.log('目标错误');
                    that.touchObj = null;
                    return false;
                }
                that.touchObj = null;
                if (pop == 'mine_building') {
                    xGame.loadWorld('mine');
                } else if (pop == 'champions_building') {
                    xGame.loadWorld('champions');
                } else if (pop == 'newbar_building') {
                    xGame.showpop('bar');
                } else if (pop == 'war_building') {
                    xGame.loadWorld('war');
                } else {
                    if (cityWorld.islock == true) {
                        return false;
                    }
                    xGame.showpop(pop.replace('_building', ''));
                }
                if ($(this).find('.tag').length > 0) {
                    if (player.info.edifice[$(this).attr('index')] != undefined && player.info.edifice[$(this).attr('index')].name == $(this).attr('name')) {
                        player.info.edifice[$(this).attr('index')] = 0;
                    }
                    $(this).find('.tag').remove();
                }
                
            });

            if (player.info.level <= 20) {
                $('#footer').find('#menu_map').addClass('tada').unbind('touchend').one('touchend',
                function() {
                    $(this).removeClass('tada');
                });
            }

            this.e.find('#citymask').unbind('touchend').bind('touchend',
            function() {
                cityWorld.unlockProcess();
            });

            xGame.iscrollRefresh();

            this.unlock();

            xGame.setMenuList2('city');
   
            this.createNew();
            $('#worldloading').removeClass('black').hide();
            this.littleStart();

            // 引导续接
            if (guide.step) {
                guide.show(guide.step);
                guide.step = 0;
            }

            // 苹果审核期间屏蔽部分功能
            player.hidePopInAppreview();
        },

        littleXX: function() {

            var that = this;

            var obj = $('#practice_building');
            var cfg = {
                x: 45,
                y: 55
            };
            if (!obj || obj.length === 0) {
                obj = $('#cityworld_page');
                cfg.x = 744;
                cfg.y = 275;
                if(typeof isIPhone5 != 'undefined' && isIPhone5 == true) {
                    cfg.x = 813;
                    cfg.y = 300;
                }
            }
            that.movieclip1 = new movieClipClass({
                "img": staticUrl + 'img/city/npc3.png',
                "width": 103,
                "height": 68,
                "x": cfg.x,
                "y": cfg.y,
                "loop": true,
                'zindex': '1',
                "frames": [{
                    rect: [0, 0],
                    interval: 200
                },
                {
                    rect: [103, 0],
                    interval: 150
                },
                {
                    rect: [206, 0],
                    interval: 150
                },
                {
                    rect: [309, 0],
                    interval: 200
                },
                {
                    rect: [412, 0],
                    interval: 150
                }]
            });
            that.movieclip1.appendTo(obj);
            that.movieclip1.play();
            obj = that = null;

        },

        littleRun: function() {
            return false; // 暂时屏蔽走动的小人
            var that = this;
            var obj = $('#cityworld_page');
            var movieclip = null;
            var posList = [{
                'left': '520px',
                'top': '460px'
            },
            {
                'left': '630px',
                'top': '390px'
            },
            {
                'left': '355px',
                'top': '260px'
            },
            {
                'left': '630px',
                'top': '390px'
            },
            {
                'left': '520px',
                'top': '460px'
            },
            {
                'left': '715px',
                'top': '555px'
            }];
            var timelist = that.getTimeList(posList);
            var obj2 = $('#worldarena_building');

            var t = 1,
            dirt, src;
            var thread = null;

            that.movieclip2 = new movieClipClass({
                "img": staticUrl + 'img/city/npc1.png',
                "width": 67,
                "height": 60,
                "x": 715,
                "y": 555,
                "loop": true,
                'zindex': '1',
                "frames": [{
                    rect: [0, 0],
                    interval: 200
                },
                {
                    rect: [67, 0],
                    interval: 200
                },
                {
                    rect: [134, 0],
                    interval: 200
                },
                {
                    rect: [201, 0],
                    interval: 200
                }]
            });
            that.movieclip2.appendTo(obj);
            that.movieclip2.play();

            var begin = function() {
                //console.log('执行' + t + '次');
                if (that.allTop) {
                    t = dirt = src = thread = obj = posList = timelist = bagin = that = obj2 = null;
                    return false;
                }
                if (t == 4 || t == 3) {
                    obj2.css({
                        'z-index': 2
                    });
                } else if (t == 2 || t == 5) {
                    obj2.css({
                        'z-index': 1
                    });
                }
                if (t == 7) {
                    t = 1;
                }
                dirt = t % 2 == 0 ? 1 : -1;
                src = t > 2 && t < 6 ? 2 : 1;

                thread = arguments.callee;
                that.movieclip2.obj.animate(posList[t - 1], timelist[t - 1], 'linear',
                function() {
                    that.movieclip2.obj.css({
                        '-webkit-transform': 'scaleX(' + dirt + ')',
                        'left': that.movieclip2.obj.offset().left,
                        'background-image': 'url(' + staticUrl + 'img/city/npc' + src + '.png)'
                    });
                    t++;
                    setTimeout(thread, 100);
                });
            };
            begin();
        },

        littleStart: function() {
            if (xGame.nowWorld != 'city') {
                return false;
            }
            this.allTop = false;
            if (this.movieclip1 == null) {
                this.littleXX();
            }
            if (this.movieclip2 == null) {
                this.littleRun();
            }
        },

        clearLittle: function() {
            if (xGame.nowWorld != 'city') {
                return false;
            }

            if (this.movieclip1 != null) {
                this.movieclip1.destory();
                this.movieclip1 = null;
            }
            if (this.movieclip2 != null) {
                this.allTop = true;
                this.movieclip2.destory();
                this.movieclip2 = null;
            }

        },

        getTimeList: function(list) {
            var that = this;
            var a = [],
            speed = 0.04;
            for (var i = 0; i < list.length; i++) {
                if (i == 0) {
                    a.push(that.getDistance(715 - parseInt(list[0].left.replace('px', '')), 555 - parseInt(list[0].top.replace('px', ''))) / speed);
                } else {
                    a.push(that.getDistance(parseInt(list[i].left.replace('px', '')) - parseInt(list[i - 1].left.replace('px', '')), parseInt(list[i].top.replace('px', '')) - parseInt(list[i - 1].top.replace('px', ''))) / speed);
                }
            }
            return a;
        },

        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },

        lock: function() {
            this.islock = true;
            $('#citymask').show();
            cityWorld.clearLittle();
        },

        unlock: function() {
            setTimeout(function() {
                if (cityWorld.islock == true) {
                    cityWorld.unlockProcess();
                }
            },
            500);
        },

        unlockProcess: function() {
            cityWorld.littleStart();
            cityWorld.islock = false;
            $('#citymask').hide();
        },
        
        createNew: function() {
            var tag = '<span class="tag"></span>';

            for (var i = 0;i < player.info.edifice.length; i ++) {
                if (player.info.edifice[i].name == 'practice' && player.info.edifice[i].open == '1') {
                    this.e.find('#practice_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                } else if (player.info.edifice[i].name == 'inherit' && player.info.edifice[i].open == '1') {
                    this.e.find('#inherit_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'mine' && player.info.edifice[i].open == '1') {
                    this.e.find('#mine_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'arena' && player.info.edifice[i].open == '1') {
                    this.e.find('#arena_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'trade' && player.info.edifice[i].open == '1') {
                    this.e.find('#trade_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'worldarena' && player.info.edifice[i].open == '1') {
                    this.e.find('#worldarena_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'book' && player.info.edifice[i].open == '1') {
                    this.e.find('#heromanual_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'workshop' && player.info.edifice[i].open == '1') {
                    this.e.find('#workshop_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'herotavern' && player.info.edifice[i].open == '1') {
                    this.e.find('#newbar_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }  else if (player.info.edifice[i].name == 'tavern' && player.info.edifice[i].open == '1') {
                    this.e.find('#bar_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                } else if (player.info.edifice[i].name == 'war' && player.info.edifice[i].open == '1') {
                    this.e.find('#war_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                } else if (player.info.edifice[i].name == 'beauty' && player.info.edifice[i].open == '1') {
                    this.e.find('#beauty_building').append(tag).attr({'name': player.info.edifice[i].name, 'index': i});
                }
            }
            tag = null;
        },

        unbindAll: function () {
            this.e.find('#citymask').unbind('touchend');
            this.e.undelegate('.building.show', 'touchstart');
            this.e.undelegate('.building.show', 'click');
            this.e.remove();
            this.e = null;
        },

        clear: function() {
            console.log('释放主城资源');
            xGame.unlock();
            this.clearLittle();
            if (this.movieclip1) {
                this.movieclip1.destroy();
                this.movieclip1 = null;
            }
            if (this.movieclip2) {
                this.movieclip2.destroy();
                this.movieclip2 = null;
            }
            this.touchObj = null;
            this.unbindAll();
        },
    };
})();