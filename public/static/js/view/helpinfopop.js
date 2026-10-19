/* 帮助 */
(function() {
    helpinfoPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        this.data = null;
    };
    helpinfoPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('helpinfo');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                helpinfopop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.myScroller = new iScroll('helpinfopop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.e.find('#helpinfopop_scroller').undelegate('span,.title', 'click').delegate('span,.title', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this).parent();
                if (obj.hasClass('content_show')) {
                    obj.attr('class', 'content_hide');
                } else {
                    obj.attr('class', 'content_show');
                }
                that.myScroller.refresh();
            });

            /* 最后 展示弹窗 */
            that.e.show();

            that.myScroller.refresh();
            $('#worldloading').hide();

        },

        unbindAll: function () {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('#helpinfopop_scroller').undelegate('span,.title', 'click');
        },

        hide: function() {
            xGame.unlock();
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            this.data = null;
            this.isshow = false;
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
             }
        },
    };
})();