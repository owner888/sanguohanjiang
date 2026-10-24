/* 将魂 */
(function() {
    soulWorldClass = function() {
        this.e;

        this.ltDom; //各种dom
        this.lbDom;
        this.rtDom;
        this.rbDom;
        this.compositeDom;
        this.infoDom;

        this.shid; //选中的武将的id
        this.ssid; //选中的将魂id
        this.leftSoulNum; //剩余的原魂数量

        this.soulScroller;

        this.eqPosList; //装备中的将魂坐标集合
        this.uneqPosList; //未装备的将魂位置坐标集合
        this.heroPosList;
        this.compositePosList; // 合成将魂的坐标集合
        this.touchSid;
        this.touchPos;
        this.canMove; //可以移动的集合
        this.nearlyX; //最近元素的x坐标
        this.nearlyY; //最近元素的y坐标
        this.nearlyPosId; //最近的元素的posid
        this.zIndex; //层级
        this.distance; //检测范围

        this.page; //当前页页码
        this.pages; //当前有效页数，也就是有道具的页数
        this.ruleList; //等级规则list
        this.isComposite = false; // 是否显示合成界面
        this.compositePage = 1; // 合成背包的当前页码
        this.compositePages = 1; // 合成背包的有效页数
        this.compositeResult = null; // 合成的将魂信息
    };
    soulWorldClass.prototype = {
        init: function() {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    var error_msg = '';
                    switch (res.status) {
                    case - 1 : error_msg = lang('lv_too_low_to_ent_soul');
                        break;
                    case - 2 : error_msg = lang('your_hero_all_sleep');
                        break;
                    case - 3 : error_msg = lang('get_soul_bag_fail');
                        break;
                    default:
                        error_msg = lang('wait_to_enter_soul');
                        break;
                    }
                    xGame.toast.show(error_msg);
                    xGame.loadWorld('city');
                    return false;
                }
                that = null;
            }
            loadContents({
                'c': 'soul',
                'm': 'index'
            },
            Func, false,
            function() {
                xGame.loadWorld('city');
            }); //加载数据

        },

        initFirst: function(res) {
            var that = this;

            if (that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('soulpop_tmpl'));
            that.e = dom_world.find('#soulpop');
            that.e.find('#soul_hide').unbind('touchend').bind('touchend',
            function(e) {
                xGame.loadWorld(xGame.lastWorld);
                e.preventDefault();
            });
            dom_world = null;

            that.ltDom = that.e.find('#soulpop_lt');
            that.lbDom = that.e.find('#soulpop_lb');
            that.rtDom = that.e.find('#soulpop_rt');
            that.rbDom = that.e.find('#soulpop_rb');
            that.compositeDom = that.e.find('#soulpop_composite');
            that.infoDom = that.e.find('#info_dom');

            that.zIndex = 10;
            that.distance = that.getDistance(75, 75);
            that.page = 1;
            that.pages = res.pack.pages;

            that.eqPosList = that.heroPosList = {
                'pos1': {
                    'left': -365,
                    'top': 28,
                    'style': 'left:-365px;top:28px;',
                    'id': 1
                },
                'pos2': {
                    'left': -245,
                    'top': 3,
                    'style': 'left:-245px;top:3px;',
                    'id': 2
                },
                'pos3': {
                    'left': -130,
                    'top': 28,
                    'style': 'left:-130px;top:28px;',
                    'id': 3
                },
                'pos4': {
                    'left': -100,
                    'top': 148,
                    'style': 'left:-100px;top:148px;',
                    'id': 4
                },
                'pos5': {
                    'left': -130,
                    'top': 258,
                    'style': 'left:-130px;top:258px;',
                    'id': 5
                },
                'pos6': {
                    'left': -245,
                    'top': 298,
                    'style': 'left:-245px;top:298px;',
                    'id': 6
                },
                'pos7': {
                    'left': -365,
                    'top': 258,
                    'style': 'left:-365px;top:258px;',
                    'id': 7
                },
                'pos8': {
                    'left': -390,
                    'top': 148,
                    'style': 'left:-390px;top:148px;',
                    'id': 8
                },
            };

            that.compositePosList = {
                'pos1': {
                    'left': -362,
                    'top': 203,
                    'style': 'left:-362px;top:203px;',
                    'id': 1
                },
                'pos2': {
                    'left': -133,
                    'top': 203,
                    'style': 'left:-133px;top:203px',
                    'id': 2
                },
            };

            var x, y;
            that.uneqPosList = {};
            for (var i = 1; i < 13; i++) {
                x = 35 + (i - 1) % 4 * 113;
                y = 42 + Math.floor((i - 1) / 4) * 107;
                that.uneqPosList['pos' + i] = {
                    'left': x,
                    'top': y,
                    'style': 'left:' + x + 'px;top:' + y + 'px;',
                    'id': i
                };
            }
            that.leftSoulNum = parseInt(res.pack.base_soul);
            that.fillRb(res.pack);
            that.fillRt(res.soul);

            var html1 = '';

            for (var i = 1; i <= res.general_totals; i++) {
                if (i == 1) {
                    that.shid = res.general[i].id;
                    that.fillLb(res.general[i]);
                    html1 += '<li id="soulHid' + res.general[i].id + '" hid="' + res.general[i].id + '" class="q' + res.general[i].quality + ' selected"><img  src="' + xGame.avatar(res.general[i].image) + '" /></li>';
                } else {
                    html1 += '<li id="soulHid' + res.general[i].id + '" hid="' + res.general[i].id + '" class="q' + res.general[i].quality + '"><img  src="' + xGame.avatar(res.general[i].image) + '" /></li>';
                }
            }
            that.ltDom.find('#soulpop_list').width(res.general_totals * 113 + 'px').html(html1);
            html1 = null;

            that.soulScroller = new iScroll('soulpop_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.soulScroller.refresh();

            that.bindAll();

            that.ruleList = ['', 1, 1, 1, 2, 4, 6, 8, 10];

            $('#worldloading').removeClass('black').hide();
        },

        bindAll: function() {
            var that = this;
            that.ltDom.find('#soulpop_list').undelegate('li', 'click').delegate('li', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                if ($(this).attr('hid') == that.shid) {
                    return false;
                }

                if (that.isComposite == true) {
                    //xGame.toast.show('请先关闭将魂合成界面');
                    return false;
                }

                //that.isComposite = false;
                //that.compositeDom.hide();
                //that.eqPosList = that.heroPosList;
                //that.lbDom.show();
                that.getHeroInfoById($(this));
            });

            that.rbDom.find('ul').undelegate('li.closed', 'click').delegate('li.closed', 'click',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.unlockPrice(parseInt($(this).attr('posid')));
            });

            that.rtDom.find('#soul_change').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.convert();
            });

            that.rtDom.find('#soul_levelup').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.levelup();
            });

            that.rbDom.find('#soul_prev').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                if (that.isComposite == false) {
                    that.getPackInfo(that.page - 1);
                } else {
                    that.getCompositePack(that.compositePage - 1, true);
                }
            });

            that.rbDom.find('#soul_next').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                if (that.isComposite == false) {
                    that.getPackInfo(that.page + 1);
                } else {
                    that.getCompositePack(that.compositePage + 1, true);
                }
            });

            that.lbDom.find('#go_detail').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#soul_detail').css('display', 'block');
                that.e.find('#soul_detail_hide').unbind('touchend').bind('touchend',
                function() {
                    that.e.find('#soul_detail').css('display', 'none');
                });
            });

            that.lbDom.find('#go_tower').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.loadWorld('tower');
            });

            that.bindSoul();

            // 合成按钮
            that.rtDom.find('#soul_composite').on('touchend', function (e) {
                
                e.preventDefault();

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.isComposite == true) {
                    return false;
                }

                that.getCompositePack(that.compositePage);
            });

            // 合成的玩法介绍按钮
            that.compositeDom.find('#composite_info').on('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.show();
                e.preventDefault();
            });

            // 合成的玩法介绍界面的关闭按钮
            that.infoDom.find('#info_hide_btn').on('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.hide();
                e.preventDefault();
            });

            that.infoDom.find('.mask').on('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.find('#info_hide_btn').trigger('touchend');
                e.preventDefault();
            });

            // 合成界面的合成按钮
            that.compositeDom.find('#composite_btn').on('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var soulDom = that.rbDom.find('.isequip');

                if (soulDom.length < 2) {
                    soulDom = null;
                    xGame.toast.show(lang('soulworld_need_composite'));
                    return false;
                }

                var sid1 = soulDom.eq(0).attr('sid');
                var sid2 = soulDom.eq(1).attr('sid');
                soulDom = null;
                that.compositeSoul(sid1, sid2);
                e.preventDefault();
            });

            // 合成界面的关闭按钮
            that.compositeDom.find('#composite_close').on('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.isComposite = false;
                that.eqPosList = that.heroPosList;
                that.compositeDom.hide();
                that.lbDom.show();
                that.refreshPage();
                e.preventDefault();
            });
        },

        bindSoul: function() {
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            
            var target = null;

            that.rbDom.find('ul').undelegate('span', 'touchstart').delegate('span', 'touchstart',
            function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('sid') != that.ssid) {
                    that.getSoulInfoById($(this));
                }

                that.touchSid = $(this).attr('sid');
                that.touchPos = $(this).attr('posid');
                /*方阵外面时就复制一个元素占着那个位置*/
                if ($(this).hasClass('isequip')) {
                    ex = parseInt(that.eqPosList['pos' + that.touchPos].left);
                    ey = parseInt(that.eqPosList['pos' + that.touchPos].top);
                } else {
                    ex = parseInt(that.uneqPosList['pos' + that.touchPos].left);
                    ey = parseInt(that.uneqPosList['pos' + that.touchPos].top);
                }

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).addClass('ismoving');
                $(this).css('z-index', that.zIndex++);

                target = $(this);
            });

            that.e.unbind('touchmove').bind('touchmove',function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
               if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ex + newX;
                    var realY = ey + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                }
            });

            /*这里必须区分*/
            that.rbDom.find('ul').undelegate('span.ismoving', 'touchend').delegate('span.ismoving', 'touchend',
            function(event) {
                target = null;
                $(this).removeClass('ismoving');
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this);
                var myx = parseInt(obj.css('left').replace('px', ''));
                var myy = parseInt(obj.css('top').replace('px', ''));

                /*如果坐标最后都没变化，就直接不管它*/
                if (myx == ex && myy == ey) {
                    return false;
                }
                /*移动的是已装备的将魂*/
                if (obj.hasClass('isequip')) {
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        obj.animate({
                            'left': that.nearlyX + 'px',
                            'top': that.nearlyY + 'px'
                        },
                        100, 'ease', null);
                        /*成功就修改信息*/
                        var callback = function() {
                            obj.attr('posid', that.nearlyPosId);
                            if (that.canMove['pos' + that.nearlyPosId] == 'block') {
                                /*是空位*/
                                that.canMove['pos' + that.nearlyPosId] = that.touchSid;
                                that.canMove['pos' + that.touchPos] = 'block';
                            } else { //如果不是空位
                                var temp = that.canMove['pos' + that.nearlyPosId];
                                that.canMove['pos' + that.nearlyPosId] = that.touchSid;
                                that.canMove['pos' + that.touchPos] = temp;
                                that.rbDom.find('#soul_span' + temp).attr('posid', that.touchPos);
                                that.rbDom.find('#soul_span' + temp).animate({
                                    'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease', null).css('z-index', that.zIndex++);;
                            }
                        };
                        /*发生错误就回去*/
                        var error = function() {
                            obj.animate({
                                'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                            },
                            100, 'ease', null);
                        }
                        
                        // 如果是在合成界面，直接调用callback
                        if (that.isComposite == false) {
                            that.equip(that.nearlyPosId, callback, error);
                        } else {
                            callback();
                        }

                    } else { //不合法，再判断有没有在背包的范围内，在范围内的话就调用卸下，不在就返回
                        if ((myx > -35 && myx < 445) && (myy > -40 && myy < 350)) {
                            var callback = function() {
                                that.canMove['pos' + that.touchPos] = 'block';

                                var size = that.rbDom.find('.block').size();
                                
                                /*如果是mustlock，都是下去就锁上*/
                                if (that.rbDom.find('#soul_span' + that.touchSid).attr('mustlock') == 'true') {
                                    that.lbDom.find('#soulpos' + that.touchPos).attr('class', 'closed').html(lang('turn_open', [that.ruleList[that.touchPos]]));
                                }
                                
                                if (size == 0) { //如果当前页满课，直接删除
                                    obj.remove();
                                } else if (size == 12) { //全是空的，可以写死
                                    that.rbDom.find('.block').eq(0).before('<li class="soul_t' + obj.attr('type') + ' soul_ts" sid ="' + obj.attr('sid') + '" id="soulS' + obj.attr('sid') + '"></li>');
                                    that.rbDom.find('.block').eq(0).remove();
                                    obj.attr({
                                        'class': 'isunequip',
                                        'posid': 1
                                    });
                                    obj.animate({
                                        'left': that.uneqPosList['pos1'].left + 'px',
                                        'top': that.uneqPosList['pos1'].top + 'px'
                                    },
                                    100, 'ease', null);
                                } else { //没有满的话就加到队末
                                    /*底*/
                                    that.rbDom.find('.block').eq(0).before('<li class="soul_t' + obj.attr('type') + ' soul_ts" sid ="' + obj.attr('sid') + '" id="soulS' + obj.attr('sid') + '"></li>');
                                    that.rbDom.find('.block').eq(0).remove();
                                    var posid = 12 - that.rbDom.find('.block').size() - that.rbDom.find('.closed').size();
                                    obj.attr({
                                        'class': 'isunequip',
                                        'posid': posid
                                    });
                                    obj.animate({
                                        'left': that.uneqPosList['pos' + posid].left + 'px',
                                        'top': that.uneqPosList['pos' + posid].top + 'px'
                                    },
                                    100, 'ease', null);
                                }
                                
                            };
                            /*发生错误就回去*/
                            var error = function() {
                                obj.animate({
                                    'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease', null);
                            }

                            
                            if (that.isComposite == false) {
                                that.unequip(that.touchPos, callback, error);
                            } else {
                                callback();
                            }

                        } else {
                            obj.animate({
                                'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                            },
                            100, 'ease', null);
                        }
                    }
                } else { //移动的是未装备的将魂
                    /*根本就没有位置可以移动，直接会原来的位置*/
                    if (that.canMove['pos1'] == undefined) {
                        obj.animate({
                            'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                            'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                        },
                        100, 'ease', null);
                    } else {
                        /*有位置可以移动，判断最近的位置*/
                        if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease', null);
                            /*成功就修改信息，右侧重新排*/
                            var callback = function() {
                                if (that.canMove['pos' + that.nearlyPosId] == 'block') { //如果是移动到空位上去
                                    that.refreshPackBySid(that.touchSid);
                                } else {
                                    var ssid = that.canMove['pos' + that.nearlyPosId]; //要跑到背包里的将魂的id
                                    var sobj = that.rbDom.find('#soul_span' + ssid); //要跑到背包里的将魂dom

                                    sobj.attr({
                                        'posid': that.touchPos,
                                        'class': 'isunequip'
                                    });
                                    sobj.animate({
                                        'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                                        'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                                    },
                                    100, 'ease', null);

                                    that.rbDom.find('#soulS' + that.touchSid).attr({
                                        'id': 'soulS' + ssid,
                                        'sid': ssid,
                                        'class': 'soul_t' + sobj.attr('type')
                                    });
                                }
                                obj.attr({
                                    'posid': that.nearlyPosId,
                                    'class': 'isequip'
                                });
                                that.canMove['pos' + that.nearlyPosId] = that.touchSid;

                            };
                            /*发生错误就回去*/
                            var error = function() {
                                obj.animate({
                                    'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease', null);
                            }

                            if (that.isComposite == false) {
                                that.equip(that.nearlyPosId, callback, error, true);
                            } else {
                                callback();
                            }

                        } else { //不合法，回到原位置
                            obj.animate({
                                'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                                'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                            },
                            100, 'ease', null);
                        }
                    }
                }
            });
        },

        refreshPackBySid: function(sid) {
            /*删除这个元素*/
            var opos = parseInt(this.rbDom.find('#soul_span' + sid + '.isunequip').attr('posid')); //原来的位置ID
            this.rbDom.find('#soulS' + sid).remove();

            /*li底图的移动*/
            if (this.rbDom.find('.block').size() == 0) {
                if (this.rbDom.find('.closed').size() != 0) {
                    this.rbDom.find('.closed').first().before('<li class="block"></li>');
                } else {
                    this.rbDom.find('ul').append('<li class="block"></li>');
                }
            } else {
                this.rbDom.find('.block').last().after('<li class="block"></li>');
            }

            /*span的更新*/
            var pos;
            for (var i = 1; i <= this.rbDom.find('.isunequip').size(); i++) {
                /*这里的顺序要小心，否则就会出错，不能用eq(i),因为顺序是会变的*/
                pos = parseInt(this.rbDom.find('.isunequip').eq(i - 1).attr('posid'));
                if (pos > opos) {
                    this.rbDom.find('.isunequip').eq(i - 1).css({
                        'left': this.uneqPosList['pos' + (pos - 1)].left + 'px',
                        'top': this.uneqPosList['pos' + (pos - 1)].top + 'px'
                    }).attr('posid', (pos - 1));
                }
            }
            opos = pos = null;
        },

        /*转原魂*/
        convert: function(confirm) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('add_soul_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('exchange_soul_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_soul_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    if (confirm == undefined) {
                        xGame.confirm.show(lang('exchange_original_soul'), lang('exchange_original_soul_ask', [res.num]), 2,
                        function() {
                            that.convert(1);
                            xGame.confirm.hide();
                        },
                        function() {
                            xGame.confirm.hide();
                        });
                    } else {
                        that.fillCurrentSoul(res.base_soul);
                        /*如果要转的战魂在背包里*/
                        var obj = that.rbDom.find('#soul_span' + that.ssid);
                        that.fillRb(res.pack, true);

                        if (obj.hasClass('isunequip')) { //如果要转的战魂在背包里
                            that.page = res.pack.page;
                            that.pages = res.pack.pages;
                            that.showPage();
                            var pos = parseInt(obj.attr('posid'));
                            var sid = that.rbDom.find('.isunequip[posid="' + (pos + 1) + '"]').attr('sid');
                            
                            if (sid == undefined) { //先看后面的，再看前一个
                                sid = that.rbDom.find('.isunequip[posid="' + (pos - 1) + '"]').attr('sid');
                                if (sid == undefined) {
                                    that.rtDom.attr('class', 'nodata');
                                } else {
                                    /*特殊处理，解锁了才能请求*/
                                    xGame.unlock();
                                    that.getSoulInfoById(that.rbDom.find('#soul_span' + sid));
                                }
                            } else {
                                /*特殊处理，解锁了才能请求*/
                                xGame.unlock();
                                that.getSoulInfoById(that.rbDom.find('#soul_span' + sid));
                            }
                            //that.refreshPackBySid(that.ssid);
                            pos = null;
                            gid = null;
                        } else { //如果要转的将魂在身上里
                            if (that.rbDom.find('#soul_span' + that.ssid).attr('mustlock') == 'true') {
                                that.lbDom.find('#soulpos' + obj.attr('posid')).attr('class', 'closed').html(lang('turn_open', [that.ruleList[obj.attr('posid')]]));
                            } else {
                                that.canMove['pos' + obj.attr('posid')] = 'block';
                            }
                            that.rtDom.attr('class', 'nodata');
                        }
                        obj.remove();
                        //xGame.toast.show('转换成功！');
                    }
                    break;
                default:
                    xGame.toast.show(lang('exchange_original_soul_fail'));
                    break;
                }

                xGame.unlock();

            };
            if (confirm == undefined) {
                loadContents({
                    'c': 'soul',
                    'm': 'convert',
                    'sid': that.ssid
                },
                Func);
            } else {
                loadContents({
                    'c': 'soul',
                    'm': 'convert',
                    'sid': that.ssid,
                    'confirm': confirm,
                    'merge': (that.isComposite == true ? 1 : 0),
                },
                Func);
            }
        },

        /*装备将魂*/
        equip: function(site, callback, error, refreshPack) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('equip_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('hero_too_low_to_equip'));
                    break;
                case - 2 : xGame.toast.show(lang('get_hero_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('other_hero_is_equip'));
                    break;
                case 3:
                    xGame.toast.show(lang('same_type_only_equip_one'));
                    break;
                case 1:
                    callback();
                    that.page = res.pack.page;
                    that.pages = res.pack.pages;
                    if (refreshPack) {
                        that.fillRb(res.pack, true);
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                if (res.status != 1) {
                    error();
                }
                xGame.unlock();

            };
            loadContents({
                'c': 'soul',
                'm': 'equip',
                'gid': that.shid,
                'sid': that.touchSid,
                'site': site
            },
            Func);
        },

        /*卸下将魂*/
        unequip: function(site, callback, error) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('remove_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('bag_is_full'));
                    break;
                case 1:
                    callback();
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                if (res.status != 1) {
                    error();
                }
                xGame.unlock();

            };
            loadContents({
                'c': 'soul',
                'm': 'unequip',
                'gid': that.shid,
                'sid': that.ssid,
                'site': site
            },
            Func);
        },

        /*获得将领信息*/
        getHeroInfoById: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var sid = that.rbDom.find('ul').find('li').eq(0).attr('sid');

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('get_lvup_rule_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_soul_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    that.ltDom.find('#soulpop_list').find('#soulHid' + that.shid).removeClass('selected');
                    obj.addClass('selected');
                    that.shid = obj.attr('hid');

                    that.ssid = res.soul.id;

                    that.fillLb(res.general);
                    that.fillRt(res.soul);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'soul',
                'm': 'get_general_info',
                'gid': obj.attr('hid'),
                'sid': sid
            },
            Func);
            sid = null;
        },

        /*这个功能的作用是传进参数，检测边界，
         * 返回true时代表有位置可以到达，再设置that.nearlyX和that.nearlyY完成操作；
         * 返回false时就代表直接返回原来的位置
         * 通过计算得知x间隔为133，y间隔为117*/
        checkPos: function(x, y) {
            var that = this;

            /*结果集*/
            var result = [];
            that.nearlyX = that.nearlyY = that.nearlyPosId = 0;

            /*第一步，告诉程序有几个点可以移动，要排除自己*/
            ////console.log(that.canMove);
            for (var i = 1; i < 9; i++) {
                if (that.canMove['pos' + i] != that.touchSid && that.canMove['pos' + i] != undefined) {
                    result.push({
                        'name': 'pos' + i,
                        'id': that.eqPosList['pos' + i].id,
                        'distance': that.getDistance(Math.abs(that.eqPosList['pos' + i].left - x), Math.abs(that.eqPosList['pos' + i].top - y))
                    });
                }
            }
            /*按属性拍大小，方法写在base.js里面*/
            result.sort(helper.compare("distance"));
            if (result.length != 0) {
                that.nearlyPosId = result[0]['id'];
                /*不管如何都返回最近的x，y*/
                that.nearlyX = that.eqPosList[result[0]['name']].left;
                that.nearlyY = that.eqPosList[result[0]['name']].top;

                var myDistance = that.getDistance(Math.abs(that.nearlyX - x), Math.abs(that.nearlyY - y));

                if (myDistance < that.distance / 2) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }

        },

        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },

        /*获得将魂信息*/
        getSoulInfoById: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('get_lvup_rule_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_soul_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    that.rbDom.find('#soulS' + that.ssid).removeClass('soul_ts');
                    that.lbDom.find('#soulpos' + that.ssid).removeClass('soul_ts');
                    that.ssid = obj.attr('sid');
                    that.rbDom.find('#soulS' + that.ssid).addClass('soul_ts');

                    that.fillRt(res.info);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'soul',
                'm': 'get_soul_info',
                'sid': obj.attr('sid')
            },
            Func, true);
        },

        /*将魂升级*/
        levelup: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 6 : xGame.toast.show(lang('get_next_lv_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('deduct_original_soul_fail'));
                    break;
                case - 4 : xGame.toast.show(lang('level_up_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_lvup_rule_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_soul_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    that.fillCurrentSoul(res.info.base_soul);
                    that.rtDom.find('#soul_abnow').find('em').html(res.info.now.addition);
                    that.rtDom.find('#soul_level').html('Lv.' + res.info.now.level);
                    if (res.info.next.ismax == 0) {
                        that.rtDom.find('#soul_abnext').html('(' + lang('next_lv') + ' +' + res.info.next.levelup.addition + ')');
                        that.rtDom.find('#soul_lvneed').html(res.info.next.levelup.price);
                        if (res.info.next.levelup.price > res.info.base_soul) {
                            that.rtDom.find('#soul_lvneed').attr('class', 'buzu');
                        }

                    } else {
                        that.rtDom.find('#soul_levelup').css('display', 'none');
                        that.rtDom.find('#soul_abnext').html(lang('full_lv'));
                        that.rtDom.find('#soul_lvneed').html(lang('no'));
                    }

                    that.showCompositeBtn(res.info.next.merge);

                    break;
                case 2:
                    xGame.toast.show(lang('lv_is_full'));
                    break;
                case 3:
                    xGame.toast.show(lang('original_soul_not_enough'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'soul',
                'm': 'levelup',
                'sid': that.ssid
            },
            Func);
        },

        /*获得解锁价格*/
        unlockPrice: function(pos) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('price_get_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    xGame.confirm.show(lang('unlock'), lang('unlock_box_ask', [res.price, res.number]), 2,
                    function() {
                        that.unlock(pos);
                        xGame.confirm.hide();
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'soul',
                'm': 'get_unlock_price',
                'pos': pos + (that.page - 1) * 12
            },
            Func);
        },

        /*解锁*/
        unlock: function(pos) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('unlock_fail_please_wait'));
                    break;
                case - 3 : xGame.toast.show(lang('deduct_gold_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('price_get_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_too_low_to_unlock'));
                    break;
                case 1:
                    player.updateinfo({
                        'gold':
                        parseInt(res.gold)
                    });
                    for (var i = 0; i < pos; i++) {
                        if (that.rbDom.find('li').eq(i).hasClass('closed')) {
                            that.rbDom.find('li').eq(i).attr('class', 'block').removeAttr('posid');
                        }
                    }
                    if (parseInt(res.pages) == that.pages + 1) {
                        that.rbDom.removeClass('islast');
                    }
                    that.pages = parseInt(res.pages);
                    break;
                case 2:
                    xGame.goPay(lang('no_gold_to_unlock'));
                    break;
                default:
                    xGame.toast.show(lang('wait_unlock'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'soul',
                'm': 'unlock',
                'pos': pos + (that.page - 1) * 12
            },
            Func);
        },

        showPage: function() {

            if (this.isComposite == false) {
                this.rbDom.find('#soul_page_num').html(this.page);
            } else {
                this.rbDom.find('#soul_page_num').html(this.compositePage);
            }
        },

        /*翻页*/
        getPackInfo: function(page) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            if (page <= 0) {
                that.page = 1;
                xGame.unlock();
                return false;
            }

            if (page > that.pages) {

                var html = '';
                for (var i = 0; i < 12; i++) {
                    html += '<li class="closed" posid="' + (i + 1) + '"></li>';
                }
                that.rbDom.find('ul').find('li').remove();
                that.rbDom.find('ul').find('.isunequip').remove();
                that.rbDom.find('ul').append(html);
                html = null;
                /*限制只显示一页*/
                if (page == that.pages + 1) {
                    this.rbDom.attr('class', 'islast');
                    that.page = that.pages + 1;
                } else {
                    that.page = page;
                }
                that.showPage();
                this.rbDom.removeClass('isfirst');
                xGame.unlock();
                return false;
            }

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('get_bag_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    that.page = page;
                    that.fillRb(res.info, true);
                    that.rbDom.find('ul').find('#soulS' + that.ssid).addClass('soul_ts');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c':
                'soul',
                'm': 'get_pack_info',
                'page': page
            },
            Func);
        },

        fillLb: function(data) {
            this.lbDom.find('#body').attr('class', 'q' + data.quality);
            
            this.lbDom.find('#hero_type').html(helper.getItemVocation(data.type));
            this.lbDom.find('#hero_lv').html('Lv.' + data.level);
            this.lbDom.find('#hero_name').html(data.name);
            this.lbDom.find('#hero_img').css('backgroundImage', 'url(' + xGame.avatar(data.image, true) + ')');

            if (data.turn != 0) {
                this.lbDom.find('#hero_turn').html(data.turn).css('display', 'block');
            } else {
                this.lbDom.find('#hero_turn').html(data.turn).css('display', 'none');
            }

            /*每次获得将领信息都要初始化一下canMove*/
            this.canMove = {};
            this.rbDom.find('ul').find('.isequip').remove();

            var html = '';
            var img = '';
            for (var i = 1; i <= 8; i++) {
                if (data['site' + i].islock == 1) {
                    this.lbDom.find('#soulpos' + i).attr('class', 'closed').html(lang('turn_open', [data['site' + i].unlock_level]));
                } else {
                    this.lbDom.find('#soulpos' + i).attr('class', 'open').html('');
                    if (data['site' + i].info.length != 0) {
                        if (data['site' + i].islock == 0) {
                            this.canMove['pos' + i] = data['site' + i].info.id;
                        }

                        html += '<span class="isequip" style="background:url(static/img/pop/soul_' + data['site' + i].info.image + '.png' + ');' + this.eqPosList['pos' + i].style + '" id="soul_span' + data['site' + i].info.id + '" posid="' + i + '" sid="' + data['site' + i].info.id + '" type="' + data['site' + i].info.quality + '" ' + (data['site' + i].islock == 2 ? 'mustlock="true"': '') + '><em>' + helper.getSoulTypeName(parseInt(data['site' + i].info.type)) + '</em></span>';
                    } else {
                        if (data['site' + i].islock == 0) {
                            this.canMove['pos' + i] = 'block';
                        }
                    }
                }
            }
            if (html != '') {
                this.rbDom.find('ul').append(html);
            }
            html = null;
        },

        fillRb: function(data, isRebuild) {

            var soulDom = this.rbDom.find('#soul_span' + this.ssid);
            this.fillCurrentSoul(data.base_soul);

            if (this.isComposite == false) {
                if (this.page == 1) {
                    this.rbDom.attr('class', 'isfirst');
                } else {
                    this.rbDom.removeClass('isfirst');
                }
            } else {
                if (this.compositePage == 1) {
                    this.rbDom.attr('class', 'isfirst');
                } else {
                    this.rbDom.removeClass('isfirst');
                }
            }

            this.rbDom.removeClass('islast');
            this.showPage();

            var html = '';
            var html1 = '';
            for (var i = 0; i < 12; i++) {
                if (i + 1 + (data.page - 1) * 12 <= data.all) {
                    if (data.list[i] != undefined) {
                        html += '<li class="soul_t' + data.list[i].quality + '" sid="' + data.list[i].id + '" id="soulS' + data.list[i].id + '"></li>';
                        html1 += '<span class="isunequip" style="background:url(static/img/pop/soul_' + data.list[i].image + '.png' + ');' + this.uneqPosList['pos' + (i + 1)].style + '" id="soul_span' + data.list[i].id + '" posid="' + (i + 1) + '" sid="' + data.list[i].id + '" type="' + data.list[i].quality + '" ><em>' + helper.getSoulTypeName(parseInt(data.list[i].type)) + '</em></span>';
                    } else {
                        html += '<li class="block"></li>';
                    }
                } else {
                    html += '<li class="closed" posid="' + (i + 1) + '"></li>';
                }
            }
            if (isRebuild == undefined) {
                this.rbDom.find('ul').html(html + html1);
            } else {
                this.rbDom.find('ul').find('li').remove();
                this.rbDom.find('ul').find('.isunequip').remove();
                this.rbDom.find('ul').append(html + html1);
            }

            html = html1 = null;

            if (this.isComposite == true && isRebuild == undefined) {
                this.showCompositeDom(soulDom);
            } else if (this.isComposite == true && isRebuild == true) {
                var equipDom = this.rbDom.find('.isequip');

                if (equipDom.length == 1) {
                    var sid1 = equipDom.eq(0).attr('sid');
                    var unequipDom1 = this.rbDom.find('#soul_span' + sid1 + '.isunequip');

                    if (unequipDom1.length != 0) {
                        this.refreshPackBySid(sid1);
                        unequipDom1.remove();
                    }

                    unequipDom1 = null;

                } else if (equipDom.length == 2) {
                    var sid1 = equipDom.eq(0).attr('sid');
                    var sid2 = equipDom.eq(1).attr('sid');
                    var unequipDom1 = this.rbDom.find('#soul_span' + sid1 + '.isunequip');
                    var unequipDom2 = this.rbDom.find('#soul_span' + sid2 + '.isunequip');

                    if (unequipDom1.length != 0) {
                        this.refreshPackBySid(sid1);
                        unequipDom1.remove();
                    }

                    unequipDom1 = null;

                    if (unequipDom2.length != 0) {
                        this.refreshPackBySid(sid2);
                        unequipDom2.remove();
                    }

                    unequipDom2 = null;
                }

                equipDom = null;
            }

            soulDom = null;
        },

        fillCurrentSoul: function(num) {
            var that = this;

            if (that.leftSoulNum != num) {
                //that.rbDom.find('#soul_own').html(num);
                xGame.doAnimate2(that.rbDom.find('#soul_own'), 'changcolor', 100, 7,
                function() {
                    that.rbDom.find('#soul_own').html(num);
                });
                that.leftSoulNum = parseInt(num);
            } else {
                that.rbDom.find('#soul_own').html(num);
            }
        },

        fillRt: function(data) {

            if (data.id == undefined) {
                this.rtDom.attr('class', 'nodata');
                return false;
            }
            this.rtDom.attr('class', 'hasdata');
            this.rbDom.find('ul').find('.soul_ts').removeClass('soul_ts');
            /*貌似这段代码是没用了
            if (data.from != undefined && data.from > 8) {
                this.rbDom.find('ul').find('li').eq(0).addClass('soul_ts');
            }
            */

            if (data.ismax == 0) {
                this.rtDom.find('#soul_levelup').css('display', 'block');
                this.rtDom.find('#soul_abnow').html(lang('soul_properties') + ':' + helper.getSoulTypeName(parseInt(data.type)) + '+<em>' + data.power + '</em><em id="soul_abnext">(' + lang('next_lv') + '+' + data.levelup.addition + ')</em>');
                this.rtDom.find('#soul_lvneed').html(data.levelup.price);
            } else {
                this.rtDom.find('#soul_levelup').css('display', 'none');
                this.rtDom.find('#soul_abnow').html(lang('soul_properties') + ':' + helper.getSoulTypeName(parseInt(data.type)) + '+<em>' + data.power + '</em><em id="soul_abnext">' + lang('full_lv') + '</em>');
                this.rtDom.find('#soul_lvneed').html(lang('no'));
            }

            this.rbDom.find('ul').find('#soulS' + this.ssid).removeClass('soul_ts');
            this.ssid = data.id;
            this.rbDom.find('ul').find('#soulS' + this.ssid).addClass('soul_ts');

            this.rtDom.find('#soul_name').html(data.name + '<em id="soul_level" style="color:' + helper.getItemColor(data.quality) + ';" >Lv.' + data.level + '</em>').css('color', helper.getItemColor(data.quality));
            this.rtDom.find('#selected_soul').attr('class', 'soul_t' + data.quality + ' soul_ts').find('span').css('background', 'url(static/img/pop/soul_' + data.image + '.png' + ') 50% 50% no-repeat');

            if (data.levelup.price > this.leftSoulNum) {
                this.rtDom.find('#soul_lvneed').attr('class', 'buzu');
            } else {
                this.rtDom.find('#soul_lvneed').removeClass('buzu');
            }

            this.showCompositeBtn(data.merge);
        },

        // 显示合成按钮
        showCompositeBtn: function (merge) {
            
            var that = this;

            if (parseInt(merge) == 1) { // 可合成
                that.rtDom.find('#soul_composite').show();
                that.rtDom.find('.soul_can_composite').show();
                that.rtDom.find('.soul_lvneed').hide();
            } else {
                that.rtDom.find('#soul_composite').hide();
                that.rtDom.find('.soul_can_composite').hide();
                that.rtDom.find('.soul_lvneed').show();
            }
        },

        // 显示合成将魂界面
        showCompositeDom: function (soulDom) {
            
            var that = this;

            if (that.isComposite == false) {
                return false;
            }

            that.canMove = {
                'pos1': that.ssid,
                'pos2': 'block',
            };

            var dom = that.rbDom.find('#soul_span' + that.ssid);

            if (dom.length != 0) {
                soulDom = dom;

                if (soulDom.hasClass('isunequip')) {
                    that.refreshPackBySid(that.ssid);
                }

                soulDom.remove();
            }

            dom = null;
            that.rbDom.find('ul').find('.isequip').remove();
            html = '<span id="soul_span'+ that.ssid +'" class="isequip" style="background-image:' + soulDom.css('background-image') + ';' + that.compositePosList['pos1'].style + '" posid="' + 1 + '" sid="' + that.ssid + '" type="'+ 5 +'"><em>'+ soulDom.children().html() +'</em></span>';
            that.rbDom.find('ul').append(html);
            html = null;
            soulDom = null;

            that.eqPosList = that.compositePosList;

            that.lbDom.hide();
            that.compositeDom.show();
        },

        showCompositeAnim1: function () {
            
            var that = this;

            var anim = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 0, top: 0, width: that.compositeDom.width(), height: that.compositeDom.height()},
                'img': ximg(staticUrl + 'img/pop/soulpop_composite_bomb.png'),
                'imgOffset': [{'left': -9, 'top': 148}, {'left': 221, 'top': 148}],
                'width': 200,
                'height': 200,
                'zIndex': 40,
                'loop': false,
                'endDestroy': true,
                'anim': [
                    {rect: [0, 0], interval: 80},
                    {rect: [200, 0], interval: 80},
                    {rect: [400, 0], interval: 80},
                    {rect: [600, 0], interval: 80},
                    {rect: [800, 0], interval: 80},
                    {rect: [1000, 0], interval: 80},
                    {rect: [1200, 0], interval: 80},
                    {rect: [1400, 0], interval: 80},
                ],
                'callback': function () {
                    that.rbDom.find('ul').find('.isequip').remove();
                    that.showCompositeAnim2();
                },
            });

            anim.appendTo(that.compositeDom);
            anim.play();
        },

        showCompositeAnim2: function () {
            
            var that = this;

            var anim = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 0, top: 0, width: that.compositeDom.width(), height: that.compositeDom.height()},
                'img': ximg(staticUrl + 'img/pop/soulpop_composite_turn.png'),
                'imgOffset': [{'left': 64, 'top': 30}, {'left': 169, 'top': 30, 'scaleX': -1}],
                'width': 180,
                'height': 250,
                'zIndex': 40,
                'loop': false,
                'endDestroy': true,
                'anim': [
                    {rect: [0, 0], interval: 80},
                    {rect: [180, 0], interval: 80},
                    {rect: [360, 0], interval: 80},
                    {rect: [540, 0], interval: 80},
                    {rect: [720, 0], interval: 80},
                    {rect: [900, 0], interval: 80},
                    {rect: [1080, 0], interval: 80},
                    {rect: [1260, 0], interval: 80},
                ],
                'callback': function () {
                    that.showCompositeAnim3();
                },
            });
            
            anim.appendTo(that.compositeDom);
            anim.play();
        },

        showCompositeAnim3: function () {
            
            var that = this;

            var anim = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 0, top: 0, width: that.compositeDom.width(), height: that.compositeDom.height()},
                'img': ximg(staticUrl + 'img/pop/soulpop_composite_result.png'),
                'imgOffset': [{'left': 130, 'top': -5}],
                'width': 150,
                'height': 153,
                'zIndex': 40,
                'loop': false,
                'endDestroy': true,
                'anim': [
                    {rect: [0, 0], interval: 80},
                    {rect: [150, 0], interval: 80},
                    {rect: [300, 0], interval: 80},
                    {rect: [450, 0], interval: 80},
                    {rect: [600, 0], interval: 80},
                    {rect: [750, 0], interval: 80},
                    {rect: [900, 0], interval: 80},
                    {rect: [1050, 0], interval: 80, callback: function () {
                        that.getSoulInfo(that.compositeResult);
                    }},
                    {rect: [0, 153], interval: 80},
                    {rect: [150, 153], interval: 80},
                    {rect: [300, 153], interval: 80},
                    {rect: [450, 153], interval: 80},
                    {rect: [600, 153], interval: 80},
                    {rect: [750, 153], interval: 80},
                    {rect: [900, 153], interval: 80},
                    {rect: [1050, 153], interval: 80},
                    {rect: [0, 306], interval: 80},
                    {rect: [150, 306], interval: 80},
                    {rect: [300, 306], interval: 80},
                    {rect: [450, 306], interval: 80},
                    {rect: [600, 306], interval: 80},
                    {rect: [750, 306], interval: 80},
                    {rect: [900, 306], interval: 80},
                    {rect: [1050, 306], interval: 80},
                    {rect: [0, 459], interval: 80},
                    {rect: [150, 459], interval: 80},
                    {rect: [300, 459], interval: 80},
                    {rect: [450, 459], interval: 80},
                    {rect: [600, 459], interval: 80},
                    {rect: [750, 459], interval: 80},
                ],
                'callback': function () {
                    xGame.unlock();
                    miscpop.showSoulInfo(that.compositeResult, true, function () {
                        that.getSoulInfo();
                    });
                },
            });

            anim.appendTo(that.compositeDom);
            anim.play();
        },

        // 获取合成的将魂信息
        getSoulInfo: function (soul) {
            
            var that = this;

            var soulDom = that.compositeDom.find('#composite_result');

            if (soul != null) {
                soulDom.children('.img').css('background-image', 'url(static/img/pop/soul_' + soul.image + '.png' + ')');
                soulDom.children('.name').html(helper.getSoulTypeName(parseInt(soul.type)));
            } else {
                soulDom.children('.img').css('background-image', '');
                soulDom.children('.name').html('');
            }

            soulDom = null;
        },

        // 获取背包中的将魂信息
        getCompositePack: function (page, click) {
            
            var that = this;

            if (page <= 0) {
                that.compositePage = 1;
                xGame.unlock();
                return false;
            }

            if (page > that.compositePages) {

                var html = '';
                for (var i = 0; i < 12; i++) {
                    html += '<li class="closed" posid="' + (i + 1) + '"></li>';
                }
                that.rbDom.find('ul').find('li').remove();
                that.rbDom.find('ul').find('.isunequip').remove();
                that.rbDom.find('ul').append(html);
                html = null;
                /*限制只显示一页*/
                if (page == that.compositePages + 1) {
                    this.rbDom.attr('class', 'islast');
                    that.compositePage = that.compositePages + 1;
                } else {
                    that.compositePage = page;
                }
                that.rbDom.find('#soul_page_num').html(that.compositePage);
                that.rbDom.removeClass('isfirst');
                xGame.unlock();
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.isComposite = true;
                    that.compositePage = res.pack.page;
                    that.compositePages = res.pack.pages;

                    if (click == true) {
                        that.fillRb(res.pack, true);
                    } else {
                        that.fillRb(res.pack);
                    }

                    that.rbDom.find('ul').find('#soulS' + that.ssid).addClass('soul_ts');
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'soul',
                'm': 'merge_index',
                'page': page
            }, render);
        },

        // 合成将魂
        compositeSoul: function (sid1, sid2) {
        
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {

                if (parseInt(res.status) == 1) {
                    that.canMove = {
                        'pos1': 'block',
                        'pos2': 'block',
                    };
                    that.compositeResult = res.new_soul;
                    that.fillRb(res.pack, true);
                    that.showCompositeAnim1();
                } else {
                    xGame.unlock();
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'soul',
                'm': 'merge_action',
                'sid1': sid1,
                'sid2': sid2,
            }, render);
        },

        // 刷新界面
        refreshPage: function () {
            
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    that.initAngin(res);
                } else {
                    var error_msg = '';
                    switch (res.status) {
                    case - 1 : error_msg = lang('lv_too_low_to_ent_soul');
                        break;
                    case - 2 : error_msg = lang('your_hero_all_sleep');
                        break;
                    case - 3 : error_msg = lang('get_soul_bag_fail');
                        break;
                    default:
                        error_msg = lang('wait_to_enter_soul');
                        break;
                    }
                    xGame.toast.show(error_msg);
                    return false;
                }
                that = null;
            }
            loadContents({
                'c': 'soul',
                'm': 'index'
            },
            Func);
        },

        initAngin: function (res) {
            
            var that = this;
            that.page = 1;
            that.pages = res.pack.pages;

            that.leftSoulNum = parseInt(res.pack.base_soul);
            that.fillRb(res.pack);
            that.fillRt(res.soul);

            var html1 = '';

            for (var i = 1; i <= res.general_totals; i++) {
                if (i == 1) {
                    that.shid = res.general[i].id;
                    that.fillLb(res.general[i]);
                    html1 += '<li id="soulHid' + res.general[i].id + '" hid="' + res.general[i].id + '" class="q' + res.general[i].quality + ' selected"><img  src="' + xGame.avatar(res.general[i].image) + '" /></li>';
                } else {
                    html1 += '<li id="soulHid' + res.general[i].id + '" hid="' + res.general[i].id + '" class="q' + res.general[i].quality + '"><img  src="' + xGame.avatar(res.general[i].image) + '" /></li>';
                }
            }
            that.ltDom.find('#soulpop_list').width(res.general_totals * 113 + 'px').html(html1);
            html1 = null;
            $('#worldloading').removeClass('black').hide();
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#soul_hide').unbind('touchend');
                this.e.find('#soul_detail_hide').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.remove();
                this.e = null;
            }
            if (this.ltDom) {
                this.ltDom.find('#soulpop_list').undelegate('li', 'click');
                this.ltDom.remove();
                this.ltDom = null;
            }
            if (this.rbDom) {
                this.rbDom.find('ul').undelegate('li.closed', 'click');
                this.rbDom.find('#soul_prev').undelegate('.pop_btn_small', 'touchend');
                this.rbDom.find('#soul_next').undelegate('.pop_btn_small', 'touchend');
                this.rbDom.find('ul').undelegate('span', 'touchstart');
                this.rbDom.find('ul').undelegate('span.ismoving', 'touchend');
                this.rbDom.remove();
                this.rbDom = null;
            }
            if (this.rtDom) {
                this.rtDom.find('#soul_change').undelegate('.pop_btn_small', 'touchend');
                this.rtDom.find('#soul_levelup').undelegate('.pop_btn_small', 'touchend');
                this.rtDom.remove();
                this.rtDom = null;
            }
            if (this.lbDom) {
                this.lbDom.find('#go_detail').undelegate('.pop_btn_small', 'touchend');
                this.lbDom.find('#go_tower').undelegate('.pop_btn_small', 'touchend');
                this.lbDom.remove();
                this.lbDom = null;
            }
            if (this.compositeDom) {
                this.compositeDom.find('#composite_info').off();
                this.compositeDom.find('#composite_close').off();
                this.compositeDom.remove();
                this.compositeDom = null;
            }
            if (this.infoDom) {
                this.infoDom.find('#info_hide_btn').off();
                this.infoDom.find('.mask').off();
                this.infoDom.remove();
                this.infoDom = null;
            }
            
        },

        clear: function() {
            xGame.unlock();
            if (this.e != null) {

                this.shid = this.ssid = this.leftSoulNum = this.uneqPosList = this.eqPosList = this.zIndex = this.touchSid = this.touchPos = this.canMove = this.nearlyX = this.nearlyY = this.nearlyPosId = this.distance = this.page = this.pages = this.ruleList = this.compositePosList = this.heroPosList = null;
                this.isComposite = false;
                this.compositePage = 1;
                this.compositePages = 1;
                if (this.soulScroller) {
                    this.soulScroller.destroy();
                    delete this.soulScroller;
                }

            }
            this.unbindAll();
            player.reloadInfo();
        }
    };
})();