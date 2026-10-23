/* 点将台 */
(function() {
    musterPopClass = function() {
        this.e;
        this.isshow = false;
        this.data;
        this.nowViewId;
        this.myScroll;
        this.canout_num = 0;
        this.step = 0;  // 引导续接的步数
        this.skillScroll = null;
    };
    musterPopClass.prototype = {
        init: function(step) {
            var that = this;
            that.step = step;

            if (that.isshow) {
                $('#worldloading').hide();
                that = null;
                return;
            }

            var initRender = function(res) {

                if (!res || res.status != 1) {
                    xGame.toast.show(lang('get_hero_list'));
                    $('#worldloading').hide();
                    return;
                }
                /* 保存数据 */
                for (k in res.list) {
                    res.list[k].k = k; //保存键值
                }
                that.data = res.list;

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                that.e = xGame.loadPopTmpl('muster');
                that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                    return false;
                    }
                    musterpop.hide();
                    e.preventDefault();
                });

                //隐藏主城气泡通知
                if (player.info.ongeneral == 1) { //隐藏主城里气泡
                    player.info.ongeneral = 0;
                    $('#muster_building').children('.popo').hide();
                }

                /* 第二步 构建数据列表等 */
                that.buildlist(res);

                if (res.sale_cd == 0) {
                    that.e.find('.pop_btn_small').css('display', 'block');
                } else {
                    that.e.find('.pop_btn_small').css('display', 'none');
                    xGame.updateCd('salehero', res.sale_cd);
                }
                
                that.bindAll();
                
                /* 展示弹窗 */
                that.e.show();
                $('#worldloading').hide();

                xGame.showpop('system', {
                    'pop': 'muster',
                    'callback': function () {
                        that.guideShow();
                    }
                });

            };
            loadContents({
                'c': 'muster',
                'm': 'index',
                'page': 1,
                'perpage': 999
            },
            initRender, true); //加载数据
            initRender = null;

        },

        guideShow: function () {

            var that = this;
            
            //新手引导判断
            if (xGame.guide) {
                switch (guide.now) {
                    case 404:
                        guide.show(1001);
                        break;
                    case 1304:
                        guide.show(1011);
                        break;
                    case 1605:
                        guide.show(1606);
                        break;
                    case 1329: // 强制引导结束
                    case 1703: // 如果玩家在20级引导后没有继续征战，而是进入了点将台界面，则在招募张梁后不进行引导
                        xGame.xsSet(player.info.uid + 'guide_err', '1', true);
                        break;
                    case 1806:
                        guide.show(1807);
                        break;
                    default :
                        break;
                }
            }
            
            // 引导续接
            if (that.step) {
                if (that.step == 1002) {    // 如果要让周仓出征，则触发周仓的触摸事件
                    var dom = $('#herolist_carousel').find('.heroitem:eq(1)');
                    dom.trigger('click');
                    dom = null;
                } else if (that.step == 1012) { // 如果要让张梁出征，则触发张梁的触摸事件
                    var dom = $('#herolist_carousel').find('.heroitem:eq(2)');
                    dom.trigger('click');
                    dom = null;
                }
                guide.show(that.step);
            }
        },
        
        bindAll: function() {
            var that = this;
            
            /* 绑定事件(必须在旋转门之后) */
            that.e.find('#herolist_carousel').undelegate('.heroitem','click').delegate('.heroitem','click',
            function() {
                if (xGame.checkLock(true) || that.nowViewId == $(this).attr('gid')) { //不设置属性
                    return false;
                }
                that.viewHero($(this));
            });
            
            that.e.find('.avatarbox').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.saleFunc(0);
            });
            
            that.e.find('#hero_sale_dom').undelegate('#cancle_btn', 'touchend').delegate('#cancle_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.hideSaleDom();
            });
            
            that.e.find('#hero_sale_dom').undelegate('#sale_btn', 'touchend').delegate('#sale_btn', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.confirm.show(lang('notice'), lang('musterpop_salehero_ask'), 2,
                function() {
                    xGame.confirm.hide();
                    that.saleFunc(1);
                },
                function() {
                    xGame.confirm.hide();
                });
                
            });
        },

        buildRight: function(data) {
            var that = this;

            var dom_right = that.e.find('.heroinfo_wrap');
            dom_right.children('.avatarbox').attr('class', 'avatarbox q' + data.quality);
            that.e.find('.pop_btn_small').attr('quality', data.quality);
            dom_right.children('.avatarbox').children('.avatar').css('background', 'url(' + xGame.avatar(data.image) + ') 50% 50% no-repeat');
            dom_right.children('.heroinfo_p').children('h4').html(data.name + '&nbsp;&nbsp;<small>Lv.' + data.level + '</small>');
            dom_right.children('.heroinfo_p').children('.wuli').children('span').text(data.wuli);
            dom_right.children('.heroinfo_p').children('.zhili').children('span').text(data.zhili);
            dom_right.children('.heroinfo_p').children('.tili').children('span').text(data.tili);
            dom_right.children('.heroinfo_talent').find('.intro').text(data.talent.name + '：' + data.talent.description);
            dom_right.children('.heroinfo_skill').find('.intro').text(data.skill.name + '：' + data.skill.description);
            dom_right.find('#muster_star').css('width', (parseInt(data.star) * 18) + 'px');

            if (that.skillScroll == null) {
                that.skillScroll = new iScroll('muster_heroskill_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                });
            }

            that.skillScroll.scrollTo(0, 0);
            that.skillScroll.refresh();

            if (data.goout == 0) { //当前休息
                dom_right.find('#musterpop_battlebtn').text(lang('expedition'));
                dom_right.find('#musterpop_battlebtn').unbind('touchend').bind('touchend',
                function() {
                    that.setStatus(data.id, true);
                });
            } else {
                dom_right.find('#musterpop_battlebtn').text(lang('sleep'));
                dom_right.find('#musterpop_battlebtn').unbind('touchend').bind('touchend',
                function() {
                    that.setStatus(data.id, false);
                });
            }
            /*根据类型加class*/
            dom_right.find('.heroinfo_p').attr('class', 'heroinfo_p ' + (data.type == 1 ? 'wj': 'ms'));
        },

        /* 构建列表 */
        buildlist: function(res) {
            var that = this;

            var html = '<li class="cs_item hero_list">';
            var n = 0;
            that.canout_num = 0;
            for (var i in res.list) {
                if (n > 0 && n % 9 == 0) { //下一页了
                    html += '</li><li class="cs_item hero_list">';
                }
                var item = res.list[i];
                html += '<div id="hero' + item.id + '" class="q' + item.quality + ' heroitem ' + (n == 0 ? ' selected': '') + '" k="' + item.k + '" gid="' + item.id + '" image="' + item.image + '"><div class="itempic" style="background:url(' + xGame.avatar(item.image) + ') center center no-repeat;' + '"></div><p class="name">' + item.name + '</p>' + (item.goout == 1 ? '<span class="isbattle"></span>': '') + '</div>';
                n++;
                if (item.goout == 1) {
                    that.canout_num++;
                }
            }
            /* 补空格 */
            var repeat_s = '<div class="heroitem"><div class="itempic"></div><p class="name">&nbsp;</p></div>';
            if (n == 0) { //神马也没有
                html += helper.str_repeat(repeat_s, 9);
            } else if (n % 9 == 0) {
                html += '';
            } else {
                html += helper.str_repeat(repeat_s, 9 - n % 9);
            }
            html += '</li>';
            that.e.find('.carousel_content').html(html);

            if (that.myScroll != null) { //先干掉原本的滑动
                that.myScroll.scrollTo(0, 0);
                that.myScroll.destroy();
                delete that.myScroll;
            }
            /* 创建旋转门 domid, 单页宽度 */
            that.myScroll = xGame.carousel('herolist_carousel', 450);

            /* 绑定右侧数据*/
            that.updateGooutNum();
            if (res.first && res.first.id) {
                that.nowViewId = res.first.id;
                that.buildRight(res.first);
            }
        },

        updateGooutNum: function() {
            this.e.find('.canout').children('span').text(this.canout_num);
        },

        /* 查看某个武将 */
        viewHero: function(obj) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var k = obj.attr('k');
            if (that.data[k]) {
                var successFunc = function(res) {
                    if (res.status == 1) {
                        $('#hero' + that.nowViewId).removeClass('selected');
                        obj.addClass('selected');
                        that.nowViewId = that.data[k].id;
                        that.buildRight(res.info);
                        obj = null;
                        //新手引导判断，点击出征按钮
                        xGame.showGuide([1001, 1011, 1606, 1807]);
                        /*
                        if(xGame.guide == true && guide.now > 0) {
                            if(guide.now == 46) {//点击出征按钮
                                guide.show(47);
                            } else if(guide.now == 71) {//点击出征按钮
                                guide.show(72);
                            } else if(guide.now == 96) {//点击出征按钮
                                guide.show(97);
                            }
                        }*/
                        that.e.find('#hero_sale_dom').css('display', 'none');
                    } else {
                        xGame.toast.show(lang('can_not_get_hero_data'));
                        obj = null;
                    }

                    successFunc = null;
                    that = null;
                    obj = null;

                    xGame.unlock();
                };
                loadContents({
                    'c': 'muster',
                    'm': 'get_general_info',
                    'gid': that.data[k].id
                },
                successFunc);
                successFunc = null;
            } else {
                xGame.unlock();
            }
        },

        /* 出征 */
        setStatus: function(heroid, sendtobattle, confirm) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            if (typeof confirm == 'undefined') {
                confirm = 0;
            }

            var render = function(res) {
                that.lock = false;
                if (res.status == 1) {
                    //var notice_str = sendtobattle == true ? '出征成功！' : '休息成功！';
                    //xGame.toast.show(notice_str);
                    var heroitem = $('#hero' + heroid);
                    if (heroitem) {
                        var dom_right = that.e.find('.heroinfo_wrap');
                        if (sendtobattle == true) {
                            that.canout_num++;
                            that.updateGooutNum();
                            heroitem.append('<span class="isbattle"></span>');
                            dom_right.find('#musterpop_battlebtn').text(lang('sleep'));
                            dom_right.find('#musterpop_battlebtn').unbind('touchend').bind('touchend',
                            function() {
                                that.setStatus(heroid, false);
                            });
                            //新手引导判断 出征成功
                            xGame.showGuide([1002, 1012, 1607, 1808]);
                            /*
                                if(xGame.guide == true && guide.now > 0) {
                                    if(guide.now == 47) {//点击关闭按钮
                                        guide.show(48);
                                    } else if(guide.now == 72) {//点击关闭按钮
                                        guide.show(73);
                                    } else if(guide.now == 97) {//点击关闭按钮
                                        guide.show(98);
                                    }
                                }*/
                        } else {
                            that.canout_num--;
                            that.updateGooutNum();
                            heroitem.find('.isbattle').remove();
                            dom_right.find('#musterpop_battlebtn').text(lang('expedition'));
                            dom_right.find('#musterpop_battlebtn').unbind('touchend').bind('touchend',
                            function() {
                                that.setStatus(heroid, true);
                            });
                        }
                        dom_right = null;
                    } else {
                        that.reflesh(); //重新刷新页面
                    }
                } else if (res.status == 2) {
                    if (sendtobattle == true) {
                        var notice_str = lang('hero_is_sleeping');
                        xGame.toast.show(notice_str);
                    } else {
                        xGame.confirm.show(lang('notice'), lang('hero_sleep_ask'), 2,
                        function() {
                            that.setStatus(heroid, sendtobattle, 1);
                            xGame.confirm.hide();
                        },
                        function() {
                            xGame.confirm.hide();
                        });
                    }
                } else if (res.status == 4) {
                    xGame.toast.show(lang('unless_one_hero_expedition'));
                } else if (res.status == 3 && sendtobattle == true) {
                    xGame.toast.show(lang('can_not_expedition'));
                } else {
                    xGame.toast.show(lang('operation_fails'));
                }
                render = null;
                xGame.unlock();
            };
            loadContents({
                'c': 'muster',
                'm': (sendtobattle == true ? 'go_battle': 'go_rest'),
                'gid': heroid,
                'confrim': confirm
            },
            render);
            render = null;
        },

        reflesh: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function(res) {

                if (!res || res.status != 1) {
                    xGame.toast.show(lang('get_hero_list'));
                    return;
                }
                /* 保存数据 */
                that.data = res.list;

                that.buildlist(res);
                xGame.unlock();
            };
            loadContents({
                'c': 'muster',
                'm': 'index',
                'page': 1,
                'perpage': 999
            },
            render); //加载数据
            render = null;
        },
        
        saleFunc: function(confirm) {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                xGame.unlock();

                switch (parseInt(res.status)) {
                    case - 1 : xGame.toast.show(lang('vip_lv_too_low'));
                        break;
                    case - 2 : xGame.toast.show(lang('musterpop_cd_not_end'));
                        break;
                    case - 3 : xGame.toast.show(lang('musterpop_hero_not_own'));
                        break;
                    case - 4 : xGame.toast.show(lang('musterpop_can_not_sale'));
                        break;
                    case - 5 : xGame.toast.show(lang('musterpop_only_mingjiang'));
                        break;
                    case - 6 : xGame.toast.show(lang('musterpop_get_price_fail'));
                        break;
                    case - 7 : xGame.toast.show(lang('musterpop_cannot_stop_trainning'));
                        break;
                    case - 8 : xGame.toast.show(lang('musterpop_down_fail'));
                        break;
                    case - 9 : xGame.toast.show(lang('musterpop_user_hero_fail'));
                        break;
                    case - 10 : xGame.toast.show(lang('musterpop_update_cd_fail'));
                        break;
                    case - 11 : xGame.toast.show(lang('musterpop_add_gem_fail'));
                        break;
                    case 1:
                        if (confirm == 0) {//预览
                            that.showSaleDom(res);
                        } else {//折旧
                            that.Anim(res);
                        }
                        break;
                    default:
                        xGame.toast.show(lang('undefined_error'));
                        break;  
                }
            };
            loadContents({
                'c': 'muster',
                'm': 'sale_general',
                'gid': that.nowViewId,
                'confirm': confirm
            },
            Func); //加载数据
            Func = null;
        },
        
        showSaleDom: function(data) {
            var dom = this.e.find('#hero_sale_dom');
            
            dom.find('#current_vip').html('VIP' + data.rule.vip);
            dom.find('#back_precent').html(data.rule.ratio);
            
            dom.find('#hero_img').css('background','url(' + xGame.avatar(this.e.find('#hero' + this.nowViewId).attr('image')) + ') center center no-repeat');
            dom.find('#hero_price').html('<em>x' + data.reward.num + '</em>').attr('class', 'gem' + data.reward.type);
            
            dom.css('display', 'block');
            xGame.doAnimate(dom, 'fadeInLeft', '0.25s',
            function() {
                dom = null;
            });
            
            dom.find('#hero_box').attr('class', 'q' + this.e.find('.pop_btn_small').attr('quality'));
        },
        
        hideSaleDom: function(data) {
            var dom = this.e.find('#hero_sale_dom');
            xGame.doAnimate(dom, 'fadeOutLeft', '0.5s',
            function() {
                dom.css('display', 'none');
                dom = null;
            });
        },
        
        Anim: function(res) {
            xGame.islock = true;
            var that = this;
            var obj = that.e.find('#hero_img').clone().attr('id', 'temp');
            obj.appendTo(that.e.find('#hero_sale_dom')).addClass('herosale_anim').one('webkitAnimationEnd',
            function() {
                obj.remove();
                obj = null;
                that.shineAnim();
                that.e.find('#hero_price').addClass('tada').one('webkitAnimationEnd',
                function() {
                    $(this).removeClass('tada');
                    that.e.find('#hero_sale_dom').css('display', 'none');
                    that.rebuild(res);
                    that = null;
                });
            });
        },
        
        rebuild: function(res) {
            /* 保存数据 */
            for (k in res.list) {
                res.list[k].k = k; //保存键值
            }
            this.data = res.list;

            this.buildlist(res);
            //刷新后处理
            if (res.sale_cd == 0) {
                this.e.find('.pop_btn_small').css('display', 'block');
            } else {
                this.e.find('.pop_btn_small').css('display', 'none');
                xGame.updateCd('salehero', res.sale_cd);
            }
            xGame.toast.show(lang('musterpop_salehero_get', [res.reward.type, res.reward.num]));
            xGame.unlock();
        },
        
        shineAnim: function() {
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/inheritpop_nim.png',
                "width": 256,
                "height": 150,
                "x": 185,
                "y": 110,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 50
                },
                {
                    rect: [256, 0],
                    interval: 50
                },
                {
                    rect: [512, 0],
                    interval: 50
                },
                {
                    rect: [768, 0],
                    interval: 50
                },
                {
                    rect: [0, 150],
                    interval: 50
                },
                {
                    rect: [256, 150],
                    interval: 50
                },
                {
                    rect: [512, 150],
                    interval: 50
                },
                {
                    rect: [768, 150],
                    interval: 50
                },
                {
                    rect: [0, 300],
                    interval: 50
                },
                {
                    rect: [256, 300],
                    interval: 50
                }]
            });
            //开始播放
            mc.appendTo(this.e.find('#hero_sale_dom'));
            mc.play();
        },

        unbindAll: function() {
            this.e.find('.content').find('.hidebtn').unbind('touchend');
            this.e.find('.heroinfo_wrap').find('#musterpop_battlebtn').unbind('touchend');
            this.e.find('#herolist_carousel').undelegate('.heroitem','click');
            this.e.find('.avatarbox').undelegate('.pop_btn_small', 'touchend');
            this.e.find('#hero_sale_dom').undelegate('#cancle_btn', 'touchend');
            this.e.find('#hero_sale_dom').undelegate('#sale_btn', 'touchend');
            this.e.remove();
            this.e = null;
        },
        
        hide: function() {
            xGame.unlock();
            this.canout_num = 0;
            this.isshow = false;
            this.step = 0;
            this.data = null;
            if (this.myScroll) {
                this.myScroll.destroy();
                delete this.myScroll;
            }
            if (CD['salehero'] != undefined) {
                xGame.updateCd('salehero', 0);
            }
            if (this.e) {
                this.unbindAll();
            }
            this.nowViewId = null;

            if (this.skillScroll) {
                this.skillScroll.destroy();
                this.skillScroll = null;
            }
            
        },
    };
})();