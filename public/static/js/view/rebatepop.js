/* 返利 */
(function() {
    rebatePopClass = function() {
        this.e;
        this.isshow = false;
    };
    rebatePopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            if(typeof jBreaking != 'undefined' && jBreaking == true) {
                this.loadAlipay();
            } else if(typeof Platform != 'undefined' && Platform.id == '91') {
                this.load91pay();
            } else if(typeof Platform != 'undefined' && Platform.id == 'pp') {
                this.loadPPpay();
            } else if(typeof Platform != 'undefined' && (Platform.id == 'h5' || Platform.id == 'pc') ) {
                this.loadH5pay();
            } else {
                this.loadIAP();
            }

        },

        buildpage: function() {
            var that = this;

            that.e = xGame.loadPopTmpl('rebate');
            that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                rebatepop.hide();
                e.preventDefault();
            });

            /*显示界面*/
            that.e.show();
            $('#worldloading').hide();
        },

        loadIAP: function() {
            var that = this;

            var initRender = function(data) {
                if (data.status == 1) {

                    that.buildpage();

                    var html = '';
                    for (var i = 0; i < data.list.length; i++) {
                        var nowitem = data.list[i];
                        html += '<li>' + lang('pay_and_get', [nowitem.num, nowitem.presentnum]) + '<button class="pop_btn_small btn" buyid="' + nowitem.buyid + '">' + lang('payment') + '</button></li>';
                    }
                    that.e.find('#rebatepop_list').html(html);
                    that.e.find('#rebatepop_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
                    function() {
                        if (typeof cordova == 'undefined') {
                            xGame.toast.show(lang('please_pay_in_ios'));
                            return false;
                        }
                        if ((typeof jBreaking == 'undefined' || jBreaking == false) && (channelID == 4 || channelID == 5)) {//兼容旧版PP、91，提示到官网支付
                            xGame.confirm.show(lang('webkit_pay'), lang('device_can_no_pay'), 1,
                            function() {
                                xGame.confirm.hide();
                            });
                            return false;
                        } else {//iOS支付
                            var productid = $(this).attr('buyid');
                            if (productid) {
                                miscpop.iosbuy(productid);
                            } else {
                                return false;
                            }
                        }
                    });

                    xGame.updateCd('rebate', data.cd);

                    html = null;

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

        loadAlipay: function() {
            var that = this;

            var initRender = function(data) {
                if (data.status == 1) {

                    that.buildpage();

                    var html = '';
                    for (var i = 0; i < data.list.length; i++) {
                        var nowitem = data.list[i];
                        html += '<li>' + lang('pay_and_get', [nowitem.gold, nowitem.add_gold]) + '<button class="pop_btn_small btn" buyid="' + nowitem.id + '">' + lang('payment') + '</button></li>';
                    }
                    that.e.find('#rebatepop_list').html(html);
                    that.e.find('#rebatepop_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
                    function() {
                        that.hide();
                        miscpop.otherPay();
                        /*
                        if (typeof jBreaking != 'undefined' && jBreaking == true) {
                            miscpop.otherPay();
                            return false;
                        }
                        var productid = $(this).attr('buyid');
                        if (productid) {
                            miscpop.alipayOrder(productid);
                        } else {
                            return false;
                        }
                        */

                    });

                    if(data.cd) {
                        xGame.updateCd('rebate', data.cd);
                    }

                    html = null;

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

        load91pay: function() {
            var that = this;

            var initRender = function(data) {
                if (data.status == 1) {

                    that.buildpage();

                    var html = '';
                    for (var i = 0; i < data.list.length; i++) {
                        var nowitem = data.list[i];
                        html += '<li>' + lang('pay_and_get', [nowitem.gold, nowitem.add_gold]) + '<button class="pop_btn_small btn" buyid="' + nowitem.id + '">' + lang('payment') + '</button></li>';
                    }
                    that.e.find('#rebatepop_list').html(html);
                    that.e.find('#rebatepop_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
                    function() {

                        var productid = $(this).attr('buyid');
                        if (productid) {
                            miscpop.go91Order(productid);
                        } else {
                            return false;
                        }

                    });

                    if(data.cd) {
                        xGame.updateCd('rebate', data.cd);
                    }

                    html = null;

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

        loadPPpay: function() {
            var that = this;

            var initRender = function(data) {
                if (data.status == 1) {

                    that.buildpage();

                    var html = '';
                    for (var i = 0; i < data.list.length; i++) {
                        var nowitem = data.list[i];
                        html += '<li>' + lang('pay_and_get', [nowitem.gold, nowitem.add_gold]) + '<button class="pop_btn_small btn" buyid="' + nowitem.id + '">' + lang('payment') + '</button></li>';
                    }
                    that.e.find('#rebatepop_list').html(html);
                    that.e.find('#rebatepop_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
                    function() {

                        var productid = $(this).attr('buyid');
                        if (productid) {
                            miscpop.goPPOrder(productid);
                        } else {
                            return false;
                        }

                    });

                    if(data.cd) {
                        xGame.updateCd('rebate', data.cd);
                    }

                    html = null;

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

        loadH5pay: function() {
            var that = this;

            var initRender = function(data) {
                if (data.status == 1) {

                    that.buildpage();

                    var html = '';
                    for (var i = 0; i < data.list.length; i++) {
                        var nowitem = data.list[i];
                        html += '<li>' + lang('pay_and_get', [nowitem.gold, nowitem.add_gold]) + '<button class="pop_btn_small btn" buyid="' + nowitem.id + '">' + lang('payment') + '</button></li>';
                    }
                    that.e.find('#rebatepop_list').html(html);
                    that.e.find('#rebatepop_list').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
                    function() {
                        if(Platform.id == 'h5') {
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
                        } else {
                            window.location.href = Platform.payurl;
                            //window.open(Platform.payurl);//open可能会被拦截
                        }
                        return;
                    });

                    if(data.cd) {
                        xGame.updateCd('rebate', data.cd);
                    }

                    html = null;

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

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('#hidebtn').unbind('touchend');
                this.e.find('#rebatepop_list').undelegate('.pop_btn_small', 'touchend');
                this.e.remove();
                this.e = null;
            }
        },
        
        hide: function() {
            xGame.unlock();
            xGame.updateCd('rebate', 0);

            this.isshow = false;
            this.unbindAll();
        },
    };
})();