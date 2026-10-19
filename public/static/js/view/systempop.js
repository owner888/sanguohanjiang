(function () {
    systemPopClass = function () {
        this.e = null;
        this.isshow = false;
        this.tabBtn = null;
        this.nowPop = null;
        this.loading = $('#worldloading');
    };

    systemPopClass.prototype = {
        init: function (data) {

            this.loading.hide();

            if (this.isshow == false) {
                this.e = xGame.loadPopTmpl('system');
                this.tabBtn = this.e.find('.tab_btn');
                this.nowPop = data.pop;
                this.tabBtn.find('.' + this.nowPop).addClass('select').parent().show();
                this.bindAll();
                this.e.show();
                this.isshow = true;
                this.loading.find('.mask').css('background', 'none');
            } else {
                window[this.nowPop + 'pop'].hide();
                this.nowPop = data.pop;
                this.tabBtn.find('.' + this.nowPop).addClass('select').siblings().removeClass('select');
            }

            if (data.callback) {
                data.callback();
            }

            if (xGame.guide) {
                switch (guide.now) {
                    case 1408:
                        guide.show(1409);
                        break;
                    case 1504:
                        guide.show(1505);
                        break;
                }
            }
        },

        bindAll: function () {
            
            var that = this;

            // 绑定切换按钮
            that.tabBtn.delegate('.btn', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);
                var level = $this.attr('level');

                if (player.info.level < parseInt(level)) {
                    xGame.toast.show(lang('missionpop_lv_notice', [$this.html(), level]));
                    return false;
                }

                var pop = $this.attr('pop');

                if (pop == 'godforge') {
                    xGame.toast.show(lang('godforgepop_close'));
                    return false;
                }

                if (pop == that.nowPop) {
                    return false;
                }

                xGame.showpop(pop);
                e.preventDefault();
            });

            // 绑定关闭按钮
            that.e.find('#system_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });
        },

        unbindAll: function () {
            
            this.tabBtn.undelegate('.btn', 'touchend');
            this.e.find('#system_hidebtn').unbind('touchend');
        },

        hide: function () {
            
            window[this.nowPop + 'pop'].hide();
            this.unbindAll();
            this.isshow = false;
            this.loading.find('.mask').css('background', '');
            this.loading = null;
            this.tabBtn = null;
            this.nowPop = null;
            this.e.remove();
            this.e = null;

            if (systempop) {
                systempop = null;
            }
        }
    }
})();