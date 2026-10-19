/* 天赋 */
(function() {
    talentPopClass = function() {
        this.e;
        this.isshow = false;
        this.talentlist = null;
        this.now_jades = 0; //当前玉石
        this.now_id = 0; //当前查看的天赋ID
        this.now_mode = 0; //模式
        this.isprotected = 0;
        this.issuccess = 0;
    };
    talentPopClass.prototype = {
        init: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == -1) {
                    xGame.toast.show(lang('lv_too_low_enter_talent'));
                    $('#worldloading').hide();
                } else if (res.status != 1) {
                    xGame.toast.show(lang('can_not_enter_talent') + res.status);
                    $('#worldloading').hide();
                } else {
                    that.buildPage(res);
                }

            };
            loadContents({
                'c': 'talent',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        buildPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('talent');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                talentpop.hide();
                e.preventDefault();

            });

            that.talentlist = that.rebuildTalentlist(res.list); //将数组转换为对象
            that.now_jades = res.jade;
            that.now_scrolls = res.scroll;

            //天赋列表
            var talentlist_html = '';
            for (var i in that.talentlist) {
                var _talent = that.talentlist[i];
                talentlist_html += '<li id="talent' + _talent.type + '" class="talentitem box' + _talent.quality + '">';
                talentlist_html += '<span class="pic" style="background-image:url(' + staticUrl + 'img/pop/talent_item' + _talent.type + '.png);"></span>';
                talentlist_html += '<span class="star">' + _talent.star + '</span>';
                talentlist_html += '</li>';
            }
            that.e.find('#talentpop_top').children('ul').html(talentlist_html);

            that.e.find('#talentpop_top').undelegate('.talentitem', 'touchend').delegate('.talentitem', 'touchend',
            function() {
                var _now_id = parseInt(this.id.replace('talent', ''));
                if (that.now_id == _now_id) {
                    return false;
                }
                that.loadTalent(_now_id);
            });
            that.e.find('#select_wrap').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (this.id == 'protected_select') {
                    if (that.isprotected) {
                        that.isprotected = 0;
                        that.issuccess = 0;
                        $('#select_wrap').attr('class', '');
                    } else {
                        that.isprotected = 1;
                        that.issuccess = 0;
                        $('#select_wrap').attr('class', 'protected_select');
                    }
                } else if (this.id == 'success_select') {
                    if (that.issuccess) {
                        that.isprotected = 0;
                        that.issuccess = 0;
                        $('#select_wrap').attr('class', '');
                    } else {
                        that.isprotected = 0;
                        that.issuccess = 1;
                        $('#select_wrap').attr('class', 'success_select');
                    }
                }
            });
            var do_btn_class = appLanguage == 'th' ? '.pop_btn_middle' : '.pop_btn_big';
            that.e.find('#talentpop_right').undelegate(do_btn_class, 'touchend').delegate(do_btn_class, 'touchend',
            function() {
                that.levelup({
                    'c': 'talent',
                    'm': 'levelup',
                    'type': that.now_id,
                    'protect': that.isprotected,
                    'success': that.issuccess
                });
            });
            //底部信息
            var talentdata = {
                "talent": that.talentlist[1],
                "jade": that.now_jades,
                "starup": res.starup,
                "ismax": res.ismax,
                "mode": res.mode,
                "scroll": res.scroll
            };
            that.now_id = talentdata.talent.type;
            that.viewTalent(talentdata);

            that.e.show();
            $('#worldloading').hide();
        },

        rebuildTalentlist: function(talentlist) {
            var obj = {};
            for (var i = 0; i < talentlist.length; i++) {
                var newkey = i + 1;
                obj[newkey] = talentlist[i];
            }
            return obj;
        },

        levelup: function(data) {
            var that = this;

            var render = function(res) {
                if (res.status == -3) {
                    xGame.toast.show(lang('can_not_get_star_rule'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('can_not_lv_star'));
                } else if (res.status == 2) {
                    xGame.toast.show(lang('tianfu_is_full'));
                } else if (res.status == 3) {
                    xGame.toast.show(lang('jade_not_enough_for_talent'));
                } else if (res.status == 4) {
                    var nowtalent = that.talentlist[that.now_id];
                    var str = '<em style="color:' + helper.getItemColor(that.now_mode == 1 ? parseInt(nowtalent.quality) + 1 : nowtalent.quality) + ';">' + helper.getTalent(nowtalent.type, true) + lang('juanzhou') + '</em>';
                    xGame.toast.show(lang('talentpop_not_enough', [str]));
                } else if (res.status == 5) {
                    xGame.toast.show(lang('vip_lv_too_low_to_star'));
                } else if (res.status == 6) {
                    xGame.toast.show(lang('gold_not_enough_for_star'));
                } else if (res.status != 1) {
                    xGame.toast.show(lang('wait_for_star'));
                } else {
                    if (res.failed == 1) { //升星失败
                        that.levelResult(2);
                    } else { //成功升星
                        that.levelResult(1, res.hit);
                    }

                    that.now_jades = parseInt(res.jade);

                    if (res.failed != 1 && data.protect == 1) { //重置顿悟
                        that.isprotected = 0;
                    }

                    var talentdata = {
                        "talent": res.info,
                        "jade": res.jade,
                        "starup": res.starup,
                        "ismax": res.ismax,
                        "mode": res.mode,
                        "scroll": res.scroll
                    };
                    that.buildTalentDetail(talentdata);
                    that.buildTalentLevelup(res.info);

                    var updatedate = {
                        "gold": res.gold
                    };
                    player.updateinfo(updatedate);
                }
            };
            loadContents(data, render); //加载数据
            render = null;
        },

        /* 强化结果 */
        levelResult: function(type, baoji) {

            var html = $('<div class="talent_result_wrap"></div>');

            var movieclip;

            var cfg = {
                "x": 176,
                "y": -50
            };
            var callback = function() {
                setTimeout(function() {
                    html.fadeOut();
                },
                1000);
            }

            //创建动画底图
            movieclip = new movieClipClass({
                "img": staticUrl + 'img/pop/strongpop_result_anim.png',
                "width": 300,
                "height": 220,
                "x": cfg.x,
                "y": cfg.y,
                "loop": false,
                "callback": callback,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 90
                },
                {
                    rect: [300, 0],
                    interval: 90
                },
                {
                    rect: [600, 0],
                    interval: 90
                },
                {
                    rect: [0, 220],
                    interval: 90
                },
                {
                    rect: [300, 220],
                    interval: 90
                },
                {
                    rect: [600, 220],
                    interval: 90
                },
                ]
            });
            movieclip.appendTo(html);
            //创建title
            html.append('<span class="type' + type + '">' + (baoji == 1 ? '<span class="reward_crit rc2"></span>': '') + '</span>');
            //开始
            this.e.find('#talentpop_wrap').append(html);
            movieclip.play();
            movieclip = null;
        },

        loadTalent: function(_now_id) {
            var that = this;

            var render = function(res) {
                if (res.status == 1) {
                    that.now_id = _now_id;
                    var talentdata = {
                        "talent": that.talentlist[that.now_id],
                        "jade": that.now_jades,
                        "starup": res.starup,
                        "ismax": res.ismax,
                        "mode": res.mode,
                        "scroll": res.scroll
                    };
                    that.viewTalent(talentdata);
                } else {
                    xGame.toast.show(lang('view_talent_fail'));
                    return false;
                }
            };
            loadContents({
                'c': 'talent',
                'm': 'get_info',
                "type": _now_id
            },
            render); //加载数据
            render = null;

        },

        /* 查看单个天赋 */
        viewTalent: function(talentdata) {
            var that = this;

            that.e.find('.talentitem').removeClass('now');
            that.e.find('#talent' + that.now_id).addClass('now');
            that.buildTalentDetail(talentdata);

        },

        buildTalentDetail: function(talentdata) {
            var that = this;

            that.now_mode = talentdata.mode;
            var dom_left = that.e.find('#talentpop_left');
            dom_left.find('.name_wrap').html(helper.getTalent(that.now_id)).attr('class', 'name_wrap quality' + talentdata.talent.quality);
            dom_left.find('.now_wrap').html(lang('current_lv') + '<em>' + talentdata.talent.star + '</em>(' + helper.getTalentColor(talentdata.talent.quality) + ')<br/>' + lang('tianfu_properties') + '<span>' + helper.getTalent(that.now_id) + ' +' + talentdata.talent.power + lang('all_of_hero') + '</span>');
            if (talentdata.ismax == 1) { //满级
                dom_left.find('.next_wrap').html(lang('next_lv') + '<span>' + lang('is_full_s') + '</span>');
            } else {
                dom_left.find('.next_wrap').html(lang('next_lv') + '<em>' + talentdata.starup.star + '</em>(' + helper.getTalentColor(talentdata.starup.quality) + ')<br/>' + lang('tianfu_properties') + '<span>' + helper.getTalent(that.now_id) + ' +' + talentdata.starup.power + lang('all_of_hero') + '</span>');
            }
            dom_left = null;

            var dom_right = that.e.find('#talentpop_right');
            dom_right.find('.own_stone').children('span').html(talentdata.jade);

            if(talentdata.ismax == 1) {//满级的情况
                dom_right.find('.own_chip').hide();
                dom_right.find('.cost_chip').hide();
                dom_right.find('.cost_stone').hide();
                dom_right.find('#success_select').hide();
                dom_right.find('#protected_select').hide();
            } else {
                if(appLanguage == 'th') {
                    dom_right.find('.own_chip').html(lang('juanzhou') + helper.getTalent(that.now_id, true) + helper.getTalentColor(talentdata.starup.quality) + ':<br/>' + lang('juanzhou_th_num', [talentdata.scroll.num]) + '</span>').show();
                    dom_right.find('.cost_chip').html(lang('juanzhou') + helper.getTalent(that.now_id, true) + helper.getTalentColor(talentdata.starup.quality) + ':<br/>' + lang('juanzhou_th_num', [talentdata.starup.part]) + '</span>').show();
                } else {
                    dom_right.find('.own_chip').html(helper.getTalentColor(talentdata.starup.quality) + '' + helper.getTalent(that.now_id, true) + lang('juanzhou_have', [talentdata.scroll.num]) + '</span>').show();
                    dom_right.find('.cost_chip').html(helper.getTalentColor(talentdata.starup.quality) + '' + helper.getTalent(that.now_id, true) + lang('juanzhou_have', [talentdata.starup.part]) + '</span>').show();
                }
                dom_right.find('.cost_stone').children('span').html(talentdata.starup.jade);
                if (parseInt(talentdata.jade) < parseInt(talentdata.starup.jade)) {
                    dom_right.find('.cost_stone').children('span').addClass('isred');
                } else {
                    dom_right.find('.cost_stone').children('span').removeClass('isred');
                }
                dom_right.find('.cost_stone').show();

                if (parseInt(talentdata.scroll.num) < parseInt(talentdata.starup.part)) {
                    dom_right.find('.cost_chip').children('span').addClass('isred');
                } else {
                    dom_right.find('.cost_chip').children('span').removeClass('isred');
                }
                dom_right.find('.cost_chip').show();

                if (talentdata.starup.success && talentdata.starup.success > 0) {
                    dom_right.find('#success_select').find('.price').html(talentdata.starup.success + lang('gold'));
                    dom_right.find('#success_select').show();
                } else {
                    dom_right.find('#success_select').hide();
                }
                if (talentdata.starup.protect && talentdata.starup.protect > 0) {
                    if (talentdata.starup.success) { //顿悟的时候，只有VIP9才能用金币100%
                        var top_px = appLanguage == 'th' ? '129px' : '84px';
                        dom_right.find('#select_wrap').css('top', top_px);
                    }
                    dom_right.find('#protected_select').find('.price').html(talentdata.starup.protect + lang('gold'));
                    dom_right.find('#protected_select').show();
                } else {
                    var top_px = appLanguage == 'th' ? '187px' : '142px';
                    dom_right.find('#select_wrap').css('top', top_px);
                    dom_right.find('#protected_select').hide();
                }
            }

            var base_btn_class = appLanguage == 'th' ? 'pop_btn_middle' : 'pop_btn_big';//th此处需要测试
            if (talentdata.ismax == 1) { //满级
                dom_right.find('#talent_btn').attr('class', 'btn ' + base_btn_class + '_disable').html(lang('is_full_s'));
            } else if (that.now_mode == 1) { //顿悟
                dom_right.find('#talent_btn').attr('class', 'btn ' + base_btn_class).html(lang('dunwu'));
            } else if (that.now_mode == 2) { //升星
                dom_right.find('#talent_btn').attr('class', 'btn ' + base_btn_class).html(lang('star'));
            }

            that.e.find('#talentpop_top').find('#talent' + talentdata.talent.type).attr('class', 'talentitem box' + talentdata.talent.quality + ' now');
        },

        buildTalentLevelup: function(data) { //升级后的特殊处理
            var that = this;

            that.talentlist[that.now_id].star = data.star;
            that.talentlist[that.now_id].quality = data.quality;
            that.talentlist[that.now_id].power = data.power;

            var dom_star = that.e.find('#talent' + that.now_id).find('.star');
            xGame.doAnimate2(dom_star, 'changcolor', 100, 7,
            function() {
                dom_star.html(data.star);
                dom_star = null;
            });
            that = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#talentpop_top').undelegate('.talentitem', 'touchend');
                this.e.find('#select_wrap').undelegate('li', 'touchend');
                var do_btn_class = appLanguage == 'th' ? '.pop_btn_middle' : '.pop_btn_big';
                this.e.find('#talentpop_right').undelegate(do_btn_class, 'touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;

            this.talentlist = null;
            this.now_jades = 0; //当前玉石
            this.now_id = 0; //当前查看的天赋ID
            this.now_mode = 0; //模式
            this.isprotected = 0;
            this.issuccess = 0;

            this.unbindAll();
        },
    };
})();
