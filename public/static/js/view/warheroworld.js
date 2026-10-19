/* 斗士堂 */
(function() {
    warheroWorldClass = function() {
        this.e;
        this.isshow = false;

        this.dom_history;
        this.historyScroller;

        this.dom_thismonth;
        this.thismonthScroller;

        this.dom_battlelog;
        this.battlelogScroller;

        this.returnData;/* 用于查看战报以后返回 */
        /* 
            {type: 1/2/3 [1普通历程，历史武神历程，本届36强历程]
            list: ...}
        */
    };
    warheroWorldClass.prototype = {
        init: function(battleReturn) {
            var that = this;

            /** 初始化数据 **/
            if(typeof battleReturn == 'undefined' || battleReturn == null) {
                that.returnData = {
                    now_page: '',//在哪个界面
                    main_list: null,//主界面数据
                    his_list: null,//历届数据
                    his_page: -1,//在历届数据的哪一页
                    thismonth_list: null,//当月36强数据
                    thismonth_px: null,//当前在36强的哪个位置
                    battlelog: null//战报数据
                };
            } else {
                that.returnData = JSON.parse(battleReturn);
            }

            //有从战斗返回的数据
            if(that.returnData.now_page != '') {
                that.initPage(that.returnData.main_list);
            } else {
                //开始构建页面数据
                var initRender = function(res) {
                    switch (res.status) {
                        
                        case 1:
                            that.returnData.main_list = res.list;//保存数据，为了从战斗回来用
                            that.initPage(res.list);
                            break;
                        case -1:
                            xGame.toast.show(lang('war_no_ranklist'));
                            xGame.loadWorld(xGame.lastWorld);
                            break;
                        default:
                            xGame.toast.show(lang('undefined_error'));
                            xGame.loadWorld(xGame.lastWorld);
                            break;
                    }
                };
                loadContents({
                    'c': 'war',
                    'm': 'get_palace_list',
                    'all': '0'
                },
                initRender, true);
                initRender = null;
            }
        },

        /* 绑定事件 */
        initPage: function(mainList) {
            var that = this;

            if (that.e != null) {
                return false;
            }

            $('#world').append($.template('warheroworld_tmpl'));

            that.e = $('#warheroworld_page');

            //关闭
            that.e.find('#warhero_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.loadWorld('war');
                e.preventDefault();
            });
            //历程按钮
            that.e.undelegate('.show_his', 'touchend').delegate('.show_his', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getHistoryDetailFunc($(this).attr('uid'), $(this).attr('serverid'));
                e.preventDefault();
            });
            //历届
            that.e.find('#warhero_history_list').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.initHistoryPage();
                e.preventDefault();
            });
            that.e.find('#warhero_history_page').undelegate('#warhero_history_hide_btn', 'touchend').delegate('#warhero_history_hide_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#warhero_history_page').hide();
                that.e.find('#warhero_history_scroller_ul').empty();
                that.historyScroller.destroy();
                that.historyScroller = null;
                //恢复默认数据
                that.returnData.now_page = 'main';
                that.returnData.his_list = null;
                that.returnData.his_page = -1;
                e.preventDefault();
            });
            //武神榜
            that.e.find('#warworld_thismonth_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.initThismonthPage();
                e.preventDefault();
            });
            that.e.find('#warhero_thismonth_page').undelegate('#warhero_thismonth_hide_btn', 'touchend').delegate('#warhero_thismonth_hide_btn', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#warhero_thismonth_page').hide();
                that.e.find('#warhero_thismonth_scroller_ul').empty();
                that.thismonthScroller.destroy();
                that.thismonthScroller = null;
                //恢复默认数据
                that.returnData.now_page = 'main';
                that.returnData.thismonth_list = null;
                that.returnData.thismonth_px = -1;
                e.preventDefault();
            });
            //战报查看处理
            //点击查看按钮
            that.e.find('#war_detail_list').undelegate('.t2', 'click').delegate('.t2', 'click',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.viewHistoryReport($(this).attr('vid'), $(this).attr('stage'));
                e.preventDefault();
            });
            that.e.find('#warhero_battlelog_page').undelegate('#war_detail_back', 'touchend').delegate('#war_detail_back', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.e.find('#warhero_battlelog_page').hide();
                that.e.find('#war_detail_list').empty();
                that.battlelogScroller.destroy();
                that.battlelogScroller = null;
                //恢复默认数据
                that.returnData.battlelog = null;
                e.preventDefault();
            });

            //显示主界面4个
            that.buildMain(mainList);
            
            $('#worldloading').removeClass('black').hide();

            /** 重要：处理从战斗出来的 **/
            if(that.returnData.now_page == 'main') {//主城里查看的普通历程
                that.returnData.now_page = 'main';
                that.showHistoryDetatil(that.returnData.battlelog);
            } else if(that.returnData.now_page == 'his_list') {//历届武神，还需要滑动到相应的位置并打开历程
                that.initHistoryPage(that.returnData.his_list);
            } else if(that.returnData.now_page == 'thismonth_list') {//36强，并打开历程
                that.initThismonthPage(that.returnData.thismonth_list);
            } else {//直接访问的
                that.returnData.now_page = 'main';
            }

            // 历届武圣弹窗的左右翻页按钮
            that.e.find('#warhero_history_content').undelegate('.scroll_btn', 'touchend').delegate('.scroll_btn', 'touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }
                
                var width = that.historyScroller.wrapperW;

                if (e.target.id == 'history_scroll_left_btn' && that.historyScroller.x < 0) {
                    xGame.islock = true;
                    that.historyScroller.scrollTo(-width, 0, 500, true);
                } else if (e.target.id == 'history_scroll_right_btn' && that.historyScroller.x > that.historyScroller.maxScrollX) {
                    xGame.islock = true;
                    that.historyScroller.scrollTo(width, 0, 500, true);
                }

                e.preventDefault();
            });
        },
        
        buildMain: function(list) {
 
            var html = '';
            for(var i = 0, len = list.length; i < len; i++) {
                html += '<div class="btn heroitem show_his" type="1" uid="' + list[i].uid + '" serverid="' + list[i].serverid + '" id="heroitem' + (i + 1) + '">'
                html +=     '<p class="heroitem_name">' + list[i].server + list[i].nickname + '</p>';
                html += '</div>';
            }

            this.e.find('#warhero_pop').append(html);
        },

        initHistoryPage: function(returnData) {
            var that = this;

            var displayHistoryPage = function(data, showBttleLog) {
                var html = '';
                for(var len = data.length, i = len - 1; i >= 0; i--) {
                    var themonth = data[i];
                    html += '<li class="' + (i % 2 == 1 ? 'bg2' : '') + '"><span class="period">'+ lang('war_period', [i + 1]) +'</span>';
                    for(var x = 0, xlen = themonth.length; x < xlen; x++) {
                        html += '<div class="btn history_heroitem show_his" type="2" uid="' + themonth[x].uid + '" serverid="' + themonth[x].serverid + '" id="history_heroitem' + (x + 1) + '">'
                        html +=     '<p class="heroitem_name">' + themonth[x].server + themonth[x].nickname + '</p>';
                        html += '</div>';
                    }
                    html += '</li>';
                }
                that.e.find('#warhero_history_scroller_ul').html(html);
                that.e.find('#warhero_history_scroller_ul').width(len * 540 + 'px');
        
                if (that.historyScroller == null) {
                    that.historyScroller = new iScroll('warhero_history_scroller', {
                        snap: 'li',
                        bounce: false,
                        momentum: false,
                        hScroll: true,
                        hScrollbar: false,
                        vScrollbar: false,
                        onScrollEnd: function() {
                            that.returnData.his_page = this.currPageX;
                            xGame.unlock();
                        }
                    });
                }

                that.e.find('#warhero_history_page').show();

                that.historyScroller.refresh();

                that.returnData.now_page = 'his_list';

                if(that.returnData.his_page > 0) {//滑动到上次查看的那一期
                    that.historyScroller.scrollToPage(that.returnData.his_page);
                }

                if(typeof showBttleLog != 'undefined') {//需要再显示当前人的历程
                    that.showHistoryDetatil(that.returnData.battlelog);
                }
            };

            //如果有从战斗返回的数据，直接显示
            if(typeof returnData != 'undefined') {
                displayHistoryPage(returnData, true);
            //否则就从网络获取最新的
            } else {
                var render = function(res) {
                    if(res.status == 1) {
                        
                        that.returnData.his_list = res.list;//保存数据
                        displayHistoryPage(res.list);

                    } else {
                        xGame.toast.show('undefined_error');
                    }
                };
                loadContents({
                    'c': 'war',
                    'm': 'get_palace_list',
                    'all': '1'
                },
                render);
                render = null;
            }
        },

        initThismonthPage: function(returnData) {
            var that = this;

            var displayThismonthPage = function(data, showBttleLog) {
                var html = '';
                for(var i = 0, len = data.length; i < len; i++) {
                    var heroitem = data[i];
                    html += '<li class="warhero_thismonth_item">'
                    html += '    <div class="rank">' + heroitem.round + '</div>';
                    html += '    <div class="namewrap">' + heroitem.server + heroitem.nickname + '</div>';
                    html += '    <div class="btn battlelog show_his" type="3" uid="' + heroitem.uid + '" serverid="' + heroitem.serverid + '">' + lang('war_history') + '</div>';
                    html += '</li>';
                }
                that.e.find('#warhero_thismonth_scroller_ul').html(html);
        
                if (that.thismonthScroller == null) {
                    that.thismonthScroller = new iScroll('warhero_thismonth_scroller', {
                        snap: 'li',
                        bounce: false,
                        momentum: false,
                        vScroll: true,
                        hScrollbar: false,
                        vScrollbar: false,
                    });
                }

                that.e.find('#warhero_thismonth_page').show();

                that.thismonthScroller.refresh();

                that.returnData.now_page = 'thismonth_list';

                if(typeof showBttleLog != 'undefined') {//需要再显示当前人的历程
                    that.showHistoryDetatil(that.returnData.battlelog);
                }
            };

            //如果有从战斗返回的数据，直接显示
            if(typeof returnData != 'undefined') {
                displayThismonthPage(returnData, true);
            //否则就从网络获取最新的
            } else {
                var render = function(res) {
                    if(res.status == 1) {
                        
                        that.returnData.thismonth_list = res.list;//保存数据
                        displayThismonthPage(res.list);

                    } else if(res.status == -1) {
                        xGame.toast.show(lang('war_no_ranklist'));
                    }  else {
                        xGame.toast.show('undefined_error');
                    }
                };
                loadContents({
                    'c': 'war',
                    'm': 'get_rank_list',
                },
                render);
                render = null;
            }

        },

        //获得玩家历程方法
        getHistoryDetailFunc: function(uid, serverid) {
            
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.returnData.battlelog = res;
                        that.showHistoryDetatil(res);
                        break;
                    case -3:
                        xGame.toast.show(lang('war_get_history_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('war_not_over'));
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_info_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'get_report_list',
                'uid' : uid,
                'serverid' : serverid
            },
            Func);
            Func = null;
        },

        //显示历程详情
        showHistoryDetatil: function(data) {
            var that = this;

            var dom_battlelog = that.e.find('#warhero_battlelog_page');

            dom_battlelog.find('#war_history_d1').html('<em class="t1">' + data.info.rank + '</em><em class="t2">' + 'Lv.' + data.info.level + ' ' + data.info.nickname + '</em>');
            dom_battlelog.find('#war_history_d2').html('<em class="t1">' + lang('war_server') + ':</em><em class="t2">' + data.info.server + '</em>');
            
            var html = '';
            for (var i = 0;i < data.list.length;i ++) {
                html += '<li><em class="t1">' + data.list[i].round + data.list[i].turn + '</em>';
                html += '<span class="pvp_info"><em class="result '+ (parseInt(data.list[i].win) == 1 ? 'win' : '') +'"></em>';
                html += '<em class="player">' + data.info.nickname + ' VS ' + data.list[i].nickname + '</em>';
                html += '<em class="t2" vid="' + data.list[i].id + '" stage="' + data.list[i].stage + '">' + lang('war_view') + '</em></span></li>';
            }
            dom_battlelog.find('#war_detail_list').html(html);
            
            that.battlelogScroller = new iScroll('war_detail_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            dom_battlelog.show();
            that.battlelogScroller.refresh();

        },
        
        //显示历程战报
        viewHistoryReport: function(id, stage) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        (function(reportData, cacheData) {
                            xGame.loadBattle(reportData, null, 'war', function() {
                                xGame.loadWorld('warhero', cacheData);
                            });
                        })(res, JSON.stringify(that.returnData));
                        
                        break;
                    case -1:
                        xGame.toast.show(lang('war_get_report_fail'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
             
            loadContents({
                'c': 'war',
                'm': 'get_report_info',
                'id' : id,
                'stage' : stage
            },
            Func);
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#warhero_hide').unbind('touchend');
                this.e.find('#warhero_history_list').unbind('touchend');
                this.e.find('#warworld_thismonth_btn').unbind('touchend');
                this.e.undelegate('.show_his', 'touchend');
                this.e.find('#warhero_history_page').undelegate('#warhero_history_hide_btn', 'touchend');
                this.e.find('#warhero_thismonth_page').undelegate('#warhero_thismonth_hide_btn', 'touchend');
                this.e.find('#war_detail_list').undelegate('.t2', 'click');
                this.e.find('#warhero_battlelog_page').undelegate('#war_detail_back', 'touchend');
                this.e.find('#warhero_history_content').undelegate('.scroll_btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },

        clear: function() {
            xGame.unlock();
            if (this.e != null) {

                if(this.historyScroller != null) {
                    this.historyScroller.destroy();
                    this.historyScroller = null;
                }
                if(this.dom_history != null) {
                    this.dom_history = null;
                }

                if(this.thismonthScroller != null) {
                    this.thismonthScroller.destroy();
                    this.thismonthScroller = null;
                }
                if(this.dom_thismonth != null) {
                    this.dom_thismonth = null;
                }

                if(this.battlelogScroller != null) {
                    this.battlelogScroller.destroy();
                    this.battlelogScroller = null;
                }
                if(this.dom_battlelog != null) {
                    this.dom_battlelog = null;
                }

                this.returnData = null;
            }
            this.unbindAll();
        },
    };
})();