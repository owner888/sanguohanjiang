/* 扫荡 */
(function() {
    forcePopClass = function() {
        this.e;
        this.isshow = false;
        this.priceDom;
        this.resultDom;
        this.times;
        this.force_price;
        this.htmlData;
        this.rewardScroller;
    };
    forcePopClass.prototype = {
        init: function(params) {
            var that = this;

            //进入页面
            that.e = xGame.loadPopTmpl('force');
            that.priceDom = that.e.find('#price_dom');
            that.resultDom = that.e.find('#result_dom');
            
            //关闭按钮
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                forcepop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
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
        getReward: function(data, select_times) {
            var html = '';
            html += '<li>';
            //根据type填充
            if (data.from == 'sanctum') {
				data.gem_num = parseInt(data.gem_num);
            	html += '<em class="title">' + lang('forcepop_rush%times', [this.times]) + '</em>';
                html += '<span class="sanctum"><em class="gem_part">' + lang('get') + ':</em><em class="plus">+' + data.chip + '</em></span>';
                if (data.gem_num > 0) {
                    html += '<span class="sanctum"><em class="gem" style="color:' + helper.getItemColor(data.gem_quality) + '">Lv.' + data.gem_level + ' ' + data.gem_name + '*' + data.gem_num + '</em></span>';
                }
            } else if (data.from == 'tower') {
                html += '<em class="title">' + lang('forcepop_rush%times', [this.times]) + '</em>';
                html += '<span class="sanctum"><em class="osoul">' + lang('get') + ':</em><em class="plus">+' + data.soul + '</em></span>';
                if (data.hero_soul != undefined && data.hero_soul.name != undefined) {
                    html += '<span class="sanctum"><em class="gem" style="color:' + helper.getItemColor(data.hero_soul.quality) + '">Lv.' + data.hero_soul.level + ' ' + data.hero_soul.name + '*' + data.hero_soul.num + '</em></span>';
                }
            } else if (data.from == 'heaven') {
                html += '<em class="title">' + lang('forcepop_rush%times', [this.times]) + '</em>';
                html += '<em class="get">' + lang('get') + '：</em>';

                var html1 = '';

                // 精炼石
                if (parseInt(data.refinestone) != 0) {
                    html1 += '<p class="refinestone">' + lang('refinestone') + 'x' + data.refinestone + '</p>';
                }

                // 五彩石
                if (parseInt(data.colorstone) != 0) {
                    html1 += '<p class="colorstone">' + lang('colorstone') + 'x' + data.colorstone + '</p>';
                }

                // 上古血玉
                if (parseInt(data.bloodstone) != 0) {
                    html1 += '<p class="bloodstone">' + lang('bloodstone') + 'x' + data.bloodstone + '</p>';
                }

                var essenceId = parseInt(data.essence.id);
                var essenceNum = parseInt(data.essence.num);

                if (essenceNum != 0) {
                    if (essenceId >= 1 && essenceId <= 6) { // 武器精华
                        html1 += '<p class="equip_essence" style="color:'+ helper.getItemColor(data.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('equip_ess') + 'x' + essenceNum + '</p>';
                    } else if (essenceId >= 7 && essenceId <= 12) { // 防具精华
                        html1 += '<p class="defense_essence" style="color:'+ helper.getItemColor(data.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('defense_ess') + 'x' + essenceNum + '</p>';
                    } else if (essenceId >= 13 && essenceId <= 18) { // 饰品精华
                        html1 += '<p class="acc_essence" style="color:'+ helper.getItemColor(data.essence.quality) +';background-image:url('+ xGame.essence(essenceId) +')">' + lang('acc_ess') + 'x' + essenceNum + '</p>';
                    }
                }

                html += '<div class="reward">' + html1 + '</div>';
                html1 = '';
            }
            html += '</li>';
            
            
            //通用方法
            
            //2013-9-24 
            //这里用append的话在ios7下其实没有重新渲染，看上去图片放大了而没有插入dom，用html可以重新渲染
            var dom = $('#forcepop_list_scroller');
            this.htmlData += html;
            var height = dom.height();
            
            dom.html(this.htmlData);
            
            if (height >= 180) {
                if (this.rewardScroller == null) {
                    this.rewardScroller = new iScroll('forcepop_scroller', {
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: true
                    });
                }
                this.rewardScroller.refresh();
                height = dom.height();
                this.rewardScroller.scrollTo(0, 214 - height, 800);
            }
            
            this.fillPrice(data, select_times);
            
            player.updateinfo({
                "gold": data.all_gold
            });
            
            html =  null;
        },
        
        fillPrice: function(data, select_times) {
            if(typeof select_times != 'undefined' && select_times == 5) { this.force_price = data.five_sub; }
            this.priceDom.find('.type1').attr('price', data.five_sub);
            this.priceDom.find('.type1').find('.gold').html(data.five_sub + '' + lang('gold'));
            if(typeof select_times != 'undefined' && select_times == 10) { this.force_price = data.ten_sub; }
            this.priceDom.find('.type2').attr('price', data.ten_sub);
            this.priceDom.find('.type2').find('.gold').html(data.ten_sub + '' + lang('gold'));
            if(typeof select_times != 'undefined' && select_times == 50) { this.force_price = data.fifty_sub; }
            this.priceDom.find('.type3').attr('price', data.fifty_sub);
            this.priceDom.find('.type3').find('.gold').html(data.fifty_sub + '' + lang('gold'));
            if(typeof select_times != 'undefined' && select_times == 100) { this.force_price = data.hundred_sub; }
            this.priceDom.find('.type4').attr('price', data.hundred_sub);
            this.priceDom.find('.type4').find('.gold').html(data.hundred_sub + '' + lang('gold'));
        },
        
        bindAll: function(params) {
            var that = this;
            that.priceDom.undelegate('span', 'touchend').delegate('span', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('select')) {
                    return false;
                }
                that.priceDom.find('.select').removeClass('select');
                $(this).addClass('select')
                that.times = $(this).attr('value');
                that.force_price = parseInt($(this).attr('price'));
            });
            
            that.e.undelegate('.attack_btns', 'touchend').delegate('.attack_btns', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }

                if(that.force_price > 0) {
                    xGame.confirm.show(lang('notice'), lang('force_confirm', [that.force_price]), 2,
                    function() {
                        xGame.confirm.hide();
                        params.atkFunc(that.times);
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('ok'), lang('close'));
                } else {
                    params.atkFunc(that.times);
                }
                
            });
        },

        unbindAll: function () {
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

        hide: function() {
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