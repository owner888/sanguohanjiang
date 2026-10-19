/* 活动 */
(function() {
    dailytaskPopClass = function() {
        this.e;
        this.isshow = false;
        this.leftTime;
        this.taskSite; //有任务的话的位置，0 为没有任务
        this.taskType; //当前任务的类型，主要是5的时候单独处理
        this.tasklt; //当前任务的剩余次数
        this.taskat; //当前任务的总次数
        this.listDom;
    };
    dailytaskPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {

                if (res.status == 1) {
                    that.showlist(res);
                } else {
                    if (res.status == -1) {
                        xGame.toast.show(lang('lv_not_enough'));
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('get_equi_info_fail'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    $('#worldloading').hide();
                }
                that = null;
            }
            loadContents({
                'c': 'task',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        showlist: function(res) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('dailytask');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                dailytaskpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            /*判断是否免费*/
            if (parseInt(res.free_times) > 0) {
                that.e.find('#dt_money').hide();
                that.e.find('#dt_refresh').html(lang('free_refresh'));
            }

            that.taskSite = 0;
            that.leftTime = res.number;
            that.listDom = that.e.find('#dt_list');

            that.listDom.html(that.fillList(res));

            that.showNum();

            that.bindAll();

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();

        },

        showNum: function() {
            this.e.find('#dt_ltimes').html(this.leftTime);
        },

        fillList: function(res) {
            var that = this;
            var html = '';

            for (var i = 1; i < 4; i++) {
                if (that.taskSite == 0 && (res['taskstatus' + i] == 1 || res['taskstatus' + i] == 2)) {
                    that.taskSite = i;
                    that.taskType = parseInt(res['taskid' + i].type);
                    that.tasklt = (res['taskstatus' + i] == 2 ? res['taskid' + i].value: (res['taskstatus' + i] == 1 ? res['taskid' + i].plan: '0'));
                    that.taskat = res['taskid' + i].value;
                }
            }

            for (var i = 1; i < 4; i++) {
                html += '<li id="dt_task' + i + '">' + that.buildOne(res['taskid' + i], res['taskstatus' + i], i) + '</li>';
            }
            return html;
        },

        buildOne: function(data, status, i) {
            var that = this;

            var html = '';

            html += '<span id="dt_img" class="dt_role' + data.type + '" type="' + data.type + '">';
            html += '<span id="dt_status" ' + (status != 2 ? 'style="display:none;"': '') + '></span>';
            html += '</span>';
            html += '<span id="dt_name">' + data.title + '</span>';
            html += '<span id="dt_star" style="width: ' + parseInt(data.star) * 18 + 'px;" star="' + data.star + '"></span>';
            html += '<p id="dt_info_title">' + lang('task_condition') + '</p>';
            html += '<p id="dt_info_des">' + data.description + '</p>';
            if (status == 0) {
                html += '<p id="dt_info_now" class="accept">' + lang('completed') + '<span><em id="dt_task_lt">0</em>/<em id="dt_task_at">' + data.value + '</em></span><b>' + lang('limited_day') + '</b></p>';
            } else if (status == 1) {
                html += '<p id="dt_info_now" class="cancel">' + lang('completed') + '<span><em id="dt_task_lt">' + data.plan + '</em>/<em id="dt_task_at">' + data.value + '</em></span><b>' + lang('limited_day') + '</b></p>';
            } else if (status == 2) {
                html += '<p id="dt_info_now" class="receive">' + lang('completed') + '<span><em id="dt_task_lt">' + data.value + '</em>/<em id="dt_task_at">' + data.value + '</em></span><b>' + lang('limited_day') + '</b></p>';
            }
            html += '<span class="dt_reward_info">';
            html += '<p class="dt_reward1">' + lang('reward') + '</p>';
            html += '<p class="dt_reward2"><span id="dt_reward_coin">x ' + data.silver + '</span></p>';
            html +=   '<p class="dt_reward3"><b class="dt_reward_item">';

            if(data.get_num > 0 && data.get_type <= 2) {//道具
                html += '<em id="dailytaskpop_reward3_em" style="color:' + helper.getItemColor(data.quality) + '">' + data.itemname + 'x' + data.equip_num + '</em>';
            } else if(data.get_type == 3 && data.get_value > 0) {//宝石
                html += '<em id="dailytaskpop_reward3_em" class="add_get3">x ' + data.get_value + '</em>';
            } else if(data.get_type == 4 && data.get_value > 0) {//玉石
                html += '<em id="dailytaskpop_reward3_em" class="add_jade">x ' + data.get_value + '</em>';
            } else if(data.get_type == 5 && data.get_value > 0) {//声望
                html += '<em id="dailytaskpop_reward3_em" class="add_prestige">x ' + data.get_value + '</em>';
            } 

            html +=   '</b></p>';
            html += '</span>';
            var s = '';
            if (that.leftTime > 0) {
                if (that.taskSite != 0) {
                    if (that.taskSite != i) {
                        s = 'pop_btn_middle_disable btn';
                    } else {
                        s = 'pop_btn_middle btn';
                    }
                } else {
                    s = 'pop_btn_middle btn';
                }
            } else {
                s = 'pop_btn_middle_disable btn';
            }
            if (status == 0) {
                html += '<button class="' + s + ' accept" type="accept" site="' + i + '">' + lang('accept_task') + '</button>';
            } else if (status == 1) {
                html += '<button class="' + s + ' cancel" type="cancel" site="' + i + '">' + lang('cancle') + '</button>';
            } else if (status == 2) {
                html += '<button class="' + s + ' receive" type="receive" site="' + i + '">' + lang('receive_reward') + '</button>';
            }

            s = null;
            return html;
        },

        bindAll: function() {
            var that = this;
            that.e.find('#dt_refresh').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.checkStar();
            });

            that.listDom.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                switch ($(this).attr('type')) {
                case 'accept':
                    that.acceptTask($(this));
                    break;
                case 'cancel':
                    that.cancelTask($(this));
                    break;
                case 'receive':
                    that.receiveTask($(this));
                    break;
                }
            });
        },

        checkStar: function() {
            var that = this;
            var maxStar = 0;
            var arr = [];
            /*计算出可接收任务中的的最大星级*/
            for (var i = 1; i <= 3; i++) {
                if (that.listDom.find('#dt_task' + i).find('button').attr('type') == 'accept') {
                    arr.push(parseInt(that.listDom.find('#dt_task' + i).find('#dt_star').attr('star')));
                }
            }
            maxStar = arr.sort()[arr.length - 1];
            if (maxStar > 7) {
                xGame.confirm.show(lang('notice'), lang('8_star_task_ask', [maxStar]), 2,
                function() {
                    xGame.confirm.hide();
                    that.refreshTask();
                    that = null;
                },
                function() {
                    xGame.confirm.hide();
                });
            } else {
                that.refreshTask();
                that = null;
            }
            maxStar = arr = null;
        },

        acceptTask: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('player_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('task_times_not_enough'));
                    break;
                case - 3 : xGame.toast.show(lang('had_one_task'));
                    break;
                case 1:
                    that.taskSite = obj.attr('site');
                    that.taskType = that.listDom.find('#dt_task' + obj.attr('site')).find('#dt_img').attr('type');
                    that.taskName = that.listDom.find('#dt_task' + obj.attr('site')).find('#dt_name').html();
                    that.tasklt = parseInt(that.listDom.find('#dt_task' + obj.attr('site')).find('#dt_task_lt').html());
                    that.taskat = parseInt(that.listDom.find('#dt_task' + obj.attr('site')).find('#dt_task_at').html());
                    obj.attr({
                        'type': 'cancel',
                        'class': 'pop_btn_middle btn cancel'
                    }).html(lang('cancle'));
                    that.listDom.find('#dt_task' + that.taskSite).find('#dt_info_now').attr('class', 'cancel');
                    that.listDom.find('button.accept').attr('class', 'pop_btn_middle_disable btn accept');
                    $('#dailytask_wrap').find('p').css({
                        'color': '#fff'
                    });
                    player.info.daily_task.type = that.taskType;
                    player.info.daily_task.value = that.taskat;
                    player.info.daily_task.plan = 0;
                    $('#dailytask_wrap').find('p').html(that.taskName + '<em>0</em>/' + that.taskat + '');
                    if (that.taskName.length + 4 > 10) {
                        $('#dailytask_wrap').find('p').addClass('sp'); 
                    } else {
                        $('#dailytask_wrap').find('p').removeClass('sp'); 
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
                'task',
                'm': 'accept_task',
                's': obj.attr('site')
            },
            Func);
            Func = null;
        },

        cancelTask: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('player_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('no_task_can_cancle'));
                    break;
                case 1:

                    obj.attr({
                        'type':
                        'accept',
                        'class': 'pop_btn_middle btn accept'
                    }).html(lang('accept_task'));
                    that.listDom.find('button.accept').attr('class', 'pop_btn_middle btn accept');
                    that.listDom.find('#dt_task' + that.taskSite).find('#dt_info_now').attr('class', 'accept');
                    if (that.tasklt > 0) {
                        that.tasklt = 0;
                        that.listDom.find('#dt_task' + that.taskSite).find('#dt_task_lt').html(that.tasklt);
                    }
                    that.taskSite = 0;
                    $('#dailytask_wrap').find('p').html(lang('can_receive_task'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'task',
                'm': 'give_up',
                's': obj.attr('site')
            },
            Func);
            Func = null;
        },

        receiveTask: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('player_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('task_uncompleted'));
                    break;
                case - 3 : xGame.toast.show(lang('get_equi_fail'));
                    break;
                case 1:
                    that.taskSite = 0;
                    player.updateinfo({
                        "silver": parseInt(res.all_silver)
                    });

                    for (var i = 1; i < 4; i++) {
                        if (res.list[i] != undefined) {
                            that.listDom.find('#dt_task' + i).html(that.buildOne(res.list[i], 0, i));
                        }
                    }
                    that.leftTime = res.number;
                    that.showNum();
                    that.listDom.find('#dt_task' + that.taskSite).find('#dt_info_now').attr('class', 'accept');
                    if (that.leftTime > 0) {
                        that.listDom.find('.pop_btn_middle_disable').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                    } else {
                        that.listDom.find('.pop_btn_middle').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                    }

                    //奖励语言
                    var reward_words = ' <span class="add_silver">'+res.silver+'</span> ';
                    if(res.get_num > 0 && res.get_type <= 2) {//道具
                        reward_words += '，<b style="color:' + helper.getItemColor(parseInt(res.quality)) + '">' + res.itemname + 'x' + res.itemnum + '</b> ';
                    } else if(res.get_type == 3 && res.get_value > 0) {//宝石
                        reward_words += '，<span class="add_get3"> ' + res.get_value + '</span>';
                    } else if(res.get_type == 4 && res.get_value > 0) {//玉石
                        reward_words += '，<span class="add_jade"> ' + res.get_value + '</span>';
                    } else if(res.get_type == 5 && res.get_value > 0) {//声望
                        reward_words += '，<span class="add_prestige"> ' + res.get_value + '</span>';
                    }
                    if (appLanguage == 'kr') {//韩文做语序调整
                        xGame.toast.show(lang('you_get', [reward_words]));
                    } else {
                        xGame.toast.show(lang('you_get') + reward_words);
                    }
                    $('#dailytask_wrap').find('p').css({
                        'color': '#fff'
                    });
                    player.info.daily_task.number -= 1;
                    if (player.info.daily_task.number != undefined && player.info.daily_task.number > 0) {
                        $('#dailytask_wrap').find('p').html(lang('can_receive_task'));
                    } else {
                        $('#dailytask_wrap').hide();
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
                'task',
                'm': 'get_reward',
                's': obj.attr('site')
            },
            Func);
            Func = null;
        },

        refreshTask: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.goPay(lang('can_not_refresh_task'));
                    break;
                case - 2 : xGame.toast.show(lang('player_not_exist'));
                    break;
                case - 3 : xGame.toast.show(lang('get_equi_fail'));
                    break;
                case 1:
                    if (parseInt(res.price) > 0) {
                        that.e.find('#dt_money').show();
                        that.e.find('#dt_refresh').html(lang('refresh_task'));
                    }
                    if (that.taskSite != 0) {
                        for (var i = 1; i < 4; i++) {
                            if (res.list[i] != undefined) {
                                that.listDom.find('#dt_task' + i).html(that.buildOne(res.list[i], 0, i));
                            }
                        }
                    } else {
                        var html = '';
                        for (var i = 1; i < 4; i++) {
                            html += '<li id="dt_task' + i + '">' + that.buildOne(res.list[i], 0, i) + '</li>';
                        }
                        that.listDom.html(html);
                        html = null;
                    }
                    player.updateinfo({
                        "gold": parseInt(res.all_gold)
                    });
                    if (that.tasklt < that.taskat) {
                        that.tasklt += 1;
                        if (that.taskType == 5) {
                            that.listDom.find('#dt_task' + that.taskSite).find('#dt_task_lt').html(that.tasklt);
                            that.listDom.find('#dt_task' + that.taskSite).find('#dt_info_now').attr('class', 'cancel');
                            if (that.tasklt == that.taskat) {
                                that.listDom.find('#dt_task' + that.taskSite).find('#dt_info_now').attr('class', 'receive');
                                that.listDom.find('#dt_task' + that.taskSite).find('#dt_status').css('display', 'block');
                                that.listDom.find('#dt_task' + that.taskSite).find('button').attr({
                                    'type': 'receive',
                                    'class': 'pop_btn_middle btn receive'
                                }).html(lang('receive'));
                            }
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
                'task',
                'm': 'refresh'
            },
            Func);
            Func = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#dt_refresh').undelegate('.pop_btn_small', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.listDom) {
                this.listDom.undelegate('.pop_btn_middle', 'touchend');
                this.listDom.remove();
                this.listDom = null;
            }
        },

        hide: function() {

            xGame.unlock();
            this.isshow = false;
            this.taskSite = this.leftTime = this.taskType = this.tasklt = this.taskat = null;
            this.unbindAll();
            player.reloadInfo();
        },
    };
})();