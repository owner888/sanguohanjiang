/* 扫荡 */
(function() {
    sweepPopClass = function() {
        this.e;
        this.isshow = false;
        this.priceDom;
        this.resultDom;
        this.times;
        this.force_price;
        this.htmlData
        this.rewardScroller;
        this.from;
    };
    sweepPopClass.prototype = {
        init : function(params) {
            var that = this;

            //进入页面
            that.e = xGame.loadPopTmpl('sweep');
            that.priceDom = that.e.find('#price_dom');
            that.resultDom = that.e.find('#result_dom');

            //默认第一次5次
            that.times = 5;
            //第一次价格
            that.force_price = params.five_sub;
            that.fillPrice(params);

            /*2013-9-24
             ios7的时候先生成iscroll的话前几下就渲染不出来了，不知道为什么。
             所以在高度超过时再生成，其实也可以节省效率
             that.rewardScroller = new iScroll('forcepop_scroller', {
             momentum: false,
             hScrollbar: false,
             vScrollbar: true
             });
             that.rewardScroller.refresh();
             */
            that.bindAll(params);
            $('#worldloading').removeClass('black').hide();
            $('#worldloading').hide();
            that.htmlData = '';
        },

        /*
         传一个对象进来进行操作
         from：类型，必须,自己设置的
         all_gold 剩余元宝，必须
         five_sub 必须
         ten_sub 必须
         fifty_sub 必须
         hundred_sub 必须
         */
        getReward : function(data, select_times) {
            var html = '';
            var rewards = data.reward;
            var len = rewards.length;
            if (len < 3) {
                html += '<li>';
                //根据type填充
                if (data.from == 'pacify') {
                    html += '<em class="title">' + lang('forcepop_rush%times', [this.times]) + '</em>';
                    html += '<span class="pacify"><em class="reputation_part">' + lang('get') + ':</em><em class="plus">+' + data.reputation + '</em></span><div class="pacify_reward">';
                    for (var i = 0; i < len; i++) {//rewards.length
                        var reward = rewards[i];
                        var type = reward.type;
                        var j = i + 1;
                        if (type == 3) {
                            html += '<span class="type' + j + '" style="background-image:url(' + xGame.forgeMaterial(reward.materials_id) + ')"><em style="color:' + helper.getItemColor(reward.quality) + '">' + reward.name + 'x' + reward.value + '</em></span>';
                        } else if (type == 1 || type == 2) {
                            html += '<span class="type' + j + '" style="background-image:url(' + xGame.itemurl(reward.id) + ')"><em style="color:' + helper.getItemColor(reward.quality) + '">' + reward.name + 'x' + reward.value + '</em></span>';
                        }
                    };
                };
            } else {
                html += '<li class="htype' + len + '">';
                //根据type填充
                if (data.from == 'pacify') {
                    html += '<em class="title">' + lang('forcepop_rush%times', [this.times]) + '</em>';
                    html += '<span class="pacify"><em class="reputation_part">' + lang('get') + ':</em><em class="plus">+' + data.reputation + '</em></span><div class="pacify_reward">';
                    for (var i = 0; i < len; i++) {//rewards.length
                        var reward = rewards[i];
                        var type = reward.type;
                        var j = i + 1;
                        if (type == 3) {
                            html += '<span class="type' + j + '" style="background-image:url(' + xGame.forgeMaterial(reward.materials_id) + ')"><em style="color:' + helper.getItemColor(reward.quality) + '">' + reward.name + 'x' + reward.value + '</em></span>';
                        } else if (type == 1 || type == 2) {
                            html += '<span class="type' + j + '" style="background-image:url(' + xGame.itemurl(reward.id) + ')"><em style="color:' + helper.getItemColor(reward.quality) + '">' + reward.name + 'x' + reward.value + '</em></span>';
                        }
                    };
                };
            }
            html += '</div></li>';

            //通用方法
            //2013-9-24
            //这里用append的话在ios7下其实没有重新渲染，看上去图片放大了而没有插入dom，用html可以重新渲染
            var dom = $('#forcepop_list_scroller');
            this.htmlData += html;
            var height = dom.height();

            dom.html(this.htmlData);

            if (height >= 120) {
                if (this.rewardScroller == null) {
                    this.rewardScroller = new iScroll('forcepop_scroller', {
                        momentum : false,
                        hScrollbar : false,
                        vScrollbar : true
                    });
                }
                this.rewardScroller.refresh();
                height = dom.height();
                this.rewardScroller.scrollTo(0, 214 - height, 800);
            }

            this.fillPrice(data.info, select_times);

            player.updateinfo({
                "gold" : data.info.gold,
                'act' : data.info.act,
            });

            html = null;
        },

        fillPrice : function(data, select_times) {
            if ( typeof select_times != 'undefined' && select_times == 1) {
                this.force_price = data.one_sub;
            }
            if ( typeof select_times != 'undefined' && select_times == 5) {
                this.force_price = data.five_sub;
            }
            if ( typeof select_times != 'undefined' && select_times == 10) {
                this.force_price = data.ten_sub;
            }
            this.priceDom.find('.type1').attr('price', data.one_sub);
            this.priceDom.find('.type1').find('.gold').html(data.one_sub + '' + lang('gold'));
            this.priceDom.find('.type2').attr('price', data.five_sub);
            this.priceDom.find('.type2').find('.gold').html(data.five_sub + '' + lang('gold'));
            this.priceDom.find('.type3').attr('price', data.ten_sub);
            this.priceDom.find('.type3').find('.gold').html(data.ten_sub + '' + lang('gold'));
            this.priceDom.find('.type4').html(lang('pacify_times_out_buy', [data.vip_times]));
        },

        bindAll : function(params) {
            var that = this;

            //关闭按钮
            that.e.find('.hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                e.preventDefault();
            });
            that.priceDom.undelegate('span', 'touchend').delegate('span', 'touchend', function() {
                if (xGame.checkLock(true) || $(this).hasClass('select')) {
                    return false;
                }
                that.priceDom.find('.select').removeClass('select');
                $(this).addClass('select')
                that.times = $(this).attr('value');
                that.force_price = parseInt($(this).attr('price'));
            });

            that.e.undelegate('.attack_btns', 'touchend').delegate('.attack_btns', 'touchend', function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.force_price > 0) {
                    xGame.confirm.show(lang('notice'), lang('force_confirm', [that.force_price]), 2, function() {
                        xGame.confirm.hide();
                        params.atkFunc(that.times);
                    }, function() {
                        xGame.confirm.hide();
                    }, lang('ok'), lang('close'));
                } else {
                    params.atkFunc(that.times);
                }

            });
        },

        unbindAll : function() {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.undelegate('.attack_btns', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.priceDom) {
                this.priceDom.undelegate('span', 'touchend');
                this.priceDom.remove();
                this.priceDom = null;
            }
        },

        hide : function() {
            xGame.unlock();
            //界面隐藏
            this.isshow = false;

            if (this.rewardScroller != null) {
                this.rewardScroller.destroy();
                this.rewardScroller = null;
            }
            this.htmlData = null;
            if (this.resultDom) {
                this.resultDom.remove();
                this.resultDom = null;
            }
            this.times = null;
            this.force_price = null;

            this.unbindAll();
        },
    };
})();
