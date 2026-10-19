/* 世界BOSS */
(function() {
    bossWorldClass = function() {
        this.e;
        this.indexdata = null;
        this.oldworld = 'city';
    };
    bossWorldClass.prototype = {
        init: function(oldworld) {
            var that = this;

            that.oldworld = oldworld ? oldworld: 'city';

            //开始构建页面数据
            var initRender = function(res) {
                //res = {"status":1,"countdown":808,"powerup":100,"bossinfo":{"name":"\u5211\u5929","blood":"100.00","image":"worldboss1"},"reward":[],"top":[],"cd":0,"upcost":{"gold":10,"silver":53000},"cdcost":10,"my":{"rank":0,"hit":0}}
                //res = {"status":2,"countdown":808,"powerup":100,"bossinfo":{"name":"\u5211\u5929","blood":"100.00","image":"worldboss1"},"reward":[],"top":[],"cd":0,"upcost":{"gold":10,"silver":53000},"cdcost":10,"my":{"rank":0,"hit":0}}
                res.status = parseInt(res.status);
                switch (res.status) {
                    /* 显示伤害榜页面 */
                case 1:
                    if (typeof activitypop != 'undefined' && activitypop.e != null) {
                        activitypop.hide();
                    }
                    xGame.loadWorld(that.oldworld);
                    xGame.showpop('bossband', res);
                    break;
                    /* 显示战斗准备页面 */
                case 2:
                    if (typeof activitypop != 'undefined' && activitypop.e != null) {
                        activitypop.hide();
                    }
                    that.indexdata = res;
                    that.showPrepare();
                    break;
                case 3:
                    xGame.toast.show(lang('boss_have_not_begin'));
                    xGame.loadWorld(that.oldworld);
                    break;
                case 4:
                    xGame.toast.show(lang('boss_closed'));
                    xGame.loadWorld(that.oldworld);
                    break;
                case 5:
                    xGame.toast.show(lang('come_tomorrow'));
                    xGame.loadWorld(that.oldworld);
                    break;
                default:
                    xGame.toast.show(lang('boss_enter_fail'));
                    xGame.loadWorld(that.oldworld);
                    break;
                }
            };
            loadContents({
                'c': 'worldboss',
                'm': 'index'
            },
            initRender, true);

            initRender = null;

        },

        /* 准备界面 */
        showPrepare: function() {
            var that = this;

            var dom_world = $('#world');
            dom_world.append($.template('bosspop_tmpl'));

            that.e = dom_world.find('#bosspop');
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                xGame.loadWorld(that.oldworld);
                e.preventDefault();
                e.stopPropagation();
            });
            dom_world = null;

            that.e.find('.gold').html(that.indexdata.upcost.gold);
            that.e.find('.silver').html(that.indexdata.upcost.silver);
            that.e.find('.promote').children('em').html(that.indexdata.powerup + '%');
            that.e.undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock()) {
                    return false;
                }
                var type = this.id;
                that.attackUp(type);
            });
            that.e.find('#action').unbind('touchend').bind('touchend',
            function() {
                that.goBattleCheck();
            });
            that.e.find('#nowrank').html(that.indexdata.my.rank);
            that.e.find('#nowhit').html(that.convertHitNum(that.indexdata.my.hit) + '');
            var html = '';
            for (var i = 0; i < 3; i++) {
                if (that.indexdata.top[i]) {
                    html += '<li><p class="t">' + that.indexdata.top[i].nickname + '</p><p class="p">' + that.convertHitNum(that.indexdata.top[i].hit) + '</p></li>';
                } else {
                    html += '<li><p class="t">' + lang('no_word') + '</p><p class="p">0</p></li>';
                }
            }
            that.e.find('#hitlist').html(html);

            xGame.updateCd('bossin', that.indexdata.countdown);
            xGame.updateCd('bosslife', that.indexdata.cd);

            $('#worldloading').removeClass('black').hide();

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
                    xGame.toast.show((type == 'gold_up' ? lang('gold') : lang('silver')) + lang('plus_success', [res.powerup]));
                    that.e.find('.promote').children('em').html(res.powerup + '%');
                    if (type == 'gold_up') {
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
                'c':
                'worldboss',
                'm': 'powerup',
                'gold': (type == 'gold_up' ? 1 : 0)
            },
            render);

            render = null;
        },

        goBattleCheck: function() {
            var that = this;

            if (that.indexdata.cd > 0) {
                xGame.confirm.show(lang('notice'), lang('buy_life_ask', [that.indexdata.cdcost]), 2,
                function() {
                    xGame.confirm.hide(function() {
                        miscpop.enterBattleFromBoss(1);
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            } else {
                miscpop.enterBattleFromBoss(0);
            }
        },

        goBattle: function(now) {
            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {
                //res = {"status":1,"info":{"win":-3},"battle":{"leftteam":{"7":{"heroid":"1934","name":"\u8521\u6587\u59ec","herotype":"2","level":"50","allblood":3053,"power":0,"image":"5","talent":"\u5dfe\u5e3c","skill":"\u51fa\u585e\u66f2"}},"rightteam":{"5":{"heroid":"1","name":"\u5211\u5929","herotype":0,"level":"37","allblood":"100.00","power":0,"image":"worldboss1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb"}},"process":[{"round":1,"launch":"left","attackor":7,"attackpower":25,"skill":0,"hit":0,"attacktype":0,"beattackors":[5],"hurt":[{"pos":5,"blood":2960,"power":25,"self":0,"faint":0}]},{"round":1,"launch":"right","attackor":5,"attackpower":50,"skill":0,"hit":0,"attacktype":0,"beattackors":[7],"hurt":[{"pos":7,"blood":3053,"power":0,"self":0,"faint":0}]}],"assess":-3,"leftresult":[],"rightresult":{"5":{"heroid":"1","name":"\u5211\u5929","herotype":0,"level":"37","allblood":100,"power":50,"image":"worldboss1","talent":"\u5e73\u5eb8","skill":"\u4f1a\u5fc3\u4e00\u51fb","blood":"100.00"}}}}

                switch (res.status) {
                case 1:
                    player.taskaction(12);
                    xGame.toast.show((type == 'gold_up' ? lang('gold') : lang('silver')) + lang('plus_success', [res.powerup]));
                    that.e.find('.promote').children('em').html((100 + parseInt(res.powerup)) + '%');
                    break;
                case 2:
                    that.indexdata.countdown = res.cd;
                    xGame.toast.show(lang('buy_life_ask2'));
                    break;
                case 3:
                    xGame.goPay(lang('no_gold_buy_life'));
                    break;
                case 5:
                    //活动结束
                    xGame.toast.show(lang('boss_closed'));
                    xGame.showpop('bossband');
                    that.hide();
                    break;
                default:
                    xGame.toast.show(lang('enter_battle_fail'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'worldboss',
                'm': 'battle',
                'now': now
            },
            render);

            render = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('#action').unbind('touchend');
                this.e.undelegate('.pop_btn_middle', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },

        clear: function() {
            xGame.unlock();
            this.indexdata = null;
            this.oldworld = '';
            xGame.updateCd('bosslife', 0);
            xGame.updateCd('bossin', 0);
            this.unbindAll();
            player.reloadInfo();
        },
    };
})();