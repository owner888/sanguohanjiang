/* 推塔 */
(function() {
    generaltaskWorldClass = function() {
        this.e;
        this.isshow = false;
        this.dom_world;
        this.generalid = 0;
        this.number = 0;//剩余次数
        this.dom_list = null;
        this.list_dom; //列表dom
        this.dom_x = 415; //要移动奖励到达的x坐标
        this.dom_y = 125; //要移动奖励到达的y坐标
        this.startX = 0; //开始移动的x坐标
        this.startY = 0; //开始移动的y坐标
        this.newX = 0; //移动后的x坐标
        this.newY = 0; //移动后的y坐标
        this.site = 0; //武将位是哪个位子的将领
        this.bmove; //8个武将的位置
        this.said = 0; //押注倍率
        this.saprice = 0;//押注花费
        this.page = 0; //当前所在分页
        this.perpage = 0; //总页数
        this.mid = 0; //关卡id
        this.isTimeout = null;
    };
    generaltaskWorldClass.prototype = {
        init: function(data) {
            var that = this;
            var render = function(res) {
                switch (res.status) {
                case - 1 : xGame.toast.show(lang('lv71_open'));
                    xGame.loadWorld('city');
                    break;
                case - 2 : xGame.toast.show(lang('get_hero_info_fail'));
                    xGame.loadWorld('city');
                    break;
                case 1:
                    if (that.generalid == 0 && typeof data != 'undefined') {
                        that.generalid = data.generalid;
                    }
                    that.preparePage();
                    that.buildGeneralList(res);
                    //判断打开列表页
                    if (typeof data != 'undefined') {
                        that.page = data.page;
                        that.openMissionPage();
                    }
                    break;
                }
            }

            //加载数据
            loadContents({
                'c': 'generaltask',
                'm': 'index'
            },
            render, false,
            function() {
                xGame.loadWorld('city');
            });
            render = null;
        },

        preparePage: function() {
            var that = this;
            //已经准备好
            if (that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('generaltaskpop_tmpl'));
            that.e = $('#generaltaskpop');
            that.e.find('#generaltask_gold').html(player.info.gold);
            that.e.find('#generaltask_soul').html(player.info.soul);
            //绑定获取列表按钮
            that.e.find('#get_list').unbind('touchend').bind('touchend',
            function() {
                if (that.generalid) {
                    that.openMissionPage();
                } else {
                    xGame.toast.show(lang('chose_one_to'));
                }
            });
            //绑定购买按钮
            that.e.find('#buy_btn').unbind('touchend').bind('touchend',
            function() {
                that.buytimesConfirm();
            });
            //绑定关闭
            that.e.find('#starway_hide').unbind('touchend').bind('touchend',
            function(e) {
                xGame.loadWorld('city');
                e.preventDefault();
                e.stopPropagation();
            });

            $('#worldloading').removeClass('black').hide();

        },

        //初始化页面信息
        buildGeneralList: function(res) {
            var that = this;
            var html = '';
            that.number = parseInt(res.number);
            that.bmove = {
                1 : {
                    'left': 41,
                    'top': 33
                },
                2 : {
                    'left': 169,
                    'top': 33
                },
                3 : {
                    'left': 41,
                    'top': 157
                },
                4 : {
                    'left': 169,
                    'top': 157
                },
                5 : {
                    'left': 41,
                    'top': 281
                },
                6 : {
                    'left': 169,
                    'top': 281
                },
                7 : {
                    'left': 41,
                    'top': 403
                },
                8 : {
                    'left': 169,
                    'top': 403
                }
            };
            var generalIsset = 0;
            var box = '';
            for (var i = 0; i < res.list.length; i++) {
                box += '<span class="box q' + res.list[i].quality + '" style="left:' + that.bmove[i + 1].left + 'px;top:' + that.bmove[i + 1].top + 'px;"></span>'
                html += '<p class="team team' + (i + 1) + '" team="' + (i + 1) + '" value="' + res.list[i].id + '" name="' + res.list[i].name + '" style="background:url(' + xGame.avatar(res.list[i].image) + ') center center no-repeat;left:' + that.bmove[i + 1].left + 'px;top:' + that.bmove[i + 1].top + 'px;"></p>';
                if (res.list[i].id == that.generalid) {
                    var moveclass = "team" + (i + 1);
                    generalIsset = 1;
                }
            }
            that.e.find('.generallist').html(box + html);
            that.e.find('.number').find('em').html(this.number);
            html = null;
            box = null;
            //有默认的上阵将领id
            if (generalIsset == 1) {
                var name = that.e.find('.generallist').find('.' + moveclass).attr('name');
                that.startX = that.e.find('.generallist').find('.' + moveclass).offset().left;
                that.startY = that.e.find('.generallist').find('.' + moveclass).offset().top - 60;
                that.e.find('.generallist').find('.' + moveclass).css({
                    'left': that.dom_x + 'px'
                });
                that.e.find('.generallist').find('.' + moveclass).css({
                    'top': that.dom_y + 'px'
                });
                that.e.find('.generalname').html(name);
            }
            
            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;
            
            that.list_dom = that.e.find('.generallist');
            that.list_dom.undelegate('.team', 'touchstart').delegate('.team', 'touchstart',
            function(event) {
                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);
                //如果这个已经选中
                if (that.generalid != 0 && that.generalid == $(this).attr('value')) {
                    that.startX = that.dom_x;
                    that.startY = that.dom_y;
                } else {
                    that.startX = that.bmove[$(this).attr('team')].left;
                    that.startY = that.bmove[$(this).attr('team')].top;
                }
                
                
                $(this).css({
                    'z-index': 100
                }).addClass('ismoving');
                
                target = $(this);
            });
            
            that.e.unbind('touchmove').bind('touchmove',function(e) {
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);
                    //that.newX = that.bmove[$(this).attr('team')].left + nX - ox;
                    //that.newY = that.bmove[$(this).attr('team')].top + nY - oy;
                    that.newX = that.startX + nX - ox;
                    that.newY = that.startY + nY - oy;
                    target.css({
                        'left': that.newX + 'px',
                        'top': that.newY + 'px'
                    });
                }
            });
            
            that.list_dom.undelegate('.team.ismoving', 'touchend').delegate('.team.ismoving', 'touchend',
            function(event) {
                target = null;
                if (that.newX >= that.dom_x - 150 && that.newX <= that.dom_x + 150 && that.newY >= that.dom_y - 150 && that.newY <= that.dom_y + 150) {
                    that.newX = that.dom_x;
                    that.newY = that.dom_y;
                    if (that.site) {
                        that.e.find('.generallist').find('.team' + that.site).css({
                            'left': that.bmove[that.site].left + 'px'
                        });
                        that.e.find('.generallist').find('.team' + that.site).css({
                            'top': that.bmove[that.site].top + 'px'
                        });
                    }
                    that.site = $(this).attr('team');
                    that.generalid = $(this).attr('value');
                    that.e.find('.generalname').html($(this).attr('name'));
                } else {
                    that.newX = that.bmove[$(this).attr('team')].left;
                    that.newY = that.bmove[$(this).attr('team')].top;
                    if (that.site == $(this).attr('team')) {
                        that.site = 0;
                        that.generalid = 0;
                        that.e.find('.generalname').html(lang('hero_box_block'));
                    }
                }
                $(this).css({
                    'z-index': 0
                }).removeClass('ismoving');
                $(this).css({
                    'left': that.newX + 'px'
                });
                $(this).css({
                    'top': that.newY + 'px'
                });
            });
        },

        //准备关卡列表界面
        openMissionPage: function() {
            $('#ajaxloading').show();
            var that = this;

            if (that.dom_list == null) {
                that.dom_list = xGame.loadPopTmpl('generaltasklist');
                //组建任务列表
                that.bulidMissionList(that.page);
                //绑定关闭按钮
                that.dom_list.find('.backbtn').unbind('touchend').bind('touchend',
                function() {
                    that.dom_list.hide();
                    return;
                });
                //绑定介绍按钮
                that.dom_list.find('.content').find('#ante').unbind('touchend').bind('touchend',
                function() {
                    that.dom_list.find('.ante_desc').css({
                        'display': 'block'
                    });
                });
                //绑定介绍关闭按钮
                that.dom_list.find('.ante_desc').find('.ante_backbtn').unbind('touchend').bind('touchend',
                function() {
                    that.dom_list.find('.ante_desc').css({
                        'display': 'none'
                    });
                });
                //绑定购买按钮
                that.dom_list.find('#buy_btn').unbind('touchend').bind('touchend',
                function() {
                    that.buytimesConfirm();
                });
                //绑定分页
                that.dom_list.find('#generaltask_page_btn1').unbind('touchend').bind('touchend',
                function() {
                    //首页
                    that.page = 1;
                    that.bulidMissionList(that.page);
                });
                that.dom_list.find('#generaltask_page_btn2').unbind('touchend').bind('touchend',
                function() {
                    //上一页
                    if (that.page <= 1) {
                        that.page = 1;
                    } else {
                        that.page = that.page - 1;
                    }
                    that.bulidMissionList(that.page);
                });
                that.dom_list.find('#generaltask_page_btn3').unbind('touchend').bind('touchend',
                function() {
                    //下一页
                    that.page = that.page + 1;
                    if (that.page > that.perpage) {
                        that.page = that.perpage;
                    }
                    that.bulidMissionList(that.page);
                });
                that.dom_list.find('#generaltask_page_btn4').unbind('touchend').bind('touchend',
                function() {
                    //尾页
                    that.page = that.perpage;
                    that.bulidMissionList(that.page);
                });
            }

            that.dom_list.find('.number').find('em').html(that.number);
            //显示界面
            $('#ajaxloading').hide();
            that.dom_list.show();
        },

        //组建任务列表
        bulidMissionList: function(page) {
            $('#ajaxloading').show();
            var that = this;

            //重置押注状态
            that.said = 0;
            that.saprice = 0;

            var render = function(res) {
                switch (res.status) {
                case - 1 : xGame.toast.show(lang('lv_to_low_to_func'));
                    break;
                case 1:
                    that.perpage = res.perpage;
                    that.page = res.page;
                    that.dom_list.find('#generaltask_page_no').html(that.page);
                    var html = '';
                    for (var i = 0; i < res.list.length; i++) {
                        html += '<li>';
                        if (res.list[i].pass) {
                            //加上击败的戳
                            html += '<em class="list_pass"></em>';
                        }
                        if (res.list[i].open == 0) {
                            html += '<span class="locked"></span>';
                            html += '<em class="list_mask"></em>';
                        }
                        html += '<p class="avater" style="background:url(' + xGame.avatar(res.list[i].avatar) + ') center center no-repeat;"><em>Lv.' + res.list[i].level + '</em></p>';
                        html += '<p class="mission_name">' + lang('checkpoints') + res.list[i].id + '：<em>' + res.list[i].name + '</em></p>';
                        
                        // 是否首杀
                        if (parseInt(res.list[i].firstkill) == 1) {
                            html += '<div class="first_kill"></div>';
                            html += '<p class="reward type'+ res.list[i].first_type +'">' + lang('drop') + '<em class="reward_value">x' + res.list[i].first_value + '</em></p>';
                        } else {
                            html += '<p class="mission_ante ante1" value="1" mid="' + res.list[i].id + '" price="' + res.price[1] + '">' + lang('ten_times_bet') + ':<em>' + res.price[1] + lang('gold') + '</em></p>';
                            html += '<p class="mission_ante ante2" value="2" mid="' + res.list[i].id + '" price="' + res.price[2] + '">' + lang('hundred_times_bet') + ':<em>' + res.price[2] + lang('gold') + '</em></p>';
                            html += '<p class="reward type'+ res.list[i].re_type +'">' + lang('drop') + '<em class="reward_value">x' + res.list[i].soul + '</em></p>';
                        }

                        html += '<button class="btn pop_btn_small report" value="' + res.list[i].id + '" name="' + res.list[i].name + '">' + lang('raiders') + '</button>';
                        html += '<button class="btn pop_btn_middle action" value="' + res.list[i].id + '">' + lang('attack') + '</button>';
                        html += '</li>';
                    }
                    that.dom_list.find('#mission_list_scroller').html(html);
                    //绑定攻略按钮
                    that.dom_list.find('#mission_list_scroller').find('.report').unbind('touchend').bind('touchend',
                    function() {
                        xGame.showpop('strategy', {
                            "name": $(this).attr('name'),
                            "c": "generaltask",
                            "m": "get_report_list",
                            "id": $(this).attr('value')
                        });
                    });
                    //绑定开战按钮
                    that.dom_list.find('#mission_list_scroller').find('.action').unbind('touchend').bind('touchend',
                    function() {
                        var missionid = $(this).attr('value');
                        that.action(missionid);
                    });
                    //绑定押注按钮
                    that.dom_list.find('#mission_list_scroller').find('.mission_ante').unbind('touchend').bind('touchend',
                    function() {
                        var dom_info = $(this);

                        if (that.said != dom_info.attr('value')) {
                            update_selected();
                        } else if (that.mid != dom_info.attr('mid')) {
                            update_selected();
                        } else {
                            if (parseInt(that.said) > 0) {
                                that.dom_list.find('#mission_list_scroller').find('.ante' + that.said).removeClass('selected');
                            }
                            that.said = 0;
                            that.saprice = 0;
                        }

                        //修改选中状态
                        function update_selected() {
                            if (parseInt(that.said) > 0) {
                                that.dom_list.find('#mission_list_scroller').find('.ante' + that.said).removeClass('selected');
                            }
                            dom_info.addClass('selected');
                            that.said = dom_info.attr('value');
                            that.mid = dom_info.attr('mid');
                            that.saprice = dom_info.attr('price');
                        }
                    });
                    break;
                }
                $('#ajaxloading').hide();
            }
            //加载数据
            loadContents({
                'c': 'generaltask',
                'm': 'get_list',
                'p': page
            },
            render, true);
            render = null;
        },

        //开始战斗
        action: function(missionid, force) {

            if(this.number <= 0) {
                this.buytimesConfirm(true);
                return;
            }

            $('#ajaxloading').show();
            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 8 : xGame.toast.show(lang('gold_bet_not_enough'));
                    $('#ajaxloading').hide();
                    break;
                case - 7 : that.buytimesConfirm(true);
                    $('#ajaxloading').hide();
                    break;
                case - 6 : xGame.toast.show(lang('checkpoints_close'));
                    $('#ajaxloading').hide();
                    break;
                case - 5 : xGame.toast.show(lang('get_user_info_fail'));
                    $('#ajaxloading').hide();
                    break;
                case - 4 : xGame.toast.show(lang('get_checkpoints_info_fail'));
                    $('#ajaxloading').hide();
                    break;
                case - 3 : xGame.toast.show(lang('get_hero_info_fail'));
                    $('#ajaxloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('checkpoints_chose_fail'));
                    $('#ajaxloading').hide();
                    break;
                case - 1 : xGame.toast.show(lang('hero_gate_close'));
                    $('#ajaxloading').hide();
                    break;
                case 0:
                    xGame.toast.show(lang('can_not_attack'));
                    $('#ajaxloading').hide();
                    break;
                case 1:
                    var temp_gid = that.generalid;
                    var temp_page = that.page;
                    that.isTimeout = setTimeout(function() {
                        xGame.loadBattle(res,
                        function() {
                            miscpop.battleResultFromGeneraltask(res, temp_gid, temp_page, missionid, type);
                        },
                        'generaltask');
                        $('#ajaxloading').hide();
                    },
                    500);
                    that.number = parseInt(res.info.number);
                    player.updateinfo({
                        'soul': parseInt(res.info.all_soul),
                        'gold': parseInt(res.info.gold)
                    });
                    that.e.find('.number').find('em').html(that.number);
                    if (that.dom_list != null) {
                        that.dom_list.find('.number').find('em').html(that.number);
                    }
                    break;
                }
            }
            type = 0;
            if (that.mid == missionid) {
                type = that.said;
            }

            if(type > 0 && typeof force == 'undefined') {
                xGame.confirm.show(lang('notice'), lang('generaltask_confirm', [that.saprice]), 2,
                function() {
                    xGame.confirm.hide();
                    that.action(missionid, true);
                },
                function() {
                    xGame.confirm.hide();
                },
                lang('ok'), lang('close'));
                $('#ajaxloading').hide();
                return false;
            }

            loadContents({
                'c': 'generaltask',
                'm': 'action',
                'id': missionid,
                'type': type,
                'gid': that.generalid
            },
            Func, true);
            Func = null;
        },

        /* 购买次数 价格确认框 */
        buytimesConfirm: function(onlyone) {
            var that = this;
            var priceRender = function(res) {
                if (res.status == 1 && res.price > 0) {
                    xGame.confirm.show(lang('notice'), lang('cost_gold_buy_times', [res.price, res.times]), 2,
                    function() {
                        that.buytimesProcess(onlyone);
                    },
                    function() {
                        xGame.confirm.hide();
                    },
                    lang('buy'), lang('close'));
                } else {
                    xGame.toast.show(lang('can_not_buy_times_yet'));
                    return false;
                }
            };
            loadContents({
                'c': 'generaltask',
                'm': 'buy_number',
                't': 0
            },
            priceRender);
            priceRender = null;
        },

        /* 开始购买次数 */
        buytimesProcess: function(onlyone) {
            var that = this;
            var buyRender = function(res) {
                if (res.status == 1) {
                    that.number = parseInt(res.times);
                    player.updateinfo({
                        "gold": res.all_gold
                    });
                    $('#buy_arenatimes_price').html(res.price);
                    $('#buy_arenatimes').html(that.number);
                    that.e.find('.number').find('em').html(that.number);
                    that.e.find('#generaltask_gold').html(res.all_gold);
                    player.updateinfo({
                        'gold': parseInt(res.all_gold)
                    });
                    if (that.dom_list != null) {
                        that.dom_list.find('.number').find('em').html(that.number);
                    }

                    if(onlyone == true) {
                        xGame.confirm.hide();
                    }
                } else if (res.status == -1) {
                    xGame.goPay(lang('gold_not_enough_for_buying'));
                } else {
                    xGame.toast.show(lang('buy_fail_try_later'));
                }
            };
            loadContents({
                'c': 'generaltask',
                'm': 'buy_number',
                't': 1
            },
            buyRender);
            buyRender = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#get_list').unbind('touchend');
                this.e.find('#buy_btn').unbind('touchend');
                this.e.find('#starway_hide').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.remove();
                this.e = null;
            }
            if (this.dom_list) {
                this.dom_list.find('.backbtn').unbind('touchend');
                this.dom_list.find('.content').find('#ante').unbind('touchend');
                this.dom_list.find('.ante_desc').find('.ante_backbtn').unbind('touchend');
                this.dom_list.find('#buy_btn').unbind('touchend');
                this.dom_list.find('#generaltask_page_btn1').unbind('touchend');
                this.dom_list.find('#generaltask_page_btn2').unbind('touchend');
                this.dom_list.find('#generaltask_page_btn3').unbind('touchend');
                this.dom_list.find('#generaltask_page_btn4').unbind('touchend');
                this.dom_list.find('#mission_list_scroller').find('.report').unbind('touchend');
                this.dom_list.find('#mission_list_scroller').find('.action').unbind('touchend');
                this.dom_list.find('#mission_list_scroller').find('.mission_ante').unbind('touchend');
                this.dom_list.remove();
                this.dom_list = null;
            }
            if (this.list_dom) {
                this.list_dom.undelegate('.team', 'touchstart');
                this.list_dom.undelegate('.team.ismoving', 'touchend');
                this.list_dom.remove();
                this.list_dom = null;
            }
        },

        clear: function() {
            xGame.unlock();
            
            this.generalid = 0;
            this.page = 0;
            this.said = 0;
            this.saprice = 0;
            this.perpage = 0;
            this.mid = 0;
            this.number = 0;
            this.unbindAll();
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
        },
    };
})();