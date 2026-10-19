/* 国家列表 */
(function() {
    countryPopClass = function() {
        this.e;
        this.isshow = false;
        this.allDom;
        this.hasCountryDom; //有国家的dom
        this.noCountryDom; //没有国家的dom
        this.leftDom; //左边国家列表的dom
        this.myScroller;
        this.scrollerDom;
        this.scid; //选中的国家id
        this.suid; //选中的用户id
        this.createDom; //建国的dom
        this.job; //当前的官职
        this.text; //当前的公告信息
        this.page; //当前页
        this.pages; //总页数
        this.pageBtns; //按钮dom
        this.hasCountry; //是否有国家，true false
        this.auditNum; //审核人数
        
        this.kickout;//是不是踢人操作,true就是踢人操作，false就不是
        this.isTimeout = [];
        this.contributionInfoBtn = null; // 贡献说明按钮
        this.contributionInfoDom = null; // 贡献说明
    };

    countryPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            if (that.e != undefined) {
                return false;
            }

            /*有国家，调用国家成员列表*/
            if (parseInt(player.info.country) > 0) {
                this.hasCountry = true;
                var initRender = function(res) {
                    if (res.status == 1) {
                        that.initFirst(res);
                    } else {
                        if (res.status == -1) {
                            xGame.toast.show(lang('get_country_member_fail'));
                        } else if (res.status == -2) {
                            xGame.toast.show(lang('get_country_placard_fail'));
                        } else if (res.status == -3) {
                            xGame.toast.show(lang('get_country_info_fail'));
                        } else if (res.status == -4) {
                            that.exceptionFunc();
                        } else if (res.status == 2) {
                            xGame.toast.show(lang('lv_not_enough'));
                        } else {
                            xGame.toast.show(lang('undefined_error'));
                        }
                        $('#worldloading').hide();
                    }
                };
                loadContents({
                    'c': 'country',
                    'm': 'get_member_list'
                },
                initRender);
                /*没有国家，调用国家列表*/
            } else {
                this.hasCountry = false;
                var initRender = function(res) {
                    if (res.status == 1) {
                        that.initFirst(res);
                    } else if (res.status == 2) {
                        xGame.toast.show(lang('lv_not_enough'));
                        $('#worldloading').hide();
                    } else {
                        xGame.toast.show(lang('get_country_list_fail'));
                        $('#worldloading').hide();
                    }
                };
                loadContents({
                    'c': 'country',
                    'm': 'get_rank'
                },
                initRender);
            }
            initRender = null;
        },

        initFirst: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('country');

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                countrypop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.allDom = that.e.find('#country_bg');
            that.noCountryDom = that.allDom.find('#no_country');
            that.hasCountryDom = that.allDom.find('#has_country');
            that.leftDom = that.e.find('#leftDom');
            that.scrollerDom = that.e.find('#country_list');
            that.createDom = that.e.find('#info_sub_pop2');
            that.pageBtns = that.e.find('#country_page_btns');
            that.contributionInfoBtn = that.e.find('#country_contribution_btn');
            that.contributionInfoDom = that.e.find('#country_contribution_info');

            if (res.info && parseInt(res.info.closeday) == 1) {
                that.contributionInfoBtn.show();
            } else {
                that.contributionInfoBtn.hide();
            }

            if (res.page != undefined) {
                that.page = res.page;
                that.pages = res.pages;
                that.pageBtns.find('#country_page_no').html(that.page);
            }
            if (res.audit != undefined) {
                that.auditNum = res.audit;
                that.showAuditNum();
            }
            //如果存在国家id
            if (res.country && res.country.id != undefined) {
                that.scid = res.country.id;
            }

            /*这里是第一层控制器，判断是否已经加入国家*/
            that.showByType(res);

            that.bindAll();

            that.e.show();

            $('#worldloading').hide();
            that.isshow = true;
        },

        /*根据type参数判断是现实有国家的界面还是显示没有国家的界面*/
        showByType: function(res) {
            if (parseInt(player.info.country) > 0) {
                this.allDom.attr('class', 'country_bg has');
                /* 这里需要根据job判断*/
                this.job = parseInt(res.info.job);
                this.showCurrentRank(res.info.rank);
                this.fillHasCountry(res);
                this.fillLeft(res.country);
            } else {
                this.job = 0;
                this.allDom.attr('class', 'country_bg no');
                this.fillNoCountry(1, res);
            }
        },

        showCurrentRank: function(num) {
            this.hasCountryDom.find('#current_rank').html(num);
        },

        /*type==1,国家列表，type == 2,申请列表*/
        fillNoCountry: function(type, res) {
            this.noCountryDom.attr('class', 't' + type);
            this.bulidLi(res);
            this.fillLeft(res.country);
            if (type == 2) {
                this.noCountryDom.find('#search_name').val('');
            } else {
                this.pageBtns.css('display', 'block');
            }
        },

        /*num == 1,君主；num == 2,丞相；num == 3,大将军；num >3 成员*/
        fillHasCountry: function(res) {
            var that = this;

            that.showBtnByJob();
            that.checkDemiseBtn(res.info.make_way);
            that.checkUsurpBtn(res.info.usurp);
            that.bulidLi(res, 1);
        },

        /*根据职位显示按钮，前提是有国家的情况下*/
        showBtnByJob: function() {
            this.hasCountryDom.attr('class', 'job' + this.job);
        },

        /*判断禅让按钮*/
        checkDemiseBtn: function(make_way) {
            if (make_way == undefined) {
                return false;
            }
            if (this.job == 1) {
                if (make_way == 1) {
                    this.hasCountryDom.find('#country_demise_btn').attr('class', 'pop_btn_small btn');
                } else if (make_way == 0) {
                    this.hasCountryDom.find('#country_demise_btn').attr('class', 'pop_btn_small_disable btn');
                }
            } else {
                return false;
            }
            //console.log(make_way);
        },

        /*判断升官和篡位按钮*/
        checkUsurpBtn: function(usurp) {
            if (this.job == 2) {
                if (usurp == 2) {
                    this.hasCountryDom.find('#country_usurp_btn').attr('class', 'pop_btn_small btn');
                } else {
                    this.hasCountryDom.find('#country_usurp_btn').attr('class', 'pop_btn_small_disable btn');
                }
            } else {
                if (usurp == 1) {
                    this.hasCountryDom.find('#country_lvup_btn').attr('class', 'pop_btn_small btn');
                } else if (usurp == 0) {
                    this.hasCountryDom.find('#country_lvup_btn').attr('class', 'pop_btn_small_disable btn');
                }
            }
        },

        /*没加入国家的不用传入后面的参数，加入的国家的必须传入后面参数，
		 *type == 1,成员列表，type== 2，审核列表；type == 3,国家列表
		*/
        bulidLi: function(res, type) {
            var html = '';
            /*判断该显示哪一个列表头*/
            if (type == undefined) {
                this.e.find('#scroller_dom').attr('class', 'type3');
            } else {
                this.e.find('#scroller_dom').attr('class', 'type' + type);
                this.e.find('#has_country_bottom').attr('class', 'bottom type' + type);
                this.e.find('#has_country_top').attr('class', 'top type' + type);
            }
            var list = res.list;
            if (list == '') {
                return;
            }
            for (var i = 0; i < list.length; i++) {
                if (parseInt(player.info.country) == 0) {
                    if (i == 0) {
                        html += '<li id="country_li' + list[i].id + '" cid="' + list[i].id + '" class="selected" >';
                        this.scid = list[i].id;
                    } else {
                        html += '<li id="country_li' + list[i].id + '" cid="' + list[i].id + '" >';
                    }
                    html += '<span></span>';
                    html += '<em class="t1">' + list[i].rank + '</em>';
                    html += '<em class="t2">' + list[i].name + '</em>';
                    html += '<em class="t3">' + list[i].level + '</em>';
                    html += '<em class="t4">' + list[i].member + '/' + 50 + '</em>';
                    html += '<em class="t5"><b class="under_line" uid="' + list[i].uid + '">' + list[i].nickname + '</b></em>';
                    html += '</li>';
                } else {
                    /*点击成员按钮后显示*/
                    if (type == 1) {
                        if (i == 0 && this.kickout != true) {
                            html += '<li id="country_li' + list[i].uid + '" uid="' + list[i].uid + '" job="' + list[i].job + '" class="selected" ' + (player.info.uid == list[i].uid ? 'style="color: purple;"': '') + '>';
                            this.suid = list[i].uid;
                            //判断踢出按钮
                            this.checkKickoutBtn(list[i].job);
                        } else if (list[i].uid == res.next_uid && this.kickout == true) {
                            html += '<li id="country_li' + list[i].uid + '" uid="' + list[i].uid + '" job="' + list[i].job + '" class="selected" ' + (player.info.uid == list[i].uid ? 'style="color: purple;"': '') + '>';
                            this.suid = list[i].uid;
                            //判断踢出按钮
                            this.checkKickoutBtn(list[i].job);
                        } else {
                            html += '<li id="country_li' + list[i].uid + '" uid="' + list[i].uid + '" job="' + list[i].job + '" ' + (player.info.uid == list[i].uid ? 'style="color: purple;"': '') + '>';
                        }
                        html += '<span></span>';
                        html += '<em class="y1"><b class="under_line" uid="' + list[i].uid + '">' + list[i].nickname + '</b></em>';
                        html += '<em class="y2">' + list[i].job_name + '</em>';
                        html += '<em class="y3">' + list[i].level + '</em>';
                        html += '<em class="y4">' + list[i].contribute + '</em>';
                        html += '<em class="y5">' + list[i].allcontribute + '</em>';
                        html += '<em class="y6">' + helper.timeToString(list[i].dateline, true) + lang('before') + '</em>';
                        html += '</li>';
                        /*点击审核按钮后显示*/
                    } else if (type == 2) {
                        html += '<li id="country_li' + list[i].uid + '" class="just_li no_box">';
                        html += '<em class="k1"><b class="under_line" uid="' + list[i].uid + '">' + list[i].nickname + '</b></em>';
                        html += '<em class="k2">' + list[i].level + '</em>';
                        html += '<em class="k3">';
                        html += '<button class="pop_btn_middle btn country_agree" uid="' + list[i].uid + '" type="1">' + lang('agree') + '</button>';
                        html += '<button class="pop_btn_middle btn country_refuse" uid="' + list[i].uid + '" type="2">' + lang('ignore') + '</button>';
                        html += '</em>';
                        html += '</li>';
                        /*点击国家列表按钮后显示*/
                    } else if (type == 3) {
                        if (i == 0) {
                            html += '<li id="country_li' + list[i].id + '" cid="' + list[i].id + '" class="selected no_box" ' + (player.info.country == list[i].id ? 'style="color: purple;"': '') + '>';
                            this.scid = list[i].id;
                        } else {
                            html += '<li id="country_li' + list[i].id + '" cid="' + list[i].id + '" class="no_box" ' + (player.info.country == list[i].id ? 'style="color: purple;"': '') + '>';
                        }
                        html += '<em class="t1">' + list[i].rank + '</em>';
                        html += '<em class="t2">' + list[i].name + '</em>';
                        html += '<em class="t3">' + list[i].level + '</em>';
                        html += '<em class="t4">' + list[i].member + '/' + 50 + '</em>';
                        html += '<em class="t5"><b class="under_line" uid="' + list[i].uid + '">' + list[i].nickname + '</b></em>';
                        html += '</li>';
                    }
                }
            }

            this.scrollerDom.html(html);
            if (type == 1 && this.job > 3) {//成员并且不能选择的
                this.e.find('#country_scroller').attr('class','scroller no_right');
            } else {
                this.e.find('#country_scroller').attr('class','scroller');
            }
            html = null;
            if (this.myScroller == undefined) { //不存在的时候
                if (list.length > 5) {
                    this.myScroller = new iScroll('country_scroller', {
                        bounce: false,
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: true
                    });
                    this.myScroller.refresh();
                }
            } else { //已经存在的时候
                if (list.length > 5) {
                    if (this.kickout != true) {
                        this.myScroller.scrollTo(0, 0);
                    }
                    this.myScroller.refresh();
                } else {
                    this.myScroller.destroy();
                    this.myScroller = null;
                }
            }
            list = null;
        },

        /*不能踢出比自己官衔高的人*/
        checkKickoutBtn: function(job) {
            if (this.job > 3) {
                return false;
            } else {
                if (this.job < job) { //官阶比自己低的可以踢,贡献度可以先不管
                    this.hasCountryDom.find('#country_kickout_btn').attr('class', 'pop_btn_small btn');
                } else {
                    this.hasCountryDom.find('#country_kickout_btn').attr('class', 'pop_btn_small_disable btn');
                }
            }
        },

        /*显示左边国家信息*/
        fillLeft: function(data) {
            if (data != undefined) {
                this.leftDom.find('#country_s_name').html(data.name);
                this.leftDom.find('#country_s_num').html(data.member + '/50');
                this.leftDom.find('#country_s_lv').html(data.level);
                this.leftDom.find('#country_s_exp').html(data.exp + '/' + data.levelupexp);
                /*刚创建国家时是没有公告信息的*/
                if (data.notice == undefined) {
                    data.notice = '';
                }
                this.leftDom.find('#country_s_pos').val(data.notice).attr('readonly', true);
                this.text = data.notice;
            } else {
                this.leftDom.find('#country_s_name').html('');
                this.leftDom.find('#country_s_num').html('');
                this.leftDom.find('#country_s_lv').html('');
                this.leftDom.find('#country_s_exp').html('');
                this.leftDom.find('#country_s_pos').val('').attr('readonly', true);
                this.leftDom.removeClass();
            }
            /*通过job判断是否显示发布*/
            if (data && data.id != undefined) {
                this.scid = data.id;
            }
            this.showEditorByJob(data);
        },

        showEditorByJob: function(data) {
            /*只有当显示国家成员并且为当前国家并且官职为1-3才显示修改按钮*/
            if  (this.job < 4 && this.job != 0 && this.scid == player.info.country && this.e.find('#scroller_dom').hasClass('type1')) {
                this.leftDom.attr('class', 'editor');
            } else {
                this.leftDom.removeClass();
            }
            
            //只有是君主并且不是修改状态才显示
            if  (this.job == 1 && !this.leftDom.hasClass('editor')) {
                this.leftDom.find('#country_sendmail_btn').css('display', 'block').attr('name', data.name);
            } else {
                this.leftDom.find('#country_sendmail_btn').css('display', 'none');
            }
        },

        bindAll: function() {
            var that = this;

            that.leftDom.undelegate('#country_s_pos', 'touchend').delegate('#country_s_pos', 'touchend',
            function() {
                if (xGame.checkLock(true) || !that.leftDom.hasClass('editor')) { //不设置属性
                    return false;
                }
                $(this).removeAttr('readonly');
            });

            /*发布按钮绑定*/
            that.leftDom.undelegate('#country_post_btn', 'touchend').delegate('#country_post_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.publishNotice(that.leftDom.find('#country_s_pos').val(), $(this));
            });
            
            /*发布按钮绑定*/
            that.leftDom.undelegate('#country_sendmail_btn', 'touchend').delegate('#country_sendmail_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var name = $(this).attr('name');
                xGame.showpop('mailbox', {'name' : name});
                name = null;
            });

            /*未加入国家的绑定*/
            that.noCountryDom.undelegate('#create_country', 'touchend').delegate('#create_country', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.createCountry();
            });

            that.noCountryDom.undelegate('#no_c_isapply', 'touchend').delegate('#no_c_isapply', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getApplyList();
            });

            that.noCountryDom.undelegate('#no_c_apply', 'touchend').delegate('#no_c_apply', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.applyFunc();
            });

            that.noCountryDom.undelegate('#no_c_cancle.pop_btn_middle', 'touchend').delegate('#no_c_cancle.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.cancleApply($(this));
            });

            that.noCountryDom.undelegate('#no_c_back', 'touchend').delegate('#no_c_back', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.page = 1;
                that.hasCountry = false;
                that.getCountryRank(that.page);
            });

            that.noCountryDom.undelegate('#search_btn', 'touchend').delegate('#search_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var str = that.noCountryDom.find('#search_name').val();
                if (str.length > 7) {
                    xGame.toast.show(lang('country_name_7_words'));
                    return false;
                }
                if (str == '') {
                    xGame.toast.show(lang('country_name_not_space'));
                    return false;
                }
                if (!helper.checkStr(str)) {
                    xGame.toast.show(lang('do_not_enter_illegal'));
                    return false;
                }
                that.searchFunc(str);
                str = null;
            });
            /*未加入国家的绑定*/

            that.scrollerDom.undelegate('li', 'click').delegate('li', 'click',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('just_li')) { //不设置属性
                    return false;
                }
                if ($(this).attr('cid') != undefined) {
                    if ($(this).attr('cid') == that.scid) {
                        return false;
                    }
                    that.scrollerDom.find('#country_li' + that.scid).removeClass('selected');
                    that.scid = $(this).attr('cid');
                    that.getCountryInfo();
                } else {
                    if ($(this).attr('uid') == that.suid) {
                        return false;
                    }
                    if ($(this).attr('job') != undefined) {
                        //判断踢出按钮
                        that.checkKickoutBtn(parseInt($(this).attr('job')));
                    }
                    that.scrollerDom.find('#country_li' + that.suid).removeClass('selected');
                    that.suid = $(this).attr('uid');
                }
                $(this).addClass('selected');
                //that.showCountryInfoById();
            });

            that.scrollerDom.undelegate('.under_line', 'click').delegate('.under_line', 'click',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('just_li')) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });

            that.scrollerDom.undelegate('.country_agree,.country_refuse', 'click').delegate('.country_agree,.country_refuse', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.auditFunc($(this));
            });

            /*建国弹窗*/
            that.createDom.undelegate('#hidebtn2,.cancle', 'touchend').delegate('#hidebtn2,.cancle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.createDom.css('display', 'none');
            });

            that.createDom.undelegate('.ok', 'touchend').delegate('.ok', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var str = that.createDom.find('input').val();
                if (str.length > 7) {
                    xGame.toast.show(lang('country_name_illegal'));
                    return false;
                }
                if (str == '') {
                    xGame.toast.show(lang('country_name_undefined'));
                    return false;
                }
                if (!helper.checkStr(str)) {
                    xGame.toast.show(lang('not_enter_illegal_str'));
                    return false;
                }
                that.createCountryFunc(str);
                str = null;
            });

            /*建国弹窗*/

            /*已加入国家的绑定*/
            that.hasCountryDom.undelegate('#country_m_btn,#country_back_btn', 'touchend').delegate('#country_m_btn,#country_back_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getMemberList();
            });

            that.hasCountryDom.undelegate('#country_check_btn', 'touchend').delegate('#country_check_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getAuditList();
            });

            that.hasCountryDom.undelegate('#country_lvup_btn.pop_btn_small,#country_usurp_btn.pop_btn_small', 'touchend').delegate('#country_lvup_btn.pop_btn_small,#country_usurp_btn.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (this.id == 'country_lvup_btn') {
                    that.checkUsurpFunc(lang('usurp_str1'));
                } else if (this.id == 'country_usurp_btn') {
                    that.checkUsurpFunc(lang('usurp_str2'));
                }
            });

            that.hasCountryDom.undelegate('#country_betray_btn', 'touchend').delegate('#country_betray_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.confirm.show(lang('treason'), lang('treason_ask'), 2,
                function() {
                    xGame.confirm.hide(function() {
                        that.betrayFunc();
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            });

            that.hasCountryDom.undelegate('#country_kickout_btn.pop_btn_small', 'touchend').delegate('#country_kickout_btn.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.kickOutInfoFunc();
            });

            that.hasCountryDom.undelegate('#country_demise_btn.pop_btn_small', 'touchend').delegate('#country_demise_btn.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.makeWayInfo();
            });

            that.hasCountryDom.undelegate('#country_rank_btn', 'touchend').delegate('#country_rank_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.hasCountry = true;
                that.getCountryRank();
            });

            that.hasCountryDom.undelegate('#country_map_btn', 'touchend').delegate('#country_map_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                countrypop.hide();
                $('#worldloading').show();
                that.isTimeout.push(setTimeout(function() {
                    xGame.loadWorld('country');
                },
                500));
            });
            
            that.hasCountryDom.undelegate('#country_cancle_all.pop_btn_small', 'touchend').delegate('#country_cancle_all.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.confirm.show(lang('notice'), lang('cancle_all_ask'), 2,
                function() {
                    xGame.confirm.hide(function() {
                        that.cancleAllFunc();
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            });

            /*页码按钮绑定*/
            that.pageBtns.undelegate('#country_page_btn1', 'touchend').delegate('#country_page_btn1', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) { //不设置属性
                    return false;
                }
                that.getCountryRank(1);
            });
            that.pageBtns.undelegate('#country_page_btn2', 'touchend').delegate('#country_page_btn2', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) { //不设置属性
                    return false;
                }
                that.getCountryRank(that.page - 1);
            });
            that.pageBtns.undelegate('#country_page_btn3', 'touchend').delegate('#country_page_btn3', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page > that.pages - 1) { //不设置属性
                    return false;
                }
                that.getCountryRank(that.page + 1);
            });
            that.pageBtns.undelegate('#country_page_btn4', 'touchend').delegate('#country_page_btn4', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == that.pages) { //不设置属性
                    return false;
                }
                that.getCountryRank(that.pages);
            });

            // 贡献说明按钮
            that.contributionInfoBtn.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.contributionInfoDom.show();
                e.preventDefault();
            });

            // 贡献说明关闭按钮和遮罩
            that.contributionInfoDom.delegate('.mask, .hidebtn', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.contributionInfoDom.hide();
                e.preventDefault();
            });
        },
        
        cancleAllFunc: function(){
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : 
                    xGame.toast.show(lang('you_have_no_country'));
                    break;
                case -2:
                    xGame.toast.show(lang('no_power'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    that.auditNum = 0;
                    that.showAuditNum();
                    if (that.myScroller != null) {
                        that.myScroller.destroy();
                        that.myScroller = null;
                    }
                    that.scrollerDom.find('li').remove();
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'country',
                'm': 'ignore_all'
            },
            Func);
            Func = null;
        },
        
        //显示审核人数
        showAuditNum: function() {
            if (this.auditNum > 0) {
                this.hasCountryDom.find('#country_check_btn').find('b').html(this.auditNum).css('display', 'block');
                this.hasCountryDom.find('#country_cancle_all').attr('class','btn pop_btn_small');
            } else {
                this.hasCountryDom.find('#country_check_btn').find('b').html('').css('display', 'none');
                this.hasCountryDom.find('#country_cancle_all').attr('class','btn pop_btn_small_disable');
            }
        },

        /*获取禅让信息*/
        makeWayInfo: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('treason_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_country_info_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    xGame.confirm.show(lang('abdication'), lang('abdication_ask', [res.info.job_name,res.info.nickname]), 2,
                    function() {
                        xGame.confirm.hide(function() {
                            that.makeWayFunc();
                        });
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'make_way_info'
            },
            Func);
            Func = null;
        },

        /*审核方法*/
        auditFunc: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 10 : that.exceptionFunc();
                    break;
                case - 9 : xGame.toast.show(lang('type_error'));
                    break;
                case - 8 : xGame.toast.show(lang('update_country_info_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('update_apply_status_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('update_member_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('add_member_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('member_not_exist'));
                    break;
                case - 3 : that.exceptionFunc();
                    break;
                case - 2 : that.auditNum = res.audit;
                    that.showAuditNum();
                    obj.parent().parent().remove();
                    if (that.scrollerDom.find('li').size() == 5) {
                        that.myScroller.destroy();
                        that.myScroller = null;
                    } else if (that.scrollerDom.find('li').size() > 5) {
                        that.myScroller.refresh();
                    }
                    xGame.toast.show(lang('hsa_country_or_cancle'));
                    break;
                case - 1 : xGame.toast.show(lang('no_power'));
                    break;
                case 1:
                    that.auditNum = res.audit;
                    that.showAuditNum();
                    obj.parent().parent().remove();
                    if (that.scrollerDom.find('li').size() == 5) {
                        that.myScroller.destroy();
                        that.myScroller = null;
                    } else if (that.scrollerDom.find('li').size() > 5) {
                        that.myScroller.refresh();
                    }
                    if (res.country != undefined && res.country.member != undefined) {
                        that.leftDom.find('#country_s_num').html(res.country.member + '/50');
                    }
                    break;
                case 2:
                    xGame.toast.show(lang('country_is_full'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 4:
                    xGame.toast.show(lang('in_countrybattle_freeze'));
                    break;
                case 5:
                    xGame.toast.show(lang('in_countryboss_freeze'));
                    break;
                case 6:
                    xGame.toast.show(lang('country_join_limit'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'audit',
                'uid': obj.attr('uid'),
                'type': obj.attr('type')
            },
            Func);
            Func = null;
        },

        /*判断升官或篡位*/
        checkUsurpFunc: function(str) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    if (res.type == 2) {
                        xGame.confirm.show(str, lang('country_atk_ask', [str, res.info.job_name, res.info.nickname]), 2,
                        function() {
                            xGame.confirm.hide(function() {
                                that.usurpFunc(str, res.info.job_name);
                            });
                        },
                        function() {
                            xGame.confirm.hide();
                        },
                        lang('duel'), lang('cower'));
                    } else if (res.type == 1) {
                        xGame.confirm.show(str, lang('country_lvup_ask', [res.info.job_name, str]), 2,
                        function() {
                            xGame.confirm.hide(function() {
                                that.usurpFunc();
                            });
                        },
                        function() {
                            xGame.confirm.hide();
                        },
                        str, lang('later'));
                    }
                    break;
                case - 1 : xGame.toast.show(lang('contribute_not_enough_for_%str',[str]));
                    break;
                case - 2 : that.exceptionFunc();
                    break;
                case - 3 : that.exceptionFunc();
                    break;
                case 2:
                    xGame.toast.show(lang('you_are_king'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'get_usurp_info'
            },
            Func);
            Func = null;
        },

        /*升官以及篡位方法*/
        usurpFunc: function(str, job) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 7 : that.exceptionFunc();
                    break;
                case - 6 : xGame.toast.show(lang('add_battle_report_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('get_mate_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('get_member_info_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('challenge_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('contribute_not_enough'));
                    break;
                case - 1 : xGame.toast.show(lang('get_member_info_fail'));
                    break;
                case 1:
                    that.job = res.info.job;
                    if (res.info.job == 1) {
                        player.info.country_job = 1;
                    }
                    that.showCurrentRank(res.info.rank);
                    if (str != undefined) { //战斗升官
                        miscpop.enterBattleFromCountry(res, res.info.uid, str, job);
                    } else { //直接升官
                        countrypop.fillHasCountry(res);
                    }

                    break;
                case 2:
                    xGame.toast.show(lang('you_are_king'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'usurp'
            },
            Func);
            Func = null;
        },

        /*叛国方法*/
        betrayFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('update_player_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('update_country_info_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_country_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('update_member_info_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    if (xGame.nowWorld == 'country') {
                        xGame.loadWorld('city')
                    }
                    player.info.country = 0; //表示没有国家
                    player.info.country_job = 0;
                    that.job = 0;
                    //特殊处理，先解锁
                    xGame.unlock();
                    that.page = 1;
                    that.hasCountry = false;
                    that.getCountryRank(that.page,
                    function() {
                        that.allDom.attr('class', 'country_bg no');
                    });
                    break;
                case 2:
                    lang('lv_not_enough');
                    break;
                case 4:
                    xGame.toast.show(lang('in_countrybattle_freeze'));
                    break;
                case 5:
                    xGame.toast.show(lang('in_countryboss_freeze'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'betray'
            },
            Func);
            Func = null;
        },

        /*踢出确认方法*/
        kickOutInfoFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 6 : that.exceptionFunc();
                    break;
                case - 5 : xGame.toast.show(lang('get_job_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('his_contribute_more_higher'));
                    break;
                case - 3 : xGame.toast.show(lang('his_job_lv_more_higher'));
                    break;
                case - 2 : xGame.toast.show(lang('player_info_undefined'));
                    break;
                case - 1 : xGame.toast.show(lang('no_power'));
                    break;
                case 1:
                    xGame.confirm.show(lang('kick_out'), lang('kick_out_ask', [res.info.nickname]), 2,
                    function() {
                        xGame.confirm.hide(function() {
                            that.kickOutFunc();
                        });
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('kick_out'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'kick_out_info',
                'uid': that.suid
            },
            Func);
            Func = null;
        },

        /*踢出方法*/
        kickOutFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 10 : that.exceptionFunc();
                    break;
                case - 9 : xGame.toast.show(lang('get_member_list_fail'));
                    break;
                case - 8 : xGame.toast.show(lang('update_country_id_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('update_country_info_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('get_country_info_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('update_member_info_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('his_contribute_more_higher'));
                    break;
                case - 3 : xGame.toast.show(lang('his_job_lv_more_higher'));
                    break;
                case - 2 : xGame.toast.show(lang('player_info_undefined'));
                    break;
                case - 1 : xGame.toast.show(lang('no_power'));
                    break;
                case 1:
                    that.leftDom.find('#country_s_num').html(res.country.member + '/50');

                    that.job = parseInt(res.info.job);
                    that.showCurrentRank(res.info.rank);
                    /*2013-8-3 加入是否是踢人的判断*/
                    that.kickout = true;
                    that.fillHasCountry(res);
                    that.kickout = false;
                    /*不做成连续踢人是因为有可能成员顺序会变，无法确定哪个是下一个*/
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 4:
                    xGame.toast.show(lang('in_countrybattle_freeze'));
                    break;
                case 5:
                    xGame.toast.show(lang('in_countryboss_freeze'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'kick_out',
                'uid': that.suid
            },
            Func);
            Func = null;
        },

        /*禅让方法*/
        makeWayFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('get_country_member_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('update_country_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_member_info_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    that.job = parseInt(res.info.job);
                    that.showCurrentRank(res.info.rank);
                    that.fillHasCountry(res);

                    that.hasCountryDom.find('#country_usurp_btn').attr('class', 'pop_btn_small btn');
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'make_way'
            },
            Func);
            Func = null;
        },

        /*已申请列表*/
        getApplyList: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('get_placard_fail'));
                    break;
                case - 1 : that.exceptionFunc(res.country);
                    break;
                case 1:
                    if (res.list.length == 0) {
                        xGame.toast.show(lang('you_have_not_apply_country_yet'));
                    } else {
                        that.pageBtns.css('display', 'none');
                        that.fillNoCountry(2, res);
                        if (res.list.length != 0) {
                            that.noCountryDom.find('#no_c_cancle').css('display', 'block');
                        } else {
                            that.noCountryDom.find('#no_c_cancle').css('display', 'none');
                        }
                    }

                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'get_apply_list'
            },
            Func);
            Func = null;
        },

        /*发布公告*/
        publishNotice: function(str, obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 4 : that.exceptionFunc();
                    break;
                case - 3 : xGame.toast.show(lang('update_placard_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('power_too_low'));
                    break;
                case - 1 : xGame.toast.show(lang('msg_more_than_70'));
                    break;
                case 1:
                    xGame.toast.show(lang('modify_success'));
                    that.leftDom.find('#country_s_pos').attr('readonly', 'true');
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.toast.show(lang('have_not_change'));
                    break;
                case -5:
                    xGame.toast.show(lang('banned_words'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'publish_notice',
                'content': str,
                'country': player.info.country
            },
            Func);
            Func = null;
        },

        /*申请加入国家*/
        applyFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('apply_country_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('you_had_apply_this_country'));
                    break;
                case - 2 : xGame.toast.show(lang('country_not_exist'));
                    break;
                case - 1 : that.exceptionFunc(res.country);
                    break;
                case 1:
                    xGame.toast.show(lang('apply_success'));
                    break;
                case 2:
                    xGame.toast.show(lang('country_is_full'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'apply',
                'id': that.scid,
                'page': that.page
            },
            Func);
            Func = null;
        },

        /*国家成员排名*/
        getMemberList: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('get_country_member_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_placard_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_country_info_fail'));
                    break;
                case - 1 : that.exceptionFunc();
                    break;
                case 1:
                    that.auditNum = res.audit;
                    that.showAuditNum();
                    that.pageBtns.css('display', 'none');
                    that.bulidLi(res, 1);
                    that.fillHasCountry(res);
                    that.fillLeft(res.country);
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'country',
                'm': 'get_member_list'
            },
            Func);
            Func = null;
        },

        /*国家排名*/
        getCountryRank: function(page, callback) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    if (that.hasCountry) { //有国家时
                        that.bulidLi(res, 3);
                    } else { //没有国家时
                        that.fillNoCountry(1, res);
                    }
                    that.pageBtns.css('display', 'block');
                    if (page != undefined) {
                        that.page = page;
                    } else { //这个是点击“我的国家按钮之后的”，需要特殊处理下
                        that.page = res.page;
                        that.scid = player.info.country;
                        that.scrollerDom.find('.selected').removeClass('selected');
                        that.scrollerDom.find('#country_li' + player.info.country).addClass('selected');
                    }
                    that.fillLeft(res.country);
                    that.pages = res.pages;
                    that.pageBtns.find('#country_page_no').html(that.page);
                    if (callback != undefined) {
                        callback();
                    }

                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            /*TODO卡的问题*/
            if (page != undefined) {
                loadContents({
                    'c': 'country',
                    'm': 'get_rank',
                    'page': page
                },
                Func);
            } else {
                loadContents({
                    'c': 'country',
                    'm': 'get_rank'
                },
                Func);
            }
            Func = null;

        },

        /*审核列表*/
        getAuditList: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 2 : that.exceptionFunc();
                    break;
                case - 1 : xGame.toast.show(lang('no_power'));
                    break;
                case 1:
                    if (res.list.length == 0) {
                        xGame.toast.show(lang('no_one_apply'));
                        that.auditNum = 0;
                    } else {
                        that.bulidLi(res, 2);
                        that.auditNum = res.list.length;
                    }
                    that.showAuditNum();
                    break;
                case 2:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'get_audit_list'
            },
            Func);
            Func = null;
        },

        createCountry: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('name_can_not_be_space'));
                    break;
                case - 2 : xGame.toast.show(lang('name_more_than_7'));
                    break;
                case - 3 : xGame.toast.show(lang('country_name_is_exist'));
                    break;
                case - 4 : xGame.toast.show(lang('create_country_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('add_or_update_member_info_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('update_member_info_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case 4:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.toast.show(lang('gold_not_enough'));
                    break;
                case 2:
                    that.exceptionFunc(res.country);
                    break;
                case 1:
                    that.createDom.find('input').val('');
                    that.createDom.find('span').html(res.price + lang('gold'));
                    that.createDom.css('display', 'block');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'create'
            },
            Func);
            Func = null;
        },

        /*建立国家*/
        createCountryFunc: function(name) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('name_can_not_be_space'));
                    break;
                case - 2 : xGame.toast.show(lang('name_more_than_7'));
                    break;
                case - 3 : xGame.toast.show(lang('country_name_is_exist'));
                    break;
                case - 4 : xGame.toast.show(lang('create_country_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('add_or_update_member_info_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('update_member_info_fail'));
                    break;
                case - 7 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case 4:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.toast.show(lang('gold_not_enough'));
                    break;
                case 2:
                    that.exceptionFunc(res.country);
                    break;
                case 1:
                    that.pageBtns.css('display', 'none');
                    player.updateinfo({
                        'gold': parseInt(res.gold)
                    });
                    player.info.country = res.country.id;
                    player.info.country_job = 1;
                    that.showByType(res);
                    that.createDom.css('display', 'none');
                    xGame.toast.show(lang('create_country_msg'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'create',
                'name': name,
                'confirm': '1'
            },
            Func);
            Func = null;
        },

        /*取消申请*/
        cancleApply: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('apply_not_exist'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 2:
                    that.exceptionFunc(res.country);
                    break;
                case 1:
                    var dom1 = that.scrollerDom.find('#country_li' + that.scid);
                    var num = that.scrollerDom.find('li').size();
                    if (num > 0) {
                        var dom2 = dom1.next().length == 0 ? dom1.prev() : dom1.next();

                        if (dom2.length != 0) {
                            dom2.addClass('selected');
                            that.scid = dom2.attr('cid');
                            //特殊处理
                            xGame.unlock();
                            that.getCountryInfo();
                            console.log
                        } else {
                            that.fillLeft(res.country);
                        }

                        if (num == 1) {
                            obj.css('display', 'none');
                        }
                    }
                    dom1.remove();
                    dom1 = dom2 = num = null;
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'del_apply',
                'id': that.scid
            },
            Func);
            Func = null;
        },

        /*搜索方法*/
        searchFunc: function(name) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('get_placard_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_country_rank_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('country_not_exist'));
                    break;
                case - 1 : xGame.toast.show(lang('name_illegal'));
                    break;
                case 1:
                    that.fillNoCountry(1, res);
                    that.fillLeft(res.country);
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'search',
                'name': name
            },
            Func);
            Func = null;
        },

        /*获取单个国家信息*/
        getCountryInfo: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('get_country_placard_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('get_country_info_fail'));
                    break;
                case 1:
                    that.fillLeft(res.country);
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'country',
                'm': 'get_country_info',
                'id': that.scid
            },
            Func);
            Func = null;
        },

        /* 晋升动画 */
        createResult: function(type, baoji) {

            var html = $('<div id="country_result_wrap"></div>');

            var movieclip;
            var that = this;

            var cfg = {
                "x": 176,
                "y": -50
            };
            var callback = function() {
                that.isTimeout.push(setTimeout(function() {
                    html.fadeOut();
                },
                1000));

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
            html.append('<span class="type' + type + '"></span>');
            //开始
            this.e.find('#countrypop_wrap').append(html);
            movieclip.play();
            movieclip = null;
        },

        /*异常处理：没有参数表示被踢出国家了，有参数表示加入国家了*/
        exceptionFunc: function(id) {
            if (id == undefined) {
                player.info.country = 0;
                player.info.country_job = 0;
                xGame.toast.show(lang('you_are_kicked_out'));
                if (this.isshow) {
                    this.hide();
                }
                xGame.loadWorld('city');
            } else {
                countrypop.hide();
                xGame.toast.show(lang('you_apply_had_pass'));
                player.info.country = id;
                xGame.showpop('country');
            }
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.leftDom) {
                this.leftDom.undelegate('#country_s_pos', 'touchend');
                this.leftDom.undelegate('#country_post_btn', 'touchend');
                this.leftDom.undelegate('#country_sendmail_btn', 'touchend');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.noCountryDom) {
                this.noCountryDom.undelegate('#create_country', 'touchend');
                this.noCountryDom.undelegate('#no_c_isapply', 'touchend');
                this.noCountryDom.undelegate('#no_c_apply', 'touchend');
                this.noCountryDom.undelegate('#no_c_cancle.pop_btn_middle', 'touchend');
                this.noCountryDom.undelegate('#no_c_back', 'touchend');
                this.noCountryDom.undelegate('#search_btn', 'touchend');
                this.noCountryDom.remove();
                this.noCountryDom = null;
            }
            if (this.scrollerDom) {
                this.scrollerDom.undelegate('li', 'click');
                this.scrollerDom.undelegate('.under_line', 'click');
                this.scrollerDom.undelegate('.country_agree,.country_refuse', 'click');
                this.scrollerDom.remove();
                this.scrollerDom = null;
            }
            if (this.createDom) {
                this.createDom.undelegate('#hidebtn2,.cancle', 'touchend');
                this.createDom.undelegate('.ok', 'touchend');
                this.createDom.remove();
                this.createDom = null;
            }
            if (this.hasCountryDom) {
                this.hasCountryDom.undelegate('#country_m_btn,#country_back_btn', 'touchend');
                this.hasCountryDom.undelegate('#country_check_btn', 'touchend');
                this.hasCountryDom.undelegate('#country_lvup_btn.pop_btn_small,#country_usurp_btn.pop_btn_small', 'touchend');
                this.hasCountryDom.undelegate('#country_betray_btn', 'touchend');
                this.hasCountryDom.undelegate('#country_kickout_btn.pop_btn_small', 'touchend');
                this.hasCountryDom.undelegate('#country_demise_btn.pop_btn_small', 'touchend');
                this.hasCountryDom.undelegate('#country_rank_btn', 'touchend');
                this.hasCountryDom.undelegate('#country_map_btn', 'touchend');
                this.hasCountryDom.undelegate('#country_cancle_all.pop_btn_small', 'touchend');
                this.hasCountryDom.remove();
                this.hasCountryDom = null;
            }
            if (this.pageBtns) {
                this.pageBtns.undelegate('#country_page_btn1', 'touchend');
                this.pageBtns.undelegate('#country_page_btn2', 'touchend');
                this.pageBtns.undelegate('#country_page_btn3', 'touchend');
                this.pageBtns.undelegate('#country_page_btn4', 'touchend');
                this.pageBtns.remove();
                this.pageBtns = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;

            if (this.myScroller != undefined) {
                this.myScroller.destroy();
                this.myScroller = null;
            }

            if (this.allDom) {
                this.allDom.remove();
                this.allDom = null;
            }
            this.scid = this.auditNum = null;
            this.suid = this.job = this.text = this.page = this.hasCountry = this.pages = null;
            this.kickout = null;
            this.contributionInfoBtn = null;
            this.contributionInfoDom = null;
            this.unbindAll();
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
        }
    };
})();