// 分解
(function () {
    resolvePopClass = function () {
        this.e = null;
        this.isshow = false;
        this.equipData = null; // 装备数据
        this.listDom = null; // 装备列表
        this.infoDom = null; // 装备信息
        this.nowItemId = null; // 当前查看物品的id
        this.nowItemIndex = null; // 当前查看物品的index
        this.successPageDom = null; // 分解成功界面
        this.successScroller = null; // 分解成功界面的滚动控件
    };

    resolvePopClass.prototype = {

        init: function () {
            
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.equipData = res.equiplist;
                    that.initPage();
                } else {
                    xGame.toast.show(res.msg);
                    $('#worldloading').hide();
                }
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'resolve_index',
                'd': 'newequip',
            }, render);
        },

        initPage: function () {

            this.e = xGame.loadPopTmpl('resolve');
            this.listDom = this.e.find('#resolvelist_carousel');
            this.infoDom = this.e.find('#resolve_info');
            this.successPageDom = this.e.find('#resolve_success_page');

            this.buildList();
            this.bindAll();

            this.e.show();
            this.isshow = true;
            $('#worldloading').hide();

            xGame.showpop('system', {
                'pop': 'resolve',
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


                if (!$this.hasClass('have')) {
                    $this = null;
                    return false;
                }

                var index = parseInt($this.attr('index'));
                that.buildInfo(index);
                that.listDom.find('.item').removeClass('selected');
                $this.addClass('selected');
                $this = null;

                e.preventDefault();
            });

            // 分解按钮
            that.infoDom.find('#resolve_resolve_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                var data = that.equipData[that.nowItemIndex];

                xGame.confirm.show(lang('resolvepop_resolve_title'), lang('resolvepop_resolve_content', [data.name, helper.getItemColor(data.quality)]), 2, function () {
                    xGame.confirm.hide();
                    that.resolveEquip();
                }, function () {
                    xGame.confirm.hide();
                });

                e.preventDefault();
            });

            // 分解成功界面的确定按钮
            that.successPageDom.find('#resolve_confirm_btn').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.successScroller.destroy();
                that.successScroller = null;
                that.successPageDom.hide();
                e.preventDefault();
            });
        },

        // 构建装备列表
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

                html += '<div class="have item c_i_bj'+ item.quality +'" itemid="'+ item.id +'" index="'+ i +'">';
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

            that.listScroller = xGame.carousel('resolvelist_carousel', 450);

            // 显示第一个物品的信息
            if (equipLen > 0) {
                that.buildInfo(0);
                that.listDom.find('.item:first').addClass('selected');
            }

            repeatDom = null;
            item = null;
            html = null;
        },

        // 构建装备信息
        buildInfo: function (index) {

            var gradeDom = this.infoDom.find('.grade');
            var data = this.equipData[index];
            var html = '';
            var item = null;

            this.nowItemId = data.id;
            this.nowItemIndex = index;
            
            this.infoDom.find('.itembox').attr('class', 'itembox c_i_bj' + data.quality);
            this.infoDom.find('.itemimg').css('background-image', 'url('+ xGame.itemurl(data.equipid) +')');
            this.infoDom.find('.name').css('color', helper.getItemColor(data.quality)).html(data.name);
            this.infoDom.find('.occupation > .des').html(helper.getItemVocation(data.type));
            this.infoDom.find('.need_level > .des').html(data.needlevel || 0);
            this.infoDom.find('.strong_level > .des').html(data.level || 0);
            this.infoDom.find('.number').html(data.saleprice);
            this.infoDom.find('.attribute > .title').html(helper.getItemPowerName(data.type) + '：').siblings('.des').html(data.allpower);

            var newQuality = parseInt(data.newquality);

            // 装备品级
            if (newQuality > 0) {
                gradeDom.children('.grade_des').css('color', helper.getItemColor(newQuality + 1)).html(helper.getNewEquipInfo(newQuality, 'title'));
                gradeDom.show();
            } else {
                gradeDom.hide();
            }

            // 构建获得神铸材料列表
            for (var i = 0, len = data.getgod.length; i < len; i++) {
                item = data.getgod[i];
                html += '<li class="material_item">';
                html += '<span class="material_name" style="background-image: url('+ xGame.forgeMaterial(item.godcast) +');color: '+ helper.getItemColor(item.quality) +';">' + item.name + 'x' + item.num + '</span>';
                
                // 获得材料的概率
                if (item.gcrate == 100) {
                    html += lang('elitepop_get');
                } else {
                    html += lang('xiao_gailv');
                }

                html += '</li>';
            }

            this.infoDom.find('.material_list').html(html);
            this.infoDom.find('#resolve_resolve_btn').show();

            gradeDom = null;
            data = null;
            html = null;
            item = null;
        },

        // 分解装备
        resolveEquip: function () {
            
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {
                
                if (parseInt(res.status) == 1) {
                    that.playAnim(function () {
                        that.buildSuccessPage(res);
                        xGame.unlock();
                    });
                } else {
                    xGame.unlock();
                    xGame.toast.show(res.msg);
                }
            };

            loadContents({
                'c': 'equip_forge',
                'm': 'resolve',
                'd': 'newequip',
                'id': that.nowItemId,
            }, render);
        },

        // 播放分解动画
        playAnim: function (callback) {
            
            var anim = new playFrameAnimClass({
                'css': {
                    'background-image': 'url('+ staticUrl + 'img/pop/resolvepop_anim.png)',
                    'left': '50%',
                    'top': '50%',
                    'width': '250px',
                    'height': '250px',
                    'margin-left': '-125px',
                    'margin-top': '-125px',
                    'z-index': '10',
                },
                'rowNum': 3,
                'colNum': 3,
                'frameNum': 9,
                'callback': function () {
                    callback();
                },
            });

            anim.appendTo(this.infoDom.find('.itembox'));
            anim.play();
            anim = null;
        },

        // 构建分解成功界面
        buildSuccessPage: function (data) {
            
            var html = '';
            var item = null;
            var resourceData = data.ret_resoure;

            player.updateinfo({
                'silver': resourceData.allsilver
            });

            if (resourceData.silver) {
                html += '<li class="resource_item type1">x' + helper.convertWan(resourceData.silver) + '</li>';
            }

            if (resourceData.jade) {
                html += '<li class="resource_item type2">x' + helper.convertWan(resourceData.jade) + '</li>';
            }

            if (resourceData.refinestone) {
                html += '<li class="resource_item type3">x' + helper.convertWan(resourceData.refinestone) + '</li>';
            }

            if (resourceData.colorstone) {
                html += '<li class="resource_item type4">x' + helper.convertWan(resourceData.colorstone) + '</li>';
            }

            if (resourceData.bloodstone) {
                html += '<li class="resource_item type5">x' + helper.convertWan(resourceData.bloodstone) + '</li>';
            }

            this.successPageDom.find('.get_resource_list').html(html);

            html = '';

            for (var i = 0, len = data.get_materials.length; i < len; i++) {
                item = data.get_materials[i];
                html += '<li class="material_item" style="background-image: url('+ xGame.forgeMaterial(item.materials_id) +');color: '+ helper.getItemColor(item.quality) +';">' + item.name + 'x' + item.num + '</li>';
            }

            for (var i = 0, len = resourceData.materials.length; i < len; i++) {
                item = resourceData.materials[i];
                html += '<li class="material_item" style="background-image: url('+ xGame.forgeMaterial(item.materials_id) +');color: '+ helper.getItemColor(item.quality) +';">' + item.name + 'x' + item.num + '</li>';
            }

            for (var i = 0, len = resourceData.essence.length; i < len; i++) {
                item = resourceData.essence[i];
                html += '<li class="material_item" style="background-image: url('+ xGame.essence(item.id) +');color: '+ helper.getItemColor(item.quality) +';">' + item.name + 'x' + item.value + '</li>';
            }

            this.successPageDom.find('.get_material_list').html(html);
            this.successPageDom.show();

            this.successScroller = new iScroll('resolve_success_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.refreshInfo();

            item = null;
            resourceData = null;
            html = null;
        },

        // 刷新当前装备信息
        refreshInfo: function () {
            
            var dom = this.listDom.find('.item').eq(this.nowItemIndex);
            var firstDom = dom.siblings('.have').first();
            dom.replaceWith('<div class="item c_i_bj1"></div>');

            if (firstDom.length > 0) {
                firstDom.trigger('click');
            } else {
                this.clearInfo();
            }

            dom = null;
            firstDom = null;
        },

        // 清除当前装备信息
        clearInfo: function () {
            this.infoDom.find('.itembox').attr('class', 'itembox c_i_bj1');
            this.infoDom.find('.itemimg').css('background-image', '');
            this.infoDom.find('.name').empty();
            this.infoDom.find('.occupation > .des').empty();
            this.infoDom.find('.need_level > .des').empty();
            this.infoDom.find('.strong_level > .des').empty();
            this.infoDom.find('.get_material > .material_list').empty();
            this.infoDom.find('.number').empty();
            this.infoDom.find('#resolve_resolve_btn').hide();
        },

        unbindAll: function () {
            this.listDom.undelegate('.item', 'click');
            this.infoDom.find('#resolve_resolve_btn').unbind('touchend');
            this.successPageDom.find('#resolve_confirm_btn').unbind('touchend');
        },

        hide: function () {
            xGame.unlock();
            this.unbindAll();

             // 分解成功界面的滚动控件
            if (this.successScroller) {
                this.successScroller.destroy();
            }

            this.successScroller = null;

            this.equipData = null; // 装备数据
            this.listDom = null; // 装备列表
            this.infoDom = null; // 装备信息
            this.nowItemId = null; // 当前查看物品的id
            this.nowItemIndex = null; // 当前查看物品的index
            this.successPageDom = null; // 分解成功界面
            this.e.remove();
            this.e = null;

            if (resolvepop) {
                resolvepop = null;
            }
        },

    };
})();