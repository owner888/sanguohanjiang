/* 世界boss */
(function() {
    bossbandPopClass = function() {
        this.e;
    };
    bossbandPopClass.prototype = {
        init: function(data) {
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                switch (parseInt(res.status)) {
                    /* 显示伤害榜页面 */
                case 1:
                    that.showBand(res);
                    $('#header').find('#header_activity_btn_wrap').children('.popo').show();
                    break;
                case 3:
                    xGame.toast.show(lang('activity_end'));
                    $('#worldloading').hide();
                    that.hide();
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    that.hide();
                    break;
                }
            };
            if (data) {
                initRender(data);
            } else {
                loadContents({
                    'c':
                    'worldboss',
                    'm': 'index'
                },
                initRender, true);
            }

        },

        /* 伤害榜 */
        showBand: function(res) {
            var that = this;

            xGame.toast.show(lang('boss_killed_please_receive'));

            that.e = xGame.loadPopTmpl('bosshurt');
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                bossbandpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            //构建页面数据
            //侧边排行
            var html = '';
            for (var i = 0; i < res.top.length; i++) {
                var hero = res.top[i];
                html += '<li class="gender' + hero.gender + '" uid="' + hero.uid + '">';
                html += '<span class="info">' + lang('player') + ':<em>' + hero.nickname + '</em></span>';
                html += '<span class="info">' + lang('level') + ':<em>' + hero.level + '</em></span>';
                html += '<span class="num">' + hero.rank + '</span>';
                html += '<span class="hurt">' + lang('damage_lv') + '<em>' + hero.hit + '</em></span>';
                html += '</li>';
            }
            that.e.find('#bossband_list').html(html);

            //右边奖励
            if (res.reward.lasthit == 1) {
                that.e.find('#lastflag').show();
                that.e.find('#lastreward').children('em').html('x150%');
            }
            that.e.find('#reward_silver').html(res.reward.silver);
           // that.e.find('#reward_stone').html(res.reward.get2 > 0 ? res.reward.get2: res.reward.get3).addClass(res.reward.get2 > 0 ? 'stone2': 'stone3');

            that.e.find('#bossband_myrank').html(res.reward.rank);
            that.e.find('#bossband_myhurt').html(res.reward.hit);
            that.e.find('#bosshurt_inter_num').html(res.reward.intnum);
            that.e.find('#bossband_fetch').unbind('touchend').bind('touchend',
            function() {
                var render = function(data) {
                    if (data.status == 1) {
                        xGame.toast.show(lang('reward_success'));
                        that.e.find('#bossband_fetch').unbind('touchend').attr({
                            'class': 'btn pop_btn_big_disable'
                        });
                        that.e.find('#bosshurt_inter_num').html(data.reward.intnum);
                        $('#header').find('#header_activity_btn_wrap').children('.popo').hide();
                      /*  player.updateinfo({
                            "silver": parseInt(data.reward.all_silver)
                        });*/
                    } else {
                        xGame.toast.show(lang('receive_reward_fail'));
                    }
                };
                loadContents({
                    'c': 'worldboss',
                    'm': 'reward'
                },
                render);

                render = null;
            });

            that.e.find('#bossband_list').undelegate('li', 'click').delegate('li', 'click',
            function() {
                if ($(this).attr('uid') != undefined) {
                    xGame.showpop('info', $(this).attr('uid'));
                }
            });

            that.e.show();
            $('#worldloading').hide();
        },

        unbindAll: function () {
	    if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#bossband_fetch').unbind('touchend');
                this.e.find('#bossband_list').undelegate('li', 'click');
                this.e.remove();
                this.e = null;
	    }
        },
        
        hide: function() {
            xGame.unlock();
            this.unbindAll();
            
        },
    };
})();