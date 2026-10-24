/* 国家学院 */
(function() {
    countrycollegePopClass = function() {
        this.collegeskillScroller;
        this.skillslist;
        this.nowViewId = 1;
        this.wandBtn = null; // 使将军令按钮
        this.skillInfo = null; // 右侧的技能信息
        this.wandNum = 0; // 将军令数量
        this.needWandNum = 0; // 需要消耗的将军令数量
    };
    countrycollegePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch (res.status) {
                    case 1:
                        that.skillslist = res.skill_list;
                        that.initFirst(res);
                        that.buildList(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'war_college',
                'm': 'index'
            },
            initRender);
            initRender = null;
        },

        initFirst: function(res) {

            this.e = xGame.loadPopTmpl('countrycollege');
            this.wandBtn = this.e.find('#countrycollege_use_wand');
            this.skillInfo = this.e.find('#countrycollegepop_right');
            this.skillInfo.find('.wand').html('x' + res.general);
            this.wandNum = res.general;
            this.needWandNum = res.need_general;

            this.bindAll();
            
            this.initNoticeCd(res);

            this.initLeftInfo(res.skill_buff.addition);

            var skill = this.skillslist[this.nowViewId-1];
            this.initRightInfo(skill);
            
            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();
        },

        bindAll: function() {
            var that = this;
            //关闭按钮
            this.e.find('#countrycollege_hidebtn').unbind('touchend').bind('touchend', function(e){

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            //玩法说明按钮
            this.e.find('#info_btn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#countrycollegepop_info').css('display', 'block');
                e.preventDefault();
            });

            this.e.find('#countrycollegepop_info').find('.mask').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#countrycollegepop_info').css('display', 'none');
                e.preventDefault();
            });

            //玩法说明关闭按钮
            this.e.find('#info_hidebtn').unbind('touchend').bind('touchend', function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#countrycollegepop_info').css('display', 'none');
                e.preventDefault();
            });

            //研究按钮
            this.e.find('#study_btn').unbind('touchend').bind('touchend', function(e) {

                if (that.e.find('#study_btn').hasClass('disabled') || xGame.checkLock(true)) {
                    return false;
                }
                var skill_name = $(this).attr('skill_name');
                var skill_lv = $(this).attr('skill_lv');
                var confirm_type = $(this).attr('confirm_type');
                var need_country_gold = $(this).attr('need_country_gold');
                var need_gold = $(this).attr('need_gold');

                if (confirm_type == 1) {
                    xGame.confirm.show('', lang('countrycollege_study_msg1', [skill_name+'LV'+skill_lv]), 2, function () {
                        that.studyBtnClick();
                        xGame.confirm.hide();
                    }, function () {
                        xGame.confirm.hide();
                    });
                } else {
                    var html = '<p>' + lang('countrycollege_study_msg2', ['<span class="red">'+ skill_name +'</span>', '<span class="add_country_gold">'+ need_country_gold +'</span>', '<span class="add_gold">'+ need_gold +'</span>']) + '</p>';
                    html += '<p class="countrycollege_tips">' + lang('countrycollege_study_msg6') + '</p>';
                    xGame.confirm.show(lang('level_up'), html, 2, function () {
                        that.studyBtnClick();
                        xGame.confirm.hide();
                    }, function () {
                        xGame.confirm.hide();
                    });
                }

                e.preventDefault();
            });

            // 使用将军令按钮
            that.skillInfo.delegate('#countrycollege_use_wand.pop_btn_small', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (parseInt(that.wandNum) > 0) {
                    xGame.confirm.show(lang('countrycollege_wand_title'), lang('countrycollege_use_wand', [that.needWandNum]), 2, function () {
                        xGame.confirm.e.removeClass('beauty_confirm_hint');
                        that.useWand();
                        xGame.confirm.hide();
                    }, function () {
                        xGame.confirm.e.removeClass('beauty_confirm_hint');
                        xGame.confirm.hide();
                    });

                    xGame.confirm.e.addClass('beauty_confirm_hint');
                } else {
                    xGame.confirm.show(lang('countrycollege_buy_title'), lang('countrycollege_buy_content'), 2, function () {
                        xGame.confirm.hide();
                        that.hide();
                        xGame.showpop('countrymall');
                    }, function () {
                        xGame.confirm.hide();
                    });
                }

                e.preventDefault();
            });
        },

        initNoticeCd: function(res) {
            if (res.is_open == 1) {
                this.e.find('.notice_wrap').css('display', 'block');
                this.e.find('.notice_wrap2').css('display', 'none');
                this.e.find('.notice_time').css('display', 'none');
                this.wandBtn.removeClass('pop_btn_small_disable').addClass('pop_btn_small');
                xGame.updateCd('countrynotice', 0);
            } else {
                this.e.find('.notice_wrap').css('display', 'none');
                this.e.find('.notice_wrap2').css('display', 'block');
                this.e.find('.notice_time').css('display', 'block');
                this.wandBtn.removeClass('pop_btn_small').addClass('pop_btn_small_disable');
                xGame.updateCd('countrynotice', res.cd);
            }

            if (parseInt(res.buffcd) > 0) {
                xGame.updateCd('countrycollegeskill', res.buffcd);
            } else {
                this.e.find('#countrycollege_effect_time').html(lang('countrycollege_skill_info'));
            }
        },

        studyBtnClick: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var initRender = function(res) {
                switch (res.status) {
                    case 1:
                        player.updateinfo({'gold':parseInt(res.gold)});

                        xGame.toast.show(lang('countrycollege_study_success'));
                        that.initLeftInfo(res.skill_buff.addition);
                        for (var i = res.skill_list.length - 1; i >= 0; i--) {
                            var skill = res.skill_list[i];
                            if (skill.skill_id == that.nowViewId) {
                                that.initRightInfo(skill);
                                that.skillslist[that.nowViewId-1] = skill;
                                break;
                            }
                        }
                        for (var i = res.skill_list.length - 1; i >= 0; i--) {
                            var skill = res.skill_list[i];
                            that.skillslist[skill.skill_id-1] = skill;
                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'war_college',
                'm': 'level_up',
                'skill_id': this.nowViewId
            },
            initRender);
            initRender = null;
        },

        buildList: function(res) {
            var that = this;
            var html = '';
            var n = 0;

            for (var i = 0; i < res.skill_list.length; i++) {
                var skill = res.skill_list[i];
                html += '<li id="skill' + skill.skill_id + '" class="skillitem ' + (n == 0 ? ' selected1': '') + ' '+(skill.open == 0 ? 'dis' : '' ) + '" skillkey="' + i + '">';
                html += '<span class="skillimg" style="background:url(static/img/pop/collegeskill'+skill.img_id + (skill.open == 0 ? '_1.png' : '.png') + ') center center no-repeat;' + '"></span>';
                html += '<p class="level">LV' + skill.skill_level + '</p>';

                if (skill.open == 0) {
                    html += '<div class="openmsg"><span class="msgspan">'+lang('countrycollege_msg', [skill.msg])+'</span></div>';
                    html += '<span class="lock"></span>';
                }

                html += '</li>';
                n++;
            }

            this.e.find('#countrycollege_list').html(html).width(this.skillslist.length * 132 + 'px');
            html = null;

            if (this.collegeskillScroller == null) {
                this.collegeskillScroller = new iScroll('countrycollege_scroller', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }
            this.collegeskillScroller.refresh();

            /* 绑定事件(必须在滑动门之后) */
            $('.skillitem').unbind('click').bind('click', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                var k = $(this).attr('skillkey');

                if(that.skillslist[k]) {
                    var data = that.skillslist[k];
                    var skill = that.skillslist[data.skill_id-1];
                    $('#skill' + that.nowViewId).removeClass('selected1');
                    $('#skill' + that.nowViewId).removeClass('selected2');

                    if (skill.open == 1) {
                        $('#skill' + data.skill_id).addClass('selected1');
                    } else {
                        $('#skill' + data.skill_id).addClass('selected2');
                    }

                    that.nowViewId = data.skill_id;
                    that.initRightInfo(skill);
                }
            });

            //绑定滑动门左右按钮
            this.e.find('.scroller_btn_left').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.collegeskillScroller.scrollToPage('prev', 0);

                return false;
            });

            this.e.find('.scroller_btn_right').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.collegeskillScroller.scrollToPage('next', 0);

                return false;
            });
        },

        initLeftInfo: function(addition) {
            this.e.find('#left_data_wrap').children('.wuli').children('span').text('+'+addition.wuli);
            this.e.find('#left_data_wrap').children('.zhili').children('span').text('+'+addition.zhili);
            this.e.find('#left_data_wrap').children('.tili').children('span').text('+'+addition.tili);
            this.e.find('#left_data_wrap').children('.shanbi').children('span').text('+'+addition.dodge);
            this.e.find('#left_data_wrap').children('.shipo').children('span').text('+'+addition.penetrate);
            this.e.find('#left_data_wrap').children('.mingzhong').children('span').text('+'+addition.mingzhong);

            this.e.find('#right_data_wrap').children('.wuligongji').children('span').text('+'+addition.attack);
            this.e.find('#right_data_wrap').children('.wulifangyu').children('span').text('+'+addition.defense);
            this.e.find('#right_data_wrap').children('.celuegongji').children('span').text('+'+addition.pattack);
            this.e.find('#right_data_wrap').children('.celuefangyu').children('span').text('+'+addition.pdefense);
            this.e.find('#right_data_wrap').children('.jingzhun').children('span').text('+'+addition.jingzhun);

        },

        initRightInfo: function(skill) {
            //技能名
            this.e.find('#skillname').text(skill.skill_name);
            //技能等级
            this.e.find('#skilllv').text('LV'+skill.skill_level);
            //当前属性
            this.e.find('#currattr').text(skill.current_name);

            //下一级属性
            if (skill.is_full_level) {
                this.e.find('#nextattr').text(lang('is_full_s'));
            } else {
                this.e.find('#nextattr').text(skill.next_name);
            }

            //研究消耗
            if (skill.is_cost == 0) {
                this.e.find('.ngold').removeClass('red').text(skill.cost);
            } else {
                this.e.find('.ngold').removeClass('red').addClass('red').text(skill.cost);    
            }

            //所需国家等级
            if (skill.level_is_open == 1) {
                this.e.find('#needlevel').removeClass('red').text(skill.msg);
            } else {
                if (skill.msg) {
                    this.e.find('#needlevel').removeClass('red').addClass('red').text(skill.msg);
                } else {
                    this.e.find('#needlevel').removeClass('red').text(lang('no'));
                }
            }

            //国家税金
            this.e.find('.cgold').text(skill.country_gold);

            //前置技能
            var html = '';
            if (skill.pre_skill_info) {
                for (var i = 0; i < skill.pre_skill_info.length; i++) {
                    var prevskill = skill.pre_skill_info[i];
                    html += '<li class="prevskillitem">';
                    html += '<span class="prevskillimg" style="background:url(static/img/pop/collegeskill'+prevskill.pre_skill_id + (prevskill.pre_skill == 1 ? '_1.png' : '.png') +') center center no-repeat; background-size:59px;' + '"></span>';
                    html += '<span class="level '+ (prevskill.pre_skill == 1 ? 'gray' : '') + '">' + prevskill.pre_skill_name + '</span>';
                    html += '</li>';
                }
                this.e.find('.prevskills').html(html);
            } else {
                this.e.find('.prevskills').html(lang('no'));
            }

            //研究按钮
            if (skill.is_active == 1) {
                this.e.find('#study_btn').removeClass('disabled').show();
            } else if (skill.is_active == 0) {
                this.e.find('#study_btn').removeClass('disabled').addClass('disabled').show();
            } else if (skill.is_active == 2) {
                this.e.find('#study_btn').hide();
            }

            this.e.find('#study_btn').attr({'skill_name': skill.skill_name, 'skill_lv':skill.skill_level, 'confirm_type':skill.confirm_type, 'need_country_gold':skill.need_country_gold, 'need_gold':skill.need_gold});

            //刷新列表项
            this.e.find('#countrycollege_list').find('#skill'+skill.skill_id).find('.level ').text('LV'+skill.skill_level);
        },

        // 使用将军令
        useWand: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    xGame.updateCd('countrycollegeskill', res.buffcd);
                    that.skillInfo.find('.wand').html('x' + res.general);
                    that.wandNum = res.general;
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'war_college',
                'm': 'use_buff'
            }, render);
        },

        unbindAll: function() {
            this.e.find('#countrycollege_hidebtn').unbind('touchend');
            this.e.find('#info_btn').unbind('touchend');
            this.e.find('#countrycollegepop_info').find('.mask').unbind('touchend');
            this.e.find('#info_hidebtn').unbind('touchend');
            this.e.find('#study_btn').unbind('touchend');
            this.e.find('.scroller_btn_left').unbind('touchend');
            this.e.find('.scroller_btn_right').unbind('touchend');
            $('.skillitem').unbind('click');
            this.skillInfo.undelegate('#countrycollege_use_wand.pop_btn_small', 'touchend');
        },

        hide: function() {
            this.isshow = false;
            this.unbindAll();
            this.wandBtn = null;
            this.skillInfo = null;
            this.wandNum = 0;
            this.needWandNum = 0;

            if (this.e) {
                this.e.remove();
                this.e = null;
            }

            if (this.collegeskillScroller) {
                this.collegeskillScroller.destroy();
                this.collegeskillScroller = null;
            }
            xGame.updateCd('countrynotice', 0);
            xGame.updateCd('countrycollegeskill', 0);
            this.skillslist = null;
            this.nowViewId = 1;
        }
    };
})();