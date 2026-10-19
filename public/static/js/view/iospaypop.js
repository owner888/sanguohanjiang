/* 支付 */
(function() {
    iospayPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller;
        /*滑动*/
        this.topDom;
        /*头部dom*/
        this.bottomDom;
    };
    iospayPopClass.prototype = {
        init: function() {

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            if (typeof Platform != "undefined" && (Platform.id == "h5" || Platform.id == "pc")) {
                if (xGame.xsGet('tryuid')) {
                    miscpop.bindPopConfirm(function() {
                        window.location.href = Platform.payurl + '&s='+xGame.server_prefix+'&u='+xGame.getUsername();
                    }, true);
                } else if(Platform.id == 'h5') {
                    window.location.href = Platform.payurl + '&s='+xGame.server_prefix+'&u='+xGame.getUsername();
                } else {
                    window.location.href = Platform.payurl;
                    //window.open(Platform.payurl);//open可能会被拦截
                }
                $("#worldloading").hide();
                return;

            }

            if(typeof jBreaking != 'undefined' && jBreaking == true) {
                this.loadAlipay();
            } else if(typeof Platform != 'undefined' && Platform.id == '91') {
                this.load91pay();
            } else if(typeof Platform != 'undefined' && Platform.id == 'pp') {
                this.loadPPpay();
            } else if(typeof Platform != 'undefined' && Platform.id == 'kuaiyong') {
                this.loadKYpay();
            } else if(typeof Platform != 'undefined' && Platform.id == 'dj') {
                this.loadDJpay();
            } else if(typeof Platform != 'undefined' && Platform.id == 'djn') {
                this.loadDJNpay();
            } else {
                this.loadIAP();
            }

        },

        preparePage: function() {
            var that = this;

            if(that.e) {
                return false;
            }
            //进入页面
            that.e = xGame.loadPopTmpl('iospay');

            //关闭按钮
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
            function() {
                if (xGame.checkLock()) {
                    return false;
                }
                setTimeout(function() {
                    iospaypop.hide();
                },
                400);
            });

            that.myScroller = new iScroll('iospaypop_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
        },

        /* 应用内支付 */
        loadIAP: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'ios',
                'm': 'buylist'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        initBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.name + '</p>';
                if (nowitem.type == 1) { //普通价格
                    if(nowitem.nowprice_display) {//直接显示价格
                        html += '<p class="new">' + nowitem.nowprice_display + '</p>';
                    } else {
                        html += '<p class="new">' + lang('price_%s', [nowitem.nowprice]) + '</p>';
                    }
                } else if (nowitem.type == 2) { //打折
                    if(nowitem.nowprice_display) {//直接从服务端返回价格和价格单位
                        html += '<p><em></em>' + nowitem.oldprice_display + '</p>';
                        html += '<p class="new">' + nowitem.nowprice_display + '</p>';
                    } else {
                        html += '<p><em></em>' + lang('price_original_%s', [nowitem.oldprice]) + '</p>';
                        html += '<p class="new">' + lang('price_now_%s', [nowitem.nowprice]) + '</p>';
                    }
                } else if (nowitem.type == 3) { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.present + '</p>';
                    if(nowitem.nowprice_display) {//直接从服务端返回价格和价格单位
                        html += '<p class="new">' + nowitem.nowprice_display + '</p>';
                    } else {
                        html += '<p class="new">' + lang('price_%s', [nowitem.nowprice]) + '</p>';
                    }
                }
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyamount="' + nowitem.nowprice+ '" buyid="' + nowitem.buyid + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (typeof cordova == 'undefined') {
                    xGame.toast.show(lang('please_pay_in_ios'));
                    return false;
                }
                if ((typeof jBreaking == 'undefined' || jBreaking == false) && (channelID == 4 || channelID == 5)) {
                    xGame.confirm.show(lang('webkit_pay'), lang('device_can_no_pay'), 1,
                    function() {
                        xGame.confirm.hide();
                    });
                    return false;
                } else {//ios支付
                    var productid = $(this).attr('buyid');
                    if (productid) {
                        miscpop.iosbuy(productid);
                        /* HasOfferTrack 先保存好最后这次点的是多少价格的，支付成功时再发送给OC */
                        var _amount = $(this).attr('buyamount');
                        if(_amount) {
                            console.log(_amount);
                            xGame.iosLastPay = parseFloat(_amount);
                        }
                    } else {
                        return false;
                    }
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },

        /* 支付宝支付 */
        loadAlipay: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initAlipayBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_product_list'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        initAlipayBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.subject + '</p>';
                if(nowitem.add_gold <= 0) {
                    html += '<p class="new">' + lang('price_%s', [nowitem.amount]) + '</p>';
                } else { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.amount + '</p>';
                    html += '<p class="new">' + lang('price_%s', [nowitem.add_gold]) + '</p>';
                }
                
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyid="' + nowitem.id + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                if (typeof cordova == 'undefined') {
                    xGame.toast.show(lang('please_pay_in_ios'));
                    return false;
                }
                var productid = $(this).attr('buyid');
                if (productid) {
                    miscpop.alipayOrder(productid);
                } else {
                    return false;
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },

        /* 91支付 */
        load91pay: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.init91payBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_product_list'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        init91payBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.subject + '</p>';
                if(nowitem.add_gold <= 0) {
                    html += '<p class="new">' + lang('price_%s', [nowitem.amount]) + '</p>';
                } else { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.amount + '</p>';
                    html += '<p class="new">' + lang('price_%s', [nowitem.add_gold]) + '</p>';
                }
                
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyid="' + nowitem.id + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                var productid = $(this).attr('buyid');
                if (productid) {
                    miscpop.go91Order(productid);
                } else {
                    return false;
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },

        /* PP支付 */
        loadPPpay: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initPPpayBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_product_list'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        initPPpayBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.subject + '</p>';
                if(nowitem.add_gold <= 0) {
                    html += '<p class="new">' + lang('price_%s', [nowitem.amount]) + '</p>';
                } else { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.amount + '</p>';
                    html += '<p class="new">' + lang('price_%s', [nowitem.add_gold]) + '</p>';
                }
                
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyid="' + nowitem.id + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                var productid = $(this).attr('buyid');
                if (productid) {
                    miscpop.goPPOrder(productid);
                } else {
                    return false;
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },

        /* PP支付 */
        loadKYpay: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initKYpayBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_product_list'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        initKYpayBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.subject + '</p>';
                if(nowitem.add_gold <= 0) {
                    html += '<p class="new">' + lang('price_%s', [nowitem.amount]) + '</p>';
                } else { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.amount + '</p>';
                    html += '<p class="new">' + lang('price_%s', [nowitem.add_gold]) + '</p>';
                }
                
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyid="' + nowitem.id + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                var productid = $(this).attr('buyid');
                if (productid) {
                    miscpop.goKYOrder(productid);
                } else {
                    return false;
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },

        /* DJ支付 */
        loadDJpay: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initDJpayBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_product_list'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        initDJpayBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.subject + '</p>';
                if(nowitem.add_gold <= 0) {
                    html += '<p class="new">' + lang('price_%s', [nowitem.amount]) + '</p>';
                } else { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.amount + '</p>';
                    html += '<p class="new">' + lang('price_%s', [nowitem.add_gold]) + '</p>';
                }
                
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyid="' + nowitem.id + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend').delegate('.pop_btn_middle', 'touchend',
            function() {
                var productid = $(this).attr('buyid');
                if (productid) {
                    miscpop.goDJOrder(productid);
                } else {
                    return false;
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },

        /* 91兼容版支付 */
        loadDJNpay: function() {
            var that = this;

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initDJNpayBuylist(res);
                } else {
                    xGame.toast.show(lang('no_pay_info'));
                    $('#worldloading').hide();
                    xGame.unlock();
                }
            };
            loadContents({
                'c': 'pay',
                'm': 'get_product_list'
            },
            initRender, true); //加载数据

        },

        initDJNpayBuylist: function(data) {
            var that = this;

            that.preparePage();

            var html = '';
            for (var i = 0; i < data.list.length; i++) {
                var nowitem = data.list[i];
                html += '<li>';
                html += '<div>';
                html += '<p class="name">' + nowitem.subject + '</p>';
                if(nowitem.add_gold <= 0) {
                    html += '<p class="new">' + lang('price_%s', [nowitem.amount]) + '</p>';
                } else { //赠送
                    html += '<p>' + lang('handsel') + ' ' + nowitem.amount + '</p>';
                    html += '<p class="new">' + lang('price_%s', [nowitem.add_gold]) + '</p>';
                }
                
                html += '</div>';
                if(nowitem.info != "") {
                    html +='<div class="title">' + nowitem.info + '</div>';
                }
                html += '<button class="btn pop_btn_middle" buyid="' + nowitem.id + '">' + lang('payment') + '</button>';
                html += '</li>';
            }
            that.e.find('#iospay_list').append(html);
            that.e.find('#iospay_list').delegate('.pop_btn_middle', 'touchend',
            function() {
                var productid = $(this).attr('buyid');
                if (productid) {
                    miscpop.goDJNOrder(productid);
                } else {
                    return false;
                }
            });

            that.myScroller.refresh();

            that.e.show();
            $('#worldloading').hide();
            xGame.unlock();
        },
	
        unbindAll: function () {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('#iospay_list').undelegate('.pop_btn_middle', 'touchend');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.myScroller) {
                this.myScroller.destroy();
                delete this.myScroller;
            }
            if (this.topDom) {
                this.topDom.remove();
                this.topDom = null;
            }
            if (this.bottomDom) {
                this.bottomDom.remove();
                this.bottomDom = null;
            }
            this.currentVipLevel = null;
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            
        },
    };
})();