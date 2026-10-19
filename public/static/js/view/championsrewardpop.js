/* 战神殿奖励预览 */
(function() {
    championsrewardPopClass = function() {
        this.e;
        this.isshow = false;
        this.myScroller1;
        this.myScroller2;
        this.leftDom;
        this.rightDom;
        this.itemDom = null;
        this.itemInfoDom = null;
        this.itemInfo = {};
        this.avatarDom = null;
        this.avatarInfoDom = null;
        this.avatarInfo = {};
    };
    championsrewardPopClass.prototype = {
        init: function() {
            var that = this;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                if (parseInt(res.status) == -1) {
                    xGame.toast.show(lang('championsrewardpop_time_error'));
                    $('#worldloading').hide();
                    return;
                } else if (parseInt(res.status) == -2) {
                    xGame.toast.show(lang('championsrewardpop_reward_error'));
                    $('#worldloading').hide();
                    return;
                } else if (parseInt(res.status) == 0) {
                    xGame.toast.show(lang('refineachievepop_requst_fail'));
                    $('#worldloading').hide();
                    return;
                } else if (parseInt(res.status) == 1) {
                    that.buildpage(res);
                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();
                    return;
                }
            }
            loadContents({
                'c': 'god',
                'm': 'get_reward_pre'
            },
            Func, true); //加载数据
        },

        buildpage: function(res) {
            var that = this;

            that.e = xGame.loadPopTmpl('championsreward');
            

            that.e.find('.content').find('#hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                championsrewardpop.hide();
                e.preventDefault();
            });

            that.leftDom = that.e.find('#championsreward_left');
            that.rightDom = that.e.find('#championsreward_right');
            that.itemDom = that.e.find('#championsreward_item_info_dom');
            that.itemInfoDom = that.e.find('#championsreward_item_info_main');
            that.avatarDom = that.e.find('#championsreward_info_dom');
            that.avatarInfoDom = that.e.find('#championsreward_info_main');

            //构建左侧英雄滑动门
            var heroData = res.general_list;
            var html1 = '';
            var n = 0;

            for(var i = 0; i < heroData.length; i++) {
                html1 += '<li class="rewardhero" hid="' + heroData[i].id + '">';
                html1 +=    '<p class="reward_top_title"><em class="text">' + heroData[i].reward_name + '</em></p>'
                html1 +=    '<div class="hero_bg1"></div>';
                html1 +=    '<div class="hero_img" style="background-image:url(' + xGame.hero(heroData[i].image, 1) + ');margin-left:' + (hero_ini[heroData[i].image]['mx'] + 80) + 'px"></div>';
                html1 +=    '<div class="hero_bg2"></div>';
                html1 +=    '<div class="hero_bg3"></div>';
                html1 +=    '<div class="hero_name">' + heroData[i].name + '</div>';
                html1 += '</li>';
                n++;
            }
            that.leftDom.find('#reward_list_scroller').html(html1);

            /* 创建滑动门 domid, 单页宽度 */
            that.myScroll1 = xGame.scroller('championsreward_scroller', n * 458);

            //构建右侧列表
            var html2 = '';
            for (var i = 0; i < res.reward_list.length; i++) {

                var rightData = res.reward_list[i];
                html2 += '<li id="championsreward2_scroller_li">';
                html2 += '<span class="li_title">' + rightData.name + '</span><div>';

                for(var j = 1; j <= 3; j++){
                    var gettype = parseInt(rightData['type' + j]);

                    html2 += '<div class="reward' + j + ' reward_box">';

                    switch (gettype) {
                        case 1 : //元宝
                            html2 += '<span class="add_gold"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 2 : //银币
                            html2 += '<span class="add_silver"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 3 : //紫宝石
                            html2 += '<span class="add_get3"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 4 : //玉石
                            html2 += '<span class="add_jade"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 5 : //原魂
                            html2 += '<span class="add_soul"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 6 : //装备
                            html2 += '<span class="item" tid="' + rightData['info' + j].id + '" style="background-image: url(' + xGame.itemurl(rightData['info' + j].id) + ');"></span><em class="num" style="color: ' + helper.getItemColor(rightData['info' + j].quality) + '">' + rightData['info' + j].name + '</em>';
                            that.itemInfo[rightData['info' + j].id] = rightData['info' + j];
                            break;
                        case 7 : //宝石碎片
                            html2 += '<span class="add_gem_part"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 8 : //声望
                            html2 += '<span class="add_prestige"></span><em class="num">' + rightData['number' + j] + '</em>';
                            break;
                        case 9 : //坐骑
                            html2 += '<span class="horse"><span style="background-position:' + helper.getHorsePostion('small', rightData['info' + j].id) + ';"></span></span><em class="num" style="color: ' + helper.getItemColor(rightData['info' + j].quality) + '">' + rightData['info' + j].name + '</em>';
                            break;
                        case 10 : //武将
                            html2 += '<span class="avatar" hid="' + rightData['info' + j].id + '" style="background-image: url(' + xGame.avatar(rightData['info' + j].image) + ');"></span><em class="num" style="color: ' + helper.getItemColor(rightData['info' + j].quality) + '">' + rightData['info' + j].name + '</em>';
                            that.avatarInfo[rightData['info' + j].id] = rightData['info' + j];
                            break;
                        default:
                            break;
                    }
                    html2 += '</div>';
                }
                html2 += '</div></li>';
            }

            that.rightDom.find('#championsreward2_list_scroller').html(html2);

            that.myScroll2 = new iScroll('championsreward2_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.myScroll2.refresh();

            if (parseInt(res.entry_cd) > 0) {
                xGame.updateCd('championreward', res.entry_cd);
            } else {
                that.leftDom.find('.reward_center_cd').hide();
                that.leftDom.find('#champions_join').show();
            }

            /*显示界面*/
            that.bindAll();
            that.e.show();

            $('#worldloading').hide();
        },

        bindAll: function() {
            var that = this;

            // 报名战神殿
            that.leftDom.find('.pop_btn_middle#champions_join').unbind('touchend').bind('touchend', 
                function() {
                    if(xGame.checkLock(true)) {
                        return false;
                    }
                    xGame.loadWorld('champions');
                    championsrewardpop.hide();
                });

            // 武将信息窗口
            that.e.undelegate('li.rewardhero, span.avatar', 'click').delegate('li.rewardhero, span.avatar', 'click', 
                function() {
                if (xGame.checkLock(true)) {
                    return false;
                }

                var heroId = $(this).attr('hid');
                miscpop.showHeroInfo(that.avatarInfo[heroId]);
            });

            // 装备信息窗口
            that.rightDom.find('span.item').unbind('click').bind('click', 
                function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var itemId = $(this).attr('tid');
                miscpop.showItemInfo(that.itemInfo[itemId]);
            });

            //绑定滑动门左右按钮
            that.leftDom.find('#scroller_btn_left').unbind('touchend').bind('touchend',
            function() {
                that.myScroll1.scrollToPage('next', 0);
                return false;
            });
            that.leftDom.find('#scroller_btn_right').unbind('touchend').bind('touchend',
            function() {
                that.myScroll1.scrollToPage('prev', 0);
                return false;
            });

            //规则说明
            that.leftDom.find('#championsreward_info_btn').unbind('click').bind('click', 
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#championsrewardpop_small').css('display', 'block');
            });
            that.e.find('#championsrewardpop_small_hide').unbind('touchend').bind('touchend', function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#championsrewardpop_small').css('display', 'none');
            });
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.content').find('#hidebtn').unbind('touchend');
                this.e.undelegate('li.rewardhero, span.avatar', 'click');
                this.e.find('#championsreward_info_hide').unbind('touchend');
                this.e.find('#championsreward_item_info_hide').unbind('touchend');
                this.e.find('#championsrewardpop_small_hide').unbind('touchend');
            }
            if (this.rightDom) {
                this.rightDom.find('span.item').unbind('click');
            }
            if (this.leftDom) {
                this.leftDom.find('.pop_btn_middle#champions_join').unbind('touchend');
                this.leftDom.find('#scroller_btn_left').unbind('touchend');
                this.leftDom.find('#scroller_btn_right').unbind('touchend');
                this.leftDom.find('#championsreward_info_btn').unbind('click');
            }
        },

        hide: function() {
            xGame.unlock();
            this.unbindAll();
            this.isshow = false;
            if (this.myScroller1 != null) { //先干掉原本的滑动
                this.myScroller1.destroy();
                this.myScroller1 = null;
            }
            if (this.myScroller2 != null) { //先干掉原本的滑动
                this.myScroller2.destroy();
                this.myScroller2 = null;
            }
            if (CD['championreward'] != undefined) {
                xGame.updateCd('championreward', 0);
            }
            this.leftDom = null;
            this.rightDom = null;
            this.itemDom = null;
            this.itemInfoDom = null;
            this.itemInfo = {};
            this.avatarDom = null;
            this.avatarInfoDom = null;
            this.avatarInfo = {};
            this.e.remove();
            this.e = null;
        },
    };
})();