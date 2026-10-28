/* 矿区 */
(function() {
    mineWorldClass = function() {
        this.e;

        this.page; //当前的页码
        this.pagebtnsDom; //页码信息的dom
        this.dialogDom; //小对话框
        this.mineData; //当前页的矿区信息，以矿的id为键
        this.mustRefresh; //是否需要每次
        this.name_x;
        this.name_y; //名字的位置
        this.resultdata;
        this.isTimeout = [];
    };
    mineWorldClass.prototype = {
        init: function(data) {
            var that = this;

            if (parseInt(player.info.level) < 26) {
                xGame.toast.show(lang('lv26_open'));
                xGame.loadWorld('city');
                return false;
            }

            if (data) { //先保存结果
                that.resultdata = data;
            }
            var Func = function(res) {
                res.status = parseInt(res.status);

                if (res.status == 1) {
                    that.initRender(res);
                } else {
                    if (res.status == -3) {
                        xGame.buyActConfirm();
                        //xGame.toast.show('军令不足！');
                    } else {
                        xGame.toast.show(lang('enter_mine_fail'));
                    }
                    xGame.loadWorld('city');
                    return false;
                }
            }
            loadContents({
                'c': 'mine',
                'm': 'index'
            },
            Func, false,
            function() {
                xGame.loadWorld('city');
            }); //加载数据
            Func = null;

        },

        initRender: function(res) {
            var that = this;

            if (that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('minepop_tmpl'));

            that.e = dom_world.find('#minepop');
            that.e.find('#mine_hide').unbind('touchend').bind('touchend',
            function() {
                that.isTimeout.push(setTimeout(function() {
                    xGame.loadWorld('city');
                },
                400));
            });
            dom_world = null;

            that.pagebtnsDom = that.e.find('#mine_page_btns');
            that.dialogDom = that.e.find('#minepop_dialog');

            that.bindAll();
            that.e.find('#mine_tips').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                $(this).addClass('hide').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                function() {
                    $(this).remove();
                });
            });
            xGame.updateCd('mine', 0);
            that.page = (res.list && res.list[0] && res.list[0].page) ? res.list[0].page : (parseInt(res.page) || 1);
            that.showPageByPage(res);

            that.pagebtnsDom.find('#mine_page_num').html(that.page);
            that.e.find('#mine_level_tips').html(lang('lv_mine', [res.level]));
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
                that.showDialogById($(this).attr('mine_id'));
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

            /*绑定刷新*/
            that.pagebtnsDom.undelegate('#mine_refresh', 'touchend').delegate('#mine_refresh', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.getMineDataByPage(1);
            });

            /*绑定对话框隐藏*/
            that.dialogDom.undelegate('#minepop_dialog_hide', 'touchend').delegate('#minepop_dialog_hide', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.dialogHide();
            });

            /*绑定开采*/
            that.dialogDom.undelegate('#m_caikuang_btn', 'touchend').delegate('#m_caikuang_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).hasClass('disable')) {
                    return;
                }
                if (parseInt(player.info.act) == 0) {
                    xGame.buyActConfirm();
                    return;
                }
                if (that.mustRefresh) {
                    xGame.toast.show(lang('mine_lv_not_fit'));
                    return;
                }
                that.Caikuang($(this).attr('sid'), $(this).attr('type'));
            });

            /*绑定占领*/
            that.dialogDom.undelegate('#m_zhanling_btn', 'touchend').delegate('#m_zhanling_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).hasClass('disable')) {
                    return;
                }
                if (parseInt(player.info.act) == 0) {
                    xGame.buyActConfirm();
                    //xGame.toast.show('您没有军令了，无法进行操作');
                    return;
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
                xGame.confirm.show(lang('notice'), lang('mine_ensure_giveup'), 2,
                function() {
                    xGame.confirm.hide(function() {
                        that.giveUp();
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
                that.getSilver($(this).attr('sid'));
            });

        },

        dialogHide: function() {
            this.e.find('#mine_mask').hide();
            this.dialogDom.hide();
        },

        showDialogById: function(id) {
            var that = this;
            var obj = that.mineData[id];

            that.dialogDom.find('span').attr('class', 'mine_img' + obj.type);
            that.dialogDom.find('#mine_cl').find('em').html(obj.silver);

            obj.time = obj.time == undefined ? 0 : obj.time;
            xGame.updateCd('mine', obj.time);
            that.dialogDom.find('#mine_owner').find('em').removeAttr('uid');

            if (that.mineData.log == null || (obj.id == parseInt(that.mineData.log.site) && obj.page == parseInt(that.mineData.log.page))) {
                that.dialogDom.find('#minepop_msg_conetnt').find('button').removeClass();
            } else {
                that.dialogDom.find('#minepop_msg_conetnt').find('button').attr('class', 'disable');
            }

            if (obj.status == 0) {
                that.dialogDom.find('#mine_owner').find('em').html(lang('no'));
                that.dialogDom.find('#mine_country').find('em').html(lang('no'));
                that.dialogDom.find('#mine_jl').find('em').html('0');
                that.dialogDom.find('#minepop_msg_conetnt').find('button').attr({
                    'id': 'm_caikuang_btn',
                    'sid': obj.id,
                    'type': obj.type
                }).html(lang('kaicai'));
                that.dialogDom.find('#mine_msg').html(lang('mine_notice1'));
            } else if (obj.status == 1) {
                that.dialogDom.find('#mine_owner').find('em').html('Lv.' + obj.level + '&nbsp;' + obj.nickname);
                that.dialogDom.find('#mine_owner').find('em').attr('uid', obj.uid);
                that.dialogDom.find('#mine_country').find('em').html(obj.country);
                that.dialogDom.find('#mine_jl').find('em').html(obj.get_silver);
                that.dialogDom.find('#minepop_msg_conetnt').find('button').attr({
                    'id': 'm_zhanling_btn',
                    'sid': obj.id,
                    'type': obj.type,
                    'uid': obj.uid
                }).html((obj.friend == 0 ? '' : '<span class="flag"></span>') + lang('qiangduo'));
                that.dialogDom.find('#mine_msg').html(lang('mine_notice2'));
            } else if (obj.status == 2) {
                that.dialogDom.find('#mine_owner').find('em').html('Lv.' + obj.level + '&nbsp;' + obj.nickname);
                that.dialogDom.find('#mine_country').find('em').html(obj.country);
                that.dialogDom.find('#mine_owner').find('em').attr('uid', obj.uid);
                if (obj.time == 0) {
                    that.dialogDom.find('#mine_jl').find('em').html(obj.get_silver);
                    that.dialogDom.find('#minepop_msg_conetnt').find('button').attr({
                        'id': 'm_get_btn',
                        'sid': obj.id,
                        'type': obj.type
                    }).html(lang('shouhuo'));
                    that.dialogDom.find('#mine_msg').html(lang('mine_notice3'));
                } else {
                    that.dialogDom.find('#mine_jl').find('em').html(obj.get_silver);
                    that.dialogDom.find('#minepop_msg_conetnt').find('button').attr({
                        'id': 'm_giveup_btn',
                        'sid': obj.id,
                        'type': obj.type
                    }).html(lang('fangqi'));
                    that.dialogDom.find('#mine_msg').html(lang('mine_notice4'));
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
            if (that.mustRefresh != undefined && that.mustRefresh) {
                that.mustRefresh = false;
            }
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    /*只有成功时才将page赋值*/
                    that.page = page;
                    that.pagebtnsDom.find('#mine_page_num').html(that.page);
                    that.e.find('#mine_level_tips').html(lang('lv_mine', [res.level]));
                    that.buildMineByPage(that.page, res.list, res.log);

                    that.showPageByPage(res);

                    break;
                case - 1 : xGame.toast.show(lang('lv26_open'));
                    break;
                case - 2 : xGame.toast.show(lang('page_error'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'mine',
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

        giveUp: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1 || res.status == 2) {
                    if (res.status == 2) {
                        that.mustRefresh = true;
                    } else {
                        that.mustRefresh = false;
                    }
                    /*修改dom*/
                    that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + that.mineData.log.site).removeClass('mine_work').find('span').remove();
                    /*因为是设置status = 0了，所以很多都是写死了，没关系*/
                    that.mineData[that.mineData.log.site].status = 0;
                    that.mineData[that.mineData.log.site].time = 0;
                    that.mineData.log = null;

                    player.updateinfo({
                        "silver": parseInt(res.all_silver)
                    });
                    if (parseInt(res.silver) > 0) {
                        if (appLanguage == 'kr') {
                            xGame.toast.show(lang('give_up_get_silver', [res.silver]));
                        } else {
                            xGame.toast.show(lang('give_up_get_silver') + res.silver);
                        }
                    } else {
                        xGame.toast.show(lang('no_more_mine'));
                    }

                    that.dialogHide();

                } else if (res.status == -1) {
                    xGame.toast.show(lang('no_mine_to_give_up'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('mine_can_get'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mine',
                'm': 'give_up'
            },
            Func);
            Func = null;
        },

        getSilver: function(id) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1 || res.status == 2) {
                    if (res.status == 2) {
                        that.mustRefresh = true;
                    } else {
                        that.mustRefresh = false;
                    }
                    /*修改dom*/
                    that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + id).removeClass('mine_work').find('span').remove();
                    /*因为是设置status = 0了，所以很多都是写死了，没关系*/
                    that.mineData[id].status = 0;
                    that.mineData[id].time = 0;
                    that.mineData.log = null;

                    player.updateinfo({
                        "silver": parseInt(res.all_silver)
                    });

                    that.dialogHide();
                    xGame.toast.show(lang('c_you_get_silver', [res.silver]));
                    if (player.info.onmine == 1) { //隐藏主城里气泡
                        player.info.onmine = 0;
                        $('#mine_building').children('.popo').hide();
                    }
                } else if (res.status == -1) {
                    xGame.toast.show(lang('no_mine'));
                } else if (res.status == -2) {
                    xGame.toast.show(lang('mine_not_belong_me'));
                } else if (res.status == -3) {
                    xGame.toast.show(lang('not_time_yet'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'mine',
                'm': 'get_silver',
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

            if (that.mustRefresh) {
                xGame.toast.show(lang('mine_lv_not_fit'));
                xGame.unlock();
                return;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    player.taskaction(3);
                    that.dialogHide();
                    miscpop.enterBattleFromMine(res, touid);
                    /*成功的话修改数据，直接在这里修改了*/
                    if (parseInt(res.info.win) > 0) {
                        /*反正刷新了，所以不用改
                            that.mineData[id].status = 2;
                            that.mineData[id].nickname = player.info.nickname;
                            that.mineData[id].time = res.info.dateline;
                            that.mineData[id].level = player.info.level;
                            that.mineData[id].get_silver = 0;
                            that.mineData.log = {'dateline':res.dateline,'page': that.page,'site': id,'type':type};
                            */
                        player.updateinfo({
                            "silver": parseInt(res.info.silver)
                        });
                    }

                    player.updateinfo({
                        "act": parseInt(res.info.act)
                    });

                    break;
                case - 1 : xGame.toast.show(lang('had_one_mine'));
                    break;
                case - 2 : xGame.toast.show(lang('can_not_mine_later'));
                    break;
                case - 3 : xGame.toast.show(lang('mine_lv_not_fit'));
                    break;
                case - 4 : xGame.buyActConfirm();
                    //xGame.toast.show('军令不足');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'mine',
                'm': 'zhanling',
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
            var that = this;
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
                switch (parseInt(res.status)) {
                case 1:
                    player.taskaction(3);
                    /*修改dom*/
                    that.e.find('#minepop_wrap').find('#mine_list').find('#mine' + id).addClass('mine_work').append('<span class="mine_owner" style="left:' + that.name_x + 'px;bottom:' + that.name_y + 'px;color:red;">' + player.info.nickname + '<em style="width:0"></em></span>');
                    if (id == 1) {
                        that.e.find('#mine' + id).find('.mine_owner').css({
                            'left': '13px',
                            'bottom': '-9px'
                        });
                    }
                    /*修改数据*/
                    that.mineData[id].status = 2;
                    that.mineData[id].nickname = player.info.nickname;
                    that.mineData[id].time = res.dateline;
                    that.mineData[id].level = player.info.level;
                    that.mineData[id].get_silver = 0;
                    that.mineData[id].country = res.country;
                    that.mineData.log = {
                        'dateline': res.dateline,
                        'page': that.page,
                        'site': id,
                        'type': type
                    };

                    player.updateinfo({
                        "act": parseInt(res.act)
                    });

                    that.dialogHide();
                    break;
                case - 1 : xGame.toast.show(lang('had_one_mine'));
                    break;
                case - 2 : xGame.toast.show(lang('mine_not_yours'));
                    break;
                case - 3 : xGame.buyActConfirm();
                    //xGame.toast.show('军令不足');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'mine',
                'm': 'caikuang',
                'p': that.page,
                'id': id,
                't': type
            },
            Func);
            Func = null;

        },

        /*通过page设置矿的位置*/
        buildMineByPage: function(page, list, log) {
            var that = this;
            list = $.isArray(list) ? list : helper.array_values(list || []);
            log = log || null;
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
            
            that.mineData = {};
            if (page == 1) {
                preNum = 3;
                x = 180;
                y = 15;
                _x = 230;
                _y = 175;
                that.name_x = -7;
                that.name_y = -34;
                for (var i = length - 1; i >= 0; i--) {
                    that.mineData[list[i].id] = list[i];
                    flag = list[i].friend == 0 ? '' : '<span class="flag"></span>';
                    if (list[i].time == undefined) {
                        that.mineData[list[i].id].time = 0;
                    }
                    if (i == 4) {
                        if (list[i].status == 0) {
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + '" style="left:388px;top:142px;" mine_id="' + list[i].id + '"></div>';
                        } else {
                            var plan = 3;
                            /*玩家收获进度的长度*/
                            plan = Math.floor(list[i].get_silver / list[i].silver * 157);
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + ' mine_work" style="left:390px;top:142px;" mine_id="' + list[i].id + '"><span class="mine_owner"' + (list[i].uid == player.info.uid ? ' style="color:red;"': '') + '>' + flag + list[i].nickname + '<em style="width:' + plan + 'px"></em></span></div>';
                        }
                    } else {
                        if (list[i].status == 0) {
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + '" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"></div>';
                        } else {
                            var plan = 3;
                            /*玩家收获进度的长度*/
                            plan = Math.floor(list[i].get_silver / list[i].silver * 157);
                            html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + ' mine_work" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"><span class="mine_owner" style="left:' + that.name_x + 'px;bottom:' + that.name_y + 'px;' + (list[i].uid == player.info.uid ? 'color:red;': '') + '">' + flag + list[i].nickname + '<em style="width:' + plan + 'px"></em></span></div>';
                        }
                    }
                }
            } else {
                preNum = 4;
                if (page == 2) {
                    x = 150;
                    y = 60;
                    _x = 180;
                    _y = 150;
                    that.name_x = -16;
                    that.name_y = -34;
                } else if (page == 3) {
                    x = 150;
                    y = 70;
                    _x = 180;
                    _y = 150;
                    that.name_x = -27;
                    that.name_y = -34;
                } else if (page >= 4) {
                    x = 150;
                    y = 80;
                    _x = 180;
                    _y = 150;
                    that.name_x = -30;
                    that.name_y = -34;
                }
                for (var i = length - 1; i >= 0; i--) {
                    that.mineData[list[i].id] = list[i];
                    flag = list[i].friend == 0 ? '' : '<span class="flag"></span>';
                    if (list[i].time == undefined) {
                        that.mineData[list[i].id].time = 0;
                    }
                    if (list[i].status == 0) {
                        html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + '" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"></div>';
                    } else {
                        var plan = 3;
                        /*玩家收获进度的长度*/
                        plan = Math.floor(list[i].get_silver / list[i].silver * 157);
                        console.log(plan + 'here');
                        html += '<div id="mine' + list[i].id + '" class="mine' + list[i].type + ' mine_work" style="left:' + (x + i % preNum * _x) + 'px;top:' + (y + Math.floor(i / preNum) * _y) + 'px;" mine_id="' + list[i].id + '"><span class="mine_owner" style="left:' + that.name_x + 'px;bottom:' + that.name_y + 'px;' + (list[i].uid == player.info.uid ? 'color:red;': '') + '">' + flag + list[i].nickname + '<em style="width:' + plan + 'px;"></em></span></div>';
                    }
                }
            }

            that.mineData.log = (!log || log.length === 0 || (typeof log === 'object' && Object.keys(log).length === 0)) ? null : log;
            that.e.find('#minepop_wrap').find('#mine_list').html(html);
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#mine_hide').unbind('touchend');
                this.e.find('#minepop_wrap').find('#mine_list').undelegate('div', 'touchend');
                this.e.find('#mine_tips').unbind('webkitAnimationEnd');
                this.e.remove();
                this.e = null;
            }
            if (this.pagebtnsDom) {
                this.pagebtnsDom.undelegate('#mine_prev', 'touchend');
                this.pagebtnsDom.undelegate('#mine_next', 'touchend');
                this.pagebtnsDom.undelegate('#mine_refresh', 'touchend');
                this.pagebtnsDom.remove();
                this.pagebtnsDom = null;
            }
            if (this.dialogDom) {
                this.dialogDom.undelegate('#minepop_dialog_hide', 'touchend');
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
                xGame.updateCd('mine', 0);

                this.resultdata = null;
                this.mineData = null;
                this.page = null;
                this.mustRefresh = null;

                this.name_x = null;
                this.name_y = null;
            }
            this.unbindAll();
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            player.reloadInfo();
        },
    };
})();