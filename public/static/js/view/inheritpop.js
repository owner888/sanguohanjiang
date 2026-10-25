/* 继承 */
(function() {
    inheritPopClass = function() {
        this.e;
        this.isshow = false;
        this.bottomDom;
        this.topDom;
        this.moveDom;
        this.previewDom;
        this.payType;
        this.gid1; //9号位置武将id
        this.gid2; //10号位置
        this.heroData; //将领的基本信息
        this.heroDataByGid; //将领的基本信息,以武将id为键
        this.touchHeroData; //当前点击的英雄信息
        this.ruleData;

        this.touchGid; //当前点击的英雄id
        this.touchPos;
        this.canMove; //可以移动的集合
        this.heroPosList; //8个武将坐标集合
        this.boxPosList; //2个容器坐标集合
        this.nearlyX; //最近元素的x坐标
        this.nearlyY; //最近元素的y坐标
        this.nearlyPosId; //最近的元素的posid
        this.zIndex; //层级
        this.distance; //检测范围
        this.cd;
        
        this.freeSiteDate;//当前的免费位置
    };
    inheritPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var initRender = function(res) {

                if (res.status != 1) {
                    if (res.status == 2) {
                        xGame.toast.show(lang('have_no_hero_ready'));
                    } else if (res.status == -1) {
                        xGame.toast.show(lang('get_inherit_rule_fail'));
                    } else if (res.status == 3) {
                        xGame.toast.show(lang('lv_not_enough'));
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                    $('#worldloading').hide();
                    return;
                }

                /* 保存数据 */

                /* 第一步，把界面先载入，渠道DOM，并绑定好基础事件 */
                that.e = xGame.loadPopTmpl('inherit');
                that.e.find('.content').find('#hidebtn_1').unbind('touchend').bind('touchend',
                function(e) {
                    if (xGame.checkLock(true)) {
                        return false;
                    }
                    inheritpop.hide();
                    e.preventDefault();
                });

                that.bottomDom = that.e.find('#inheritpop_bottom');
                that.topDom = that.e.find('#inheritpop_top');
                that.moveDom = that.e.find('#move_dom');
                that.previewDom = that.e.find('#inheritpop_show');

                that.heroData = res.list;
                that.gid1 = 0;
                that.gid2 = 0;
                that.ruleData = {};
                that.checkForPreviewBtn();

                that.zIndex = 10;
                that.distance = that.getDistance(100, 100);

                //初始化位置参数
                that.heroPosList = {};
                that.heroDataByGid = {};
                var x;
                for (var i = 1; i < 9; i++) {
                    x = 114 * (i - 1);
                    that.heroPosList['pos' + i] = {
                        'left': x,
                        'top': '0',
                        'style': 'left:' + x + 'px;top:0px;',
                        'id': i
                    };
                    if (res.list[i] != undefined) {
                        that.heroDataByGid[res.list[i].id] = res.list[i];
                    }
                }
                that.heroPosList['pos9'] = {
                    'left': '546',
                    'top': '205',
                    'style': 'left:546px;top:205px;',
                    'id': '9'
                };
                that.heroPosList['pos10'] = {
                    'left': '743',
                    'top': '205',
                    'style': 'left:743px;top:205px;',
                    'id': '10'
                };
                x = null;
                that.boxPosList = {
                    'pos9': {
                        'left': '546',
                        'top': '205',
                        'style': 'left:546px;top:205px;',
                        'id': '9'
                    },
                    'pos10': {
                        'left': '743',
                        'top': '205',
                        'style': 'left:743px;top:205px;',
                        'id': '10'
                    }
                }

                that.canMove = {
                    'pos9': 'block',
                    'pos10': 'block'
                }
                
                //2013-8-20 所有都有可能免费
                that.freeSiteData = res.free_inherit || {};
                
                //判断是否有免费继承cd
                if(res.endtime > 0) {
                    //更新cd
                    that.cd = res.endtime;
                    xGame.updateCd('inherit', res.endtime);
                } else {
                    that.cd = 0;
                }

                that.buildHeroBylist(res.list || {});
                that.fillLeftRule(res.rule || {});

                that.bindAll();

                /* 展示弹窗 */
                that.e.show();
                $('#worldloading').hide();

            };
            loadContents({
                'c': 'general_inherit',
                'm': 'index'
            },
            initRender, true); //加载数据
            initRender = null;

        },

        /*创建顶部将领信息*/
        buildHeroBylist: function(list) {
            //html1 为topDom html2 为moveDom
            var html1 = '',
            html2 = '';
            for (var i = 1; i < 9; i++) {
                if (list[i] != undefined) {
                    html1 += '<li class="hero q' + list[i].quality + '" id="hero' + list[i].id + '"><span>' + list[i].name + '</span></li>';
                    html2 += '<span quality="' + list[i].quality + '" posid="' + i + '" gid="' + list[i].id + '" id="move_pos' + i + '" style="background-image:url(' + xGame.avatar(list[i].image) + ');' + this.heroPosList['pos' + i].style + '"></span>';
                } else {
                    html1 += '<li class="none"></li>';
                }
            }
            this.topDom.find('ul').html(html1);
            this.moveDom.html(html2);
            html1 = html2 = null;
        },

        /*拖动主要逻辑*/
        bindHero: function() {
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;

            that.moveDom.undelegate('span', 'touchstart').delegate('span', 'touchstart',
            function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }

                that.touchGid = $(this).attr('gid');
                that.touchPos = $(this).attr('posid');
                that.touchHeroData = that.heroData[that.touchPos];

                /*复制一个元素占着那个位置*/
                if (that.touchPos < 9) {
                    that.topDom.find('ul').find('#hero' + that.touchGid).append('<img src="' + xGame.avatar(that.heroData[that.touchPos].image) + '" />');
                }

                ex = parseInt(that.heroPosList['pos' + that.touchPos].left);
                ey = parseInt(that.heroPosList['pos' + that.touchPos].top);

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).addClass('ismoving');
                $(this).css('z-index', that.zIndex++);
                target = $(this);
            });

            that.e.unbind('touchmove').bind('touchmove',function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ex + newX;
                    var realY = ey + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                }
            });

            /*这里必须区分*/
            that.moveDom.undelegate('span.ismoving', 'touchend').delegate('span.ismoving', 'touchend',
            function(event) {
                target = null;
                $(this).removeClass('ismoving');
                $(this).css('z-index', 'auto');
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this);
                var myx = parseInt(obj.css('left').replace('px', ''));
                var myy = parseInt(obj.css('top').replace('px', ''));

                /*如果坐标最后都没变化，就直接不管它,只删除后面的img*/
                if (myx == ex && myy == ey) {
                    that.topDom.find('ul').find('#hero' + that.touchGid).find('img').remove();
                    return false;
                }
                /*移动的是已在容器里的武将*/
                if (that.touchPos > 8) {
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        if (that.canMove['pos' + that.nearlyPosId] == that.touchGid) { //自己的范围内，返回自己范围
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease', null);
                        } else { //对面
                            //如果对面是空的，过去，
                            var duiId = that.touchPos == 9 ? 10 : 9;
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                //到位置上后再显示该显示的武将信息
                                obj.attr({
                                    'posid': duiId,
                                    'id': 'move_pos' + duiId
                                }).html(that.getInnerHtml(that.heroDataByGid[that.touchGid]));
                            });
                            if (that.canMove['pos' + duiId] == 'block') {
                                that.canMove['pos' + that.touchPos] = 'block';
                            } else { //对面不是空的，交换
                                //先将对面的gid保存起来
                                var gid = that.canMove['pos' + duiId];
                                //console.log('对面的武将ID是' + gid);
                                that.moveDom.find('#move_pos' + duiId).animate({
                                    'left': that.heroPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.heroPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease',
                                function() {
                                    //到位置上后再显示该显示的武将信息
                                    $(this).attr({
                                        'posid': that.touchPos,
                                        'id': 'move_pos' + that.touchPos
                                    }).html(that.getInnerHtml(that.heroDataByGid[gid]));
                                });
                                that.canMove['pos' + that.touchPos] = gid;
                            }
                            //设置参数
                            that.canMove['pos' + duiId] = that.touchGid;
                        }
                    } else { //不合法，直接消失
                        obj.remove();
                        that.canMove['pos' + that.touchPos] = 'block';
                    }
                } else { //移动的没有在容器里的武将
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        if (that.canMove['pos' + that.nearlyPosId] == that.touchGid) { //选择的英雄在容器内，先靠近，最后回到原处
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                obj.css({
                                    'left': that.heroPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.heroPosList['pos' + that.touchPos].top + 'px'
                                });
                            });
                        } else { //选择的英雄不在容器内
                            //如果不是空位，删除原来空位元素，后面都一样：当前元素改成空位元素，新建一个初始元素，
                            if (that.canMove['pos' + that.nearlyPosId] != 'block') {
                                that.moveDom.find('#move_pos' + that.nearlyPosId).remove();
                            }
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease',
                            function() {
                                //到位置上后再显示该显示的武将信息
                                obj.attr({
                                    'posid': that.nearlyPosId,
                                    'id': 'move_pos' + that.nearlyPosId
                                }).html(that.getInnerHtml(that.touchHeroData));
                            });

                            that.moveDom.append('<span posid="' + that.touchPos + '" gid="' + that.touchHeroData.id + '" id="move_pos' + that.touchPos + '" style="background-image:url(' + xGame.avatar(that.touchHeroData.image) + ');' + that.heroPosList['pos' + that.touchPos].style + '"></span>');

                            //设置数据
                            that.canMove['pos' + that.nearlyPosId] = that.touchHeroData.id;

                            var duiId = that.nearlyPosId == 9 ? 10 : 9;
                            if (that.canMove['pos' + duiId] == that.touchGid) {
                                that.canMove['pos' + duiId] = 'block';
                                that.moveDom.find('#move_pos' + duiId).remove();
                            }
                            duiId = null;
                        }
                    } else { //不合法
                        //位置没到位,回到原处，显示上是消失了
                        obj.css({
                            'left': that.heroPosList['pos' + that.touchPos].left + 'px',
                            'top': that.heroPosList['pos' + that.touchPos].top + 'px'
                        });
                        that.topDom.find('ul').find('#hero' + that.touchGid).find('img').remove();
                    }
                }

                that.gid1 = that.canMove['pos9'] == 'block' ? 0 : that.canMove['pos9'];
                that.gid2 = that.canMove['pos10'] == 'block' ? 0 : that.canMove['pos10'];

                that.checkForPreviewBtn();

                if (that.canMove['pos9'] != 'block') { //当前不是空位
                    if ((that.nearlyPosId == 9 && that.touchPos != 9) || (that.nearlyPosId == 10 && that.canMove['pos10'] != 'block' && that.touchPos != 10)) { //如果最近的是9号位置，或者是交换请求
                        if (that.checkPos(myx, myy)) {
                            that.movePos9();
                        }
                    }
                } else { //当前是空位
                    that.bottomDom.find('#inheritpop_bottom_r').find('p').html('');
                    //金币清零
                    for(var i = 1; i <= 4; i++) {
                        if((i == player.info.free_inherit_site && that.cd == 0) || parseInt(that.freeSiteData['inherit_' + i]) > 0) {//2013-7-22 一次性免费继承
                            that.bottomDom.find('#inheritpop_li' + i).find('.t2').addClass('free');
                            that.bottomDom.find('#inheritpop_li' + i).find('.t5').removeClass('off_5');
                        } else {
                            that.bottomDom.find('#inheritpop_li' + i).find('.t2').removeClass('free');
                            that.bottomDom.find('#inheritpop_li' + i).find('.t5').addClass('off_5');
                            that.bottomDom.find('#inheritpop_li' + i).find('.t2').html(lang('0_gold'));
                        }
                    }
                }

                if (that.gid1 != 0) {
                    that.bottomDom.find('#d1').html('').attr('class', 'inhert_box q' + that.heroDataByGid[that.gid1].quality);
                } else {
                    that.bottomDom.find('#d1').html(lang('advancedpop_move_to')).attr('class', 'inhert_box');
                }

                if (that.gid2 != 0) {
                    that.bottomDom.find('#d2').html('').attr('class', 'inhert_box q' + that.heroDataByGid[that.gid2].quality);
                } else {
                    that.bottomDom.find('#d2').html(lang('advancedpop_move_to')).attr('class', 'inhert_box');
                }
            });
        },

        /*显示经验*/
        showExp: function() {
            if (this.ruleData[this.payType].allexp != undefined) {
                this.bottomDom.find('#inheritpop_bottom_r').find('p').html(lang('hero_inherit_exp') + '<span>' + this.ruleData[this.payType].allexp + '</span>');
            } else {
                this.bottomDom.find('#inheritpop_bottom_r').find('p').html('');
            }
        },

        /*预览*/
        preview: function(callback) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 4 : xGame.toast.show(lang('vip_lv_too_low'));
                    break;
                case - 3 : xGame.toast.show(lang('get_hero_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_inherit_rule_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('heri_id_repeat'));
                    break;
                case 3:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('hero_not_expedition'));
                    break;
                case 1:
                    that.fillPreview(res);
                    that.previewDom.css('display', 'block');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'general_inherit',
                'm': 'preview',
                'gid1': that.gid1,
                'gid2': that.gid2,
                'type': that.payType
            },
            Func);
            Func = null;
        },

        fillPreview: function(data) {
            this.previewDom.find('#preview_type').html(this.ruleData[this.payType].name);
            var html = '';
            //before
            html += '<li>' + this.getPreviewHtml(data.before[0]) + '</li>';
            html += '<li style="margin-left:54px;">' + this.getPreviewHtml(data.before[1]) + '</li>';
            //after
            html += '<li style="margin-top:61px;">' + this.getPreviewHtml(data.after[0]) + '</li>';
            html += '<li style="margin-top:61px;margin-left:54px;">' + this.getPreviewHtml(data.after[1]) + '</li>';

            this.previewDom.find('#preview_info').html(html);
            html = null;
        },

        getPreviewHtml: function(data) {
            var html = '';

            data.wuli = parseInt(data.wuli);
            data.zhili = parseInt(data.zhili);

            html += '<span style="background-image:url(' + xGame.avatar(data.image) + ');">' + this.getInnerHtml(data) + '</span>';
            html += '<p class="name">' + data.name + '</p>';
            html += '<p class="wl' + (data.type == 1 ? ' red': '') + '">' + lang('wuli') + '：<em>' + data.wuli + '+' + data.wuliup + '</em></p>';
            html += '<p class="zl' + (data.type == 2 ? ' red': '') + '">' + lang('zhili') + '：<em>' + data.zhili + '+' + data.zhiliup + '</em></p>';
            html += '<p class="tl">' + lang('tili') + '：<em>' + data.tili + '+' + data.tiliup + '</em></p>';

            return html;
        },

        /*继承*/
        goInherit: function() {
            if (xGame.checkLock()) {
                return false;
            }
            if (parseInt($('#inheritpop_bottom_r').find('p').children('span').html()) <= 0) {
                xGame.toast.show(lang('hero_exp_too_low'));
                xGame.unlock();
                return false;
            }

            var that = this;

            var Func = function(res) {
                /*特殊处理下*/
                if (res.status != 1) {
                    xGame.unlock();
                }
                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('vip_lv_too_low'));
                    break;
                case - 4 : xGame.toast.show(lang('update_hero_info_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_hero_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_inherit_rule_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('heri_id_repeat'));
                    break;
                case 5:
                    xGame.toast.show(lang('hero_exp_too_low'));
                    break;
                case 4:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 3:
                    xGame.toast.show(lang('gold_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('hero_not_expedition'));
                    break;
                case 4:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    player.updateinfo({
                        'gold':
                        res.gold
                    });
                    /*缺少武将后来的等级信息*/
                    that.freeSiteData = res.free_inherit;
                    var obj9 = null;
                    var obj10 = null;

                    //更新本地数据
                    for (var i = 1; i < 9; i++) {
                        if (that.heroData[i] != undefined) {
                            if (res.info[that.heroData[i].id] != undefined) {
                                that.heroDataByGid[that.heroData[i].id].level = res.info[that.heroData[i].id].level;
                                that.heroDataByGid[that.heroData[i].id].turn = res.info[that.heroData[i].id].turn;

                                that.heroData[i].levle = res.info[that.heroData[i].id].level;
                                that.heroData[i].turn = res.info[that.heroData[i].id].turn;

                                if (obj9 == null && that.heroData[i].id == that.canMove['pos9']) {
                                    obj9 = that.heroData[i];
                                } else {
                                    obj10 = that.heroData[i];
                                }
                            }
                        }
                    }

                    that.inheritAnim(obj9, obj10);

                    //判断是否有免费继承cd
                    if(res.endtime > 0) {
                        //更新cd
                        that.cd = res.endtime;
                        xGame.updateCd('inherit', res.endtime);
                    } else {
                        that.cd = 0;
                    }

                    //金币清零
                    for(var i = 1; i <= 4; i++) {
                        if((i == player.info.free_inherit_site && that.cd == 0) || parseInt(that.freeSiteData['inherit_' + i]) > 0) {//2013-7-22 一次性免费继承
                            that.bottomDom.find('#inheritpop_li' + i).find('.t2').addClass('free');
                            that.bottomDom.find('#inheritpop_li' + i).find('.t5').removeClass('off_5');
                        } else {
                            that.bottomDom.find('#inheritpop_li' + i).find('.t2').removeClass('free');
                            that.bottomDom.find('#inheritpop_li' + i).find('.t5').addClass('off_5');
                            that.bottomDom.find('#inheritpop_li' + i).find('.t2').html(lang('0_gold'));
                        }
                    }
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
            };
            loadContents({
                'c':
                'general_inherit',
                'm': 'go_inherit',
                'gid1': that.gid1,
                'gid2': that.gid2,
                'type': that.payType,
                'confirm': '1'
            },
            Func);
            Func = null;
        },

        checkInherit: function() {
            if (xGame.checkLock()) {
                return false;
            }
            if (parseInt($('#inheritpop_bottom_r').find('p').children('span').html()) <= 0) {
                xGame.toast.show(lang('hero_exp_too_low'));
                xGame.unlock();
                return false;
            }

            var that = this;

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 5 : xGame.toast.show(lang('vip_lv_too_low'));
                    break;
                case - 4 : xGame.toast.show(lang('update_hero_info_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('get_hero_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('get_inherit_rule_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('heri_id_repeat'));
                    break;
                case 5:
                    xGame.toast.show(lang('hero_exp_too_low'));
                    break;
                case 4:
                    xGame.toast.show(lang('your_lv_too_low'));
                    break;
                case 3:
                    xGame.toast.show(lang('gold_not_enough'));
                    break;
                case 2:
                    xGame.toast.show(lang('hero_not_expedition'));
                    break;
                case 4:
                    xGame.toast.show(lang('lv_not_enough'));
                    break;
                case 1:
                    xGame.confirm.show(lang('notice'), lang('inherit_ask') + (res.max == 1 ? '<br/><b style="color:#f00;font-size: 18px;">' + lang('inherit_ask_tip') + '</b>': ''), 2,
                    function() {
                        xGame.confirm.hide();
                        that.goInherit();
                    },
                    function() {
                        xGame.confirm.hide();
                    });
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'general_inherit',
                'm': 'go_inherit',
                'gid1': that.gid1,
                'gid2': that.gid2,
                'type': that.payType
            },
            Func);
            Func = null;
        },

        inheritAnim: function(obj9, obj10) {
            xGame.islock = true;
            var that = this;
            that.moveDom.find('#move_pos9').html('');
            that.moveDom.find('#move_pos10').html('');
            var obj = that.moveDom.find('#move_pos9').clone().attr('id', 'temp');
            obj.appendTo(that.moveDom).addClass('inherit_anim').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
            function() {
                obj.remove();
                obj = null;
                that.shineAnim();
                that.moveDom.find('#move_pos10').addClass('tada').unbind('webkitAnimationEnd').one('webkitAnimationEnd',
                function() {
                    $(this).removeClass('tada');

                    that.moveDom.find('#move_pos9').html(that.getInnerHtml(obj9));
                    that.moveDom.find('#move_pos10').html(that.getInnerHtml(obj10));
                    that.bottomDom.find('#inheritpop_bottom_r').find('p').html(lang('hero_inherit_exp') + '<span>0</span>');
                    that = null;
                });
            });
        },

        shineAnim: function() {
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": staticUrl + 'img/pop/inheritpop_nim.png',
                "width": 256,
                "height": 150,
                "x": 670,
                "y": 175,
                "loop": false,
                "endDestory": true,
                "callback": function() {
                    xGame.islock = false;
                },
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
            mc.appendTo(this.moveDom);
            mc.play();
        },

        /*移动到9号位置上时触发*/
        movePos9: function(callback) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_inherit_rule_fail'));
                    break;
                case 2:
                    xGame.toast.show(lang('hero_inherit_exp'));
                    break;
                case 3:
                    xGame.toast.show(lang('your_lv_too_low'));
                    break;
                case 1:
                    that.fillLeftRule(res.rule);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };
            loadContents({
                'c':
                'general_inherit',
                'm': 'get_inherit_info',
                'gid': that.gid1
            },
            Func);
        },

        getInnerHtml: function(data) {
            return '<b class="t1">Lv.' + data.level + '</b>' + (data.turn > 0 ? '<b class="t2">' + data.turn + '</b>': '');
        },

        /*这个功能的作用是传进参数，检测边界，
         * 返回true时代表有位置可以到达，再设置that.nearlyX和that.nearlyY完成操作；
         * 返回false时就代表直接返回原来的位置
         */
        checkPos: function(x, y) {
            var that = this;

            /*结果集*/
            var result = [];
            that.nearlyX = that.nearlyY = that.nearlyPosId = 0;

            /*第一步，告诉程序有几个点可以移动，不用排除自己*/
            ////console.log(that.canMove);
            for (var i = 9; i < 11; i++) {
                if (that.canMove['pos' + i] != undefined) {
                    result.push({
                        'name': 'pos' + i,
                        'id': that.boxPosList['pos' + i].id,
                        'distance': that.getDistance(Math.abs(that.boxPosList['pos' + i].left - x), Math.abs(that.boxPosList['pos' + i].top - y))
                    });
                }
            }
            /*按属性排大小，方法写在base.js里面*/
            result.sort(helper.compare("distance"));
            if (result.length != 0) {
                that.nearlyPosId = result[0]['id'];
                /*不管如何都返回最近的x，y*/
                that.nearlyX = that.boxPosList[result[0]['name']].left;
                that.nearlyY = that.boxPosList[result[0]['name']].top;

                var myDistance = that.getDistance(Math.abs(that.nearlyX - x), Math.abs(that.nearlyY - y));

                //console.log('距离 = ' + myDistance);
                //console.log('最近的posid = ' + that.nearlyPosId);
                if (myDistance < that.distance * 3 / 4) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }

        },

        /*判断按钮是否该变灰*/
        checkForPreviewBtn: function() {
            if (this.gid1 == 0 || this.gid2 == 0) {
                this.bottomDom.find('#inheritpop_bottom_btn1').attr('class', 'btn pop_btn_middle_disable');
                this.bottomDom.find('#inheritpop_bottom_btn2').attr('class', 'btn pop_btn_middle_disable');
            } else {
                this.bottomDom.find('#inheritpop_bottom_btn1').attr('class', 'btn pop_btn_middle');
                this.bottomDom.find('#inheritpop_bottom_btn2').attr('class', 'btn pop_btn_middle');
            }
        },

        /*出拖动外的绑定事件*/
        bindAll: function() {
            var that = this;

            that.bottomDom.undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#inheritpop_small').css('display', 'block');
            });

            that.bottomDom.undelegate('#inheritpop_bottom_btn1.pop_btn_middle', 'touchend').delegate('#inheritpop_bottom_btn1.pop_btn_middle', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.preview();
            });

            that.bottomDom.undelegate('#inheritpop_bottom_btn2', 'touchend').delegate('#inheritpop_bottom_btn2', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.checkInherit();
            });

            that.bottomDom.find('ul').undelegate('li', 'touchend').delegate('li', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).attr('ptype') == that.payType) { //不设置属性
                    return false;
                }
                that.bottomDom.find('ul').find('#inheritpop_li' + that.payType).removeClass('selected');
                $(this).addClass('selected');
                that.payType = $(this).attr('ptype');
                that.showExp();
            });

            that.e.find('#inheritpop_small').undelegate('#inheritpop_small_hide', 'touchend').delegate('#inheritpop_small_hide', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) { //不设置属性
                    return false;
                }
                that.e.find('#inheritpop_small').css('display', 'none');
            });

            that.previewDom.undelegate('#inheritpop_show_hide', 'touchend').delegate('#inheritpop_show_hide', 'touchend',
            function() {
                if (xGame.checkLock(true) || $(this).hasClass('selected')) { //不设置属性
                    return false;
                }
                that.previewDom.css('display', 'none');
            });

            that.bindHero();
        },

        /*填充规则*/
        fillLeftRule: function(list) {
            var html = '';
            if (this.payType == undefined) {
                this.payType = 1;
            }
            this.ruleData = list || {};
            if (!this.ruleData['1']) {
                this.ruleData['1'] = { id: 1, name: '普通继承', gold: 0, exp: 70, attr: 70 };
            }

            this.ruleData['1'].color = '#fff';
            if (this.ruleData['2'] != undefined) {
                this.ruleData['2'].color = '#09964c';
            }
            if (this.ruleData['3'] != undefined) {
                this.ruleData['3'].color = '#d6a600';
            }
            if (this.ruleData['4'] != undefined) {
                this.ruleData['4'].color = '#4A3113';
            }

            for (var i = 1; i < 5; i++) {
                if (list[i] != undefined) {
                    html += '<li ' + (i == this.payType ? ' class="selected" ': '') + 'id="inheritpop_li' + list[i].id + '" ptype="' + list[i].id + '">';
                    html += '<span class="t1" style="color:' + this.ruleData[i].color + '">' + list[i].name + '：</span>';
                    if((i == player.info.free_inherit_site && this.cd == 0) || parseInt(this.freeSiteData['inherit_' + i]) > 0) {//2013-7-22 一次性免费继承
                        html += '<span class="t2 free"></span>';
                    } else {
                        html += '<span class="t2">' + list[i].gold + lang('gold') + '</span>';
                    }
                    html += '<span class="t3">' + lang('inherit_exp') + ' ' + list[i].exp + '%</span>';
                    html += '<span class="t4">' + lang('inherit_properties') + ' ' + list[i].attr + '%</span>';
                    //必须是vip7以上,否则就不存在这个dom;不是免费继承，显示5折，
                    if (parseInt(player.info.vip) >= 7) {
                        if((i == player.info.free_inherit_site && this.cd == 0) || parseInt(this.freeSiteData['inherit_' + i]) > 0) {//2013-7-22 一次性免费继承
                            html += '<span class="t5"></span>';
                        } else {
                            html += '<span class="t5 off_5"></span>';
                        }
                    } else {
                        html += '';
                    }
                    html += '</li>';
                }
            }
            this.bottomDom.find('ul').html(html);
            this.showExp();
            html = null;
        },

        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('#hidebtn_1').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.find('#inheritpop_small').undelegate('#inheritpop_small_hide', 'touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.moveDom) {
                this.moveDom.undelegate('span', 'touchstart');
                this.moveDom.undelegate('span.ismoving', 'touchend');
                this.moveDom.find('#move_pos9').unbind('webkitAnimationEnd');
                this.moveDom.find('#move_pos10').unbind('webkitAnimationEnd');
                this.moveDom.remove();
                this.moveDom = null;
            }
            if (this.bottomDom) {
                this.bottomDom.undelegate('.pop_btn_small', 'touchend');
                this.bottomDom.undelegate('#inheritpop_bottom_btn1.pop_btn_middle', 'touchend');
                this.bottomDom.undelegate('#inheritpop_bottom_btn2', 'touchend');
                this.bottomDom.find('ul').undelegate('li', 'touchend');
                this.bottomDom.remove();
                this.bottomDom = null;
            }
            if (this.previewDom) {
                this.previewDom.undelegate('#inheritpop_show_hide', 'touchend');
                this.previewDom.remove();
                this.previewDom = null;
            }
        },

        hide: function() {

            xGame.unlock();

            this.isshow = false;
          
            if (this.topDom) {
                this.topDom.remove();
                this.topDom = null;
            }
         
            this.payType = null;
            this.gid1 = this.gid2 = this.heroData = this.heroDataByGid = null;
            this.touchHeroData = this.ruleData = this.touchGid = this.touchPos = this.canMove = null;
            this.heroPosList = this.boxPosList = this.nearlyX = this.nearlyY = this.nearlyPosId = null;
            this.zIndex = this.distance = null;

            xGame.updateCd('inherit', 0);
            this.freeSiteData = null;//2013-7-22 一次性免费继承
            
            if (xGame.nowWorld == 'country' && countryWorld != undefined) {
                countryWorld.showLeftBar();
            }
            this.unbindAll();
            
        }
    };
})();