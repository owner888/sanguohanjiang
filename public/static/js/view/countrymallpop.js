(function(){
    countrymallPopClass = function () {
        this.e = null;
        this.isshow = false;
        this.goodId = 0; //物品ID
        this.countrymallScroll = null;
    };

    countrymallPopClass.prototype = {
    
        init:function () {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function (res) {
                
                switch (parseInt(res.status)) {
                    case 1:
                        that.initManager(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };

            loadContents({
                'c': 'country_taxes_shop',
                'm': 'index'
            },
            initRender);
            initRender = null;
        },

        initManager:function (res) {
            $('#worldloading').hide();
            this.e = xGame.loadPopTmpl('countrymall');
            this.bindAll();
            this.initGoods(res); //初始化商品列表  
            this.isshow = true;
        },
        
        bindAll: function () {
            that = this;

            //关闭按钮
            this.e.find('.hidebtn').unbind('touchend').bind('touchend',

            function (e) {

                if(xGame.checkLock(true)) {
                    return false;
                }

                e.preventDefault();
                that.hide();        //注意要hide  否则 会有屏幕锁
            });

            //绑定购买按钮
            this.e.find('#countrymall_wrap').undelegate('.resource_buy','touchend').delegate('.resource_buy','touchend',
            function (e) {
                
                if (xGame.checkLock()) {
                    return false;
                }

                that.goodTaxes = $(this).attr('index');
                that.goodId = $(this).attr('key');
                that.buy();

            });

        },

        buy: function () {
            var that = this;

            var initRender = function (res) {
                    switch (parseInt(res.status)) {
                        case 1:
                             
                            // 找到点击的那个按钮然后变灰+修改文字
                            if ( res.reward_info.type != 32 && res.item.num == 0) {
                                that.e.find('#countrymall_wrap .buy_btn').eq(that.goodTaxes).removeClass('resource_buy').addClass('resource_no_open').html(lang('christmasgeneral_no_num'));
                            }
                            if(res.reward_info.type != 32) {
                                that.e.find('#countrymall_scroller .list').eq(that.goodTaxes).find('.buy_times').html(res.item.num);
                            }
                            that.e.find('#countrymall_my_general').html(res.general);
                            that.e.find('#countrymall_my_taxes').html(res.taxes);
                            var html ='';
                            switch (parseInt(res.reward_info.type)) {
                                case 3: // 黄宝石
                                    html = lang('acttreasurepop_msg3', [2, res.reward_info.value, lang('yellow_jade')]);
                                    break;
                                case 4: // 紫宝石
                                    html = lang('acttreasurepop_msg3', [3, res.reward_info.value, lang('purple_jade')]);
                                    break;
                                case 5: // 原魂
                                    html = lang('acttreasurepop_msg4', [res.reward_info.value]);
                                    break;
                                case 6: // 玉石
                                    html = lang('acttreasurepop_msg5', [res.reward_info.value]);
                                    break;
                                case 7: // 声望
                                    html = lang('acttreasurepop_msg6', [res.reward_info.value]);
                                    break;
                                case 9: // 宝石碎片
                                    html = lang('acttreasurepop_msg8',[res.reward_info.value]);
                                    break;
                                case 11: // 蓝宝石
                                    html = lang('acttreasurepop_msg3', [1, res.reward_info.value, lang('blue_jade')]);
                                    break;
                                case 15: // 五彩石
                                    html = lang('acttreasurepop_msg15', [res.reward_info.value]);
                                    break;
                                case 16: // 上古血玉
                                    html = lang('acttreasurepop_msg16', [res.reward_info.value]);
                                    break;
                                case 27: // 玫瑰
                                    html = lang('acttreasurepop_msg27', [res.reward_info.value]);
                                    break;
                                case 28: // 钻石
                                    html = lang('acttreasurepop_msg29', [res.reward_info.value]);
                                    break;
                                case 29: //  
                                    html = lang('lordsoul_msg', [1, res.reward_info.value, lang('lordsoul_name1')]);
                                    break;
                                case 30: //  
                                    html = lang('lordsoul_msg', [2, res.reward_info.value, lang('lordsoul_name2')]);
                                    break;
                                case 31: //  
                                    html = lang('lordsoul_msg', [3, res.reward_info.value, lang('lordsoul_name3')]);
                                    break;
                                case 32:
                                    html = lang('acttreasurepop_msg32',[res.reward_info.value]);
                                default :
                                    break;
                            }
                            xGame.toast.show(html);                               
                            html = null;
                            break;
                        default :
                            xGame.toast.show(res.msg);
                            break;
                        }
                    xGame.unlock();
                    
            };
                
            loadContents({
                'c': 'country_taxes_shop',
                'm': 'buy',
                'id': that.goodId
            }, initRender);
            initRender = null;
            
        },

        //初始化商品列表 包括下边我的贡献我的税金和将军令
        initGoods: function(res) {

            var that = this;
            var html = '';
            var n = 0;                                       //判断是会否需要增加li

            html += '<li class="cs_item">';
            for (var i = 0, len = res.shop_list.list.length; i < len; i++) {
                var resource = res.shop_list.list[i];
                
                if (n > 0 && n % 6 == 0) {
                    html += '</li><li class="cs_item">'
                }

                html += '<div class="list">';
                html += '<div class="resource_background">';
                html += '<span class="type type'+resource.type+'"></span>';
                html += '<span class="resource_num">x'+helper.convertWan(resource.value)+'</span>';
                html += '</div>';
                html += '<span class="resource_price">'+helper.convertWan(resource.taxes)+'</span>';
                
                if (resource.is_open == 1) {
                    if (resource.limit == 0) {
                        
                        //设置一个通用属性buy_btn是因为index方法要数按钮个数（包括不可点击的按钮各种按钮都要设置buy_btn）
                        html += '<button class="resource_buy buy_btn" key = "'+resource.id+'">'+lang('buy')+'</button>';
                       
                    } else if (resource.num > 0) {

                        html += '<span class="buy_time buy_time'+resource.id+'">'+lang('countrymall_times_out_buy',[resource.num])+'</span>';
                        html += '<button class="buy_btn resource_buy" key = "'+resource.id+'" index="'+ i +'">'+lang('buy')+'</button>';

                    } else {
                        
                        html += '<span class="buy_time buy_time'+resource.id+'">'+lang('countrymall_times_out_buy',[resource.num])+'</span>';
                        html += '<button class="buy_btn resource_no_open" key = "'+resource.id+'">'+lang('christmasgeneral_no_num')+'</button>';
                    }

                } else {

                    html += '<span class="no_open">'+lang('countrymall_no_open',[resource.openlevel])+'</span>';
                    html += '<button class="buy_btn resource_no_open" key = "'+resource.id+'">'+lang('countrymall_resource_no_open')+'</button>';            //未开启
                }
               
                html += '</div>';
                n++;
            }

            html += '</li>';

            that.e.find('.carousel_content').html(html);
            that.countrymallScroll = xGame.carousel('countrymall_scroller', 700);
            that.e.find('#countrymall_my_contribution').html(res.member_info.info.contribute);
            that.e.find('#countrymall_my_taxes').html(res.member_info.info.taxes);
            that.e.find('#countrymall_my_general').html(res.member_info.info.general);
            html = null;
        },

        unbindAll: function () {
            this.e.find('.hidebtn').unbind('touchend');
            this.e.find('#countrymall_wrap').undelegate('.resource_buy','touchend');
        },

        hide:function () {
            xGame.unlock();
            this.unbindAll();
            this.isshow = false;
            this.goodId = null;
            if (this.e) {
                this.e.remove();
                this.e = null;
            }
            if (this.countrymallScroll) {
                this.countrymallScroll.destroy();
                delete this.countrymallScroll;
            }
            if (countrymallpop) {
                countrymallpop = null;
            };
        }
    }
})()