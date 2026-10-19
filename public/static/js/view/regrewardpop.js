(function () {
    regrewardPopClass = function () {
        this.e = null;
        this.isshow = false;
    };

    regrewardPopClass.prototype = {
        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            that.initPage();
        },

        initPage: function () {
            
            var that = this;
            that.e = xGame.loadPopTmpl('regreward');

            that.e.find('.hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            that.e.show();

            if (xGame.xsExist('tryuid') == false) {
                that.getReward();
            }

            that.bindAll();
            that.isshow = true;
            $('#worldloading').hide();
        },

        bindAll: function () {

            var that = this;
            var $username = that.e.find('#reg_username');
            var $password = that.e.find('#reg_password');
            var $mobile = that.e.find('#reg_mobile');

            $username.unbind('touchend').one('touchend', function() {
                $(this).val('');
            });

            $password.unbind('touchend').one('touchend', function() {
                $(this).val('');
            });

            $mobile.unbind('touchend').one('touchend', function() {
                $(this).val('');
            });

            that.e.find('#rbtn_ingame').unbind('touchend').bind('touchend', function() {
                
                if (xGame.checkLock()) {
                    return false;
                }

                if ($username.val() == lang('4_16')) {
                    $username.val('');
                }
                var _username = $username.val().replace(' ', '');

                if ($password.val() == lang('6_16')) {
                    $password.val('');
                }
                var _password = $password.val().replace(' ', '');

                if ($mobile.val() == lang('only_find_psw')) {
                    $mobile.val('');
                }
                var _mobile = $mobile.val().replace(' ', '');

                var render = function(res) {
                    xGame.unlock();

                    switch (res.status) {
                        case 1:
                            xGame.xsRemove('tryuid');
                            xGame.saveAccount(_username, _password);
                            xGame.confirm.show(lang('bind_complete'), lang('bind_complete_msg', [_username, _password]), 1, function() {
                                
                                xGame.confirm.hide(function() {
                                    that.getReward();
                                });
                            });
                            break;
                        case -1:
                            xGame.toast.show(lang('bind_fail'));
                            break;
                        case -2:
                            xGame.toast.show(lang('you_had_bind_account'));
                            break;
                        case -3:
                            xGame.toast.show(lang('account_mate_error'));
                            break;
                        case -4:
                            xGame.toast.show(lang('psw_mate_error'));
                            break;
                        case -5:
                            xGame.toast.show(lang('account_is_exist'));
                            break;
                        default :
                            break;
                    }
                   render = null;
                };

                if (helper.checkUserName(_username) && helper.checkPassWord(_password) && helper.checkTel(_mobile)) {
                    http(UCAPI + '&c=register&m=binding&', {
                        'uid': player.info.uid,
                        'u': _username,
                        'p': _password,
                        'mobile': _mobile,
                        'v': localStorage['sg_ver'],
                        'mac': xGame.macaddr,
                        'adid': xGame.adid,
                        'devicetoken': xGame.devicetoken,
                        'channel': channelID
                    },
                    render);
                } else {
                    xGame.unlock();
                }
                render = null;

            });

        },

        getReward: function () {

            if (xGame.checkLock()) {
                return false;
            }
            
            var that = this;

            var render = function (res) {
                
                if (res.status == 1) {

                    var $reward = that.e.find('#reward');
                    $reward.show();

                    var time = setTimeout(function () {

                        var $box = $reward.children('#reward_box');
                        $box.addClass('open');

                        that.openBoxAnim($box, function () {
                            xGame.toast.show(lang('regrewardpop_reward', [res.gold]));
                            player.updateinfo({
                                'gold': res.all_gold
                            });

                            setTimeout(function () {
                                $reward.hide();
                                $('#header_regreward_btn_wrap').hide();
                                that.hide();
                            }, 3000);
                        });
                    }, 500);

                    time = null;
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            }

            loadContents({
                'c': 'regreward',
                'm': 'get_reward'
            }, render, true);
            render = null;
        },

        openBoxAnim: function(nowdom, callback) {
            var movieclip = new movieClipClass({
                "img": staticUrl + 'img/battle/resutl_box_stars.png',
                "width": 261,
                "height": 233,
                "x": -10,
                "y": -17,
                "loop": false,
                "endDestory": true,
                "callback": callback,
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
            nowdom.append(movieclip.obj);
            movieclip.play();
            movieclip = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#reg_username').unbind('touchend');
                this.e.find('#reg_password').unbind('touchend');
                this.e.find('#reg_mobile').unbind('touchend');
                this.e.find('#rbtn_ingame').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function () {
            xGame.unlock();
            this.isshow = false;

            this.unbindAll();
        }
    };
})();