/* 绝版神将 */
(function() {
    outprintgodPopClass = function() {
        this.e;
        this.isshow = false;
        this.heroScroller;
        this.herolist = [];
        this.currIndex = 0;
        this.animover = true;
        this.currpoint;
        this.ismoving = false;
    };
    outprintgodPopClass.prototype = {
        init: function() {
            var that = this;

            if (this.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {
                switch (res.status) {
                    case 1:
                        that.initPage(res);
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            loadContents({
                'c': 'general_only',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;
        },

        onLeftBtnClick: function() {
            var prev = next = 0;
            var that = this;

            if (this.herolist.length == 2 && this.currIndex == 1) {
                return;
            }

            if (!this.animover) {
                return;
            }

            this.animover = false;

            setTimeout(function(){
                that.animover = true;
            }, 1000);

            switch(this.currIndex) {
                case 0:
                    prev = 2;
                    next = 1;
                    break;
                case 1:
                    prev = 0;
                    next = 2;
                    break;
                case 2:
                    prev = 1;
                    next = 0;
                    break;
                default:
                    break;
            }

            var curr = this.e.find('.content').find('#img'+this.currIndex);
            xGame.doAnimate(curr, 'hero_RightToLeft1', '0.35s', function() {
                curr.css({'opacity':0.5, 'z-index':1, 'left':'115px', 'top':'130px', '-webkit-transform':'scale(0.7)'});
            });
            var pre = this.e.find('.content').find('#img'+prev);
            xGame.doAnimate(pre, 'hero_LeftToRight3', '0.35s', function() {
                pre.css({'opacity':0.5, 'z-index':1, 'left':'300px', 'top':'130px', '-webkit-transform':'scale(0.7)'});
            });

            var nex = this.e.find('.content').find('#img'+next);
            xGame.doAnimate(nex, 'hero_RightToLeft2', '0.35s', function() {
                nex.css({'opacity':1, 'z-index':10, 'left':'190px', 'top':'95px', '-webkit-transform':'scale(1.2)'});
            });

            this.currIndex = next;
            var herodata = this.herolist[this.currIndex];
            this.e.find('.desc').html('&nbsp;&nbsp;&nbsp;&nbsp;'+herodata.desc);
            this.setBuyBtnStatus(herodata.isget);
            this.e.find('.needgem').find('em').html(herodata.price1);
            this.e.find('.needprestige').find('em').html(herodata.price2);

            this.e.find('.scroller_btn_left').hide();
            this.e.find('.scroller_btn_right').show();

        },

        onRightBtnClick: function() {
            var prev = next = 0;
            var that = this;

            if (this.herolist.length == 2 && this.currIndex == 0) {
                return;
            }

            if (!this.animover) {
                return;
            }

            this.animover = false;

            setTimeout(function(){
                that.animover = true;
            }, 1000);

            switch(this.currIndex) {
                case 0:
                    prev = 1;
                    next = 2;
                    break;
                case 1:
                    prev = 2;
                    next = 0;
                    break;
                case 2:
                    prev = 0;
                    next = 1;
                    break;
                default:
                    break;
            }

            var curr = this.e.find('.content').find('#img'+this.currIndex);
            xGame.doAnimate(curr, 'hero_LeftToRight1', '0.35s', function() {
                curr.css({'opacity':0.5, 'z-index':1, 'left':'260px', 'top':'130px', '-webkit-transform':'scale(0.7)'});
            });
            var pre = this.e.find('.content').find('#img'+prev);
            xGame.doAnimate(pre, 'hero_RightToLeft3', '0.35s', function() {
                pre.css({'opacity':0.5, 'z-index':1, 'left':'130px', 'top':'130px', '-webkit-transform':'scale(0.7)'});
            });

            var nex = this.e.find('.content').find('#img'+next);
            xGame.doAnimate(nex, 'hero_LeftToRight2', '0.35s', function() {
                nex.css({'opacity':1, 'z-index':10, 'left':'185px', 'top':'95px', '-webkit-transform':'scale(1.2)'});
            });

            this.currIndex = next;
            var herodata = this.herolist[this.currIndex];
            this.e.find('.desc').html('&nbsp;&nbsp;&nbsp;&nbsp;'+herodata.desc);
            this.setBuyBtnStatus(herodata.isget);

            this.e.find('.needgem').find('em').html(herodata.price1);
            this.e.find('.needprestige').find('em').html(herodata.price2);

            this.e.find('.scroller_btn_left').show();
            this.e.find('.scroller_btn_right').hide();
        },

        initPage: function(res) {
            var that = this;
            this.herolist = res.list;
          
            this.e = xGame.loadPopTmpl('outprintgod');
            //关闭按钮
            this.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                that.hide();
                e.preventDefault();
                e.stopPropagation();
                return false;
            });

            //购买按钮
            this.e.find('#receive_btn').unbind('touchend').bind('touchend', function(e){
                if (that.e.find('#receive_btn').hasClass('disabled')) {
                    return;
                }
                var initRender = function(res) {
                    switch (res.status) {
                        case 1:
                            that.e.find('#receive_btn').removeClass('disabled').addClass('disabled').html(lang('christmasgiftpop_had_buy'));
                            miscpop.newhero(res.general);
                            that.e.find('.totalgem').html(that.getWan(res.jade));
                            that.e.find('.totalprestige').html(that.getWan(res.reputation));
                            that.herolist[that.currIndex].isget = 2;
                            break;
                        default:
                            xGame.toast.show(res.msg);
                            break;
                    }
                };
                loadContents({
                    'c': 'general_only',
                    'm': 'buy',
                    'generalid': that.herolist[that.currIndex].generalid
                },
                initRender, true); //加载数据
                initRender = null;

                e.preventDefault();
                e.stopPropagation();
            });

            for (var i = 0; i < that.herolist.length; i++) {

                var html = '';
                var hero = that.herolist[i];
                
                html += '<div class="img" id="img'+i+'" uid="'+i+'" style="background:url(' + xGame.hero(hero.info.image, 1) + ') -11px 0 no-repeat;"></div>';
                
                this.e.find('.content').append(html);
            }
            
            this.e.undelegate('.img', 'touchstart').delegate('.img', 'touchstart', function(e){
                if (e.touches.length == 1) {
                    that.ismoving = false;
                    var touch = e.touches[0];
                    that.currpoint = touch.pageX;
                }
                e.preventDefault();
                e.stopPropagation();
            });

            this.e.undelegate('.img', 'touchmove').delegate('.img', 'touchmove', function(e){
                if (e.touches.length == 1) {
                    var id = $(this).attr('uid');
                    var touch = e.touches[0];
                    var offset = touch.pageX - that.currpoint;
                    if (offset > 5) {//向右划
                        that.ismoving = true;
                        that.onRightBtnClick();
                    } else if (offset < -5) {//向左划
                        that.ismoving = true;
                        that.onLeftBtnClick();
                    } 
                }
                e.preventDefault();
                e.stopPropagation();
                
            });
            
            this.e.undelegate('.img', 'touchend').delegate('.img', 'touchend', function (e) {
                if (!that.ismoving) {
                    var id = $(this).attr('uid');
                    miscpop.showHeroInfo(that.herolist[id].info);
                }
                e.preventDefault();
                e.stopPropagation();
            });
            //活动说明
            this.e.find('.info1').find('em').html(lang('putprint_info1'));
            this.e.find('.info2').find('em').html(lang('putprint_info2'));
            this.e.find('.info3').find('em').html(lang('putprint_info3'));
            //默认描述为第一个
            var herodata = this.herolist[0];
            this.e.find('.desc').html('&nbsp;&nbsp;&nbsp;&nbsp;'+herodata.desc);
            this.setBuyBtnStatus(herodata.isget);
            this.e.find('.needgem').find('em').html(herodata.price1);
            this.e.find('.needprestige').find('em').html(herodata.price2);
            this.e.find('.scroller_btn_left').show();
            this.e.find('.scroller_btn_right').hide();
            //刷新倒计时
            xGame.updateCd('outprint', res.cd);
            //刷新紫宝石数量
            this.e.find('.totalgem').html(this.getWan(res.jade));
            //刷新声望数量
            this.e.find('.totalprestige').html(this.getWan(res.reputation));
            //向左翻页按钮
            this.e.find('#scroller_btn_left').unbind('touchend').bind('touchend', function(e) {
                that.onLeftBtnClick();
                e.preventDefault();
                e.stopPropagation();
            });
            //向右翻页按钮
            this.e.find('.scroller_btn_right').unbind('click').bind('click', function(e) {
                that.onRightBtnClick();
                e.preventDefault();
                e.stopPropagation();
            });

            this.isshow = true;
            $('#worldloading').hide();
        },

        setBuyBtnStatus: function(status) {
            var buybtn = this.e.find('#receive_btn');
            switch(status){
                case 0:
                    //buybtn.removeClass('disabled').addClass('disabled').html(lang('buy'));
                    //break;
                case 1:
                    buybtn.removeClass('disabled').html(lang('buy'));
                    break;
                case 2:
                    buybtn.removeClass('disabled').addClass('disabled').html(lang('christmasgiftpop_had_buy'));
                    break;
                default:
                    break;
            }
        },

        getWan: function (num) {

            var num = parseInt(num);

            if (num >= 100000) {
                return Math.floor(parseInt(num) / 10000) + lang('10k');
            } else {
                return num;
            }
        },

        unbindAll: function() {
            this.e.find('#receive_btn').unbind('touchend');
            this.e.find('#outprintgodpop_scroller').undelegate('.img', 'click');
            this.e.find('#scroller_btn_left').unbind('touchend');
            this.e.find('.scroller_btn_right').unbind('click');
            this.e.undelegate('.img', 'touchstart');
            this.e.undelegate('.img', 'touchmove');
            this.e.undelegate('.img', 'click');
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            if (this.e) {
                this.unbindAll();
                this.e.remove();
                this.e = null;
            }
            if (this.heroScroller) {
                this.heroScroller.destroy();
                this.heroScroller = null;
            }
            this.isshow = false;
            this.herolist = null;
            this.currIndex = 0;
            this.animover = true;
            xGame.updateCd('outprint', 0);
        },
    };
})();