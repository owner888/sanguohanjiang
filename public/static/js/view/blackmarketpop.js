/* 黑市 */
(function() {
    blackmarketPopClass = function() {
        this.e;
        this.isshow = false;
        this.resourse_Dom = null;
        this.listdata = null;
        this.item_Dom = null;
        this.quality = 0;
    };
    blackmarketPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('blackmarket_cannot_enter'));
                    $('#worldloading').hide();
                    break;
                case - 2 : xGame.toast.show(lang('blackmarket_cannot_enter'));
                    $('#worldloading').hide();
                    break;
                case 2:
                    xGame.toast.show(lang('blackmarket_end'));
                    $('#worldloading').hide();
                    break;
                case 1:
                    //保存数据
                    that.listdata = res.list;
                    //载入界面
                    that.perpage(res.info);
                    break;
                default:
                    break;
                }
            };
            loadContents({
                'c':
                'blackmarket',
                'm': 'index'
            },
            initRender, true); //加载数据

            initRender = null;

        },
        perpage: function(data) {
            var that = this;
            //载入页面
            that.e = xGame.loadPopTmpl('blackmarket');

            if (data.type == 1) {
                that.e.addClass('thank');
            }

            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                blackmarketpop.hide();
                e.preventDefault();
                e.stopPropagation();
            });
            //更新页面所有数据
            that.resourse_Dom = that.e.find('#blackmarketpop_wrap').find('.resourse');
            var silver = parseInt(player.info.silver) > 999999 ? player.getWan(player.info.silver) : player.info.silver;
            var gold = parseInt(player.info.gold) > 999999 ? player.getWan(player.info.gold) : player.info.gold;
            that.resourse_Dom.find('.silver').html(silver);
            that.resourse_Dom.find('.gold').html(gold);
            that.resourse_Dom.find('.jade1').html(helper.convertWan(data.get1));
            that.resourse_Dom.find('.jade2').html(helper.convertWan(data.get2));
            that.resourse_Dom.find('.jade3').html(helper.convertWan(data.get3));

            if (parseInt(data.reputation) > 0 && data.type == 1) {
                that.resourse_Dom.find('.reputation').html(helper.convertWan(data.reputation)).show();
            } else {
                that.resourse_Dom.find('.reputation').hide();
            }

            //更新cd
            xGame.updateCd('blackmarket', data.cd);
            //组装商品list数据
            that.buildlist();
            if (data.times > 0) {
                that.e.find('#refresh').attr('class', 'refresh_btn pop_btn_small').html(lang('blackmarket_free_refresh') + 'x' + data.times);
            } else {
                that.e.find('#refresh').attr('class', 'refresh_btn refresh').html('x' + data.price);
            }
            //绑定刷新按钮
            that.e.find('#refresh').unbind('touchend').bind('touchend',
            function() {
                if (that.quality > 0) {
                    xGame.confirm.show(lang('blackmarket_refresh_title'), lang('blackmarket_refresh_content'), 2,
                    function() {
                        xGame.confirm.hide();
                        that.quality = 0;
                        that.refresh();
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                } else {
                    that.refresh();
                }
            });
            //显示界面
            that.e.show();
            $('#worldloading').hide();
        },

        //组装商品列表数据
        buildlist: function() {
            var that = this;
            for (var i = 1; i <= 3; i++) {
                if (typeof that.listdata[i] != 'undefined') {
                    that.item_Dom = that.e.find('#blackmarketpop_wrap').find('.item' + i);
                    //图片
                    var name = that.listdata[i].name;
                    if (parseInt(that.listdata[i].type) == 1) {
                        var gold = parseInt(that.listdata[i].value);
                        name += 'x' + (gold > 9999 ? player.getWan(gold) : gold);
                        that.item_Dom.find('.img').css({
                            'background': 'url(' + staticUrl + 'img/pop/blackmarket_silver.jpg) center center no-repeat'
                        });
                    } else if (parseInt(that.listdata[i].type) == 2) {
                        name += 'x' + that.listdata[i].value;
                        that.item_Dom.find('.img').css({
                            'background': 'url(' + staticUrl + 'img/pop/barpop_lv1.jpg) center center no-repeat'
                        });
                    } else if (parseInt(that.listdata[i].type) == 3) {
                        name += 'x' + that.listdata[i].value;
                        that.item_Dom.find('.img').css({
                            'background': 'url(' + staticUrl + 'img/pop/barpop_lv2.jpg) center center no-repeat'
                        });
                    } else if (parseInt(that.listdata[i].type) == 4) {
                        name += 'x' + that.listdata[i].value;
                        that.item_Dom.find('.img').css({
                            'background': 'url(' + staticUrl + 'img/pop/barpop_lv3.jpg) center center no-repeat'
                        });
                    } else if (parseInt(that.listdata[i].type) == 5) {
                        that.item_Dom.find('.img').css({
                            'background': 'url(' + xGame.avatar(that.listdata[i].image, true) + ') center center no-repeat'
                        });
                    } else if (parseInt(that.listdata[i].type) == 6) {
                        name += 'x' + that.listdata[i].value;
                        that.item_Dom.find('.img').css({
                            'background': 'url(' + staticUrl + 'img/pop/blackmarket_act.jpg) center center no-repeat'
                        });
                    }
                    //名字
                    that.item_Dom.find('.name').html(name);
                    //文字颜色
                    if (parseInt(that.listdata[i].quality) == 1) {
                        that.item_Dom.find('.name').css({
                            'color': '#FFFFFF'
                        });
                    } else if (parseInt(that.listdata[i].quality) == 2) {
                        that.item_Dom.find('.name').css({
                            'color': '#0ec141'
                        });
                    } else if (parseInt(that.listdata[i].quality) == 3) {
                        that.item_Dom.find('.name').css({
                            'color': '#1681e5'
                        });
                    } else if (parseInt(that.listdata[i].quality) == 4) {
                        that.item_Dom.find('.name').css({
                            'color': '#ffd632'
                        });
                    } else if (parseInt(that.listdata[i].quality) == 5) {
                        that.item_Dom.find('.name').css({
                            'color': '#d018c9'
                        });
                        that.quality += 1;
                    }
                    //商品几折
                    if (appLanguage == 'zh-cn' || appLanguage == 'zh-tw') {
                        that.item_Dom.find('.discount').html(that.listdata[i].discount + '<br>折');
                    } else {
                        that.item_Dom.find('.discount').html(( 100 - Number(that.listdata[i].discount) * 10) + '%<br>off');
                    }

                    //商品价格
                    var classes = '';
                    if (parseInt(that.listdata[i].buy_type) == 1) {
                        classes = 'silver';
                    } else if (parseInt(that.listdata[i].buy_type) == 2) {
                        classes = 'gold';
                    } else if (parseInt(that.listdata[i].buy_type) == 3) {
                        classes = 'gem1';
                    } else if (parseInt(that.listdata[i].buy_type) == 4) {
                        classes = 'gem2';
                    } else if (parseInt(that.listdata[i].buy_type) == 5) {
                        classes = 'gem3';
                    }
                    var cost = Math.floor(parseInt(that.listdata[i].buy_value) / that.listdata[i].discount * 10);
                    that.item_Dom.find('.cost').find('em').attr('class', classes).html(cost);
                    that.item_Dom.find('.current').find('em').attr('class', classes).html(parseInt(that.listdata[i].buy_value));

                    // 声望
                    var reputation = parseInt(that.listdata[i].reputation);

                    if (reputation > 0) {
                        that.item_Dom.find('.cost .reputation').html(helper.convertWan(Math.floor(reputation / that.listdata[i].discount * 10))).show();
                        that.item_Dom.find('.current .reputation').html(helper.convertWan(reputation)).show();
                        that.item_Dom.find('.buy_btn').attr('reputation', reputation);
                    } else {
                        that.item_Dom.find('.reputation').hide();
                        that.item_Dom.find('.buy_btn').removeAttr('reputation');
                    }

                    //判断商品是否锁定
                    that.item_Dom.find('.select').attr('value', i);
                    that.item_Dom.find('.select').attr('quality', that.listdata[i].quality);
                    that.item_Dom.find('.selected').attr('quality', that.listdata[i].quality);
                    that.item_Dom.find('.buy_btn').attr('value', that.listdata[i].id);
                    that.item_Dom.find('.buy_btn').attr('type', that.listdata[i].type);
                    that.item_Dom.find('.buy_btn').attr('site', i);
                    that.item_Dom.find('.buy_btn').attr('quality',that.listdata[i].quality);
                    if(parseInt(that.listdata[i].status) == 1) {
                        that.item_Dom.find('.select').attr('class', 'selected');
                        if(parseInt(that.listdata[i].quality) == 5) {
                            that.quality -= 1;
                        }
                    }
                    that.item_Dom = null;
                }
            }
            //绑定锁定按钮
            that.e.find('#blackmarketpop_wrap').find('.select, .selected').unbind('touchend').bind('touchend',
            function() {
                if ($(this).attr('class') == 'select') {
                    that.lock($(this).attr('value'), 1, $(this).attr('quality'));
                } else {
                    that.lock($(this).attr('value'), 2, $(this).attr('quality'));
                }
            });
            //绑定购买按钮
            that.e.find('#blackmarketpop_wrap').find('.buy_btn').unbind('touchend').bind('touchend',
            function() {
                var quality = false;
                var dom = that.e.find('#blackmarketpop_wrap').find('.item' + $(this).attr('site'));
                var item_id = $(this).attr('value');
                var quality = $(this).attr('quality');
                var status = dom.find('span:last').hasClass('selected');
                if ($(this).attr('type') == 5) {
                    var name = dom.find('.name').html();
                    var price = dom.find('.current').find('em').html();
                    var price_class = dom.find('.current').find('em').attr('class');
                    var reputation = $(this).attr('reputation'); // 该将领所需花的声望，购买神将时才有
                    var content = '';

                    if (reputation != null) {
                        content = lang('blackmarket_buy_godhero_content', [price_class, price, name, reputation]);
                    } else {
                        content = lang('blackmarket_buy_content', [price_class, price, name]);
                    }

                    xGame.confirm.show(lang('blackmarket_buy_title'), content, 2,
                    function() {
                        xGame.confirm.hide();
                        if(quality == 5 && status == false) {
                            quality = true;
                        }
                        that.buy_item(item_id, quality);
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                } else {
                    if(quality == 5 && status == false) {
                        quality = true;
                    }
                    that.buy_item(item_id, quality);
                }

            });
        },

        //锁定or解锁
        lock: function(site, status, quality) {
            var that = this;

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('blackmarket_lock_selecterror'));
                    break;
                case - 2 : xGame.toast.show(lang('blackmarket_lock_noinfo'));
                    break;
                case 2:
                    xGame.toast.show(lang('blackmarket_end'));
                    break;
                case 1:
                    if (status == 1) {
                        if (quality == 5) {
                            that.quality -= 1;
                        }
                        that.e.find('.item' + site).find('.select').attr('class', 'selected');
                    } else {
                        if (quality == 5) {
                            that.quality += 1;
                        }
                        that.e.find('.item' + site).find('.selected').attr('class', 'select');
                    }
                    break;
                default:
                    xGame.toast.show(lang('blackmarket_lock_error'));
                    break;
                }
            };
            loadContents({
                'c':
                'blackmarket',
                'm': 'lock',
                'site': site,
                'status': status
            },
            initRender); //加载数据
            initRender = null;
        },

        //刷新商品
        refresh: function() {
            var that = this;

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('blackmarket_refresh_noinfo'));
                    break;
                case - 2 : xGame.toast.show(lang('blackmarket_refresh_nogold'));
                    break;
                case - 3 : xGame.toast.show(lang('blackmarket_refresh_alllock'));
                    break;
                case - 4 : xGame.toast.show(lang('blackmarket_refresh_errorlist'));
                    break;
                case 2:
                    xGame.toast.show(lang('blackmarket_end'));
                    break;
                case 1:
                    if (res.times > 0) {
                        that.e.find('#refresh').attr('class', 'refresh_btn pop_btn_small').html(lang('blackmarket_free_refresh') + 'x' + res.times);
                    } else {
                        that.e.find('#refresh').attr('class', 'refresh_btn refresh').html('x' + res.price);
                    }
                    player.updateinfo({
                        'gold': parseInt(res.all_gold)
                    });
                    var gold_str = parseInt(res.all_gold) > 999999 ? player.getWan(parseInt(res.all_gold)) : parseInt(res.all_gold);
                    if (gold_str != that.resourse_Dom.find('.gold').html()) {
                        xGame.doAnimate2(that.resourse_Dom.find('.gold'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.gold').html(gold_str);
                        });
                    }
                    that.listdata = res.list;
                    that.buildlist();
                    break;
                default:
                    xGame.toast.show(lang('blackmarket_cannot_refresh'));
                    break;
                }
            };
            loadContents({
                'c':
                'blackmarket',
                'm': 'refresh'
            },
            initRender); //加载数据

            initRender = null;
        },

        //购买商品
        buy_item: function(id, quality) {
            var that = this;

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('blackmarket_buyerror_noinfo'));
                    break;
                case - 2 : xGame.toast.show(lang('blackmarket_buyerror_notinlist'));
                    break;
                case - 3 : xGame.toast.show(lang('blackmarket_buyerror_cannot_get_iteminfo'));
                    break;
                case - 4 : xGame.toast.show(lang('blackmarket_buyerror_nosilver'));
                    break;
                case - 5 : xGame.toast.show(lang('blackmarket_buyerror_nogold'));
                    break;
                case - 6 : xGame.toast.show(lang('blackmarket_buyerror_jade1'));
                    break;
                case - 7 : xGame.toast.show(lang('blackmarket_buyerror_jade2'));
                    break;
                case - 8 : xGame.toast.show(lang('blackmarket_buyerror_jade3'));
                    break;
                case - 9 : xGame.toast.show(lang('blackmarket_buyerror_general_is_own'));
                    break;
                case - 10 : xGame.toast.show(lang('newbar_reputation_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('blackmarket_end'));
                    break;
                case 1:
                    //购买成功
                    if (parseInt(res.get_type) == 1) {
                        xGame.toast.show(lang('blackmarket_buy_1', [(res.get_value > 9999 ? player.getWan(res.get_value) : res.get_value)]));
                    } else if (parseInt(res.get_type) == 2) {
                        xGame.toast.show(lang('blackmarket_buy_2', [res.get_value]));
                    } else if (parseInt(res.get_type) == 3) {
                        xGame.toast.show(lang('blackmarket_buy_3', [res.get_value]));
                    } else if (parseInt(res.get_type) == 4) {
                        xGame.toast.show(lang('blackmarket_buy_4', [res.get_value]));
                    } else if (parseInt(res.get_type) == 5) {
                        miscpop.newhero(res.get_value);
                        if (typeof CD['chat'] != 'undefined') {
                            CD['chat'].read();//手动请求一次，为了早点广播
                        }
                    } else if (parseInt(res.get_type) == 6) {
                        xGame.toast.show(lang('blackmarket_buy_6', [res.get_value]));
                    }
                    //判断是否去掉提示
                    if(quality == true) {
                        that.quality -= 1;
                    }
                    //处理消耗
                    player.updateinfo({
                        'silver': parseInt(res.all_silver),
                        'gold': parseInt(res.all_gold),
                        'act': parseInt(res.all_act)
                    });
                    var silver_str = parseInt(res.all_silver) > 999999 ? player.getWan(parseInt(res.all_silver)) : parseInt(res.all_silver);
                    var gold_str = parseInt(res.all_gold) > 999999 ? player.getWan(parseInt(res.all_gold)) : parseInt(res.all_gold);
                    var reputation_str = helper.convertWan(res.all_reputation);

                    if (silver_str != that.resourse_Dom.find('.silver').html()) {
                        xGame.doAnimate2(that.resourse_Dom.find('.silver'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.silver').html(silver_str);
                        });
                    }

                    if (gold_str != that.resourse_Dom.find('.gold').html()) {
                        xGame.doAnimate2(that.resourse_Dom.find('.gold'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.gold').html(gold_str);
                        });
                    }

                    if (reputation_str != that.resourse_Dom.find('.reputation').html()) {
                        xGame.doAnimate2(that.resourse_Dom.find('.reputation'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.reputation').html(reputation_str);
                        });
                    }

                    if (parseInt(res.sub_type) == 3) {
                        xGame.doAnimate2(that.resourse_Dom.find('.jade1'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.jade1').html(helper.convertWan(res.surplus));
                        });
                    } else if (parseInt(res.sub_type) == 4) {
                        xGame.doAnimate2(that.resourse_Dom.find('.jade2'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.jade2').html(helper.convertWan(res.surplus));
                        });
                    } else if (parseInt(res.sub_type) == 5) {
                        xGame.doAnimate2(that.resourse_Dom.find('.jade3'), 'changcolor', 100, 7,
                        function() {
                            that.resourse_Dom.find('.jade3').html(helper.convertWan(res.surplus));
                        });
                    }
                    //刷新新的列表
                    that.listdata = res.list;
                    that.buildlist();
                    break;
                default:
                    xGame.toast.show(lang('blackmarket_cannot_buy'));
                    break;
                }
            };
            loadContents({
                'c':
                'blackmarket',
                'm': 'buy',
                'id': id
            },
            initRender); //加载数据

            initRender = null;
        },

        unbindAll: function () {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('#refresh').unbind('touchend');
            this.e.find('#blackmarketpop_wrap').find('.select, .selected').unbind('touchend');
            this.e.find('#blackmarketpop_wrap').find('.buy_btn').unbind('touchend');
        },

        hide: function() {
            xGame.unlock();
            xGame.updateCd('blackmarket', 0);      
            this.quality = 0;
            this.isshow = false;
            this.listdata = null;
            if (this.resourse_Dom) {
                this.resourse_Dom.remove();
                this.resourse_Dom = null;
            }
            if (this.item_Dom) {
                this.item_Dom.remove();
                this.item_Dom = null;
            }
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
        },
    };
})();