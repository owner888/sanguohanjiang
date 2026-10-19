/* 战帝堂 */
(function() {
    palaceheroWorldClass = function() {
        this.e;
        this.isshow = false;
        
        this.leftDom;
        this.rightDom;
        
        this.leftScroller;
        this.rightScroller;
        
        this.listData;//保存的基础数据
        this.pages;//总数
        this.page;//当前页
    };
    palaceheroWorldClass.prototype = {
        init: function(page) {
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                switch (res.status) {
                    
                    case 1:
                        that.preparePage(res, page);
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        xGame.loadWorld(xGame.lastWorld);
                        break;
                }
            };
             
            loadContents({
                'c': 'god',
                'm': 'palace',
                'page': '1'
            },
            initRender, true);
            initRender = null;
        },

        preparePage: function(res, page) {
            if (this.e != null) {
                return false;
            }

            $('#header').hide();
            $('#footer').hide();

            $('div#world').append($.template('palaceheroworld_tmpl'));

            this.e = $('#palaceheroworld_page');
            this.leftDom = this.e.find('#palacehero_left');
            this.rightDom = this.e.find('#palacehero_right');

            this.e.find('#palacehero_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                xGame.loadWorld('champions');
                e.preventDefault();
            });
            
            this.bindAll();
            
            $('#worldloading').removeClass('black').hide();
            
            this.listData = {};
            
            var temp = [];
            var i = 1;
            while(res.list[i] != undefined) {
                temp.push(res.list[i]);
                i ++;
            }
            
            var k = 1;
            for (var j = temp.length - 1;j >= 0;j --) {
                this.listData[k] = temp[j];
                k ++;
            }
            
            temp = null;
            i = null;
            j = null;
            k = null;
            this.fillLeft(1);//只填充第一次，会多次调用
            this.fillRight(page);//一次性生成，只调用一次
        },
        
        bindAll: function() {
            var that = this;
            
            //查看战报信息
            that.leftDom.find('#ph_history_list_scroller').undelegate('.t2', 'touchend').delegate('.t2', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var key = $(this).attr('index');
                var data = that.listData[that.page].report_list[key];
                that.viewBattle(data.chapter, data.round, data.stage);
            });
            
            that.rightDom.undelegate('#ph_left_btn', 'click').delegate('#ph_left_btn', 'click',
            function() {
                if (xGame.checkLock(true) || (that.page == 1)) { //不设置属性
                    return false;
                }
                that.rightScroller.scrollToPage('prev', 0);
            });
            
            that.rightDom.undelegate('#ph_right_btn', 'click').delegate('#ph_right_btn', 'click',
            function() {
                if (xGame.checkLock(true) || (that.page == that.pages)) { //不设置属性
                    return false;
                }
                that.rightScroller.scrollToPage('next', 0);
            });
            
            that.e.undelegate('#palacehero_right', 'swipeLeft').delegate('#palacehero_right', 'swipeLeft',
            function() {
                if (xGame.checkLock(true) || (that.page == that.pages)) { //不设置属性
                    return false;
                }
                that.rightScroller.scrollToPage('next', 0);
            });
            
            that.e.undelegate('#palacehero_right', 'swipeRight').delegate('#palacehero_right', 'swipeRight',
            function() {
                if (xGame.checkLock(true) || (that.page == 1)) { //不设置属性
                    return false;
                }
                that.rightScroller.scrollToPage('prev', 0);
            });

        },
        
        //构建左侧，需要传入key
        fillLeft: function(key){
            var info = '';
            info += '<li><em class="t1">' + this.listData[key].title + ':</em><em class="t2">' + this.listData[key].nickname + '</em></li>';
            info += '<li><em class="t1">' + lang('champions_king_name') + ':</em><em class="t2">' + this.listData[key].name + '</em></li>';
            info += '<li><em class="t1">' + lang('champions_in_server') + ':</em><em class="t2">' + this.listData[key].server + '</em></li>';
            this.leftDom.find('#ph_player_info').html(info);
            
            var history = '';
            for (var i = 0;i < this.listData[key].report_list.length;i ++) {
                history += '<li><em class="t1">' + this.listData[key].report_list[i].title + ':</em><em class="t2" index="' + i + '">' + lang('champions_view') + '</em></li>';
            }
            
            this.leftDom.find('#ph_history_list_scroller').html(history);
            
            if (this.leftScroller == null) {
                this.leftScroller = new iScroll('ph_history_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.leftScroller.refresh();
        },
        
        //构建右侧，需要传入key,只调用一次
        fillRight: function(page){
            var width = $('body').hasClass('iPad') ? 1024 : 960;
            width = isIPhone5 ? 1136 : width;
            var player = '';
            
            this.page = 1;
            this.pages = 0;
            for (var i = 1;i < 100;i ++) {
                if (this.listData[i] != undefined) {
                    if (this.listData[i].chapter == 1) {
                        player += '<li style="background-image:url(' + ximg(staticUrl+'img/pop/palaceheroworld_hero_' + this.listData[i].chapter + '.jpg') + ');"><p><em>' + this.listData[i].nickname + '</em></p></li>';
                    } else {
                        player += '<li style="background-image:url(http://img2.hanjiangsanguo.com/hjimg/pop/palaceheroworld_hero_' + this.listData[i].chapter + '.jpg);"><p><em>' + this.listData[i].nickname + '</em></p></li>';
                    }this.pages += 1;
                }
            }
            if (this.pages == 1) {
                this.rightDom.attr('class','only_1');
            } else {
                this.rightDom.attr('class','first');
            }
            
            this.e.find('#ph_player_list_scroller').html(player).width(this.pages * width + 'px');
            
            if (this.rightScroller == null) {
                this.rightScroller = new iScroll('ph_player_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false,
                    onScrollEnd: function() {
                        if (palaceheroWorld.pages > 1) {
                            palaceheroWorld.page = palaceheroWorld.rightScroller.currPageX + 1;
                            if (palaceheroWorld.page == palaceheroWorld.pages) {
                                palaceheroWorld.rightDom.attr('class','last');
                            } else if (palaceheroWorld.rightScroller.currPageX == 0) {
                                palaceheroWorld.rightDom.attr('class','first');
                            } else {
                                palaceheroWorld.rightDom.removeClass();
                            }
                            palaceheroWorld.fillLeft(palaceheroWorld.page);
                        }
                    }
                });
            }
            
            //回来之后回到上次的页面
            if (page != undefined) {
                if (page != palaceheroWorld.page) {
                    palaceheroWorld.rightScroller.currPageX = page - 1;
                    palaceheroWorld.rightScroller.scrollTo(0 - palaceheroWorld.rightScroller.currPageX * width, 0);
                    
                    if (palaceheroWorld.pages > 1) {
                        palaceheroWorld.page = palaceheroWorld.rightScroller.currPageX + 1;
                        if (palaceheroWorld.page == palaceheroWorld.pages) {
                            palaceheroWorld.rightDom.attr('class','last');
                        } else if (palaceheroWorld.rightScroller.currPageX == 0) {
                            palaceheroWorld.rightDom.attr('class','first');
                        } else {
                            palaceheroWorld.rightDom.removeClass();
                        }
                        palaceheroWorld.fillLeft(palaceheroWorld.page);
                    }
                }
            }
            this.rightScroller.refresh();
            
            player = null;
        },
        
        /* 查看战报 */
        viewBattle: function(chapter, round, stage) {

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                xGame.unlock();

                if (res.status == 1) {
                    /* 开始进入战斗界面 */
                    $('#worldloading').addClass('black').show();
                    var page = palaceheroWorld.page;
                    setTimeout(function() {
                        xGame.loadBattle(res, null, 'champions',
                        function() {
                            xGame.loadWorld('palacehero', page);
                        });
                    },
                    500);

                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'god',
                'm': 'palace_report',
                'chapter' : chapter,
                'round' : round,
                'stage' : stage
            },
            render);
            render = null;

        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#palacehero_hide').unbind('touchend');
                this.e.undelegate('#palacehero_right', 'swipeLeft');
                this.e.undelegate('#palacehero_right', 'swipeRight');
                this.e.remove();
                this.e = null;
            }
            if (this.leftDom) {
                this.leftDom.find('#ph_history_list_scroller').undelegate('.t2', 'touchend');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightDom) {
                this.rightDom.undelegate('#ph_left_btn', 'click');
                this.rightDom.undelegate('#ph_right_btn', 'click');
                this.rightDom.remove();
                this.rightDom = null;
            }
        },
        
        clear: function() {
            xGame.unlock();
            if (this.e != null) {
                
                this.listData = null;
                this.pages = null;
                this.page = null;
                
                if (this.leftScroller != null) {
                    this.leftScroller.destroy();
                    this.leftScroller = null;
                }
                
                if (this.rightScroller != null) {
                    this.rightScroller.destroy();
                    this.rightScroller = null;
                }
                
                this.unbindAll();
            }
        },
    };
})();