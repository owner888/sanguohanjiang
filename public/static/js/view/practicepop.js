/* 校场 */
(function() {
    practicePopClass = function() {
        this.e;
        this.isshow = false;

        this.leftDom; //左侧hero列表
        this.ruleDom; //规则模板
        this.practiceListDom; //训练位列表
        this.practiceScroll; //强化等级滚动
        this.heroDom; //英雄信息的dom
        this.rightTop1Dom; //开始训练
        this.rightTop2Dom; //训练中
        this.rightTop3Dom; //转生
        this.heroData; //所有的武将信息,以武将id为键
        this.shid; //目前所选武将的id
        this.placeData; //当前有武将训练位的数据，以英雄d为键
        this.stype; //选中的训练类型
        this.blockPlace; //空的的训练位置
        this.unlockPlace; //未解锁位置
        this.spid; //当前选中训练位
        this.leap; //突飞需要的元宝

        this.freeTimes; //免费次数

        this.rtList; //时间集合,键是英雄位置，值是英雄的冷却时间
        this.minData; //最小时间的武将的数据
        this.maxSec; //最大的秒数名字
        this.step = 0;  // 引导续接的步数

        this.practiceleapforce_list;

        this.isTimeout = [];
    };
    practicePopClass.prototype = {
        init: function(step) {
            var that = this;
            that.step = step;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_practice_list_fail'));
                    $('#worldloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('get_practice_rule_fail'));
                    $('#worldloading').hide();
                    break;
                case - 4 : xGame.toast.show(lang('leap_times_reach_limit'));
                    $('#worldloading').hide();
                    break;
                case 2:
                    xGame.toast.show(lang('no_hero_chuzhen'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    that.freeTimes = parseInt(res.freetimes);
                    that.practiceleapforce_list = res.mop;/* 扫荡次数列表 */
                    that.fillData(res.list, res.place, res.rule, res.leap);

                    xGame.showpop('system', {
                        'pop': 'practice',
                        'callback': function () {
                            that.guideShow();
                        }
                    });
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    break;
                }
            };
            loadContents({
                'c':
                'practice',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        guideShow: function () {

            var that = this;

            /* 新手引导判断*/
            if (xGame.guide) {
                if (guide.now == 501) {
                    guide.show(1201);
                } else if (guide.now == 1308) {
                    guide.show(1211);
                }
            }

            // 引导续接
            if (that.step) {

                // 如果是在对周仓进行突飞，则触发触摸事件
                if (miscpop.in_array(that.step, [1202, 1203, 1204, 1205, 1206, 1207, 1208, 1209])) {
                    var nexthero = $('#left_dom').find('.hero:eq(1)');
                    nexthero.trigger('touchend');
                    nexthero = null;
                // 如果是在对张梁进行突飞，则触发触摸事件
                } else if (miscpop.in_array(that.step, [1212, 1213, 1214, 1215, 1216, 1217, 1218, 1219])) {
                    var nexthero = $('#left_dom').find('.hero:eq(2)');
                    nexthero.trigger('touchend');
                    nexthero = null;
                }

                guide.show(that.step);

                if (miscpop.in_array(that.step, [1204, 1205, 1206, 1207, 1208, 1214, 1215, 1216, 1217, 1218])) {
                    $('#start_practice').trigger("touchend");
                }

            }
        },

        fillData: function(hero, place, rule, leap) {
            var that = this;

            that.e = xGame.loadPopTmpl('practice');
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                practicepop.hide();
                e.preventDefault();
            });

            var html1 = html2 = html3 = '';

            that.rightTop1Dom = that.e.find('#top1');
            that.rightTop2Dom = that.e.find('#top2');
            that.rightTop3Dom = that.e.find('#top3');
            /*先处理hero*/
            that.leftDom = that.e.find('#left_dom');

            that.heroData = {};
            that.rtList = {};
            var num = 0;
            for (var i = 1; i <= 8; i++) {
                if (hero[i] != undefined) {

                    num++;

                    that.heroData[hero[i].id] = hero[i];
                    /*这个是新添加的两个属性*/
                    that.heroData[hero[i].id]['pid'] = 0;
                    that.heroData[hero[i].id]['cd'] = 0;
                    that.heroData[hero[i].id]['num'] = i;
                    /*根据英雄数创建时间差集合*/
                    that.rtList[i] = {};
                    that.rtList[i]['cd'] = 0;
                    that.rtList[i]['status'] = hero[i].ispractice;
                    if (i == 1) {
                        that.shid = hero[1].id;
                        html1 += '<li class="q' + hero[i].quality + ' hero selected" gid="' + hero[i].id + '" id="lh' + hero[i].id + '"><img src="' + xGame.avatar(hero[i].image) + '" /><p>' + hero[i].name + '</p>' + (hero[i].ispractice == 1 ? ' <p class="training"></p>': '') + '</li>';
                    } else {
                        html1 += '<li class="q' + hero[i].quality + ' hero" gid="' + hero[i].id + '" id="lh' + hero[i].id + '"><img src="' + xGame.avatar(hero[i].image) + '" /><p>' + hero[i].name + '</p>' + (hero[i].ispractice == 1 ? ' <p class="training"></p>': '') + '</li>';
                    }

                } else {
                    html1 += '<li></li>';
                }
            }
            that.heroData['total'] = num;
            that.leftDom.find('ul').html(html1);

            that.heroDom = that.e.find('#body');

            /*训练位*/
            that.practiceListDom = that.e.find('#practicepop_list');
            that.placeData = {};
            that.unlockPlace = 0;
            var templist = [];
            for (var i = 1; i <= 8; i++) {
                if (place[i] != undefined) {
                    if (parseInt(place[i].gid) != 0) {
                        that.placeData[place[i].gid] = place[i];
                        /*修改新属性*/
                        that.heroData[place[i].gid]['pid'] = place[i].id; //训练位位置
                        that.heroData[place[i].gid]['cd'] = place[i].cd; //冷却时间

                        html2 += '<li id="pid' + place[i].id + '" pid="' + place[i].id + '" class="q' + that.heroData[place[i].gid].quality + '"><img src="' + xGame.avatar(that.heroData[place[i].gid].image) + '"  gid="' + place[i].gid + '"/><span class="practice_lv' + place[i].level + '"></span>' + (place[i].level == 6 ? '': '<a class="pop_btn_small btn" pid="' + place[i].id + '" type="levelup">' + lang('level_up') + '</a>') + '</li>';
                    } else {
                        if (that.blockPlace == undefined) {
                            that.blockPlace = place[i].id;
                        }
                        /*暂时只开到至尊*/
                        html2 += '<li id="pid' + place[i].id + '" class="block" pid="' + place[i].id + '"><span class="practice_lv' + place[i].level + '"></span>' + (place[i].level == 6 ? '': '<a class="pop_btn_small btn" pid="' + place[i].id + '" type="levelup">' + lang('level_up') + '</a>') + '</li>';
                    }
                } else {
                    if (that.unlockPlace == 0) {
                        that.unlockPlace = i;
                        html2 += '<li class="locked"><a class="pop_btn_small btn" pid="' + i + '" type="unlock">' + lang('unlock') + '</a></li>';
                    } else {
                        html2 += '<li class="locked"></li>';
                    }
                }
            }
            that.practiceListDom.html(html2);

            that.practiceScroll = new iScroll('practicepop_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.practiceListDom.css('width', 8 * 110 + 'px');
            that.practiceScroll.refresh();
            that.isTimeout.push(setTimeout(function() {
                that.practiceScroll.refresh();
            },
            300));

            /*规则*/
            that.ruleDom = that.e.find('#select_dom');
            for (var i = 1; i <= 4; i++) {
                if (i == 1) {
                    that.stype = 1;
                    html3 += '<li class="selected" id="rule' + i + '" stype="' + i + '"><b>' + rule[i].hour + lang('hour') + '：</b><span class="m' + rule[i].paytype + '">' + rule[i].cost + (rule[i].paytype == 1 ? lang('silver') : lang('gold')) + '</span></li>';
                } else {
                    html3 += '<li id="rule' + i + '" stype="' + i + '"><b>' + rule[i].hour + lang('hour') + '：</b><span class="m' + rule[i].paytype + '">' + rule[i].cost + (rule[i].paytype == 1 ? lang('silver') : lang('gold')) + '</span></li>';
                }
            }
            that.ruleDom.find('ul').html(html3);

            that.leap = leap;

            xGame.updateCd('practice', 0);
            that.setMinData();
            CD['practice'].checkEveryOne();

            that.fillHeroData();

            that.bindAll();

            that.e.show();
            $('#worldloading').hide();
        },

        setMinData: function() {
            var that = this;
            var temp = [];
            for (var i in that.placeData) {
                /*这里需要减去增加的时间*/
                //console.log('-------------------' + that.rtList[that.heroData[that.shid].num]['cd']);
                temp.push({
                    'cd': (that.placeData[i].cd - that.rtList[that.heroData[that.placeData[i].gid].num]['cd']),
                    'gid': that.placeData[i].gid,
                    'pid': that.placeData[i].id
                });
            }
            if (temp.length >= 1) {
                temp = temp.sort(helper.compare('cd'));
                that.minData = temp[0];
                that.maxSec = temp[temp.length - 1].cd;
            } else {
                that.minData = undefined;
                that.maxSec = 0;
            }

            temp = null;
        },

        fillHeroData: function() {
            var that = this;
            /*填充数据*/
            that.heroDom.find('#hero_img').css('background-image', 'url(' + xGame.avatar(that.heroData[that.shid].image, true) + ')');
            that.heroDom.find('#hero_type').html(helper.getItemVocation(parseInt(that.heroData[that.shid].type)));
            that.heroDom.find('#hero_lv').html('Lv.' + that.heroData[that.shid].level);
            that.heroDom.find('#hero_name').html(that.heroData[that.shid].name);
            if (that.heroData[that.shid].turn != 0) {
                that.heroDom.find('#hero_turn').html(that.heroData[that.shid].turn).show();
            } else {
                that.heroDom.find('#hero_turn').html('').hide();
            }

            /*判断显示*/
            that.checkDisplay(that.heroData[that.shid].ispractice);
            
            that.heroDom.attr('class', 'q' + that.heroData[that.shid].quality);
        },

        checkDisplay: function(ispractice) {
            var that = this;
            if (ispractice == 0) {
                that.rightTop2Dom.css('display', 'none');
                if (that.stype != 1) {
                    that.rightTop1Dom.find('#rule' + that.stype).removeClass('selected');
                    that.stype = 1;
                    that.rightTop1Dom.find('#rule' + that.stype).addClass('selected');
                }
                /*判断转生*/
                if (parseInt(that.heroData[that.shid].isturn) == 1) {
                    that.rightTop1Dom.css('display', 'none');
                    that.rightTop3Dom.css('display', 'block');
                    that.rightTop3Dom.find('#ratio').html(that.heroData[that.shid].ratio);
                } else {
                    that.rightTop3Dom.css('display', 'none');
                    that.rightTop1Dom.css('display', 'block');
                }
            } else {
                xGame.updateCd('practice', 0);
                that.rightTop1Dom.css('display', 'none');
                that.rightTop2Dom.find('#practice_lv').html(that.heroData[that.shid].level);
                that.rightTop2Dom.find('#practice_exp').html(that.heroData[that.shid].exp);
                that.rightTop2Dom.find('#practice_levelepexp').html(that.heroData[that.shid].levelupexp);
                if (that.heroData[that.shid].pid != 0) {
                    that.spid = that.heroData[that.shid].pid;
                }
                /*之前赋值过了，而且新的也改过时间差了，所以这里只要调相减就行 */
                xGame.updateCd('practice', that.heroData[that.shid].cd - that.rtList[that.heroData[that.shid].num]['cd']);
                /*判断转生*/
                if (parseInt(that.heroData[that.shid].isturn) == 1) {
                    that.rightTop2Dom.css('display', 'none');
                    that.rightTop3Dom.css('display', 'block');
                    that.rightTop3Dom.find('#ratio').html(that.heroData[that.shid].ratio);
                } else {
                    that.rightTop3Dom.css('display', 'none');

                    var s = parseInt(that.heroData[that.shid].exp) / parseInt(that.heroData[that.shid].levelupexp);
                    s = s >= 1 ? 1 : s;

                    xGame.islock = true; //设置锁

                    if (s > 0) {
                        that.rightTop2Dom.find('#practice_bar').css('width', '0').animate({
                            'width': (s * 305 + 13) + 'px'
                        },
                        500, 'esae',
                        function() {
                            xGame.unlock();
                        });
                    } else {
                        /*奇怪的bug，为0时竟然有一个默认长度,Fuck!!!*/
                        if (s == 0) {
                            that.rightTop2Dom.find('#practice_bar').css('width', '0');
                        }
                        xGame.unlock();
                    }
                    that.checkByFreeTimes();

                    that.rightTop2Dom.css('display', 'block');
                    
                    that.e.find('#practiceleapforce_btn').show();
                }
            }
        },

        checkByFreeTimes: function(next) {//加入参数，解决突飞扫荡后突飞卡为0的bug

            var next = next || this.leap;
            if (this.freeTimes > 0) {
                this.rightTop2Dom.find('#tufei_ft').html(this.getWan(this.freeTimes));
                this.rightTop2Dom.find('#practice_cost').attr('class', 'practice_cost ft');
            } else {
                this.rightTop2Dom.find('#tufei_cost').html(next + lang('gold'));
                this.rightTop2Dom.find('#practice_cost').attr('class', 'practice_cost gold');
            }
        },
        
        getWan: function(num) {
            if (parseInt(num) < 100000) {
                return num;
            } else {
                return Math.floor(num / 10000) + lang('10k');
            }
        },

        turn: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('turn_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_hero_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('parameter_error'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_is_200'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:

                    that.heroData[that.shid]['isturn'] = 0;
                    that.heroData[that.shid]['turn'] = res.info.turn;
                    that.heroData[that.shid]['level'] = res.info.level;
                    that.heroData[that.shid]['exp'] = res.info.exp;
                    that.heroData[that.shid]['levelupexp'] = res.info.levelupexp;
                    that.rightTop2Dom.find('#practice_lv').html('1'),
                    that.heroDom.find('#hero_lv').html('Lv.1');
                    that.rightTop2Dom.find('#practice_bar').css('width', '0px');
                    that.heroDom.find('#hero_turn').html(that.heroData[that.shid].turn).show();
                    /*训练中的*/
                    if (parseInt(that.heroData[that.shid]['ispractice']) == 1) {
                        that.rightTop2Dom.find('#practice_exp').html(that.heroData[that.shid].exp);
                        that.rightTop2Dom.find('#practice_levelepexp').html(that.heroData[that.shid].levelupexp);
                        that.rightTop2Dom.css('display', 'block');
                        that.rightTop2Dom.find('#practiceleapforce_btn').css('display', 'block');
                        that.rightTop3Dom.css('display', 'none');
                    } else {
                        that.rightTop1Dom.css('display', 'block');
                        that.rightTop3Dom.css('display', 'none');
                    }
                    that.checkByFreeTimes();
                    that.rightTop2Dom.find('#add_exp_show').remove();

                    xGame.toast.show(lang('turn_success'));

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'practice',
                'm': 'turn',
                'gid': that.shid
            },
            Func);
            Func = null;
        },

        goLeap: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 8 : xGame.toast.show(lang('get_hero_info_fail'));
                    xGame.unlock();
                    break;
                case - 7 : xGame.toast.show(lang('stop_practice_fail'));
                    xGame.unlock();
                    break;
                case - 6 : xGame.toast.show(lang('get_practice_rule_fail'));
                    xGame.unlock();
                    break;
                case - 5 : xGame.toast.show(lang('tufei_times_get_fail'));
                    xGame.unlock();
                    break;
                case - 4 : xGame.toast.show(lang('plus_exp_fail'));
                    xGame.unlock();
                    break;
                case - 3 : xGame.toast.show(lang('parameter_error'));
                    xGame.unlock();
                    break;
                case - 2 : xGame.toast.show(lang('deduct_gold_fail'));
                    xGame.unlock();
                    break;
                case - 1 : xGame.toast.show(lang('get_practice_rule_fail'));
                    xGame.unlock();
                    break;
                case 2:
                    xGame.goPay(lang('no_gold_for_leap'));
                    xGame.unlock();
                    break;
                case 3:
                    xGame.toast.show(lang('lv_is_ceiling'));
                    xGame.unlock();
                    break;
                case 4:
                    xGame.toast.show(lang('only_practice_can_tufei'));
                    xGame.unlock();
                    break;
                case 1:
                    /*dom修改*/
                    that.rightTop2Dom.find('#go_leap').addClass('lock');
                    var s = parseInt(res.info.exp) / parseInt(res.info.levelupexp);
                    s = s >= 1 ? 1 : s;

                    if (miscpop.in_array(guide.now, [1204, 1205, 1206, 1207, 1208, 1214, 1215, 1216, 1217, 1218])) {
                        guide.updateStep(null, true);
                    }
                    
                    /*这里为突飞专门写一个方法*/
                    that.leapShow(parseInt(that.heroData[that.shid].level), parseInt(res.info.level), s, parseInt(res.info.isturn), res.info.ratio);

                    var obj = $('<div id="add_exp_show" class="bounceInUp">' + (res.info.hit == 1 ? '<span class="reward_crit rc2"></span>': '') + '<b>' + lang('exp') + ' + ' + res.info.addexp + '</b></div>');
                    that.rightTop2Dom.find('#p_exp_bar').append(obj);

                    player.updateinfo({
                        'gold': parseInt(res.info.gold)
                    });
                    that.heroData[that.shid].level = res.info.level;
                    that.heroData[that.shid].exp = res.info.exp;
                    that.heroData[that.shid].levelupexp = res.info.levelupexp;
                    that.leap = parseInt(res.info.next);
                    that.freeTimes = parseInt(res.info.freetimes);
                    that.heroData[that.shid].isturn = parseInt(res.info.isturn);

                    that.checkByFreeTimes();

                    that.rightTop2Dom.find('#practice_exp').html(that.heroData[that.shid].exp);
                    that.rightTop2Dom.find('#practice_levelepexp').html(that.heroData[that.shid].levelupexp);
                    player.taskaction(1);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    xGame.unlock();
                    break;
                }
                //新手引导判断 突飞直接加
                //xGame.showGuide([59,60,61,62,63]);

            };
            loadContents({
                'c':
                'practice',
                'm': 'go_leap',
                'gid': that.shid
            },
            Func);
            Func = null;
        },

        bindAll: function() {
            var that = this;

            /*左右滑动*/
            that.e.find('#bottom_dom').undelegate('#scroller_btn_left', 'touchend').delegate('#scroller_btn_left', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.practiceScroll.scrollToPage('prev', 0);
            });
            that.e.find('#bottom_dom').undelegate('#scroller_btn_right', 'touchend').delegate('#scroller_btn_right', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.practiceScroll.scrollToPage('next', 0);
            });

            /*ruleDom绑定*/
            that.ruleDom.undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var ruleid = parseInt($(this).attr('stype'));
                if (ruleid == that.stype) {
                    return;
                } else {
                    that.ruleDom.find('#rule' + ruleid).addClass('selected');
                    that.ruleDom.find('#rule' + that.stype).removeClass('selected');
                    that.stype = ruleid;
                }
            });

            /*左边的武将信息绑定*/
            that.leftDom.find('ul').undelegate('li.hero', 'touchend').delegate('li.hero', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var gid = parseInt($(this).attr('gid'));
                if (gid == that.shid) {
                    return;
                } else {
                    that.leftDom.find('#lh' + that.shid).removeClass('selected');
                    that.leftDom.find('#lh' + gid).addClass('selected');
                    that.shid = gid;
                    that.fillHeroData();
                }
            });

            /*绑定开始训练*/
            that.rightTop1Dom.undelegate('#start_practice', 'touchend').delegate('#start_practice', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.blockPlace == undefined) {
                    xGame.toast.show(lang('no_block_site'));
                    return;
                }
                that.practiceStart();
            });
            that.e.undelegate('#practiceleapforce_btn', 'touchend').delegate('#practiceleapforce_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.showpop('practiceleap', {
                    'gid': that.shid,
                    'freetimes': that.freeTimes,
                    'timeslist': that.practiceleapforce_list,
                    'atkFunc': function(times) {
                        practiceleappop.atkFunc(times);
                    },
                });
            });

            /*绑定训练位按钮:升级和解锁*/
            that.practiceListDom.undelegate('a', 'touchend').delegate('a', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var type = $(this).attr('type');
                var pid = parseInt($(this).attr('pid'));
                if (type == 'levelup') {
                    that.levelUpPrice(pid);
                } else if (type == 'unlock') {
                    that.unlockPrice(pid);
                }
            });

            /*绑定停止按钮*/
            that.rightTop2Dom.undelegate('#stop_practice', 'touchend').delegate('#stop_practice', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (that.spid == undefined) {
                    xGame.toast.show(lang('operation_fails'));
                    return;
                }
                that.stopPractice();
            });

            /*绑定突飞按钮*/
            that.rightTop2Dom.undelegate('#go_leap.pop_btn_middle', 'touchend').delegate('#go_leap.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.goLeap();
            });

            /*绑定转生按钮*/
            that.e.find('#top_dom').undelegate('#turn', 'touchend').delegate('#turn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.turn();
            });

            that.e.find('#bottom_dom').find('ul').find('li').undelegate('img', 'touchend').delegate('img', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var gid = parseInt($(this).attr('gid'));
                if (gid == that.shid) {
                    return;
                } else {
                    that.leftDom.find('#lh' + that.shid).removeClass('selected');
                    that.leftDom.find('#lh' + gid).addClass('selected');
                    that.shid = gid;
                    that.fillHeroData();
                }
            });

            that.rightTop2Dom.find('#p_exp_bar').undelegate('#add_exp_show', 'webkitAnimationEnd').delegate('#add_exp_show', 'webkitAnimationEnd',
                function() {
                    $(this).fadeOut(800,
                    function() {
                        $(this).remove();
                    });
                }
            );

        },

        practiceStart: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 7 : xGame.toast.show(lang('get_extend_info_fail'));
                    break;
                case - 6 : xGame.toast.show(lang('time_log_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('deduct_silver_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_practice_price_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_own_practice_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('get_hero_info_fail'));
                    break;
                case 2:
                    xGame.toast.show(lang('hero_not_expedition'));
                    break;
                case 3:
                    xGame.toast.show(lang('practice_site_is_used'));
                    break;
                case 4:
                    xGame.toast.show(lang('silver_not_enough'));
                    break;
                case 5:
                    xGame.goPay(lang('no_gold_for_practice'));
                    break;
                case 6:
                    xGame.toast.show(lang('hero_is_training'));
                    break;
                case 7:
                    xGame.toast.show(lang('lv_is_ceiling_for_practice'));
                    break;
                case 1:
                    /*dom修改*/
                    player.updateinfo({
                        'silver': parseInt(res.info.silver),
                        'gold': parseInt(res.info.gold)
                    });
                    that.leftDom.find('#lh' + that.shid).append('<p class="training"></p>');
                    that.practiceListDom.find('#pid' + that.blockPlace).attr('class', 'q' + that.heroData[that.shid].quality).append('<img src="' + xGame.avatar(that.heroData[that.shid].image) + '" gid="' + that.shid + '"/>');
                    /*数据修改*/
                    that.heroData[that.shid].ispractice = 1;
                    /*修改两个新属性*/
                    that.heroData[that.shid].pid = that.blockPlace;
                    that.heroData[that.shid].cd = res.info.cd;
                    that.blockPlace = that.practiceListDom.find('.block').first().attr('pid');
                    that.placeData[that.shid] = res.info;
                    /*最新的时候，时间差改为0*/
                    that.rtList[that.heroData[that.shid].num]['cd'] = 0;
                    that.rtList[that.heroData[that.shid].num]['status'] = 1;
                    that.checkDisplay(1);

                    //训练侧边栏数据更新
                    if (res.info.practicetime) {
                        player.showPracticeSider(res.info.practicetime);
                    }
                    //训练侧边栏数据更新 end

                    /*原来人在训练中了*/
                    var b = false;
                    if (that.maxSec == 0) {
                        b = true;
                    }

                    that.setMinData();
                    if (b) {
                        CD['practice'].checkEveryOne();
                    }
                    b = null;

                    //在平乱副本里
                    if (pacifypop && pacifypop.e) {
                        pacifypop.militaryDom.find('.pacify_gold span').text(res.info.gold);
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();

                if (that.step && miscpop.in_array(that.step, [1204, 1205, 1206, 1207, 1208, 1214, 1215, 1216, 1217, 1218])) {
                    guide.show(that.step);
                }

                //新手引导判断 点训练后的操作
                xGame.showGuide([1202, 1212]);
            };
            loadContents({
                'c':
                'practice',
                'm': 'practice_start',
                'pid': that.blockPlace,
                'gid': that.shid,
                'type': that.stype
            },
            Func);
            Func = null;
        },

        levelUpPrice: function(pid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    xGame.confirm.show(lang('lvup_practice_site'), lang('lv_site_ask', [res.info.gold, res.info.level, res.info.result]), 2,
                    function() {
                        xGame.confirm.hide(function() {
                            that.levelUp(pid);
                        });
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                    break;
                case 2:
                    xGame.toast.show(lang('vip_lv_not_enough'));
                    break;
                case - 1 : xGame.toast.show(lang('get_practice_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_lv_info_fail'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'practice',
                'm': 'get_levelup_price',
                'pid': pid
            },
            Func);
            Func = null;
        },

        levelUp: function(pid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    that.practiceListDom.find('#pid' + pid).find('span').attr('class', 'practice_lv' + res.info.level);
                    if (res.info.level == 6) {
                        that.practiceListDom.find('#pid' + pid).find('a').remove();
                    }
                    player.updateinfo({
                        'gold': parseInt(res.info.gold)
                    });
                    xGame.toast.show(lang('lvup_to_lv', [res.info.name]));

                    //在平乱副本里
                    if (pacifypop && pacifypop.e) {
                        pacifypop.militaryDom.find('.pacify_gold span').text(res.info.gold);
                    }
                    break;
                case 2:
                    xGame.toast.show(lang('practice_site_is_full_lv'));
                    break;
                case 3:
                    xGame.toast.show(lang('vip_lv_not_enough'));
                    break;
                case 4:
                    xGame.goPay(lang('your_gold_not_enough'));
                    break;
                case - 1 : xGame.toast.show(lang('parameter_error'));
                    break;
                case - 2 : xGame.toast.show(lang('get_practice_rule_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'practice',
                'm': 'levelup',
                'pid': pid
            },
            Func);
            Func = null;
        },

        unlockPrice: function(pid) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case 1:
                    xGame.confirm.show(lang('unlock_practice'), lang('unlock_site_ask', [res.gold]), 2,
                    function() {
                        xGame.confirm.hide(function() {
                            if (parseInt(res.vip) > parseInt(player.info.vip)) {
                                xGame.toast.show(lang('only_vip%lv_can_open', [res.vip]));
                                return;
                            }
                            that.unlock(parseInt(res.gold));
                        });
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                    break;
                case - 1 : xGame.toast.show(lang('site_illegal'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'practice',
                'm': 'get_unlock_price',
                'squence': pid
            },
            Func);
            Func = null;
        },

        unlock: function(gold) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('add_practice_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_practice_price_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('get_extend_info_fail'));
                    break;
                case 3:
                    xGame.toast.show(lang('vip_lv_too_low'));
                    break;
                case 2:
                    xGame.goPay(lang('no_gold_for_unlock'));
                    break;
                case 1:
                    /*修改dom*/
                    var dom = that.practiceListDom.find('.locked').first();
                    dom.attr('class', 'block').attr({
                        'id': 'pid' + res.info.id,
                        'pid': res.info.id
                    }).html('<span class="practice_lv1"></span><a class="pop_btn_small btn" pid="' + res.info.id + '" type="levelup">' + lang('level_up') + '</a>');
                    if (that.blockPlace == undefined) {
                        that.blockPlace = res.info.id;
                    }
                    if (that.unlockPlace < 8) {
                        //that.placeData[res.info.id] = res.info;
                        that.unlockPlace++;
                        that.practiceListDom.find('.locked').first().html('<a class="pop_btn_small btn" pid="' + that.unlockPlace + '" type="unlock">' + lang('unlock') + '</a>');
                    }

                    player.updateinfo({
                        'gold': parseInt(res.info.gold)
                    });
                    /*修改数据*/
                    xGame.toast.show(lang('unlock_complete'));

                    //训练侧边栏数据更新
                    player.showPracticeSider(0);
                    //训练侧边栏数据更新 end

                    //在平乱副本里
                    if (pacifypop && pacifypop.e) {
                        pacifypop.militaryDom.find('.pacify_gold span').text(res.info.gold);
                    }

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'practice',
                'm': 'unlock'
            },
            Func);
            Func = null;
        },

        stopPractice: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;
            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('stop_practice_fail'));
                    break;
                case 1:
                    //that.fillData(res.list,res.place,res.rule);
                    /*dom修改*/
                    that.checkDisplay(0);
                    that.leftDom.find('#lh' + that.shid).find('.training').remove();
                    that.practiceListDom.find('#pid' + that.spid).attr('class', 'block').find('img').remove();
                    /*数据修改*/
                    that.blockPlace = that.practiceListDom.find('.block').first().attr('pid');
                    that.heroData[that.shid].ispractice = 0;
                    /*修改两个新属性*/
                    that.heroData[that.shid].pid = 0;
                    that.heroData[that.shid].cd = 0;
                    xGame.updateCd('practice', 0);
                    xGame.toast.show(lang('stop_complete'));

                    //训练侧边栏数据更新
                    player.showPracticeSider(0);
                    //训练侧边栏数据更新 end

                    /*下训练位时删除后要判断*/
                    delete that.placeData[that.shid];
                    that.setMinData();

                    that.rtList[that.heroData[that.shid].num]['cd'] = 0;
                    that.rtList[that.heroData[that.shid].num]['status'] = 0;
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'practice',
                'm': 'practice_stop',
                'pid': that.spid
            },
            Func);
            Func = null;
        },

        /*参数，以前的等级，突飞后的等级，百分比,是否是突飞,转生加成*/
        leapShow: function(lastLevel, nowLevel, percent, isturn, ratio) {
            var that = this;
            /*用锁防止它没看完就点*/
            xGame.islock = true;

            var times = nowLevel - lastLevel;
            var obj = that.rightTop2Dom.find('#practice_bar'),
            obj2 = that.rightTop2Dom.find('#practice_lv'),
            obj3 = that.heroDom.find('#hero_lv');

            /*突飞没升级*/
            if (times == 0) {
                obj.animate({
                    'width': (percent * 305 + 13) + 'px'
                }, 500, 'ease',
                function() {
                    that.rightTop2Dom.find('#go_leap').removeClass('lock');
                    xGame.unlock();
                    xGame.showGuide([1204, 1205, 1206, 1207, 1208, 1214, 1215, 1216, 1217, 1218]);
                });
            } else {
                /*升级了的时候，未到转生时和转生时*/
                /*防止bug*/
                if (obj.width() == 318) {
                    obj.css('width', '0');
                }

                obj.css({'width': '318px', '-webkit-transition': 'width 0.5s ease'});

                that.rightTop2Dom.undelegate('#practice_bar', 'webkitTransitionEnd').delegate('#practice_bar', 'webkitTransitionEnd', function(e) {

                    if (e.target != obj[0]) {
                        return false;
                    }

                    times--;
                    lastLevel += 1;

                    if (times < 0) {
                        return;
                    } else {
                        if (times == 0) { //最后一下
                            /*判读如果转生的话就不现在最后的一点了*/
                            if (isturn == 1) {
                                that.isTimeout.push(setTimeout(function() {
                                    xGame.toast.show(lang('you_can_turn_now'));
                                    that.rightTop2Dom.css('display', 'none');
                                    that.rightTop3Dom.css('display', 'block');
                                    that.heroData[that.shid]['ratio'] = ratio;
                                    that.rightTop3Dom.find('#ratio').html(that.heroData[that.shid].ratio);
                                    that.rightTop2Dom.find('#go_leap').removeClass('lock');
                                    xGame.unlock();
                                },
                                1000));
                                obj2.html(lastLevel);
                                obj3.html('Lv.' + lastLevel);
                                return;
                            }
                            that.rightTop2Dom.undelegate('webkitTransitionEnd');
                            obj.css({'width': '0', '-webkit-transition': ''});

                            obj.animate({
                                'width': (percent * 305 + 13) + 'px'
                            }, 500, 'ease', function () {
                                that.rightTop2Dom.find('#go_leap').removeClass('lock');
                                xGame.unlock();
                                xGame.showGuide([1204, 1205, 1206, 1207, 1208, 1214, 1215, 1216, 1217, 1218]);
                            });
                        } else { //中间补全
                            obj.css({'width': '0', '-webkit-transition': ''});
                            console.log(obj.width());   // 这一行代码必不可少，防止上一行代码不起作用
                            obj.css({'width': '318px', '-webkit-transition': 'width 0.5s ease'});
                        }

                        obj2.html(lastLevel);
                        obj3.html('Lv.' + lastLevel);
                    }
                });
            }
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.find('#bottom_dom').undelegate('#scroller_btn_left', 'touchend');
                this.e.find('#bottom_dom').undelegate('#scroller_btn_right', 'touchend');
                this.e.undelegate('#practiceleapforce_btn', 'touchend');
                this.e.find('#top_dom').undelegate('#turn', 'touchend');
                this.e.find('#bottom_dom').find('ul').find('li').undelegate('img', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.ruleDom) {
                this.ruleDom.undelegate('li', 'touchend');
                this.ruleDom.remove();
                this.ruleDom = null;
            }
            if (this.leftDom) {
                this.leftDom.find('ul').undelegate('li.hero', 'touchend');
                this.leftDom.remove();
                this.leftDom = null;
            }
            if (this.rightTop1Dom) {
                this.rightTop1Dom.undelegate('#start_practice', 'touchend');
                this.rightTop1Dom.remove();
                this.rightTop1Dom = null;
            }
            if (this.practiceListDom) {
                this.practiceListDom.undelegate('a', 'touchend');
                this.practiceListDom.remove();
                this.practiceListDom = null;
            }
            if (this.rightTop2Dom) {
                this.rightTop2Dom.undelegate('#stop_practice', 'touchend');
                this.rightTop2Dom.undelegate('#go_leap.pop_btn_middle', 'touchend');
                this.rightTop2Dom.find('#p_exp_bar').undelegate('#add_exp_show', 'webkitAnimationEnd');
                this.rightTop2Dom.undelegate('#practice_bar', 'webkitTransitionEnd');
                this.rightTop2Dom.remove();
                this.rightTop2Dom = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.isshow = false;
            this.step = 0;
            xGame.updateCd('practice', 0);


            if (this.practiceScroll) {
                this.practiceScroll.destroy();
                delete this.practiceScroll;
            }
            //武将提醒
            if(typeof cordova != 'undefined' && typeof window.plugins.localNotification != 'undefined') {//如果可以
                if (this.minData != null) {//如果有最近的训练中的武将
                    window.plugins.localNotification.cancel('practice');
                    var time = Math.round(new Date().getTime()/1000) + (practicepop.minData.cd - practicepop.rtList[practicepop.heroData[practicepop.minData.gid].num]['cd']);//当前时间加上cd时间
                    window.plugins.localNotification.add({
                        date: time, // your set date object
                        message: lang('practice_clock', [practicepop.heroData[practicepop.minData.gid].name]),
                        repeat: 'daily', // will fire every week on this day
                        badge: 1,
                        id: 'practice',
                        sound:'sub.caf'
                    });
                }
            }
            this.unbindAll();
            if (this.heroDom) {
                this.heroDom.remove();
                this.heroDom = null;
            }
            if (this.rightTop3Dom) {
                this.rightTop3Dom.remove();
                this.rightTop3Dom = null;
            }
            this.maxSec = 0;
            this.heroData = this.shid = this.stype = this.blockPlace = this.unlockPlace = this.spid = this.placeData = this.leap = this.rtList = this.minData = this.maxSec = this.freeTimes = null;
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
            player.reloadInfo();
        },
    };
})();