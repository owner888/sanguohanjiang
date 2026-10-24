/* 藏宝图 */
(function() {
    treasuremapPopClass = function() {
        this.e;
        this.isshow = false;
        this.isrefresh = false;
        this.myScroller; //列表滑动
        this.list; //列表数据
        this.team; //队伍信息
        this.map; //藏宝图信息
        this.num; //藏宝图数量
        this.refresh; //刷新价格
        this.loopenable = true; //循环开关
        this.looptimer = null; //循环定时器
    };

    treasuremapPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            this.loopenable = true;

            //请求数据
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 0:
                    xGame.toast.show(lang('wait_for_treasuremap'));
                    $('#worldloading').hide();
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    $('#worldloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('get_activity_info_fail'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    /* 请求成功，执行各项操作 */
                    that.preparePage();
                    that.buildPage(res);
                    that.startLoop();
                    break;
                }
            };

            loadContents({
                'c':
                'treasuremap',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;

        },

        startLoop: function() {
            var that = this;

            var loopfunc = function() {
                if (that.loopenable == false) { //停止
                    loopfunc = null;
                    that.stopLoop();
                    return false;
                }

                that.refreshData(function() {
                    that.looptimer = setTimeout(loopfunc, 5000);
                });
            };
            that.looptimer = setTimeout(loopfunc, 5000);
        },

        stopLoop: function() {
            this.loopenable = false;
            clearTimeout(this.looptimer);
            this.looptimer = null;
        },

        /* 初始化界面 */
        preparePage: function() {
            var that = this;

            if (that.e != null) {
                return false;
            }

            that.e = xGame.loadPopTmpl('treasuremap');

            //绑定关闭事件
            that.e.find('#treasuremappop_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                treasuremappop.hide();
                e.preventDefault();
            });

            /* 滑动 */
            that.myScroller = new iScroll('treasuremappop_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            $('#worldloading').hide();
        },

        refreshData: function(callback) {
            var that = this;

            //if(xGame.checkLock()) {
            //    return false;
            //}

            //请求数据
            var Func = function(res) {
                xGame.unlock();
                if (res.status == 1) {
                    that.buildPage(res);
                    if (callback) { //执行回调函数
                        callback();
                    }
                } else {
                    xGame.toast.show(lang('get_activity_info_fail'));
                    return false;
                }
            };
            loadContents({
                'c': 'treasuremap',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;

        },

        buildPage: function(res) {
            var that = this;

            //数据保存
            that.list = res.list || [];
            that.team = res.team || [];
            that.map = res.map || [];
            that.num = parseInt(res.num || 0);
            that.refresh = parseInt(res.refresh_price || 2);

            if (parseInt(res.team_status) == -1) {
                xGame.toast.show(lang('team_has_been_dissolved'));
            } else if (parseInt(res.team_status) == 0 && parseInt(res.add_silver) > 0) {
                xGame.confirm.show(lang('treasuremap'), lang('treasuremap_complete', [res.add_silver]), 1,
                function() {
                    xGame.confirm.hide();
                });
                player.updateinfo({
                    'silver': parseInt(res.silver)
                });
            }

            //各种数据拼装
            that.buildlist();
            that.buildteam();
            that.buildmap();

            that.myScroller.refresh();

        },

        /*列表信息*/
        buildlist: function() {
            //组装列表数据
            var that = this;
            var html = '';
            that.list = that.list || [];
            if (that.list.length > 0) {
                for (var i = 0; i < that.list.length; i++) {
                    var item = that.list[i];
                    if (!item || !item.list || !item.list.length) continue;
                    var top = '';
                    var center = '';
                    var buttom = '';
                    html += '<li>';
                    var leaderName = (item.list[0] && item.list[0].nickname) ? item.list[0].nickname : '探险队长';
                    html += '<p id="name">' + leaderName + '</p>';
                    var pQuality = item.quality || 1;
                    for (var j = 0; j < item.list.length; j++) {
                        var pType = parseInt(item.list[j].type || 1);
                        if (pType == 1) {
                            top = '<div class="mapbox top">' + lang('part1') + '<span class="bg' + pQuality + ' own' + pQuality + '"></span></div>';
                        } else if (pType == 2) {
                            center = '<div class="mapbox center">' + lang('part2') + '<span class="bg' + pQuality + ' own' + pQuality + '"></span></div>';
                        } else if (pType == 3) {
                            buttom = '<div class="mapbox buttom">' + lang('part3') + '<span class="bg' + pQuality + ' own' + pQuality + '"></span></div>';
                        }
                    }

                    if (top == '') {
                        html += '<div class="mapbox top">' + lang('part1') + '<span class="bg' + pQuality + '"></span></div>';
                    } else {
                        html += top;
                    }
                    if (center == '') {
                        html += '<div class="mapbox center">' + lang('part2') + '<span class="bg' + pQuality + '"></span></div>';
                    } else {
                        html += center;
                    }
                    if (buttom == '') {
                        html += '<div class="mapbox buttom">' + lang('part3') + '<span class="bg' + pQuality + '"></span></div>';
                    } else {
                        html += buttom;
                    }

                    html += '<p class="army">' + lang('team_num') + item.list.length + '/3</p>';
                    html += '<button class="btn pop_btn_middle" tid=' + item.id + '>' + lang('join') + '</button>';
                    html += '</li>';
                }
            }
            if (html == '') {
                that.e.find('#treasuremappop_scroller').addClass('nodata');
            } else {
                that.e.find('#treasuremappop_scroller').removeClass('nodata');
            }
            that.e.find('#treasuremappop_scroller').find('ul').html(html);
            if ($.isArray(that.map) == false) {
                //绑定事件
                that.e.find('#treasuremappop_scroller').find('ul').undelegate('.btn', 'touchend').delegate('.btn', 'touchend',
                function() {
                    that.join_team($(this).attr('tid'));
                });
            } else {
                that.e.find('#treasuremappop_scroller').find('.btn').attr('class', 'btn pop_btn_middle_disable');
                that.e.find('#treasuremappop_scroller').find('ul').undelegate('.btn', 'touchend').delegate('.btn', 'touchend',
                function() {
                    xGame.toast.show(lang('no_treasuremap_to_join'));
                });
            }
            html = null;
        },

        /*队伍信息*/
        buildteam: function() {
            var that = this;
            if ($.isArray(that.team) == false) {
                //组装右边队伍信息
                var html = '';
                for (var i = 0; i < 3; i++) {
                    if (that.team.list[i] != undefined) {
                        html += '<p><em class="name">' + that.team.list[i].nickname + '：</em>';
                        html += '<em class="desc' + that.team.quality + '">' + that.team.list[i].name + '(' + that.get_type(that.team.list[i].type) + ')</em>';
                        if (parseInt(that.team.list[i].uid) == parseInt(that.team.id)) {
                            html += '<em class="guide"> ' + lang('leader') + '</em>';
                        }
                        html += '</p>';
                    } else {
                        html += '<p>' + lang('unmatch') + '</p>';
                    }
                }
                that.e.find('#have_army_wrap').find('.myteaminfo').html(html);
                that.e.find('#have_army_wrap').show();
                that.e.find('#no_number_wrap').hide();
                //绑定解散
                that.e.find('#have_army_wrap').find('.btn').unbind('touchend').bind('touchend',
                function() {
                    that.dismiss();
                });
                if (parseInt(that.team.list[0].uid) == parseInt(player.info.uid) && that.team.list.length == 1) {
                    //显示解散
                    that.e.find('#have_army_wrap').find('.btn').html(lang('dissolve'));
                } else {
                    //显示退出
                    that.e.find('#have_army_wrap').find('.btn').html(lang('exit'));
                }
                //列表按钮全灰掉
                that.e.find('#treasuremappop_scroller').find('.btn').attr('class', 'btn pop_btn_middle_disable');
                that.bindAll(1);
            } else {
                //显示创建队伍
                that.e.find('#have_army_wrap').hide();
                that.e.find('#no_number_wrap').show();
                //列表按钮全灰掉
                //that.e.find('#treasuremappop_scroller').find('.btn').attr('class','btn pop_btn_middle_disable');
                //卖出，升级，刷新按钮灰掉
                that.bindAll();
                //绑定创建按钮
                that.e.find('#no_number_wrap').find('.btn').unbind('touchend').bind('touchend',
                function() {
                    that.create_team();
                });
            }
        },

        /*藏宝图信息*/
        buildmap: function() {
            var that = this;
            if (that.map && $.isArray(that.map) == false && that.map.name) {
                //还有藏宝图
                var mapName = that.map.name || '';
                var mapType = (that.map.type != null) ? that.map.type : 1;
                var mapQuality = (that.map.quality != null) ? that.map.quality : 1;
                var lvPrice = (that.map.levelup_price != null) ? that.map.levelup_price : 5;
                var salePrice = (that.map.sale_price != null) ? that.map.sale_price : 12000;

                that.e.find('#mapdetail_wrap').find('.mapshow').find('.name').html(mapName + '(<span id="maptype">' + that.get_type(mapType) + '</span>)').attr('class', 'name desc' + mapQuality);
                that.e.find('#mapdetail_wrap').find('.mapshow').find('em').css({
                    'background': 'url(' + staticUrl + 'img/pop/treasuremappop_map_' + parseInt(mapQuality) + '.jpg' + ') 0 0 no-repeat'
                });
                that.e.find('#mapdetail_wrap').find('#refresh').html((that.refresh || 2) + lang('gold'));
                if (parseInt(that.map.ismax) == 1) {
                    //最高等级藏宝图，不能升级
                    that.e.find('#mapdetail_wrap').find('#up').html(0 + lang('gold'));
                    that.e.find('#mapdetail_wrap').find('.up').attr('class', 'pop_btn_middle_disable btn up');
                    that.e.find('#mapdetail_wrap').find('.up').unbind().html(lang('grade_is_full'));
                } else {
                    that.e.find('#mapdetail_wrap').find('#up').html(lvPrice + lang('gold'));
                    that.e.find('#mapdetail_wrap').find('.up').html(lang('grade_lvup'));
                }
                that.e.find('#mapdetail_wrap').find('#save').html(salePrice);
                that.e.find('#mapdetail_wrap').find('.mapshow').show();
                //that.e.find('#no_number_wrap').hide();
            } else {
                that.e.find('#mapdetail_wrap').find('.mapshow').children('em').css('background', 'url(' + (staticUrl + 'img/pop/treasuremap_nomap.jpg') + ') 0 0 no-repeat');
                that.e.find('#mapdetail_wrap').find('.mapshow').children('.name').attr('class', 'name desc1').html(lang('have_no_treasuremap'));
                that.bindAll(1);
                //没有藏宝图了
                that.e.find('#have_army_wrap').hide();
                that.e.find('#no_number_wrap').show();
                //创建队伍的按钮灰掉
                that.e.find('#no_number_wrap').find('.btn').attr('class', 'btn pop_btn_middle_disable').unbind();
            }
            that.e.find('#treasuremap_number').html(parseInt(that.num));
        },

        /*加入队伍*/
        join_team: function(tid) {
            var that = this;

            //if(xGame.checkLock()) {
            //    return false;
            //}

            tid = parseInt(tid);
            var Func = function(res) {

                //xGame.unlock();

                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('get_treasuremap_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('team_get_treasuremap'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 4:
                    xGame.toast.show(lang('treasuremap_not_match'));
                    break;
                case 3:
                    xGame.toast.show(lang('team_have_treasuremap'));
                    break;
                case 2:
                    xGame.toast.show(lang('you_had_join_a_team'));
                    break;
                case 0:
                    xGame.toast.show(lang('wait_for_join_team'));
                    break;
                case 1:
                    if (parseInt(res.finish) == 1) {
                        xGame.confirm.show(lang('treasuremap'), lang('treasuremap_complete', [res.add_silver]), 1,
                        function() {
                            xGame.confirm.hide();
                        });
                        player.updateinfo({
                            'silver': parseInt(res.silver)
                        });
                        that.map = res.map;
                        that.num = parseInt(res.num);
                        that.buildmap();
                    }
                    that.list = res.list || [];
                    that.team = res.team || [];
                    that.bindAll(1);
                    that.buildlist();
                    that.buildteam();
                    //列表按钮全灰掉
                    that.e.find('#treasuremappop_scroller').find('.btn').attr('class', 'btn pop_btn_middle_disable');
                    break;
                }
            };
            loadContents({
                'c': 'treasuremap',
                'm': 'join_team',
                'tid': tid
            },
            Func, true); //加载数据
            Func = null;
        },

        /*宝藏图升级品级*/
        levelup: function() {
            var that = this;

            //if(xGame.checkLock()) {
            //     return false;
            // }

            var Func = function(res) {

                //xGame.unlock();

                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('update_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('no_lvup_map'));
                    break;
                case - 2 : xGame.toast.show(lang('can_not_lvup_in_team'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 3:
                    xGame.goPay(lang('no_gold_to_grade_lvup'));
                    break;
                case 2:
                    xGame.toast.show(lang('treasuremap_full_lv'));
                    break;
                case 0:
                    xGame.toast.show(lang('wait_for_treasuremap_lvup'));
                    break;
                case 1:
                    if (res.map) {
                        res.map.type = (that.map && that.map.type) ? that.map.type : (res.map.type || 1);
                        that.map = res.map;
                    }
                    that.buildmap();
                    player.updateinfo({
                        'gold': res.gold
                    });
                    break;
                }
            };
            loadContents({
                'c': 'treasuremap',
                'm': 'levelup'
            },
            Func, true); //加载数据
            Func = null;
        },

        /*解散队伍*/
        dismiss: function() {
            var that = this;

            //if(xGame.checkLock()) {
            //    return false;
            //}

            var Func = function(res) {

                //xGame.unlock();

                switch (parseInt(res.status)) {
                case 0:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case - 2 : xGame.toast.show(lang('team_is_dissolve'));
                    break;
                case - 3 : xGame.toast.show(lang('team_not_exist'));
                    break;
                case 1:
                    that.e.find('#have_army_wrap').hide();
                    that.e.find('#no_number_wrap').show();
                    //绑定创建按钮
                    that.e.find('#no_number_wrap').find('.btn').unbind('touchend').bind('touchend',
                    function() {
                        that.create_team();
                    });
                    that.bindAll();
                    //组装数据
                    that.list = res.list || [];
                    that.buildlist();
                    break;
                }
            };
            loadContents({
                'c': 'treasuremap',
                'm': 'exit_team'
            },
            Func, true); //加载数据
            Func = null;
        },

        /*事件绑定*/
        bindAll: function(type) {
            var that = this;
            if (parseInt(type) == 1) {
                //解锁绑定
                that.e.find('#mapdetail_wrap').find('.refresh').unbind('touchend');
                that.e.find('#mapdetail_wrap').find('.up').unbind('touchend');
                that.e.find('#mapdetail_wrap').find('.save').unbind('touchend');
                that.e.find('#mapdetail_wrap').find('.refresh').attr('class', 'pop_btn_middle_disable btn refresh');
                that.e.find('#mapdetail_wrap').find('.up').attr('class', 'pop_btn_middle_disable btn up');
                that.e.find('#mapdetail_wrap').find('.save').attr('class', 'pop_btn_middle_disable btn save');
            } else {
                //绑定刷新
                that.e.find('#mapdetail_wrap').find('.refresh').unbind('touchend').bind('touchend',
                function() {
                    that.map_refresh();
                });
                that.e.find('#mapdetail_wrap').find('.refresh').attr('class', 'pop_btn_middle btn refresh');
                //绑定升级
                if (parseInt(that.map.ismax) != 1) {
                    that.e.find('#mapdetail_wrap').find('.up').unbind('touchend').bind('touchend',
                    function() {
                        that.levelup();
                    });
                    that.e.find('#mapdetail_wrap').find('.up').attr('class', 'pop_btn_middle btn up');
                }
                //绑定出售
                that.e.find('#mapdetail_wrap').find('.save').unbind('touchend').bind('touchend',
                function() {
                    that.sale();
                });
                that.e.find('#mapdetail_wrap').find('.save').attr('class', 'pop_btn_middle btn save');
            }
        },

        /*创建队伍*/
        create_team: function() {
            var that = this;

            //if(xGame.checkLock()) {
            //    return false;
            //}

            var Func = function(res) {

                //xGame.unlock();

                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('create_team_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('treasure_map_no_exist'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 0:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                case 2:
                    xGame.toast.show(lang('have_team_already'));
                    break;
                case 1:
                    that.list = res.list || [];
                    that.team = res.team || [];
                    that.buildlist();
                    that.buildteam();
                    break;
                }
            };
            loadContents({
                'c': 'treasuremap',
                'm': 'create_team'
            },
            Func, true); //加载数据
            Func = null;
        },

        /*部件刷新*/
        map_refresh: function() {
            var that = this;

            //if(xGame.checkLock()) {
            //    return false;
            //}

            var Func = function(res) {

                //xGame.unlock();

                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('update_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('no_treasuremap'));
                    break;
                case - 2 : xGame.toast.show(lang('can_not_refresh_in_team'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 2:
                    xGame.goPay(lang('no_gold_for_refresh'));
                    break;
                case 0:
                    xGame.toast.show(lang('can_not_lvup_part'));
                    break;
                case 1:
                    that.e.find('#maptype').html(that.get_type(res.type));
                    player.updateinfo({
                        'gold':
                        parseInt(res.gold)
                    });
                    break;
                }
            };
            loadContents({
                'c':
                'treasuremap',
                'm': 'refresh'
            },
            Func, true); //加载数据
            Func = null;
        },

        /*藏宝图卖出*/
        sale: function() {
            var that = this;

            //if(xGame.checkLock()) {
            //    return false;
            //}

            var Func = function(res) {

                //xGame.unlock();

                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('no_map_to_sell'));
                    break;
                case - 2 : xGame.toast.show(lang('can_not_sell_in_team'));
                    break;
                case - 1 : xGame.toast.show(lang('activity_closed'));
                    break;
                case 0:
                    xGame.toast.show(lang('wait_for_sell'));
                    break;
                case 1:
                    xGame.toast.show(lang('sell_up_get', [res.add_silver]));
                    player.updateinfo({
                        'silver': res.silver
                    });
                    that.map = res.map;
                    that.num = parseInt(res.num);
                    that.buildmap();
                    if ($.isArray(that.map) == false) {
                        that.bindAll();
                    } else {
                        that.bindAll(1);
                        that.e.find('#treasuremappop_scroller').find('.btn').attr('class', 'btn pop_btn_middle_disable');
                    }
                    break;
                }
            };
            loadContents({
                'c': 'treasuremap',
                'm': 'sale'
            },
            Func, true); //加载数据
            Func = null;
        },

        /*得到藏宝图是属于哪一部分*/
        get_type: function(type) {
            type = parseInt(type);
            var name = '';
            if (type == 1) {
                name = lang('part1');
            } else if (type == 2) {
                name = lang('part2');
            } else if (type == 3) {
                name = lang('part3');
            }
            return name;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#treasuremappop_hidebtn').unbind('touchend');
                this.e.find('#have_army_wrap').find('.btn').unbind('touchend');
                this.e.find('#no_number_wrap').find('.btn').unbind('touchend');
                this.e.find('#mapdetail_wrap').find('.refresh').unbind('touchend');
                this.e.find('#mapdetail_wrap').find('.up').unbind('touchend');
                this.e.find('#mapdetail_wrap').find('.save').unbind('touchend');
                this.e.find('#treasuremappop_scroller').find('ul').undelegate('.btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }

            this.isshow = false;
            this.unbindAll();
            this.stopLoop(); //停止循环
        },
    };
})();