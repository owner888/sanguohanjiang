/* 世界BOSS */
(function() {
    countrybossWorldClass = function() {
        this.e;
        this.indexdata = null;
        this.sideScroller;
    };
    countrybossWorldClass.prototype = {
        init: function() {
            var that = this;

            //开始构建页面数据
            var initRender = function(res) {
                res.status = parseInt(res.status);
                that.indexdata = res;
                switch (res.status) {
                    /* 显示伤害榜页面 */
                    case 1:
                        that.showPrepare(true);                        
                        var obj = {};
                        obj.info = {};
                        obj.info.win = res.win;
                        obj.no_replay = true;
                        miscpop.countryBossReward(obj);
                        break;
                    /* 显示战斗准备页面 */
                    case 2:
                        that.showPrepare();
                        break;
                    case 3:
                        xGame.toast.show(lang('countryboss_closed',[res.starttime]));
                        that.showPrepare(true);
                        break;
                    case 4:
                        xGame.toast.show(lang('activity_end'));
                        that.showPrepare(true);
                        break;
                    case 5:
                        xGame.toast.show(lang('had_join_today'));
                        that.showPrepare(true);
                        break;
                    case 6:
                        xGame.toast.show(lang('lord_lv_too_low',[res.member_level]));
                        xGame.loadWorld('country');
                        break;
                    case 7:
                        xGame.toast.show(lang('no_country'));
                        xGame.loadWorld('country');
                        break;
                    case 8:
                        xGame.toast.show(lang('country_lv_too_low',[res.country_level]));
                        xGame.loadWorld('country');
                        break;
                    default:
                        xGame.toast.show(lang('boss_enter_fail'));
                        xGame.loadWorld('country');
                        break;
                }
            };
             
            loadContents({
                'c': 'countryboss',
                'm': 'index'
            },
            initRender, true); 
            initRender = null;

        },

        /* 准备界面 */
        showPrepare: function(isPreparing) {
            var that = this;
            
            if (that.e == null) {
                var dom_world = $('#world');
                dom_world.append($.template('countryboss_tmpl'));

                that.e = dom_world.find('#countryboss');
                that.e.find('#countryboss_hide').unbind('touchend').bind('touchend',
                function() {
                    setTimeout(function() {
                        xGame.loadWorld('country');
                    },
                    400);
                });
                dom_world = null;
            }
            
            if (isPreparing) {
                //没数据的时候
                that.e.find('#c_gold_up').attr('class','btn pop_btn_middle_disable');
                that.e.find('#c_silver_up').attr('class','btn pop_btn_middle_disable');
                that.e.find('#c_action').attr('class','btn pop_btn_big_disable');
                
                that.e.find('.gold').html('0');
                that.e.find('.silver').html('0');
                that.e.find('.promote').children('em').html('100%');
                
                that.e.find('#c_action').html(lang('not_open'));
                
                xGame.updateCd('cBoss', 0);
                xGame.updateCd('cBossButton', 0);
            } else {
                //有数据的时候
                that.e.find('#c_gold_up').attr('class','btn pop_btn_middle');
                that.e.find('#c_silver_up').attr('class','btn pop_btn_middle');
                that.e.find('#c_action').attr('class','btn pop_btn_big');

                that.e.find('.gold').html(that.indexdata.upcost.gold);
                that.e.find('.silver').html(that.indexdata.upcost.silver);
                that.e.find('.promote').children('em').html(that.indexdata.powerup + '%');
                
                xGame.updateCd('cBoss', that.indexdata.countdown);
                xGame.updateCd('cBossButton', that.indexdata.cd);
                
            }
            
            //生成伤害榜
            if (that.indexdata.top.length == 0) {
                that.e.find('#cboss_scroller_bg').html('<span class="no_data">' + lang('no_word') + '</span>');
            } else {
                var html = '';
                var self = 0;
                for (var i = 0; i < that.indexdata.top.length; i++) {
                    if (that.indexdata.top[i].uid == player.info.uid) {
                        html += '<li><p class="t1"><em>' + that.indexdata.top[i].rank + '</em><b style="color:red;">' + that.indexdata.top[i].nickname + '</b></p><p class="t2">' + that.convertHitNum(that.indexdata.top[i].hit) + '</p></li>';
                        self = that.indexdata.top[i].rank;
                    } else {
                        html += '<li><p class="t1"><em>' + that.indexdata.top[i].rank + '</em><b>' + that.indexdata.top[i].nickname + '</b></p><p class="t2">' + that.convertHitNum(that.indexdata.top[i].hit) + '</p></li>';
                    }
                }
                that.e.find('#cboss_list_scroller').html(html);
                
                if (that.indexdata.top.length > 6) {
                    this.sideScroller = new iScroll('cboss_scroller', {
                        snap : 'li',
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                    that.e.find('#cboss_scroller_bg').addClass('more_than_6');
                    
                    //如果大于3就开始判断，用于始终第三位显示
                    if (self > 3) {
                        var max_num = that.indexdata.top.length - 6;//最大移动的几个单位长度
                        var num = (self - 3) <= max_num ? (self - 3) : max_num;
                        that.sideScroller.scrollTo(0,num * -52);
                    }
                }
            }
            
            //2013-10-10修改 国家boss改成有等级的
            if (typeof that.indexdata.level != 'undefined' && parseInt(that.indexdata.level) > 0) {
                that.e.find('#cbosspop_wrap').append('<div class="cboss_lv">Lv.' + that.indexdata.level + '</div>');
                
            }
            if (typeof that.indexdata.add != 'undefined') {
                that.e.find('#cbosspop_wrap').append('<div class="cboss_add">(' + lang('qingminpop_current_reward') + that.indexdata.add + '%)</div>');
            }
            
            that.bindAll();
            
            $('#worldloading').removeClass('black').hide();

        },
        
        bindAll: function() {
            var that = this;
            
            that.e.undelegate('#cboss_rule_btn', 'touchend').delegate('#cboss_rule_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#countryboss_rule_mask').css('display','block');
                that.e.find('#countryboss_rule').css('display','block');
            });
            
            that.e.undelegate('#cboss_rule_hide', 'touchend').delegate('#cboss_rule_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#countryboss_rule_mask').css('display','none');
                that.e.find('#countryboss_rule').css('display','none');
            });
            
            that.e.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var type = this.id;
                that.attackUp(type);
            });
            
            that.e.find('#c_action').unbind('touchend').bind('touchend',
            function() {
                that.goBattleCheck();
            });
        },

        convertHitNum: function(old) {
            var old = parseInt(old);
            if (old < 1000000) {
                return old;
            } else {
                return Math.round(old / 10000) + lang('10k');
            }
        },

        attackUp: function(type) {
            var that = this;

            var render = function(res) {
                switch (res.status) {
                case 1:
                    xGame.toast.show((type == 'c_gold_up' ? lang('gold') : lang('silver')) + lang('plus_success', [res.powerup]));
                    that.e.find('.promote').children('em').html(res.powerup + '%');
                    if (type == 'c_gold_up') {
                        player.updateinfo({
                            "gold": parseInt(res.all_gold)
                        });
                    } else {
                        player.updateinfo({
                            "silver": parseInt(res.all_silver)
                        });
                    }
                    break;
                case 2:
                    xGame.goPay(lang('no_gold_to_plus'));
                    break;
                case 3:
                    xGame.toast.show(lang('no_silver_to_plus'));
                    break;
                case 4:
                    xGame.toast.show(lang('plus_touch_top'));
                    break;
                default:
                    xGame.toast.show(lang('plus_fail'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':'countryboss',
                'm': 'powerup',
                'gold': (type == 'c_gold_up' ? 1 : 0)
            },render);
        },

        goBattleCheck: function() {
            var that = this;

            if (that.indexdata.cd > 0) {
                xGame.confirm.show(lang('notice'), lang('buy_life_ask', [that.indexdata.cdcost]), 2,
                function() {
                    xGame.confirm.hide(function() {
                        miscpop.enterBattleFromCountryBoss(1);
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            } else {
                miscpop.enterBattleFromCountryBoss(0);
            }
        },

        unbindAll: function () {
            this.e.find('#countryboss_hide').unbind('touchend');
            this.e.find('#c_action').unbind('touchend');
            this.e.undelegate('#cboss_rule_btn', 'touchend');
            this.e.undelegate('#cboss_rule_hide', 'touchend');
            this.e.undelegate('.pop_btn_middle', 'touchend');
        },

        clear: function() {
            xGame.unlock();
            this.indexdata = null;
    
            xGame.updateCd('cBossButton', 0);
            xGame.updateCd('cBoss', 0);
            
            if (this.sideScroller != null) {
                this.sideScroller.destroy();
                delete this.sideScroller;
            }
            
            
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }

        },
    };
})();