/*领红包*/
(function(){
    nationaldayPopClass = function () {
        this.e = null;
        this.isshow = false;
        this.ymd = null;//领取奖励时的传的参数
        this.date = null;//确定你点的是哪个button
        this.reward_gold = null;//奖励的数量
        this.data = null;//存储数据
    };

    nationaldayPopClass.prototype = {
   
        init:function () {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function (res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.data = res;
                        that.initManager(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };

            loadContents({
                'c': 'act_national_day',
                'm': 'index'
            },
            initRender);

            initRender = null;
        },

        initManager:function (res) {

            $('#worldloading').hide();
            this.e = xGame.loadPopTmpl('nationalday');
            this.bindAll();
            this.buildList(res);
            this.isshow = true;
        },

        bindAll: function() {
            var that = this;

            //关闭界面
            this.e.find('#nationalday_wrap').find('.hidebtn').unbind('touchend').bind('touchend',
            function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                e.preventDefault();
                that.hide();
            });

            //关闭领取界面
            this.e.find('#nationalday_hide_receive, #nationalday_wrap .mask').unbind('touchend').bind('touchend',
            function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#nationalday_wrap').children('.mask').css('display','none');
                that.e.find('#nationalday_receive').css('display','none');
                e.preventDefault();
            });

            //打开领取奖励弹窗
            this.e.find('#nationalday_wrap').undelegate('.reward0','touchend').delegate('.reward0','touchend',
            function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.date = $(this).attr('key');
                that.ymd = $(this).attr('reward_ymd');
                //弹窗上边的日期和元宝
                that.e.find('.receive_date').html('<span>'+that.data.list[that.date].title+'</span>');
                that.e.find('.receive_reward').html('<span>x'+that.data.list[that.date].gold+'</span>');
                that.e.find('#nationalday_wrap').children('.mask').css('display','block');
                that.e.find('#nationalday_receive').css('display','block');
                e.preventDefault();
            });

            //领取按钮
            this.e.find('#nationalday_receive_button').unbind('touchend').bind('touchend',
            function (e) {

                if(xGame.checkLock()) {
                    return false;
                }

                that.receiveReward();
            });
        },

        receiveReward: function () {
            var that = this;

            var initRender = function (res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.reward_gold = that.data.list[that.date].gold;
                        //隐藏弹窗以及弹窗的遮罩层
                        that.e.find('#nationalday_wrap').children('.mask').css('display','none');
                        that.e.find('#nationalday_receive').css('display','none');
                        //让领取后的li不可点 并且添加领取列表界面你点击的那个li的小遮罩
                        that.clickLi = that.e.find('li').eq(that.date);
                        that.clickLi.removeClass('reward0').addClass('reward1');
                        that.clickLi.children('.mask').css('display','block');     
                        //更改你点击的那个li的背景图片
                        that.clickLi.children('span:last-child').removeClass('reward_bg0').addClass('reward_bg1');
                        that.clickLi.children('span:first-child').removeClass('reward_bg10').addClass('reward_bg11');
                        xGame.toast.show(lang('acttreasurepop_msg1', [that.reward_gold]));
                        player.updateinfo({
                            'gold': (parseInt(res.gold))
                        });
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'act_national_day',
                'm': 'open_red_packets',
                'ymd': that.ymd
            },
            initRender);

            initRender = null;
        },

        buildList: function (res) {
            var that = this;
            var html = '';
            var reward = null;
            for (var i = 0, len = res.list.length; i < len; i++) {
                reward = res.list[i];
                html += '<li class="reward reward' + reward.status+'" key="'+i+'" reward_ymd="'+reward.ymd+'">';           
                html += '<span class="gift reward_bg1'+reward.status+'"></span>'; //11已领取的标志
                html += '<span class="mask"></span>';
                html += '<span class="gift reward_bg'+reward.status+'"></span>'; //1已领取0未领取
                html += '</li>';
               
            }

            this.e.find('#nationalday_reward').html(html);
            this.e.find('.tips').html(res.introduction);
            html = null;
        },

        unbindAll: function () {
            this.e.find('.hidebtn').unbind('touchend');
            this.e.find('#nationalday_hide_receive').unbind('touchend');
            this.e.find('#nationalday_wrap').undelegate('.reward0','touchend');
            this.e.find('#nationalday_receive_button').unbind('touchend');
        },
       
        hide:function () {
            xGame.unlock();
            this.unbindAll();
            this.isshow = false;
            this.ymd = null;
            this.date = null;
            this.data = null;
            this.reward_gold = null;
            this.e.remove();
            this.e = null;
            //注意别忘了这个 而且放在最后
            if (nationaldaypop) {
                nationaldaypop = null;
            };
        }
    }
})()