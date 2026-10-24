/* 布阵 */
(function() {
    teamPopClass = function() {
        this.e = null;
        this.isshow = false;
        this.wrapDom = null;
        this.heroConentDom = null;
        this.matrixContentDom = null;
        this.teamContentDom = null;
        this.nowMid = 0;            // 当前的阵法ID
        this.selectMid = 0;         // 选择的阵法ID
        this.heroData = null;       // 存储将领数据
        this.matrixData = null;     // 存储布阵数据
        this.matrixArr = null;      // 当前阵法的九宫格数据
        this.selectFormationData = null;    // 当前阵法的阵型数据和上阵将领数据
        this.formationData = null;  // 前8个阵法的阵型数据
        this.scroller = null;
        this.moving = false;        // 是否正在拖动
        this.isMove = false;        // 将领是否移动
        this.moveItemDom = null;    // 移动的将领
        this.replaceItemDom = null; // 被替换的将领
        this.targetIndex = 0;       // 要移动到的阵法框的Index
        this.moveHeroId = 0;        // 移动将领的ID
        this.startPos = null;       // 起始触摸点
        this.moveItemLeft = 0;
        this.moveItemTop = 0;
        this.step = 0;

    };

    teamPopClass.prototype = {
        
        init: function(step) {
            var that = this;
            that.step = step;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }
            
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.initPage(res);
                        break;
                    case -1:
                        xGame.toast.show(lang('get_own_team_fail'));
                        $('#worldloading').hide();
                        break;
                    case -2:
                        xGame.toast.show(lang('get_team_tmpl_fail'));
                        $('#worldloading').hide();
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        $('#worldloading').hide();
                        break;
                }

            };

            loadContents({
                'c': 'matrix',
                'm': 'index'
            },
            initRender);
            initRender = null;

        },
        
        getStyle: function(data) {
            return 'left:' + data.left + 'px;top:' + data.top + 'px';
        },

        initPage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('team');
            that.wrapDom = that.e.find('#teampop_wrap');

            var vip = parseInt(player.info.vip);

            if (vip >= 13) {
                that.wrapDom.addClass('vip').addClass('vip13');
            } else if (vip >= 10) {
                that.wrapDom.addClass('vip').addClass('vip10');
            } else if (vip >= 7) {
                that.wrapDom.addClass('vip').addClass('vip7');
            }

            // 关闭按钮
            that.e.find('.content').find('.hidebtn').unbind('touchend').bind('touchend', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.hide();
                e.preventDefault();
            });

            that.heroConentDom = that.e.find('#hero_content');
            that.matrixContentDom = that.e.find('#matrix_content');
            that.teamContentDom = that.e.find('#team_content');
            that.moveItemDom = that.e.find('#move_item');
            that.replaceItemDom = that.e.find('#replace_item');
            that.heroData = {};
            that.matrixData = {};
            that.matrixArr = [];
            that.formationData = {
                '1' : {'1':'-2','3':'-2','5':'-2','8':'-2'},
                '2' : {'4':'-2','6':'-2','7':'-2','9':'-2'},
                '3' : {'2':'-2','3':'-2','7':'-2','8':'-2'},
                '4' : {'2':'-2','4':'-2','6':'-2','8':'-2'},
                '5' : {'1':'-2','3':'-2','7':'-2','9':'-2'},
                '6' : {'2':'-2','5':'-2','7':'-2','9':'-2'},
                '7' : {'1':'-2','5':'-2','6':'-2','7':'-2'},
                '8' : {'2':'-2','3':'-2','5':'-2','6':'-2'}
            };
            that.selectFormationData = {};

            that.showHero(res.general);
            that.showTeam(res.matrix);
            that.bindAll();

            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();

            xGame.showpop('system', {
                'pop': 'team',
                'callback': function () {
                    that.guideShow();
                }
            });

            // PC拖动异常处理
            that.preventOverflowOnPC();
        },

        guideShow: function () {

            var that = this;

            /* 新手引导处理 */
            if (xGame.guide) {
                switch (guide.now) {
                    case 409:
                        guide.show(1101);
                        break;
                    case 1318:
                        guide.show(1111);
                        break;
                    case 1609:
                        guide.show(1610);
                        break;
                    default :
                        break;
                }
            }

            // 引导续接
            if (that.step) {
                switch(that.step) {//此处需要让玩家重新拖放阵法（在关闭阵法之前，是没有把上阵信息发到服务器保存的）
                    case 1102:
                    case 1103:
                        guide.show(1102);
                        break;
                    case 1112:
                    case 1113:
                    case 1114:
                        guide.show(1112);
                        break;
                    default:
                        guide.show(that.step);
                        break;
                }
            }
        },

        // 构建左侧的英雄列表
        showHero: function (hero) {

            var that = this;
            var html = '';
            var avatar = null;
            if (!hero) hero = {};
            
            for (var i = 1; i <= 8; i++) {
                avatar = (hero[i] !== undefined) ? hero[i] : (hero[i - 1] !== undefined ? hero[i - 1] : null);
                if (avatar == null) {
                    html += '<li class="hero_item touch_item"></li>';
                    continue;
                }

                that.heroData[avatar.id] = avatar;

                html += '<li class="hero_item touch_item" id="hero'+ avatar.id +'" heroId="'+ avatar.id +'">';
                html += '<span class="pic" style="background-image:url('+ xGame.avatar(avatar.image) +')"></span>';
                html += '<em class="name">' + avatar.name + '</em>';
                //html += '<em class="on '+ (parseInt(avatar.goout) == 1 ? '' : 'hide') +'"></em>';
                html += '<em class="on"></em>';
                html += '</li>';
            }

            that.heroConentDom.find('#scroller_content').html(html);
        },

        // 显示布阵列表
        showTeam: function (matrix) {
            
            var that = this;
            var data = null;
            var html = '';
            var length = 0;
            var dataOpen = null;

            // 对数据进行重排序
            for (var i = 1; i <= 11; i++) {

                data = matrix[i];
                
                if (data != null) {
                    that.matrixData[data.sort] = data;
                    length ++;
                }
            }

            // 构建布阵列表
            for (var i = 1; i <= 11; i++) {
                
                data = that.matrixData[i];

                if (data == null) {
                    continue;
                }

                if (parseInt(data.open) == 1) {
                    dataOpen = data;
                    that.nowMid = that.selectMid = data.mid;
                }
                    
                html += '<li class="team_item'+ (parseInt(data.open) == 1 ? ' selected' : '') +'" id="team'+ data.mid +'" mid="'+ data.mid +'">';
                html += '<span class="team_pic" style="background-image:url('+ staticUrl +'img/pop/teampop_'+ (parseInt(player.info.vip) < 7 ? '' : 'vip_') +'matrix'+ data.mid +'.png)"></span>';
                html += '<span class="text"></span><span class="selected_pic"></span>';

                if (parseInt(data.vip) > 0) {
                    html += '<span class="vip_star">' + that.transfer(data.vip) + '</span>';
                }

                html += '</li>';

            }

            that.teamContentDom.find('#teampop_list').html(html).find('.selected').children('.text').html(lang('enable'));

            if (length > 8) {
                that.scroller = new iScroll('teampop_scroller', {
                    bounce: false,
                    momentum: false,
                    hScrollbar: false,
                    vScrollbar: false
                });
            }

            if (!dataOpen) {
                dataOpen = that.matrixData[1] || matrix[1] || matrix[that.nowMid] || null;
                if (dataOpen) {
                    that.nowMid = that.selectMid = dataOpen.mid;
                }
            }

            if (dataOpen) {
                that.showMatrix(dataOpen);
            }
        },

        // 构建九宫格阵法
        showMatrix: function (data) {
            var that = this;
            if (!data) return;
            that.isMove = false;

            that.showMatrixInfo(data);
            that.matrixArr = [];

            // 存储阵型数据
            for (var i = 1; i <= 9; i++) {

                if (parseInt(data.vip) >= 7 && parseInt(data['position' + i]) == -1) {
                    data['position' + i] = -2;
                }

                that.matrixArr.push(data['position' + i]);

                if (that.formationData[that.selectMid] != null && that.formationData[that.selectMid][i] == '-2') {
                    that.selectFormationData[i] = '-2';
                } else {
                    that.selectFormationData[i] = data['position' + i];
                }
            }

            that.showFormation();
            xGame.unlock();
        },

        // 显示阵法信息
        showMatrixInfo: function (data) {
            var that = this;
            if (!data) return;
            data.addition = data.addition || { type: 1, percent: 5, next: 1000 };
            
            // 主要用来显示中间阵法详情
            if (parseInt(data.mid) > 8) {
                if (data.mid == 9) {
                    that.matrixContentDom.find('#matrix_name').html('<em>(' + data.level + lang('lv') + ')</em>').attr('class', 'vip vip7');
                } else if (data.mid == 10) {
                    that.matrixContentDom.find('#matrix_name').html('<em>(' + data.level + lang('lv') + ')</em>').attr('class', 'vip vip10');
                } else if (data.mid == 11) {
                    that.matrixContentDom.find('#matrix_name').html('<em>(' + data.level + lang('lv') + ')</em>').attr('class', 'vip vip13');
                }
            } else {
                that.matrixContentDom.find('#matrix_name').html(helper.getNameByMatrixId(data.mid) + '<em>(' + data.level + lang('lv') + ')</em>').removeClass();
            }
            
            that.matrixContentDom.find('#matrix_info').html(helper.getPowerName(data.addition.type) + lang('up_percent', [data.addition.percent]));
            that.matrixContentDom.find('p').html(lang('notice') + ':' + that.showMsgByLevel(data.level));
            that.showBtnByPrice(data.addition.next);
        },

        // 构建九宫格
        showFormation: function () {

            var that = this;
            var dom = that.matrixContentDom.find('#matrix_list');
            var item = 0;
            var li = null;
            var html = '';
            var hand = 1;

            // 清空数据
            dom.children('li').removeClass().removeAttr('heroid').html('');

            for (var i = 1; i <= 9; i++) {

                item = parseInt(that.selectFormationData[i]);
                li = dom.children('#pos' + i);
                li.addClass('matrix_item').addClass('touch_item');
                
                if (item == -2) {
                    continue;
                } else if (item == -1) {
                    li.addClass('close');
                } else if (item == 0) {
                    li.addClass('block').addClass('canMove');
                } else {
                    li.addClass('on').addClass('canMove').attr('heroid', item);
                    var heroImg = (that.heroData && that.heroData[item] && that.heroData[item].image) ? that.heroData[item].image : (player.info ? player.info.gender : 1);
                    html = '<span class="pic" style="background-image:url('+ xGame.avatar(heroImg) +')"></span>';
                    html += '<em class="hand hand'+ hand +'"></em>';
                    li.html(html);
                    hand ++;
                }
            }

            that.goBattle();
            
        },

        // 标记将领上阵
        goBattle: function () {

            var that = this;
            var sign = null;
            that.heroConentDom.find('.on').addClass('hide');
            
            for (var i = 0, len = that.matrixArr.length; i < len; i++) {                
                
                if (parseInt(that.matrixArr[i]) > 0) {
                    that.heroConentDom.find('#hero' + that.matrixArr[i]).children('.on').removeClass('hide');
                }
            }
        },

        bindAll: function() {
            var that = this;

            that.matrixContentDom.find('#matrix_head').undelegate('a', 'touchend').delegate('a', 'touchend', function() {
                
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                
                that.levelupMatrix();
            });

            /*绑定右边阵法图片*/
            that.teamContentDom.find('#teampop_list').undelegate('.team_item', 'click').delegate('.team_item', 'click', function(e) {

                if (xGame.checkLock(true) || $(this).hasClass('block')) { //不设置属性
                    return false;
                }

                // 如果在引导期间
                if (xGame.guide) {
                    return false;
                }

                var mid = $(this).attr('mid');

                if (that.selectMid == mid) {
                    return false;
                }
                
                /*点其他阵时更新阵法*/
                that.updateTeam();
                that.selectMid = mid;

                that.getMatrixData();
                e.preventDefault();
                
            });

            /*绑定启用阵法按钮*/
            that.teamContentDom.undelegate('.btn', 'touchend').delegate('.btn', 'touchend', function() {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.isNotBlock()) {
                    if (that.isMove) {
                        that.updateTeam('callback');
                    } else {
                        that.openMatrix();
                    }
                } else {
                    xGame.toast.show(lang('no_hero_to_enable'));
                }

            });

            that.bindHero();

        },

        bindHero: function() {

            var that = this;

            // 为将领绑定触摸事件
            that.wrapDom.undelegate('.touch_item', 'touchstart').delegate('.touch_item', 'touchstart', function(e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                that.moveHeroId = $(this).attr('heroid');

                // 如果没有将领，禁止移动
                if (that.moveHeroId == null) {
                    that.moveHeroId = 0;
                    return false;
                }

                // 布阵引导
                if (miscpop.in_array(guide.now, [1102, 1112, 1113, 1611])) {

                    var index = 0;
                    var heroList = that.heroConentDom.find('.hero_item');
                    
                    if (guide.now == 1102) {
                        index = heroList.index($(this));

                        if (index != 1) {
                            that.moveHeroId = 0;
                            return false;
                        }
                    } else if (guide.now == 1112) {
                        index = heroList.index($(this));

                        if (index != 2) {
                            that.moveHeroId = 0;
                            return false;
                        }
                    } else if (guide.now == 1113) {
                        var pos = parseInt($(this).attr('pos'));
                        if (pos != 6)  {
                            that.moveHeroId = 0;
                            return false;
                        }
                    } else if (guide.now == 1611) {
                        index = heroList.index($(this));

                        if (index != 3) {
                            that.moveHeroId = 0;
                            return false;
                        }
                    }
                }

                if (e.targetTouches.length == 1) {
                    that.startPos = {pageX: e.targetTouches[0].pageX, pageY: e.targetTouches[0].pageY};
                    var $this = $(this);
                    var offset1 = $this.offset();
                    that.moveItemLeft = offset1.left;
                    that.moveItemTop = offset1.top;
                    that.moveItemDom.css({'-webkit-transform': 'translate3d('+that.moveItemLeft+'px, '+that.moveItemTop+'px, 0)', 'width': offset1.width + 'px', 'height': offset1.height + 'px' , 'background-image': $this.children('.pic').css('background-image')});
                    //that.moveItemDom.css({'left': that.moveItemLeft + 'px', 'top': that.moveItemTop + 'px', 'width': offset1.width + 'px', 'height': offset1.height + 'px' , 'background-image': $this.children('.pic').css('background-image')});
                    that.scaleAnim();
                }

                e.preventDefault();

                that.e.unbind('touchmove').bind('touchmove', function (e) {
                    that.moving = true;
                    var touch = e.targetTouches[0];
                    var x = touch.pageX - that.startPos.pageX;
                    var y = touch.pageY - that.startPos.pageY;
                    that.moveItemDom.css({'-webkit-transform': 'translate3d('+(that.moveItemLeft + x)+'px, '+(that.moveItemTop + y)+'px, 0)'});
                    //that.moveItemDom.css({'left': (that.moveItemLeft + x) + 'px', 'top': (that.moveItemTop + y) + 'px'});

                });

                that.e.unbind('touchend').bind('touchend', function (e) {

                    that.moving = false;

                    var offset2 = that.moveItemDom.offset();

                    // 如果没有发生移动
                    if (that.checkClick(offset1, offset2)) {
                    	that.moveItemDom.css({'-webkit-transform': 'translate3d(0px, 0px, 0)',  'width': '100px', 'height': '100px','background-image': 'none'});
                        //that.moveItemDom.css({'left': '0', 'top': '0', 'width': '100px', 'height': '100px','background-image': 'none'});
                        that.e.unbind();
                        return false;
                    }

                    that.isMove = true;
                    var index = that.getHeroIndex();

                    // 布阵引导
                    if (miscpop.in_array(guide.now, [1102, 1112, 1113, 1611])) {
                        
                        var targetDom = null;

                        if (guide.now == 1102) {
                            targetDom = that.matrixContentDom.find('#pos4');
                        } else if (guide.now == 1112) {
                            targetDom = that.matrixContentDom.find('#pos6');
                        } else if (guide.now == 1113) {
                            targetDom = that.matrixContentDom.find('#pos2');
                        } else if (guide.now == 1611) {
                            targetDom = that.matrixContentDom.find('#pos7');
                        }

                        var targetHeroId = targetDom.attr('heroid');
                        var targetOffset = targetDom.offset();
                        var targetPos = targetDom.attr('pos');

                        if (guide.now == 1102 || guide.now == 1112 || guide.now == 1611) {
                            
                            // 更新目标位置的ID
                            that.matrixArr[targetPos - 1] = that.moveHeroId;
                            that.selectFormationData[targetPos] = that.moveHeroId;

                            // 占据目标框
                            that.moveItemDom.animate({'-webkit-transform': 'translate3d('+targetOffset.left+'px, '+targetOffset.top+'px, 0)', 'width': targetOffset.width + 'px', 'height': targetOffset.height + 'px'}, 100, 'linear', function () {
                                targetDom.children('.pic').css('background-image', 'url('+ xGame.avatar(that.heroData[that.moveHeroId].image) +')');
                                that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});
                                that.showFormation();
                                xGame.showGuide([1102, 1112, 1611]);
                            });
                        } else if (guide.now == 1113) {

                            that.replaceItemDom.css({'-webkit-transform': 'translate3d('+targetOffset.left + 'px, '+targetOffset.top + 'px, 0)', 'width': targetOffset.width + 'px', 'height': targetOffset.height + 'px', 'background': 'url('+ xGame.avatar(that.heroData[targetHeroId].image) +') 50% 50% no-repeat', 'background-size': '80px'});
                            
                            // 将移动的将领放进框中
                            targetDom.children('.pic').css('background-image', 'none');
                            that.moveItemDom.animate({'-webkit-transform': 'translate3d('+targetOffset.left+'px, '+targetOffset.top+'px, 0)', 'width': targetOffset.width + 'px', 'height': targetOffset.height + 'px'}, 100, 'linear', function () {
                                targetDom.children('.pic').css('background-image', 'url('+ xGame.avatar(that.heroData[that.moveHeroId].image) +')');
                                that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});
                            });

                            // 交换两个将领的ID
                            that.matrixArr[index] = targetHeroId;
                            that.matrixArr[targetPos - 1] = that.moveHeroId;
                            that.selectFormationData[index + 1] = targetHeroId;
                            that.selectFormationData[targetPos] = that.moveHeroId;
                            var nowHero = that.matrixContentDom.find('#pos' + (index + 1));
                            var nowHeroOffset = nowHero.offset();

                            // 移动被替换的将领
                            that.replaceItemDom.animate({'-webkit-transform': 'translate3d('+nowHeroOffset.left + 'px, '+nowHeroOffset.top+'px, 0)'}, 100, 'linear', function () {
                                that.replaceItemDom.css({'-webkit-transform': 'translate3d(0px, 0px, 0)', 'background': 'none'});
                                nowHero.children('.pic').css('background-image', 'none');
                                that.showFormation();
                                xGame.showGuide([1113]);
                            });
                        }

                        that.e.unbind();
                        return false;
                    }


                    // 如果移动的将领与阵法中的框重叠
                    if (that.checkCross()) {
                        
                        var targetDom = that.matrixContentDom.find('.matrix_item').eq(that.targetIndex);
                        var targetHeroId = targetDom.attr('heroid');
                        var targetOffset = targetDom.offset();
                        var targetPos = targetDom.attr('pos');

                        // 如果目标框中有其他将领
                        if (targetHeroId != null && targetHeroId != that.moveHeroId) {

                            that.replaceItemDom.css({'-webkit-transform': 'translate3d('+targetOffset.left + 'px, '+targetOffset.top+'px, 0)', 'width': targetOffset.width + 'px', 'height': targetOffset.height + 'px', 'background': 'url('+ xGame.avatar(that.heroData[targetHeroId].image) +') 50% 50% no-repeat', 'background-size': '80px'});

                            // 将移动的将领放进框中
                            targetDom.children('.pic').css('background-image', 'none');
                            that.moveItemDom.animate({'-webkit-transform': 'translate3d('+targetOffset.left + 'px, '+targetOffset.top+'px, 0)', 'width': targetOffset.width + 'px', 'height': targetOffset.height + 'px'}, 100, 'linear', function () {
                                targetDom.children('.pic').css('background-image', 'url('+ xGame.avatar(that.heroData[that.moveHeroId].image) +')');
                                that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});
                            });

                            if (index != -1) {  // 如果移动的将领已上阵

                                // 交换两个将领的ID
                                that.matrixArr[index] = targetHeroId;
                                that.matrixArr[targetPos - 1] = that.moveHeroId;
                                that.selectFormationData[index + 1] = targetHeroId;
                                that.selectFormationData[targetPos] = that.moveHeroId;
                                var nowHero = that.matrixContentDom.find('#pos' + (index + 1));
                                var nowHeroOffset = nowHero.offset();

                                // 移动被替换的将领
                                that.replaceItemDom.animate({'-webkit-transform': 'translate3d('+nowHeroOffset.left + 'px, '+nowHeroOffset.top+'px, 0)'}, 100, 'linear', function () {
                                    nowHero.children('.pic').css('background-image', 'none');
                                    that.replaceItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'background': 'none'});
                                    that.showFormation();
                                });
                            } else {    // 移动的将领未上阵

                                // 替换将领的ID
                                that.matrixArr[targetPos - 1] = that.moveHeroId;
                                that.selectFormationData[targetPos] = that.moveHeroId;
                                
                                // 将被替下的将领移动回来
                                var leftHeroDom = that.heroConentDom.find('#hero' + targetHeroId);
                                var leftHeroOffset = leftHeroDom.offset();
                                that.replaceItemDom.animate({'-webkit-transform': 'translate3d('+leftHeroOffset.left + 'px, '+leftHeroOffset.top+'px, 0)', 'width': leftHeroOffset.width + 'px', 'height': leftHeroOffset.height + 'px'}, 100, 'linear', function () {
                                    that.replaceItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'background': 'none'});
                                    that.showFormation();
                                });

                            }

                        } else if (targetHeroId == that.moveHeroId) {   // 如果目标将领与移动将领一样
                            that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});
                        } else {    // 如果目标框为空

                            // 判断上阵将领是否达到5人
                            if (that.bodyCount() && index == -1) {
                                that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});
                                xGame.toast.show(lang('teampop_only_5'));
                                that.e.unbind();
                                return false;
                            }
                            
                            // 更新目标位置的ID
                            that.matrixArr[targetPos - 1] = that.moveHeroId;
                            that.selectFormationData[targetPos] = that.moveHeroId;

                            if (index != -1) {  // 如果移动的将领已上阵
                                that.matrixArr[index] = 0;
                                that.selectFormationData[index + 1] = 0;
                            }

                            // 占据目标框
                            that.moveItemDom.animate({'-webkit-transform': 'translate3d('+targetOffset.left+'px, '+targetOffset.top+'px, 0)', 'width': targetOffset.width + 'px', 'height': targetOffset.height + 'px'}, 100, 'linear', function () {
                                targetDom.children('.pic').css('background-image', 'url('+ xGame.avatar(that.heroData[that.moveHeroId].image) +')');
                                that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});
                                that.showFormation();
                            });
                        }
                    } else {    // 如果没有与可移动的阵法框重叠
                        that.moveItemDom.css({'-webkit-transform': 'translate3d(0, 0, 0)', 'width': '100px', 'height': '100px','background-image': 'none'});

                        // 如果是阵中的将领
                        if ($this.hasClass('matrix_item')) {

                            that.matrixArr[index] = 0;
                            that.selectFormationData[index + 1] = 0;
                            that.showFormation();
                        }
                    }

                    that.e.unbind();

                });

            });
        },

        // 统计上阵人数
        bodyCount: function () {

            var that = this;
            var item = that.matrixContentDom.find('.matrix_item.on');

            if (item.length >= 5) {
                return true;
            }
        },

        // 检测移动的奖领是否与阵法中的框相交，如果没有，返回false，如果有，返回最近的那个
        checkCross: function () {
            
            var that = this;
            that.targetIndex = 0;
            var matrixItemDom = that.matrixContentDom.find('#matrix_list').find('.matrix_item');
            var offset1 = that.moveItemDom.offset();
            var offset2 = null;
            var result = false;
            var firstDis = 100000;
            var secondDis = 0;
            var disX = 0;
            var disY = 0;

            for (var i = 0, len = matrixItemDom.length; i < len; i++) {

                if (!matrixItemDom.eq(i).hasClass('canMove')) {
                    continue;
                }

                offset2 = matrixItemDom.eq(i).offset();

                if (that.checkPos(offset1, offset2)) {
                    result = true;
                    disX = (offset1.left + offset1.width / 2) - (offset2.left + offset2.width / 2);
                    disY = (offset1.top + offset1.height / 2) - (offset2.top + offset2.height / 2);
                    secondDis = Math.sqrt(disX * disX + disY * disY);

                    if (firstDis > secondDis) {
                        firstDis = secondDis;
                        that.targetIndex = i;
                    }
                }
            }

            return result;
        },

        // 检测两个物体是否重叠
        checkPos: function (offset1, offset2) {
            
            if (offset1.left + offset1.width >= offset2.left && offset2.left + offset2.width >= offset1.left && offset1.top + offset1.height >= offset2.top && offset2.top + offset2.height >= offset1.top) {
                return true;
            } else {
                return false;
            }
        },

        // 检测是否是点击
        checkClick: function (offset1, offset2) {
            
            if (offset1.left == offset2.left && offset1.top == offset2.top) {
                return true;
            } else {
                return false;
            }
        },

        // 放大移动的将领
        scaleAnim: function () {

            var that = this;
            var scale = 1.5;
            var offset = that.moveItemDom.offset();
            var centerX = offset.left + offset.width / 2;
            var centerY = offset.top + offset.height / 2;
            var width = offset.width * scale;
            var height = offset.height * scale;
            var left = centerX - width / 2;
            var top = centerY - height / 2;

            that.moveItemDom.css({'-webkit-transform': 'translate3d('+left+'px, '+top+'px, 0)', 'width': width + 'px', 'height': height + 'px'});
            that.moveItemLeft = left;
            that.moveItemTop = top;
        },

        // 获取将领的索引
        getHeroIndex: function () {

            var that = this;
            var index = -1;

            for (var i = 0, len = that.matrixArr.length; i < len; i++) {
                
                if (that.moveHeroId == that.matrixArr[i]) {
                    index = i;
                    break;
                }
            }

            return index;
        },

        // 更新阵法数据
        updateTeam: function (callback) {
            
            var that = this;

            // 如果阵法中将领数据没变
            if (that.isMove == false) {
                return false;
            }

            var list = that.matrixArr.toString();

            if (callback != undefined) {

                var render = function (res) {

                    if (parseInt(res.status) == 1) {
                        that.openMatrix();
                    } else {
                        xGame.toast.show(lang('undefined_error'));
                    }
                }
            } else {
                var render = null;
            }

            loadContents({
                'c': 'matrix',
                'm': 'update_matrix',
                'list': list,
                'mid': that.selectMid
            }, render, true);
            render = null;
        },

        // 获取阵法数据
        getMatrixData: function () {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {

                xGame.unlock();
                
                switch (parseInt(res.status)) {
                    case 1:
                        xGame.islock = true;
                        that.showMatrix(res.matrix);
                        that.teamContentDom.find('#team' + that.selectMid).addClass('selected').siblings().removeClass('selected');

                        // 是否显示启用阵法按钮
                        if (that.nowMid == that.selectMid) {
                            that.teamContentDom.find('.btn').hide();
                        } else {
                            that.teamContentDom.find('.btn').show();
                        }
                        break;
                    case -1:
                        xGame.toast.show(lang('get_own_team_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('get_team_rule_fail'));
                        break;
                    case -3:
                        xGame.toast.show(lang('get_hero_fail'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };

            loadContents({
                'c': 'matrix',
                'm': 'get_info',
                'mid': that.selectMid
            }, render);
            render = null;
        },
        
        // 将vip数据转换为相应的图片
        transfer: function(num) {
            num = parseInt(num);
            var s;
            if (num == 0) {
                s = '';
            } else if (num >= 10) {
                s = '<span class="vip_flag ten"><em class="vip' + num % 10 + '"></em><em class="vip' + Math.floor(num / 10) + '"></em></span>';
            } else {
                s = '<span class="vip_flag"><em class="vip' + num + '"></em></span>';
            }
            return s;
        },

        // 启用阵法
        openMatrix: function () {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var render = function (res) {

                xGame.unlock();
                
                switch (parseInt(res.status)) {
                    case 1:
                        that.teamContentDom.find('#team' + that.nowMid).find('.text').html('');
                        that.teamContentDom.find('#team' + that.selectMid).find('.text').html(lang('enable'));
                        that.teamContentDom.find('.btn').hide();
                        that.nowMid = that.selectMid;
                        break;
                    case 2:
                        xGame.toast.show(lang('parameter_error'));
                        break;
                    case 3:
                        xGame.toast.show(lang('no_hero_to_enable'));
                        break;
                    case -1:
                        xGame.toast.show(lang('enable_team_fail'));
                        break;
                    case -2:
                        xGame.toast.show(lang('undefined_error'));
                        break;
                    default :
                        xGame.toast.show(lang('undefined_error'));
                        break;
                }
            };

            loadContents({
                'c': 'matrix',
                'm': 'use_matrix',
                'mid': that.selectMid
            }, render);
            render = null;
        },

        // 启用阵法时，判断是否有将领上阵
        isNotBlock: function() {
            var that = this;

            for (var i = 0, len = that.matrixArr.length; i < len; i++) {
                
                if (parseInt(that.matrixArr[i]) > 0) {
                    return true;
                }
            }

            return false;
        },

        // 显示阵法提示
        showMsgByLevel: function(level) {
            var str = '';
            if (parseInt(this.selectMid) > 8) {
                str = lang('team_lv_high');
            } else {
                if (level < 3) {
                    str = lang('team_lv3_open_3_hero');
                } else if (level == 3) {
                    str = lang('team_lv4_open_4_hero');
                } else if (level == 4) {
                    str = lang('team_lv5_open_5_hero');
                } else if (level >= 5) {
                    str = lang('team_lv_high');
                }
            }
            return str;
        },

        // 升级阵法
        levelupMatrix: function() {
            var that = this;

            if (xGame.checkLock()) {
                return false;
            }

            var Func = function(res) {
                switch (parseInt(res.status)) {
                case - 1 : xGame.toast.show(lang('get_team_info_fail'));
                    break;
                case 2:
                    xGame.toast.show(lang('lv_is_full'));
                    break;
                case 3:
                    xGame.toast.show(lang('team_lv_need_lv', [res.info.matrix_level, res.info.master_level]));
                    break;
                case 4:
                    xGame.goPay(lang('gold_not_enough_for_lv_team'));
                    break;
                case 1:
                    that.matrixContentDom.find('#matrix_name').find('em').html('(' + res.info.level + lang('lv') + ')');
                    that.matrixContentDom.find('#matrix_info').html(helper.getPowerName(res.info.type) + lang('up_percent', [res.info.percent]));

                    that.showBtnByPrice(res.info.next);

                    if (parseInt(res.info.position) != 0) {
                        that.matrixArr[res.info.position - 1] = 0;
                        that.selectFormationData[res.info.position] = 0;
                        that.matrixContentDom.find('#matrix_list').find('#pos' + res.info.position).removeClass('close').addClass('block').addClass('canMove');
                    }
                    player.updateinfo({
                        "gold": res.info.gold
                    });
                    that.matrixContentDom.find('p').html(lang('notice') + ':' + that.showMsgByLevel(res.info.level));

                    //在平乱副本里
                    if (pacifypop && pacifypop.e) {
                        pacifypop.militaryDom.find('.pacify_gold span').text(res.info.gold);
                    }

                    //新手引导
                    xGame.showGuide([1101, 1111, 1610]);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'matrix',
                'm': 'levelup',
                'mid': that.selectMid
            },
            Func);
            Func = null;
        },

        // 显示阵法升级要花费的钱币
        showBtnByPrice: function(price) {
            var that = this;
            if (price == 0) {
                that.matrixContentDom.find('#matrix_lvup_btn').html('').hide();
            } else {
                that.matrixContentDom.find('#matrix_lvup_btn').html('x' + price).show();
            }
        },

        // 针对PC版本，托送移出屏幕外的异常处理
        preventOverflowOnPC:function() {
        	if($.os.desktop) {
        		var that = this;

        		// 第1种 离开整个窗口
        		document.onmouseleave = function() {
		            if(that.moving) {
		            	that.e.trigger('touchend', []);
		            }
		        };
		        // 第2种 离开游戏区域
        		var bodyOffset = $('body').offset();
        		$('html').unbind('mousemove').bind('mousemove', function(e) {
        			if(that.moving) {
	        			if(e.clientX > bodyOffset.width || e.clientY > bodyOffset.height) {// 越界
	        				that.e.trigger('touchend', []);
	        			}
        			}
				});
        	}
        },

        stopPreventOfPC: function() {
        	if($.os.desktop) {
        		document.onmouseleave = null;
        		$('html').unbind('mousemove');
        	}
        },

        unbindAll: function() {
            if (this.e) {
                this.e.find('.content').find('.hidebtn').unbind('touchend');
                this.e.unbind('touchmove');
                this.e.unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.matrixContentDom) {
                this.matrixContentDom.find('#matrix_head').undelegate('a', 'touchend');
                this.matrixContentDom.remove();
                this.matrixContentDom = null;
            }
            if (this.teamContentDom) {
                this.teamContentDom.find('#teampop_list').undelegate('.team_item', 'click');
                this.teamContentDom.undelegate('.btn', 'touchend');
                this.teamContentDom.remove();
                this.teamContentDom = null;
            }
            if (this.wrapDom) {
                this.wrapDom.undelegate('.touch_item', 'touchstart');
                this.wrapDom.remove();
                this.wrapDom = null;
            }
        },

        hide: function() {

            xGame.unlock();
            this.updateTeam();
            this.step = 0;
            this.isshow = false;
            this.heroData = null;
            this.matrixData = null;
            this.matrixArr = null;
            this.nowMid = 0;
            this.selectMid = 0;

            if (this.scroller != null) {
                this.scroller.destroy();
                this.scroller = null;
            }
            
            this.formationData = null;
            this.selectFormationData = null;
            this.isMove = false;
            this.moving = false;
            this.targetIndex = 0;
            this.moveHeroId = 0;
            this.startPos = null;
            this.moveItemLeft = 0;
            this.moveItemTop = 0;
            if (this.moveItemDom) {
                this.moveItemDom.remove();
                this.moveItemDom = null;
            }
            if (this.heroConentDom) {
                this.heroConentDom.remove();
                this.heroConentDom = null;
            }
            if (this.replaceItemDom) {
                this.replaceItemDom.remove();
                this.replaceItemDom = null;
            }
            this.unbindAll();

            this.stopPreventOfPC();
        },
    };
})();
