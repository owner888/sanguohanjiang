/* 国家矿区 */
(function() {
    countrymineWorldClass = function() {
        this.e;

        this.page; //当前的页码
        this.pagebtnsDom; //页码信息的dom
        this.dialogDom; //小对话框
        this.mineData; //当前页的矿区信息，以矿的id为键
        this.name_x;
        this.name_y; //名字的位置
        this.resultdata;

        this.kTimes;//开采次数
        this.kTimes;//抢劫次数

        this.flag_x;//国旗坐标集合，id为键，值为坐标
        this.flag_y;
    };
    countrymineWorldClass.prototype = {
        init: function(data) {
            var that = this;

            if (data) { //先保存结果
                that.resultdata = data;
            }
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    that.initRender(res);
                } else {
                    if (res.status == 2) {
                        xGame.toast.show(lang('countrymine_lv91'));
                    } else if (res.status == 3) {
                        xGame.toast.show(lang('no_country'));
                    } else if (res.status == -1) {
                        xGame.toast.show(lang('countrymine_fail'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    xGame.loadWorld('country');
                    return false;
                }
            }
            loadContents({
                'c': 'countrymine',
                'm': 'index'
            },
            Func, false,
            function() {
                xGame.loadWorld('country');
            }); //加载数据
            Func = null;

        },

        initRender: function(res) {
            var that = this;

            if (that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('countrymineworld_tmpl'));

            that.e = dom_world.find('#countrymineworld');
            that.e.find('#countrymine_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                xGame.loadWorld('country');
                e.preventDefault();
                e.stopPropagation();
            });
            
            dom_world = null;

            that.pagebtnsDom = that.e.find('#mine_page_btns');
            that.dialogDom = that.e.find('#countrymine_dialog');

            that.bindAll();

            that.e.find('#mine_tips').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                $(this).addClass('hide').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                function() {
                    $(this).remove();
                });
            });

            that.kTimes = res.log.times;
            that.rTimes = res.log.robtimes;

            that.flag_x = {};
            that.flag_y = {};

            that.e.find('#mine_level_tips').find('#ktimes').html(that.kTimes + lang('times'));
            that.e.find('#mine_level_tips').find('#rtimes').html(that.rTimes + lang('times'));

            xGame.updateCd('countrymine', 0);
            that.page = parseInt(res.list[0].page);
            that.showPageByPage(res);

            that.pagebtnsDom.find('#mine_page_num').html(that.page);

            that.buildMineByPage(that.page, res.list, res.log);

            $('#worldloading').removeClass('black').hide();

            //处理结果
            if (that.resultdata && that.resultdata.win > 0) {
                that.createResult(1);
            }

        },

        bindAll: function() {
            var that = this;

            /*绑定矿信息*/
            that.e.find('#minepop_wrap').find('#mine_list').undelegate('div', 'touchend').delegate('div', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = that.mineData[$(this).attr('mine_id')];
                if (obj.status == 0) {
                    that.showDialogById(obj);
                } else {
                    that.viewOne(obj)
                }
            });

            /*绑定上一页*/
            that.pagebtnsDom.undelegate('#mine_prev', 'touchend').delegate('#mine_prev', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.pagebtnsDom.hasClass('page_begin')) {
                    return;
                }
                that.getMineDataByPage(that.page - 1);
            });

            /*绑定下一页*/
            that.pagebtnsDom.undelegate('#mine_next', 'touchend').delegate('#mine_next', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.pagebtnsDom.hasClass('page_end')) {
                    return;
                }
                that.getMineDataByPage(that.page + 1);
            });

            /*绑定对话框隐藏*/
            that.dialogDom.undelegate('#countrymine_dialog_hide', 'touchend').delegate('#countrymine_dialog_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.dialogHide();
            });

            /*绑定开采*/
            that.dialogDom.undelegate('#m_caikuang_btn', 'touchend').delegate('#m_caikuang_btn', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('disable')) { //不设置属性
                    return false;
                }
                that.Caikuang($(this).attr('sid'), $(this).attr('type'));
            });

            /*绑定占领*/
            that.dialogDom.undelegate('#m_zhanling_btn', 'touchend').delegate('#m_zhanling_btn', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('disable')) { //不设置属性
                    return false;
                }
                that.Zhanling($(this).attr('sid'), $(this).attr('type'), $(this).attr('uid'));
            });

            /*绑定资料*/
            that.dialogDom.undelegate('#mine_owner', 'touchend').delegate('#mine_owner', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).find('em').attr('uid') == undefined) { //不设置属性
                    return false;
                }
                xGame.showpop('info', $(this).find('em').attr('uid'));
            });

            /*绑定放弃*/
            that.dialogDom.undelegate('#m_giveup_btn', 'touchend').delegate('#m_giveup_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).hasClass('disable')) {
                    return;
                }
                var sid =$(this).attr('sid');
                xGame.confirm.show(lang('notice'), lang('mine_ensure_giveup'), 2,
                function() {
                    xGame.confirm.hide(function() {
                        that.giveUp(sid);
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            });

            /*绑定收获*/
            that.dialogDom.undelegate('#m_get_btn', 'touchend').delegate('#m_get_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).hasClass('disable')) {
                    return;
                }
                that.getJade($(this).attr('sid'));
            });

        },

        dialogHide: function() {
            this.e.find('#mine_mask').hide();
            this.dialogDom.hide();
        },

        showDialogById: function(obj) {
            var that = this;

            that.dialogDom.find('span').attr('class', 'mine_img' + obj.type);
            that.dialogDom.find('#mine_cl').find('em').html(obj.jade);

            xGame.updateCd('countrymine', obj.time);
            that.dialogDom.find('#mine_owner').find('em').removeAttr('uid');

            if (that.mineData.log == null || (obj.id == parseInt(that.mineData.log.site) && obj.page == parseInt(that.mineData.log.page))) {
                that.dialogDom.find('#countrymine_msg_conetnt').find('button').removeClass();
            } else {
                that.dialogDom.find('#countrymine_msg_conetnt').find('button').attr('class', 'disable');
            }

            if (obj.status == 0) {
                that.dialogDom.find('#mine_owner').find('em').html(lang('no'));
                that.dialogDom.find('#mine_country').find('em').html(lang('no'));
                that.dialogDom.find('#mine_jl').find('em').html('0');
                that.dialogDom.find('#countrymine_msg_conetnt').find('button').attr({
                    'id': 'm_caikuang_btn',
                    'sid': obj.id,
                    'type': obj.type
                }).html(lang('kaicai'));
                that.dialogDom.find('#mine_msg').html(lang('countrymine_notice1'));
                that.dialogDom.find('#tag').css('display', 'none');
            } else if (obj.status == 1) {
                that.dialogDom.find('#mine_owner').find('em').html('Lv.' + obj.level + '&nbsp;' + obj.nickname);
                that.dialogDom.find('#mine_owner').find('em').attr('uid', obj.uid);
                that.dialogDom.find('#mine_country').find('em').html(obj.country);
                that.dialogDom.find('#mine_jl').find('em').html(obj.get_jade);
                that.dialogDom.find('#countrymine_msg_conetnt').find('button').attr({
                    'id': 'm_zhanling_btn',
                    'sid': obj.id,
                    'type': obj.type,
                    'uid': obj.uid
                }).html((obj.friend == 0 ? '' : '<span class="flag"></span>') + lang('qiangduo'));
                that.dialogDom.find('#mine_msg').html(lang('countrymine_notice2'));
                that.dialogDom.find('#tag').css('display', 'block');
            } else if (obj.status == 2) {
                that.dialogDom.find('#mine_owner').find('em').html('Lv.' + obj.level + '&nbsp;' + obj.nickname);
                that.dialogDom.find('#mine_country').find('em').html(obj.country);
                that.dialogDom.find('#mine_owner').find('em').attr('uid', obj.uid);
                if (obj.time == 0) {
                    that.dialogDom.find('#mine_jl').find('em').html(obj.get_jade);
                    that.dialogDom.find('#countrymine_msg_conetnt').find('button').attr({
                        'id': 'm_get_btn',
                        'sid': obj.id,
                        'type': obj.type
                    }).html(lang('shouhuo'));
                    that.dialogDom.find('#mine_msg').html(lang('countrymine_notice3'));
                    that.dialogDom.find('#tag').css('display', 'block');
                } else {
                    that.dialogDom.find('#mine_jl').find('em').html(obj.get_jade);
                    that.dialogDom.find('#countrymine_msg_conetnt').find('button').attr({
                        'id': 'm_giveup_btn',
                        'sid': obj.id,
                        'type': obj.type
                    }).html(lang('fangqi'));
                    that.dialogDom.find('#mine_msg').html(lang('countrymine_notice4'));
                    that.dialogDom.find('#tag').css('display', 'none');
                }

            }

            that.e.find('#mine_mask').show();
            that.dialogDom.show();
        },

        getMineDataByPage: function(page) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    /*只有成功时才将page赋值*/
                    that.page = page;
                    that.pagebtnsDom.find('#mine_page_num').html(that.page);
                    that.buildMineByPage(that.page, res.list, res.log);

                    that.showPageByPage(res);

                    break;
                case - 1 : xGame.toast.show(lang('countrymine_fail'));
                    break;
                case 2 : xGame.toast.show(lang('countrymine_lv91'));
                    break;
                case 3 : xGame.toast.show(lang('no_country'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'countrymine',
                'm': 'index',
                'p': page
            },
            Func);
            Func = null;
        },

        showPageByPage: function(res) {
            var that = this;
            /*根据page显示页码信息*/
            if (that.page == 1) {
                that.pagebtnsDom.attr('class', 'page_begin');
            } else {
                if (res.page != 0) {
                    that.pagebtnsDom.removeClass();
                } else {
                    that.pagebtnsDom.attr('class', 'page_end');
                }
            }
        },

        giveUp: function(id) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                xGame.unlock();
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    /*修改dom*/
                    that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + that.mineData.log.site).removeClass('mine_work').find('span').remove();
                    that.e.find('#minepop_wrap').find('#mine_list').find('#flag' + id).remove();
                    /*因为是设置status = 0了，所以很多都是写死了，没关系*/
                    that.mineData[that.mineData.log.site].status = 0;
                    that.mineData[that.mineData.log.site].time = 0;
                    that.mineData.log = null;

                    if (parseInt(res.jade) > 0) {
                        xGame.toast.show(lang('countrymine_gup_get_jade', [res.jade]));
                    } else {
                        xGame.toast.show(lang('countrymine_no_reward'));
                    }

                    that.dialogHide();

                } else if (res.status == 2) {
                    xGame.toast.show(lang('countrymine_lv91'));
                } else if (res.status == 3) {
                    xGame.toast.show(lang('no_country'));
                } else if (res.status == -1) {
                    xGame.toast.show(lang('get_user_info_fail'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('countrymine_was_robbed'));
                    that.dialogHide();
                    that.getMineDataByPage(that.page);
                } else if (res.status == -3) {
                    xGame.toast.show(lang('mine_can_get'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }

            };
            loadContents({
                'c': 'countrymine',
                'm': 'give_up'
            },
            Func);
            Func = null;
        },

        getJade: function(id) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                xGame.unlock();
                res.status = parseInt(res.status);
                if (res.status == 1) {

                    /*修改dom*/
                    that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + id).removeClass('mine_work').find('span').remove();
                    that.e.find('#minepop_wrap').find('#mine_list').find('#flag' + id).remove();
                    /*因为是设置status = 0了，所以很多都是写死了，没关系*/
                    that.mineData[id].status = 0;
                    that.mineData[id].time = 0;
                    that.mineData.log = null;

                    that.dialogHide();
                    //如果有特殊事件
                    if (res.event_status == 1) {
                        xGame.islock = true;
                        miscpop.showCountrymineSp(res.event_name, res.event_type, res.event_value, res.jade, res.event_add, res.all_gold, function() {
                            xGame.toast.show(lang('countrymine_get_jade', [res.jade + (res.event_type == 2 ? ('<em style="color:red;">(' + res.event_value + '%)</em>') : '')]));
                        });
                    } else {
                        xGame.toast.show(lang('countrymine_get_jade', [res.jade]));
                    }

                    if (player.info.oncountrymine == 1) { //隐藏主城里气泡
                        player.info.oncountrymine = 0;
                        $('#countrymine_building').children('span').css('display', 'none');
                    }

                } else if (res.status == 2) {
                    xGame.toast.show(lang('countrymine_lv91'));
                } else if (res.status == 3) {
                    xGame.toast.show(lang('no_country'));
                } else if (res.status == -1) {
                    xGame.toast.show(lang('get_user_info_fail'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('countrymine_was_robbed'));
                    that.dialogHide();
                    that.getMineDataByPage(that.page);
                } else if (res.status == -3) {
                    xGame.toast.show(lang('not_time_yet'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
            };
            loadContents({
                'c': 'countrymine',
                'm': 'get_reward',
                's': id
            },
            Func);
            Func = null;
        },

        Zhanling: function(id, type, touid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                    case 1:
                        that.dialogHide();
                        miscpop.enterBattleFromCountryMine(res, touid);
                        player.taskaction(13);
                        break;
                    case 2 : xGame.toast.show(lang('countrymine_lv91'));
                        break;
                    case 3 : xGame.toast.show(lang('no_country'));
                        break;
                    case - 1 : xGame.toast.show(lang('get_user_info_fail'));
                        break;
                    case - 2 : xGame.toast.show(lang('had_one_mine'));
                        break;
                    case - 3 : xGame.toast.show(lang('countrymine_notimes_rob'));
                        break;
                    case - 4 : xGame.toast.show(lang('countrymine_not_exist_rob'));
                        that.dialogHide();
                        that.getMineDataByPage(that.page);
                        break;
                    case - 5 : xGame.toast.show(lang('countrymine_same_country'));
                        break;
                    case - 6 : xGame.toast.show(lang('countrymine_rtimes_fail'));
                        break;
                    case - 7 : xGame.toast.show(lang('countrymine_had_robbed'));
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                    }
            };
            loadContents({
                'c': 'countrymine',
                'm': 'rob',
                'p': that.page,
                'id': id,
                't': type
            },
            Func);
            Func = null;
        },

        /* 占领结果 */
        createResult: function(type) {

            var html = $('<div class="mine_result_wrap"></div>');

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
            html.append('<span class="type' + type + '"></span>');
            //开始
            this.e.find('#minepop_wrap').append(html);
            movieclip.play();
            movieclip = null;
        },

        Caikuang: function(id, type) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                case 1:
                    /*修改dom*/
                    that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + id).addClass('mine_work').append('<span class="mine_owner" style="left:' + that.name_x + 'px;bottom:' + that.name_y + 'px;color:red;">' + player.info.nickname + '<em style="width:0"></em></span>');
                    //只有中间的需要特殊处理
                    if (that.page == 1 && id == 1) {
                        that.e.find('#mine' + id).find('.mine_owner').css({
                            'left': '60px',
                            'bottom': '-5px'
                        });
                        that.e.find('#mine_list').append(that.buildFlag(id, type, res.country_name));
                    } else {
                        that.e.find('#mine_list').append(that.buildFlag(id, type, res.country_name, that.flag_x[id], that.flag_y[id]));
                    }


                    /*修改数据*/
                    that.mineData[id].status = 2;
                    that.mineData[id].nickname = player.info.nickname;
                    that.mineData[id].time = res.dateline;
                    that.mineData[id].level = player.info.level;
                    that.mineData[id].get_jade = 0;
                    that.mineData[id].country = res.country;
                    that.mineData.log = {
                        'dateline': res.dateline,
                        'page': that.page,
                        'site': id,
                        'type': type
                    };

                    that.kTimes = res.times;
                    that.e.find('#mine_level_tips').find('#ktimes').html(that.kTimes + lang('times'));

                    that.dialogHide();
                    break;
                case 2 : xGame.toast.show(lang('countrymine_lv91'));
                    break;
                case 3 : xGame.toast.show(lang('no_country'));
                    break;
                case - 1 : xGame.toast.show(lang('get_user_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('had_one_mine'));
                    break;
                case - 3 : xGame.toast.show(lang('countrymine_no_ktime'));
                    break;
                case - 4 : xGame.toast.show(lang('countrymine_not_exist_kc'));
                        that.dialogHide();
                        that.getMineDataByPage(that.page);
                        break;
                case - 5 : xGame.toast.show(lang('countrymine_try_later'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

            };
            loadContents({
                'c': 'countrymine',
                'm': 'caikuang',
                'p': that.page,
                'id': id,
                't': type
            },
            Func);
            Func = null;

        },

        viewOne: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                case 1:
                    obj.country = res.info.country;
                    obj.friend = res.info.friend;
                    obj.get_jade = parseInt(res.info.get_jade);
                    obj.jade = parseInt(res.info.jade);
                    obj.nickname = res.info.nickname;
                    obj.status = res.info.status;
                    obj.time = res.info.time;
                    obj.uid = res.info.uid;

                    that.showDialogById(obj);
                    if (res.info.status != 0) {
                        that.e.find('#mine_list').find('#mine' + obj.id).find('span').html((res.info.friend == 1 ? '<span class="flag"></span>' : '') + res.info.nickname + '<em style="width: ' + Math.floor(obj.get_jade / obj.jade * 157) + 'px;"></em>').css('color', (res.info.uid == player.info.uid ? 'red' : '#581100'));
                    } else {
                        that.e.find('#minepop_wrap').find('#mine_list').find('#flag' + obj.id).remove();
                        that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + obj.id).removeClass('mine_work').find('span').remove();
                    }
                    break;
                case 2 : xGame.toast.show(lang('countrymine_lv91'));
                    break;
                case 3 : xGame.toast.show(lang('no_country'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

            };
            loadContents({
                'c': 'countrymine',
                'm': 'get_countrymine_info',
                'p': that.page,
                'id': obj.id,
                't': obj.type
            },
            Func);
            Func = null;

        },

        /*通过page设置矿的位置*/
        buildMineByPage: function(page, list, log) {
            var that = this;
            var html = '';
            var preNum;
            /*分别代表每行几个*/
            var x, y;
            /*起始的x,y坐标*/
            var _x, _y;
            /*分别代表间隔*/
            var length = list.length;
            /*数组的长度*/

            var flag = '';
            var flagDom = '';

            that.mineData = {};
            if (page == 1) {
                preNum = 3;
                x = 140;
                y = 30;
                _x = 260;
                _y = 175;
                that.name_x = 0;
                that.name_y = -15;
                for (var i = length - 1; i >= 0; i--) {
                    that.mineData[list[i].id] = list[i];
                    flag = list[i].friend == 0 ? '' : '<span class="flag"></span>';
                    if (list[i].time == undefined) {
                        that.mineData[list[i].id].time = 0;
                    }
                    if (i == 4) {
                        flagDom = that.buildFlag(list[i].id, list[i].type, list[i].country_name);

                        if (list[i].status == 0) {
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + '" style="left:340px;top:182px;" mine_id="' + list[i].id + '"></div>';
                        } else {
                            var plan = 3;
                            /*玩家收获进度的长度*/
                            plan = Math.floor(list[i].get_jade / list[i].jade * 157);
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + ' mine_work" style="left:340px;top:182px;" mine_id="' + list[i].id + '"><span class="mine_owner"' + (list[i].uid == player.info.uid ? ' style="color:red;"': '') + '>' + flag + list[i].nickname + '<em style="width:' + plan + 'px"></em></span></div>' + flagDom;
                        }
                    } else {
                        flagDom = that.buildFlag(list[i].id, list[i].type, list[i].country_name, (x + i % preNum * _x), (y + Math.floor(i / preNum) * _y));
                        if (list[i].status == 0) {
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + '" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"></div>';
                        } else {
                            var plan = 3;
                            /*玩家收获进度的长度*/
                            plan = Math.floor(list[i].get_jade / list[i].jade * 157);
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + ' mine_work" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"><span class="mine_owner" style="left:' + that.name_x + 'px;bottom:' + that.name_y + 'px;' + (list[i].uid == player.info.uid ? 'color:red;': '') + '">' + flag + list[i].nickname + '<em style="width:' + plan + 'px"></em></span></div>' + flagDom;
                        }
                    }
                }
            } else {
                preNum = 4;
                if (page == 2) {
                    x = 110;
                    y = 70;
                    _x = 190;
                    _y = 155;
                    that.name_x = -5;
                    that.name_y = -20;
                } else if (page == 3) {
                    x = 140;
                    y = 90;
                    _x = 180;
                    _y = 155;
                    that.name_x = -15;
                    that.name_y = -25;
                } else if (page >= 4) {
                    x = 145;
                    y = 110;
                    _x = 190;
                    _y = 155;
                    that.name_x = -25;
                    that.name_y = -25;
                }
                for (var i = length - 1; i >= 0; i--) {
                    that.mineData[list[i].id] = list[i];
                    flag = list[i].friend == 0 ? '' : '<span class="flag"></span>';
                    if (list[i].time == undefined) {
                        that.mineData[list[i].id].time = 0;
                    }
                    flagDom = that.buildFlag(list[i].id, list[i].type, list[i].country_name, (x + i % preNum * _x), (y + Math.floor(i / preNum) * _y));
                    if (list[i].status == 0) {
                        html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + '" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"></div>';
                    } else {
                        var plan = 3;
                        /*玩家收获进度的长度*/
                        plan = Math.floor(list[i].get_jade / list[i].jade * 157);
                        html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + ' mine_work" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"><span class="mine_owner" style="left:' + that.name_x + 'px;bottom:' + that.name_y + 'px;' + (list[i].uid == player.info.uid ? 'color:red;': '') + '">' + flag + list[i].nickname + '<em style="width:' + plan + 'px;"></em></span></div>' + flagDom;
                    }
                }
            }

            that.mineData.log = log.page == 0 ? null : log;
            that.e.find('#minepop_wrap').find('#mine_list').html(html);

            that = null;
            html = null;
            preNum = null;
            x = null;
            y = null;
            _x = null;
            _y = null;
            length = null;
            flag = null;
            flagDom = null;
        },

        //生成国旗的方法
        buildFlag: function(id, type, name, left, top) {
            if (type == 1) {
                return '<span id="flag' + id + '" class="flag" style="left:530px;top:185px;">' + name + '</span>';
            } else {
                this.flag_x[id] = left;
                this.flag_y[id] = top;

                if (type == 2) {
                    return '<span id="flag' + id + '" class="flag" style="left:' + (left + 130) + 'px;top:' + (top + 5)+ 'px;">' + name + '</span>';
                } else if (type == 3) {
                    return '<span id="flag' + id + '" class="flag" style="left:' + (left + 130) + 'px;top:' + (top + 5)+ 'px;">' + name + '</span>';
                } else if (type == 4) {
                    return '<span id="flag' + id + '" class="flag" style="left:' + (left + 110) + 'px;top:' + (top - 5)+ 'px;">' + name + '</span>';
                } else if (type == 5) {
                    return '<span id="flag' + id + '" class="flag" style="left:' + (left + 90) + 'px;top:' + (top - 30)+ 'px;">' + name + '</span>';
                }
            }
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#countrymine_hide').unbind('touchend');
                this.e.find('#minepop_wrap').find('#mine_list').undelegate('div', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.pagebtnsDom) {
                this.pagebtnsDom.undelegate('#mine_prev', 'touchend');
                this.pagebtnsDom.undelegate('#mine_next', 'touchend');
                this.pagebtnsDom.remove();
                this.pagebtnsDom = null;
            }
            if (this.dialogDom) {
                this.dialogDom.undelegate('#countrymine_dialog_hide', 'touchend');
                this.dialogDom.undelegate('#m_caikuang_btn', 'touchend');
                this.dialogDom.undelegate('#m_zhanling_btn', 'touchend');
                this.dialogDom.undelegate('#mine_owner', 'touchend');
                this.dialogDom.undelegate('#m_giveup_btn', 'touchend');
                this.dialogDom.undelegate('#m_get_btn', 'touchend');
                this.dialogDom.remove();
                this.dialogDom = null;
            }
        },

        clear: function() {
            xGame.unlock();
            if (this.e != null) {
                xGame.updateCd('countrymine', 0);

                this.resultdata = null;
                this.mineData = null;
                this.page = null;
                

                this.name_x = null;
                this.name_y = null;

                this.kTimes = null;
                this.kTimes = null;

                this.flag_x = null;
                this.flag_y = null;
                this.unbindAll();
                
            }
            
        }
    };
})();