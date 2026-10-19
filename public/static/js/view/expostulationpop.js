/* 谏言 */
(function() {
    expostulationPopClass = function() {
        this.e;
        this.isshow = false;
        this.ulDom;
        this.scroller;
        this.btnDom;
        this.hasPower; //是否有审核权限
        this.infoBox;
        this.smallUlDom;
        this.isSubmit; //是否提交了谏言
        /*list里面的support;
		 *1是已经支持过了，2是我的谏言不用支持，0是可支持
         */
        this.free_support; //1是可免费支持，0是不可免费支持
        this.hasPurple;//是否有紫色谏言
        this.isTimeout = null;
    };

    expostulationPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                if (res.status == 1 || res.status == -2) {
                    that.free_support = res.free_support;
                    that.initFirst(res.list);
                } else if (res.status == -1) {
                    that.exceptionFunc();
                    $('#worldloading').hide();
                } else if (res.status == -3) {
                    xGame.toast.show(lang('get_expostulation_tmpl_fail'));
                    $('#worldloading').hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'expostulation',
                'm': 'index'
            },
            initRender, true);
            initRender = null;

        },

        /* 初始化界面 */
        initFirst: function(data) {

            var that = this;

            if (that.e != null) {
                return false;
            }

            that.e = xGame.loadPopTmpl('expostulation');
            that.ulDom = that.e.find('#expostulation_list');
            that.btnDom = that.e.find('#expostulation_btns');

            that.infoBox = that.e.find('#expostulation_infobox');
            that.smallUlDom = that.e.find('#expostulation_list_s');

            //绑定关闭事件
            that.e.find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                expostulationpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.fillList(data, 1);

            that.e.show();

            that.bindAll();

            $('#worldloading').hide();
            that.isshow = true;
        },

        /*type=1,谏言列表；type=2，采纳谏言*/
        fillList: function(list, type) {
            var that = this;

            that.btnDom.attr('type', type);
            that.btnDom.attr('class', 'expostulation_btns type' + type);

            that.ulDom.html(that.getHtml(list, type));
            html = null;

            if (list.length > 3) {
                if (that.scroller == undefined) {
                    that.scroller = new iScroll('expostulation_scroller', {
                        snap: 'li',
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                }
                that.scroller.refresh();
                that.scroller.scrollTo(0, 0);
            } else {
                that.hideScroller(that.scroller);
                that.scroller = null;
            }

            if (list.length == 0) {
                that.noData();
            } else {
                that.e.find('#expostulation_scroller_bg').removeClass('no_data');
            }

        },

        getHtml: function(list, type) {
            var that = this;
            var html = '',
            name = '';;
            for (var i = 0; i < list.length; i++) {
                name = parseInt(list[i].get_type) == 1 ? lang('silver') : lang('original_soul');
                html += '<li class="li_' + list[i].gender + '">';
                html += '<p><em>' + list[i].job + '</em>【' + list[i].nickname + '】<em>' + lang('expostulation') + '：</em><em class="type" style="color:' + helper.getItemColor(parseInt(list[i].quality)) + ';">' + list[i].name + '</em></p>';
                html += '<p><em>' + lang('expostulation_info') + '</em>' + list[i].description + '<b>' + list[i].value + '</b>' + lang('times') + '&nbsp;&nbsp;' + (list[i].plan != undefined ? '(' + list[i].plan + '/' + list[i].value + ')': '') + '</p>';
                html += '<p><em>' + lang('expostulation_reward') + '</em><em class="reward r' + list[i].get_type + '">' + list[i].put_value + name + '，' + list[i].put_contribute + lang('country_contribut') + '</em></p>';
                html += '<p><em>' + lang('expostulation_support') + '</em><em class="reward r' + list[i].get_type + '">' + list[i].sup_value + name + '，' + list[i].sup_contribute + lang('country_contribut') + '</em></p>';
                if (type == 1) {
                    if (list[i].support == 1 || list[i].support == 2) { //已支持过或者是自己的谏言
                        if (list[i].status == 2) {
                            html += '<button class="pop_btn_middle btn reward" taskid="' + list[i].id + '">' + lang('receive') + '</button>';
                        } else {
                            html += '<button class="pop_btn_middle_disable btn running" taskid="' + list[i].id + '">' + lang('is_running') + '</button>';
                        }
                    } else if (list[i].support == 0) { //可支持
                        /*根据free_support判断是否是免费支持*/
                        html += '<div class="' + (that.free_support == 1 ? 'free': 'pay') + '"><span class="s_free">' + lang('first_support_free') + '</span>';
                        html += '<span class="gold">' + list[i].sup_price + lang('gold') + '</span></div>';
                        html += '<button class="pop_btn_middle btn support" taskid="' + list[i].id + '">' + lang('support') + '</button>';
                    }
                } else {
                    if (that.hasPower) {
                        html += '<button class="pop_btn_middle btn accept" taskid="' + list[i].id + '">' + lang('accept') + '</button>';
                    }
                }

                html += '</li>';
            }
            name = null;
            return html;
        },

        noData: function() {
            this.hideScroller(this.scroller);
            this.scroller = null;

            this.ulDom.html('');
            this.e.find('#expostulation_scroller_bg').addClass('no_data');
        },

        bindAll: function() {
            var that = this;

            /*谏言列表*/
            that.btnDom.undelegate('#expostulation_list_btn', 'click').delegate('#expostulation_list_btn', 'click',
            function() {
                if (xGame.checkLock(true) || that.btnDom.attr('type') == '1') {
                    return false;
                }
                that.indexFunc();
            });
            /*支持按钮*/
            that.ulDom.undelegate('.support', 'touchend').delegate('.support', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.agreeFunc($(this));
            });
            /*领取按钮*/
            that.ulDom.undelegate('.btn.reward', 'touchend').delegate('.btn.reward', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.rewardFunc($(this));
            });

            /*采纳谏言*/
            that.btnDom.undelegate('#expostulation_accept_btn', 'click').delegate('#expostulation_accept_btn', 'click',
            function() {
                if (xGame.checkLock(true) || that.btnDom.attr('type') == '2') {
                    return false;
                }
                that.acceptListFunc();
            });
            /*采纳按钮*/
            that.ulDom.undelegate('.accept', 'touchend').delegate('.accept', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.acceptFunc($(this));
            });

            /*刷新*/
            that.infoBox.undelegate('#expostulation_refresh', 'touchend').delegate('#expostulation_refresh', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var obj = $(this);
                if (that.hasPurple) {
                    xGame.confirm.show('', lang('refresh_expostulation_ask'), 2,
                    function() {
                        xGame.confirm.hide();
                        that.refreshFunc(obj);
                    },
                    function() {
                        xGame.confirm.hide();
                    },lang('refresh'),lang('close'));
                } else {
                    that.refreshFunc(obj);
                }
            });
            /*谏言按钮*/
            that.infoBox.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.sayFunc($(this));
            });
            /*提出谏言*/
            that.btnDom.undelegate('#expostulation_ask_btn', 'click').delegate('#expostulation_ask_btn', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                
                that.sayListFunc();
            });
            /*小窗口关闭*/
            that.infoBox.undelegate('#hidebtn2', 'touchend').delegate('#hidebtn2', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.isTimeout = setTimeout(function() {
                    that.infoBoxHide();
                },
                400);
            });

        },

        hideScroller: function(obj) {
            if (obj != undefined) {
                obj.destroy();
            }
        },

        infoBoxShow: function(list) {
            this.smallUlDom.html(this.getHtmlSmall(list));
            this.infoBox.css('display', 'block');
        },

        getHtmlSmall: function(list, sp) {
            var html = '',
            name = '';
            this.hasPurple = false;
            for (var i = 0; i < list.length; i++) {
                name = parseInt(list[i].get_type) == 1 ? lang('silver') : lang('original_soul');
                html += (sp == true ? '' : '<li>');
                html += '<p><em>' + lang('expostulation') + '：</em><em class="type" style="color:' + helper.getItemColor(parseInt(list[i].quality)) + ';">' + list[i].name + '</em></p>';
                html += '<p><em>' + lang('expostulation_info') + '</em>' + list[i].description + '<b>' + list[i].value + '</b>' + lang('times') + '</p>';
                html += '<p><em>' + lang('expostulation_reward') + '</em><em class="reward r' + list[i].get_type + '">' + list[i].put_value + name + '，' + list[i].put_contribute + lang('country_contribut') + '</em></p>';
                html += '<p><em>' + lang('expostulation_support') + '</em><em class="reward r' + list[i].get_type + '">' + list[i].sup_value + name + '，' + list[i].sup_contribute + lang('country_contribut') + '</em></p>';
                html += '<span class="gold">' + list[i].put_price + lang('gold') + '</span>';
                html += '<button class="pop_btn_middle btn" sayid="' + list[i].id + '">' + lang('expostulation') + '</button>';
                html += (sp == true ? '' : '</li>');
                if (this.hasPurple == false && list[i].quality == 5) {
                    this.hasPurple = true;
                }
            }
            name = null;
            return html;
        },

        infoBoxHide: function() {
            this.infoBox.css('display', 'none');
            this.smallUlDom.html('');
            if (this.isSubmit && this.btnDom.hasClass('type2')) {
                this.acceptListFunc();
            }
        },

        /*谏言列表*/
        indexFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                if (res.status == 1 || res.status == -2) {
                    that.fillList(res.list, 1);
                } else if (res.status == -1) {
                    that.exceptionFunc();
                } else if (res.status == -3) {
                    xGame.toast.show(lang('get_expostulation_tmpl_fail'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'expostulation',
                'm': 'index'
            },
            Func);
            Func = null;
        },

        /*支持谏言*/
        agreeFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 7 : xGame.toast.show(lang('support_lv_low_for_soul'));
                    break;
                case - 6 : xGame.toast.show(lang('gold_not_enough'));
                    break;
                case - 5 : xGame.toast.show(lang('self_expostulation'));
                    break;
                case - 4 : xGame.toast.show(lang('support_lv_low_for_business'));
                    break;
                case - 3 : xGame.toast.show(lang('get_expostulation_tmpl_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_expostulation_info_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    player.updateinfo({
                        'gold':
                        res.all_gold
                    });
                    if (that.free_support == 1) {
                        that.free_support = 0;
                        that.ulDom.find('.free').attr('class', 'pay');
                    }
                    obj.html(lang('is_running')).attr('class', 'pop_btn_middle_disable btn running');
                    obj.parent().find('div').remove();
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'expostulation',
                'm': 'support',
                'id': obj.attr('taskid')
            },
            Func);
            Func = null;
        },

        /*领取谏言奖励*/
        rewardFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 5 : that.exceptionFunc();
                    break;
                case - 4 : xGame.toast.show(lang('reward_had_receive'));
                    break;
                case - 3 : xGame.toast.show(lang('have_not_support'));
                    break;
                case - 2 : xGame.toast.show(lang('task_uncompleted'));
                    break;
                case - 1 : xGame.toast.show(lang('expostulation_not_exist'));
                    break;
                case - 8 : xGame.toast.show(lang('countrypop_contribution_limit'));
                    break;
                case 1:
                    if (res.get_type == 1) {
                        xGame.toast.show(lang('receive_get_silver2', [res.get_value,res.get_contribute]));
                    } else {
                        xGame.toast.show(lang('receive_get_soul2', [res.get_value,res.get_contribute]));
                    }
                    player.updateinfo({
                        'silver': res.all_silver
                    });
                    obj.parent().remove();
                    if (that.ulDom.find('li').size() <= 3) {
                        if (that.scroller != undefined) {
                            that.hideScroller(that.scroller);
                            that.scroller = null;
                        } else {
                            if (that.ulDom.find('li').size() == 0) {
                                that.noData();
                            }
                        }
                    } else {
                        if (that.scroller != undefined) {
                            that.scroller.refresh();
                        }
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
                'expostulation',
                'm': 'get_reward',
                'id': obj.attr('taskid')
            },
            Func);
            Func = null;
        },

        /*采纳谏言*/
        acceptFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 9 : xGame.toast.show(lang('expostulationpop_adopt_limit'));
                    break;
                case - 7 : xGame.toast.show(lang('expostulation_accpet_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('expostulation_same'));
                    break;
                case - 5 : xGame.toast.show(lang('expostulation_complete'));
                    break;
                case - 4 : xGame.toast.show(lang('expostulation_is_accept'));
                    break;
                case - 3 : xGame.toast.show(lang('get_expostulation_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('job_too_low'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    obj.parent().remove();
                    if (that.ulDom.find('li').size() <= 3) {
                        if (that.scroller != undefined) {
                            that.hideScroller(that.scroller);
                            that.scroller = null;
                        } else {
                            if (that.ulDom.find('li').size() == 0) {
                                that.noData();
                            }
                        }
                    } else {
                        if (that.scroller != undefined) {
                            that.scroller.refresh();
                        }
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
                'expostulation',
                'm': 'adopt',
                'id': obj.attr('taskid')
            },
            Func);
            Func = null;
        },

        /*采纳谏言列表*/
        acceptListFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {

                case - 3 : xGame.toast.show(lang('get_expostulation_tmpl_fail'));
                    break;
                case - 2 : that.fillList([], 2);
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    that.hasPower = true;
                    that.fillList(res.list, 2);
                    that.isSubmit = false;
                    break;
                case 2:
                    that.hasPower = false;
                    that.fillList(res.list, 2);
                    that.isSubmit = false;
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'expostulation',
                'm': 'authstr'
            },
            Func);
            Func = null;
        },

        /*提出谏言列表*/
        sayListFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : that.exceptionFunc();
                    break;
                case - 2 : xGame.toast.show(lang('get_expostulation_list_fail'));
                    break;
                case 1:
                    if (res.refresh == 1) {
                        that.infoBox.find('#expostulation_refresh').html(lang('free_refresh'));
                    } else {
                        that.infoBox.find('#expostulation_refresh').html(lang('refresh'));
                    }
                    that.infoBoxShow(res.list);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'expostulation',
                'm': 'expostulation_list'
            },
            Func);
            Func = null;
        },

        /*刷新谏言列表*/
        refreshFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('refresh_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('gold_not_enough'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    if (obj.html() == lang('free_refresh')) {
                        obj.html(lang('refresh'));
                    }
                    player.updateinfo({
                        'gold': res.all_gold
                    });
                    that.smallUlDom.html(that.getHtmlSmall(res.list));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'expostulation',
                'm': 'refresh'
            },
            Func);
            Func = null;
        },

        /*发起谏言*/
        sayFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case -9 : xGame.toast.show(lang('expostulationpop_list_limit'));
                    break;
                case -8 : xGame.toast.show(lang('expostulationpop_propose_limit'));
                    break;
                case -7 : xGame.toast.show(lang('lv_low_for_put_expostulation'));
                    break;
                case - 6 : xGame.toast.show(lang('add_expostulation_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('expostulation_same2'));
                    break;
                case - 4 : xGame.toast.show(lang('gold_not_enough'));
                    break;
                case - 3 : xGame.toast.show(lang('get_expostulation_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('expostulation_id_not_exist'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    player.updateinfo({
                        'gold':
                        res.all_gold
                    });
                    obj.parent().html(that.getHtmlSmall(res.list, true));
                    that.isSubmit = true;
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'expostulation',
                'm': 'put_expostulation',
                'id': obj.attr('sayid')
            },
            Func);
            Func = null;
        },

        /*异常处理：被提出国家时*/
        exceptionFunc: function() {
            player.info.country = 0;
            xGame.toast.show(lang('you_are_kicked_out'));
            if (this.isshow) {
                this.hide();
            }
            xGame.loadWorld('city');
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.btnDom) {
                this.btnDom.undelegate('#expostulation_list_btn', 'click');
                this.btnDom.undelegate('#expostulation_accept_btn', 'click');
                this.btnDom.undelegate('#expostulation_ask_btn', 'click');
                this.btnDom.remove();
                this.btnDom = null;
            }
            if (this.ulDom) {
                this.ulDom.undelegate('.support', 'touchend');
                this.ulDom.undelegate('.btn.reward', 'touchend');
                this.ulDom.undelegate('.accept', 'touchend');
                this.ulDom.remove();
                this.ulDom = null;
            }
            if (this.infoBox) {
                this.infoBox.undelegate('#expostulation_refresh', 'touchend');
                this.infoBox.undelegate('.pop_btn_middle', 'touchend');
                this.infoBox.undelegate('#hidebtn2', 'touchend');
                this.infoBox.remove();
                this.infoBox = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.isshow = false;          
            if (this.smallUlDom) {
                this.smallUlDom.remove();
                this.smallUlDom = null;
            }
            this.hasPower = this.isSubmit = this.free_support = null;
            this.hideScroller(this.scroller);

            this.scroller = null;
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            this.unbindAll();
            
        },
    };
})();