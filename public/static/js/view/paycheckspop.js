/* 邀请好友 */
(function() {
    paychecksPopClass = function() {
        this.e;
        this.myScroller;
        this.maxId;//最大的那个奖励ID
        this.infoDom = null;
    };
    paychecksPopClass.prototype = {
        init: function() {
            var that = this;

            var initRender = function(res) {
                if(res.status == 1 || res.status == 2) {//1.可以被邀请 2.已被邀请
                    that.initPage(res);
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'act_paychecks',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
        },

        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('paychecks');
            
            that.e.find('#hide_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                that.hide();
            });

            that.e.find('#paychecks_intro_btn').unbind('touchend').bind('touchend',
            function(e) {
                that.infoPage();
            });

            // 活动倒计时
            if (parseInt(res.cd) > 0) {
                xGame.updateCd('paychecks', res.cd);
            } else {
                that.e.find('#paychecks_cd').html(lang('had_end'));
            }
            
            //邀请码
            that.e.find('#my_code').html(res.code);
            //已邀请人数
            that.e.find('#my_invitenum').children('span').html(res.number);
            
            // 战友充值额度
            if (res.pay_gold != null) {
                that.e.find('#paychecks_pay_gold').html(helper.convertWan(res.pay_gold));
            }

            // 当前可领工资
            if (res.pay_check != null) {
                that.e.find('#paychecks_get_gold').html(helper.convertWan(res.pay_check));
            }

            //奖励列表
            if(res.list) {
                var html = '';
                for(var i = 0; i < res.list.length; i++) {
                    html += '<li class="inviteitem open" id="inviteitem'+res.list[i].id+'">';
                    html += '<p class="target">'+ res.list[i].desc +'</p>';
                    if(res.list[i].completed != 2) { html += '<p class="process '+(res.list[i].completed!=0?'green':'')+'">'+lang('inviteitem_mynumber')+' <span>'+res.number+'/'+res.list[i].count+'</span></p>'; }//当前进度
                    html += '<p class="reward">'+lang('reward')+'</p>';
                    html += '<p class="reward_type type'+res.list[i].type+'">'+res.list[i].number+'</p>';
                    html += '<button class="btn pop_btn_middle'+(res.list[i].completed != 1 ? '_disable' : '')+' fetch_btn" data-rewardid="'+res.list[i].id+'">'+(res.list[i].completed == 1 ? lang('receive') : (res.list[i].completed == 2 ? lang('warena_have_fetch') : lang('uncomplete')))+'</button>';
                    html += '</li>';
                    that.maxId = res.list[i].id;
                }
                that.e.find('#paychecks_list').html(html);
            }
            that.e.find('#paychecks_list').undelegate('.pop_btn_middle.fetch_btn', 'touchend').delegate('.pop_btn_middle.fetch_btn', 'touchend',
            function() {
                var rewardid = $(this).data('rewardid');
                that.fetchReward(rewardid);
            });
            //邀请人按钮状态
            if(res.is_act == 0) {
                that.e.find('#paychecks_myinvitor_btn').hide();
            } else {
                that.e.find('#paychecks_myinvitor_btn').unbind('touchend').bind('touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (player.info.paychecks == 1) {
                        miscpop.showNewinvitecode();
                    } else {
                        miscpop.showInvitecode();
                    }
                });
            }

            that.myScroller = new iScroll('paycheckspop_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            
        },

        fetchReward: function(rewardid) {
            var that = this;

            var initRender = function(res) {
                res.status = parseInt(res.status);
                switch(res.status) {
                    case 1:
                        xGame.toast.show(lang('reward_success'));
                        if(res.all_gold && res.all_gold > 0) {//如果是元宝的条目，更新一下元宝数
                            player.updateinfo({gold: res.all_gold});
                        }
                        //按钮状态修改
                        that.e.find('#inviteitem'+rewardid).children('.process').hide();
                        that.e.find('#inviteitem'+rewardid).children('.fetch_btn').attr('class', 'btn pop_btn_middle_disable fetch_btn').html(lang('warena_have_fetch'));
                        //如果是最后一个奖励领取了，需要对activitypop做一下处理
                        if(rewardid == that.maxId) {
                            var activity_paychecks = $('#activity_paychecks');
                            if(activity_paychecks.length > 0) {
                                activity_paychecks.remove();
                                activity_paychecks = null;
                                if(activitypop && activitypop.myScroller) {
                                    activitypop.myScroller.refresh();
                                }
                            }
                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            
            loadContents({
                'c': 'act_paychecks',
                'm': 'get_reward',
                'id': rewardid
            },
            initRender);
            initRender = null;
        },

        infoPage: function() {
            var that = this;
            that.infoDom = that.e.find('#paychecks_info_dom');

            that.infoDom.show();

            // 玩法介绍的关闭按钮
            that.infoDom.find('#paychecks_info_hide').unbind('touchend').bind('touchend', function(e) {
                e.preventDefault();
                that.infoDom.hide();
            });

            // 玩法介绍的遮罩层
            that.infoDom.find('.mask').unbind('touchend').bind('touchend', function (e) {
                e.preventDefault();
                that.infoDom.hide();
            });
        },

        unbindAll: function() {
            this.e.find('#hide_btn').unbind('touchend');
            this.e.find('#paychecks_intro_btn').unbind('touchend');
            this.e.find('#paychecks_myinvitor_btn').unbind('touchend');
            this.e.find('#paychecks_info_hide').unbind('touchend');
            this.e.find('#paychecks_list').undelegate('.pop_btn_middle.fetch_btn', 'touchend');
        },

        hide: function() {
            xGame.unlock();
            this.unbindAll();
            xGame.updateCd('paychecks', 0);

            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }

            this.infoDom = null;
            this.e.remove();
            this.e = null;
        },
    };
})();