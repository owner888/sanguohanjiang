/* 邀请好友 */
(function() {
    invitationPopClass = function() {
        this.e;
        this.myScroller;
        this.maxId;//最大的那个奖励ID
    };
    invitationPopClass.prototype = {
        init: function() {
            var that = this;

            var initRender = function(res) {
                if(res.status == 1 || res.status == 2) {//1.可以被邀请 2.已被邀请
                    that.initPage(res);
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            
            loadContents({
                'c': 'invitation',
                'm': 'index'
            },
            initRender, true);
            initRender = null;
        },

        //设置页面
        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('invitefriend');
            
            that.e.find('#hide_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                e.stopPropagation();
                that.hide();
            });
            that.e.find('#invitefriend_intro_btn').unbind('touchend').bind('touchend',
            function(e) {
                that.infoPage();
            });
            
            //邀请码
            that.e.find('#my_code').html(res.code);
            //已邀请人数
            that.e.find('#my_invitenum').children('span').html(res.number);
            //奖励列表
            if(res.list) {
                var html = '';
                for(var i = 0; i < res.list.length; i++) {
                    html += '<li class="inviteitem open" id="inviteitem'+res.list[i].id+'">';
                    html += '        <p class="target">'+lang('inviteitem_limit', [res.list[i].count])+'</p>';//邀请 <span>'+res.list[i].count+'</span> 个好友达到50级
                    if(res.list[i].completed != 1) { html += '        <p class="process '+(res.list[i].completed!=0?'green':'')+'">'+lang('inviteitem_mynumber')+' <span>'+res.number+'/'+res.list[i].count+'</span></p>'; }//当前进度
                    html += '        <p class="reward">'+lang('reward')+'</p>';
                    html += '        <p class="reward_type type'+res.list[i].type+'">'+res.list[i].number+'</p>';
                    html += '        <button class="btn pop_btn_middle'+(res.list[i].completed != 2 ? '_disable' : '')+' fetch_btn" data-rewardid="'+res.list[i].id+'">'+(res.list[i].completed == 2 ? lang('receive') : (res.list[i].completed == 1 ? lang('warena_have_fetch') : lang('uncomplete')))+'</button>';
                    html += '</li>';
                    that.maxId = res.list[i].id;
                }
                that.e.find('#inviteitem_wrap').html(html);
            }
            that.e.find('#inviteitem_wrap').undelegate('.pop_btn_middle.fetch_btn', 'touchend').delegate('.pop_btn_middle.fetch_btn', 'touchend',
            function() {
                var rewardid = $(this).data('rewardid');
                that.fetchReward(rewardid);
            });
            //邀请人按钮状态
            if(res.status == 2) {
                that.e.find('#invitefriend_myinvitor_btn').hide();
            } else {
                that.e.find('#invitefriend_myinvitor_btn').unbind('touchend').bind('touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (player.info.paychecks == 1) {
                        miscpop.showNewinvitecode();
                    } else {
                        miscpop.showInvitecode();
                    }
                });
            }

            that.myScroller = new iScroll('invitefriendpop_scroller', {
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
                            var activity_invitation = $('#activity_invitation');
                            if(activity_invitation.length > 0) {
                                activity_invitation.remove();
                                activity_invitation = null;
                                if(activitypop && activitypop.myScroller) {
                                    activitypop.myScroller.refresh();
                                }
                            }
                        }
                        break;
                    case 2:
                        xGame.toast.show(lang('inviteitem_people_not_enough'));
                        break;
                    case 3:
                        xGame.toast.show(lang('had_receive'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            
            loadContents({
                'c': 'invitation',
                'm': 'get_reward',
                'id': rewardid
            },
            initRender);
            initRender = null;
        },

        infoPage: function() {
            var that = this;

            that.e.find('#invitefriend_info_dom').show();
            that.e.find('#invitefriend_info_hide').unbind('touchend').bind('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                that.e.find('#invitefriend_info_dom').hide();
            });
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#hide_btn').unbind('touchend');
                this.e.find('#invitefriend_intro_btn').unbind('touchend');
                this.e.find('#invitefriend_myinvitor_btn').unbind('touchend');
                this.e.find('#invitefriend_info_hide').unbind('touchend');
                this.e.find('#inviteitem_wrap').undelegate('.pop_btn_middle.fetch_btn', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            this.unbindAll();
            
        },
    };
})();