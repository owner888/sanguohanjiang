/* 玉石作坊 */
(function() {
    workshopPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        this.listDom;
        this.w; //进度条的宽度
        this.choseDom;
		this.jade;/*当前玉石数量*/
        this.addition = 0;//玉石参量加成
    };

    workshopPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initFirst(res);
                } else if (res.status == -1) {
                    xGame.toast.show(lang('get_workshop_fail'));
                    $('#worldloading').hide();
                } else if (res.status == 2) {
                    xGame.toast.show(lang('lv_can_not_open_func'));
                    $('#worldloading').hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'workshop',
                'm': 'index'
            },
            initRender, true);
            initRender = null;

        },

        /* 初始化界面 */
        initFirst: function(res) {

            var that = this;

            if (that.e != null) {
                return false;
            }

            that.e = xGame.loadPopTmpl('workshop');
            that.listDom = that.e.find('#workshoppop_list');
            that.w = 430;
            that.jade = parseInt(res.all_jade);
            that.addition = res.addition;

            //绑定关闭事件
            that.e.find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                workshoppop.hide();
                e.preventDefault();
            });

            //列表
            var html = '',
            hide = true;
            //
            for (var i = 1; i < 9; i++) {
                if (res.list[i] != undefined) {
                    html += '<li class="lv' + res.list[i].level + '" lv="' + res.list[i].level + '" site="' + res.list[i].site + '">';
                    if (res.list[i].full_level != 1) {
                        html += '<button class="pop_btn_small btn workshop_lvup">' + lang('level_up') + '</button>';
                    }
                    html += '<p class="t">' + lang('caiji_shangxian') + '</p>';
                    html += '<span style="width:' + (that.w * (res.list[i].yield / res.list[i].volume) + 30) + 'px"></span>';
                    html += '<div class="work_bg"></div>';
                    html += '<p class="progress_num"><em class="now_num">' + res.list[i].yield + '</em>/<em class="total_num">' + res.list[i].volume + '</em></p>';
                    html += '<p class="produce">' + lang('hour_changliang') + res.list[i].add + (that.addition > 0 ? '<em style="color:red;"> ( '+that.addition +'% '+lang('workshop_country_addition')+')</em>' : '') + '</p>';
                    html += '<p class="current_num">' + lang('current_get') + res.list[i].yield + '</p>';
                    html += '<button class="pop_btn_middle btn gain" id="gain">' + lang('harvest') + '</button>';
                    html += '<em class="name">' + lang('workshop') + i + '</em>';
                    html += '</li>';
                } else {
                    html += '<li class="lock" site="' + i + '">';
                    html += '<button class="pop_btn_small btn workshop_lvup" style="display:none;">' + lang('level_up') + '</button>';
                    html += '<p class="t" style="display:none;">' + lang('caiji_shangxian') + '</p>';
                    html += '<span></span>';
                    html += '<div class="work_bg"></div>';
                    html += '<p class="progress_num"></p>';
                    html += '<p class="produce"></p>';
                    html += '<p class="current_num"></p>';
                    if (hide) {
                        html += '<button class="pop_btn_middle btn unlock" id="unclock">' + lang('unlock') + '</button>';
                        hide = false;
                    } else {
                        html += '<button class="pop_btn_middle btn unlock" id="unclock" style="display:none;">' + lang('unlock') + '</button>';
                    }
                    html += '<em class="name">' + lang('workshop') + i + '</em>';
                    html += '</li>';
                }
            }
            that.listDom.html(html);
            html = null;
            that.myScroller = new iScroll('workshoppop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.e.find('#ws_now_jade').html('<span>' + res.all_jade + lang('jade') + '</span>');

            that.e.show();

            that.myScroller.refresh();
            that.bindAll();

            $('#worldloading').hide();
        },

        bindAll: function() {
            var that = this;

            that.listDom.undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.choseDom = $(this).parent();
                that.levelUpFunc(that.choseDom.attr('site'), parseInt(that.choseDom.attr('lv')));
            });

            that.listDom.undelegate('.gain', 'touchend').delegate('.gain', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.choseDom = $(this).parent();
                that.getFunc(that.choseDom.attr('site'));
            });

            that.listDom.undelegate('.unlock', 'touchend').delegate('.unlock', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.choseDom = $(this).parent();
                that.unlockFunc();
            });

            that.e.find('#go_gambling').unbind('touchend').bind('touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                workshoppop.hide();
                xGame.showpop('slyz');
            });
        },

        showNum: function(num) {
            var that = this;
            if (that.jade != parseInt(num)) {
                xGame.doAnimate2(that.e.find('#ws_now_jade'), 'changcolor', 100, 7,
                function() {
                    if (that.e) {
                        that.e.find('#ws_now_jade').html('<span>' + num + lang('jade') + '</span>');
                    }
                });
                that.jade = parseInt(num);
            }
        },

        levelUpFunc: function(site, lv, type) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('caiji_site_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('caiji_site_close'));
                    break;
                case - 3 : if (res.viplevel) {
                        xGame.toast.show(lang('vip_lv_to_caiji', [res.viplevel]));
                    } else {
                        xGame.toast.show(lang('vip_lv_too_low'));
                    }
                    break;
                case - 4 : xGame.toast.show(lang('gold_not_enough'));
                    break;
                case - 5 : xGame.toast.show(lang('level_up_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('lv_is_full'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    if (type == undefined) {
                        var html = '<ul class="workshop_info">';
                        html += '<li><em class="t1"></em><em class="t2 tn" style="color:' + helper.getItemColor(lv + 1) + '">' + res.now_name + '</em><em class="t3 tn" style="color:' + helper.getItemColor(lv + 2) + '">' + res.name + '</em></li>';
                        html += '<li><em class="t1">' + lang('shouyilv') + '</em><em class="t2">' + res.now_result + '%</em><em class="t3">' + res.result + '%</em></li>';
                        html += '<li><em class="t1">' + lang('hour_changliang') + '</em><em class="t2">' + res.now_add + '</em><em class="t3">' + res.add + '</em></li>';
                        html += '<li><em class="t1">' + lang('cangchu_shangxian') + '</em><em class="t2">' + res.now_volume + '</em><em class="t3">' + res.volume + '</em></li>';
                        html += '</ul>';
                        xGame.confirm.show('<b style="color:#724100;">' + lang('lv_up_zuofang') + '</b>', lang('lvup_zuofang_ask', [res.price]) + html, 2,
                        function() {
                            xGame.confirm.hide(function() {
                                that.levelUpFunc(site, lv, 1);
                            });
                        },
                        function() {
                            xGame.confirm.hide();
                        });
                        html = null;
                    } else {
                        that.choseDom.find('.produce').html(lang('hour_changliang') + res.add);
                        that.choseDom.find('.current_num').html(lang('current_get') + res.yield);
                        that.choseDom.find('.progress_num').html('<em class="now_num">' + res.yield + '</em>/<em class="total_num">' + res.volume + '</em>');
                        that.choseDom.attr({
                            'class': 'lv' + (lv + 1),
                            'lv': (lv + 1)
                        });
                        player.updateinfo({
                            "gold": parseInt(res.all_gold)
                        });
                        that.choseDom.find('span').width(that.w * (res.yield / res.volume) + 30);
                        if (res.full_level == 1) {
                            that.choseDom.find('.workshop_lvup').remove();
                        }
                        xGame.toast.show(lang('lv_complete'));
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            if (type == undefined) {
                loadContents({
                    'c': 'workshop',
                    'm': 'levelup',
                    's': site
                },
                Func);
            } else {
                loadContents({
                    'c': 'workshop',
                    'm': 'levelup',
                    's': site,
                    't': type
                },
                Func);
            }
            Func = null;
        },

        unlockFunc: function(type) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_caiji_site_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('caiji_site_all_open'));
                    break;
                case - 3 : xGame.toast.show(lang('caiji_site_open'));
                    break;
                case - 4 : if (res.viplevel) {
                        xGame.toast.show(lang('vip_lv_to_caiji', [res.viplevel]));
                    } else {
                        xGame.toast.show(lang('vip_lv_too_low'));
                    }
                    break;
                case - 5 : xGame.toast.show(lang('gold_not_enough'));
                    break;
                case - 6 : xGame.toast.show(lang('open_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    if (type == undefined) {
                        xGame.confirm.show('<b style="color:#724100;">' + lang('unlock_zuofang') + '</b>', lang('unlock_ask', [res.price]), 2,
                        function() {
                            xGame.confirm.hide(function() {
                                that.unlockFunc(1);
                            });
                        },
                        function() {
                            xGame.confirm.hide();
                        });
                    } else {
                        player.updateinfo({
                            "gold": parseInt(res.all_gold)
                        });
                        that.choseDom.attr({
                            'class': 'lv0',
                            'lv': '0'
                        });
                        that.choseDom.find('.workshop_lvup').css('display', 'block');
                        that.choseDom.find('.t').css('display', 'block');
                        that.choseDom.find('.progress_num').html('<em class="now_num">' + res.yield + '</em>/<em class="total_num">' + res.volume + '</em>');
                        that.choseDom.find('.produce').html(lang('hour_changliang') + res.add).css('display', 'block');
                        that.choseDom.find('.current_num').html(lang('current_get') + res.yield).css('display', 'block');
                        that.choseDom.find('.unlock').attr('class', 'pop_btn_middle btn gain').html(lang('harvest'));
                        that.choseDom.next().find('.unlock').css('display', 'block');
                        xGame.toast.show(lang('unlock_complete'));
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            if (type == undefined) {
                loadContents({
                    'c': 'workshop',
                    'm': 'open_workshop_site'
                },
                Func);
            } else {
                loadContents({
                    'c': 'workshop',
                    'm': 'open_workshop_site',
                    't': type
                },
                Func);
            }
            Func = null;
        },

        getFunc: function(site) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('caiji_site_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('caiji_site_close'));
                    break;
                case - 3 : xGame.toast.show(lang('current_0_for_get'));
                    break;
                case - 4 : xGame.toast.show(lang('get_fail'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    that.showNum(res.all_jade);
                    that.choseDom.find('.now_num').html('0');
                    that.choseDom.find('.current_num').html(lang('current_get') + '0');
                    that.choseDom.find('span').width(30);
                    xGame.toast.show(lang('you_get_jade', [res.add]));
                    if (player.info.onworkshop == 1) { //隐藏主城里气泡
                        player.info.onworkshop = 0;
                        $('#workshop_building').children('.popo').hide();
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'workshop',
                'm': 'get_reward',
                's': site
            },
            Func);
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.find('#go_gambling').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.listDom) {
                this.listDom.undelegate('.pop_btn_small', 'touchend');
                this.listDom.undelegate('.gain', 'touchend');
                this.listDom.undelegate('.unlock', 'touchend');
                this.listDom.remove();
                this.listDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.myScroller) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            
            this.w = this.choseDom = this.jade = null;
            this.unbindAll();
        },
    };
})();