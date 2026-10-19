/* 感恩 */
(function() {
    ganenPopClass = function() {
        this.e;
        this.isshow = false;

    };
    ganenPopClass.prototype = {
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
                'c': 'newyeardailysign',
                'm': 'index'
            },
            Func, true); //加载数据
            Func = null;
        },

        buildpage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('yuandansign');

            that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                ganenpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.find('#ganen_date').html(that.number(res.time));

            //desc
            that.e.find('#yuandan_msg').html(res.desc);
            //box
            if(res.box_log == 1) {//开过宝箱了
                that.e.find('#box_image').addClass('open');
                that.e.find('#openbox_btn').attr('class', 'btn pop_btn_small_disable');
            }
            //绑定领取宝箱按钮
            that.e.undelegate('#openbox_btn.pop_btn_small', 'click').delegate('#openbox_btn.pop_btn_small', 'click', function() {
                that.fetchbox();
            });

            //列表
            var html = '';
            for(var i = 0; i < res.list.length; i++) {
                var desckey = i+1 > 3 ? 3 : i+1;
                if(res.list[i].open == 1) {
                    html += '<li><em class="day now_day">';
                } else {
                    html += '<li><em class="day">';
                }
                html += '<span style="background-position:-'+(22*(i+1))+'px 0px"></span></em><em class="desc">' + lang('ganen_lang'+desckey) + '</em>';
                if(res.list[i].open == 1 && res.list[i].get != 1) {
                    html += '<button class="btn pop_btn_small" id="today">' + lang('receive') + '</button></li>';
                } else if(res.list[i].get == 1) {
                    html += '<button class="btn pop_btn_small_disable is_get"></button></li>';
                } else if(res.list[i].get == 2) {
                    html += '<button class="btn pop_btn_small_disable">' + lang('un_receive') + '</button></li>';
                } else {
                    html += '<button class="btn pop_btn_small_disable">' + lang('receive') + '</button></li>';
                }
            }
            that.e.find('#yuandansign_list_scroller').html(html);

            //绑定领取按钮
            that.e.find('#yuandansign_list_scroller').find('li').undelegate('.pop_btn_small', 'click').delegate('.pop_btn_small', 'click', function() {
                that.sign();
            });

            /*显示界面*/
            that.e.show();
            $('#worldloading').hide();
        },

        sign: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case -1:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    case -2:
                        xGame.toast.show(lang('had_sign'));
                        break;
                    case 1:
                        //签到成功
                        xGame.toast.show(lang('christmassign_success_msg', [res.add_gold]));
                        player.updateinfo({
                            'gold': parseInt(res.all_gold)
                        });
                        that.e.find('#today').attr('class', 'btn pop_btn_small_disable is_get').html('');
                        break;
                    default:
                        xGame.toast.show(lang('ganen_error'));
                        break;
                }
                xGame.unlock();
            }
            loadContents({
                'c': 'newyeardailysign',
                'm': 'sign'
            },
            Func); //加载数据
            Func = null;
        },

        fetchbox: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case -1:
                        xGame.toast.show(lang('ganen_opened'));
                        break;
                    case 1:
                        //签到成功
                        xGame.toast.show(lang('christmassign_success_msg', [res.add_gold]));
                        player.updateinfo({
                            'gold': parseInt(res.all_gold)
                        });
                        //播动画
                        var movieclip = new movieClipClass({
                            "img": staticUrl + 'img/battle/resutl_box_stars.png',
                            "width": 261,
                            "height": 233,
                            "x": -20,
                            "y": -30,
                            "loop": false,
                            "endDestory": true,
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
                        that.e.find('#box_image').append(movieclip.obj);
                        movieclip.play();
                        that.e.find('#box_image').addClass('open');
                        that.e.find('#openbox_btn').attr('class', 'btn pop_btn_small_disable');
                        break;
                    default:
                        xGame.toast.show(lang('ganen_error'));
                        break;
                }
                xGame.unlock();
            }
            loadContents({
                'c': 'newyeardailysign',
                'm': 'open_box'
            },
            Func); //加载数据
            Func = null;
        },

        number: function(time) {
            var html = '';
            for (var i = 0; i < time.length; i++) {
                if (!isNaN(parseInt(time[i]))) {
                    html += '<span class="number_img number' + time[i] + '"></span>';
                } else if (time[i] == ':' || time[i] == '.') {
                    html += '<span class="number_img number_s"></span>';
                } else if (time[i] == '-' || time[i] == '~') {
                    html += '<span class="number_img number_l"></span>';
                }
            }
            return html;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('#hidebtn').unbind('touchend');
                this.e.undelegate('#openbox_btn.pop_btn_small', 'click');
                this.e.find('#yuandansign_list_scroller').find('li').undelegate('.pop_btn_small', 'click');
                this.e.remove();
                this.e = null;
             }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.unbindAll();
        },
    };
})();