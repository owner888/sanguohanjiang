/* 竞速 */
(function() {
    rushPopClass = function() {
        this.e;
        this.isshow = false;
        this.rushScroller;
    };
    rushPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('activity_is_end'));
                    $('#worldloading').hide();
                    player.hideActivity('rush');
                    break;
                case 1:
                    that.initList(res.list, res.race_dateline);
                    break;
                }
                xGame.unlock();
                that = null;
            };
            loadContents({
                'c': 'map',
                'm': 'get_reward_list'
            },
            Func, true); //加载数据
            Func = null;
        },

        initList: function(list, time) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('rush');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                rushpop.hide();
                e.preventDefault();
            });

            var html = '',
            button = '';
            for (var i = 0; i < list.length; i++) {
                if (list[i].open_status == 1) {
                    button = '<button class="pop_btn_middle_disable btn" mid="' + list[i].missionid + '" gold="' + list[i].gold + '">' + lang('not_reached') + '</button>';
                } else if (list[i].open_status == 0) {
                    button = '<button class="pop_btn_middle btn" mid="' + list[i].missionid + '" gold="' + list[i].gold + '">' + lang('receive_s') + '</button>';
                } else if (list[i].open_status == 2) {
                    button = '<span class="rushpop_got"></span>';
                }
                html += '<li><img src="' + xGame.avatar(list[i].image) + '"  />' + lang('beat_someone_get_gold', [list[i].name, list[i].gold]) + button + '</li>';
            }

            that.e.find('#rushpop_list').html(html);

            that.rushScroller = new iScroll('rushpop_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.rushScroller.refresh();

            that.e.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend').delegate('button.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.get_reward($(this));
            });

            xGame.updateCd('rush', time);

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();

        },

        get_reward: function(obj) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('activity_is_end'));
                    rushpop.e.find('li').find('button').attr('class', 'pop_btn_middle_disable btn');
                    break;
                case - 2 : xGame.toast.show(lang('checkpoint_not_cross'));
                    break;
                case - 3 : xGame.toast.show(lang('had_receive'));
                    break;
                case - 4 : xGame.toast.show(lang('reward_info_no_exist'));
                    break;
                case 1:
                    obj.parent().append('<span class="rushpop_got"></span>');
                    player.updateinfo({
                        "gold":
                        res.gold
                    });
                    xGame.toast.show(lang('receive_get_gold', [obj.attr('gold')]));

                    if (res.popo == 0) {
                        $('#rush_btn').children('.popo').hide();
                    }
                    obj.remove();
                    break;
                }
                xGame.unlock();
                that = null;
            };
            loadContents({
                'c': 'map',
                'm': 'get_mission_reward',
                'id': obj.attr('mid')
            },
            Func);
            Func = null;
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('rush', 0);
            if (this.rushScroller) {
                this.rushScroller.destroy();
                delete this.rushScroller;
            }
            this.isshow = false;
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('ul').find('li').undelegate('button.pop_btn_middle', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },
    };
})();