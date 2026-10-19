/* 活动 */
(function() {
    dailysignPopClass = function() {
        this.e;
        this.isshow = false;

    };
    dailysignPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (parseInt(res.status) == -1) {
                    xGame.toast.show(lang('activity_closed'));
                    $('#worldloading').hide();
                    return;
                } else {
                    that.buildpage(res);
                }
            }
            loadContents({
                'c': 'dailysign',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        buildpage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('dailysign');

            that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                dailysignpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            var html = '';
            var open = 0;
            for (var i = 0; i < res.list.length; i++) {
                if (res.list[i].open == 1) {
                    html += '<li id="today">';
                    html += '<p class="day_bg" style="background-position:0px -42px;">';
                    open = 1;
                } else {
                    html += '<li>';
                    html += '<p class="day_bg">';
                }
                html += '<span style="background-position:-' + 22 * (i + 1) + 'px 0px"></span></p>';
                html += '<p class="act">' + res.list[i].act + '</p>';
                html += '<p class="silver">' + res.list[i].silver + '</p>';
                if (res.list[i].get == 1) {
                    html += '<p class="get"></p>';
                }
                if (res.list[i].open == 1 && res.list[i].get == 0) {
                    html += '<button class="btn pop_btn_small get_reward">' + lang('receive') + '</button>';
                }
                if (res.list[i].open == 0 && res.list[i].get == 0 && open == 1) {
                    html += '<button class="btn pop_btn_small_disable">' + lang('receive') + '</button>';
                }
                html += '</li>';
            }
            that.e.find('#dailysign_list').html(html);
            that.e.find('#dailysign_date').html(that.number(res.date));
            that.e.find('#today').find('.get_reward').unbind('touchend').bind('touchend',
            function() {
                that.sign();
            });
            /*显示界面*/
            that.e.show();
            $('#worldloading').hide();
        },

        sign: function() {
            var that = this;
            var Func = function(res) {
                if (parseInt(res.status) == -1) {
                    xGame.toast.show(lang('had_sign'));
                    return;
                } else {
                    //签到成功
                    xGame.toast.show(lang('receive_success_msg', [res.add_silver, res.add_act]));
                    player.updateinfo({
                        'silver': parseInt(res.all_silver),
                        'act': parseInt(res.all_act)
                    });
                    $('<div class="get"></div>').appendTo(that.e.find('#today'));
                    that.e.find('#today').find('.btn').unbind().remove();
                }
            }
            loadContents({
                'c': 'dailysign',
                'm': 'sign'
            },
            Func, true); //加载数据
            Func = null;
        },

        number: function(time) {
            var html = '';
            for (var i = 0; i < time.length; i++) {
                if (!isNaN(parseInt(time[i]))) {
                    html += '<span class="number_img number' + time[i] + '"></span>';
                } else if (time[i] == ':' || time[i] == '.') {
                    html += '<span class="number_img number_s"></span>';
                } else if (time[i] == '-') {
                    html += '<span class="number_img number_l"></span>';
                }
            }
            return html;
        },

        unbindAll: function () {
            this.e.find('.content').find('#hidebtn').unbind('touchend');
            this.e.find('#today').find('.get_reward').unbind('touchend');
            this.e.find('#today').find('.btn').unbind();
            this.e.remove();
            this.e = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.e) {
                this.unbindAll();
            }
        },
    };
})();