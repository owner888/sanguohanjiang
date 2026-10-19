/* 切石 */
(function() {
    slyzPopClass = function() {
        this.e;
        this.isshow = false;
        this.dom;
        this.infoBox;
        this.site; //当前选中位置
        this.isCuting; //正在切石中
        this.Value; //当前玉石数量
        this.freeData;//免费数据
        this.isTimeout = [];
    };
    slyzPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                if (res.status == 1) {
                    that.initFirst(res);
                } else if (res.status == -1) {
                    xGame.toast.show(lang('lv_can_not_open_func'));
                    $('#worldloading').hide();
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                }
            };
            loadContents({
                'c': 'gambling',
                'm': 'index'
            },
            initRender, true);
            initRender = null;

        },

        initFirst: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('slyz');
            that.e.find('#hidebtn').unbind('touchend').one('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                slyzpop.hide();
                e.preventDefault();
            });

            that.dom = that.e.find('#slyz_dom').find('ul');
            that.infoBox = that.e.find('#slyz_infobox');
            if (res.jade == undefined) {
                that.showNum(player.info.jade);
            } else {
                that.showNum(res.jade);
            }
            that.isCuting = false;
            that.freeData = {};
            var html = '';

            for (var i = 1; i < 4; i++) {
                if (res.list[i].num) {
                    that.freeData[i] = parseInt(res.list[i].num);
                } else {
                    that.freeData[i] = 0;
                }
                html += '<li id="stone' + i + '">';
                html += '<div class="stone_bg">';
                if (res.list[i].stoneid != 0) {
                    html += '<div class="stone_t stone_t' + i + '0"></div>';
                    that.site = i;
                    that.fillInfoBox(res.list[i].value, ('stone_reward stone_reward' + res.list[i].stoneid), res.list[i].stoneid, res.list[i].name);
                    that.infoBox.css('display', 'block');
                    that.infoBox.find('#stone_div').css('color', helper.getItemColor(res.list[i].level));
                } else {
                    html += '<div class="stone_t stone_t' + i + res.list[i].cut + '"></div>';
                }
                html += '<div class="stone_lizi"></div></div>';
                html += '<div class="desc">' + res.list[i].description + '</div>';
                if (appLanguage == 'th') {
                    html += '<div class="trait"><span>' + res.list[i].trait + '</span></div>';
                } else {
                    html += '<div class="trait"><em>' + lang('feature') + '</em><span>' + res.list[i].trait + '</span></div>';
                }
                if (res.list[i].price != 0 && res.list[i].num == 0) {
                    if (res.list[i].buytype == 1) {
                        html += '<div class="price">' + res.list[i].price + lang('gold') + '</div>';
                    } else {
                        html += '<div class="price silver">' + res.list[i].price + lang('silver') + '</div>';
                    }
                    html += '<button class="pop_btn_middle btn stone_buy" site="' + i + '">' + lang('buy') + '</button>';
                } else {
                    html += '<div class="price no_data">&nbsp;</div>';
                    if (that.freeData[i] > 0) {
                        html += '<button class="pop_btn_middle btn stone_info" site="' + i + '">' + lang('slyzpop_cute_left', [that.freeData[i]]) + '</button>';
                    } else {
                        html += '<button class="pop_btn_middle btn stone_info" site="' + i + '">' + lang('go_to_cut_stone') + '</button>';
                    }
                }
                html += '</li>';
            }

            that.dom.html(html);
            html = null;
            that.bindAll();
            that.e.show();
            $('#worldloading').hide();
        },

        showNum: function(num) {
            this.e.find('#sl_now_jade').html('<span>' + num + lang('jade') + '</span>');
        },

        showValue: function(num) {
            this.infoBox.find('#stone_price_g').find('em').html(num + lang('jade'));
            this.Value = parseInt(num);
        },

        /*有动画的*/
        showValue2: function(num) {
            var that = this;
            if (that.Value != parseInt(num)) {
                xGame.doAnimate2(that.infoBox.find('#stone_price_g').find('em'), 'changcolor', 100, 7,
                function() {
                    that.infoBox.find('#stone_price_g').find('em').html(num + lang('jade'));
                });
                that.Value = parseInt(num);
            }
        },

        bindAll: function() {
            var that = this;
            that.dom.undelegate('.stone_info', 'touchend').delegate('.stone_info', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.infoAndBuy(1, $(this));
            });

            that.dom.undelegate('.stone_buy', 'touchend').delegate('.stone_buy', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.infoAndBuy(2, $(this));
            });

            that.infoBox.undelegate('#hidebtn2', 'touchend').delegate('#hidebtn2', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.isCuting) { //不设置属性
                    return false;
                }
                if (that.infoBox.find('#stone_div').hasClass('stone_reward')) {
                    xGame.toast.show(lang('please_sell_gem_first'));
                    return false;
                }
                that.infoBox.css('display', 'none');
            });

            that.infoBox.undelegate('#stone_sell', 'touchend').delegate('#stone_sell', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.isCuting) { //不设置属性
                    return false;
                }
                that.sellFunc();
            });

            that.infoBox.undelegate('#stone_cut', 'touchend').delegate('#stone_cut', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.isCuting) { //不设置属性
                    return false;
                }
                that.isCuting = true;
                that.cutFunc();
            });

            that.infoBox.undelegate('#stone_rebuy', 'touchend').delegate('#stone_rebuy', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.isCuting) { //不设置属性
                    return false;
                }
                that.infoAndBuy(3, that.dom.find('#stone' + that.site).find('.stone_buy'));
            });
            
            that.infoBox.undelegate('#stone_tips', 'webkitAnimationEnd').delegate('#stone_tips', 'webkitAnimationEnd',function() {
                $(this).removeClass();
            });
        },

        /*type == 1,前往切石，type == 2,买,type == 3,重新买*/
        infoAndBuy: function(type, obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('id_error'));
                    break;
                case - 2 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('gold_not_enough'));
                    break;
                case 3:
                    xGame.toast.show(lang('silver_not_enough'));
                    break;
                case 1:
                    that.site = obj.attr('site'); //只有当请求成功的时候才保存site
                    if (type != 1) {
                        player.updateinfo({
                            "gold": parseInt(res.gold),
                            "silver": parseInt(res.silver)
                        });
                        obj.attr('class', 'pop_btn_middle btn stone_info').html(lang('go_to_cut_stone'));
                        obj.parent().find('.price').addClass('no_data').html('&nbsp;');
                    }
                    var classname = '';
                    if (res.info.stoneid != 0) {
                        classname = 'stone_reward stone_reward' + res.info.stoneid;
                    } else {
                        classname = 'stone_ts stone_t' + obj.attr('site') + res.info.cut;
                    }
                    if (res.stone1_times) {
                        that.freeData[1] = parseInt(res.stone1_times);
                        that.freeData[2] = parseInt(res.stone2_times);
                        that.freeData[3] = parseInt(res.stone3_times);
                    }
                    
                    that.fillInfoBox(res.info.value, classname, res.info.stoneid);
                    classname = null;
                    that.infoBox.css('display', 'block');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();

            };
            loadContents({
                'c':'gambling',
                'm': 'buy',
                'id': obj.attr('site')
            },
            Func);
            Func = null;
        },

        cutFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.num) {
                    res.num = parseInt(res.num);
                    that.freeData[that.site] = res.num;
                } else {
                    res.num = 0;
                    that.freeData[that.site] = 0;
                }
                if (res.status <= 0) {
                    that.isCuting = false;
                } else {
                    
                    player.updateinfo({
                        "gold": parseInt(res.gold)
                    });
                    
                }
                
                switch (parseInt(res.status)) {
                    case - 1 : xGame.toast.show(lang('id_error'));
                        break;
                    case - 2 : xGame.toast.show(lang('have_no_stone'));
                        break;
                    case - 3 : xGame.toast.show(lang('gem_can_not_cut'));
                        break;
                    case - 4 : xGame.toast.show(lang('lv_not_enough'));
                        break;
                    case 1:
                        /*切出宝石*/
                        that.cutAnimate('cut',
                        function() {
                            that.infoBox.find('#stone_tips').attr('class', 'success');
                            that.infoBox.find('#stone_div').removeClass('stone_show').attr('class', 'stone_show stone_reward stone_reward' + res.info.stoneid).html(res.info.name).css('color', helper.getItemColor(res.info.level));;
                            that.showValue2(res.info['value']);
                            that.infoBox.find('#stone_btns').attr('class', 'one');
                            that.isCuting = false;
                            if (res.num > 0) {
                                that.infoBox.find('#stone_cut').html(lang('slyzpop_cute_left', [res.num]));
                                that.dom.find('#stone' + that.site).find('button').html(lang('slyzpop_cute_left', [res.num]));
                            }
                        });
                        break;
                    case 2:
                        /*切碎*/
                        that.cutAnimate('broken',
                        function() {
                            that.infoBox.find('#stone_tips').attr('class', 'fail');
                            //解锁
                            that.isCuting = false;
                            //设置为空
                            that.infoBox.find('#stone_btns').attr('class', 'zero');
                            that.showValue2('0');
                            that.infoBox.find('#stone_div').removeClass();
                            that.dom.find('#stone' + that.site).find('.stone_t').attr('class', 'stone_t stone_t' + that.site + '0').html('');
                            
                            //如果有次数，延时显示，没有显示购买
                            if (that.freeData[that.site] > 0) {
                                that.isTimeout.push(setTimeout(function() {
                                    if(that.infoBox) {
                                        that.showValue(res.info.value);
                                        that.infoBox.find('#stone_div').attr('class', 'stone_ts stone_t' + res.info.stoneid + res.info.cut);
                                        that.infoBox.find('#stone_btns').attr('class', 'two');
                                        that.infoBox.find('#stone_cut').html(lang('slyzpop_cute_left', [that.freeData[that.site]]));
                                        that.dom.find('#stone' + that.site).find('button').html(lang('slyzpop_cute_left', [res.num]));
                                    }
                                }, 1000));
                            } else {
                                that.dom.find('#stone' + that.site).find('button').attr('class', 'pop_btn_middle btn stone_buy').html(lang('buy'));
                                if (res.buytype == 1) {
                                    that.dom.find('#stone' + that.site).find('.price').html(res.price + lang('gold')).removeClass('no_data').removeClass('silver');
                                    that.infoBox.find('#stone_rebuy_price').html(res.price + lang('gold')).removeClass('silver');
                                } else {
                                    that.dom.find('#stone' + that.site).find('.price').html(res.price + lang('silver')).removeClass('no_data').addClass('silver');
                                    that.infoBox.find('#stone_rebuy_price').html(res.price + lang('silver')).addClass('silver');
                                }
                                that.infoBox.find('#stone_rebuy_dom').css('display', 'block');
                            }
                        });
                        break;
                    case 3:
                        /*切小*/
                        that.cutAnimate('cut',
                        function() {
                            that.showValue2(res.info['value']);
                            that.infoBox.find('#stone_div').attr('class', 'stone_ts stone_t' + that.site + res.info.cut);
                            that.dom.find('#stone' + that.site).find('.stone_t').attr('class', 'stone_t stone_t' + that.site + res.info.cut);
                            that.isCuting = false;
                            if (res.num > 0) {
                                that.infoBox.find('#stone_cut').html(lang('slyzpop_cute_left', [res.num]));
                                that.dom.find('#stone' + that.site).find('button').html(lang('slyzpop_cute_left', [res.num]));
                            }
                        });
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
                xGame.unlock();

            };
            loadContents({
                'c': 'gambling',
                'm': 'cut',
                'id': that.site
            },
            Func);
            Func = null;
        },

        /*type只能是broken或者cut*/
        cutAnimate: function(type, callback) {
            this.infoBox.find('#stone_div').addClass('stone_shake');
            /*整套动作刚好半秒，stone_shake延时加上动画也是刚好半秒*/
            var movieclip = new movieClipClass({
                "img": staticUrl + 'img/pop/slyzpop_stone_' + type + '.png',
                "width": 250,
                "height": 300,
                "x": 160,
                "y": 60,
                "loop": false,
                'zindex': '1',
                "frames": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [250, 0],
                    interval: 50
                },
                {
                    rect: [500, 0],
                    interval: 50
                },
                {
                    rect: [750, 0],
                    interval: 50
                },
                {
                    rect: [1000, 0],
                    interval: 50
                },
                {
                    rect: [0, 300],
                    interval: 50
                },
                {
                    rect: [250, 300],
                    interval: 50
                },
                {
                    rect: [500, 300],
                    interval: 50
                },
                {
                    rect: [750, 300],
                    interval: 50
                },
                {
                    rect: [1000, 300],
                    interval: 50
                },
                ],
                'callback': callback,
                'endDestory': true
            });
            movieclip.appendTo(this.infoBox.find('.infobox'));
            movieclip.play();
            movieclip = null;
        },

        sellFunc: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('id_error'));
                    break;
                case - 2 : xGame.toast.show(lang('have_no_jade'));
                    break;
                case - 3 : xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    player.updateinfo({
                        "jade":parseInt(res.jade)
                    });
                    that.showNum(res.jade);
                    xGame.toast.show(lang('sell_get_jade', [res.value]));
                    if (res.num) {
                        res.num = parseInt(res.num);
                        that.freeData[that.site] = res.num;
                    } else {
                        res.num = 0;
                        that.freeData[that.site] = 0;
                    }
                    
                    /*后修改的体验*/
                    that.infoBox.find('#stone_btns').attr('class', 'zero');
                    that.showValue2('0');
                    that.infoBox.find('#stone_div').removeClass().html('');
                    that.dom.find('#stone' + that.site).find('.stone_t').attr('class', 'stone_t stone_t' + that.site + '0').html('');
                    
                    if (that.freeData[that.site] > 0) {
                        that.isTimeout.push(setTimeout(function() {
                            if (that.infoBox) {
                                that.infoBox.find('#stone_cut').html(lang('slyzpop_cute_left', [that.freeData[that.site]]));
                                that.dom.find('#stone' + that.site).find('button').html(lang('slyzpop_cute_left', [res.num]));
                                that.showValue(res.info.value);
                                that.infoBox.find('#stone_div').attr('class', 'stone_ts stone_t' + res.info.stoneid + res.info.cut).html('');
                                that.infoBox.find('#stone_btns').attr('class', 'two');
                            }
                        }, 1000));
                        
                    } else {
                        that.dom.find('#stone' + that.site).find('button').attr('class', 'pop_btn_middle btn stone_buy').html(lang('buy'));
                        
                        if (res.buytype == 1) {
                            that.dom.find('#stone' + that.site).find('.price').html(res.price + lang('gold')).removeClass('no_data').removeClass('silver');
                            that.infoBox.find('#stone_rebuy_price').html(res.price + lang('gold')).removeClass('silver');
                        } else {
                            that.dom.find('#stone' + that.site).find('.price').html(res.price + lang('silver')).removeClass('no_data').addClass('silver');
                            that.infoBox.find('#stone_rebuy_price').html(res.price + lang('silver')).addClass('silver');
                        }
                        that.infoBox.find('#stone_rebuy_dom').css('display', 'block');
                    }

                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();

            };
            loadContents({
                'c':
                'gambling',
                'm': 'sell',
                'id': that.site
            },
            Func);
            Func = null;
        },

        fillInfoBox: function(stone_value, class_name, stoneid, name) {
            this.showValue(stone_value);
            this.infoBox.find('#stone_div').attr('class', class_name);
            if (name != undefined) {
                this.infoBox.find('#stone_div').html(name);
            } else {
                this.infoBox.find('#stone_div').html('');
            }
            if (stoneid != 0) {
                this.infoBox.find('#stone_btns').attr('class', 'one');
            } else {
                this.infoBox.find('#stone_btns').attr('class', 'two');
                if (this.freeData[this.site] > 0) {
                    this.infoBox.find('#stone_cut').html(lang('slyzpop_cute_left', [this.freeData[this.site]]));
                } else {
                    this.infoBox.find('#stone_cut').html(lang('slyzpop_cute'));
                }
            }
            this.infoBox.find('#stone_tips').removeClass();
            this.infoBox.find('#stone_rebuy_dom').css('display', 'none');
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('#hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;    
            }
            if (this.dom) {
                this.dom.undelegate('.stone_info', 'touchend');
                this.dom.undelegate('.stone_buy', 'touchend');
                this.dom.remove();
                this.dom = null;
            }
            if (this.infoBox) {
                this.infoBox.undelegate('#hidebtn2', 'touchend');
                this.infoBox.undelegate('#stone_sell', 'touchend');
                this.infoBox.undelegate('#stone_cut', 'touchend');
                this.infoBox.undelegate('#stone_rebuy', 'touchend');
                this.infoBox.undelegate('#stone_tips', 'webkitAnimationEnd');
                this.infoBox.remove();
                this.infoBox = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.site = this.isCuting = this.Value = null;
            this.freeData = null;
            this.unbindAll();
            
            for (var i = 0; i < this.isTimeout.length; i++) {
                clearTimeout(this.isTimeout[i]);
            }
            this.isTimeout.length = 0;
        }
    };
})();