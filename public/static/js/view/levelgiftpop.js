/* 等级大礼包 */
(function() {
    levelgiftPopClass = function() {
        this.e = null;
        this.isshow = false;
        this.rewardDom = null;
        this.itemDom = null;
        this.itemInfoDom = null;
        this.avatarDom = null;
        this.avatarInfoDom = null;
        this.myScroller = null;
        this.anim = null;
        this.clearAnim = null;
        this.rewardData = null;//奖励数据
        this.deleteThread = null;  // 删除线程
        this.listItemMarginB = 0;
        this.rewardIndex = 0;   // 最后一个可领取奖励的index
        this.listItemHeight = 0;    // 每条奖励所占高度
        this.max = 0;   // 最大滚动高度
        this.step = 0;
    };

    levelgiftPopClass.prototype = {
        init: function(step) {
            var that = this;
            that.step = step;

            if (that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                
                switch (res.status) {
                    case 1:
                        that.initPage(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        $('#worldloading').hide();
                        break;
                }
            };
            
            //加载数据
            loadContents({
                'c': 'levelgift',
                'm': 'index'
            },
            Func);

            Func = null;
        },
        
        initPage: function(res) {
            
            var that = this;
            that.e = xGame.loadPopTmpl('levelgift');
            
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                e.preventDefault();
            });
            
            that.rewardDom = that.e.find('#levelgift_list');
            that.itemDom = that.e.find('#levelgift_item_info_dom');
            that.itemInfoDom = that.e.find('#levelgift_item_info_main');
            that.avatarDom = that.e.find('#levelgift_info_dom');
            that.avatarInfoDom = that.e.find('#levelgift_info_main');
            that.rewardData = res.list;
            that.buildList(res.list);
            that.bindAll();
            
            that.listItemMarginB = parseInt(that.rewardDom.children('li').css('margin-bottom')); // 每条奖励的边距
            that.listItemHeight = that.rewardDom.children('li').height() + that.listItemMarginB; // 每条奖励所占高度
            var listHeight = that.e.find('#levelgift_scroller').height();
            that.max = (that.listItemHeight * res.list.length - listHeight) * -1;
            var my = that.listItemHeight * that.rewardIndex * -1;
            
            if (my < that.max) {
                that.rewardDom.children('li:last-child').css('margin-bottom', that.listItemMarginB + that.max - my + 'px');
            }

            that.myScroller = new iScroll('levelgift_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.myScroller.scrollTo(0, my);
            that.myScroller.refresh();
            
            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();

            xGame.showGuide([1325, 1501]);

            // 引导续接
            if (that.step) {
                guide.show(that.step);
            }
        },
        
        buildList: function(list) {

            var html = '';
            var len = list.length;

            for (var i = 0; i < len;i ++) {

                html += '<li id="levelgift_l' + list[i].level + '">';

                switch (parseInt(list[i].type)) {
                    case 1:
                        html += '<button class="btn pop_btn_middle" level="' + list[i].level + '">' + lang('receive') + '</button>';
                        this.rewardIndex = i;
                        break;
                    case 2:
                        html += '<button class="btn pop_btn_middle_disable" level="' + list[i].level + '">' + lang('not_reached') + '</button>';
                        break;
                    case 3:
                        html += '<button class="btn pop_btn_middle_disable" level="' + list[i].level + '">' + lang('warena_have_fetch') + '</button>';
                        break;
                    default :
                        break;
                }

                html +='<div class="title">' + lang('levelgiftpop_title', [list[i].level]) + '</div>';
                html += this.buildRewardHtml(list[i]);
                html += '</li>';
            }

            this.rewardDom.html(html);
            html = null;
        },
        
        bindAll: function() {
            var that = this;

            that.rewardDom.unbind('touchend').one('touchend', function () {
                that.rewardDom.children('li:last-child').css('margin-bottom', that.listItemMarginB + 'px');
                that.myScroller.refresh();
            });

            that.rewardDom.undelegate('.pop_btn_middle', 'click').delegate('.pop_btn_middle', 'click',
            function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.receiveGift(parseInt($(this).attr('level')));
            });

            // 弹出装备信息窗口
            that.rewardDom.undelegate('li .rtype3', 'click').delegate('li .rtype3', 'click',
            function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var $li = $(this).parents('li');
                miscpop.showItemInfo(that.rewardData[$li.index()].information[$li.children('.reward').index($(this)) + 1]);
                e.preventDefault();
                e.stopPropagation();
            });

            // 弹出将魂信息窗口
            that.rewardDom.undelegate('li .rtype9', 'click').delegate('li .rtype9', 'click',
            function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var $li = $(this).parents('li');
                miscpop.showSoulInfo(that.rewardData[$li.index()].soul[$li.children('.reward').index($(this)) + 1]);
                e.preventDefault();
                e.stopPropagation();
            });

            // 弹出武将信息窗口
            that.rewardDom.undelegate('li .rtype10', 'click').delegate('li .rtype10', 'click',
            function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var $li = $(this).parents('li');
                miscpop.showHeroInfo(that.rewardData[$li.index()].information[$li.children('.reward').index($(this)) + 1]);
                e.preventDefault();
                e.stopPropagation();
            });

            that.itemDom.unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                $(this).css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

            that.itemInfoDom.unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                e.stopPropagation();
            });

            that.avatarInfoDom.unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                e.preventDefault();
                e.stopPropagation();
            });

            that.e.find('.mask').unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.itemDom.css('display', 'none');
                e.preventDefault();
                e.stopPropagation();
            });

        },
        
        buildRewardHtml: function(data) {
            var html = '';
            var gettype = 0;
            for (var i = 1; i < 5; i ++) {
                gettype = parseInt(data['gettype' + i]);
                if (gettype == 3) {
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '">';
                    html += '<span class="item" style="background-image: url(' + xGame.itemurl(data.information[i].id) + ');"></span>';
                    html += '<b class=' + (data.information[i].name.length == 5 ? '"indent"' : '""') + 'style="color: ' + helper.getItemColor(data.information[i].quality) + '">' + data.information[i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 9) {
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '">';
                    html += '<span class="item" style="background-image: url(http://img2.hanjiangsanguo.com/hjimg/pop/soul_' + data.soul[i].image + '.png' + ');"></span>';
                    html += '<b style="color: ' + helper.getItemColor(data.soul[i].quality) + '">' + data.soul[i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 10) {
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '">';
                    html += '<span class="avatar" style="background-image: url(' + xGame.avatar(data.information[i].image) + ');"></span>';
                    html += '<b style="color: ' + helper.getItemColor(data.information[i].quality) + '">' + data.information[i].name + '</b>';
                    html += '</div>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html +='<div class="reward reward' + i + ' rtype' + gettype + '">';
                    html +='<span></span>';
                    html +='<b>x' + data['getvalue' + i] + '</b>';
                    html +='</div>';
                }
            }
            return html;
        },
        
        receiveGift: function(level) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {
                switch(parseInt(res.status)) {
                    case 1:

                        if (that.anim) {
                            that.hideAnim();
                        }

                        if (that.clearAnim) {
                            clearTimeout(that.clearAnim);
                        }
                        
                        that.e.find('.levelgift_reward_box').remove();
                        
                        if (that.deleteThread) {
                            clearTimeout(that.deleteThread);
                        }

                        that.deleteThread = null;
                        
                        player.updateinfo({
                            'gold' : res.all_gold,
                            'act' : res.all_act,
                            'silver' : res.all_silver
                        });

                        that.buildList(res.list);

                        for (var i = 0, len = res.list.length; i < len; i++) {
                            if (res.list[i].level == level) {
                                that.rewardDown(res.list[i]);

                                if (i != 0) {
                                    
                                    for (var j = 0; j < i; j++) {

                                        if (parseInt(res.list[j].type) == 1) {
                                            that.rewardIndex = j;
                                        }
                                    }

                                    var my = that.listItemHeight * that.rewardIndex * -1;

                                    if (my > that.max) {
                                        that.myScroller.scrollTo(0, my, 500);
                                        that.myScroller.refresh();
                                    }
                                }
                            }

                        }

                        that.playAnim(that.rewardDom.find('#levelgift_l' + level));

                        that.clearAnim = setTimeout(function () {
                            that.hideAnim();
                        }, 3000);

                        if (res.popo == 0) {
                            $('#header_levelgift_btn_wrap').children('.popo').hide();
                        }

                        if (res.lost == 0) {
                            $('#header_levelgift_btn_wrap').hide();
                        }

                        // 新手引导
                        xGame.showGuide([1326, 1502]);

                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }

                xGame.unlock();
            };

            loadContents({
                'c': 'levelgift',
                'm': 'get_reward',
                'level': level
            },
            Func);

            Func = null;
        },
        
        rewardDown: function(data) {
            
            var html = '';
            var gettype = 0;
            html += '<div class="levelgift_reward_box">';

            for (var i = 1;i < 5; i ++) {
                gettype = parseInt(data['gettype' + i]);
                if (gettype == 3) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-image: url(' + xGame.itemurl(data.information[i].id) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.information[i].quality) + '">' + data.information[i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 9) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-image: url(http://img2.hanjiangsanguo.com/hjimg/pop/soul_' + data.soul[i].image + '.png' + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.soul[i].quality) + '">' + data.soul[i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 10) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="avatar" style="background-image: url(' + xGame.avatar(data.information[i].image) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.information[i].quality) + '">' + data.information[i].name + '</b>';
                    html += '</span>';
                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '"><em></em>x' + data['getvalue' + i] + '</span>';
                }
            }

            html += '</div>';
            this.e.find('#levelgiftpop_wrap').append(html);
            
            this.deleteThread = setTimeout(function() {
                
                if (levelgiftpop.e) {
                    levelgiftpop.e.find('.levelgift_reward_box').remove();
                }

            }, 3000);
            
            html = null;
        },
        
        playAnim: function(obj) {
            var that = this;
            var length = obj.find('.reward').length;

            var xy_config = {
                1 : [6, 30],
                2 : [98, 30],
                3 : [190, 30],
                4 : [282, 30]
            };

            that.anim = [];

            for (var i = 1; i <= length; i++) {
                that.anim[i] = new movieClipClass({
                    "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                    "width": 93,
                    "height": 90,
                    "x": xy_config[i][0],
                    "y": xy_config[i][1],
                    "loop": true,
                    "frames": [{
                        rect: [0, 0],
                        interval: 100
                    },
                    {
                        rect: [93, 0],
                        interval: 100
                    },
                    {
                        rect: [186, 0],
                        interval: 100
                    },
                    {
                        rect: [279, 0],
                        interval: 100
                    },
                    {
                        rect: [0, 90],
                        interval: 100
                    },
                    {
                        rect: [93, 90],
                        interval: 100
                    },
                    {
                        rect: [186, 90],
                        interval: 100
                    }
                    ]
                });
                //开始播放
                that.anim[i].appendTo(obj);
                that.anim[i].play();
            }
        },

        hideAnim: function() {
            if (!this.anim) {
                return;
            }

            for (var i = 1; i <= 4; i++) {
                if (this.anim[i]) {
                    this.anim[i].destory();
                }
            }
            this.anim = null;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('.mask').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.rewardDom) {
                this.rewardDom.unbind('touchend');
                this.rewardDom.undelegate('.pop_btn_middle', 'click');
                this.rewardDom.undelegate('li .rtype3', 'click');
                this.rewardDom.undelegate('li .rtype9', 'click');
                this.rewardDom.undelegate('li .rtype10', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.itemDom) {
                this.itemDom.unbind('touchend');
                this.itemDom.remove();
                this.itemDom = null;
            }
            if (this.itemInfoDom) {
                this.itemInfoDom.unbind('touchend');
                this.itemInfoDom.remove();
                this.itemInfoDom = null;
            }
            if (this.avatarInfoDom) {
                this.avatarInfoDom.unbind('touchend');
                this.avatarInfoDom.remove();
                this.avatarInfoDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            this.step = 0;
                        
            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            
            if (this.deleteThread) {
                clearTimeout(this.deleteThread);
                this.deleteThread = null;
            }

            if (this.clearAnim) {
                clearTimeout(this.clearAnim);
                this.clearAnim = null;
            }

            this.listItemMarginB = 0;
            this.listItemHeight = 0;
            this.max = 0;
            this.rewardIndex = 0;
            this.rewardData = null;
            this.avatarDom = null;
            this.hideAnim();
            this.unbindAll();
        },
    };
})();