/* 装备精炼 */
(function () {
    equiprefinePopClass = function () {
        this.e = null;
        this.isshow = false;
        this.equipID = 0;               // 要进行精炼的装备ID
        this.selectEquipID = 0;         // 玩家选择的装备ID
        this.infoDom = null;            // 玩法说明
        this.masterAvatarDom = null;    // 大师的头像
        this.masterInfoDom = null;      // 大师的信息
        this.resourceDom = null;        // 玩家拥有的资源
        this.equipItemDom = null;       // 要精炼的装备
        this.equipInfoDom = null;       // 要精炼的装备的信息
        this.equipOffset = null;        // 要精炼的装备的CSS位置
        this.startPos = null;           // 移动要精炼的装备时，手指的初始位置
        this.endPos = null;
        this.materialDom = null;        // 消耗的材料
        this.selectEquipDom = null;     // 选择装备
        this.selectEquip = null;        // 玩家在选择装备界面选择的装备
        this.nowRefineLevel = null;     // 装备当前精炼等级
        this.nextRefineLevel = null;    // 装备下一精炼等级
        this.refineLevel = null;        // 装备框中的精炼等级
        this.strongLevel = null;        // 装备框中的强化等级
        this.bottomDom = null;
        this.equipImg = null;           // 要精炼的装备图片
        this.pageBtn = null;            // 选择装备界面中的翻页按钮
        this.pageIndex = 0;             // 每页的索引，从0开始
        this.pageSum = 0;               // 总页数
        this.pageWidth = 0;             // 每一页的宽度
        this.hammer = 0;                // 精炼装备的精炼等级
        this.strong = 0;                // 精炼装备的强化等级
        this.offset = null;
        this.touchMaskDom = null;
        this.touchPos = [];
        this.left = 0;
        this.top = 0;
        this.masterGrade = 0;           // 大师的品级
        this.equipData = null;          // 要精炼装备的数据
    };

    equiprefinePopClass.prototype = {
        
        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                
                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        xGame.showpop('system', {'pop': 'equiprefine'});
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('equiprefinepop_refine_msg13'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };

            loadContents({
                'c': 'refine',
                'm': 'index'
            }, render);
        },

        // 初始化界面
        initPage: function (res) {

            var that = this;
            that.e = xGame.loadPopTmpl('equiprefine');
            that.infoDom = that.e.find('#info_dom');
            that.masterAvatarDom = that.e.find('#master_avatar');
            that.masterInfoDom = that.e.find('#master_info');
            that.resourceDom = that.e.find('#resource');
            that.equipItemDom = that.e.find('#equip_item');
            that.equipInfoDom = that.e.find('#equip_info');
            that.materialDom = that.e.find('#material');
            that.selectEquipDom = that.e.find('#select_equip');
            that.nowRefineLevel = that.e.find('#now_refine_level');
            that.nextRefineLevel = that.e.find('#next_refine_level');
            that.refineLevel = that.equipItemDom.children('.refine_level');
            that.strongLevel = that.equipItemDom.children('.strong_level');
            that.bottomDom = that.e.find('#bottom_dom');
            that.equipImg = that.equipItemDom.find('.equip_img');
            that.pageBtn = that.selectEquipDom.find('#page_btn');
            that.touchMaskDom = that.e.find('#touch_mask');

            // 界面关闭按钮
            that.e.find('#hidebtn').unbind('touchend').bind('touchend', function (e) {
               
               if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            that.buildPage(res);
            that.bindAll();

            that.touchPos = [
                [18, 14],
                [157, 14],
                [296, 14],
                [18, 145],
                [157, 145],
                [296, 145],
                [18, 276],
                [157, 276],
                [296, 276]
            ];

            that.equipOffset = that.equipItemDom.children('.equip_img').offset(); // 获取精炼装备的位置数据

            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },

        // 构建装备精炼界面
        buildPage: function (res) {
            
            this.showMaster(res.master);    // 显示大师
            this.showResource(res.resource);    // 显示资源
            this.e.find('#refresh_master_btn').children('.gold').html('x' + res.price); // 刷新大师所花元宝
            this.equipInfoDom.children('.name').html(lang('equiprefinepop_select_tip'));
            xGame.updateCd('equiprefine', res.cd);
        },

        bindAll: function () {
            
            var that = this;

            // 玩法说明按钮
            that.e.find('#info_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.show();
                e.preventDefault();
            });

            // 玩法说明界面的关闭按钮
            that.infoDom.find('#info_hide_btn').unbind('touchend').bind('touchend', function (e) {
               
               if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoDom.hide();
                e.preventDefault();
            });

            // 精炼成就按钮
            that.e.find('#refine_achieve_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.showpop('refineachieve');
                that.hide();
                e.preventDefault();
            });

            // 通天塔按钮
            that.e.find('#heaven_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (systempop) {
                    systempop.hide();
                }
                
                xGame.loadWorld('heaven');
                e.preventDefault();
            });

            // 精炼继承按钮
            that.e.find('#refine_inherit_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                xGame.showpop('refineinherit');
                e.preventDefault();
            });

            // 刷新大师按钮
            that.e.find('#refresh_master_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (that.masterGrade >= 5) {

                    var color = '';

                    if (that.masterGrade == 5) {
                        color = lang('purple');
                    } else if (that.masterGrade == 6) {
                        color = lang('talent_red');
                    }

                    xGame.confirm.show(lang('equiprefinepop_refresh_title'), lang('equiprefinepop_refresh_content', [helper.getItemColor(that.masterGrade), color]), 2,
                        function() {
                            xGame.confirm.hide(
                                function() {
                                    if (xGame.checkLock()) {
                                        return false;
                                    }

                                    that.refreshMaster(0);
                                }
                            );
                        }, function() {
                            xGame.confirm.hide();
                        }
                    );
                } else {
                    if (xGame.checkLock()) {
                        return false;
                    }

                    that.refreshMaster(0);
                }

                e.preventDefault();
            });

            // 精炼按钮
            that.e.find('#left_dom').undelegate('#refine_btn.pop_btn_big', 'touchend').delegate('#refine_btn.pop_btn_big', 'touchend', function (e) {
                that.refine();
                e.preventDefault();
            });

            // 点击装备框时触发
            that.equipItemDom.unbind('touchend').bind('touchend', function (e) {
                
                // 如果装备框不为空
                if (that.equipID != 0) {
                    return false;
                }

                // 如果选择装备界面已经显示
                if (that.selectEquipDom.css('display') == 'block') {
                    return false
                }

                if (xGame.checkLock()) {
                    return false;
                }

                $(this).addClass('index');

                that.getEquipData();
                e.preventDefault();

            });

            // 移动要精炼的装备
            that.equipImg.unbind('touchstart').bind('touchstart', function (e) {
                
                if (that.equipID == 0) {
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.targetTouches.length == 1) {
                    that.startPos = {pageX: e.targetTouches[0].pageX, pageY: e.targetTouches[0].pageY};
                    that.endPos = {pageX: e.targetTouches[0].pageX, pageY: e.targetTouches[0].pageY};
                    that.refineLevel.hide();
                    that.strongLevel.hide();
                    var offset = $(this).offset();
                    that.e.append($(this));
                    $(this).css({'left': offset.left + 'px', 'top': offset.top + 'px'});
                }

                that.e.unbind('touchmove').bind('touchmove', function (e) {
                    
                    if (that.equipID == 0) {
                        return false;
                    }

                    if (xGame.checkLock(true)) {
                        return false;
                    }

                    if (e.targetTouches.length == 1) {
                        var touch = e.targetTouches[0];
                        var x = touch.pageX - that.startPos.pageX;
                        var y = touch.pageY - that.startPos.pageY;
                        that.endPos = {pageX: touch.pageX, pageY: touch.pageY};
                        that.equipImg.css('-webkit-transform', 'translate3d('+ x +'px, '+ y +'px, 0)');
                    }

                    e.preventDefault();
                });

                that.e.unbind('touchend').bind('touchend', function (e) {

                    if (that.equipID == 0) {
                        return false;
                    }

                    if (xGame.checkLock(true)) {
                        return false;
                    }

                    var x = that.endPos.pageX - that.startPos.pageX;
                    var y = that.endPos.pageY - that.startPos.pageY;

                    if (that.hammer != 0) {
                        that.refineLevel.show();
                    }

                    if (that.strong != 0) {
                        that.strongLevel.show();
                    }

                    that.equipImg.css('-webkit-transform', 'translate3d(0, 0, 0)');
                    that.equipItemDom.prepend(that.equipImg);
                    that.equipImg.css({'left': '0', 'top': '0'});

                    if (x == 0 && y == 0) {

                        that.clearRefineEquip();

                        if (xGame.checkLock()) {
                            return false;
                        }

                        that.equipItemDom.addClass('index');
                        that.getEquipData();
                    }

                    // 如果装备的位置没有和装备框相交
                    if (Math.abs(x) > that.equipImg.width() || Math.abs(y) > that.equipImg.height()) {
                        that.equipData = null;
                        that.clearRefineEquip();
                    }

                    that.e.unbind();

                    e.preventDefault();
                });

                e.preventDefault();

            });

            // 移动选择装备窗口中的装备
            that.touchMaskDom.undelegate('.touch_item', 'touchstart').delegate('.touch_item', 'touchstart', function(e) {
                
                e.preventDefault();

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.targetTouches.length == 1) {
                    that.startPos = {pageX: e.targetTouches[0].pageX, pageY: e.targetTouches[0].pageY};
                    that.endPos = {pageX: e.targetTouches[0].pageX, pageY: e.targetTouches[0].pageY};
                    var $this = $(this);
                    that.offset = $this.offset();
                    that.left = parseInt($this.css('left'));
                    that.top = parseInt($this.css('top'));
                    $this.addClass('ismoving');
                    $this.css('z-index', '100');

                    var index = that.touchMaskDom.find('.touch_item').index($this);
                    var itembox = that.selectEquipDom.find('li').eq(that.pageIndex).children('.itembox').eq(index);

                    if (itembox.length != 0) {
                        $this.css('background-image', itembox.children('.item_img').css('background-image'));
                        that.selectEquipID = itembox.attr('gid');
                        that.selectEquipDom.find('.itembox').removeClass('selected');
                        itembox.addClass('selected');
                    }

                }
            });

            // 移动选择装备窗口中的装备
            that.touchMaskDom.undelegate('.touch_item.ismoving', 'touchmove').delegate('.touch_item.ismoving', 'touchmove', function(e) {
                
                if (that.selectEquipID == 0) {
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.targetTouches.length == 1) {
                    var touch = e.targetTouches[0];
                    var x = touch.pageX - that.startPos.pageX;
                    var y = touch.pageY - that.startPos.pageY;
                    that.endPos = {pageX: touch.pageX, pageY: touch.pageY};
                    $(this).css({'left': (that.left + x) + 'px', 'top': (that.top + y) + 'px'});
                }

                e.preventDefault();
            });

            // 移动选择装备窗口中的装备
            that.touchMaskDom.undelegate('.touch_item.ismoving', 'touchend').delegate('.touch_item.ismoving', 'touchend', function(e) {
                
                if (that.selectEquipID == 0) {
                    return false;
                }

                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);
                $this.removeClass('ismoving');
                var left = parseInt($this.css('left'));
                var top = parseInt($this.css('top'));
                var offset1 = $this.offset();
                var offset2 = that.equipImg.offset();

                // 如果装备与精炼框相交
                if ((offset1.left + offset1.width >= offset2.left) && (offset2.left + offset2.width >= offset1.left) && (offset1.top + offset1.height >= offset2.top) && (offset2.top + offset2.height >= offset1.top)) {

                    if (xGame.checkLock()) {
                        return false;
                    }

                    $this.animate({'left': (left + offset2.left - offset1.left) + 'px', 'top': (top + offset2.top - offset1.top) + 'px', 'width': offset2.width + 'px', 'height': offset2.height + 'px'}, 100);
                    that.getRefineEquipData();
                    that.selectEquipDom.fadeOut('fast');
                    that.equipItemDom.removeClass('index');
                    that.pageBtn.children('.pages').html('1');
                    that.selectEquipDom.find('.carousel_scroller').css('-webkit-transform', 'translate3d(0px, 0px, 0px)');
                    that.pageIndex = 0;

                // 如果触摸点依旧在装备框中
                } else if (that.endPos.pageX >= that.offset.left && that.endPos.pageX <= that.offset.left + that.offset.width && that.endPos.pageY >= that.offset.top && that.endPos.pageY <= that.offset.top + that.offset.width) {

                    if (xGame.checkLock()) {
                        return false;
                    }

                    that.selectEquipDom.fadeOut('fast');
                    $this.animate({'left': (left + offset2.left - offset1.left) + 'px', 'top': (top + offset2.top - offset1.top) + 'px', 'width': offset2.width + 'px', 'height': offset2.height + 'px'}, 500, 'ease', function () {
                        that.getRefineEquipData();
                        that.equipItemDom.removeClass('index');
                        that.pageBtn.children('.pages').html('1');
                        that.selectEquipDom.find('.carousel_scroller').css('-webkit-transform', 'translate3d(0px, 0px, 0px)');
                        that.pageIndex = 0;
                    });

                } else {

                    var index = that.touchMaskDom.find('.touch_item').index($this);
                    $this.css({'left': that.touchPos[index][0] + 'px', 'top': that.touchPos[index][1] + 'px', 'background-image': 'none', 'z-index': 'auto'});
                    that.selectEquipID = 0;
                }
            });


            // 关闭选择装备窗口
            that.selectEquipDom.find('.mask').unbind('touchend').bind('touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.touchMaskDom.hide();
                that.selectEquipDom.hide();
                that.equipImg.html(lang('equiprefinepop_select'));
                that.equipItemDom.removeClass('index');
                that.pageBtn.children('.pages').html('1');
                that.selectEquipDom.find('.carousel_scroller').css('-webkit-transform', 'translate3d(0px, 0px, 0px)');
                that.pageIndex = 0;

                // 如果有精炼装备数据
                if (that.equipData != null) {
                    that.showRefineEquip(that.equipData);
                }

                e.preventDefault();
            });

            // 选择装备窗口的关闭按钮
            that.selectEquipDom.find('#select_equip_hidebtn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.selectEquipDom.find('.mask').trigger('touchend');
                e.preventDefault();
            });

            // 选择装备窗口的左翻页按钮
            that.pageBtn.children('.prev_btn').unbind('touchend').bind('touchend', function (e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.turnPage(true);
                e.preventDefault();
            });

            // 选择装备窗口的右翻页按钮
            that.pageBtn.children('.next_btn').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.turnPage(false);
                e.preventDefault();
            });

        },

        // 显示用户拥有的资源
        showResource: function (resource) {
            this.resourceDom.children('.bloodstone').html(this.getWan(resource.bloodstone));
            this.resourceDom.children('.colorstone').html(this.getWan(resource.colorstone));
            this.resourceDom.children('.jade').html(this.getWan(resource.jade));
            this.resourceDom.children('.refinestone').html(this.getWan(resource.refinestone));
        },

        // 获取玩家装备数据
        getEquipData: function () {

            var that = this;
            var render = function (res) {

                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        xGame.islock = true;
                        that.buildEquipPage(res.list);
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('equiprefinepop_refine_msg14'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }

            };

            loadContents({
                'c': 'refine',
                'm': 'item_index'
            }, render);
        },

        // 构建选择装备界面
        buildEquipPage: function (list) {
            
            var html = '<li>';
            var n = 0;

            for (var i = 0, len = list.length; i < len; i++) {

                var item = list[i];
                
                if (n > 0 && n % 9 == 0) {
                    html += '</li><li>';
                }

                html += '<div class="itembox box'+ item.quality +'" gid="'+ item.id +'">';
                html += '<span class="item_img" style="background-image:url('+ xGame.itemurl(item.equipid) +')"></span>';

                if (parseInt(item.hammer) != 0) {
                    html += '<em class="level hammer' + item.hammer_quality + '">' + item.hammer + '</em>';
                }

                if (parseInt(item.level) != 0) {
                    html += '<em class="strong">' + item.level + '</em>';
                }

                if (item.generalname != null) {
                    html += '<em class="hero_name">' + item.generalname + '</em>';
                }

                html += '</div>';

                n ++;
            }

            html += '</li>';

            this.selectEquipDom.find('.carousel_content').html(html);
            this.pageSum = this.selectEquipDom.find('li').length;
            this.selectEquipDom.show();
            this.pageWidth = this.selectEquipDom.find('li').width();
            this.selectEquipDom.find('.carousel_scroller').width(this.pageSum * this.pageWidth);

            var html1 = '';

            for (var i = 0; i < 9; i++) {
                html1 += '<span class="touch_item" id="touch_item' + i + '" style="left:'+ this.touchPos[i][0] +'px;top:'+ this.touchPos[i][1] +'px;"></span>';
            }

            this.touchMaskDom.html(html1).show();

            xGame.unlock();
        },

        // 获取要进行精炼的装备的数据
        getRefineEquipData: function () {
            
            var that = this;

            var render = function (res) {

                xGame.unlock();
                that.selectEquipID = 0;

                switch (parseInt(res.status)) {
                    case 1:
                        xGame.islock = true;
                        that.showRefineEquip(res.list);
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('equiprefinepop_refine_msg4'));
                        break;
                    case -3:
                        xGame.toast.show(lang('equiprefinepop_refine_msg15'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };

            loadContents({
                'c': 'refine',
                'm': 'item_selected',
                'id': that.selectEquipID
            }, render);
        },

        // 显示要精炼的装备
        showRefineEquip: function (equip) {

            this.touchMaskDom.hide();
            this.equipData = equip;     // 暂存装备数据
            this.equipID = parseInt(equip.id);
            this.bottomDom.removeClass('hidden');
            var hammer = parseInt(equip.hammer);
            this.hammer = hammer;
            this.strong = parseInt(equip.level);

            // 装备的图片信息
            this.equipItemDom.attr('class', 'equip_item').addClass('q' + equip.quality);
            this.equipItemDom.children('.equip_img').css('background-image', 'url(' + xGame.itemurl(equip.equipid) + ')').removeClass('fadeinout').html('');

            if (hammer != 0) {
                this.refineLevel.attr('class', 'refine_level hammer' + equip.hammer_quality).html(hammer).show();
            } else {
                this.refineLevel.hide();
            }

            if (this.strong != 0) {
                this.strongLevel.html(this.strong).show();
            } else {
                this.strongLevel.hide();
            }

            var prop = 0;   // 1：攻击，2：防御，3：生命
            var type = parseInt(equip.type);    // 获取装备的属性类型
            if (type <= 2) {
                prop = 1;
            } else if (type <= 4) {
                prop = 2;
            } else {
                prop = 3;
            }

            // 装备的属性信息
            this.equipInfoDom.children('.name').removeClass('empty').css('color', helper.getItemColor(equip.quality)).html(equip.name);
            this.equipInfoDom.children('.total_prop').html(lang('total_prop' + prop) + equip.all);
            this.equipInfoDom.children('.initial_prop').html(lang('initial_prop' + prop) + equip.power);
            this.equipInfoDom.children('.strong_prop').html(lang('strong_prop' + prop) + equip.powerups);
            this.equipInfoDom.find('.refine_prop > .now_prop').html(lang('refine_prop' + prop) + equip.refine).next('.add_prop').html(' +' + equip.refine_next);

            if (parseInt(equip.strength_ach) != 0) {
                this.equipInfoDom.find('.strong_achieve > .now_prop').html(lang('equiprefinepop_strong_achieve') + equip.strength_ach).next('.add_prop').html(' +' + equip.strength_ach_next);
            } else {
                this.equipInfoDom.find('.strong_achieve > .now_prop').html('').next('.add_prop').html('');
            }

            // 精炼按钮
            this.e.find('#refine_btn').html(parseInt(equip.now_mode) == 1 ? lang('equiprefinepop_mode') : lang('equiprefinepop_refine_btn'));

            // 精炼等级
            if (hammer == 0) {
                this.nowRefineLevel.children('.effect').html(lang('equiprefinepop_effect_level', [hammer]));
            } else {
                this.nowRefineLevel.children('.effect').html(lang('equiprefinepop_effect_level', [hammer]) + lang('equiprefinepop_effect_prop', [equip.percent]));
            }

            // 如果未满级
            if (equip.ismax == 0) {
                this.nextRefineLevel.children('.effect').html(lang('equiprefinepop_effect_level', [hammer + 1]) + lang('equiprefinepop_effect_prop', [equip.percent_next]));

                // 精炼装备要消耗的资源
                this.materialDom.removeClass('hidden');

                if (parseInt(equip.cost_next.jade) != 0) {
                    this.materialDom.children('.jade').html(equip.cost_next.jade).show();
                } else {
                    this.materialDom.children('.jade').hide();
                }

                if (parseInt(equip.cost_next.refinestone) != 0) {
                    this.materialDom.children('.refinestone').html(equip.cost_next.refinestone).show();
                } else {
                    this.materialDom.children('.refinestone').hide();
                }

                if (parseInt(equip.cost_next.colorstone) != 0) {
                    this.materialDom.children('.colorstone').html(equip.cost_next.colorstone).show();
                } else {
                    this.materialDom.children('.colorstone').hide();
                }

                if (parseInt(equip.cost_next.bloodstone) != 0) {
                    this.materialDom.children('.bloodstone').html(equip.cost_next.bloodstone).show();
                } else {
                    this.materialDom.children('.bloodstone').hide();
                }

            } else {
                this.materialDom.addClass('hidden');
                this.e.find('#refine_btn').attr('class', 'btn pop_btn_big_disable').html(lang('equiprefinepop_refine_btn'));
                this.nextRefineLevel.children('.effect').html(lang('equiprefinepop_full'));
            }

            xGame.unlock();
        },

        // 清除装备信息
        clearRefineEquip: function () {
            this.equipID = 0;
            this.hammer = 0;
            this.equipItemDom.attr('class', 'equip_item q1');
            this.equipInfoDom.children('.name').html(lang('equiprefinepop_select_tip')).addClass('empty').css({'color': '#EA6310', 'visibility': 'visible'});
            this.equipImg.addClass('fadeinout').css('background-image', 'none');
            this.refineLevel.hide();
            this.strongLevel.hide();
            this.materialDom.children('li').hide();
            this.bottomDom.addClass('hidden');
            this.e.find('#refine_btn').attr('class', 'btn pop_btn_big').html(lang('equiprefinepop_refine_btn'));
            this.equipImg.html(lang('equiprefinepop_select'));
            xGame.unlock();
        },

        // 刷新大师，zero为1是表示倒计时为0
        refreshMaster: function (zero) {
            
            var that = this;

            var render = function (res) {
                
                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        that.showMaster(res.list);
                        player.updateinfo({'gold': res.all_gold});

                        if (typeof res.cd != 'undefined' && zero == 1) {
                            xGame.updateCd('equiprefine', res.cd);
                        }
                        break;
                    case 2:
                        xGame.toast.show(lang('equiprefinepop_refine_msg23'));
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('equiprefinepop_refine_msg4'));
                        break;
                    case -3:
                        xGame.toast.show(lang('gold_not_enough'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };

            loadContents({
                'c': 'refine',
                'm': 'master_refresh',
                'zero': zero
            }, render);
        },

        // 显示大师
        showMaster: function (master) {

            this.masterGrade = parseInt(master.grade);

            this.masterAvatarDom.attr('class', 'master_avatar').addClass('q' + master.grade);
            this.masterAvatarDom.children('.img').css('background-image', 'url(' + xGame.master(master.id) + ')');
            this.masterAvatarDom.children('.name').css('color', helper.getItemColor(master.grade)).html(master.name);
            this.masterInfoDom.children('.success_rate').html(master.current + '%');
            this.masterInfoDom.find('.refine_rate > .text').html('+' + master.succ_rate + '%');
            this.masterInfoDom.find('.add_value > .title').html(lang('equiprefinepop_master_type' + master.add_type)).next('.add_value > .text').html(' +' + master.add_value + '%');
            this.masterInfoDom.find('.desc > .text').html(master.desc);
        },

        // 精炼
        refine: function () {

            var that = this;

            if (that.equipID == 0) {
                xGame.toast.show(lang('equiprefinepop_refine'));
                return false;
            }

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                xGame.unlock();

                switch (parseInt(res.status)) {
                    case 1:
                        that.levelResult(1, res.hit);
                        that.showRefineEquip(res.list);
                        that.showResource(res.resource);
                        break;
                    case 3:
                        xGame.toast.show(lang('equiprefinepop_refine_msg16'));
                        break;
                    case 4:
                        that.levelResult(2);
                        that.showResource(res.resource);
                        break;
                    case 5:
                        xGame.toast.show(lang('equiprefinepop_refine_msg22'));
                        break;
                    case -1:
                        xGame.toast.show(lang('equiprefinepop_level'));
                        break;
                    case -2:
                        xGame.toast.show(lang('equiprefinepop_refine_msg4'));
                        break;
                    case -3:
                        xGame.toast.show(lang('equiprefinepop_refine_msg5'));
                        break;
                    case -4:
                        xGame.toast.show(lang('equiprefinepop_refine_msg17'));
                        break;
                    case -5:
                        xGame.toast.show(lang('equiprefinepop_refine_msg6'));
                        break;
                    case -6:
                        xGame.toast.show(lang('equiprefinepop_refine_msg7'));
                        break;
                    case -7:
                        xGame.toast.show(lang('equiprefinepop_refine_msg8'));
                        break;
                    case -8:
                        xGame.toast.show(lang('equiprefinepop_refine_msg9'));
                        break;
                    case -9:
                        xGame.toast.show(lang('equiprefinepop_refine_msg10'));
                        break;
                    case -10:
                        xGame.toast.show(lang('equiprefinepop_refine_msg13'));
                        break;
                    case -11:
                        xGame.toast.show(lang('equiprefinepop_refine_msg18'));
                        break;
                    case -12:
                        xGame.toast.show(lang('equiprefinepop_refine_msg19'));
                        break;
                    case -13:
                        xGame.toast.show(lang('equiprefinepop_refine_msg20'));
                        break;
                    case -14:
                        xGame.toast.show(lang('equiprefinepop_refine_msg21'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };

            loadContents({
                'c': 'refine',
                'm': 'refinning',
                'id': that.equipID
            }, render);
        },

        // 精炼结果
        levelResult: function(type, baoji) {

            var html = $('<div class="equiprefine_result_wrap"></div>');

            var movieclip;

            var cfg = {
                "x": 176,
                "y": -50
            };
            var callback = function() {
                setTimeout(function() {
                    html.fadeOut('normal', function () {
                        html.remove();
                    });
                },
                1000);
            };

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
            this.e.find('#equiprefinepop_wrap').append(html);
            movieclip.play();
            movieclip = null;
        },

        // 选择装备界面中的翻页
        turnPage: function (left) {

            if (left && this.pageIndex > 0) {
                this.pageIndex --;
            } else if (!left && this.pageIndex < this.pageSum - 1) {
                this.pageIndex ++;
            }

            this.pageBtn.children('.pages').html(this.pageIndex + 1);
            this.selectEquipDom.find('.carousel_scroller').animate({'translateX': (this.pageIndex * this.pageWidth * -1) +'px'}, 500, 'ease');
        },

        getWan: function(num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.find('#info_btn').unbind('touchend');
                this.e.find('#refine_achieve_btn').unbind('touchend');
                this.e.find('#heaven_btn').unbind('touchend');
                this.e.find('#refine_inherit_btn').unbind('touchend');
                this.e.find('#refresh_master_btn').unbind('touchend');
                this.e.find('#left_dom').undelegate('#refine_btn.pop_btn_big', 'touchend');
                this.e.unbind('touchmove');
                this.e.unbind('touchend');
            }
            if (this.infoDom) {// 玩法说明
                this.infoDom.find('#info_hide_btn').unbind('touchend');
                this.infoDom.remove();
                this.infoDom = null;
            }
            if (this.equipItemDom) {// 要精炼的装备的图片
                this.equipItemDom.unbind('touchend');
                this.equipItemDom.remove();
                this.equipItemDom = null;
            }
            if (this.equipImg) {
                this.equipImg.unbind('touchstart');
                this.equipImg = null;
            }
            if (this.selectEquipDom) { // 选择装备
                this.selectEquipDom.find('.mask').unbind('touchend');
                this.selectEquipDom.find('#select_equip_hidebtn').unbind('touchend');
                this.selectEquipDom.remove();
                this.selectEquipDom = null;
            }
            if (this.pageBtn) {
                this.pageBtn.unbind('touchend');
                this.pageBtn.remove();
                this.pageBtn = null;
            }
            if (this.touchMaskDom) {
                this.touchMaskDom.undelegate('.touch_item', 'touchstart');
                this.touchMaskDom.undelegate('.touch_item.ismoving', 'touchmove');
                this.touchMaskDom.undelegate('.touch_item.ismoving', 'touchend');
                this.touchMaskDom.remove();
                this.touchMaskDom = null;
            }
        },

        hide: function () {
            xGame.unlock();
            this.isshow = false;
            xGame.updateCd('equiprefine', 0);
            this.unbindAll();
            this.equipID = 0;               // 要进行精炼的装备ID
            this.selectEquipID = 0;         // 玩家选择的装备ID
            if (this.masterAvatarDom) {     // 大师的头像
                this.masterAvatarDom.remove();
                this.masterAvatarDom = null;
            }
            if (this.masterInfoDom) {       // 大师的信息
                this.masterInfoDom.remove();
                this.masterInfoDom = null;
            }
            if (this.resourceDom) {         // 玩家拥有的资源
                this.resourceDom.remove();
                this.resourceDom = null;
            }
            if (this.equipInfoDom) {         // 要精炼的装备的信息
                this.equipInfoDom.remove();
                this.equipInfoDom = null;
            }
            if (this.materialDom) {         // 消耗的材料
                this.materialDom.remove();
                this.materialDom = null;
            }
            if (this.bottomDom) {
                this.bottomDom.remove();
                this.bottomDom = null;
            }
            this.equipOffset = null;        // 要精炼的装备的CSS位置
            this.startPos = null;           // 移动要精炼的装备时，手指的初始位置
            this.endPos = null;
            this.selectEquip = null;        // 玩家在选择装备界面选择的装备
            this.nowRefineLevel = null;     // 装备当前精炼等级
            this.nextRefineLevel = null;    // 装备下一精炼等级
            this.refineLevel = null;        // 装备框中的精炼等级
            this.strongLevel = null;        // 装备框中的强化等级
            this.hammer = 0;
            this.strong = 0;                // 精炼装备的强化等级
            this.pageIndex = 0;
            this.pageSum = 0;
            this.pageWidth = 0;
            this.masterGrade = 0;           // 大师的品级
            this.equipData = null;          // 要精炼装备的数据
            this.offset = null;
            this.touchPos = [];
            this.left = 0;
            this.top = 0;
            this.e.remove();
            this.e = null;
        }
    }
})();