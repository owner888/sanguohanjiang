/* 91助手-版本有奖切换 */
(function() {
    ndgiftPopClass = function() {
        this.e;
        this.isshow = false;
    };

    ndgiftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            that.e = xGame.loadPopTmpl('ndgift');

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                ndgiftpop.hide();
                e.preventDefault();
            });

            that.e.find('.hanjiangsanguo').unbind('touchend').bind('touchend',
            function(e) {
                window.location.href = 'http://www.hanjiangsanguo.com';
                e.preventDefault();
            });

            that.e.find('.newversion_install').unbind('touchend').bind('touchend',
            function(e) {
                window.location.href = 'http://www.hanjiangsanguo.com/goky.html';
                e.preventDefault();
            });
            
            that.e.show();
            $('#worldloading').hide();
            that.isshow = true;
        },

        unbindAll: function () {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('.hanjiangsanguo').unbind('touchend');
            this.e.find('.newversion_install').unbind('touchend');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.unbindAll();
            this.e.remove();
            this.e = null;
        },
    };
})();