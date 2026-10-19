/* 国库 */
(function() {
    countryhousePopClass = function() {
        this.e;
        this.isshow = false;

        this.infoDom; //国家信息dom
        this.rewardDom; //国家奖励dom
        this.myScroller;
        this.ulDom;
        this.barDom; //进度条dom
        this.level; //当前国家的等级
        this.diff;//当前的差值
        this.isTimeout = null;
    };
    countryhousePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            if (xGame.checkLock()) {
                return false;
            }
            var render = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    that.initPage(res);
                    break;
                case 2:
                    xGame.toast.show(lang('lv50_open'));
                    break;
                case - 4 : xGame.toast.show(lang('get_house_info_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_house_rule_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_member_list_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                if (parseInt(res.status) != 1) {
                    $('#worldloading').hide();
                }
                xGame.unlock();
                that = null;
            };
            loadContents({
                'c': 'country',
                'm': 'storage'
            },
            render, true); //加载数据
            render = null;
        },

        initPage: function(res) {
            var that = this;

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('countryhouse');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countryhousepop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.infoDom = that.e.find('#countryinfo_dom');
            that.rewardDom = that.e.find('#countryreward_dom');
            that.ulDom = that.e.find('#donatelist');
            that.myScroller = null;
            that.barDom = that.e.find('#country_bar');

            that.fillRewardDom(res.salary);
            that.fillInfoDom(res.info);
            that.fillRule(res.donate);
            that.fillMember(res.list);

            /*设置按钮颜色*/
            if (parseInt(res.get) == 0) {
                that.e.find('#fetch_btn').attr('class', 'btn pop_btn_middle fetch_btn');
            } else {
                that.e.find('#fetch_btn').attr('class', 'btn pop_btn_middle_disable fetch_btn');
            }

            /*第一次特殊处理*/
            that.fillBarNum(res.country);
            var get_width = that.getWidth(res.country);
            if (get_width > 30){
                that.barDom.width('30px').animate({'width':get_width + 'px'}, 500);
            } else {
                that.barDom.width('30px');
            }
            get_width = null;
            that.bindAll();

            if (player.info.level < 50) {
                that.e.find('#donate_gold_btn').html(lang('lv50_can_donate')).attr('class', 'btn pop_btn_middle_disable donate_gold_btn');
                that.e.find('#donate_silver_btn').html(lang('lv50_can_donate')).attr('class', 'btn pop_btn_middle_disable donate_silver_btn');
            }

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            that.isshow = true;

        },

        bindAll: function() {
            var that = this;

            /*领取按钮*/
            that.e.undelegate('#fetch_btn.pop_btn_middle', 'touchend').delegate('#fetch_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.get_reward($(this));
            });

            /*捐献按钮*/
            that.e.undelegate('#donate_silver_btn.pop_btn_middle,#donate_gold_btn.pop_btn_middle', 'touchend').delegate('#donate_silver_btn.pop_btn_middle,#donate_gold_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.donateFunc($(this).attr('ptype'));
            });
            
            that.ulDom.undelegate('.g2', 'click').delegate('.g2', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('info', $(this).attr('uid'));
            });
        },

        fillMember: function(list) {
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<li>';
                html += '<span class="g1">' + list[i].rank + '</span>';
                html += '<span class="g2" uid="' + list[i].uid + '">' + list[i].nickname + '</span>';
                html += '<span class="g3">' + list[i].contribute + '</span>';
                html += '</li>';
            }
            this.ulDom.html(html);

            if (this.myScroller == null) { //不存在的时候
                if (list.length > 6) {
                    this.myScroller = new iScroll('donatelist_scroller', {
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                    this.myScroller.refresh();
                }
            } else { //已经存在的时候
                if (list.length > 6) {
                    this.myScroller.refresh();
                    this.myScroller.scrollTo(0, 0);
                } else {
                    this.myScroller.destroy();
                    this.myScroller = null;
                }
            }

            html = null;
        },

        fillBar: function(data) {
            this.leapShow(data);
            this.fillBarNum(data);
        },

        getWidth: function(data) {
            var myWidth = parseInt(data.exp) / parseInt(data.levelupexp) * 564;
            myWidth = myWidth < 30 ? 30 : myWidth;
            //这里这么写防止小于1的时候animate不执行
            this.diff = myWidth - this.barDom.width();
            return myWidth;
        },

        fillBarNum: function(data) {
            this.e.find('#country_bar_num').html(data.exp + '/' + data.levelupexp);
        },

        fillInfoDom: function(data) {
            this.infoDom.find('#cidom_lv').html(data.level);
            this.infoDom.find('#cidom_contribution').html(data.allcontribute);
            this.infoDom.find('#cidom_rank').html(data.rank);
            this.infoDom.find('#cidom_job').html(data.job_name);
            this.level = parseInt(data.level);
        },

        fillRewardDom: function(data) {
            if (data.act != undefined) {
                this.rewardDom.find('#crdom_jl').html(data.act);
                this.rewardDom.find('.junling').css('display', 'block');
            }
            if (data.silver != undefined) {
                data.silver = parseInt(data.silver);
                data.silver = data.silver > 9999 ? this.getWan(data.silver) : data.silver + '';
                this.rewardDom.find('#crdom_yb').html(data.silver);
                this.rewardDom.find('.silver').css('display', 'block');
            }
            if (data.jade != undefined) {
                this.rewardDom.find('#crdom_ys').html(data.jade);
                this.rewardDom.find('.jade').css('display', 'block');
            }
            if (data.soul != undefined) {
                this.rewardDom.find('#crdom_yh').html(data.soul);
                this.rewardDom.find('.soul').css('display', 'block');
            }
        },

        fillRule: function(list) {
            if (list.silver != undefined) {
                this.e.find('#donate_silver_cost').html(list.silver + lang('silver'));
                this.e.find('#donate_tips').find('.c1').html(list.silver);
            }

            if (list.gold != undefined) {
                this.e.find('#donate_gold_cost').html(list.gold + lang('gold'));
                this.e.find('#donate_tips').find('.c2').html(list.gold);
            }

            this.e.find('#donate_tips').find('.c3').html(list.contribute);
        },

        getWan: function(num) {
            return Math.round(parseInt(num) / 10000) + lang('10k');
        },

        /* 领取奖励 */
        get_reward: function(obj) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('update_player_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('get_house_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_house_info_fail'));
                    break;
                case - 2 : that.exceptionFunc();
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    obj.removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                    if (res.act != undefined) {
                        player.updateinfo({
                            'act': parseInt(res.act)
                        });
                    }
                    if (res.silver != undefined) {
                        player.updateinfo({
                            'silver': parseInt(res.silver)
                        });
                    }
                    if (res.jade != undefined) {
                        player.updateinfo({
                            'jade': parseInt(res.jade)
                        });
                    }
                    if (res.soul != undefined) {
                        player.updateinfo({
                            'soul': parseInt(res.soul)
                        });
                    }

                    xGame.toast.show(lang('reward_success'));
                    break;
                case 2:
                    xGame.toast.show(lang('today_had_recive'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv50_open'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
                that = null;
            }
            loadContents({
                'c': 'country',
                'm': 'get_salary'
            },
            Func);
            Func = null;
        },

        /* 捐献 */
        donateFunc: function(type) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {
                /*特殊处理下*/
                if (res.status != 1) {
                    xGame.unlock();
                }
                switch (parseInt(res.status)) {
                case - 11 : xGame.toast.show(lang('countrypop_contribution_limit'));
                    break;
                case - 8 : xGame.toast.show(lang('plus_house_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('update_house_info_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('get_member_info_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('parameter_error'));
                    break;
                case - 4 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('deduct_silver_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_house_rule_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    player.updateinfo({
                        'gold':
                        parseInt(res.gold),
                        'silver': parseInt(res.silver)
                    });
                    that.fillBar(res.country);
                    that.fillInfoDom(res.info);
                    that.fillMember(res.list);
                    that.fillRule(res.donate);

                    xGame.toast.show(lang('donate_get', [res.donate.contribute]));
                    break;
                case 2:
                    xGame.toast.show(lang('country_full_lv'));
                    break;
                case 3:
                    xGame.toast.show(lang('silver_not_enough'));
                    break;
                case 4:
                    xGame.toast.show(lang('gold_not_enough'));
                    break;
                case 5:
                    xGame.toast.show(lang('lv50_open'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                that = null;
            };
            loadContents({
                'c': 'country',
                'm': 'donate',
                'type': type
            },
            Func);
            Func = null;
        },

        /*参数，传入country的对象*/
        leapShow : function(data){
            var that = this;
            /*用锁防止它没看完就点*/
            xGame.islock = true;
            var get_width = that.getWidth(data);
            var times = data.level - that.level;

            if(times == 0){/*没升级*/
                if(get_width == 30){
                    xGame.islock = false;
                }else{
                    if (that.diff < 1) {
                        that.barDom.width(get_width + 'px');
                        xGame.islock = false;
                    } else {
                        that.barDom.animate({'width':get_width + 'px'},500,'ease',function(){
                            xGame.islock = false;
                        });
                    }
                }
            }else{
                /*升级了的时候，*/
                that.levelUpShow();
                if(that.barDom.width()  == 564){
                    that.barDom.width('30px');
                }
                that.barDom.animate({'width':'564px'}, 500,'ease',function(){
                    times --;

                    if(times < 0){
                        xGame.islock = false;
                        return;
                    }else{
                        if (times == 0) { //最后一下
                            if (get_width == 30) {
                                that.barDom.width('30px');
                                xGame.islock = false;
                            } else {
                                if (that.diff < 1) {
                                    that.barDom.width(get_width + 'px');
                                    xGame.islock = false;
                                } else {
                                    that.barDom.width('30px').animate({
                                        'width': get_width + 'px'
                                        },
                                        500, 'ease',
                                        function() {
                                            xGame.islock = false;
                                    });
                                }
                            }
                        } else { //中间补全
                            that.barDom.width('30px').animate({
                                'width': '564px'
                            },
                            500);
                        }
                        that.isTimeout = setTimeout(arguments.callee,800);
                    }
                });
            }
        },

        levelUpShow: function() {
            var obj = $('<div class="country_lvup"></div>');
            obj.appendTo(this.e.find('#countryhousepop_wrap')).unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                obj.remove();
            });
        },

        /*异常处理：被提出国家时*/
        exceptionFunc: function() {
            player.info.country = 0;
            xGame.toast.show(lang('you_are_kicked_out'));
            if (this.isshow) {
                this.hide();
            }
            xGame.loadWorld('city');
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.undelegate('#fetch_btn.pop_btn_middle', 'touchend');
                this.e.undelegate('#donate_silver_btn.pop_btn_middle,#donate_gold_btn.pop_btn_middle', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.ulDom) {
                this.ulDom.undelegate('.g2', 'click');
                this.ulDom.remove();
                this.ulDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.infoDom) {
                this.infoDom.remove();
                this.infoDom = null;
            }
            if (this.rewardDom) {
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.barDom) {
                this.barDom.remove();
                this.barDom = null;
            }
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            this.level = null;
            this.unbindAll();
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                delete this.myScroller;
            }      
            
        },
    };
})();