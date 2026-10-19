/* 连续登录 */
(function() {
    loginedPopClass = function() {
        this.e = null;
        this.isshow = false;
        this.rewardDom = null;
        this.myScroller = null;
        this.rewardData = null;//奖励数据
        this.deleteThread = null;  // 删除线程
        this.listItemMarginB = 0;
        this.rewardIndex = 0;   // 最后一个可领取奖励的index
        this.listItemHeight = 0;    // 每条奖励所占高度
        this.max = 0;   // 最大滚动高度
    };

    loginedPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                
                switch (res.status) {
                    case 1:
                        that.initPage(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            
            //加载数据
            loadContents({
                'c': 'logined',
                'm': 'index'
            },
            Func);
            Func = null;
        },
        
        initPage: function(res) {
            
            this.e = xGame.loadPopTmpl('logined');
            
            this.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                loginedpop.hide();
                e.preventDefault();
            });
            
            this.e.find('#time').html(res.day + lang('day'));
            this.rewardDom = this.e.find('#logined_list');
            this.rewardData = res.list;
            this.buildList(res.list);
            this.bindAll();

            this.listItemMarginB = parseInt(this.rewardDom.children('li').css('margin-bottom')); // 每条奖励的边距
            this.listItemHeight = this.rewardDom.children('li').height() + this.listItemMarginB; // 每条奖励所占高度
            var listHeight = this.e.find('#logined_scroller').height();
            this.max = (this.listItemHeight * res.list.length - listHeight) * -1;
            var my = this.listItemHeight * this.rewardIndex * -1;
            
            if (my < this.max) {
                this.rewardDom.children('li:last-child').css('margin-bottom', this.listItemMarginB + this.max - my + 'px');
            }
            
            this.myScroller = new iScroll('logined_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.myScroller.scrollTo(0, my);
            this.myScroller.refresh();
            
            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();
        },
        
        buildList: function(list) {

            var html = '';
            var len = list.length;

            for (var i = 0; i < len;i ++) {

                html += '<li id="logined_l' + list[i].id + '">';

                switch (parseInt(list[i].type)) {
                    case 1:
                        html += '<button class="btn pop_btn_middle" gid="' + list[i].id + '">' + lang('receive') + '</button>';
                        this.rewardIndex = i;
                        break;
                    case 2:
                        html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '">' + lang('not_reached') + '</button>';
                        break;
                    case 3:
                        html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '">' + lang('warena_have_fetch') + '</button>';
                        break;
                    default :
                        break;
                }

                html +='<div class="title">' + lang('loginedpop_title', [list[i].id]) + '</div>';
                html += this.buildRewardHtml(list[i]);
                html += '</li>';
            }

            this.rewardDom.html(html);
            html = null;
        },
        
        bindAll: function() {
            var that = this;

            that.rewardDom.unbind('touchend').one('touchend', function () {
                that.rewardDom.children('li:last-child').css('margin-bottom', that.listItemMarginB + 'px');
                that.myScroller.refresh();
            });

            that.rewardDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveGift(parseInt($(this).attr('gid')));
            });
        },
        
        buildRewardHtml: function(data) {
            var html = '';
            var gettype = 0;
            for (var i = 1; i < 5; i ++) {
                gettype = parseInt(data['gettype' + i]);
                
                if (gettype == 0) {
                    continue;
                } else {
                    html +='<div class="reward reward' + i + ' rtype' + gettype + '">';
                    html +='<span></span>';
                    html +='<b>x' + data['getvalue' + i] + '</b>';
                    html +='</div>';
                }
            }
            return html;
        },
        
        receiveGift: function(gid) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case 1:
                        
                        that.e.find('.logined_reward_box').remove();
                        
                        if (that.deleteThread) {
                            clearTimeout(that.deleteThread);
                        }

                        that.deleteThread = null;
                        
                        player.updateinfo({
                            'gold' : res.all_gold,
                            'act' : res.all_act,
                            'silver' : res.all_silver
                        });

                        that.myScroller.disable();

                        that.rewardDown(gid - 1);

                        // 暂时写死
                        if (res.popo == 0) {
                            if(player.info.openbossreward == 0){
                                $('#header_activity_btn_wrap').children('.popo').hide();
                            }
                            $('#activitypop').find('#activity_logined').find('.popo').hide();
                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        xGame.unlock();
                        break;
                }

            };

            loadContents({
                'c': 'logined',
                'm': 'get_reward',
                'id': gid
            },
            Func);
            Func = null;
        },
        
        rewardDown: function(index) {

            var that = this;
            var html = '';
            var gettype = 0;
            var data = that.rewardData[index];
            html += '<div class="logined_reward_box">';

            for (var i = 1;i < 5; i ++) {
                gettype = parseInt(data['gettype' + i]);
                
                if (gettype == 0) {
                    continue;
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '"><em></em>x' + data['getvalue' + i] + '</span>';
                }
            }

            html += '</div>';
            this.e.find('#loginedpop_wrap').append(html);
            data.type = 3;

            this.rewardDom.find('.btn').eq(index).removeClass('pop_btn_middle').addClass('pop_btn_middle_disable').html(lang('warena_have_fetch'));
            
            this.deleteThread = setTimeout(function() {
                
                if (loginedpop.e) {
                    loginedpop.e.find('.logined_reward_box').remove();
                }

                that.myScroller.enable();

                if (index != 0) {
                    
                    for (var j = 0; j < index; j++) {

                        if (parseInt(that.rewardData[j].type) == 1) {
                            that.rewardIndex = j;
                        }
                    }

                    var my = that.listItemHeight * that.rewardIndex * -1;

                    if (my > that.max) {
                        that.myScroller.scrollTo(0, my, 500);
                    }
                }

                xGame.unlock();

            }, 3000);
            
            html = null;
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.rewardDom) {
                this.rewardDom.unbind('touchend');
                this.rewardDom.undelegate('.pop_btn_middle', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
                        
            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            
            if (this.deleteThread) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }

            this.listItemMarginB = 0;
            this.listItemHeight = 0;
            this.max = 0;
            this.rewardIndex = 0;
            this.rewardData = null;
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            
        },
    };
})();