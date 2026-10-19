/*信箱*/
(function() {
    mailboxPopClass = function() {
        this.e;
        this.isshow = false;

        this.topDom;
        this.bottomDom;

        this.stype; //当前的tab类型 1，公告 ，2 收件箱 3，发件箱 4 给GM发信 5 国家发件箱
        this.readMid; //当前读取的信件ID
        this.page;
        this.perPage;

        this.listScroller;
        this.listDom;

        this.mailInfoScroller;
        this.mailInfoDom;

        this.mailNoticeScroller;
        this.mailNoticeDom;

        this.starDom;
        this.star = 1;
        this.grade = 0;
        this.isTimeout = [];
    };
    mailboxPopClass.prototype = {
        init: function(mailToByName) {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
            that.e = xGame.loadPopTmpl('mailbox');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                mailboxpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });

            that.initList();
            
            if (mailToByName != undefined) {
                //that.sendFunc();
                if (mailToByName.name != undefined) {
                    that.e.find('#mb_countrysend_tbtn').trigger('touchend');
                    that.bottomDom.find('#mb_write_all').find('#mb_receiver_input').val(mailToByName.name);
                } else {
                    that.e.find('#mb_send_tbtn').trigger('touchend');
                    that.bottomDom.find('#mb_write_all').find('#mb_receiver_input').val(mailToByName);
                }
            } else {
                that.fillSystem();
            }

            if (player.info.activities == 1) {
                that.e.find('#mb_activities_tbtn').append('<b class="new"></b>');
            } else {
                that.e.find('#mb_activities_tbtn').css('display', 'none');
            }
            
            if (player.info.country_job == 1) {//是君主
               that.e.find('#mb_countrysend_tbtn').css('display', 'inline-block');
            }

            /* 最后 展示弹窗 */
            that.e.show();
            $('#worldloading').hide();
            //this.isshow = true;
        },

        checkHeightScroller: function() {
            var dom = $('#mb_system_list');
            if (dom.height() > 330) {
                if (this.mailNoticeScroller == null) {
                    this.mailNoticeScroller = new iScroll('mailboxpop_notice_scroller', {
                        hScrollbar: false,
                        vScrollbar: false
                    });
                }
                $('#mb_system_list').height(dom.height());
                this.mailNoticeScroller.refresh();
            } else {
                if (this.mailNoticeScroller != null) {
                    this.mailNoticeScroller.destroy();
                    this.mailNoticeScroller = null;
                }
            }
            dom = null;
        },

        initList: function(data) {
            var that = this;

            that.topDom = that.e.find('#mb_top');
            that.bottomDom = that.e.find('#mb_content');

            that.listDom = that.bottomDom.find('#mailpoppop_list');

            that.starDom = that.e.find('#grade_detail');

            that.listScroller = null;
            that.mailInfoScroller = null;
            that.mailNoticeScroller = null;

            that.stype = 1;
            that.readMid = 0;

            that.bindAll();
            that.createNewByInfo();
        },

        createNewByInfo: function() {
            var that = this;
            if (player.info.newmail == 1) {
                that.topDom.find('#mb_receive_tbtn').append('<b class="new"></b>').unbind('touchend').one('touchend',
                function() {
                    $(this).find('b').remove();
                });
            } else if (player.info.newmail == 2) {
                that.topDom.find('#mb_gm_tbtn').append('<b class="new"></b>').unbind('touchend').one('touchend',
                function() {
                    $(this).find('b').remove();
                });
            } else if (player.info.newmail == 3) {
                that.topDom.find('#mb_receive_tbtn').append('<b class="new"></b>').unbind('touchend').one('touchend',
                function() {
                    $(this).find('b').remove();
                });
                that.topDom.find('#mb_gm_tbtn').append('<b class="new"></b>').unbind('touchend').one('touchend',
                function() {
                    $(this).find('b').remove();
                });
            }
        },

        bindAll: function() {
            var that = this;
            /*公告按钮*/
            that.topDom.undelegate('#mb_system_tbtn', 'touchend').delegate('#mb_system_tbtn', 'touchend',
            function() {
                if (xGame.islock || that.stype == 1) {
                    return;
                }
                that.stype = 1;
                that.readMid = 0;
                that.topDom.attr('class', 'mb_system_tab');
                that.bottomDom.attr('class', 'mb_system');
                that.fillSystem();
                that.checkHeightScroller();
            });
            /*收件箱按钮*/
            that.topDom.undelegate('#mb_receive_tbtn', 'touchend').delegate('#mb_receive_tbtn', 'touchend',
            function() {
                if (xGame.islock || that.stype == 2) {
                    return;
                }
                that.page = 1;
                that.perPage = 10;
                that.stype = 2;
                that.readMid = 0;
                that.topDom.attr('class', 'mb_receive_tab');
                that.bottomDom.attr('class', 'mb_common');
                that.hideWriteDiv();
                that.bottomDom.find('#mb_creat_btn').html(lang('new_mail'));
                that.fillMessage();
            });
            /*发件箱按钮*/
            that.topDom.undelegate('#mb_send_tbtn', 'touchend').delegate('#mb_send_tbtn', 'touchend',
            function() {
                if (xGame.islock || that.stype == 3) {
                    return;
                }
                that.sendFunc();
            });
            /*GM按钮*/
            that.topDom.undelegate('#mb_gm_tbtn', 'touchend').delegate('#mb_gm_tbtn', 'touchend',
            function() {
                if (xGame.islock || that.stype == 4) {
                    return;
                }
                that.page = 1;
                that.perPage = 10;
                that.stype = 4;
                that.readMid = 0;
                that.topDom.attr('class', 'mb_gm_tab');
                that.bottomDom.attr('class', 'mb_common');
                that.hideWriteDiv();
                that.bottomDom.find('#mb_creat_btn').html(lang('leave_msg'));
                that.fillMessage();
                that.createFunc();
            });
            /* 活动预告 */
            that.topDom.undelegate('#mb_activities_tbtn', 'touchend').delegate('#mb_activities_tbtn', 'touchend',
            function() {
                that.hide();
                xGame.showpop('activities');
            });
            
            /*国家发件箱按钮*/
            that.topDom.undelegate('#mb_countrysend_tbtn', 'touchend').delegate('#mb_countrysend_tbtn', 'touchend',
            function() {
                if (xGame.islock || that.stype == 5) {
                    return;
                }
                that.countrySendFunc();
            });

            /*左侧信件行*/
            that.bottomDom.find('#mailpoppop_list').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.hideWriteDiv();
                if ($(this).attr('mbid') == that.readMid) {
                    return;
                }
                that.readById($(this).attr('mbid'));

            });

            that.bottomDom.undelegate('#mb_creat_btn', 'touchend').delegate('#mb_creat_btn', 'touchend',
            function() {
                if (xGame.islock || that.bottomDom.find('#mb_mail_content').hasClass('write')) {
                    return;
                }
                that.createFunc();
            });

            that.bottomDom.undelegate('#mb_send_btn', 'touchend').delegate('#mb_send_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.sendMessage($(this).attr('sendtype'));
            });

            that.bottomDom.undelegate('#mb_reset_btn', 'touchend').delegate('#mb_reset_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.resetWriteFunc();
            });

            that.bottomDom.undelegate('#mb_delete_btn.pop_btn_middle', 'touchend').delegate('#mb_delete_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.deleteFunc(that.readMid);
            });

            that.bottomDom.undelegate('#mb_delete_all_btn.pop_btn_middle', 'touchend').delegate('#mb_delete_all_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.confirm.show('', lang('all_delete_ask'), 2,
                function() {
                    xGame.confirm.hide();
                    that.deleteFunc();
                },
                function() {
                    xGame.confirm.hide();
                },lang('ok'),lang('close'));
            });

            that.bottomDom.undelegate('#mb_reply_btn.pop_btn_middle', 'touchend').delegate('#mb_reply_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('type') == 'country') {
                    that.bottomDom.find('#mb_write_all').find('#mb_nickname').html(lang('mailboxpop_receiver_country'));
                    that.bottomDom.find('#mb_send_btn').attr('sendtype', '5');//强制设置为国家信件发件类型
                } else {
                    that.bottomDom.find('#mb_write_all').find('#mb_nickname').html(lang('receiver'));
                    that.bottomDom.find('#mb_send_btn').attr('sendtype', that.stype);
                }
                that.resetWriteFunc();

                that.bottomDom.find('#mb_write_all').find('#mb_receiver_input').val(that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').val());

                that.bottomDom.find('#mb_mail_content').attr('class', 'write');
                that.bottomDom.find('#mb_write_all').css('display', 'block');
            });

            that.bottomDom.undelegate('#mb_fetch_btn.pop_btn_middle', 'touchend').delegate('#mb_fetch_btn.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.fetchRewardFromMail(that.readMid);
                that.hideByObj($(this));
            });

            that.bottomDom.undelegate('#mb_nickname_input', 'touchend').delegate('#mb_nickname_input', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).attr('uid') == undefined || $(this).attr('uid') == player.info.uid) { //不设置属性
                    return false;
                }
                xGame.showpop('info', parseInt($(this).attr('uid')));
            });

            //打分按钮
            that.bottomDom.undelegate('#mb_gm_grade', 'touchend').delegate('#mb_gm_grade', 'touchend', function() {
                if (that.grade > 0) {
                    xGame.toast.show(lang('mailboxpop_has_grade'));
                } else {
                    that.setStar(1);
                    that.starDom.show();
                }
            });

            //关闭打分按钮
            that.starDom.undelegate('#grade_detail_hide', 'touchend').delegate('#grade_detail_hide', 'touchend', function() {
                that.e.find('#grade_detail').hide();
            });

            //点击评分的星星
            that.starDom.find('#grade_list').undelegate('.grade', 'touchstart').delegate('.grade', 'touchstart', function() {
                var star = $(this).attr('value');
                that.setStar(star);
            });

            //提交评分
            that.starDom.undelegate('#grade_submit_btn', 'touchend').delegate('#grade_submit_btn', 'touchend', function() {
                that.submitGrade(that.readMid);
            });
        },

        submitGrade: function(id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                if (res.status == 1) {
                    that.grade = that.star;
                    that.e.find('#grade_detail').hide();
                    var html = '';
                    for (var i = 1; i <= that.star; i++) {
                        html += '<em></em>';
                    }
                    that.bottomDom.find('#mb_read_all').find('#mb_grade_info').html(html);
                    that.bottomDom.find('#mb_read_all').find('#mb_grade_info').show();
                    that.bottomDom.find('#mb_read_all').find('#mb_gm_grade').hide();
                    xGame.toast.show(lang('mailboxpop_grade_success'));
                } else if (res.status == -1) {
                    xGame.toast.show(lang('mailboxpop_grade_star_error'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('mailboxpop_grade_info_lose'));
                } else if (res.status == -3) {
                    xGame.toast.show(lang('mailboxpop_grade_not_reply'));
                } else if (res.status == -4) {
                    xGame.toast.show(lang('mailboxpop_has_grade'));
                } else {
                    xGame.toast.show(lang('mailboxpop_grade_lose'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'gmmail',
                'm': 'grade',
                'id': id,
                'grade': that.star
            },
            Func);
            Func = null;
        },

        setStar: function(star) {
            var that = this;
            var time = 0;
            that.star = star;
            for (var i = 1; i <= 5; i++) {
                if(i <= star) {
                    if(!that.starDom.find('#grade_list').find('.grade' + i).hasClass('show')) {
                        (function(starid){
                            that.isTimeout.push(setTimeout(function() {
                                that.starDom.find('#grade_list').find('.grade' + starid).addClass('show');
                            }, time));
                            time += 80;
                        })(i);
                    }
                } else {
                    that.starDom.find('#grade_list').find('.grade' + i).removeClass('show');
                }
            }
        },

        sendFunc: function() {
            var that = this;
            that.page = 1;
            that.perPage = 10;
            that.stype = 3;
            that.readMid = 0;
            that.topDom.attr('class', 'mb_send_tab');
            that.bottomDom.attr('class', 'mb_common');
            that.hideWriteDiv();
            that.bottomDom.find('#mb_creat_btn').html(lang('new_mail'));
            that.fillMessage();
            that.createFunc();
        },
        
        countrySendFunc: function() {
            var that = this;
            that.page = 1;
            that.perPage = 10;
            that.stype = 5;
            that.readMid = 0;
            that.topDom.attr('class', 'mb_countrysend_tab');
            that.bottomDom.attr('class', 'mb_common');
            that.hideWriteDiv();
            that.bottomDom.find('#mb_creat_btn').html(lang('new_mail'));
            that.fillMessage();
            that.createFunc();
        },

        createFunc: function() {
            var that = this;
            if (that.stype == 5) {
                that.bottomDom.find('#mb_write_all').find('#mb_nickname').html(lang('mailboxpop_receiver_country'));
                that.bottomDom.find('#mb_receiver_input').attr('placeholder', lang('mailboxpop_enter_country'));
            } else {
                that.bottomDom.find('#mb_write_all').find('#mb_nickname').html(lang('receiver'));
                that.bottomDom.find('#mb_receiver_input').attr('placeholder', lang('mailboxpop_enter_nickname'));
            }
            
            that.bottomDom.find('#mb_send_btn').attr('sendtype', that.stype);
            that.resetWriteFunc();
            that.bottomDom.find('#mb_mail_content').attr('class', 'write');
            that.bottomDom.find('#mb_write_all').css('display', 'block');
            if (that.stype == 4) {
                that.bottomDom.find('#mb_receiver_input').val('GM').attr('readonly', 'true');
            } else {
                that.bottomDom.find('#mb_receiver_input').removeAttr('readonly');
            }
        },

        hideWriteDiv: function() {
            var that = this;
            if (!that.bottomDom.find('#mb_mail_content').hasClass('read')) {
                that.bottomDom.find('#mb_mail_content').attr('class', 'read');
            }
        },

        fillSystem: function() {
            var that = this;
            if (xGame.xsGet(xGame.server_prefix + 'game_notice') != undefined && xGame.xsGet(xGame.server_prefix + 'notice_id') == player.info.notice_id) {
                $('#mb_system_list').html(xGame.xsGet(xGame.server_prefix + 'game_notice'));
                that.checkHeightScroller();
            } else {
                if (xGame.checkLock()) {
                    return false;
                }

                var html = '';
                var Func = function(res) {
                    if (res.status == 1) {
                        for (var i = 0; i < res.list.length; i++) {
                            html += '<li>' + res.list[i].info + '</li>';
                        }
                        $('#mb_system_list').html(html);
                        that.checkHeightScroller();
                        xGame.xsSet(xGame.server_prefix + 'game_notice', html);
                        xGame.xsSet(xGame.server_prefix + 'notice_id', player.info.notice_id);
                        that.e.find('#mb_system_tbtn').append('<b class="new"></b>');
                    }
                    xGame.unlock();
                };
                loadContents({
                    'c': 'message',
                    'm': 'get_notice'
                },
                Func);
                Func = null;
            }
        },

        fillMessage: function(page) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status >= 0) {
                    that.bottomDom.find('#mb_mail_author').html('');
                    that.bottomDom.find('#mb_mail_dateline').html('');
                
                    if (res.status == 1) {
                        if (page != undefined) {
                            that.page = page;
                            that.buildList(res.list, true);
                        } else {
                            //console.log('现在是第一页');
                            that.buildList(res.list);
                        }
                        if (that.stype == 2) {
                            that.showRight(res.info);
                        }

                    } else if (res.status == 0) {
                        if (page != undefined) {
                            that.page = page;
                            that.buildList(res.list, true);
                        } else {
                            //console.log('现在是第一页');
                            that.buildList(res.list);
                        }
                        if (that.stype == 2) {
                            that.showRight(res.info);
                        }
                        //xGame.toast.show('没有信件');
                    } 
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }

                xGame.unlock();
            };
            
            if (that.stype == 2) {
                loadContents({
                    'c': 'message',
                    'm': 'get_message',
                    't': '1',
                    'f': '0',
                    'p': page == undefined ? that.page : page,
                    'per': that.perPage
                },
                Func);
            } else if (that.stype == 3) {
                loadContents({
                    'c': 'message',
                    'm': 'get_message',
                    't': '1',
                    'f': '1',
                    'p': page == undefined ? that.page : page,
                    'per': that.perPage
                },
                Func);
            } else if (that.stype == 4) {
                loadContents({
                    'c': 'gmmail',
                    'm': 'get_message',
                    'p': page == undefined ? that.page : page,
                    'per': that.perPage
                },
                Func);
            } else if (that.stype == 5) {
                loadContents({
                    'c': 'message',
                    'm': 'get_message',
                    't': '4',
                    'f': '1',
                    'p': page == undefined ? that.page : page,
                    'per': that.perPage
                },
                Func);
            }
            Func = null;

        },

        buildList: function(data, append) {
            var that = this;
            var html = '',
            className = '';
            /*判断是否存在数据*/
            if (data.length == 0) {
                if (append == undefined) {
                    that.isNoMail();
                    //console.log('第一次没有数据，清空页面');
                }
            } else {
                that.bottomDom.find('#mb_read_all').find('#mb_grade_info').hide();
                that.bottomDom.find('#mb_read_all').find('#mb_gm_grade').hide();
                that.bottomDom.find('#mb_read_all').find('.mb_input').removeClass('gm_name_bg');
                that.bottomDom.find('#mb_list_all').removeClass('nodata');
                that.bottomDom.find('#mb_delete_all_btn').attr('class', 'pop_btn_middle btn').show();
                that.bottomDom.find('#mb_delete_btn').attr('class', 'pop_btn_middle btn').show();
                if (that.stype == 2) {
                    that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('sender'));
                    /*这里屏蔽，收件箱单独处理
                    that.bottomDom.find('#mb_reply_btn').attr('class','pop_btn_middle btn').show();
                    */
                }
                if (that.stype == 3) {
                    that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('receiver'));
                    that.hideByObj(that.bottomDom.find('#mb_reply_btn'));
                    that.hideByObj(that.bottomDom.find('#mb_fetch_btn'));
                }
                if (that.stype == 4) {
                    that.hideByObj(that.bottomDom.find('#mb_reply_btn'));
                    that.hideByObj(that.bottomDom.find('#mb_fetch_btn'));
                    that.bottomDom.find('#mb_nickname_input').val('GM');
                    that.bottomDom.find('#mb_read_all').find('.mb_input').addClass('gm_name_bg');
                }
                if (that.stype == 5) {
                    that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('mailboxpop_receiver_country'));
                    that.hideByObj(that.bottomDom.find('#mb_reply_btn'));
                    that.hideByObj(that.bottomDom.find('#mb_fetch_btn'));
                }
                for (var i = 0; i < data.length; i++) {
                    if (that.stype == 2) {
                        className = (data[i].isread == 0 ? 'unread': 'isread');
                    }

                    if (that.stype == 3 || that.stype == 5) {
                        className = 'issend';
                    }

                    if (that.stype == 4) {
                        className = (data[i].status == 0 ? 'issubmit': 'isreply');
                    }

                    if (i == 0 && append == undefined && that.stype == 2) {
                        that.readMid = data[i].id;
                        className += ' selected';
                    }
                    if (that.stype != 5) {
                        if (that.stype == 2 && data[i].type == 10) {
                            html += '<li class="' + className + '" id="mbid_' + data[i].id + '" mbid="' + data[i].id + '"><span></span><p><em>[' + lang('mailboxpop_country_mail') + '] ' + data[i].name + '</em></p></li>';
                        } else {
                            html += '<li class="' + className + '" id="mbid_' + data[i].id + '" mbid="' + data[i].id + '"><span></span><p><em>[' + data[i].name + '] ' + data[i].message + '</em></p></li>';
                        }
                    } else {
                        html += '<li class="' + className + '" id="mbid_' + data[i].id + '" mbid="' + data[i].id + '"><span></span><p><em>' + lang('mailboxpop_to_country', [data[i].name]) + '</em></p></li>';
                    }
                }
                if (append) {
                    that.listDom.append(html);
                    if (data.length == that.perPage) {
                        that.getMore();
                    }
                } else {
                    that.listDom.html(html);
                }

            }
            /*判断是否删除iscroll*/
            if (that.listScroller == null) {
                if (that.listDom.find('li').size() > 4) {
                    that.listScrollerMaker();
                    if (that.listDom.find('li').size() == that.perPage) {
                        that.getMore();
                    }
                    that.listScroller.refresh();
                }
            } else if (that.listScroller != null) {

                if(that.listDom.find('li').size() > 4) {
                    if (append == undefined) {
                        that.listScroller.scrollTo(0, 0);
                    }
                    that.listScroller.refresh();
                } else if (that.listDom.find('li').size() <= 4) {
                    that.listScroller.destroy();
                    that.listScroller = null;
                }
            }
        },

        getMore: function() {
            var that = this;
            $('<div id="pullDown" ><span class="pullDownLabel">' + lang('click_for_more') + '</span></div>').appendTo('#mailpoppop_list').one('touchend',
            function() {
                that.fillMessage(that.page + 1);
                that = null;
                $(this).remove();
            });
        },

        listScrollerMaker: function() {
            //var pullDownEl = document.getElementById('pullDown');
            this.listScroller = new iScroll('mailboxpop_scroller', {
                snap: 'li',
                hScroll: true,
                hScrollbar: false,
                vScrollbar: false,
                checkDOMChanges: true,
                bounce: true,
                /*
                onRefresh: function () {
                    if (pullDownEl.className.match('loading')) {
                        pullDownEl.className = '';
                        pullDownEl.querySelector('.pullDownLabel').innerHTML = '查看更多...';
                    }
                },
                onScrollMove: function () {
                    if (this.y < -5 && !pullDownEl.className.match('flip')) {
                        pullDownEl.className = 'flip';
                        pullDownEl.querySelector('.pullDownLabel').innerHTML = '松开刷新...';
                        this.minScrollY = 0;
                    }
                },
                onScrollEnd: function () {
                    if (pullDownEl.className.match('flip')) {
                        pullDownEl.className = 'loading';
                        pullDownEl.querySelector('.pullDownLabel').innerHTML = '载入中...';
                        //that.refresh();
                    }
                }
                */
            });
        },

        showRight: function(data) {
            var that = this;

            if (data == undefined) {
                return;
            }
            if (that.stype != 4) {
                if (that.stype == 2) {
                    if (data.type == 10) {
                        that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('mailboxpop_send_country'));
                    } else {
                        that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('sender'));
                    }
                }
                that.bottomDom.find('#mb_nickname_input').val(data.name);
            }
            if (that.stype == 4 && data.status != undefined) {
                if (data.status == 0) {
                    that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('receiver'));
                } else {
                    that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('sender'));
                }
            }

            var dom;
            /*单独处理*/
            if (that.stype == 2) {
                if (data.type == 1) {
                    that.hideByObj(that.bottomDom.find('#mb_reply_btn'));
                    if (data.giftstatus == 1) {
                        that.hideByObj(that.bottomDom.find('#mb_fetch_btn'));
                    } else {
                        that.bottomDom.find('#mb_fetch_btn').attr('class', 'pop_btn_middle btn').show();
                    }
                } else {
                    that.hideByObj(that.bottomDom.find('#mb_fetch_btn'));
                    if (data.type == 10) {
                        if (player.info.country_job == 1) {//是君主
                            that.bottomDom.find('#mb_reply_btn').attr({'class': 'pop_btn_middle btn','type': 'country'}).show();
                        } else {
                            that.hideByObj(that.bottomDom.find('#mb_reply_btn'));
                        }
                    } else {
                        that.bottomDom.find('#mb_reply_btn').attr({'class': 'pop_btn_middle btn','type': 'player'}).show();
                    }
                    
                }
                var str = '';
                if (data.act > 0) {
                    str += '<li class="act">' + lang('act') + ' +' + data.act + '</li>';
                }
                if (data.silver > 0) {
                    str += '<li class="silver">' + lang('silver') + ' +' + data.silver + '</li>';
                }
                if (data.gold > 0) {
                    str += '<li class="gold">' + lang('gold') + ' +' + data.gold + '</li>';
                }
                if (data.barcard > 0) {
                    str += '<li class="barcard">' + lang('free_leap') + ' +' + data.barcard + '</li>';
                }
                dom = $('<li>' + data.message + '</li><ul id="mailreward_wrap" style="opacity: 1; display: block; ">' + str + '</ul>');
                str = null;
            } else {
                dom = $('<li>' + data.message + '</li>');
            }
            
            that.bottomDom.find('#mb_mail_text_ul').html(dom);
            that.setIscroller(dom, data.dateline, data.king);
            dom = null;
            
            if (data.type == 5) {
                //console.log('显示uid' + data.fromuid);
                that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').attr('uid',data.fromuid);
            } else {
                //console.log('不显示');
                that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').removeAttr('uid');
            }

        },

        //处理信件是否需要iscroll滑动以及是否显示时间和君主名
        setIscroller: function(obj, dateline, king) {
            
            if (dateline != undefined) {
                this.bottomDom.find('#mb_mail_dateline').html(dateline);
            } else {
                this.bottomDom.find('#mb_mail_dateline').html('');
            }
            
            var height = 0;
            if (king != undefined) {
                this.bottomDom.find('#mb_mail_author').html(lang('mailboxpop_king_is', [king]));
                height = 188;
            } else {
                height = 212;
                this.bottomDom.find('#mb_mail_author').html('');
            }
            
            this.bottomDom.find('#mb_mail_text_scroller').height(height);
            
            
            if (obj.height() > height) {
                if (this.mailInfoScroller == null) {
                    this.mailInfoScroller = new iScroll('mb_mail_text_scroller', {
                        momentum: false,
                        hScrollbar: false,
                        vScrollbar: false
                    });
                }
                this.bottomDom.find('#mb_mail_text_ul').height(obj.height());
                this.mailInfoScroller.refresh();
            } else {
                if (this.mailInfoScroller != null) {
                    this.mailInfoScroller.destroy();
                    this.mailInfoScroller = null;
                }
            }
        },
        
        readById: function(id) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                if (parseInt(res.status) == 1) {
                    that.bottomDom.find('#mbid_' + that.readMid).removeClass('selected');
                    that.readMid = id;
                    that.bottomDom.find('#mbid_' + that.readMid).addClass('selected');
                    that.showRight(res.list);
                    if (that.bottomDom.find('#mbid_' + that.readMid).hasClass('unread') && res.list.isread == 1) {
                        that.bottomDom.find('#mbid_' + that.readMid).removeClass('unread').addClass('isread');
                    }
                    that.grade = res.list.grade;
                    if (that.stype == 4 && res.list.reply_uid > 0) {
                        //已回复的信件
                        if (res.list.grade > 0) {
                            //显示评了几分
                            var html = '';
                            for (var i = 1; i <= res.list.grade; i++) {
                                html += '<em></em>';
                            }
                            that.bottomDom.find('#mb_read_all').find('#mb_grade_info').html(html);
                            that.bottomDom.find('#mb_read_all').find('#mb_grade_info').show();
                            that.bottomDom.find('#mb_read_all').find('#mb_gm_grade').hide();
                        } else {
                            that.bottomDom.find('#mb_read_all').find('#mb_gm_grade').show();
                            that.bottomDom.find('#mb_read_all').find('#mb_grade_info').hide();
                        }
                    } else {
                        //未回复
                        that.bottomDom.find('#mb_read_all').find('#mb_gm_grade').hide();
                        that.bottomDom.find('#mb_read_all').find('#mb_grade_info').hide();
                    }
                } else {
                    xGame.toast.show(lang('can_not_found_mail'));
                }
                xGame.unlock();
            };
            if (that.stype == 4) {
                loadContents({
                    'c': 'gmmail',
                    'm': 'index',
                    'id': id,
                },
                Func);
            } else {
                loadContents({
                    'c': 'message',
                    'm': 'index',
                    'id': id,
                    'f': (that.stype == 2 ? '0': '1'),
                    't': '2'
                },
                Func);
            }
            Func = null;
        },

        sendMessage: function(type, countrysend) {
            var that = this;

            var name = that.bottomDom.find('#mb_receiver_input').val();
            var messgae = that.bottomDom.find('#mb_message_input').val();

            if (name == '') {
                xGame.toast.show(lang('receiver_is_null'));
                return;
            }

            if (messgae == '') {
                xGame.toast.show(lang('msg_is_null'));
                return;
            }

            if (messgae.length > 150) {
                xGame.toast.show(lang('msg_more_than_150'));
                return;
            }

            if (name == player.info.nickname && type != 5) {
                xGame.toast.show(lang('can_not_send_self'));
                that.bottomDom.find('#mb_receiver_input').val('');
                return;
            }

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                if (res.status == 1) {
                    that.hideWriteDiv();
                    xGame.toast.show(lang('send_complete'));
                    if (that.stype != 2) {
                        that.bottomDom.find('#mb_list_all').removeClass('nodata');
                        that.bottomDom.find('.selected').removeClass('selected');
                        var dom = $('<li>' + messgae + '</li>')
                        that.bottomDom.find('#mb_mail_text_ul').html(dom);
                        that.setIscroller(dom, res.dateline, res.king);
                        
                        that.readMid = res.id.id;
                        that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').removeAttr('uid');
                        if (that.stype == 3) {
                            that.listDom.prepend('<li class="issend selected" id="mbid_' + res.id.id + '" mbid="' + res.id.id + '"><span></span><p><em>[' + name + '] ' + messgae + '</em></p></li>');
                            that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('receiver'));
                            that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').val(name);
                            if (res.uid != undefined) {
                                that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').attr('uid', res.uid);
                            }
                        } else if (that.stype == 4) {
                            that.listDom.prepend('<li class="issubmit selected" id="mbid_' + res.id.id + '" mbid="' + res.id.id + '"><span></span><p><em>[GM] ' + messgae + '</em></p></li>');
                            that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('receiver'));
                            that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').val('GM');
                            that.bottomDom.find('#mb_read_all').find('#mb_grade_info').hide();
                            that.bottomDom.find('#mb_read_all').find('#mb_gm_grade').hide();
                        } else if (that.stype == 5) {
                            that.listDom.prepend('<li class="issend selected" id="mbid_' + res.id.id + '" mbid="' + res.id.id + '"><span></span><p><em>' + lang('mailboxpop_to_country', [name]) + '</em></p></li>');
                            that.bottomDom.find('#mb_read_all').find('#mb_nickname').html(lang('mailboxpop_receiver_country'));
                            that.bottomDom.find('#mb_read_all').find('#mb_nickname_input').val(name);
                        }
                        that.bottomDom.find('#mb_delete_all_btn').attr('class', 'pop_btn_middle btn').show();
                        that.bottomDom.find('#mb_delete_btn').attr('class', 'pop_btn_middle btn').show();
                    }
                    
                    //不管是什么情况,回复国家都更新金币
                    if (type == 5) {
                        player.updateinfo({"gold":res.all_gold});
                    }

                    if (that.listDom.find('li').size() >= 5) {
                        if (that.listScroller == null) {
                            that.listScrollerMaker();
                        }
                        that.listScroller.refresh();
                    }
                } else {
                    if (res.status == -1) {
                        xGame.toast.show(lang('no_user_try_again'));
                        that.bottomDom.find('#mb_receiver_input').val('');
                    } else if (res.status == -2) {
                        xGame.toast.show(lang('mailboxpop_cannot_use'));
                    } else if (res.status == -3) {
                        xGame.toast.show(lang('mailboxpop_name_error'));
                    } else if (res.status == -4) {//返回价格
                        xGame.confirm.show(lang('notice'), lang('mailboxpop_send_ensure', [res.price]), 2,
                        function() {
                            xGame.confirm.hide();
                            that.sendMessage(type, true);
                        },
                        function() {
                            xGame.confirm.hide();
                        });
                    } else if (res.status == -5) {//元宝不足
                        xGame.goPay(lang('mailboxpop_no_gold_to_send'));
                    } else if (res.status == -6) {
                        xGame.toast.show(lang('banned_words'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    
                }

                xGame.unlock();
            };
            if (type == 4) {//gm发信
                loadContents({
                    'c': 'gmmail',
                    'm': 'send_message',
                    'i': messgae,
                },
                Func);
            } else if (type == 5) {//国家发信
                loadContents({
                    'c': 'message',
                    'm': 'send_message',
                    'n': name,
                    'i': messgae,
                    'sure': countrysend == undefined ? '0' : '1',
                    't': '2'
                },
                Func);
            } else {
                loadContents({//普通发信
                    'c': 'message',
                    'm': 'send_message',
                    'n': name,
                    'i': messgae
                },
                Func);
            }
            Func = null;
        },

        resetWriteFunc: function() {
            var that = this;
            if (that.stype != 4) {
                that.bottomDom.find('#mb_receiver_input').val('');
            }
            that.bottomDom.find('#mb_message_input').val('');
        },

        /*有参数的是单个删除，没参数的是全部删除*/
        deleteFunc: function(id) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                if (res.status == 1) {
                    if (id != undefined) {
                        if (that.listDom.find('li').size() == 5) {
                            that.listScroller.destroy();
                            that.listScroller = null;
                        }
                        var dom = that.bottomDom.find('#mbid_' + that.readMid).siblings().first();

                        if (dom.length != 0) {
                            xGame.unlock();
                            that.readById(dom.attr('mbid'));
                            that.bottomDom.find('#mbid_' + that.readMid).remove();
                        } else {
                            that.isNoMail();
                        }
                    } else {
                        if (that.listScroller != null) {
                            that.listScroller.destroy();
                            that.listScroller = null;
                        }
                        that.isNoMail();
                    }

                    xGame.toast.show(lang('delete_complete'));
                } else if (res.status == 0) {
                    xGame.toast.show(lang('can_not_delete'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
                xGame.unlock();
            };
            if (id != undefined) {
                if (that.stype == 4) {
                    loadContents({
                        'c': 'gmmail',
                        'm': 'del_message',
                        'id': id,
                    },
                    Func);
                    /*GM*/
                } else if (that.stype == 2) {
                    loadContents({
                        'c': 'message',
                        'm': 'del_message',
                        'id': id,
                        't': '1'
                    },
                    Func);
                    /*收件箱*/
                } else if (that.stype == 3) {
                    loadContents({
                        'c': 'message',
                        'm': 'del_message',
                        'id': id,
                        't': '1',
                        'f': '1'
                    },
                    Func);
                    /*发件箱*/
                }  else if (that.stype == 5) {
                    loadContents({
                        'c': 'message',
                        'm': 'del_message',
                        'id': id,
                        't': '3',
                        'f': '1'
                    },
                    Func);
                    /*国家发件箱*/
                }
                
            } else {
                if (that.stype == 4) {
                    loadContents({
                        'c': 'gmmail',
                        'm': 'del_message'
                    },
                    Func);
                    /*GM*/
                } else if (that.stype == 2) {
                    loadContents({
                        'c': 'message',
                        'm': 'del_message',
                        't': '1'
                    },
                    Func);
                    /*收件箱*/
                } else if (that.stype == 3) {
                    loadContents({
                        'c': 'message',
                        'm': 'del_message',
                        't': '1',
                        'f': '1'
                    },
                    Func);
                    /*发件箱*/
                } else if (that.stype == 5) {
                    loadContents({
                        'c': 'message',
                        'm': 'del_message',
                        't': '3',
                        'f': '1'
                    },
                    Func);
                    /*国家发件箱*/
                }
            }
            Func = null;

        },

        isNoMail: function() {
            var that = this;

            that.bottomDom.find('#mb_nickname_input').val('');
            that.bottomDom.find('#mb_mail_text_ul').html('');
            that.bottomDom.find('#mb_grade_info').html('');
            that.bottomDom.find('#mb_read_all').find('#mb_nickname').html('');
            that.listDom.html('');
            that.bottomDom.find('#mb_mail_author').html('');
            that.bottomDom.find('#mb_mail_dateline').html('');

            that.hideByObj(that.bottomDom.find('#mb_delete_all_btn'));
            that.hideByObj(that.bottomDom.find('#mb_delete_btn'));
            that.hideByObj(that.bottomDom.find('#mb_reply_btn'));
            that.hideByObj(that.bottomDom.find('#mb_fetch_btn'));
            
            that.bottomDom.find('#mb_list_all').addClass('nodata');
        },

        hideByObj: function(obj) {
            obj.attr('class', 'pop_btn_middle_disable btn').hide();
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.topDom) {
                this.topDom.undelegate('#mb_system_tbtn', 'touchend');
                this.topDom.undelegate('#mb_receive_tbtn', 'touchend');
                this.topDom.undelegate('#mb_send_tbtn', 'touchend');
                this.topDom.undelegate('#mb_gm_tbtn', 'touchend');
                this.topDom.undelegate('#mb_activities_tbtn', 'touchend');
                this.topDom.undelegate('#mb_countrysend_tbtn', 'touchend');
                this.topDom.remove();
                this.topDom = null;
            }
            if (this.bottomDom) {
                this.bottomDom.find('#mailpoppop_list').undelegate('li', 'touchend');
                this.bottomDom.undelegate('#mb_creat_btn', 'touchend');
                this.bottomDom.undelegate('#mb_send_btn', 'touchend');
                this.bottomDom.undelegate('#mb_reset_btn', 'touchend');
                this.bottomDom.undelegate('#mb_delete_btn.pop_btn_middle', 'touchend');
                this.bottomDom.undelegate('#mb_delete_all_btn.pop_btn_middle', 'touchend');
                this.bottomDom.undelegate('#mb_reply_btn.pop_btn_middle', 'touchend');
                this.bottomDom.undelegate('#mb_fetch_btn.pop_btn_middle', 'touchend');
                this.bottomDom.undelegate('#mb_nickname_input', 'touchend');
                this.bottomDom.undelegate('#mb_gm_grade', 'touchend');
                this.bottomDom.remove();
                this.bottomDom = null;
            }
            if (this.starDom) {
                this.starDom.undelegate('#grade_detail_hide', 'touchend');
                this.starDom.find('#grade_list').undelegate('.grade', 'touchstart');
                this.starDom.undelegate('#grade_submit_btn', 'touchend');
                this.starDom.remove();
                this.starDom = null;
           }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
           
            if (this.listScroller != null) {
                this.listScroller.destroy();
                this.listScroller = null;
            }

            if (this.mailInfoScroller != null) {
                this.mailInfoScroller.destroy();
                this.mailInfoScroller = null;
            }

            if (this.mailNoticeScroller != null) {
                this.mailNoticeScroller.destroy();
                this.mailNoticeScroller = null;
            }
            if (this.listDom) {
                this.listDom.remove();
                this.listDom = null;
            }
            if (this.mailInfoDom) {
                this.mailInfoDom.remove();
                this.mailInfoDom = null;
            }
            if (this.mailNoticeDom) {
                this.mailNoticeDom.remove();
                this.mailNoticeDom = null;
            }
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            this.readMid = this.page = this.perPage = this.stype = null;
            this.unbindAll();
        },
    };
})();