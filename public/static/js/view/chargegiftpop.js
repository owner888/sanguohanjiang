/* 充值赠礼*/
(function() {
    chargegiftPopClass = function() {
        this.e;
        this.isshow = false;
        this.rewardScroller;
        this.rewardData;
        this.totalchip = 0;
    };

    chargegiftPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function(res) {
                switch(res.status) {
                    case 1:
                        that.rewardData = res.reward_info;
                        that.initPage(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            
            //加载数据
            loadContents({
                'c': 'act_thirty',
                'm': 'index'
            }, render);
            render = null;
        },

        initPage: function(res) {
            var that = this;
            this.e = xGame.loadPopTmpl('chargegift');
            $('#worldloading').hide();
            this.isshow = true;
            
            //关闭按钮
            this.e.find('#chargegift_hide').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                chargegiftpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            //充值按钮
            this.e.find('#chargeact_btn').unbind('touchend').bind('touchend', function(e){
                chargegiftpop.hide();
                xGame.enterPay();
                e.preventDefault();
                e.stopPropagation();
            });

            //武将碎片
            this.e.find('.headimg').unbind('touchend').bind('touchend', function(e){
                for (var i = 0; i < that.rewardData.length; i++) {
                    if (parseInt(that.rewardData[i].type) == 15) {
                        miscpop.showHeroInfo(that.rewardData[i].general);
                        break;
                    }
                }
                 e.preventDefault();
                e.stopPropagation();
            });

            // 奖励列表中的奖励项
            this.e.find('#chargegiftpop_scroller').undelegate('.img', 'click').delegate('.img', 'click', function (e) {
               
               if (xGame.checkLock(true)) {
                    return true;
                }
                var parent = $(this).parent();
                if (parent.hasClass('type12') || parent.hasClass('type15')) {
                    var index = $('#chargegiftpop_scroller .box').index(parent); // 取得武将/武将碎片在所有奖励中的索引
                    miscpop.showHeroInfo(that.rewardData[index].general);
                } else if (parent.hasClass('type8') || parent.hasClass('type16') ) {
                    var index = $('#chargegiftpop_scroller .box').index(parent); // 取得装备/装备碎片在所有奖励中的索引
                    miscpop.showItemInfo(that.rewardData[index].info);
                }

                e.preventDefault();
                e.stopPropagation();
            });

            //领取奖励按钮
            this.e.find('#giftact_btn').unbind('touchend').bind('touchend', function(e){
                var giftact = that.e.find('#giftact_btn');
                if (giftact.hasClass('disabled')) {
                    return;
                } else {
                    var render = function(res) {
                        switch(res.status) {
                            case 1:
                                giftact.addClass('disabled').html(lang('warena_have_fetch'));
                                player.updateinfo({'gold': res.gold});
                                player.updateinfo({'silver': res.silver});
                                that.e.find('.soulnum').find('em').html(res.own_chip+"/"+that.totalchip);
                                if(res.chip_status == 1) {
                                    that.e.find('#heroact_btn').removeClass('disabled').html(lang('receive'));
                                } else if(res.chip_status == 2) {
                                    that.e.find('#heroact_btn').removeClass('disabled').addClass('disabled').html(lang('warena_have_fetch'));
                                }
                                that.showMessage(res.reward_info);
                                break;
                            default:
                                xGame.toast.show(res.msg);
                                break;
                        }
                    };
            
                    //加载数据
                    loadContents({
                        'c': 'act_thirty',
                        'm': 'action_reward'
                    }, render);
                    render = null;
                }

                e.preventDefault();
                e.stopPropagation();
            });
            
            //领取武将按钮
            this.e.find('#heroact_btn').unbind('touchend').bind('touchend', function(e){
                var heroact = that.e.find('#heroact_btn');
                if (heroact.hasClass('disabled')) {
                    return;
                } else {
                    var render = function(res) {
                        switch(res.status) {
                            case 1:
                                heroact.addClass('disabled').html(lang('warena_have_fetch'));
                                if (res.reward_info.type == 15) {
                                    miscpop.newhero(res.reward_info.info);
                                } else {
                                    //自动折旧成紫宝石
                                    //xGame.toast.show(res.reward_info.msg);
                                }
                                break;
                            default:
                                xGame.toast.show(res.msg);
                                break;
                        }
                    };
            
                    //加载数据
                    loadContents({
                        'c': 'act_thirty',
                        'm': 'action_chip_reward'
                    }, render);
                    render = null;
                }

                e.preventDefault();
                e.stopPropagation();
            });

            //玩法说明按钮
            this.e.find('#info_btn').unbind('touchend').bind('touchend', function(e) {
                that.e.find('#chargegift_info').css('display', 'block');
                e.preventDefault();
                e.stopPropagation();
            });

            //关闭玩法说明
            this.e.find('#chargegift_info').find('.mask').unbind('touchend').bind('touchend', function(e){
                that.e.find('#chargegift_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            this.e.find('#chargegift_info').find('.hidebtn').unbind('touchend').bind('touchend', function(e){
                that.e.find('#chargegift_info').css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            var html = this.getHtml(res);
            this.e.find('#chargegift_list').html(html).width(res.reward_info.length * 77 + 'px');
            html = null;
            if (this.rewardScroller == null) {
                this.rewardScroller = new iScroll('chargegiftpop_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.rewardScroller.refresh();

            //绑定滑动门左右按钮
            this.e.find('#scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                that.rewardScroller.scrollToPage('prev', 0);
                return false;
            });
            this.e.find('#scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                that.rewardScroller.scrollToPage('next', 0);
                return false;
            });
            
        },

        // 奖励掉落
        showMessage: function(rewards) {
            var html = '';
            var that = this;
            html += '<div class="reward_box">';

            for (var i = 0; i <rewards.length; i++) {
                var reward = rewards[i];
                if (reward.type == 8 || reward.type == 16) {//装备 装备碎片
                    html += '<span class="reward_down pos' + i + ' type'+ reward.type +'" style="color:'+ helper.getItemColor(reward.quality) +'"><em class="img" style="background-image:url('+ xGame.itemurl(reward.id) +')"></em>' + reward.name + 'x'+reward.num+'</span>';
                } else if (reward.type == 12 || reward.type == 15) {//武将 武将碎片
                    if (reward.type == 15) {
                        html += '<span class="reward_down pos' + i + ' type'+ reward.type +'"><em class="img" style="background-image:url('+ xGame.avatar(reward.value) +')"></em>' + reward.name + 'x'+reward.num+'</span>';
                    } else {
                        html += '<span class="reward_down pos' + i + ' type'+ reward.type +'"><em class="img" style="background-image:url('+ xGame.avatar(reward.general.image) +')"></em>' + reward.general.name + 'x'+reward.num+'</span>';
                    }
                } else if (reward.type == 14) {//坐骑
                    html += '<span class="reward_down pos' + i + ' type'+ reward.type +'" style="color:'+ helper.getItemColor(reward.info.quality) +'"><em class="img" style="background-position:'+ helper.getHorsePostion('buff', reward.info.id) +'"></em>' + reward.info.name + 'x'+reward.num+'</span>';
                } else {
                    html += '<span class="reward_down pos' + i + '"><em class="img reward type'+ reward.type +'"><em class="img"></em></em>x' + reward.num + '</span>';
                }
            }

            html += '</div>';
            this.e.find('.content').append(html);
            
            this.achieveTimer = setTimeout(function() {
                if (that.e.css('display') == 'block') {
                    that.e.find('.reward_box').remove();
                }
            }, 3000);
            
            html = null;
        },

        getHtml: function (res) {
            var list = res.reward_info;
            var html = '';
            //活动时间
            this.e.find('.duration').html(res.time);
            //活动当前天数
            this.e.find('.currtime').find('.red').html(res.day);
            //当前充值/充值上限
            this.e.find('.currcharge').html(lang('chargegift_currcharge')+res.gold+"/"+res.max_gold); 
            //领取奖励按钮
            var giftact = this.e.find('#giftact_btn');
            switch(res.is_open) {
                case 0:
                   giftact.removeClass('disabled').addClass('disabled').html(lang('not_reached'));
                   break;
                case 1:
                   giftact.removeClass('disabled').html(lang('receive_reward'));
                   break;
                case 2:
                   giftact.removeClass('disabled').addClass('disabled').html(lang('warena_have_fetch'));
                   break;
                default:
                   break;
            }
            //当前碎片/碎片总量
            this.totalchip = res.total_chip;
            this.e.find('.soulnum').find('em').html(res.own_chip+"/"+res.total_chip);
            //领取武将按钮
            var heroact = this.e.find('#heroact_btn');
            switch(res.chip_status) {
                case 0:
                   heroact.removeClass('disabled').addClass('disabled').html(lang('not_reached'));
                   break;
                case 1:
                   heroact.removeClass('disabled').html(lang('receive_reward'));
                   break;
                case 2:
                   heroact.removeClass('disabled').addClass('disabled').html(lang('warena_have_fetch'));
                   break;
                default:
                   break;
            }
            for (var i = 0, len = list.length; i < len; i++) {
                if (len <= 3) {
                    this.e.find('#scroller_btn_left').hide();
                    this.e.find('#scroller_btn_right').hide();
                } else {
                    this.e.find('#scroller_btn_left').show();
                    this.e.find('#scroller_btn_right').show();
                }

                html += '<li>';
                var item = list[i];
                
                html += '<div class="box type'+ item.type +'">';
                
                if (parseInt(item.type) == 12 || parseInt(item.type) == 15) {//武将 武将碎片
                    
                    html += '<span class="img" style="background-image:url(' + xGame.avatar(item.general.image) + ');background-size: 60px;"></span>';
                    if (parseInt(item.type) == 15) {
                        html += '<span class="soulpart"></span>';
                        html += '<em class="value">x' + item.num + '</em>';
                        this.e.find('.herohead').find('.headimg').css('background-image', 'url('+ xGame.avatar(item.general.image) +')');
                    } else {
                        html += '<em class="value" style="color:'+helper.getHeroColor(item.general.quality)+'">' + item.name + '</em>';
                    }
                    
                } else if (parseInt(item.type) == 8 || parseInt(item.type) == 16) { //装备 装备碎片
                    html += '<span class="img" style="background-image:url('+ xGame.itemurl(item.info.image) +');"></span>';
                    if (parseInt(item.type) == 16) {
                        html += '<span class="itempart"></span>';
                        html += '<em class="value">x' + item.num + '</em>';
                    } else {
                        html += '<em class="value" style="color:'+ helper.getItemColor(item.info.quality) +';">' + item.name + '</em>';
                    }
                } else {
                    html += '<span class="img"></span>';
                    html += '<em class="value">x' + item.num + '</em>';
                }

                html += '</div></li>';
            }

            return html;
        },

        unbindAll: function() {
            this.e.find('#chargegift_hide').unbind('touchend')
            this.e.find('#chargeact_btn').unbind('touchend')
            this.e.find('.headimg').unbind('touchend')
            this.e.find('#chargegiftpop_scroller').undelegate('.img', 'click')
            this.e.find('#giftact_btn').unbind('touchend')
            this.e.find('#heroact_btn').unbind('touchend')
            this.e.find('#info_btn').unbind('touchend')
            this.e.find('#chargegift_info').find('.mask').unbind('touchend')
            this.e.find('#chargegift_info').find('.hidebtn').unbind('touchend')
            this.e.find('#scroller_btn_left').unbind('touchend')
            this.e.find('#scroller_btn_right').unbind('touchend')
        },
        
        hide: function() {
            xGame.unlock();
            this.isshow = false;

            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }

            if (this.rewardScroller) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }

            if (this.achieveTimer) {
                clearTimeout(this.achieveTimer);
            }

            this.rewardData = null;
        },
    };
})();