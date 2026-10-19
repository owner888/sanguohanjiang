/* 91 */
(function() {
    binding91PopClass = function() {
        this.e;
        this.isshow = false;
    };
    binding91PopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            if(parseInt(player.info.bind91) != 1) {
                $('#worldloading').hide();
                return;
            }

            //初始化界面
            that.e = xGame.loadPopTmpl('binding91');
            //关闭按钮
            that.e.find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                binding91pop.hide();
                player.binding91Show = false;
                e.preventDefault();

            });
            $('#worldloading').hide();
            that.bindAll();
            that.e.show();
        },

        bindAll: function() {
            var that = this;

            that.e.find('#breg_username').unbind('touchend').one('touchend',
            function() {
                $(this).val('');
            });

            that.e.find('#breg_password').unbind('touchend').one('touchend',
            function() {
                $(this).val('');
            });

            that.e.find('#breg_mobile').unbind('touchend').one('touchend',
            function() {
                $(this).val('');
            });

            that.e.find('#binding').unbind('touchend').bind('touchend',
            function() {
                that.gobinding91();
            });
        },


        gobinding91: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var username = that.e.find('#breg_username').val();
            var password = that.e.find('#breg_password').val();
            var mobile = that.e.find('#breg_mobile').val();

            if (username == lang('4_16')) {
                username = '';
            }
            if (password == lang('6_16')) {
                password = '';
            }
            if (mobile == lang('only_find_psw')) {
                mobile = '';
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    xGame.confirm.show(lang('binding_user_bind'), lang('binding_user_is_success', [username, password]), 1,
                    function() {
                        xGame.confirm.hide(function() {
                            xGame.confirm.show(lang('binding_user_join_notice'), lang('binding_user_join_content', [username, password]), 1,
                            function() {
                                xGame.confirm.hide();
                                binding91pop.hide();
                                player.info.bind91 = 2;
                            });
                        });
                    });
                    break;
                case 2 :
                    xGame.toast.show(lang('player_not_exist'));
                    player.info.bind91 = 0;
                    binding91pop.hide();
                    break;
                case 3 :
                    xGame.toast.show(lang('binding_user_is_bind'));
                    player.info.bind91 = 0;
                    binding91pop.hide();
                    break;
                case 4 :
                    xGame.toast.show(lang('binding_user_info_diff'));
                    player.info.bind91 = 0;
                    binding91pop.hide();
                    break;
                case - 5 :
                    xGame.toast.show(lang('binding_user_name_exist'));
                    break;
                case - 4 :
                    xGame.toast.show(lang('binding_user_pass_fail'));
                    break;
                case - 3 :
                    xGame.toast.show(lang('binding_user_name_fail'));
                    break;
                case - 2 :
                    xGame.toast.show(lang('binding_user_is_bind'));
                    player.info.bind91 = 0;
                    binding91pop.hide();
                    break;
                case - 1 :
                    xGame.toast.show(lang('binding_user_info_fail'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'login',
                'm': 'binding_91',
                'u': username,
                'p': password,
                'mobile': mobile,
                'uid': player.info.uid,
                'mac': xGame.macaddr
            },
            Func);

            Func = null;
        },

        unbindAll: function() {
            this.e.find('#hidebtn').unbind('touchend');
            this.e.find('#breg_username').unbind('touchend');
            this.e.find('#breg_password').unbind('touchend');
            this.e.find('#breg_mobile').unbind('touchend');
            this.e.find('#binding').unbind('touchend');
        },

        hide: function() {
            this.isshow = false;
            xGame.unlock();
            this.unbindAll();
            if (this.e) {
                this.e.remove();
                this.e = null;
            }
        }
    };
})();