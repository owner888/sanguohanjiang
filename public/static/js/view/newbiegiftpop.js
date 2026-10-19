/* 新手礼包 */
(function() {
    newbiegiftPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroll = null;
        this.isTimeout = [];
    };

    newbiegiftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {

                if(res.status != 1) {
                    var error_msg = '';

                    if(res.status == -1) {//活动未开启
                        error_msg = lang('activity_closed');
                    } else {
                        error_msg = lang('undefined_error');
                    }

                    xGame.toast.show(error_msg);
                    $('#worldloading').hide();
                    return false;
                }

                //正常流程
                that.e = xGame.loadPopTmpl('newbiegift');

                //加载数据
                var mynowpage = -1;
                var lastpage = -1;
                var endtimes = [];
                for(var i = 0; i < res.list.length; i++) {
                    var _day = res.list[i].id;
                    var dom_day = that.e.find('#dayreward'+_day);

                    //需要帮用户自动定位到第一个可领取的页面
                    if(mynowpage == -1 && res.list[i].open == 1) {
                        mynowpage = _day;
                    }
                    if(lastpage == -1 && res.list[i].open == 0) {
                        lastpage = _day;
                    }
                    //奖励
                    for(var ii = 1; ii <= 2; ii++) {
                        dom_day.find('.rewardp'+ii).addClass('type'+res.list[i]['type'+ii]).html(res.list[i]['value'+ii]);
                    }
                    //箱子和按钮
                    var _box_class = res.list[i].open == 2 ? 'rewardbox open' : 'rewardbox';
                    dom_day.find('.rewardbox').attr('class', _box_class);
                    var _btn_class = res.list[i].open == 1 ? 'btn fetch_btn pop_btn_middle' : 'btn fetch_btn pop_btn_middle_disable';
                    var _btn_word = '';
                    if(res.list[i].open == 1) {
                        _btn_word = lang('newbiegift_enable_fetch');
                    } else if(res.list[i].open == 2) {
                        _btn_word = lang('newbiegift_fetched');
                    } else if(res.list[i].open == 3) {
                        _btn_word = lang('newbiegift_unfetched')
                    } else {
                        _btn_word = lang('newbiegift_unopen');
                    }
                    dom_day.find('.pop_btn_middle').attr('class', _btn_class).html(_btn_word);

                    //时间
                    endtimes[i] = res.list[i].endtime;

                    _box_class = null;
                    _btn_class = null;
                    dom_day = null;
                }
                xGame.updateCd('newbiegift', endtimes);

                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    newbiegiftpop.hide();
                    e.preventDefault();
                });
                that.e.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend', function() {
                    if (xGame.checkLock()) {
                        return false;
                    }
                    var fetchday = $(this).attr('day');
                    that.fetch_reward(fetchday);//领奖
                });

                that.e.show();
                $('#worldloading').hide();

                that.myScroll = xGame.carousel('newbiegift_carousel', 514, 3, function() {
                    var nowpage = that.myScroll.currPageX + 1;
                    that.e.find('#newbiegift_page').attr('class', 'content page' + nowpage);
                });
                //帮用户滑动到当前页
                if(mynowpage != -1) {//有未开启的
                    console.log('mynowpage:' + mynowpage);
                    //var gopx = 514 * (mynowpage - 1) * -1;
                    //that.myScroll.scrollTo(gopx);
                    var gopx = mynowpage - 1;
                    that.myScroll.scrollToPage(gopx);
                } else if(lastpage != -1) {//到下一个该领取的
                    console.log('lastpage:' + lastpage);
                    var gopx = lastpage - 1;
                    that.myScroll.scrollToPage(gopx);
                } else {//直接到最后一页
                    that.myScroll.scrollToPage(2);
                }
                that.e.find('#newbiegift_page').undelegate('.turn_btn', 'click').delegate('.turn_btn', 'click', function() {
                    if(xGame.checkLock(true)) {
                        return false;
                    }

                    var dirc = $(this).attr('id');
                    var go;
                    if(dirc == 'turn_left') {
                        if(that.myScroll.currPageX <= 0) {
                            return false;
                        }
                        go = that.myScroll.currPageX - 1;
                    } else {
                        if(that.myScroll.currPageX >= 2) {
                            return false;
                        }
                        go = that.myScroll.currPageX + 1;
                    }

                    that.turn_page(go);
                });

            };

            //加载数据
            loadContents({
                'c': 'newrolereward',
                'm': 'index'
            },
            Func);
            Func = null;
        },

        /* 跳转到某一页 */
        turn_page: function(go) {
            this.myScroll.scrollToPage(go);
            xGame.islock = true;
            this.isTimeout.push(setTimeout(function() {
                xGame.unlock();
            }, 200));
        },

        /* 领奖励 */
        fetch_reward: function(fetchday) {
            var that = this;

            var fetch_render = function(res) {
                if(res.status != 1) {
                    var error_msg = '';
                    if(res.status == -1) {
                        error_msg = lang('activity_closed');
                    } else if(res.status == -3) {
                        error_msg = lang('had_receive');
                    } else {
                        error_msg = lang('undefined_error');
                    }
                    xGame.toast.show(error_msg);
                    xGame.unlock();
                    return false;
                }
                //正常逻辑
                var callback = function() {
                    //提示、更新金钱、更新按钮、延迟跳转页面
                    xGame.toast.show(lang('reward_success'));
                    var updatedata = {};
                    if(res.all_gold) {
                        updatedata['gold'] = res.all_gold;
                    }
                    if(res.all_silver) {
                        updatedata['silver'] = res.all_silver;
                    }
                    player.updateinfo(updatedata);
                    var dom_day = that.e.find('#dayreward'+fetchday);
                    dom_day.find('.rewardbox').attr('class', 'rewardbox open');
                    dom_day.find('.btn.pop_btn_middle').attr('class', 'btn fetch_btn pop_btn_middle_disable').html(lang('newbiegift_fetched'));
                    dom_day = null;
                    xGame.unlock();

                    if(fetchday < 3) {
                        xGame.islock = true;
                        that.isTimeout.push(setTimeout(function() {
                            that.turn_page(fetchday);//iscroll的page是从0开始，所以下一页的下标刚刚是当前页码
                        }, 300));
                    } else {
                        player.hideActivity('newbiegift');
                    }
                };
                that.openBoxAnim(that.e.find('#dayreward'+fetchday), callback);
                
            };
            loadContents({
                'c': 'newrolereward',
                'm': 'get_reward'
            },
            fetch_render);
            fetch_render = null;
        },

        openBoxAnim: function(nowdom, callback) {
            var movieclip = new movieClipClass({
                "img": staticUrl + 'img/battle/resutl_box_stars.png',
                "width": 261,
                "height": 233,
                "x": 20,
                "y": 46,
                "loop": false,
                "endDestory": true,
                "callback": callback,
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
            nowdom.append(movieclip.obj);
            movieclip.play();
        },

        unbindAll: function() {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.undelegate('.pop_btn_middle', 'touchend');
            this.e.find('#newbiegift_page').undelegate('.turn_btn', 'click');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('newbiegift', [0, 0, 0]);
            if (this.myScroll) {
               this.myScroll.destroy();
               delete this.myScroll;
            }
            if(this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
        },
    };
})();