/* 小圆圈提示 */
(function() {
    mailPopClass = function() {
        this.e;
        this.isshow = false;

        this.mailBtns;
        this.mailData;
        this.msgDom;
        this.nextId;
        this.currentId;
        this.stype; //类型1系统通知 2vip通知 3矿战 4演武榜 7国家 8跨服 9海外贸易 11国家矿脉

    };
    mailPopClass.prototype = {
        init: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                if (parseInt(res.status) == 1) {
                    if (that.e == undefined) {
                        /*避免重复创建和重复绑定*/
                        that.e = xGame.loadPopTmpl('mail');
                        that.e.hide();
                        that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                        function(e) {
                            if (xGame.checkLock(true)) { //不设置属性
                                return false;
                            }
                            mailpop.myHide();
                            e.preventDefault();
                            e.stopPropagation();
                        });

                        that.mailBtns = $('#mail_btns');
                        that.msgDom = that.e.find('#mail_msg_conetent');
                        that.nextId = null;
                        that.currentId = null;

                        that.bindAll();
                    }

                    that.mailBtns.hide();

                    var num = 0;
                    var oldNum = that.mailData ? that.mailData['num'] : 0;

                    that.mailData = {};
                    for (var i = 1; i <= 11; i++) {
                        if (res.list[i] != undefined) {
                            num++;
                            that.mailData[i] = res.list[i];
                            var theBtn = that.mailBtns.find('#mail_btn' + i);
                            if(theBtn.css('display') != 'inline-block') {
                                //console.log('modify btn');
                                theBtn.css('display', 'inline-block');
                            } else {
                                //console.log('not need to modify btn');
                            }
                            theBtn = null;
                        } else {
                            that.mailData[i] = null;
                        }
                    }

                    if(typeof oldNum == 'undefined' || oldNum == 0 || oldNum != num) {
                        //console.log('modify wrap');
                        that.mailBtns.attr('class', 'mail_have' + num);
                    } else {
                        //console.log('not need to modify wrap');
                    }
                    that.mailData['num'] = num;

                    if(that.mailData['num'] > 0) {
                        that.mailBtns.show();
                    } else {
                        that.mailBtns.hide();
                    }

                }
                xGame.unlock();
            };
            loadContents({
                'c': 'message',
                'm': 'index'
            },
            Func, true);
            Func = null;

        },

        bindAll: function() {
            var that = this;
            that.mailBtns.undelegate('button', 'touchend').delegate('button', 'touchend',
            function() {
                that.stype = $(this).attr('type');
                that.currentIdIndex = 0;
                that.currentId = that.mailData[that.stype][that.currentIdIndex];
                if (that.mailData[that.stype][1] != undefined) {
                    that.nextId = that.mailData[that.stype][that.currentIdIndex + 1];
                }
                that.readById();
            });

            /*当显示ok时，就证明只有一个，直接结束掉,设置那个类型为null。num -= 1*/
            that.msgDom.undelegate('#mail_ok', 'touchend').delegate('#mail_ok', 'touchend',
            function(e) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.myHide();
                e.preventDefault();
                e.stopPropagation();
            });

            /*能显示就已经证明有下一个*/
            that.msgDom.undelegate('#mail_next', 'touchend').delegate('#mail_next', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                //console.log('点击前：' + that.currentId + ' ,' + that.nextId);
                //console.log('点击前：' +that.mailData[that.stype].toString());
                that.mailData[that.stype].shift();
                if (that.mailData[that.stype].length <= 1) {
                    that.currentId = that.nextId;
                    that.nextId = null;
                } else {
                    var temp = that.nextId;
                    that.currentId = temp;
                    that.nextId = that.mailData[that.stype][1];
                }
                //console.log('点击后：' + that.currentId + ' ,' + that.nextId);
                //console.log('点击后：' +that.mailData[that.stype].toString());
                that.readById();
            });

            /* 战报按钮 */
            that.msgDom.undelegate('#mail_report', 'touchend').delegate('#mail_report', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.stype == 3 || that.stype == 4 || that.stype == 11) {
                    miscpop.viewBattleFromMail(that.currentId, 'message');
                } else if (that.stype == 8) {
                    miscpop.viewBattleFromMail($(this).attr('reportid'), 'worldarena');
                } else {
                    $(this).hide();
                }
            });

            /* 领取奖励按钮 */
            that.msgDom.undelegate('#mail_fetch', 'touchend').delegate('#mail_fetch', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                miscpop.fetchRewardFromMail(that.currentId);
            });
            
            that.msgDom.undelegate('em', 'touchend').delegate('em', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('uid') != undefined) {
                    xGame.showpop('info', parseInt($(this).attr('uid')));
                }
            });
            
        },

        myHide: function() {
            var that = this;
            /*更新会出错，这里做个判断*/
            if (that.mailData[that.stype] != undefined) {
                loadContents({
                    'c': 'message',
                    'm': 'update_read_status',
                    'type': that.stype
                },
                null, true);
                that.mailData[that.stype] = null;
                that.mailData['num'] -= 1;
                that.mailBtns.find('#mail_btn' + that.stype).css('display', 'none');
                that.mailBtns.attr('class', 'mail_have' + that.mailData['num']);
            } else {
                that.mailBtns.find('#mail_btn' + that.stype).css('display', 'none');
                that.mailBtns.attr('class', 'mail_have' + that.mailData['num']);
            }

            if (that.mailData['num'] == 0) {
                that.hide();
            } else {
                xGame.doAnimate(that.e, 'fadeOutUp', '0.25s',
                function() {
                    that.e.hide();
                    that.isshow = false;
                    /*关闭后都是初始化数据的*/
                    that.nextId = null;
                    that.currentId = null;
                });
            }
        },

        showByMyIdAndMsg: function(msg, getreward, date, res) {
            var that = this;
            var dom = that.msgDom.find('#mail_btn_line'),
            msgDom = that.msgDom.find('p'),
            rewardDom = that.msgDom.find('#mailreward_wrap');

            /*表示只有一个信件的时候，那么就只显示确定按钮*/
            if (that.currentId != null && that.nextId == null) {
                dom.attr('class', 'one');
            } else if (that.currentId != null && that.nextId != null) {
                /*不止只有一封信件时*/
                dom.attr('class', 'two');
            }

            if (that.stype == 3 || that.stype == 4 || that.stype == 8 || that.stype == 11) { //有战报
                dom.find('#mail_report').css({
                    'display': 'inline-block'
                });
				if (that.stype == 8) {
					dom.find('#mail_report').attr('reportid', res.list.title);
				}
            } else if (getreward.length > 0) { //有奖励
                dom.find('#mail_fetch').css({
                    'display': 'inline-block'
                });
                //dom.find('#mail_ok').css({'display':'none'});
            }

            if (getreward.length > 0) {
                var rewardhtml = '';
                for (var i = 0; i < getreward.length; i++) {
                    rewardhtml += '<li class="' + getreward[i]['type'] + '">' + getreward[i]['desc'] + ' +' + getreward[i]['num'] + '</li>';
                }
                rewardDom.html(rewardhtml).show();
            } else {
                rewardDom.hide();
            }

            msgDom.html(msg + '<span class="time">' + date + '</span>');

        },

        readById: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                if (parseInt(res.status) == 1) {
                    if (that.isshow == false) {
                        that.e.show();
                        xGame.doAnimate(that.e, 'fadeInDown', '0.25s');
                        that.isshow = true;
                    }
                    //检查是否有奖励
                    var _getreward = [];
                    var rewardlist = ['act', 'silver', 'gold', 'barcard'];
                    for (var i = 0; i < rewardlist.length; i++) {
                        var _rewardfield = rewardlist[i];
                        var _rewardvalue = res.list[rewardlist[i]];
                        if (_rewardvalue > 0) {
                            var _tmp = {
                                'type': '',
                                'desc': '',
                                'num': 0
                            };
                            _tmp['type'] = _rewardfield;
                            _tmp['desc'] = _rewardfield == 'act' ? lang('act') : (_rewardfield == 'silver' ? lang('silver') : (_rewardfield == 'gold' ? lang('gold') : lang('free_leap_times')));
                            _tmp['num'] = _rewardvalue;
                            _getreward.push(_tmp);
                            _tmp = null;
                        }
                    }
                    that.showByMyIdAndMsg(res.list.message, _getreward, res.list.dateline, res);
                } else {
                    xGame.toast.show(lang('can_not_found_mail'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'message',
                'm': 'index',
                'id': that.currentId
            },
            Func, false, function() { console.log('c=message&m=index error.'); }); //加上loading，否则网速慢的情况下很蛋疼
            Func = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.mailBtns) {
                this.mailBtns.undelegate('button', 'touchend');
                this.mailBtns.remove();
                this.mailBtns = null;
            }
            if (this.msgDom) {
                this.msgDom.undelegate('#mail_ok', 'touchend');
                this.msgDom.undelegate('#mail_next', 'touchend');
                this.msgDom.undelegate('#mail_report', 'touchend');
                this.msgDom.undelegate('#mail_fetch', 'touchend');
                this.msgDom.undelegate('em', 'touchend');
                this.msgDom.remove();
                this.msgDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.mailData = this.nextId = this.currentId = this.stype = this.currentIdIndex = null;

            this.unbindAll();
        },
    };
})();