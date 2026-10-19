/* 突飞扫荡 */
(function() {
    practiceleapPopClass = function() {
        this.e;
        this.isshow = false;
        this.priceDom;
        this.resultDom;
        this.times;
        this.mylefttimes;
        this.gid;
        this.htmlData;
        this.rewardScroller;
    };
    practiceleapPopClass.prototype = {
        init: function(params) {
            var that = this;

            //进入页面
            that.e = xGame.loadPopTmpl('forcepracticeleap');//增加突飞的class（位置，突飞卡数量）
            that.priceDom = that.e.find('#price_dom');
            that.resultDom = that.e.find('#result_dom');
            
            //关闭按钮
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                practiceleappop.hide();
                e.preventDefault();
            });
            //默认第一次5次
            that.mylefttimes = parseInt(params.freetimes);
            that.times = parseInt(params.timeslist[0]);
            that.gid = params.gid;
            that.fillData(params);
            
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
        
        fillData: function(data) {
            this.e.find('#all_tfcard').html(data.freetimes);
            for(var i = 0; i < 4; i++) {
                var _cls = '.type'+(i+1);
                var _val = data.timeslist[i];
                this.priceDom.find(_cls).attr('value', _val);
                this.priceDom.find(_cls).find('.tfcard').html('x'+_val);
            }
        },

        getReward: function(data) {
            var html = ''
            html += lang('enemypop_cleanup_message_practiceleap', [this.times, data.exp, data.ext_exp]);

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
            
            html =  null;
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
                that.times = parseInt($(this).attr('value'));
            });
            
            that.e.undelegate('.attack_btns', 'touchend').delegate('.attack_btns', 'touchend',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if(that.mylefttimes < that.times) {
                    xGame.toast.show(lang('practiceleap_no_times'));
                } else {
                    params.atkFunc(that.times);
                }

            });
        },

        atkFunc: function(num) {
            var that = this;
            
            if (xGame.checkLock()) {
                return false;
            }
            
            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {

                    case 1:

                        //exp
                        //ext_exp
                        //general
                        that.mylefttimes = parseInt(res.freeleap);
                        that.e.find('#all_tfcard').html(res.freeleap);
                        practiceleappop.getReward(res);
                        
                        //修改数据
                        practicepop.heroData[practicepop.shid].level = res.general.level;
                        practicepop.heroData[practicepop.shid].exp = res.general.exp;
                        practicepop.heroData[practicepop.shid].levelupexp = res.general.levelupexp;
                        practicepop.freeTimes = that.mylefttimes;

                        //修改界面显示
                        if(parseInt(practicepop.freeTimes) == 0) {//修复突飞卡为0时的BUG
                            practicepop.checkByFreeTimes(res.next);
                        }
                        practicepop.rightTop2Dom.find('#tufei_ft').html(practicepop.freeTimes);

                        var percent = parseInt(res.general.exp) / parseInt(res.general.levelupexp);
                        practicepop.rightTop2Dom.find('#practice_bar').css('width', parseInt(percent * 305 + 13) + 'px');

                        practicepop.rightTop2Dom.find('#practice_lv').html(res.general.level);
                        practicepop.heroDom.find('#hero_lv').html('Lv.' + res.general.level);

                        practicepop.rightTop2Dom.find('#practice_exp').html(practicepop.heroData[practicepop.shid].exp);
                        practicepop.rightTop2Dom.find('#practice_levelepexp').html(practicepop.heroData[practicepop.shid].levelupexp);
                        
                        break;
                    case 2:
                        xGame.toast.show(lang('practiceleap_no_times'));
                        break;
                    case 3:
                    case 4:
                        xGame.confirm.show(lang('notice'), lang('practiceleap_exp_out', [that.times]), 1,
                        function() {
                            xGame.confirm.hide();
                        });
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };
            loadContents({
                'c': 'practice',
                'm': 'mop',
                'gid': that.gid,
                'times': num
            }, Func);
            Func = null;
            
        },

        unbindAll: function() {
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
            this.unbindAll();
            if (this.resultDom) {
                this.resultDom.remove();
                this.resultDom = null;
            }
            this.times = null;
        },
    };
})();