/* 连续签到 */
(function() {
    signinPopClass = function() {
        this.e;
        this.isshow = false;
    };
    signinPopClass.prototype = {
        init: function() {
            var that = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch (res.status) {
                    case 1:
                        that.initPage(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            loadContents({
                'c': 'totallogin',
                'm': 'index',
            },
            initRender, true); //加载数据
            initRender = null;
        },

        initPage: function(res) {
            var that = this;

            this.e = xGame.loadPopTmpl('signin');

            if (parseInt(res.total_login_bg) <= 2) {
                this.e.find('#signin_wrap').css('background', 'url('+ staticUrl + 'img/pop/signinpop_bg' + res.total_login_bg + '.png' + ') 50% 50% no-repeat');
            } else {
                this.e.find('#signin_wrap').css('background', 'url('+ ximg(staticUrl + 'img/plugin/signinpop_bg' + res.total_login_bg + '.png') + ') 50% 50% no-repeat');
            }

            //活动时间
            this.e.find('.duration ').html(res.time);
            //当前登录天数
            this.e.find('.currday').html(res.day);
            //关闭按钮
            this.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                that.hide();
                return false;
            });
            //武将图片
            var heroimg = this.e.find('.content').find('.heroImg');
            heroimg.unbind('touchend').bind('touchend', function(e){
                miscpop.showHeroInfo(res.list.info);
            });
            //领取按钮
            var receive_btn = this.e.find('#receive_btn');
            switch(res.is_get) {
                case 0:
                    receive_btn.removeClass('disabled').addClass('disabled').html(lang('receive_reward'));
                    break;
                case 1:
                    receive_btn.removeClass('disabled').html(lang('receive_reward'));
                    break;
                case 2:
                    receive_btn.removeClass('disabled').addClass('disabled').html(lang('warena_have_fetch'));
                    break;
                default:
                    break;
            }
            if (!receive_btn.hasClass('disabled')) {
                receive_btn.unbind('touchend').bind('touchend', function(e){
                    var initRender = function(res) {
                    switch (res.status) {
                        case 1:
                            receive_btn.removeClass('disabled').addClass('disabled').html(lang('warena_have_fetch'));
                            miscpop.newhero(res.general);
                            break;
                        default:
                            xGame.toast.show(res.msg);
                            break;
                        }
                    };

                    loadContents({
                        'c': 'totallogin',
                        'm': 'action'
                    },
                    initRender, true); //加载数据
                    initRender = null;

                    e.preventDefault();
                    e.stopPropagation();
                });
            }

            this.isshow = true;
            $('#worldloading').hide();
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#receive_btn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },
    };
})();