/* 背包 */
(function() {
    packPopClass = function() {
        this.e;
        this.isshow = false;
        this.data; //以武器id为键
        this.nowViewId = 0; //当前的ID
        this.myScroll = null;
    };
    packPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                that = null;
                return;
            }

            var initRender = function(res) {
                if (!res) {
                    xGame.toast.show(lang('get_bag_data_fail'));
                    $('#worldloading').hide();
                    return;
                }
                /* 保存数据 */
                that.data = {};
                var list = $.isArray(res.list) ? res.list : helper.array_values(res.list || []);
                for (var i = 0; i < list.length; i++) {
                    that.data[list[i]['id']] = list[i];
                }

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                that.e = xGame.loadPopTmpl('pack');
                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    packpop.hide();
                    e.preventDefault();
                });

                /* 第二步 构建数据列表等 */
                that.buildlist(list);

                /* 第四步 展示弹窗 */
                that.e.show();
                $('#worldloading').hide();

                xGame.showpop('system', {
                    'pop': 'pack',
                    'callback': null,
                });

                that = null;
                initRender = null;
            };
            loadContents({
                'c': 'pack',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        buildRight: function(data) {

            var dom_right = this.e.find('.iteminfo_wrap');

            if (data.type <= 5) {
                dom_right.children('.itembox').attr('class', 'itembox c_i_bj' + data.quality).children('span').css('background', 'url(' + xGame.itemurl(data.equipid) + ') center center no-repeat;');
                dom_right.children('.iteminfo_p').children('.powerup').html(helper.getItemPowerName(data.type) + '：<span>' + data.powerup + '</span>');
                dom_right.children('.iteminfo_desc').children('p').text(data.description);
            } else if(data.type <= 10) {//卷轴
                dom_right.children('.itembox').attr('class', 'itembox c_i_bj' + data.quality).children('span').css('background', 'url(' + staticUrl + 'img/pop/talent_item' + (parseInt(data.type) - 5) + '.png' + ') center center no-repeat;');
                dom_right.children('.iteminfo_p').children('.powerup').html('');
                dom_right.children('.iteminfo_desc').children('p').text(lang('can_tianfu_lvup'));
            } else if (data.type == 100) {  // 武器精华等
                dom_right.children('.itembox').attr('class', 'itembox c_i_bj' + data.quality).children('span').css('background', 'url(' + xGame.essence(data.equipid) + ') center center no-repeat;');
                dom_right.children('.iteminfo_p').children('.powerup').html('');
                dom_right.children('.iteminfo_desc').children('p').text(data.description);
            } else { //跨服道具
                dom_right.children('.itembox').attr('class','itembox c_i_bj1').children('span').css('background', 'url(' + staticUrl + 'img/'+ packpop.getItemImg(parseInt(data.type) - 10)+') center center no-repeat;');
                dom_right.children('.iteminfo_p').children('.powerup').html('');
                dom_right.children('.iteminfo_desc').children('p').text(lang('packpop_use_tips'));
            }

            dom_right.children('.iteminfo_p').children('.vocation').children('span').text(helper.getItemVocation(data.type));
            dom_right.children('.iteminfo_p').children('.powerlevel').children('span').text(data.level);
            dom_right.children('.iteminfo_p').children('.needlevel').children('span').text(data.needlevel);

            if(data.type <= 10) {//卖出道具
                var name = (data.full_num == undefined ? data.name: data.name + '<em>' + data.full_num + '</em>');
                dom_right.children('.iteminfo_p').children('h4').html(name).css({'color': helper.getItemColor(data.quality)});
                dom_right.find('.itemprice').css({'opacity':'1', 'display':'block'});
                dom_right.find('.itemprice').children('span').text(data.saleprice + lang('silver'));
                dom_right.find('#packpop_sellbtn').html(lang('sell')).unbind('touchend').bind('touchend', function() {
                    packpop.sellItemConfirm(data.id);
                });
            } else if (data.type == 100) {  // 武器精华等
                dom_right.children('.iteminfo_p').children('h4').html(data.name).css({'color': helper.getItemColor(data.quality)});
                dom_right.find('.itemprice').css({'opacity':'1', 'display':'block'}).children('span').text(data.saleprice + lang('silver'));
                dom_right.find('#packpop_sellbtn').html(lang('sell')).unbind('touchend').bind('touchend', function() {
                    packpop.sellItemConfirm(data.id);
                });
            } else {//使用道具
                var name = packpop.getItemName(parseInt(data.type) - 10) +''+ data.name;
                dom_right.children('.iteminfo_p').children('h4').html(name).css({'color':helper.getItemColor(1)});
                dom_right.find('.itemprice').css({'opacity':'0', 'display':'none'});
                dom_right.find('#packpop_sellbtn').html(lang('use')).unbind('touchend').bind('touchend', function() {
                    packpop.useItemConfirm(data.id);
                });
            }

        },

        /* 构建列表，重复构建即刷新 */
        buildlist: function(data) {
            var that = this;

            if (that.myScroll != null) { //先干掉原本的滑动
                that.myScroll.scrollTo(0, 0);
                that.myScroll.destroy();
                delete that.myScroll;
            }
            var html = '<li class="cs_item packpop_list">';
            var n = 0;
            for (var i in data) {
                if (n > 0 && n % 9 == 0) { //下一页了
                    html += '</li><li class="cs_item packpop_list">';
                }
                var item = data[i];
                if (item.type <= 5) { //普通道具
                    html += '<div id="packitem' + item.id + '" class="c_i_bj' + item.quality + ' packitem ' + (n == 0 ? ' selected': '') + '" itemid="' + item.id + '"><div class="itempic" style="background-image:url(' + xGame.itemurl(item.equipid) + ')' + '"></div>' + (item.full_num == undefined ? ((item.level > 0 ? '<span class="slevel">' + item.level + '</span>': '') + (parseInt(item.hammer) > 0 ? '<span class="hammer hammer'+ item.hammer_quality +'">' + item.hammer + '</span>' : '')) : '<span class="itempart"></span><span class="part_num">' + item.full_num + '</span>') + '<p class="name" style="display:none;color:' + helper.getItemColor(item.quality) + '">' + item.name + '</p></div>';
                } else if(item.type <= 10) {//天赋卷轴
                    html += '<div id="packitem' + item.id + '" class="c_i_bj' + item.quality + ' packitem ' + (n == 0 ? ' selected': '') + '" itemid="' + item.id + '"><div class="itempic" style="background-image:url(' + staticUrl + 'img/pop/talent_item' + (parseInt(item.type) - 5) + '.png' + ');"></div>' + (item.chip == 0 ? '<span class="jian_num">' + item.number + '</span>': '<span class="itempart"></span><span class="part_num">' + item.full_num + '</span>') + '<p class="name" style="display:none;color:' + helper.getItemColor(item.quality) + '">' + item.name + '</p></div>';
                } else if (item.type == 100) {  // 武器精华等
                    html += '<div id="packitem' + item.id + '" class="c_i_bj' + item.quality + ' packitem ' + (n == 0 ? ' selected': '') + '" itemid="' + item.id + '"><div class="itempic" style="background-image:url(' + xGame.essence(item.equipid) + ');"></div>' + '<span class="jian_num">' + item.number + '</span>' + '<p class="name" style="display:none;color:' + helper.getItemColor(item.quality) + '">' + item.name + '</p></div>';
                } else {//跨服道具
                    html += '<div id="packitem' + item.id + '" class="c_i_bj' + 1 + ' packitem ' + (n == 0 ? ' selected' : '') + '" itemid="' + item.id + '"><div class="itempic" style="background-image:url(' + staticUrl + 'img/'+packpop.getItemImg(parseInt(item.type) - 10)+');"></div>' + (item.chip == 0 ? '<span class="jian_num">' + item.number + '</span>' : '<span class="itempart"></span><span class="part_num">' + item.full_num + '</span>') + '<p class="name" style="display:none;color:' + helper.getItemColor(1) + '">' + item.name+ '</p></div>';
                }
                n++;
            }
            /* 补空格 */
            var repeat_s = '<div class="packitem c_i_bj1"><div class="itempic"></div><p class="name">&nbsp;</p></div>';
            if (n == 0) { //神马也没有
                html += helper.str_repeat(repeat_s, 9);
                that.cleanRight();
            } else if (n % 9 == 0) {
                html += '';
            } else {
                html += helper.str_repeat(repeat_s, 9 - n % 9);
            }
            html += '</li>';
            that.e.find('.carousel_content').html(html);

            /* 创建旋转门 domid, 单页宽度 */
            that.myScroll = xGame.carousel('packlist_carousel', 450);

            /* 绑定事件 */
            $('#packlist_carousel').undelegate('.packitem', 'click').delegate('.packitem', 'click',
            function() {
                packpop.viewItem($(this));
            });

            /* 绑定右侧数据*/
            //获取数组第一条数据，通用方法
            var rightdata = helper.array_first(data);
            if (rightdata) {
                that.nowViewId = rightdata.id;
                that.buildRight(rightdata);
            }
            that = null;
        },

        /* 出售某件道具确认框 */
        sellItemConfirm: function(id) {
            var item = this.data ? this.data[id] : null;
            if (item) {
                if (item.is_equipped == 1 || item.gid > 0) {
                    xGame.toast.show('已穿戴装备无法出售，请先在将领面板卸下！');
                    return false;
                }
                xGame.confirm.show(lang('notice'), lang('sell_ask', [item.saleprice]), 2,
                function() {
                    xGame.confirm.hide(function() {
                        packpop.sellItem(id);
                    });
                },
                function() {
                    xGame.confirm.hide();
                });
            }
        },

        /* 出售某件道具确认框 */
        useItemConfirm : function(id) {
            if(this.data[id]) {
                var that = this;

                var sellRender = function(res) {
                    if(res.status == 1) {
                        xGame.confirm.show(lang('notice'), lang('packpop_use_get', [that.getItemName(res.info.type) + res.info.reward]), 2, function() {
                               xGame.confirm.hide(
                                   function() {
                                       packpop.useItem(id);
                                   }
                               );
                            }, function() {
                                xGame.confirm.hide();
                            }
                        );
                    } else if(res.status == -1) {
                        xGame.toast.show(lang('undefined_error'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    that = null;
                    sellRender = null;
                    xGame.unlock();
                };
                loadContents({'c':'pack', 'm':'use_item', 'id':id, 'confirm':0}, sellRender);
                sellRender = null;
            }
        },

        /* 清空右边的信息 */
        cleanRight: function() {

            var dom_right = this.e.find('.iteminfo_wrap');

            dom_right.children('.itembox').attr('class', 'itembox c_i_bj1');
            dom_right.children('.itembox').children('span').css({
                'background': 'none'
            });
            dom_right.children('.iteminfo_p').children('h4').text('');
            dom_right.children('.iteminfo_p').children('.vocation').children('span').text('');
            dom_right.children('.iteminfo_p').children('.powerlevel').children('span').text('');
            dom_right.children('.iteminfo_p').children('.needlevel').children('span').text('');
            dom_right.children('.iteminfo_p').children('.powerup').html('');
            dom_right.children('.iteminfo_desc').children('p').text('');
            dom_right.find('.itemprice').hide();
            dom_right.find('#packpop_sellbtn').unbind('touchend').hide();

        },

        /* 出售 */
        sellItem: function(id) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var sellRender = function(res) {
                if (res.status == 1) {
                    delete that.data[id];
                    $('#packitem' + id).removeAttr('itemid').attr('class', 'c_i_bj1 packitem').html('<div class="itempic"></div><p class="name">&nbsp;</p>');

                    var near = that.getRightDom($('#packitem' + id).siblings());
                    if (near != null) { //查看邻居的信息
                        packpop.viewItem($(near));
                    } else {
                        that.cleanRight();
                    }
                    //that.buildlist(that.data);
                    player.updateinfo({
                        'silver': (parseInt(res.all_silver))
                    }); //更新用户信息
                    xGame.toast.show(lang('sell_success', [res.silver]));
                } else if (res.status == -1) {
                    xGame.toast.show(lang('sell_fail_no_equip'));
                } else if (res.status == -2) {
                    xGame.toast.show(res.message || res.msg || '已穿戴装备无法出售，请先在将领面板卸下！');
                } else if (res.status == -3) {
                    xGame.toast.show(lang('packpop_sell_closed'));
                } else {
                    xGame.toast.show(res.message || res.msg || lang('sell_fail_unknown'));
                }

                that = null;
                sellRender = null;
                xGame.unlock();
            };
            loadContents({
                'c': 'pack',
                'm': 'sale',
                'id': id
            },
            sellRender);
            sellRender = null;
        },

        /* 使用 */
        useItem : function(id) {
            var that = this;

            if(xGame.checkLock()) {
                return false;
            }

            var sellRender = function(res) {
                if(res.status == 1) {
                    if (res.number == 0) {
                        delete that.data[id];
                        $('#packitem'+id).removeAttr('itemid').attr('class','c_i_bj1 packitem').html('<div class="itempic"></div><p class="name">&nbsp;</p>');
                        var near = that.getRightDom($('#packitem'+id).siblings());
                        if(near != null) {//查看邻居的信息
                            packpop.viewItem($(near));
                        } else {
                            that.cleanRight();
                        }
                    } else {
                        $('#packitem'+id).find('.jian_num').html(res.number);
                    }
                    player.updateinfo({'silver' : (parseInt(res.silver)), 'act': (parseInt(res.act))});//更新用户信息
                    xGame.toast.show(lang('get') + packpop.getItemName(res.info.type) + res.info.reward);
                } else if(res.status == -1) {
                    xGame.toast.show(lang('undefined_error'));
                } else {
                    xGame.toast.show(lang('undefined_error'));
                }

                that = null;
                sellRender = null;
                xGame.unlock();
            };
            loadContents({'c':'pack', 'm':'use_item', 'id':id, 'confirm':1}, sellRender);
            sellRender = null;
        },

        getRightDom: function(listDom) {
            var obj = null;
            for (var i = 0; i < listDom.length; i++) {
                if (obj == null && $(listDom[i]).find('.name').html() != '&nbsp;') {
                    obj = listDom[i];
                }
            }
            return obj;
        },

        /* 查看某件道具 */
        viewItem: function(obj) {
            var that = this;

            var id = obj.attr('itemid');
            if (that.data[id]) {
                $('#packitem' + that.nowViewId).removeClass('selected');
                obj.addClass('selected');
                that.nowViewId = id;
                that.buildRight(that.data[id]);
                obj = null;
                //this.e.find('.iteminfo_wrap').find('.itemprice').show();
                this.e.find('.iteminfo_wrap').find('#packpop_sellbtn').show();
            }

        },

        /* 取跨服道具名称 */
        getItemName: function(type) {
            var item = {1:lang('warena_item1'), 2:lang('warena_item2'), 3:lang('warena_item3'), 4:lang('warena_item4'), 5:lang('warena_item5'), 6:lang('warena_item6'), 7:lang('warena_item7'),8:lang('warena_item8')};
            type = type != null ? type : 1;
            return item[type];
        },
        /* 取跨服道具图片 */
        getItemImg: function(type) {
            var item = {1:'pop/worshippop_type2.png', 2:'pop/pop_gem1.png', 3:'pop/pop_gem2.png', 4:'pop/pop_gem3.png', 5:'pop/worshippop_type3.png', 6:'pop/worshippop_type4.png', 7:'pop/worshippop_type5.png', 8:'pop/worshippop_type6.png'};
            type = type != null ? type : 1;
            return item[type];
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                var dom_right = this.e.find('.iteminfo_wrap');
                if (dom_right) {
                    dom_right.find('#packpop_sellbtn').unbind('touchend');
                    dom_right.find('#packpop_sellbtn').unbind('touchend');
                }
                this.e.remove();
                this.e = null;
            }
            $('#packlist_carousel').undelegate('.packitem', 'click');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.cleanRight();
            if (this.myScroll) {
                this.myScroll.destroy();
                this.myScroll = null;
            }
            this.data = null;
            this.nowViewId = null;
            this.unbindAll();
        },
    };
})();