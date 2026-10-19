/*圣诞团购*/

(function() {
    christmasbuyPopClass = function() {
        this.e = null;;
        this.isshow = false;
        this.prizeList = [];
        this.prizeListScroller = null;
        this.ismoving = false;
        this.currpoint = 0;
        this.currSelected = 1;
        this.nowchoice = null; //判定只能选中一个框  当前商品id
        this.nowchoiceId = null;
        this.isAddCart = false;
        this.isDeleteCart = false;
        this.isBuyCart = false;
        this.myScroller = null;
        this.cartList = null;
        this.deleteThread = null; //删除线程
        this.autoThread = null;
        this.pageData = null;
        this.returnDom = null; //消费返利DOM
        this.introduceList = null; //玩法介绍集合
        this.rightDom = null; // 购物车Dom
        this.returnScroller = null; // 消费返利界面的滚动控件
        this.progressArray = null; // 消费返利的进度列表
    };
    christmasbuyPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#wordloading').hide();
                return;
            }

            var initRender = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.pageData = res;
                        that.prizeList = res.list;
                        that.initPage(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;
                }

            };

            loadContents({
                'c': 'christmasbuy',
                'm': 'index',
            },
            initRender);
        },

        initPage: function(res) {
            this.e = xGame.loadPopTmpl('christmasbuy');
            this.rightDom = this.e.find('#christmasbuy_right_dom');
            this.returnDom = this.e.find('#christmasbuy_return_dom');

            this.initPrize(this.prizeList);
            this.buildShoppingCart(res);

            xGame.updateCd('christmasbuy', res.cd);
            this.bindAll();
            this.isshow = true;
            this.e.show();
            $('#worldloading').hide();
        },

        //初始化奖品
        initPrize: function(list) {
            var that = this;

            var html = that.getHtml(that.prizeList);
            that.e.find('#christmasbuy_carousel_content').html(html);
            html = null;

            if (that.prizeListScroller == null) {
                that.prizeListScroller = new iScroll('christmasbuypop_carousel', {
                    snap: 'li',
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            that.prizeListScroller.refresh();
        },

        bindAll: function() {
            var that = this;

            // 关闭按钮
            that.e.find('#christmasbuy_hidebtn').unbind('touchend').bind('touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();

            });

            // 玩法介绍的关闭按钮
            that.e.undelegate('#christmasbuypop_info_hide, #christmasbuy_info_dom>.mask', 'touchend').delegate('#christmasbuypop_info_hide, #christmasbuy_info_dom>.mask', 'touchend', function() {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#christmasbuy_info_dom').hide();
            });

            // 玩法介绍按钮
            that.e.undelegate('#christmasbuy_infobtn', 'touchend').delegate('#christmasbuy_infobtn', 'touchend', function() {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.playIntroduce();
            });

            //绑定滑动门左右按钮
            that.e.find('#christmasbuy_leftbtn').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }
                that.prizeListScroller.scrollToPage('prev', 0);
                that.refreshBtnStatus(that.prizeListScroller.currPageX);
            });

            //绑定滑动门左右按钮
            that.e.find('#christmasbuy_rightbtn').unbind('touchend').bind('touchend', function() {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.prizeListScroller.scrollToPage('next', 0);
                that.refreshBtnStatus(that.prizeListScroller.currPageX);
            });

            //消費返利的关闭按钮
            that.e.undelegate('#christmasbuy_return_hidebtn', 'touchend').delegate('#christmasbuy_return_hidebtn', 'touchend', function() {
                
                if (xGame.checkLock(true)) {
                    return false;
                }
                
                that.e.find('#christmasbuy_return_dom').hide();
            });

            // 消费返利按钮
            that.e.undelegate('#christmasbuy_return_btn', 'touchend').delegate('#christmasbuy_return_btn', 'touchend', function() {
                
                if (xGame.checkLock()) {
                    return false;
                }
                
                that.e.find('#christmasbuy_return_dom').show();
                that.costReturn();
            });

            /* 加入购物车*/
            that.e.undelegate('#christmasbuy_shoppingcart_btn.pop_btn_middle', 'touchend').delegate('#christmasbuy_shoppingcart_btn.pop_btn_middle', 'touchend', function() {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.addShoppingCart(parseInt(that.nowchoiceId));
            });

            //删除购物车
            that.e.undelegate('#christmasbuy_shoppingBox_btn', 'touchend').delegate('#christmasbuy_shoppingBox_btn', 'touchend', function() {
                
                if (xGame.checkLock()) {
                    return false;
                }
                
                that.deleteChoice = $(this).parent().attr("bid");
                that.deleteShoppingCart(parseInt(that.deleteChoice));
            });

            //委托点击prizeBox
            that.e.find('#christmasbuy_carousel_content').undelegate('.prizeBox', 'click').delegate('.prizeBox', 'click', function() {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.e.find('#christmasbuy_carousel_content .prizeBox').removeClass('prizeBox_s');
                that.nowchoice = $(this).attr("id");
                that.nowchoiceId = $(this).attr("bid");
                $(this).addClass('prizeBox_s');

                if ($(this).find('.buyTime').text() == 0) {
                    that.e.find('#christmasbuy_shoppingcart_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                } else {
                    that.e.find('#christmasbuy_shoppingcart_btn').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                }

            });

            //购买购物车商品
            that.e.undelegate('#christmas_buybtn.pop_btn_middle', 'touchend').delegate('#christmas_buybtn.pop_btn_middle', 'touchend', function() {
                
                if (xGame.checkLock(true)) {
                    return false;
                }
                
                var cost_rt = that.rightDom.find('.cost_rt').text();
                
                xGame.confirm.show('', lang('christmasbuypop_buy_confirm', [cost_rt]), 2, function() {
                    xGame.confirm.hide();
                    that.buyGood();
                }, function() {
                    xGame.confirm.hide();
                });
            });

            //领取成就
            that.returnDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click', function() {
                
                if (xGame.checkLock()) {
                    return false;
                }
                
                var id = $(this).parent().attr("bid");
                $(this).replaceWith('<span class="haved"></span>');
                that.recieveReward(id);
            });

            //商品scroll滑动事件处理
            that.e.undelegate('.christmasbuy_prizeBox', 'touchstart').delegate('.christmasbuy_prizeBox', 'touchstart', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.touches.length == 1) {
                    that.ismoving = false;
                    var touch = e.touches[0];
                    that.currpoint = touch.pageX;
                }

                e.preventDefault();
            });

            that.e.undelegate('.christmasbuy_prizeBox', 'touchmove').delegate('.christmasbuy_prizeBox', 'touchmove', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                if (e.touches.length == 1) {
                    that.ismoving = false;
                    var touch = e.touches[0];
                    var offset = touch.pageX - that.currpoint;
                    if (offset > 10) { //向右划
                        var currindex = that.prizeListScroller.currPageX - 1;
                        if (currindex < 0 || that.ismoving) {
                            return false;
                        }
                        that.ismoving = true;
                        that.refreshBtnStatus(currindex);
                    } else if (offset < -10) { //向左划
                        var currindex = that.prizeListScroller.currPageX + 1;
                        if (currindex > 4 || that.ismoving) {
                            return false;
                        }

                        that.ismoving = true;
                        that.refreshBtnStatus(currindex);
                    }
                }
                e.preventDefault();


            });

            that.e.undelegate('.christmasbuy_prizeBox', 'touchend').delegate('.christmasbuy_prizeBox', 'touchend', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                that.ismoving = false;
                that.refreshBtnStatus(that.prizeListScroller.currPageX);
                e.preventDefault();
            });

            // 查看坐骑和将领的信息
            that.e.find('.prizeBox').delegate('.img', 'click', function(e) {

                if (xGame.checkLock(true)) {
                    return false;
                }

                var $this = $(this);
                var bid = $this.parent().attr('bid');

                if ($this.hasClass('horse')) {
                    miscpop.showMarchInfo(that.pageData.list[bid - 1].info);
                } else if ($this.hasClass('equip')) {
                    miscpop.showItemInfo(that.pageData.list[bid - 1].info);
                } else if ($this.hasClass('material')) {
                    miscpop.showMaterialItemInfo(that.pageData.list[bid - 1].info);
                }

                e.preventDefault();
            });

        },

        //刷新scroll左右滑动按钮
        refreshBtnStatus: function(index) {
            var len = Math.floor(this.prizeList.length / 6);

            if (index > 0 && index < len) {
                this.e.find('#christmasbuy_rightbtn').show();
                this.e.find('#christmasbuy_leftbtn').show();
            } else if (index <= 0) {
                this.e.find('#christmasbuy_rightbtn').show();
                this.e.find('#christmasbuy_leftbtn').hide();
            } else if (index == len) {
                this.e.find('#christmasbuy_rightbtn').hide();
                this.e.find('#christmasbuy_leftbtn').show();
            }
        },

        //购买购物车商品
        buyGood: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            this.isBuyCart = true;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:

                        player.updateinfo({
                            'gold': res.gold,
                            'silver': res.silver,
                        });
                        
                        xGame.toast.show(lang('buy_success'));
                        that.buildShoppingCart(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'christmasbuy',
                'm': 'buy_good',
            }, Func);
        },

        //删除购物车
        deleteShoppingCart: function(id) {
            var that = this;
            that.isDeleteCart = true;

            var Func = function(res) {
                that.cartList = res.car_info;

                switch (parseInt(res.status)) {
                    case 1:
                        that.e.find('#prizeBox' + id + '').find('.buyTime').html(res.list[id - 1].num);

                        if (id == that.nowchoiceId && res.list[id - 1].num > 0) {
                            that.e.find('#christmasbuy_shoppingcart_btn').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
                        }

                        that.buildShoppingCart(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'christmasbuy',
                'm': 'del_car_good',
                'id': id
            },
            Func);

            Func = null;
        },

        //加入购物车
        addShoppingCart: function(id) {
            var that = this;

            that.isAddCart = true;

            var Func = function(res) {

                that.cartList = res.car_info;

                switch (parseInt(res.status)) {
                    case 1:
                        that.e.find('#' + that.nowchoice + '').find('.buyTime').html(res.list[id - 1].num);

                        if (res.list[id - 1].num == 0) {
                            that.e.find('#christmasbuy_shoppingcart_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                        }

                        that.buildShoppingCart(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                    'c': 'christmasbuy',
                    'm': 'add_good',
                    'id': id
                },
                Func);

            Func = null;
        },

        buildShoppingCart: function(res) {
            var that = this;
            var html = '';
            var carinfo = res.carinfo;
            var shoppingList = res.list;
            var car_array = res.car_array;
            var len = shoppingList.length;
            var carinfoLen = 0;

            if (this.isAddCart || this.isDeleteCart || this.isBuyCart) {
                carinfo = res.car_info;
            } else {
                carinfo = res.carinfo;
            }

            if (carinfo != null) {
                for (i in carinfo) {
                    var id = i;
                    carinfoLen++;
                    for (var j = 0; j < len; j++) {
                        if (id == shoppingList[j].id) {
                            html += '<li bid=' + shoppingList[j].id + ' class="shoppingBox type' + shoppingList[j].type + '">';
                            if (parseInt(shoppingList[j].type) == 14) { //打造材料
                                html += '<span class="img" style="background-image:url(' + xGame.forgeMaterial(shoppingList[j].info.id) + ');"></span>';
                            } else if (parseInt(shoppingList[j].type) == 15) { //装备
                                html += '<span class="img" style="background-image:url(' + xGame.itemurl(shoppingList[j].info.id) + ');"></span>';
                            } else if (parseInt(shoppingList[j].type) == 16) { //坐骑
                                html += '<span class="img" style="background-position:' + helper.getHorsePostion('small', shoppingList[j].info.id) + ';"></span>';
                            } else {
                                html += '<span class="img"></span>';
                            }

                            html += '<em class="re_value1">x' + (helper.convertWan(shoppingList[j].gnum * carinfo[i])) + '</em>'; //商品数量

                            html += '<em class="price">' + (shoppingList[j].price * carinfo[i]) + '</em>';
                            html += '<button class="shoppingBox_btn" id="christmasbuy_shoppingBox_btn"></button>';
                            html += '</li>';
                        }

                    }

                }
            }

            this.e.find('#christmasbuy_list').html(html);
            this.rightDom.find('.cost_now').html(car_array.price);
            this.rightDom.find('.cost').find('.cost_discount').html(car_array.discount);
            this.rightDom.find('.cost_rt').html(car_array.gold);

            if (car_array.discount > 0 && car_array.discount < 1) {
                this.rightDom.find('.cost_discount').html((car_array.discount * 10) + lang('christmasbuypop_discount'));
            } else {
                this.rightDom.find('.cost_discount').html(lang('no'));
            }

            if (carinfoLen == 0) {
                this.rightDom.find('#christmas_buybtn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
            } else {
                this.rightDom.find('#christmas_buybtn').removeClass('pop_btn_middle_disable').addClass('pop_btn_middle');
            }

            if (this.myScroller == null) {
                this.myScroller = new iScroll('christmasbuy_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            this.myScroller.refresh();
        },

        //消費返利
        costReturn: function() {
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.showCostReturn(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;

                }

                xGame.unlock();
            };

            loadContents({
                'c': 'christmasbuy',
                'm': 'rebate_reward_index',
            },
            Func);
        },

        //领取成就
        recieveReward: function(id) {
            var that = this;
            var html = '';
            var Func = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        html += '<div class="recieve_box">';

                        for (var j = 0; j < 3; j++) {
                            if (parseInt(res.reward[j].type) == 14) { //打造材料
                                html += '<span class="reward_down pos' + (j + 1) + '">';
                                html += '<span class="img image' + res.reward[j].type + '" style="background-image:url(' + xGame.forgeMaterial(res.reward[j].info.id) + ');"></span>' + res.reward[j].info['name'] + '</span>';
                            } else if (parseInt(res.reward[j].type) == 15) { //装备
                                html += '<span class="reward_down pos' + (j + 1) + '">';
                                html += '<span class="img image' + res.reward[j].type + '" style="background-image:url(' + xGame.itemurl(res.reward[j].info.id) + ');"></span>' + res.reward[j].info['name'] + '</span>';
                            } else if (parseInt(res.reward[j].type) == 16) { //坐骑
                                html += '<span class="reward_down pos' + (j + 1) + '">';
                                html += '<span class="img image' + res.reward[j].type + '" style="background-position:' + helper.getHorsePostion('small', res.reward[j].info.id) + ';"></span>' + res.reward[j].info['name'] + '</span>';
                            } else {
                                html += '<span class="reward_down pos' + (j + 1) + '">';
                                html += '<span class="img image' + res.reward[j].type + '"></span>x' + helper.convertWan(res.reward[j].num) + '</span>';
                            }
                        }

                        html += '</div>';
                        that.e.find('#christmasbuy_return_dom').append(html);

                        player.updateinfo({
                            'gold': res.gold,
                            'silver': res.silver
                        });

                        that.deleteThread = setTimeout(function() {
                            if (christmasbuypop.e) {
                                christmasbuypop.e.find('.recieve_box').remove();
                            }
                            xGame.unlock();
                        }, 3000);

                        html = null;
                        break;
                    default:
                        xGame.unlock();
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;
                }
            };

            loadContents({
                'c': 'christmasbuy',
                'm': 'rebate_reward',
                'id': id,
            },
            Func);
        },

        //显示元宝花费进度
        showPrpgress: function(allgold) {
            var that = this;
            var html = '';
            var percent = 0;
            var item = null;
            var previousNum = 0;

            for (var i = 0, len = that.progressArray.length; i < len; i++) {
                item = that.progressArray[i];
                html += '<li class ="gold_num">' + helper.convertWan(item) + '</li>';

                if (allgold > previousNum && allgold < item) {
                    percent = i;
                    percent += (allgold - previousNum) / (item - previousNum);
                    percent = (percent * 100 / len).toFixed(2);
                }

                if (i == len - 1 && allgold > item) {
                    percent = 100;
                }

                previousNum = item;
            }

            that.e.find('#christmasbuy_return_progress_phase').html(html);

            if (percent > 100) {
                percent = 100;
            } else if (percent < 0) {
                percent = 0;
            }

            that.e.find('#christmasbuy_return_now_progress').width(percent + '%');

            if (percent > 0 && percent < 100) {
                that.e.find('#christmasbuy_return_now_progress').html('<span class="progress_light"></span>');
            }
        },

        //构建消费返利
        showCostReturn: function(res) {
            var that = this;
            var html = '';
            that.progressArray = [];

            for (var i = 0, len = res.list.length; i < len; i++) {
                var list = res.list[i];
                that.progressArray.push(parseInt(list.limit));
                html += '<li bid=' + list.id + ' class="return_list">';
                html += '<span class="have_costed">' + lang('christmasbuypop_cost') + '<em class="return_cost_now">' + helper.convertWan(list.limit) + '</em>' + lang('gold') + '</span>';
                html += '<div class="return_imgBox">';

                for (var j = 0, len1 = list.reward_info.length; j < len1; j++) {
                    var rewardlist = list.reward_info[j];

                    if (parseInt(rewardlist.type) == 14) { //打造材料
                        html += '<span class="return_img return_type' + rewardlist.type + '" style="background-image:url(' + xGame.forgeMaterial(rewardlist.info['id']) + ');color: ' + helper.getItemColor(rewardlist.info['quality']) + ';">' + rewardlist.info['name'] + '</span>';
                    } else if (parseInt(rewardlist.type) == 15) { //装备
                        html += '<span class="return_img material return_type' + rewardlist.type + '" style="background-image:url(' + xGame.itemurl(rewardlist.info['id']) + ');color: ' + helper.getItemColor(rewardlist.info['quality']) + '">' + rewardlist.info['name'] + '</span>';
                    } else if (parseInt(rewardlist.type) == 16) { //坐骑
                        html += '<span class="return_img horse style="color: ' + helper.getItemColor(rewardlist.info['quality']) + '"><span class="horse_img return_type' + rewardlist.type + '" style="background-position:' + helper.getHorsePostion('small', rewardlist.info['id']) + ';"></span>' + rewardlist.info['name'] + '</span>';
                    } else {
                        html += '<span class="return_img return_type' + rewardlist.type + '">' + helper.convertWan(rewardlist.num) + '</span>';
                    }

                }

                html += '</div>';

                if (list.button_status == 1) {
                    html += '<button class="btn pop_btn_middle recieve_btn">' + lang('receive') + '</button>';
                } else if (list.button_status == 2) {
                    html += '<span class="haved"></span>';
                } else {
                    html += '<button class="btn pop_btn_middle_disable recieve_btn">' + lang('not_reached') + '</button>';
                }

                html += '</li>';
            }

            that.e.find('#christmasbuy_scroller_content').html(html);

            if (res.allgold == null) {
                that.e.find('#christmasbuy_return_complete_num').html(0);
            } else {
                that.e.find('#christmasbuy_return_complete_num').html(helper.convertWan(res.allgold));
            }

            if (that.returnScroller == null) {
                that.returnScroller = new iScroll('christmasbuy_return_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            that.returnScroller.refresh();
            that.returnScroller.scrollTo(0, 0);

            that.showPrpgress(parseInt(res.allgold));
        },

        //玩法介绍
        playIntroduce: function() {
            var that = this;

            var Func = function(res) {
                that.introduceList = res.info;
                switch (parseInt(res.status)) {
                    case 1:
                        that.showIntroduce(res.info);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#wordloading').hide();
                        break;
                }

                xGame.unlock();
            };

            if (that.introduceList == null) {

                if (xGame.checkLock()) {
                    return false;
                }

                loadContents({
                    'c': 'christmasbuy',
                    'm': 'play_introduce',
                },
                Func);
            } else {
                that.e.find('#christmasbuy_info_dom').show();
            }
        },

        //显示玩法说明中的价格信息
        showIntroduce: function(info) {
            var that = this;
            var html = '';

            for (var i = 0, len = info.length; i < len; i++) {
                html += '<li class="message_li"><span class="price">' + info[i].price + '</span>';
                html += '<span class="discount">' + info[i].discount + '</span></li>';
            }
            
            that.e.find('#christmasbuy_info_dom').find('.bg').find('.message_ul').html(html);
            that.e.find('#christmasbuy_info_dom').show();
        },

        getHtml: function(list) {
            this.nowchoice = 'prizeBox1';
            this.nowchoiceId = 1;
            var html = '<li class="christmasbuy_prizeBox">';
            var m = 0;
            var n = 0;

            for (var i = 0, len = list.length; i < len; i++) {

                var tempList = list[i];

                if (m > 0 && m % 6 == 0) {
                    html += '</li><li class="christmasbuy_prizeBox">';
                }

                html += '<div bid=' + tempList.id + ' class="prizeBox type' + tempList.type + ' ' + (i == 0 ? 'prizeBox_s' : '') + '" id="prizeBox' + tempList.id + '">';

                if (parseInt(tempList.type) == 14) { //打造材料
                    html += '<span class="img material" style="background-image:url(' + xGame.forgeMaterial(tempList.info.id) + ');"></span>';
                } else if (parseInt(tempList.type) == 15) { //装备
                    html += '<span class="img equip" style="background-image:url(' + xGame.itemurl(tempList.info.id) + ');"></span>';
                } else if (parseInt(tempList.type) == 16) { //坐骑
                    html += '<span class="img horse" style="background-position:' + helper.getHorsePostion('small', tempList.info.id) + ';"></span>';
                } else {
                    html += '<span class="img"></span>';
                }

                html += '<em class="re_value1">x' + tempList.gnum + '</em>'; //商品数量
                html += '<em class="price">' + tempList.price + '</em>';
                html += '<span class="buyNum">' + lang('christmasbuypop_leaveBuy') + '</span>';
                html += '<em class="buyTime">' + tempList.num + '</em>';
                html += '</div>';
                m++;

                if (i == 0 && tempList.num == 0) {
                    this.e.find('#christmasbuy_shoppingcart_btn').removeClass('pop_btn_middle').addClass('pop_btn_middle_disable');
                }
            }

            html += '</li>';
            this.e.find('#christmasbuypop_carousel ul').html(html);
            this.recoverScroller = xGame.carousel('christmasbuypop_carousel', 500, Math.ceil(m / 6));
        },

        unbindAll: function() {
            this.e.find('#christmasbuy_hidebtn').unbind('touchend');
            this.e.undelegate('#christmasbuypop_info_hide, #christmasbuy_info_dom>.mask', 'touchend');
            this.e.undelegate('#christmasbuy_infobtn', 'touchend');
            this.e.find('#christmasbuy_leftbtn').unbind('touchend');
            this.e.find('#christmasbuy_rightbtn').unbind('touchend');
            this.e.undelegate('#christmasbuy_return_hidebtn', 'touchend');
            this.e.undelegate('#christmasbuy_return_btn', 'touchend');
            this.e.undelegate('#christmasbuy_shoppingcart_btn.pop_btn_middle', 'touchend');
            this.e.undelegate('#christmasbuy_shoppingBox_btn', 'touchend');
            this.e.find('#christmasbuy_carousel_content').undelegate('.prizeBox', 'click');
            this.e.undelegate('#christmas_buybtn.pop_btn_middle', 'touchend');
            this.returnDom.undelegate('.pop_btn_middle', 'click');
            this.e.undelegate('.christmasbuy_prizeBox', 'touchstart');
            this.e.undelegate('.christmasbuy_prizeBox', 'touchmove');
            this.e.undelegate('.christmasbuy_prizeBox', 'touchend');
            this.e.find('.prizeBox').undelegate('.img', 'click');
        },

        hide: function() {
            this.unbindAll();
            xGame.unlock();
            xGame.updateCd('christmasbuy', 0);
            this.isshow = false;
            this.prizeList = [];
            this.ismoving = false;
            this.currpoint = 0;
            this.currSelected = 1;
            this.nowchoice = null; //判定只能选中一个框  当前商品id
            this.nowchoiceId = null;
            this.isAddCart = false;
            this.isDeleteCart = false;
            this.isBuyCart = false;
            this.cartList = null;
            this.pageData = null;
            this.returnDom = null; //消费返利DOM
            this.introduceList = null; //玩法介绍集合
            this.rightDom = null; // 购物车Dom
            this.progressArray = null; // 消费返利的进度列表

            if (this.deleteThread) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }

            if (this.autoThread) {
                clearTimeout(this.autoThread);
                this.autoThread = null;
            }

            if (this.myScroller) {
                this.myScroller.destroy();
                this.myScroller = null;
            }

            if (this.returnScroller) {
                this.returnScroller.destroy();
                this.returnScroller = null;
            }

            if (this.prizeListScroller) {
                this.prizeListScroller.destroy();
                this.prizeListScroller = null;
            }

            this.e.remove();
            this.e = null;

            if (christmasbuypop) {
                christmasbuypop = null;
            }

        },

    };

})();