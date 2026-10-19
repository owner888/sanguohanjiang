/*貔貅聚财*/
(function() {
    enrichmentPopClass = function() {
        this.e;
        this.isshow = false;
        this.pageData = null; //开始页面数据
        this.rewardsData = null; //奖励界面数据
        this.getrewardData = null; //领取奖励数据
        this.mainDom = null; //主界面dom
        this.infoDom = null; //玩法说明dom
        this.rewardDom = null; //领取奖励dom
        this.rewardTime = null; //iscroll的延时refresh;
        this.rewardScroller = null; //领取奖励展示iscroll
        this.nowchoice = null;
    };

    enrichmentPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.pageData = res;
                        that.fillData();
                        break;
                    default:
                        $('#worldloading').hide();
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            loadContents({
                'c': 'act_enrichment',
                'm': 'index'
            },
            Func);
            Func = null;
        },

        //填充主界面doom
        fillData: function() {
            this.isshow = true;
            this.e = xGame.loadPopTmpl('enrichment');
            this.mainDom = this.e.find('#enrichmentpop_wrap');
            this.infoDom = this.e.find('#enrichment_info_dom');
            this.rewardDom = this.e.find('#enrichment_reward_dom');
            this.bind();

            xGame.updateCd('enrichment',this.pageData.cd); //更新CD

            // //展示三只貔貅的状态
            var info = this.pageData.reward_info;

            var dom = this.mainDom.find('.enrichment_three_item');
            for (var i = 0; i < info.length; i++) {
                var status = info[i].status;
                var type = info[i].type;

                //添加貔貅奖励类型
                dom.eq(i).attr("bid", type);

                if (status) {
                    dom.eq(i).find('.pixiu_bg').removeClass('unreached'+ (i + 1) +'').addClass('reached'+ (i + 1) +'');
                    dom.eq(i).find('.isshowlogo').addClass('showlogo');
                };
            };

            //展示充值额度
            var num = this.pageData.gold_num;
            this.mainDom.find('#enrichment_rechargelimit').text(helper.convertWan(num));
            if (num >= 6480) {
                this.mainDom.find('.limit_bar').width(456);
                this.mainDom.find('#enrichment_graduation_bar1').show();
                this.mainDom.find('#enrichment_graduation_bar2').show();
            }else if (num > 0 && num < 6480 ) {
                var height = 0;
                if(num > 1000 && num <= 3280) {
                    height = (num - 1000) / 2280 * (245 - 70) + 70 ;
                    this.mainDom.find('#enrichment_graduation_bar1').show();
                }else if (num > 3280 && num < 6480 ) {
                    height = (num - 3280) / 3280 * (456 - 245) + 245;
                    this.mainDom.find('#enrichment_graduation_bar1').show();
                    this.mainDom.find('#enrichment_graduation_bar2').show();
                }else {
                    height = num / 6480 * 456;    
                }
                this.mainDom.find('.limit_bar').width(height);
                this.mainDom.find('.bright_bar').show();
            }

            // 控制领取奖励按钮
            if (this.pageData.button_status == 1) {
                this.mainDom.find('#enrichment_seereward').text(lang('receive_reward'));
            }
            
            $('#worldloading').hide();
        },

        //获取奖励界面数据
        getRewardsInfo: function() {
            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.rewardsData = res;
                        that.fillrewardsData();
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            loadContents({
                'c': 'act_enrichment',
                'm': 'reward_index'
            },
            Func);
            Func = null;
        },

        //填充奖励界面数据
        fillrewardsData: function() {
            var that = this;
            var html = '';

            for (var i = 0, len = that.rewardsData.reward_info[0].length; i < len; i++) {
                html += '<li class="rewards_item"><span class="thetime">'+lang('theday' + (i + 1) + '')+'</span>';
                for (var j = 0, lens = that.rewardsData.reward_info.length; j < lens; j++) {
                    var reward = that.rewardsData.reward_info[j][i];
                    var status = reward.is_get;
                    var rewardclass = 'getReward_btn_disable';
                    if (status == 1) {
                        rewardclass = 'getReward_btn';
                    } else if (status == 2) {
                        rewardclass = 'getReward_btn_disable hasgotReward';
                    }
                    if (reward.type == 1) {
                        html += '<div class="reward ptype' + (j + 1) + '"><span class="reward_showgold"></span><em class="reward_num">x'+ reward.value +'</em><button bid="' + (i + 1) + '" class="btn '+ rewardclass +'">' + lang('get_reward') + '</button></div>'; 
                    } else if (reward.type == 2) {
                        html += '<div class="reward ptype' + (j + 1) + '"><span class="reward_showsoul"  bid="' + j + '" hid="' + i + '" style="background-image:url(http://img2.hanjiangsanguo.com/hjimg/pop/soul_' + reward.info.image +'.png)"></span><button bid="' + (i + 1) + '" class="btn '+ rewardclass +'">' + lang('get_reward') + '</button></div>'; 
                    } else if (reward.type == 3) {
                        html += '<div class="reward ptype' + (j + 1) + '"><span class="reward_showitem"  bid="' + j + '" hid="' + i + '" style="background-image: url(' + xGame.itemurl(reward.info.id) + ');"></span><button bid="' + (i + 1) + '" class="btn '+ rewardclass +'">' + lang('get_reward') + '</button></div>'; 
                    }
                }
                html += '</li>';
            };
            this.rewardDom.find('#enrichment_rewards_list').html(html);
            this.rewardDom.find('.hasgotReward').text(lang('warena_have_fetch'));
            that.rewardDom.show();

            //上面貔貅模板改变状态
            var dom = this.rewardDom.find('.pixiu_modelitem');
            for (var k = 0,len2 = that.pageData.reward_info.length; k < len2; k++) {
                var get = that.pageData.reward_info[k].status;
                if (get == 0) {
                     dom.eq(k).removeClass('model'+ (k + 1) +'').addClass('unreach_model'+ (k + 1) +'')
                }
            };

            if (this.rewardScroller == null) {
                this.rewardScroller = new iScroll('enrichment_rewards_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.rewardTime = setTimeout(function() {
                that.rewardScroller.refresh();
            },200)
        },

        //领取奖励后后台返回信息
        getRewardcom: function(day){
            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.getrewardData = res;
                        that.showrewardInfo();
                        that.nowchoice.removeClass('getReward_btn').addClass('getReward_btn_disable hasgotReward').text(lang('warena_have_fetch'));
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
            };
            loadContents({
                'c': 'act_enrichment',
                'm': 'get_day_reward',
                'day': day,
            },
            Func);
            Func = null;
        },

        //展示已领取的奖励
        showrewardInfo: function(){
            player.updateinfo({
                'gold' : this.getrewardData.gold
            });

            var reward = this.getrewardData.reward;
            var html = '';
            if (parseInt(reward.type) == 1) {
                html += '<span class="reward gold">x' + reward.value + '</span>';
            }else if (parseInt(reward.type) == 2) {
                html += '<span style="color:'+ helper.getItemColor(reward.info.quality) +'">'+ reward.info.name +'</span>';
            }else if (parseInt(reward.type) == 3) {
                html += '<span style="color:'+ helper.getItemColor(reward.info.quality) +'">'+ reward.info.name +'</span>';
            }
            xGame.toast.show(lang('get') + html);
            html = null;
        },
        
        bind: function() {
            var that = this;

            //主界面dom关闭按钮
            this.e.find('#enrichment_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                e.preventDefault();
            });

            //玩法说明按钮
            this.mainDom.find('#enrichment_info').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.infoDom.show();
                e.preventDefault();
            });

            //充值按钮
            this.mainDom.find('.recharge_btn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                xGame.showpop('iospay');
                e.preventDefault();
            });

            //前往领取奖励dom
            this.mainDom.find('#enrichment_seereward').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
               
                that.getRewardsInfo();
                
                e.preventDefault();
            });

            //主界面貔貅的弹窗
            this.mainDom.find('#enrichment_three_list').undelegate('.enrichment_three_item','touchend').delegate('.enrichment_three_item','touchend',function(e){
                if (xGame.checkLock(true)) {
                    return false;
                }
                var id = $(this).index();
                var type = parseInt($(this).attr('bid'));
                if (type == 3) {
                    miscpop.showItemInfo(that.pageData.reward_info[id].info);
                }else if (type == 2) {
                    miscpop.showSoulInfo(that.pageData.reward_info[id].info);
                }
                e.preventDefault();
            })

            //关闭玩法说明按钮
            this.infoDom.find('#enrichment_info_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.infoDom.hide();
                e.preventDefault();
            });

            //点击mask即可关闭玩法说明
            this.infoDom.find('.mask').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.infoDom.hide();
                e.preventDefault();
            });

            //关闭领取奖励dom
            this.rewardDom.find('#enrichment_reward_hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.rewardScroller) {
                    that.rewardScroller.destroy();
                    that.rewardScroller = null;
                }
                if (that.rewardTime) {
                    clearTimeout(that.rewardTime);
                    that.rewardTime = null;
                }
                that.rewardDom.hide();
                e.preventDefault();
            });

            //点击将魂弹框
            this.rewardDom.find('#enrichment_rewards_list').undelegate('.reward_showsoul','click').delegate('.reward_showsoul','click',function(e){
                if (xGame.checkLock(true)) {
                    return false;
                }
                var bid = parseInt($(this).attr('bid'));
                var hid = parseInt($(this).attr('hid'));
                miscpop.showSoulInfo(that.rewardsData.reward_info[bid][hid].info);
            });

            //点击道具弹框
            this.rewardDom.find('#enrichment_rewards_list').undelegate('.reward_showitem','click').delegate('.reward_showitem','click',function(e){
                if (xGame.checkLock(true)) {
                    return false;
                }
                var bid = parseInt($(this).attr('bid'));
                var hid = parseInt($(this).attr('hid'));
                miscpop.showItemInfo(that.rewardsData.reward_info[bid][hid].info);
            });

            //领取奖励
            this.rewardDom.find('#enrichment_rewards_list').undelegate('.getReward_btn','click').delegate('.getReward_btn','click',function(e){
                if (xGame.checkLock(true)) {
                    return false;
                }
                var id = parseInt($(this).attr('bid'));
                that.getRewardcom(id);
                that.nowchoice =  $(this);
            });
        },

        unbind: function() {
            if (this.infoDom) {
                this.infoDom.find('#enrichment_info_hidebtn').unbind('touchend');
                this.infoDom.find('.mask').unbind('touchend');
                this.infoDom = null;
            }
            if (this.mainDom) {
                this.mainDom.find('#enrichment_info').unbind('touchend');
                this.mainDom.find('.recharge_btn').unbind('touchend');
                this.mainDom.find('#enrichment_seereward').unbind('touchend');
                this.mainDom.find('#enrichment_three_list').undelegate('.enrichment_three_item','touchend');
                this.mainDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.find('#enrichment_reward_hidebtn').unbind('touchend');
                this.rewardDom.find('#enrichment_rewards_list').undelegate('.getReward_btn','click');
                this.rewardDom = null;
            }
            if (this.e) {
                this.e.find('#enrichment_hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            this.unbind();
            this.isshow = false;
            xGame.updateCd('enrichment', 0);
            if (this.rewardScroller) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            if (this.rewardTime) {
                clearTimeout(this.rewardTime);
                this.rewardTime = null;
            }
            this.pageData = null;
            this.rewardsData = null;
            this.getrewardData = null;
            this.nowchoice = null;
        },
    };
})();