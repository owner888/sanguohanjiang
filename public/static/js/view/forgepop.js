// 铸造
(function () {
    forgePopClass = function () {
        this.e = null;
        this.isshow = false;
        this.equipData = null; // 装备数据
        this.materialData = null; // 材料数据
        this.listDom = null; // 装备和材料列表
        this.infoDom = null; // 装备和材料的信息
        this.forgePageDom = null; // 铸造弹窗界面
        this.sellPageDom = null; // 出售材料界面
        this.listScroller = null; // 列表的滚动控件
        this.nowItemId = null; // 当前查看物品的id
        this.nowItemIndex = null; // 当前查看物品的index
        this.nowItemType = null; // 当前查看物品的类型，1是装备，2是材料
        this.newEquipData = null; // 要铸造的装备数据
        this.newEquipListScroller = null; // 要铸造装备列表
        this.nowNewEquipId = null; // 当前查看要铸造成的装备id
        this.nowNewEquipIndex = null; // 当前查看要铸造成的装备index
        this.nowMaterialNum = null; // 当前卖出材料的总量
        this.nowMaterialPrice = null; // 当前材料的单价
        this.selectMaterialNum = null; // 选择的材料数量
        this.nowPosX = null; // 当前触摸的水平位置
        this.barOffset = null; // 进度条外框的位置属性
        this.nowbarRatio = null; // 当前进度条的百分比
        this.minbarRatio = null; // 最小进度条的百分比
        this.sliderbtn = null; // 滑块
        this.progressCoverDom = null; // 进度条
        this.forgeBtn = null; // 主界面中的铸造按钮
        this.sellBtn = null; // 主界面中的卖出按钮
        this.priceDom = null; // 物品价格
        this.successPageDom = null; // 锻造成功界面
        this.attributeBtn = null; // 主界面的属性按钮
        this.suitPageDom = null; // 查看套装界面
        this.infoPageDom = null; // 玩法介绍界面
        this.successEuqipId = null; // 铸造成功的装备ID
    };

    forgePopClass.prototype = {

        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.equipData = res.equiplist;
                    that.materialData = res.materiallist;
                    that.initPage();
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'index',
                'd': 'newequip',
            }, render);
        },

        initPage: function () {

            this.e = xGame.loadPopTmpl('forge');
            this.listDom = this.e.find('#forgelist_carousel');
            this.infoDom = this.e.find('#forge_info');
            this.forgePageDom = this.e.find('#forge_page');
            this.sellPageDom = this.e.find('#forge_sell_page');
            this.sliderbtn = this.sellPageDom.find('#forge_sell_slider_btn');
            this.progressCoverDom = this.sellPageDom.find('.progress_cover');
            this.forgeBtn = this.infoDom.find('#forge_forge_btn');
            this.sellBtn = this.infoDom.find('#forge_sell_btn');
            this.priceDom = this.infoDom.find('.price');
            this.successPageDom = this.e.find('#forge_success_page');
            this.attributeBtn = this.e.find('#forge_attribute_btn');
            this.suitPageDom = this.e.find('#forge_suit_page');
            this.infoPageDom = this.e.find('#forge_info_page');
            this.nowItemIndex = 0;

            this.buildList();
            this.bindAll();

            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();

            xGame.showpop('system', {
                'pop': 'forge',
                'callback': null,
            });
        },

        bindAll: function () {
            
            var that = this;

            // 查看物品信息
            that.listDom.delegate('.item', 'click', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);

                if ($this.hasClass('selected')) {
                    $this = null;
                    return false;
                }

                var type = $this.attr('type');

                if (type == null) {
                    return false;
                }

                var index = parseInt($this.attr('index'));

                that.buildInfo(index, type);
                that.listDom.find('.item').removeClass('selected');
                $this.addClass('selected');
                $this = null;

                e.preventDefault();
            });

            // 铸造按钮
            that.forgeBtn.bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                if (that.nowItemType == 1) { // 装备
                    that.getForgeEquipInfo();
                }

                e.preventDefault();
            });

            // 卖出按钮
            that.sellBtn.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.nowItemType == 2) {
                    that.buildSellItemPage();
                }

                e.preventDefault();
            });

            // 出售材料界面的关闭按钮
            that.sellPageDom.find('#forge_sell_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.sellPageDom.hide();
                e.preventDefault();
            });

            // 绑定滑块的触摸事件
            that.sliderbtn.bind('touchstart', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.targetTouches.length != 1) {
                    return false;
                }

                that.nowPosX = e.targetTouches[0].pageX;
                e.preventDefault();
            }).bind('touchmove', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false
                }

                if (e.targetTouches.length != 1) {
                    return false;
                }

                var $this = $(this);
                var pageX = e.targetTouches[0].pageX;
                var ratio = ((pageX - that.nowPosX) / that.barOffset.width * 100) + that.nowbarRatio;

                if (ratio < that.minbarRatio) {
                    ratio = that.minbarRatio;
                } else if (ratio > 100) {
                    ratio = 100;
                }

                that.nowbarRatio = ratio;
                that.nowPosX = pageX;
                that.selectMaterialNum = parseInt(that.nowbarRatio * that.nowMaterialNum / 100);

                if (that.selectMaterialNum == 0) {
                    that.selectMaterialNum = 1;
                }

                that.setSellItemInfo(that.selectMaterialNum);
                that.progressCoverDom.width(that.nowbarRatio + '%');
                e.preventDefault();
            });

            // 出售材料界面的减小增大按钮
            that.sellPageDom.delegate('.operation_btn', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var type = parseInt($(this).attr('type'));

                if (type == 1) {
                    that.selectMaterialNum--;
                } else {
                    that.selectMaterialNum++;
                }

                if (that.selectMaterialNum < 1) {
                    that.selectMaterialNum = 1;
                } else if (that.selectMaterialNum > that.nowMaterialNum) {
                    that.selectMaterialNum = that.nowMaterialNum;
                }

                that.nowbarRatio = that.selectMaterialNum / that.nowMaterialNum * 100;

                that.setSellItemInfo(that.selectMaterialNum);
                that.progressCoverDom.width(that.nowbarRatio + '%');
                e.preventDefault();
            });

            // 出售材料界面的卖出按钮
            that.sellPageDom.find('#forge_sell_sale_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var name = that.materialData[that.nowItemIndex].name;

                xGame.confirm.show(lang('forgepop_sell_title'), lang('forgepop_sell_material', [that.selectMaterialNum, name, that.selectMaterialNum * that.nowMaterialPrice]), 2, function () {
                    xGame.confirm.hide();
                    that.sellMaterial();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 铸造信息弹窗的关闭按钮
            that.forgePageDom.find('#forge_page_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.forgePageDom.hide();
                that.newEquipListScroller.destroy();
                that.newEquipListScroller = null;
                e.preventDefault();
            });

            // 铸造信息弹窗的装备列表
            that.forgePageDom.delegate('.itembox', 'click', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);

                if ($this.hasClass('selected')) {
                    $this = null;
                    return false;
                }

                var index = parseInt($this.attr('index'));
                $this.addClass('selected').siblings().removeClass('selected');
                that.buildForgeEquipInfo(index);
                e.preventDefault();
            });

            // 铸造信息弹窗的铸造按钮
            that.forgePageDom.delegate('#forge_cast_btn.pop_btn_middle', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var data = that.equipData[that.nowItemIndex];
                var content = lang('forgepop_cast_content');

                if (parseInt(data.quality) == 5) {
                    content += lang('forgepop_cast_content1', [data.name, data.saleprice]);
                } else {
                    content += lang('forgepop_cast_content2');
                }


                xGame.confirm.show(lang('forgepop_cast_title'), content, 2, function () {
                    xGame.confirm.hide();
                    that.castEquip();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 铸造信息弹窗的材料列表
            that.forgePageDom.delegate('.material_box', 'touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var index = $(this).index();
                miscpop.showMaterialInfo(that.newEquipData[that.nowNewEquipIndex].consumearr[index]);
                e.preventDefault();
            });

            // 锻造成功弹窗的确定按钮
            that.successPageDom.find('#forge_confirm_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.successPageDom.hide();
                that.forgePageDom.hide();
                that.newEquipListScroller.destroy();
                that.newEquipListScroller = null;
                that.refreshPage();
                e.preventDefault();
            });

            // 查看装备属性按钮
            that.attributeBtn.bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                miscpop.showForgeEquipInfo(that.nowItemId);
                e.preventDefault();
            });

            // 查看套装按钮
            that.forgePageDom.find('#forge_view_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.getSuitData();
                e.preventDefault();
            });

            // 查看套装界面的关闭按钮
            that.suitPageDom.find('#forge_suit_hidebtn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.suitPageDom.hide();
                e.preventDefault();
            });

            // 玩法说明按钮
            that.forgePageDom.find('#forge_info_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoPageDom.show();
                e.preventDefault();
            });

            // 玩法说明界面的关闭事件
            that.infoPageDom.find('#forge_info_hidebtn, .mask').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.infoPageDom.hide();
                e.preventDefault();
            })
        },

        // 构建装备和材料列表
        buildList: function () {
            
            var that = this;
            var html = '<li class="cs_item">';
            var n = 0;
            var item = null;

            // 构建装备列表
            for (var i = 0, equipLen = that.equipData.length; i < equipLen; i++) {
                item = that.equipData[i];

                if (n > 0 && n % 9 == 0) {
                    html += '</li><li class="cs_item">';
                }

                // 是否是铸造成功的装备
                if (item.id == that.successEuqipId) {
                    that.nowItemIndex = i;
                }

                html += '<div class="have item c_i_bj'+ item.quality +'" itemid="'+ item.id +'" type="1" index="'+ i +'">';
                html += '<span class="img" style="background-image: url('+ xGame.itemurl(item.equipid) +')"></span>';

                if (parseInt(item.hammer) > 0) {
                    html += '<span class="hammer hammer'+ item.hammer_quality +'">' + item.hammer + '</span>';
                }

                if (parseInt(item.level) > 0) {
                    html += '<span class="strong_level">' + item.level + '</span>';
                }

                if (item.gname != false) {
                    html += '<em class="name">' + item.gname + '</em>';
                }

                html += '</div>';

                n ++;
            }

            // 构建材料列表
            for (var i = 0, materialLen = that.materialData.length; i < materialLen; i++) {
                item = that.materialData[i];

                if (n > 0 && n % 9 == 0) {
                    html += '</li><li class="cs_item">';
                }

                html += '<div class="have item c_i_bj'+ item.quality +'" itemid="'+ item.id +'" type="2" index="'+ i +'">';
                html += '<span class="img" style="background-image: url('+ xGame.forgeMaterial(item.materials_id) +')"></span>';
                html += '<span class="jian_num">' + item.num + '</span>';
                html += '</div>';
                n ++;
            }

            // 补空格
            var repeatDom = '<div class="item c_i_bj1"></div>';

            if (n == 0) {
                html += helper.str_repeat(repeatDom, 9);
            } else if (n % 9 == 0) {
                html += '';
            } else {
                html += helper.str_repeat(repeatDom, 9 - n % 9);
            }

            html += '</li>';
            that.listDom.find('.carousel_content').html(html);

            that.listScroller = xGame.carousel('forgelist_carousel', 450);
            
            var page = Math.floor(that.nowItemIndex / 9);
            that.listScroller.scrollTo(-page * 450, 0);
            that.listDom.find('.carousel_page li').eq(page).addClass('active').siblings().removeClass('active');

            // 显示物品信息
            if (equipLen > 0) {
                that.buildInfo(that.nowItemIndex, 1);
                that.listDom.find('.item').eq(that.nowItemIndex).addClass('selected');
            } else if (materialLen > 0) {
                that.buildInfo(that.nowItemIndex, 2);
                that.listDom.find('.item').eq(that.nowItemIndex).addClass('selected');
            }

            repeatDom = null;
            item = null;
            html = null;
        },

        // 构建装备和材料信息
        buildInfo: function (index, type) {

            var attributeDom = this.infoDom.find('.attribute');
            var gradeDom = this.infoDom.find('.grade');
            var data = null;

            if (type == 1) {
                data = this.equipData[index];
            } else {
                data = this.materialData[index];
            }

            this.nowItemId = data.id;
            this.nowItemIndex = index;
            this.nowItemType = type;
            
            this.infoDom.find('.itembox').attr('class', 'itembox c_i_bj' + data.quality);
            this.infoDom.find('.name').css('color', helper.getItemColor(data.quality)).html(data.name);
            this.infoDom.find('.occupation > .des').html(helper.getItemVocation(data.type));
            this.infoDom.find('.need_level > .des').html(data.needlevel || 0);
            this.infoDom.find('.strong_level > .des').html(data.level || 0);
            this.infoDom.find('.intro > .des').html(data.desc);

            if (type == 1) { // 装备
                this.infoDom.find('.itemimg').css('background-image', 'url('+ xGame.itemurl(data.equipid) +')');
                this.infoDom.find('.number').html(data.saleprice);
                attributeDom.children('.title').html(helper.getItemPowerName(data.type) + '：').siblings('.des').html(data.allpower);
                attributeDom.show();
                this.forgeBtn.show();
                this.sellBtn.hide();
                this.priceDom.hide();
            } else { // 材料
                this.infoDom.find('.itemimg').css('background-image', 'url('+ xGame.forgeMaterial(data.materials_id) +')');
                this.infoDom.find('.number').html(parseInt(data.sales) * parseInt(data.num));
                attributeDom.hide();
                this.forgeBtn.hide();
                this.sellBtn.show();
                this.priceDom.show();
            }

            var newQuality = parseInt(data.newquality);

            // 装备品级
            if (newQuality > 0) {
                gradeDom.children('.grade_des').css('color', helper.getItemColor(newQuality + 1)).html(helper.getNewEquipInfo(newQuality, 'title'));
                gradeDom.show();
                this.forgeBtn.html(lang('forgepop_cast_again'));
                this.forgePageDom.find('#forge_cast_btn').html(lang('forgepop_cast_again'));
                this.attributeBtn.show();
            } else {
                gradeDom.hide();
                this.forgeBtn.html(lang('forgepop_cast'));
                this.forgePageDom.find('#forge_cast_btn').html(lang('forgepop_cast'));
                this.attributeBtn.hide();
            }

            attributeDom = null;
            gradeDom = null;
            data = null;
        },

        // 构建卖出物品界面
        buildSellItemPage: function () {
            
            var data = this.materialData[this.nowItemIndex];
            this.nowMaterialNum = this.selectMaterialNum = parseInt(data.num);
            this.nowMaterialPrice = parseInt(data.sales);

            this.sellPageDom.find('.itemimg').css('background-image', 'url('+ xGame.forgeMaterial(data.materials_id) +')');
            this.sellPageDom.find('.jian_num').html(data.num);
            this.setSellItemInfo(this.nowMaterialNum);
            this.progressCoverDom.width('100%');
            this.sellPageDom.show();

            this.barOffset = this.sellPageDom.find('.progress_bar').offset();
            this.nowbarRatio = 100;
            this.minbarRatio = 1 / this.nowMaterialNum * 100;
            data = null;
        },

        // 设置卖出物品的数量和价格
        setSellItemInfo: function (num) {
            this.sellPageDom.find('.num > .des').html(num);
            this.sellPageDom.find('.price > .des').html(num * this.nowMaterialPrice);
        },

        // 卖出材料
        sellMaterial: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                xGame.unlock();

                if (parseInt(res.status) == 1) {
                    player.updateinfo({
                        'silver': res.silver
                    });

                    xGame.toast.show(lang('sell_up_get', [that.selectMaterialNum * that.nowMaterialPrice]));

                    var data = that.materialData[that.nowItemIndex];
                    data.num = that.nowMaterialNum - that.selectMaterialNum;

                    if (parseInt(data.num) > 0) {
                        that.buildSellItemPage();
                    } else {
                        that.sellPageDom.hide();
                    }

                    that.refreshMaterialInfo();
                } else {
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'sell',
                'd': 'newequip',
                'id': that.nowItemId,
                'num': that.selectMaterialNum,
            }, render);
        },

        // 刷新当前材料信息
        refreshMaterialInfo: function () {
            
            var length = this.equipData.length || 0;
            var index = length + this.nowItemIndex;
            var dom = this.listDom.find('.item').eq(index);
            var num = parseInt(this.materialData[this.nowItemIndex].num);
            
            if (num > 0) {
                dom.find('.jian_num').html(num);
            } else {
                var firstDom = dom.siblings('.have').first();
                dom.replaceWith('<div class="item c_i_bj1"></div>');

                if (firstDom.length > 0) {
                    firstDom.trigger('click');
                } else {
                    this.clearMaterialInfo();
                }
            }

            dom = null;
        },

        // 清除当前材料信息
        clearMaterialInfo: function () {
            this.infoDom.find('.itembox').attr('class', 'itembox c_i_bj1');
            this.infoDom.find('.itemimg').css('background-image', '');
            this.infoDom.find('.name').empty();
            this.infoDom.find('.occupation > .des').empty();
            this.infoDom.find('.need_level > .des').empty();
            this.infoDom.find('.strong_level > .des').empty();
            this.infoDom.find('.intro > .des').empty();
            this.infoDom.find('.number').empty();
            this.sellBtn.hide();
        },

        // 获取铸造装备信息
        getForgeEquipInfo: function () {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.newEquipData = res.newequiplist;
                    that.forgePageDom.show();
                    that.buildForgeEquipList();
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'get_fabrication_info',
                'd': 'newequip',
                'id': that.nowItemId,
            }, render);
        },

        // 构建铸造装备列表
        buildForgeEquipList: function () {
            
            var html = '';
            var item = null;
            var index = 0;
            var dom = null;
            var height = 0;

            for (var i = 0, len = this.newEquipData.length; i < len; i++) {
                item = this.newEquipData[i];

                if (item.newequipid == this.equipData[this.nowItemIndex].equipid) {
                    index = i;
                }

                html += '<li class="itembox quality'+ item.newequipinfo.quality +'" index="'+ i +'">';
                html += '<span class="itemimg" style="background-image: url('+ xGame.itemurl(item.newequipid) +')"></span>';
                html += '</li>';
            }

            this.forgePageDom.find('.scroller_content').html(html);
            dom = this.forgePageDom.find('.scroller_content .itembox');
            dom.eq(index).addClass('selected');
            height = dom.height() + parseInt(dom.css('margin-bottom'));

            this.newEquipListScroller = new iScroll('forge_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.newEquipListScroller.scrollTo(0, -height * index);
            this.buildForgeEquipInfo(index);
            html = null;
            item = null;
        },

        // 构建铸造装备信息
        buildForgeEquipInfo: function (index) {
            
            this.nowNewEquipIndex = index;
            var data = this.newEquipData[index];
            var info = data.newequipinfo;
            var item = null;
            var html = '';
            var forgeEnable = true; // 材料是否齐全可以铸造
            this.nowNewEquipId = data.newequipid;
            this.forgePageDom.find('.new_itembox').attr('class', 'new_itembox quality' + info.quality);
            this.forgePageDom.find('.new_itemimg').css('background-image', 'url('+ xGame.itemurl(data.newequipid) +')');
            this.forgePageDom.find('.name').css('color', helper.getItemColor(info.quality)).html(info.name);
            this.forgePageDom.find('.occupation > .des').html(helper.getItemVocation(info.type));
            this.forgePageDom.find('.need_level > .des').html(info.needlevel);

            // 如果是普通紫装锻造成普通红装
            if (parseInt(data.typeup) == 1) {
                this.forgePageDom.find('.min_value > .title').html(helper.getItemPowerName(info.type) + '：').siblings('.des').html(data.newpowerdown);
                this.forgePageDom.find('.max_value').hide();
            } else {
                this.forgePageDom.find('.max_value > .title').html(lang('forgepop_max') + this.getItemValue(info.type) + '：').siblings('.des').html(data.sumnewpowerup);
                this.forgePageDom.find('.min_value > .title').html(lang('forgepop_min') + this.getItemValue(info.type) + '：').siblings('.des').html(data.sumnewpowerdown);
                this.forgePageDom.find('.max_value').show();
            }

            // 构建材料列表
            for (var i = 0, len = data.consumearr.length; i < len; i++) {
                item = data.consumearr[i];
                html += '<li class="material_box material_quality'+ item.quality +'">';
                html += '<span class="img" style="background-image: url('+ xGame.forgeMaterial(item.type) +')"></span>';

                if (parseInt(item.ownnum) >= parseInt(item.params)) {
                    html += '<em class="num green">' + item.ownnum + '/' + item.params + '</em>';
                } else {
                    html += '<em class="num red">' + item.ownnum + '/' + item.params + '</em>';
                    forgeEnable = false;
                }
            }

            this.forgePageDom.find('.material_list').html(html);

            // 当材料不全时，不可铸造
            if (!forgeEnable) {
                this.forgePageDom.find('#forge_cast_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
            } else {
                this.forgePageDom.find('#forge_cast_btn').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
            }

            info = null;
            item = null;
            html = null;
        },

        // 获取装备属性
        getItemValue: function (type) {
            
            var str = '';

            switch (parseInt(type)) {
                case 1:
                case 2:
                    str = lang('attack');
                    break;
                case 3:
                case 4:
                    str = lang('defense_force');
                    break;
                case 5:
                    str = lang('life');
                    break;
                default :
                    str = '';
                    break;
            }

            return str;
        },

        // 铸造装备
        castEquip: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {

                    that.successEuqipId = res.ownid;

                    that.playSuccessAnim(function () {
                        that.buildSuccessPage(res.newquality_info);

                        if (res.retsilver > 0) {
                            xGame.toast.show(lang('forgepop_return_silver', [res.retsilver]));
                        }

                        player.updateinfo({
                            'silver': res.allsilver
                        });
                    });

                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'fabrication',
                'd': 'newequip',
                'newequipid': that.nowNewEquipId,
                'id': that.nowItemId
            }, render);
        },

        // 构建铸造成功界面
        buildSuccessPage: function (data) {

            var newQuality = parseInt(data.qualityid) + 1;
            
            this.successPageDom.find('.new_itemimg').css('background-image', 'url('+ xGame.itemurl(data.id) +')');
            this.successPageDom.find('.name').css('color', helper.getItemColor(data.quality)).html(data.name);
            this.successPageDom.find('.occupation > .des').html(helper.getItemVocation(data.type));
            this.successPageDom.find('.level > .des').css('color', helper.getItemColor(newQuality)).html(helper.getNewEquipInfo(data.qualityid, 'title'));
            this.successPageDom.find('.attribute > .title').html(helper.getItemPowerName(data.type) + '：').siblings('.des').html(data.newattr);
            this.successPageDom.find('.evaluation').css('color', helper.getItemColor(newQuality)).html(helper.getNewEquipInfo(data.qualityid, 'des'));
            this.successPageDom.show();
        },

        // 播放锻造成功动画
        playSuccessAnim: function (callback) {

            var that = this;
            var anim = new playFrameAnimClass({
                'css': {
                    'background-image': 'url('+ staticUrl +'img/pop/forgepop_success_light.png)',
                    'left': '50%',
                    'top': '50%',
                    'width': '170px',
                    'height': '170px',
                    'margin-left': '-85px',
                    'margin-top': '-85px',
                },
                'rowNum': 2,
                'colNum': 4,
                'frameNum': 8,
                'loop': false,
                'endDestory': true,
                'callback': function () {
                    callback();
                },
            });

            var dom = this.forgePageDom.find('.material_list .img');
            var itemOffset = this.forgePageDom.find('.new_itembox').offset();
            var materialOffset = dom.offset();
            var endPosX = itemOffset.left + itemOffset.width / 2 - materialOffset.width / 2;
            var endPosY = itemOffset.top + itemOffset.height / 2 - materialOffset.height / 2;
            var cloneDom = dom.clone();

            dom.each(function (i) {
                var $this = $(this);
                var offset = $this.offset();
                var bg = $this.css('background-image');
                cloneDom.eq(i).addClass('moving_img').css({'left': offset.left + 'px', 'top': offset.top + 'px', 'width': offset.width + 'px', 'height': offset.height + 'px', 'background-image': bg});
            });

            that.e.append(cloneDom);

            helper.animate(cloneDom, {'left': endPosX + 'px', 'top': endPosY + 'px'}, 300, 'Linear', function () {
                cloneDom.remove();
                anim.appendTo(that.forgePageDom.find('.new_itembox'));
                anim.play();
                anim = null;
                cloneDom = null;
            });

            dom = null;
        },

        // 刷新界面
        refreshPage: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {

                xGame.unlock();
                
                if (parseInt(res.status) == 1) {
                    that.equipData = res.equiplist;
                    that.materialData = res.materiallist;
                    that.buildList();
                } else {
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'index',
                'd': 'newequip',
            }, render);
        },

        // 获取套装数据
        getSuitData: function () {
            
            var that = this;

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.buildSuitPage(res.suit_attr);
                } else {
                    xGame.toast.show(res.msg);
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'get_suit_attr',
                'd': 'newequip',
                'npid': that.nowNewEquipId
            }, render);
        },

        // 构建套装界面
        buildSuitPage: function (suitData) {
            
            var html = '';

            this.suitPageDom.find('.suit_name').html(suitData.name);

            for (var i = 1; i <= 4; i++) {
                html += '<li class="box c_i_bj'+ suitData.quality +'">';
                html += '<span class="img" style="background-image: url('+ xGame.itemurl(suitData['eid' + i]) +')"></span>';
                html += '<em class="name" style="color: '+ helper.getItemColor(suitData.quality) +'">' + suitData['name' + i] + '</em>';
                html += '</li>';
            }

            this.suitPageDom.find('.equip_list').html(html);
            html = '';

            for (var i = 2; i <= 4; i++) {
                html += '<li class="add_item">';
                html += '<div class="title">' + lang('forgeequipinfopop_equip', [i]) + '</div>';
                html += '<p class="add_attr">' + helper.getSuitAttr(suitData['suit'+ i +'_1'], suitData['params' + i + '_1']) + '</p>';
                html += '<p class="add_attr">' + helper.getSuitAttr(suitData['suit'+ i +'_2'], suitData['params' + i + '_2']) + '</p>';
                html += '</li>';
            }

            this.suitPageDom.find('.add_list').html(html);
            this.suitPageDom.show();
        },

        unbindAll: function () {

            this.listDom.undelegate('.item', 'click');
            this.forgeBtn.unbind('touchend');
            this.sellBtn.unbind('touchend');
            this.sellPageDom.find('#forge_sell_hidebtn').unbind('touchend');
            this.sliderbtn.unbind();
            this.sellPageDom.undelegate('.operation_btn', 'touchend');
            this.sellPageDom.find('#forge_sell_sale_btn').unbind('touchend');
            this.forgePageDom.find('#forge_page_hidebtn').unbind('touchend');
            this.forgePageDom.undelegate('.itembox', 'click');
            this.forgePageDom.undelegate('#forge_cast_btn.pop_btn_middle', 'touchend');
            this.forgePageDom.undelegate('.material_box', 'touchend');
            this.successPageDom.find('#forge_confirm_btn').unbind('touchend');
            this.attributeBtn.unbind('touchend');
            this.forgePageDom.find('#forge_view_btn').unbind('touchend');
            this.suitPageDom.find('#forge_suit_hidebtn').unbind('touchend');
            this.forgePageDom.find('#forge_info_btn').unbind('touchend');
            this.infoPageDom.find('#forge_info_hidebtn, .mask').unbind('touchend');
        },

        hide: function () {
            
            xGame.unlock();
            this.unbindAll();

            // 列表的滚动控件
            if (this.listScroller) {
                this.listScroller.destroy();
            }

            this.listScroller = null;

            // 要铸造装备列表
            if (this.newEquipListScroller) {
                this.newEquipListScroller.destroy();
            }

            this.newEquipListScroller = null;

            this.isshow = false;
            this.equipData = null; // 装备数据
            this.materialData = null; // 材料数据
            this.listDom = null; // 装备和材料列表
            this.infoDom = null; // 装备和材料的信息
            this.forgePageDom = null; // 铸造弹窗界面
            this.sellPageDom = null; // 出售材料界面
            this.nowItemId = null; // 当前查看物品的id
            this.nowItemIndex = null; // 当前查看物品的index
            this.nowItemType = null; // 当前查看物品的类型，1是装备，2是材料
            this.newEquipData = null; // 要铸造的装备数据
            this.nowNewEquipId = null; // 当前查看要铸造成的装备id
            this.nowNewEquipIndex = null; // 当前查看要铸造成的装备index
            this.nowMaterialNum = null; // 当前卖出材料的总量
            this.nowMaterialPrice = null; // 当前材料的单价
            this.selectMaterialNum = null; // 选择的材料数量
            this.nowPosX = null; // 当前触摸的水平位置
            this.barOffset = null; // 进度条外框的位置属性
            this.nowbarRatio = null; // 当前进度条的百分比
            this.minbarRatio = null; // 最小进度条的百分比
            this.sliderbtn = null; // 滑块
            this.progressCoverDom = null; // 进度条
            this.forgeBtn = null; // 主界面中的铸造按钮
            this.sellBtn = null; // 主界面中的卖出按钮
            this.priceDom = null; // 物品价格
            this.successPageDom = null; // 锻造成功界面
            this.attributeBtn = null; // 主界面的属性按钮
            this.suitPageDom = null; // 查看套装界面
            this.successEuqipId = null; // 铸造成功的装备ID
            this.e.remove();
            this.e = null;

            if (forgepop) {
                forgepop = null;
            }
        }
    };
})();