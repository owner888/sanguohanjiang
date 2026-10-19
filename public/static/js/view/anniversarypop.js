/* 周年庆典 */
(function() {
    anniversaryPopClass = function() {
        this.e;
        this.isshow = false;
        this.rewardData;
        this.anim = null;
    };

    anniversaryPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {

                if (res.status == '-2') {
                    xGame.toast.show(lang('activity_closed'));
                    $('#worldloading').hide();
                    return false;
                } else if (res.status == '-1') {
                    xGame.toast.show(lang('get_reward_info_fail'));
                    $('#worldloading').hide();
                    return false;
                }

                that.e = xGame.loadPopTmpl('anniversary');

                that.e.find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    anniversarypop.hide();
                    e.preventDefault();
                });

                that.e.find('#anniversary_receive').unbind('touchend').bind('touchend',
                function() {
                    //调用领取接口
                    if (!$(this).hasClass('disable')) {
                        that.get_gift();
                    }
                });

                if (res.last_day == 1) {
                    $('#anniversary_cd').html(lang('anniversarypop_last_day'));
                } else {
                    if (res.is_get == 1) {
                        xGame.updateCd('anniversary', res.cd);
                    } else {
                        $('#anniversary_cd').css('display', 'none');
                    }
                }

                if (res.is_get == 1) {
                    that.e.find('#anniversary_receive').addClass('disable');
                }

                that.playAnim();
                that.e.find('#anniversary_day').html(res.time);
                that.e.find('#anniversary_receive').find('em').html('x' + res.price);
                
                that.rewardData = res.list;
                
                that.e.find('#anniversary_reward1').html('x' + res.list[1].value);
                that.e.find('#anniversary_reward2').html('x' + res.list[2].value);
                that.e.find('#anniversary_reward3').html('x' + res.list[3].value);
                that.e.find('#anniversary_reward4').html('x' + res.list[4].value);
                
                that.e.show();
                $('#worldloading').hide();

            };

            //加载数据
            loadContents({
                'c': 'anniversary',
                'm': 'index'
            },
            Func);

            Func = null;
        },

        get_gift: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case -5:
                        xGame.toast.show(lang('anniversarypop_wait'));
                        break;
                    case -4:
                        xGame.toast.show(lang('ganen_error'));
                        break;
                    case -3:
                        xGame.goPay(lang('gold_not_enough'));
                        break;
                    case -2:
                        xGame.toast.show(lang('reward_had_receive'));
                        break;
                    case -1:
                        xGame.toast.show(lang('get_reward_info_fail'));
                        break;
                    case 1:
                        //xGame.toast.show(lang('anniversarypop_buy_success'));
                        that.rewardDown();
                        that.e.find('#anniversary_receive').addClass('disable');
                        player.updateinfo({
                            "silver": res.all_silver,
                            "gold": res.all_gold
                        });

                        if (res.last_day != 1) {
                            $('#anniversary_cd').css('display', 'block');
                            xGame.updateCd('anniversary', res.cd);
                        }
                        that.e.find('#anniversary_receive').addClass('disable');

                        break;
                    case 2:
                        xGame.toast.show(lang('activity_closed'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'anniversary',
                'm': 'get_reward'
            },
            Func);

            Func = null;
        },

        rewardDown: function() {
            var html = '';
            html += '<div class="anniversary_reward_box">';
            html += '<span class="reward_down pos1 silver">x' + this.rewardData[1].value + '</span>';
            html += '<span class="reward_down pos2 tfcard">x' + this.rewardData[2].value + '</span>';
            html += '<span class="reward_down pos3 osoul">x' + this.rewardData[3].value + '</span>';
            html += '<span class="reward_down pos4 gem3">x' + this.rewardData[4].value + '</span>';
            html += '</div>';
            this.e.find('.content').append(html);
            html = null;
        },

        playAnim: function() {
            var that = this;

            var xy_config = {
                1 : [31, 389],
                2 : [133, 389],
                3 : [237, 389],
                4 : [342, 389]
            };

            that.anim = [];
            for (var i = 1; i <= 4; i++) {
                //that.anim[i] = {};
                that.anim[i] = new movieClipClass({
                    "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                    "width": 93,
                    "height": 90,
                    "x": xy_config[i][0],
                    "y": xy_config[i][1],
                    "loop": true,
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
                    }
                    ]
                });
                //开始播放
                that.anim[i].appendTo(that.e.find('.content'));
                that.anim[i].play();
            }
        },

        hideAnim: function() {
            if (!this.anim) {
                return;
            }

            for (var i = 1; i <= 4; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = null;
        },

        unbindAll: function () {
            this.e.find('.hidebtn').unbind('touchend');
            this.e.find('#anniversary_receive').unbind('touchend');
            this.e.remove();
            this.e = null;
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.rewardData = null;
            this.unbindAll();
            this.hideAnim();
            if (typeof CD['anniversary'] != undefined) {
                xGame.updateCd('anniversary', 0);
            }

        },
    };
})();