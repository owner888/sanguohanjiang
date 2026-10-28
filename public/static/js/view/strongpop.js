/* 强化装备 */
(function() {
    strongPopClass = function() {
        this.e;
        this.isshow = false;
        this.strongList = {};
        this.ulList; //左边容器
        this.sid; //所选择的id
        this.useGold; //元宝补全1为元宝0为银币
        /*
        this.t = null;
        this.timeseconds = 0;
        */
        this.gold; //需要元宝数，rule中
        this.silver; //需要银币数，rule中

        this.cd = false;
        this.step = 0;
        this.isTimeout = null;
    };
    strongPopClass.prototype = {
        init: function(step) {
            var that = this;
            that.step = step;

            var initRender = function(res) {

                if (!res || res.status == -1) {
                    xGame.toast.show(lang('you_have_no_item'));
                    $('#worldloading').hide();
                    xGame.unlock();
                    return;
                }

                //保存数据
                that.strongList = res.list;

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                that.e = xGame.loadPopTmpl('strong');
                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    strongpop.hide();
                    e.preventDefault();
                });

                /* 第二步 构建数据列表等 */
                var html = '<li class="cs_item strongpop_list">';
                var n = 0;
                for (var i in res.list) {
                    if (n > 0 && n % 9 == 0) { //下一页了
                        html += '</li><li class="cs_item strongpop_list">';
                    }
                    var item = res.list[i];
                    html += '<div id="strongitem' + item.id + '" class="strongpop_listitem c_i_bj' + item.quality + '" itemid="' + item.id + '">' + (item.level != 0 ? '<span>' + item.level + '</span>': '') + '<div class="itempic" style="background-image:url(' + xGame.itemurl(item.equipid) + ');' + '"></div><div class="hammer hammer' + item.hammer_quality + '">' + (item.hammer == 0 ? '' : item.hammer) + '</div>' + (item.generalname != undefined ? '<p class="name">' + item.generalname + '</p>': '') + '</div>';

                    n++;
                }
                /* 补空格 */
                var repeat_s = '<div class="strongpop_listitem c_i_bj1"><div class="itempic"></div></div>';
                if (n == 0) { //神马也没有
                    html += helper.str_repeat(repeat_s, 9);
                } else if (n % 9 == 0) {
                    html += '';
                } else {
                    html += helper.str_repeat(repeat_s, 9 - n % 9);
                }

                html += '</li>';

                that.ulList = that.e.find('#ul_list');
                /*给左边容器赋值*/

                that.ulList.html(html);

                /* 创建旋转门 domid, 单页宽度 */
                that.myScroll = xGame.carousel('stronglist_carousel', 450);

                /* 绑定事件(必须在旋转门之后) */
                that.ulList.undelegate('.strongpop_listitem', 'click').delegate('.strongpop_listitem', 'click',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    if ($(this).attr('itemid') == undefined || that.sid == $(this).attr('itemid')) {
                        return;
                    }
                    that.ulList.find('#strongitem' + that.sid).removeClass('selected');
                    $(this).addClass('selected');
                    that.sid = $(this).attr('itemid');
                    that.viewItem();
                });

                /*右边数据*/
                //获取数组第一条数据，通用方法
                var rightdata = helper.array_first(res.list);
                if (rightdata) {
                    that.sid = rightdata.id;
                    $('#strongitem' + that.sid).addClass('selected');
                }
                res.info = res.info || {};
                res.info.info = res.info.info || res.info;
                res.info.rule = res.info.rule || { rate: 100, silver: 1500, gold: 0 };
                res.info.cd = res.info.cd || { status: 0, cd: 0 };
                that.fillRightInfo(res.info.info);
                that.fillRightRule(res.info.rule);
                xGame.updateCd('strong', res.info.cd.cd || 0);
                if (res.info.cd.status == 1) {
                    that.cd = true;
                    $('#strong_cd').addClass('cd');
                }
                //that.fillRightCd(res.info.cd);

                /*绑定强化按钮*/
                that.e.find('#strongpop_strongbtn').unbind('touchend').bind('touchend',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    that.strongFunc();
                });
                /*绑定选择按钮*/
                that.e.find('.iteminfo_needmoney').unbind('touchend').bind('touchend',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    that.e.find('#sss1').toggle();
                });
                /* 绑定清CD按钮 */
                that.e.find('#clear_btn').unbind('touchend').bind('touchend',
                function() {
                    if (CD['strong'].s <= 0) {
                        return false;
                    }
                    that.qh();
                });

                /*绑定强化成就按钮*/
                if(player.info.level >= 160) {
                    that.e.find('#strong_achieve').show();
                    that.e.find('#strong_achieve').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) { //不设置属性
                            return false;
                        }

                        that.hide();
                        xGame.showpop('strongachieve');
                        e.preventDefault();
                    });
                }

                /* 最后 展示弹窗 */
                that.e.show();
                $('#worldloading').hide();
                xGame.unlock();
                xGame.showpop('system', {
                    'pop': 'strong',
                    'callback': function () {
                        that.guideShow();
                    }
                });

            };
            if (xGame.checkLock()) {
                return false;
            }
            loadContents({
                'c': 'strengthen',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        guideShow: function () {

            var that = this;

            /* 新手引导 */
            if (xGame.guide) {
                switch (guide.now) {
                    case 861:
                        guide.show(1410);
                        break;
                    case 1901:
                        guide.show(1902);
                        break;
                }
            }

            // 引导续接
            if (that.step) {
                if (miscpop.in_array(that.step, [1421, 1422, 1423, 1424, 1425, 1426, 1427, 1428, 1429])) {
                    that.e.find('.strongpop_listitem:eq(1)').trigger('click'); // 选中防具
                }
                guide.show(that.step);
            }
        },

        qh: function() {
            var that = this;
            var golds = Math.ceil(CD['strong'].s / 300);
            xGame.confirm.show(lang('notice'), lang('clear_ask', [golds]), 2,
            function() {
                xGame.confirm.hide(function() {
                    var clearRender = function(res) {
                        switch (parseInt(res.status)) {
                        case - 1 : xGame.toast.show(lang('no_need_to_clear_cd'));
                            break;
                        case - 2 : xGame.goPay(lang('no_gold_clear_cd'));
                            break;
                        case - 3 : xGame.toast.show(lang('clear_fail_please_wait'));
                            break;
                        case 1:
                            //xGame.toast.show('清除成功');
                            that.cd = false;
                            xGame.updateCd('strong', 0);
                            player.updateinfo({
                                'gold': parseInt(res.all_gold)
                            });
                            break;
                        }
                        clearRender = null;
                    }
                    loadContents({
                        'c': 'strengthen',
                        'm': 'clear_cd',
                        'id': strongpop.sid,
                        'type': '1'
                    },
                    clearRender);
                    clearRender = null;
                });
            },
            function() {
                xGame.confirm.hide();
            });
        },

        viewItem: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            var func = function(res) {
                if (!res || res.status != 1) {
                    xGame.toast.show(lang('can_not_get_equip_list_data'));
                    return;
                }
                that.fillRightInfo(res.info);
                that.fillRightRule(res.rule);
                xGame.unlock();
            };
            loadContents({
                'c': 'strengthen',
                'm': 'strengthen_info',
                'id': that.sid
            },
            func); //加载数据
            func = null;
        },

        strongFunc: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            if (that.e.find('#sss1').is(":visible")) {
                that.useGold = 1;
            } else {
                that.useGold = 0;
            }

            var func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 :
                    //xGame.toast.show('请先清除冷却时间');
                    that.qh();
                    break;
                case - 2 : xGame.toast.show(lang('equip_not_exist'));
                    break;
                case - 3 : xGame.toast.show(lang('strong_lv_full'));
                    break;
                case - 4 : xGame.toast.show(lang('no_silver_to_strong'));
                    break;
                case - 5 : xGame.goPay(lang('no_gold_to_strong'));
                    break;
                case - 6 : that.createResult(2);
                    if (res.cd >= 60 * 30) {
                        that.cd = true;
                    }
                    xGame.updateCd('strong', res.cd);
                    player.updateinfo({
                        'silver': parseInt(res.all_silver)
                    });
                    //TODO 还需要处理金钱方面的
                    //xGame.toast.show('强化失败');
                    break;
                case 1:
                    that.createResult(1, res.baoji);
                    //xGame.toast.show('强化成功');
                    if (res.cd >= 60 * 30) {
                        that.cd = true;
                    }
                    xGame.updateCd('strong', res.cd);
                    //that.countDown(res.cd);
                    that.e.find('#iteminfo_p').find('#st_lv').html(res.newlevel);
                    if (res.newlevel > 1) {
                        that.ulList.find('#strongitem' + that.sid).find('span').html(res.newlevel);
                    } else {
                        that.ulList.find('#strongitem' + that.sid).append('<span>1</span>');
                    }

                    that.e.find('#iteminfo_p').find('#atk_num').html(res.newpower);
                    that.e.find('#now_percent').html(res.rate + '%');
                    that.e.find('#need_gold').html(res.gold);
                    that.e.find('#strong_cost').html(res.silver);
                    if (that.useGold == 1) {
                        player.updateinfo({
                            'gold': parseInt(res.all_gold)
                        });
                    }
                    player.updateinfo({
                        'silver': parseInt(res.all_silver)
                    });
                    that.silver = res.silver;
                    player.taskaction(8);
                    /* 新手引导 */
                    xGame.showGuide([1410, 1411, 1412, 1413, 1414, 1415, 1416, 1417, 1418, 1419, 1420, 1421, 1422, 1423, 1424, 1425, 1426, 1427, 1428, 1429, 1902, 1903, 1904, 1905, 1906, 1907, 1908, 1909, 1910, 1911, 1912, 1913, 1914, 1915, 1916, 1917, 1918, 1919, 1920, 1921, 1922]);
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c': 'strengthen',
                'm': 'strengthen_start',
                'id': that.sid,
                'ratetype': that.useGold
            },
            func);
            func = null;
        },

        fillRightInfo: function(info) {
            var that = this;
            info = info || { quality: 1, equipid: 1, name: '装备', type: 1, needlevel: 1, level: 1, powerup: 50, gid: 0, gidname: '' };
            that.e.find('.itembox').attr('class', 'itembox c_i_bj' + (info.quality || 1)).children('span').css({
                'background': 'url(' + xGame.itemurl(info.equipid || 1) + ') 50% 50% no-repeat;'
            });
            that.e.find('#iteminfo_p').find('h4').text(info.name || '');
            that.e.find('#iteminfo_p').find('h4').css({
                'color': helper.getItemColor(info.quality || 1)
            });
            that.e.find('#iteminfo_p').find('#type').html(helper.getItemVocation(parseInt(info.type || 1)));
            that.e.find('#iteminfo_p').find('#eq_lv').html(info.needlevel || 1);
            that.e.find('#iteminfo_p').find('#st_lv').html(info.level || 1);
            that.e.find('#iteminfo_p').find('#atk').html(helper.getItemPowerName(parseInt(info.type || 1)) + '：<span id="atk_num">' + (info.powerup || 0) + '</span>');
            if (info.gid > 0) {
                that.e.find('#eq_name').html(info.gidname + lang('is_equip')).css('display', 'block');
            } else {
                that.e.find('#eq_name').html('').css('display', 'none');
            }
        },

        fillRightRule: function(rule) {
            var that = this;
            /*初始化*/
            that.useGold = 0;
            that.silver = rule.silver;
            that.gold = rule.gold;
            that.e.find('#sss1').css('display', 'none');
            /*填充*/
            that.e.find('#now_percent').html(rule.rate + '%');
            that.e.find('#strong_cost').html(rule.silver);
            that.e.find('#need_gold').html(rule.gold);

        },

        /* 强化结果 */
        createResult: function(type, baoji) {

            var html = $('<div class="strong_result_wrap"></div>');

            var movieclip;

            var cfg = {
                "x": 176,
                "y": -50
            };
            var that = this;
            var callback = function() {
                that.isTimeout = setTimeout(function() {
                    html.fadeOut(500, function() {
                        html.remove();
                    });
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
            this.e.find('#strongpop_wrap').append(html);
            movieclip.play();
            movieclip = null;
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#strongpop_strongbtn').unbind('touchend');
                this.e.find('.iteminfo_needmoney').unbind('touchend');
                this.e.find('#clear_btn').unbind('touchend');
                this.e.find('#strong_achieve').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.ulList) {
                this.ulList.undelegate('.strongpop_listitem', 'click');
                this.ulList.remove();
                this.ulList = null;
            }
        },

        hide: function() {
            xGame.unlock();
            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {//如果可以
                window.plugins.localNotification.cancel('strong');
                if (CD['strong'] != undefined && CD['strong'].s > 300) {//如果有最近的训练中的武将
                    var time = Math.round(new Date().getTime()/1000) + CD['strong'].s;//当前时间加上cd时间
                    window.plugins.localNotification.add({
                        date: time, // your set date object
                        message: lang('practice_clock_end'),
                        badge: 1,
                        id: 'strong',
                        sound:'sub.caf'
                    });
                }
            }
            
            xGame.updateCd('strong', 0);
            
            this.isshow = false;
            this.step = 0;
            if (this.myScroll) {
                this.myScroll.destroy();
                delete this.myScroll;
            }
            this.cd = false;
            this.strongList = this.sid = this.silver = this.gold = null;
            if (xGame.nowWorld == 'country' && countryWorld != undefined) {
                countryWorld.showLeftBar();
            }
            if (this.isTimeout) {
                clearTimeout(this.isTimeout);
                this.isTimeout = null;
            }
            this.unbindAll();
            player.reloadInfo();
        },
    };
})();