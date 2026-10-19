/* VIP奖励 */
(function() {
    viprewardPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        this.anim = null;
    };
    viprewardPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (res.status == 1) {
                    that.buildPage(res);
                } else if (res.status == -2) {
                    xGame.toast.show(lang('activity_closed'));
                    $('#worldloading').hide();
                } else {
                    xGame.toast.show(lang('no_activity'));
                    $('#worldloading').hide();
                }
                that = null;
            };
            loadContents({
                'c': 'vipwage',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        buildPage: function(data) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('vipreward');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                viprewardpop.hide();
                e.preventDefault();
                return false;
            });
            that.e.find('#viprewardpop_wrap').undelegate('.fetch_btn', 'touchend').delegate('.fetch_btn', 'touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.fetchFunc();
                e.preventDefault();
                return false;
            });
            that.e.find('#viprewardpop_wrap').undelegate('.go_pay', 'touchend').delegate('.go_pay', 'touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                that.hide();
                xGame.showpop('vip');
                e.preventDefault();
                return false;
            });

            var html = '';
            for(var i = 0; i < data.list.length; i++) {
                html += '<li class="vipreward_item">';
                html +=     '<div class="vip"><div class="viplevel_wrap">' + this.createVIPimage(data.list[i].vip) + '</div></div>';
                for(var x = 1; x <= 3; x ++) {
                    if(data.list[i]['get'+x] > 0) {
                        if(data.list[i]['get'+x] >= 1000000) {
                            data.list[i]['get'+x] = player.getWan(data.list[i]['get'+x]);
                        }
                        html +=     '<div class="gird gird'+x+ ' t' + data.list[i]['type'+x] + '">x' + data.list[i]['get'+x] + '</div>';
                    }
                }
                html += '</li>';
            }
            that.e.find('#viprewardpop_list').html(html);

            that.myScroller = new iScroll('viprewardpop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.myScroller.refresh();
            //针对VIP滑动
            player.info.vip = parseInt(player.info.vip);
            if(player.info.vip > 4) {
                var my = (player.info.vip - 4) * - 71 + (300 - 71 * 4);
                that.myScroller.scrollTo(0, my);
            }

            that.e.find('#viprewardpop_now').html('VIP' + player.info.vip);

            if(typeof data.my_reward.length == 'undefined') {//当有奖励的时候
                var html = '';
                for(var z = 1; z <= 3; z ++) {
                    if(data.my_reward['get'+z] > 0) {
                        if(data.my_reward['get'+z] >= 1000000) {
                            data.my_reward['get'+z] = player.getWan(data.my_reward['get'+z]);
                        }
                        html += '<div class="rewardbox_wrap" id="rewardbox_wrap'+z+'">';
                        html +=     '<span class="t' + data.my_reward['type'+z] + '"></span>';
                        html +=     '<p>x' + data.my_reward['get'+z] + '</p>';
                        html += '</div>';
                    }
                }
                that.e.find('#viprewardpop_wrap').append(html);
            } else {
                that.e.find('#viprewardpop_tips').show();
            }

            if(data.get == 1) {
                that.e.find('#fetch_btn').attr('class', 'pop_btn_middle fetch_btn btn').html(lang('vipreward_btn1'));
            } else if(data.get == 0) {
                that.e.find('#fetch_btn').attr('class', 'pop_btn_middle go_pay btn').html(lang('vipreward_btn0'));
            } else {
                that.e.find('#fetch_btn').attr('class', 'pop_btn_middle_disable btn').html(lang('vipreward_btn'+data.get));
            }

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();


        },

        fetchFunc: function() {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if(res.status == 1) {
                    that.e.find('#fetch_btn').attr('class', 'pop_btn_middle_disable btn').html(lang('vipreward_btn2'));
                    xGame.toast.show(lang('vipreward_fetch_ok'));
                    player.updateinfo({
                        'gold': parseInt(res.all_gold),
                        'silver': parseInt(res.all_silver),
                        'act': parseInt(res.all_act),
                    });
                    var rewardnums = that.e.find('.rewardbox_wrap').length;
                    that.playAnim(rewardnums);
                } else {
                    var error_msg = '';
                    switch(res.status) {
                        case -1: error_msg = lang('vipreward_fetch_no_salary');break;
                        case -3: error_msg = lang('vipreward_fetch_ever');break;
                        case -5: error_msg = lang('activity_closed');break;
                        default: error_msg = lang('vipreward_fetch_error');break;
                    }
                    xGame.toast.show(error_msg);
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'vipwage',
                'm': 'get_vip_wage'
            },
            Func); //加载数据
            Func = null;
        },

        createVIPimage: function(vip) {
            var html = '';
            var vip = vip + '';
            for(var i = 0; i < vip.length; i ++) {
                html += '<span style="background-position: -' + (vip[i] * 30 + 4) + 'px 0;"></span>';
            }
            return html;
        },

        playAnim: function(rewardnums) {
            var that = this;


            that.anim = [];
            for (var i = 1; i <= rewardnums; i++) {
                //that.anim[i] = {};
                var obj = new movieClipClass({
                    "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                    "width": 93,
                    "height": 90,
                    "x": -6,
                    "y": -5,
                    "loop": 3,
                    "endDestory": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 100
                    },
                    {
                        rect: [93, 0],
                        interval: 100
                    },
                    {
                        rect: [186, 0],
                        interval: 100
                    },
                    {
                        rect: [279, 0],
                        interval: 100
                    },
                    {
                        rect: [0, 90],
                        interval: 100
                    },
                    {
                        rect: [93, 90],
                        interval: 100
                    },
                    {
                        rect: [186, 90],
                        interval: 100
                    },
                    ]
                });
                //开始播放
                obj.appendTo(that.e.find('#rewardbox_wrap' + i));
                obj.play();
                that.anim.push(obj);
            }
        },

        hideAnim: function() {
            if(this.anim == null) {
                return false;
            }
            for (var i = 0; i <= this.anim.length; i++) {
                if(this.anim[i] && this.anim[i].obj) {
                    this.anim[i].destory();
                    this.anim[i] = null;
                }
            }
            this.anim = null;
        },

        hide: function() {
            xGame.unlock();
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            this.isshow = false;
            this.hideAnim();
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#viprewardpop_wrap').undelegate('.fetch_btn', 'touchend');
                this.e.find('#viprewardpop_wrap').undelegate('.go_pay', 'touchend');
                this.e.remove();
                this.e = null;
            }
            
        },
    };
})();