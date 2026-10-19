/* 国家领地 */
(function() {
    countryWorldClass = function() {
        this.e = null;
        this.dom = null;
        this.country_lv = 0;//当前的国家等级 20级开启国宴 国师30级
        this.myScroller;
        this.thread;
    };
    countryWorldClass.prototype = {
        init: function() {

            xGame.setMenuList2('map'); //显示回城按钮
            var dom_world = $('#scroller');
            dom_world.html($.template('countryworld_tmpl'));
            xGame.iscrollRefresh();
            $('#header').addClass('country');
            this.e = dom_world.find('#countryworld_page');
            this.e.undelegate('.building', 'click').delegate('.building', 'click',
            function(e) {
                var pop = this.id;
                if (pop == 'countrybattle_building') {
                    xGame.loadWorld('countrymap');
                } else if (pop == 'countrymine_building') {
                    if (parseInt(player.info.level) < 91) {
                        xGame.toast.show(lang('countrymine_lv91'));
                        return false;
                    }
                    xGame.loadWorld('countrymine');
                } else {
                    xGame.showpop(pop.replace('_building', ''));
                }
                e.preventDefault();
                e.stopPropagation();
            });
            //进入国家领地再提示可以申请
            if (player.info.country_war == 3 && parseInt(player.info.is_saturday) != 1) {//可以申请，官职也够并且不是周六
                miscpop.openCbattleConfirm(player.info.country_war);
            }
            player.showActivity('cbattle');
            $('#scroller').css({
                    '-webkit-transform': 'translate3d(0px, -' + (768 - $('body').height()) + 'px, 0px) scale(1)'
                });

            $('#worldloading').removeClass('black').hide();

            this.dom = $('#expostulation_wrap').find('p');
            this.showLeftBar();
            var that = this;
            this.thread = setInterval(function() {
                that.showLeftBar();
            }, 10000);
            this.myScroller = new iScroll('country_leftside_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: true
            });
            
            if (player.info.oncountrymine == 1) {
                this.e.find('#countrymine_building').children('span').css('display', 'block');
            } else {
                this.e.find('#countrymine_building').children('span').css('display', 'none');
            }
            
        },

        /*功能开放*/
        open: function() {
            if (this.country_lv >= 20) {
                $('#countrybanquet_wrap').addClass('open');
                $('#countrybanquet_wrap').find('p').html(lang('click_enter')).css('color','#027c39');
            } else {
                $('#countrybanquet_wrap').find('p').html(lang('open_need_level', ['20'])).css('color','#6b6b6b');;
            }
            if (this.country_lv >= 30) {
                $('#countrylearn_wrap').addClass('open');
                $('#countrylearn_wrap').find('p').html(lang('click_enter')).css('color','#027c39');
                $('#countrycollege_wrap').addClass('open');
                $('#countrycollege_wrap').find('p').html(lang('click_enter')).css('color','#027c39');
            } else {
                $('#countrylearn_wrap').find('p').html(lang('open_need_level', ['30'])).css('color','#6b6b6b');;
                $('#countrycollege_wrap').find('p').html(lang('open_need_level', ['30'])).css('color','#6b6b6b');;
            }
            if (this.country_lv >= 40) {
                $('#countryboss_wrap').addClass('open');
                $('#countryboss_wrap').find('p').html(lang('click_enter')).css('color','#027c39');
            } else {
                $('#countryboss_wrap').find('p').html(lang('open_need_level', ['40'])).css('color','#6b6b6b');;
            }
        },

        /*显示左边侧边栏*/
        showLeftBar: function() {
            if (this.dom == null) {
                return false;
            }
            var that = this;
            var Func = function(res) {
                if (that.dom == null) {
                    return false;
                }
                switch (parseInt(res.status)) {
                case - 4 : that.dom.html(lang('click_enter')).css('color','#027c39');
                    break;
                case - 3 :
                    //没有我支持过或者我提出的谏言
                    that.dom.html(lang('click_enter')).css('color','#027c39');
                    break;
                case - 2 :
                    //没有谏言
                    that.dom.html(lang('click_enter')).css('color','#027c39');
                    break;
                case - 1 : player.info.country = 0;
                    xGame.toast.show(lang('you_are_kicked_out'));
                    xGame.loadWorld('city');
                    break;
                case 1:
                    that.dom.html(lang('mission_stage') + ':' + res.list.plan + '/' + res.list.value).css('color', '#603206');
                    break;
                case 2:
                    that.dom.html(lang('click_reward')).css('color', '#027c39');
                    break;
                default:
                    that.dom.html(lang('get_jy_fail')).css('color', '#000000');;
                    break;
                }
                that.country_lv = parseInt(res.level);
                that.open();
            };
            loadContents({
                'c': 'expostulation',
                'm': 'get_expostulation_by_user'
            },
            Func, true, function(){});
            Func = null;
       },
         
        unbindAll: function () {
            this.e.undelegate('.building', 'click');
            this.e.remove();
            this.e = null;
        },

        clear: function() {
            xGame.unlock();
            console.log('释放国家领地资源');
            $('#header').removeClass('country');
            
            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            this.dom = null;
            

            /* 判断是否显示国战 */
            if (player.info.country_war > 0) {//1为报名，2为马上开始了,3为国家管理层可以申请占领了,4可以直接战斗
                if (player.info.country_war != 3) {
                    player.showActivity('cbattle');
                } else {
                    player.hideActivity('cbattle');
                }
            } else {
                player.hideActivity('cbattle');
            }
            if (this.thread != null) {
                clearInterval(this.thread);
                this.thread = null;
            }
           if (this.e) {
               this.unbindAll();
            }
            
        }
    };
})();