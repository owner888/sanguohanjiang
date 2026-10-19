/* 活动 */
(function() {
    activityPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        this.data = null;
    };
    activityPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (res.status == 1) {
                    that.data = res.list;
                    //that.data['tower'] = {'endtime': 0,'starttime': 0,'status': 1};
                    that.showlist(res);
                } else {
                    xGame.toast.show(lang('no_activity'));
                    $('#worldloading').hide();
                }
                that = null;
            };
            loadContents({
                'c': 'activity',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        showlist: function(res) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('activity');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                activitypop.hide();
                e.preventDefault();
                e.stopPropagation();

            });

            // 显示提示图标
            if (res.list.logined.popo == 1) {
                that.e.find('#activity_logined').children('.popo').show();
            }
            //世界boss提示图标
            if (res.list.boss.popo == 1) {
                that.e.find('#activity_boss').children('.popo').show();
            }
            //var arr = ['lottery', 'treasure', 'boss', 'drink','tower'];
            var arr = ['invitation', 'lottery', 'treasure', 'boss', 'drink', 'generaltask', 'elite'];
            for (var i = 0; i < arr.length; i++) {
                var myactivity = arr[i];
                var myactivity_dom = that.e.find('#activity_' + myactivity);

                if (typeof that.data[myactivity] != 'undefined' && that.data[myactivity].status != 0) {
                    if (that.data[myactivity].starttime != '') { //有时间限定
                        myactivity_dom.find('.target_time').html(that.data[myactivity].starttime + '-' + that.data[myactivity].endtime + (that.data[myactivity].starttime2 ? ('<br/>' + that.data[myactivity].starttime2 + '-' + that.data[myactivity].endtime2) : ''));
                    }
                    if (that.data[myactivity].status == 1) {
                        myactivity_dom.find('.pop_btn_middle').html(lang('deliver'));
                    } else if (that.data[myactivity].status == 2) {
                        myactivity_dom.find('.pop_btn_middle').attr({
                            'class': 'pop_btn_middle_disable btn'
                        }).html(lang('time_not_yet'));
                    } else if (that.data[myactivity].status == 3) {
                        myactivity_dom.find('.pop_btn_middle').attr({
                            'class': 'pop_btn_middle_disable btn'
                        }).html(lang('today_end'));
                    }
                } else {
                    myactivity_dom.hide();
                }

                myactivity = null;
                myactivity_dom = null;
            }

            //针对91审核的修改
            if(typeof Platform != 'undefined' && (Platform.id == '91' ||  Platform.id == 'dj' ||  Platform.id == 'djn')) {
                that.e.find('#activity_slyz').find('.target_name').html('切石');
            }

            if (player.info.level < 16) {
                that.e.find('#go_lottery').attr('class', 'pop_btn_middle_disable btn').html('16' + lang('lv_open'));
            }
            if (player.info.level < 20) {
                that.e.find('#go_egg').attr('class', 'pop_btn_middle_disable btn').html('20' + lang('lv_open'));
            }
            if (player.info.level < 26) {
                that.e.find('#go_treasuremap').attr('class', 'pop_btn_middle_disable btn').html('26' + lang('lv_open'));
            }
            if (player.info.level < 31) {
                that.e.find('#go_elite').attr('class', 'pop_btn_middle_disable btn').html('31' + lang('lv_open'));
            }
            if (player.info.level < 36) {
                that.e.find('#go_treasure').attr('class', 'pop_btn_middle_disable btn').html('36' + lang('lv_open'));
            }
            if (player.info.level < 41) {
                that.e.find('#go_drink').attr('class', 'pop_btn_middle_disable btn').html('41' + lang('lv_open'));
                that.e.find('#go_dailytask').attr('class', 'pop_btn_middle_disable btn').html('41' + lang('lv_open'));
            }
            if (player.info.level < 46) {
                that.e.find('#go_boss').attr('class', 'pop_btn_middle_disable btn').html('46' + lang('lv_open'));
            }
            if (player.info.level < 50) {
                that.e.find('#go_invitation').attr('class', 'pop_btn_middle_disable btn').html('50' + lang('lv_open'));
            }
            if (player.info.level < 51) {
                that.e.find('#go_tower').attr('class', 'pop_btn_middle_disable btn').html('51' + lang('lv_open'));
                that.e.find('#go_overseastrade').attr('class', 'pop_btn_middle_disable btn').html('51' + lang('lv_open'));
            }
            if (player.info.level < 61) {
                that.e.find('#go_dailytrade').attr('class', 'pop_btn_middle_disable btn').html('61' + lang('lv_open'));
            }
            if (player.info.level < 71) {
                that.e.find('#go_generaltask').attr('class', 'pop_btn_middle_disable btn').html('71' + lang('lv_open'));
            }
            if (player.info.level < 81) {
                that.e.find('#go_heromanual').attr('class', 'pop_btn_middle_disable btn').html('81' + lang('lv_open'));
            }
            if (player.info.level < 91) {
                that.e.find('#go_slyz').attr('class', 'pop_btn_middle_disable btn').html('91' + lang('lv_open'));
            }
            if (player.info.level < 101) {
                that.e.find('#go_herothrone').attr('class', 'pop_btn_middle_disable btn').html('101' + lang('lv_open'));
            }
            if (player.info.level < 120) {
                that.e.find('#go_studfarm').attr('class', 'pop_btn_middle_disable btn').html('120' + lang('lv_open'));
            }
            if (player.info.level < 140) {
                that.e.find('#go_sanctum').attr('class', 'pop_btn_middle_disable btn').html('140' + lang('lv_open'));
            }
            if (player.info.level < 150) {
                that.e.find('#go_smash').attr('class', 'pop_btn_middle_disable btn').html('150' + lang('lv_open'));
            }
            if (player.info.level < 150) {
                that.e.find('#go_epic').attr('class', 'pop_btn_middle_disable btn').html('150' + lang('lv_open'));
            }
            if (player.info.level < 160) {
                that.e.find('#go_heaven').attr('class', 'pop_btn_middle_disable btn').html('160' + lang('lv_open'));
            }
            if (player.info.level < 160) {
                that.e.find('#go_strongachieve').attr('class', 'pop_btn_middle_disable btn').html('160' + lang('lv_open'));
            }

            that.e.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend').delegate('button.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var type = $(this).attr('type');
                switch (type) {
                case 'treasure':
                    if (player.info.level < 36) {
                        xGame.toast.show(lang('lv_36'));
                        return false;
                    }
                    that.hide();
                    xGame.loadWorld(type);
                    break;
                case 'tower':
                    if (player.info.level < 51) {
                        xGame.toast.show(lang('lv_51'));
                        return false;
                    }
                    that.hide();
                    xGame.loadWorld(type);
                    break;
                case 'boss':
                    if (player.info.level < 46) {
                        xGame.toast.show(lang('lv_46'));
                        return false;
                    }
                    //that.hide();
                    xGame.loadWorld(type, xGame.nowWorld);
                    break;
                case 'generaltask':
                    if (player.info.level < 71) {
                        xGame.toast.show(lang('lv_71'));
                        return false;
                    }
                    that.hide();
                    xGame.loadWorld(type);
                    break;
                case 'herothrone':
                    that.hide();
                    xGame.loadWorld(type);
                    break;
                case 'sanctum':
                    that.hide();
                    xGame.loadWorld(type);
                    break;
                case 'heaven':
                    if (player.info.level < 160) {
                        xGame.toast.show(lang('lv_160'));
                        return false;
                    }
                    that.hide();
                    xGame.loadWorld(type);
                    break;
                default:
                    xGame.showpop(type);
                    //that.hide();
                    break;
                }
            });

            // 苹果审核期间屏蔽部分功能
            player.hidePopInAppreview();

            that.myScroller = new iScroll('activitypop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.myScroller.refresh();

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();

            xGame.showGuide([1701]);

        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend');
                this.e.find('.content').find('.hidebtn').unbind('touchend');
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
            this.data = null;
            this.isshow = false;
            this.unbindAll();
            
        },
    };
})();