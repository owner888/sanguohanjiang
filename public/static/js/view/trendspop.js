/* 每日提醒 */
(function() {
    trendsPopClass = function() {
        this.e;
        this.isshow = false;
        this.selected = 0;
        this.myScroller;
    };
    trendsPopClass.prototype = {
        init: function(savedata) {
            var that = this;

            this.isshow = true;

            var Func = function(res) {

                if (xGame.guide) {
                    res.status = -1;
                }
                
                switch (parseInt(res.status)) {
                case 1:
                    //有提醒的情况下
                    that.initList(res);
                    //保存数据
                    if(savedata) {
                        xGame.xsSet(xGame.server_prefix + 'last_trends_id', savedata.id);
                        xGame.xsSet(xGame.server_prefix + 'last_trends_date', savedata.date);
                    }
                    break;
                default:
                    //错误的情况下，不做任何处理
                    $('#worldloading').hide();
                    that.isshow = false;
                    break;
                }
                xGame.unlock();
                that = null;
            };

            loadContents({
                'c': 'message',
                'm': 'get_new_trends'
            },
            Func, true); //加载数据
            Func = null;

        },

        initList: function(data) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('trends');
            that.e.find('.content').find('.hidebtn').unbind('touchend').one('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                //关闭时判断执行不再提醒操作
                if (that.selected == 1) {
                    that.closetrends();
                }
                trendspop.hide();
                e.preventDefault();

            });

            /* 第二步 构建数据 */
            that.e.find('.content').find('#info').html(data.list.info);

            that.myScroller = new iScroll('trendspop_scroller', {
                bounce: false,
                momentum: false
            });

            that.myScroller.refresh();

            /* 绑定关闭每日提醒按钮 */
            that.e.find('.content').find('.closetrends').unbind('touchend').bind('touchend',
            function() {
                if (that.selected == 1) {
                    that.selected = 0;
                    $(this).removeClass('selected');
                } else {
                    that.selected = 1;
                    $(this).addClass('selected');
                }
            });

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            html = null;
        },

        /*不再提醒操作*/
        closetrends: function() {
            //该请求不需要返回值
            loadContents({
                'c': 'message',
                'm': 'update_trends_status'
            });
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.closetrends').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.unbindAll();
            this.isshow = false;
            if (this.myScroller) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            
            this.selected = 0;
            
        },
    };
})();