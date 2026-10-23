/* 将领 */
(function() {
    heroPopClass = function() {
        this.e;
        this.isshow = false;
        this.data;
        this.myScroll;
        this.bottomDom;

        this.nowHeroId; //当前查看的武将id
        this.nowHero; //当前武将信息
        this.page = 1; //当前道具列表页码数
        this.equipments = null; //当前页面的装备列表
        this.nowItem = null; //movingObj
        this.nowEid = 0;

        this.elePositions = null;
        this.canmoves = null; //可以移动到的位置s
        this.tree;
        this.step = 0;  // 引导续接的步数
        this.anim = [];
        this.attributeBtn = null; // 装备属性按钮
        this.nowItemId = null; // 当前装备的Id
        this.skillScroll = null;
    };
    heroPopClass.prototype = {
        init: function(step) {
            var that = this;
            that.step = step;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_hero_list'));
                    $('#worldloading').hide();
                    break;
                case 2:
                    xGame.toast.show(lang('have_no_hero_ready'));
                    $('#worldloading').hide();
                    break;
                case 3:
                    xGame.toast.show(lang('hero_no_equip'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    //console.log('成功');
                    /* 保存数据 */
                    that.data = res;

                    /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                    that.e = xGame.loadPopTmpl('hero');
                    that.attributeBtn = that.e.find('#hero_attribute_btn');
                    that.e.find('#hidebtn').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        heropop.hide();
                        e.preventDefault();
                        e.stopPropagation();
                    });
                    if (typeof res.tree != 'undefined') {
                        that.tree = parseInt(res.tree);
                    } else {
                        that.tree = 0;
                    }
                    /* 第二步 构建数据列表等 */
                    /* 顶部武将列表 */
                    var html = '';
                    var n = 0;
                    for (var i in res.list) {
                        var item = res.list[i];
                        //html += '<li id="hero' + item.id + '" class="heroitem' + (n == 0 ? ' selected' : '') + '" heroid="' + item.id + '" herolv="' + item.level + '" ><span class="avatar" style="background:url(' + staticUrl + 'img/avatar/' + item.image + '.png) center center no-repeat;' + '"></span><p class="name">' + item.name+ '</p></li>';
                        html += '<li id="hero' + item.id + '" class="q' + item.quality + ' heroitem' + (n == 0 ? ' selected': '') + '" heroid="' + item.id + '" herolv="' + item.level + '" ><span class="avatar" style="background:url(' + xGame.avatar(item.image) + ') center center no-repeat;' + '"></span><p class="name">' + item.name + '</p></li>';
                        n++;
                    }
                    that.e.find('#hero_list_scroller').html(html);

                    /* 创建滑动门 domid, 单页宽度 */
                    that.myScroll = xGame.scroller('heropop_scroller', n * 120);

                    //绑定滑动门左右按钮
                    that.e.find('#scroller_btn_left').unbind('touchend').bind('touchend',
                    function() {
                        that.myScroll.scrollToPage('prev', 0);
                        return false;
                    });
                    that.e.find('#scroller_btn_right').unbind('touchend').bind('touchend',
                    function() {
                        that.myScroll.scrollToPage('next', 0);
                        return false;
                    });
                    /* 第三步 获取默认数据*/
                    that.bottomDom = that.e.find('#hero_bottom_wrap');

                    /*第一次通过index接口获得的数据看将领信息*/
                    var firstdata = helper.array_first(that.data.list);
                    if (firstdata) {
                        that.nowHero = firstdata; //当前将领数据
                        that.nowHeroId = firstdata.id; //当前将领ID
                        that.viewHero(firstdata);
                        that.playAnim(that.bottomDom, firstdata.achID);
                    }

                    /* 绑定武将头像 */
                    that.e.find('#hero_list_scroller').undelegate('li', 'click').delegate('li', 'click',
                    function() {
                        if (xGame.checkLock(true)) { //不设置属性
                            return false;
                        }
                        var newHeroid = $(this).attr('heroid');
                        if (newHeroid == that.nowHeroId) {
                            return false;
                        }
                        that.e.find('#hero' + that.nowHeroId).removeClass('selected');
                        $(this).addClass('selected');
                        that.nowHeroId = newHeroid;
                        that.viewHero();
                    });

                    /* 绑定上下页 */
                    $('#prevpage').unbind('touchend').bind('touchend',
                    function() {
                        that.itemlist_page('prev');
                    });
                    $('#nextpage').unbind('touchend').bind('touchend',
                    function() {
                        that.itemlist_page('next');
                    });
                    /* 绑定道具事件 */
                    that.bindItems();
                    //显示第一个道具
                    that.displayFirstItem();

                    /* 设置拖动区域 */
                    that.canmoves = {
                        'left': {
                            1 : {
                                'left': 26,
                                'top': -10
                            },
                            2 : {
                                'left': 26,
                                'top': 120
                            },
                            3 : {
                                'left': 314,
                                'top': -10
                            },
                            4 : {
                                'left': 314,
                                'top': 120
                            },
                        },
                        'right': {
                            1 : {
                                'left': 450,
                                'top': 0
                            },
                            2 : {
                                'left': 572,
                                'top': 0
                            },
                            3 : {
                                'left': 694,
                                'top': 0
                            },
                            4 : {
                                'left': 816,
                                'top': 0
                            },
                            5 : {
                                'left': 450,
                                'top': 150
                            },
                            6 : {
                                'left': 572,
                                'top': 150
                            },
                            7 : {
                                'left': 694,
                                'top': 150
                            },
                            8 : {
                                'left': 816,
                                'top': 150
                            },
                        },
                    };

                    /* 第五步 展示弹窗 */
                    that.e.show();
                    $('#worldloading').hide();

                    xGame.showpop('system', {
                        'pop': 'hero',
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
                'general',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        guideShow: function () {

            var that = this;

            if (xGame.guide) {
                switch (guide.now) {
                    case 8:
                        guide.show(801);
                        break;
                    case 110:
                        guide.show(811);
                        break;
                    case 210:
                        guide.show(821);
                        break;
                    case 608:
                        guide.show(831);
                        break;
                    case 618:
                        guide.show(841);
                        break;
                    case 716:
                        guide.show(851);
                        break;
                    case 1409:
                        guide.show(861);
                        break;
                    default:
                        break;
                }

                // 引导续接
                if (that.step) {
                    if (miscpop.in_array(that.step, [832, 833, 842, 843, 852, 853])) {
                        var hero = $('#hero_list_scroller').find('.heroitem:eq(1)');
                        hero.trigger('click');
                        hero = null;
                    }
                    guide.show(that.step);
                }
            }
        },

        bindItems: function() {

            var that = this;
            
            this.elePositions = {
                't': '',
                'p': 0
            }; //元素初始属性: t:左右, p:位置
            var ox = 0;
            var oy = 0;

            var target = null;
            /* 绑定道具列表，拖动 */
            this.bottomDom.undelegate('.moveitem', 'touchstart').delegate('.moveitem', 'touchstart',
            function(event) {
                if ($(this).hasClass('equiped')) { //左边的
                    heropop.elePositions = {
                        't': 'left',
                        'p': $(this).attr('eid'),
                        'eid': $(this).attr('eid')
                    };
                    heropop.showItemEquiped($(this));
                } else {
                    heropop.elePositions = {
                        't': 'right',
                        'p': $(this).attr('rp'),
                        'eid': $(this).attr('eid')
                    };
                    heropop.showItemUnequip($(this));
                }
                if ($(this).hasClass('islock')) { //不可装备的道具禁止拖拽
                    return false;
                }
                
                //拖动时隐藏右下图标
                $(this).find('#heropop_hammer').hide();

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).css({
                    'z-index': 100
                }).addClass('ismoving');
                
                target = $(this);

            });
            
            this.e.unbind('touchmove').bind('touchmove',function(event) {
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var ele_orig_XY = heropop.canmoves[heropop.elePositions.t][heropop.elePositions.p]; //原始位置
                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ele_orig_XY.left + newX;
                    var realY = ele_orig_XY.top + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                    target.find('p').hide();
                }
            });
            
            this.bottomDom.undelegate('.moveitem.ismoving', 'touchend').delegate('.moveitem.ismoving', 'touchend',
            function(event) {
                target = null;
                //显示右下图标
                $(this).find('#heropop_hammer').show();

                heropop.checkPositions($(this));
                ox = 0;
                oy = 0;
                $(this).css({
                    'z-index': 0
                }).removeClass('ismoving');

            });

            that.attributeBtn.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                miscpop.showForgeEquipInfo(that.nowItemId);
                e.preventDefault();
            });
        },

        /* 检查是否移动到了目标位置 */
        checkPositions: function(obj) {
            var objPositions = null;

            /* 寻找位置 */
            //元素当前的left,top
            var nowPos = {
                'left': parseInt(obj.css('left').replace('px', '')),
                'top': parseInt(obj.css('top').replace('px', ''))
            };

            /* 1.卸下的操作 */
            if (heropop.elePositions.t == 'left') {

                if (nowPos.left < 420) { //在左边区域，不操作
                    console.log('没有达到右边区域，不操作');
                    var ele_orig_XY = heropop.canmoves[heropop.elePositions.t][heropop.elePositions.p]; //原始位置
                    obj.animate({
                        'left': ele_orig_XY.left + 'px',
                        'top': ele_orig_XY.top + 'px'
                    },
                    100);
                    obj.find('p').hide();
                } else { //到了右边区域，卸下

                    console.log('准备卸下装备，开始寻找位置');
                    var objNewPos = 1; //默认第一个
                    var rightItems = heropop.bottomDom.find('.unequip');
                    if (rightItems.size() == 8) { //如果满了，干掉最后一个
                        heropop.bottomDom.find('.moveitem.right8').remove();
                        objNewPos = 8;
                    } else if (rightItems.size() > 0) { //如果至少有一个，挨个循环，找个空位即可
                        for (var i = 1; i <= 8; i++) {
                            var box = heropop.bottomDom.find('.unequip.right' + i);
                            if (box.length <= 0) { //找到空位
                                objNewPos = i;
                                break;
                            }
                        }
                    }
                    obj.animate({
                        'left': heropop.canmoves['right'][objNewPos].left + 'px',
                        'top': heropop.canmoves['right'][objNewPos].top + 'px'
                    },
                    100, 'ease',
                    function() {
                        $(this).attr({
                            'class': 'moveitem right' + objNewPos + ' unequip',
                            'rp': objNewPos
                        });
                        $(this).find('p').show();
                        heropop.unequip2();
                    });

                }

                /* 2.装备的操作 */
            } else {
                var toPos = heropop.elePositions.eid;
                var box = heropop.canmoves.left[toPos];
                //if(nowPos.left + 50 > box.left && nowPos.left - 50 < box.left && nowPos.top + 50 > box.top && nowPos.top - 50 < box.top) {
                if (nowPos.left < 420) { //精简判断，上面的是精确判断
                    objPositions = {
                        'left': heropop.canmoves.left[toPos].left,
                        'top': heropop.canmoves.left[toPos].top
                    };
                }
                //结果
                if (objPositions == null) {
                    var ele_orig_XY = heropop.canmoves[heropop.elePositions.t][heropop.elePositions.p]; //原始位置
                    obj.animate({
                        'left': ele_orig_XY.left + 'px',
                        'top': ele_orig_XY.top + 'px'
                    },
                    100);
                    obj.find('p').show();

                } else {
                    console.log('找到目标位置，移动过去');
                    /* 再判断目标位置是否已装备道具 */
                    var equipedObj = heropop.bottomDom.find('.moveitem.left' + toPos + '.equiped');
                    if (equipedObj.length > 0) {
                        console.log('目标位置已装备道具，寻找右边一个空位，移开');

                        var eid = parseInt(equipedObj.attr('eid'));
                        var data = heropop.nowHero['eid' + eid];
                        var endIndex = parseInt(helper.array_end_key(heropop.equipments)) + 1;
                        heropop.equipments[endIndex] = data;
                        delete heropop.nowHero['eid' + eid];

                        var equipedObjNewPos = obj.attr('rp');
                        equipedObj.animate({
                            'left': heropop.canmoves['right'][equipedObjNewPos].left + 'px',
                            'top': heropop.canmoves['right'][equipedObjNewPos].top + 'px'
                        },
                        80, 'ease',
                        function() {
                            $(this).attr({
                                'class': 'moveitem right' + equipedObjNewPos + ' unequip',
                                "rp": equipedObjNewPos,
                                "k": endIndex
                            });
                            //heropop.showItemUnequip(heropop.bottomDom.find('.right' + equipedObjNewPos + '.unequip'));
                        });
                    }

                    var data = heropop.equipments[parseInt(obj.attr('k'))];
                    heropop.nowHero['eid' + toPos] = data;
                    //delete heropop.equipments[parseInt(obj.attr('k'))];

                    obj.animate({
                        'left': objPositions.left + 'px',
                        'top': objPositions.top + 'px'
                    },
                    100, 'ease',
                    function() {
                        $(this).attr({
                            'class': 'moveitem left' + toPos + ' equiped'
                        });
                        heropop.equip2();
                        //heropop.viewHero();
                        //heropop.showItemEquiped(heropop.bottomDom.find('.left' + toPos + '.equiped'));
                        //heropop.equip2();
                    });
                    /*
                    if(type == 'unequip') {
                        var data = that.equipments[k];
                    } else if(type == 'equiped') {
                        var data = that.nowHero['eid'+k];
                    }*/
                }
            }

        },

        displayFirstItem: function(item) {
            var that = this;

            if (item == null) {
                item = '.moveitem';

                //检查是否相同位置也有道具
                var have_same_eid = null;
                if (that.nowEid > 0) {
                    $(item).each(function() {
                        if ($(this).hasClass('left' + that.nowEid)) {
                            have_same_eid = $(this);
                        }
                    });
                }
                if (have_same_eid != null) {
                    var firstItem = have_same_eid;
                } else {
                    var firstItem = $(item)[0];
                }
            } else {
                var firstItem = $(item)[0];
            }

            if (firstItem) {
                if ($(firstItem).hasClass('equiped')) {
                    that.showItemEquiped($(firstItem));
                } else {
                    that.showItemUnequip($(firstItem));
                }
            }
        },

        /* 查看未装备的道具 */
        showItemUnequip: function(obj) {
            var that = this;

            var k = obj.attr('k');
            if (k) {
                //清理之前的样式
                that.clearDetailBox();

                that.nowItem = obj;
                var p = that.nowItem.attr('rp');
                that.bottomDom.find('#itembg' + p).addClass('selected');

                that.fillDetailBox('unequip', k);

            }

            that = null;
        },

        /* 查看身上的道具 */
        showItemEquiped: function(obj) {
            var that = this;

            var k = obj.attr('eid');
            if (k) {
                //清理之前的样式
                that.clearDetailBox();

                that.nowItem = obj;
                that.nowEid = k;
                that.bottomDom.find('#eid' + k).attr({
                    "class": 'eid' + k + ' c_s_i_bj' + that.nowHero['eid' + k].quality + ' selected'
                });

                that.fillDetailBox('equiped', k);
            }

            that = null;
        },

        clearDetailBox: function() {
            var that = this;

            if (that.nowItem != null) {
                if (that.nowItem.attr('rp')) {
                    that.bottomDom.find('#itembg' + that.nowItem.attr('rp')).removeClass('selected');
                }
                if (that.nowItem.attr('eid')) {
                    var eid = that.nowItem.attr('eid');
                    that.bottomDom.find('#eid' + eid).removeClass('selected');
                }
            }

            that = null;
        },

        fillDetailBox: function(type, k) {
            var that = this;

            var dom = $('#hero_dress');
            if (type == 'unequip') {
                var data = that.equipments[k];
            } else if (type == 'equiped') {
                var data = that.nowHero['eid' + k];
            }
            if (data) {
                that.nowItemId = data.id;
                
                dom.find('.hero_equi').children('.pic').css({
                    'background-image': 'url(' + xGame.itemurl(data.equipid) + ')'
                });
                dom.find('.name').css({
                    'color': helper.getItemColor(data.quality)
                }).html(data.name);
                dom.find('.hero_equi').attr('class','hero_equi q' + data.quality);
                dom.find('#equi_lv').children('span').text(data.needlevel).css('color', data.available == 0 ? 'red': '#000000');
                dom.find('#strong_lv').children('span').text(data.level);
                dom.find('#atk_type').html(helper.getItemPowerName(parseInt(data.type)) + '：<span>' + data.powerup + '</span>');
                dom.find('#equi_type').children('span').text(helper.getItemVocation(data.type));

                if (type == 'unequip') {
                    dom.find('.hero_equi').children('.isequiped').hide();
                    dom.find('button').text(lang('equip')).unbind('touchend').bind('touchend',
                    function() {
                        heropop.equip();
                    });
                } else {
                    dom.find('.hero_equi').children('.isequiped').show();
                    dom.find('button').text(lang('remove')).unbind('touchend').bind('touchend',
                    function() {
                        heropop.unequip2();
                    });
                }

                // 装备的锻造品级
                var newQuality = parseInt(data.newquality);

                if (newQuality) {
                    that.attributeBtn.show();
                    dom.find('.grade').show();
                    dom.find('.grade_des').css('color', helper.getItemColor(newQuality + 1)).html(helper.getNewEquipInfo(newQuality, 'title'));
                } else {
                    that.attributeBtn.hide();
                    dom.find('.grade').hide();
                }

                dom.show();
            }

            that = null;
        },

        /*卸下装备方法*/
        unequip2: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            if (that.nowItem == null || that.nowItem.attr('eid') == null) {
                xGame.unlock();
                console.log('没有选择要卸载的道具');
                return;
            }
            var leftPos = that.nowItem.attr('eid');
            var leftId = that.nowHero['eid' + leftPos].id;

            var Func = function(res) {
                xGame.unlock();
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('remove_fail'));
                    break;
                case 2:
                    xGame.toast.show(lang('you_have_no_this_equip'));
                    break;
                case 1:
                    //xGame.toast.show('您已成功卸下装备！');
                    /* reload */
                    that.reviewHero(res);
                    break;
                    /*判断右边是否有8个道具，是的话，先删除最后一个*/
                    var rightItems = that.bottomDom.find('.unequip');
                    if (rightItems.size() == 8) {
                        that.bottomDom.find('.right8').remove();
                    }
                    /*所有更改class，留出第一个位置*/
                    var n = 2;
                    rightItems.each(function() {
                        $(this).attr({
                            'class': 'moveitem right' + n + ' unequip'
                        });
                        n++;
                    });
                    /* 把道具移到右边 */
                    that.bottomDom.find('.left' + leftPos).attr({
                        'class': 'moveitem right1 unequip'
                    });
                    that.clearDetailBox();
                    $('#hero_dress').hide();

                    //xGame.toast.show('您已成功卸下装备！');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
            };
            loadContents({
                'c':
                'general',
                'm': 'unequip',
                'gid': that.nowHeroId,
                'eid': leftId,
                'position': leftPos
            },
            Func); //加载数据
            Func = null;
        },

        /*装备方法*/
        equip: function() {
            var that = this;

            var obj = that.nowItem;
            var objdata = that.equipments[parseInt(obj.attr('k'))];
            if (objdata.available == 0) {
                xGame.toast.show(lang('equip_item_when_lv', [objdata.needlevel]));
                return;
            }
            var toPos = obj.attr('eid');

            var objPositions = {
                'left': heropop.canmoves.left[toPos].left,
                'top': heropop.canmoves.left[toPos].top
            };

            var equipedObj = heropop.bottomDom.find('.moveitem.left' + toPos + '.equiped');
            if (equipedObj.length > 0) {
                console.log('目标位置已装备道具，寻找右边一个空位，移开');

                var eid = parseInt(equipedObj.attr('eid'));
                var data = heropop.nowHero['eid' + eid];
                var endIndex = parseInt(helper.array_end_key(heropop.equipments)) + 1;
                heropop.equipments[endIndex] = data;
                delete heropop.nowHero['eid' + eid];

                var equipedObjNewPos = obj.attr('rp');
                equipedObj.animate({
                    'left': heropop.canmoves['right'][equipedObjNewPos].left + 'px',
                    'top': heropop.canmoves['right'][equipedObjNewPos].top + 'px'
                },
                100, 'ease',
                function() {
                    $(this).attr({
                        'class': 'moveitem right' + equipedObjNewPos + ' unequip',
                        "rp": equipedObjNewPos,
                        "k": endIndex
                    });
                });
            }

            heropop.nowHero['eid' + toPos] = objdata; //当前装备的道具

            obj.animate({
                'left': objPositions.left + 'px',
                'top': objPositions.top + 'px'
            },
            200, 'ease',
            function() {
                $(this).attr({
                    'class': 'moveitem left' + toPos + ' equiped'
                });
                heropop.equip2();
            });
        },

        equip2: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            if (that.nowItem == null || that.nowItem.attr('k') == null) {
                console.log('没有选择要装备的道具');
                xGame.unlock();
                return;
            }
            var _k = that.nowItem.attr('k');
            var equipid = that.equipments[_k].id;
            var pos = that.equipments[_k].type;

            if (that.equipments[_k].available == 0) {
                xGame.toast.show(lang('equip_item_when_lv', [that.equipments[_k].needlevel]));
                return;
            }
            var Func = function(res) {
                xGame.unlock();

                switch (parseInt(res.status)) {

                case - 1 : xGame.toast.show(lang('hero_info_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('lv_can_not_equip'));
                    break;
                case - 3 : xGame.toast.show(lang('equip_fail'));
                    break;
                case 1:
                    //xGame.toast.show('您已成功装备该道具！');
                    /* reload */
                    that.reviewHero(res);
                    //新手引导判断
                    xGame.showGuide([801, 811, 821, 832, 842, 852]);
                    /*
                        if(xGame.guide == true && guide.now > 0) {
                            if(guide.now == 9) {//第1把点击关闭按钮
                                guide.show(10);
                            } else if(guide.now == 21) {//第2把点击关闭按钮
                                guide.show(22);
                            } else if(guide.now == 33) {//第3把点击关闭按钮
                                guide.show(34);
                            }
                        }*/

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
            };
            loadContents({
                'c':
                'general',
                'm': 'equip',
                'gid': that.nowHeroId,
                'eid': equipid
            },
            Func); //加载数据
            Func = null;
        },

        attrinfo_show: function() {
            var hero_wrap_dom1 = this.e.find('.hero_attribute');
            hero_wrap_dom1.show();

            xGame.doAnimate(hero_wrap_dom1, 'fadeInLeft', '0.25s',
            function() {
                xGame.unlock();
                hero_wrap_dom1 = null;
            });
        },

        attrinfo_hide: function() {
            var hero_wrap_dom2 = $('#heropop_wrap').find('.hero_attribute');

            xGame.doAnimate(hero_wrap_dom2, 'fadeOutLeft', '0.5s',
            function() {
                hero_wrap_dom2.hide();
                hero_wrap_dom2 = null;
                xGame.unlock();

            });
        },

        /*显示左边玩家信息，以及道具装备状况*/
        buildBottomLeft: function(data, equipments) {
            var that = this;

            //先清空当前界面所有道具
            var hero_wrap_dom = $('#heropop_wrap');
            hero_wrap_dom.find('.moveitem').remove();
            hero_wrap_dom.find('.itembg').remove();
            hero_wrap_dom.find('#hero_dress').hide();
            hero_wrap_dom.find('#attribute').attr({
                'status': '0'
            }).html(lang('view_properties')); //status:0 关闭
            hero_wrap_dom.find('.hero_attribute').hide();
            that.nowAnimPos = [];
            that.hammerQuality = [];

            var html = '';
            /*四个装备信息*/
            for (var i = 1; i <= 4; i++) {

                if (data['eid' + i] != '0' && data['eid' + i] != undefined) {
                    var _color = helper.getItemColor(data['eid' + i].quality);
                    var hammer = data['eid' + i].hammer;
                    var hammerQuality = data['eid' + i].hammer_quality;

                    html += '<div class="moveitem left' + i + ' equiped" eid="' + i + '">';
                    html += '<div class="pic" style="background-image:url(' + xGame.itemurl(data['eid' + i].equipid) + ');"></div>';
                    html += '<p style="color:' + _color + '">' + data['eid' + i].name + '</p>';
                    html += '<div id="heropop_hammer" class="hammer' + hammerQuality + '">' + (hammer == 0 ? '' : hammer) + '</div>';
                    html += '</div>';
                    that.bottomDom.find('#eid' + i).attr({
                        "class": "eid" + i + ' c_s_i_bj' + data['eid' + i].quality
                    }); //已有装备的格子，删除装备背景
                } else {
                    that.bottomDom.find('#eid' + i).attr({
                        "class": "eid" + i + ' eidbg' + i
                    });
                }
            }

            /*武将信息*/
            that.bottomDom.find('#hero_type').html(helper.getItemVocation(data.type));
            that.bottomDom.find('#hero_lv').html('Lv.' + data.level);
            that.bottomDom.find('#hero_name').html(data.name);
            if (data.turn > 0) {
                that.bottomDom.find('#hero_turn').html(data.turn).show();
            } else {
                that.bottomDom.find('#hero_turn').hide();
            }
            //that.bottomDom.find('#hero_img').css('backgroundImage','url(' + staticUrl + 'img/avatar/a' + data.image + '.jpg)');
            that.bottomDom.find('#hero_img').css('backgroundImage', 'url(' + xGame.avatar(data.image, true) + ')');

            var starVal = parseInt(data.star || 1);
            that.bottomDom.find('#star_num').css('width', (isNaN(starVal) ? 1 : starVal) * 18 + 'px');

            that.bottomDom.find('#hero_h').html(data.hp + (that.tree > 1 ? '<span class="tree tree' + that.tree + '"></span>' : ''));
            that.bottomDom.find('#hero_a').html((data.wuli || 85) + ' +' + (data.wuliup || 0));
            var talentObj = data.talent || { name: lang('no'), description: '' };
            var skillObj = data.skill || { name: lang('no'), description: '' };
            that.bottomDom.find('#hero_t').html(talentObj.name == null ? lang('no') : talentObj.name);
            that.bottomDom.find('#hero_k').html((data.zhili || 70) + ' +' + (data.zhiliup || 0));
            if (skillObj && skillObj.name) {
                that.bottomDom.find('#hero_s').html(skillObj.name == null ? lang('no') : skillObj.name);
            }
            that.bottomDom.find('#hero_p').html((data.tili || 90) + ' +' + (data.tiliup || 0));

            //填充侧边信息
            $('#talent_info').html(talentObj.name == null ? lang('no') : talentObj.name + '：' + (talentObj.description || ''));
            $('#skilld_info').html(skillObj.name == null ? lang('no') : skillObj.name + '：' + (skillObj.description || ''));

            if (that.skillScroll == null) {
                that.skillScroll = new iScroll('hero_skill_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                });
            }

            //属性点击事件
            hero_wrap_dom.find('#attribute').unbind('touchend').bind('touchend',
            function() {
                if (xGame.checkLock()) { //检查是否锁
                    return false;
                }
                var status = $(this).attr('status');
                if (status == '0') { //开启
                    that.attrinfo_show();
                    $(this).attr('status', '1').html(lang('close_properties'));
                } else { //关闭
                    that.attrinfo_hide();
                    $(this).attr('status', '0').html(lang('view_properties'));
                }

                that.skillScroll.scrollTo(0, 0);
                that.skillScroll.refresh();
            });

            /*根据类型加class*/
            that.bottomDom.attr('class', (data.type == 1 ? 'wj': 'ms'));

            /* 左边道具列表 */
            that.equipments = equipments;
            html += that.HtmlOfFillRightList(that.equipments, 1);

            that.bottomDom.append(html);

            // 如果处于强制引导，给周仓穿装备
            if (miscpop.in_array(that.step, [842, 852])) {
                that.displayFirstItem('.unequip');
            } else {
                //显示第一个道具
                that.displayFirstItem();
            }

            //检查列表是否为空
            if (hero_wrap_dom.find('.itembg').length <= 0) {
                var _html = '<div class="itembg right1 c_i_bj1" id="itembg1"></div>' + '<div class="itembg right2 c_i_bj1" id="itembg2"></div>' + '<div class="itembg right3 c_i_bj1" id="itembg3"></div>' + '<div class="itembg right4 c_i_bj1" id="itembg4"></div>' + '<div class="itembg right5 c_i_bj1" id="itembg5"></div>' + '<div class="itembg right6 c_i_bj1" id="itembg6"></div>' + '<div class="itembg right7 c_i_bj1" id="itembg7"></div>' + '<div class="itembg right8 c_i_bj1" id="itembg8"></div>';
                that.bottomDom.append(_html);
            }

            that.bottomDom.find('#hero_body').attr('class', 'hero_body q' + data.quality);
        },

        itemlist_page: function(type) {
            var that = this;

            var page = 1;
            if (type == 'prev') {
                page = helper.max(1, that.page - 1);
            } else {
                page = that.page + 1;
            }

            var html = that.HtmlOfFillRightList(that.equipments, page);
            if (html != '') {
                if (that.nowItem != null && that.nowItem.attr('rp')) { //清理之前的样式
                    that.bottomDom.find('#itembg' + that.nowItem.attr('rp')).removeClass('selected');
                    //$('#hero_dress').hide();
                }
                $('.itembg').remove();
                $('.unequip').remove();
                that.bottomDom.append(html);
                that.displayFirstItem('.unequip');
            }

            that = null;
        },

        HtmlOfFillRightList: function(data, page) {
            var html = '';
            var html2 = '';

            var start = 1;
            if (page) {
                start = (parseInt(page) - 1) * 8 + 1;
                this.page = page;
            }
            var end = start + 8;
            var n = 1; //位置
            //console.log('start:'+start+'|end:'+end);
            for (; start < end; start++) {
                var _item = data[start];

                //道具背景
                html2 += '<div class="itembg right' + n + ' c_i_bj' + (_item ? _item.quality: '1') + '" id="itembg' + n + '"></div>';
                if (_item) {
                    //道具信息
                    var _color = helper.getItemColor(_item.quality);
                    html += '<div class="moveitem right' + n + ' unequip ' + (_item.available == 0 ? 'islock': '') + '" eid="' + _item.etype + '" k="' + start + '" rp="' + n + '">';
                    html += '<div class="pic" style="background-image:url(' + xGame.itemurl(_item.equipid) + ');"></div>';
                    html += _item.available == 0 ? '<span>' + lang('can_not_equip') + '</span>': '';
                    html += '<div id="heropop_hammer" class="hammer' + _item.hammer_quality + '">' + (_item.hammer == 0 ? '' : _item.hammer) + '</div>';
                    html += '<p style="color:' + _color + '">' + _item.name + '</p>';
                    html += '</div>';
                }
                n++;
            }
            if (html == '') { //没有任何数据
                this.page = helper.max(1, this.page - 1);
                html2 = '';
            }

            return html2 + html;
        },

        /* 查看某个武将 */
        viewHero: function(data) {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            /*如果不是第一次*/
            if (data == undefined || data == null) {
                var Func = function(res) {
                    switch (parseInt(res.status)) {
                    case - 1 : xGame.toast.show(lang('hero_not_exist'));
                        break;
                    case - 2 : xGame.toast.show(lang('parameter_error'));
                        break;
                    case 2:
                        xGame.toast.show(lang('hero_not_expedition'));
                        break;
                    case 1:

                        that.clearDetailBox();

                        that.nowHero = res.info; //当前将领数据
                        that.nowHeroId = res.info.id; //当前将领ID
                        that.buildBottomLeft(res.info, res.equipments);
                        that.hideAnim();
                        that.playAnim(that.bottomDom, res.info.achID);

                        //新手引导：75点击周仓后显示滑动手
                        xGame.showGuide([831, 841, 851]);

                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                    }
                    xGame.unlock();
                };
                loadContents({
                    'c':
                    'general',
                    'm': 'get_info',
                    'gid': that.nowHeroId
                },
                Func); //加载数据
                Func = null;

            } else {
                that.buildBottomLeft(data, that.data.equipments);
                xGame.unlock();
            }

        },

        /* 查看某个武将(当装备或卸下时) */
        reviewHero: function(res) {
            this.clearDetailBox();

            this.nowHero = res.info; //当前将领数据
            this.buildBottomLeft(res.info, res.equipments);
            this.hideAnim();
            this.playAnim(this.bottomDom, res.info.achID);

        },

        refreshItemlist: function() {
            if (xGame.checkLock()) {
                return false;
            }

            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('hero_not_exist'));
                    break;
                case - 2 : xGame.toast.show(lang('parameter_error'));
                    break;
                case 2:
                    xGame.toast.show(lang('hero_not_expedition'));
                    break;
                case 1:

                    //that.clearDetailBox();
                    /* 左边道具列表 */
                    //清空右边
                    $('.itembg').remove();
                    $('.moveitem.unequip').remove();
                    var html = that.HtmlOfFillRightList(res.equipments, 1);
                    that.bottomDom.append(html);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'general',
                'm': 'get_info',
                'gid': that.nowHeroId
            },
            Func); //加载数据
            Func = null;

        },

        playAnim: function(obj, quality) {
            var that = this;

            if (parseInt(quality) == 0) {
                return false;
            }

            that.anim = [];

            // 动画位置数据
            var animPos = [
                [22, -14],
                [22, 116],
                [312, -14],
                [312, 116]
            ];

            for (var i = 0; i < 4; i++) {

                that.anim[i] = new movieClipClass({
                    "img": ximg(staticUrl + 'img/pop/refine_animate' + quality + '.png'),
                    "width": 130,
                    "height": 130,
                    "x": animPos[i][0],
                    "y": animPos[i][1],
                    "loop": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 80
                    },
                    {
                        rect: [130, 0],
                        interval: 80
                    },
                    {
                        rect: [260, 0],
                        interval: 80
                    },
                    {
                        rect: [390, 0],
                        interval: 80
                    },
                    {
                        rect: [520, 0],
                        interval: 80
                    },
                    {
                        rect: [0, 130],
                        interval: 80
                    },
                    {
                        rect: [130, 130],
                        interval: 80
                    },
                    {
                        rect: [260, 130],
                        interval: 80
                    },
                    {
                        rect: [390, 130],
                        interval: 80
                    },
                    {
                        rect: [520, 130],
                        interval: 80
                    }
                    ]
                });
                //开始播放
                that.anim[i].appendTo(obj);
                that.anim[i].play();
            }
        },

        hideAnim: function() {
            if (!this.anim) {
                return;
            }
            for (var i = 0; i < 4; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = [];
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.find('#scroller_btn_left').unbind('touchend');
                this.e.find('#scroller_btn_right').unbind('touchend');
                this.e.find('#hero_list_scroller').undelegate('li', 'click');
                this.e.unbind('touchmove');
                this.e.remove();
                this.e = null;
            }
            var prevpageDom = $('#prevpage');
            if (prevpageDom) {
                prevpageDom.unbind('touchend');
            }
            var nextpageDom = $('#nextpage');
            if (nextpageDom) {
                nextpageDom.unbind('touchend');
            }
            var dressDom = $('#hero_dress');
            if (dressDom) {
                dressDom.find('button').text(lang('equip')).unbind('touchend');
                dressDom.find('button').text(lang('remove')).unbind('touchend');
            }
            var hero_wrap_dom = $('#heropop_wrap');
            if (hero_wrap_dom) {
                hero_wrap_dom.find('#attribute').unbind('touchend');
            }
            if (this.bottomDom) {
                this.bottomDom.undelegate('.moveitem', 'touchstart');
                this.bottomDom.undelegate('.moveitem.ismoving', 'touchend');
                this.bottomDom.remove();
                this.bottomDom = null;
            }
        },

        hide: function() {

            xGame.unlock();
            
            this.isshow = false;
            this.data = null;
            this.step = 0;
            this.hideAnim();
            if (this.myScroll) {
                this.myScroll.destroy();
                delete this.myScroll;
            }

            if (this.skillScroll) {
                this.skillScroll.destroy();
                this.skillScroll = null;
            }

            this.nowHeroId = null;
            this.nowHero = null;
            this.page = 1;
            this.equipments = null;
            this.nowItem = null;
            this.tree = null;
            this.unbindAll();
            
        },
    };
})();