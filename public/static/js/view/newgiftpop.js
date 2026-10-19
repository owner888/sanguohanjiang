/* 新服礼包 */
(function() {
    newgiftPopClass = function() {
        this.e = null;
        this.isshow = false;
        this.rewardDom = null;
        this.headRewardDom = null;
        this.avatarDom = null;
        this.avatarInfoDom = null;
        this.itemDom = null;
        this.itemInfoDom = null;
        this.myScroller = null;
        this.anim = null;
        this.headAnim = null;
        this.clearAnim = null;
        this.rewardData = null;//奖励数据
        this.deleteThread = null;//删除线程
        this.listItemMarginB = 0;
        this.rewardIndex = 0;   // 最后一个可领取奖励的index
        this.listItemHeight = 0;    // 每条奖励所占高度
        this.max = 0;   // 最大滚动高度
    };

    newgiftPopClass.prototype = {
        init: function() {
            var that = this;

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
                'c': 'newgift',
                'm': 'index'
            },
            Func);
            Func = null;
        },
        
        initPage: function(res) {
            
            var that = this;
            that.e = xGame.loadPopTmpl('newgift');

            that.avatarDom = that.e.find('#newgift_info_dom');
            that.avatarInfoDom = that.e.find('#newgift_info_main');
            that.itemDom = that.e.find('#newgift_item_info_dom');
            that.itemInfoDom = that.e.find('#newgift_item_info_main');
            
            that.e.find('.hidebtn').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.hide();
                e.preventDefault();
            });
            
            xGame.updateCd('newgift', res.cd);
            that.rewardDom = that.e.find('#newgift_list');
            that.headRewardDom = that.e.find('#head_reward');
            that.rewardData = res.list;
            that.buildHeadReward(res.info);
            that.buildList(res.list);
            that.bindAll();
            
            that.listItemMarginB = parseInt(that.rewardDom.children('li').css('margin-bottom')); // 每条奖励的边距
            that.listItemHeight = that.rewardDom.children('li').height() + that.listItemMarginB; // 每条奖励所占高度
            var listHeight = that.e.find('#newgift_scroller').height();
            that.max = (that.listItemHeight * res.list.length - listHeight) * -1;
            var my = that.listItemHeight * that.rewardIndex * -1;
            
            if (my < that.max) {
                that.rewardDom.children('li:last-child').css('margin-bottom', that.listItemMarginB + that.max - my + 'px');
            }

            that.myScroller = new iScroll('newgift_scroller', {
                bounce: false,
                hScrollbar: false,
                vScrollbar: false
            });

            that.myScroller.scrollTo(0, my);
            that.myScroller.refresh();
            
            that.e.show();
            that.isshow = true;
            $('#worldloading').hide();
        },
        
        buildHeadReward: function (info) {
            
            var html = '';

            for (var i = 0, len = info.length; i < len; i++) {
                html += '<div id="head_reward' + i + '" class="head_reward_item" style="background: url(' + xGame.avatar(info[i].image);
                html += ') center center no-repeat;"><span class="reward_text"></span></div>';
            }

            this.headRewardDom.html(html);
            this.buildHeadTouchDom();
            this.playHeadAnim(this.e.find('.content'));
        },

        buildList: function(list) {
            var html = '';
            var len = list.length;

            for (var i = 0; i < len;i ++) {

                html += '<li id="newgift_l' + list[i].id + '">';

                switch (parseInt(list[i].type)) {
                    case 1:
                        html += '<button class="btn pop_btn_middle" gid="' + list[i].id + '">' + lang('receive') + '</button>';
                        this.rewardIndex = i;
                        break;
                    case 2:
                        html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '">' + lang('not_reached') + '</button>';
                        break;
                    case 3:
                        html += '<button class="btn pop_btn_middle_disable" gid="' + list[i].id + '">' + lang('warena_have_fetch') + '</button>';
                        break;
                    default :
                        break;
                }

                html +='<div class="title">' + lang('newgiftpop_title', [list[i].id]) + '</div>';
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
                that.receiveGift(parseInt($(this).attr('gid')));
            });

            // 弹出武将信息窗口
            that.rewardDom.undelegate('li .rtype8', 'click').delegate('li .rtype8', 'click',
            function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                miscpop.showHeroInfo(that.rewardData[$(this).parents('li').index()].information);
                e.preventDefault();
            });

            // 弹出装备信息窗口
            that.rewardDom.undelegate('li .rtype3', 'click').delegate('li .rtype3', 'click',
            function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                miscpop.showItemInfo(that.rewardData[$(this).parents('li').index()].information);
                e.preventDefault();
            });

            that.headRewardDom.undelegate('.head_reward_item', 'click').delegate('.head_reward_item', 'click',
            function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                switch (e.target.id) {
                    case 'head_touch0':
                        miscpop.showHeroInfo(that.rewardData['6'].information);
                        break;
                    case 'head_touch1':
                        miscpop.showHeroInfo(that.rewardData['14'].information);
                        break;
                    case 'head_touch2':
                        miscpop.showHeroInfo(that.rewardData['29'].information);
                        break;
                    default :
                        break;
                }
                e.preventDefault();
            });

            that.avatarDom.unbind('touchend').bind('touchend', function (e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                $(this).css('display', 'none');
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

            that.e.find('.mask').unbind('touchend').bind('touchend', function (e) {
                
                if (xGame.checkLock(true)) {
                    return false;
                }

                if (that.itemDom.css('display') == 'block') {
                    that.itemDom.css('display', 'none');
                }

                if (that.avatarDom.css('display') == 'block') {
                    that.avatarDom.css('display', 'none');
                }

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
                    html += '<span class="item" style="background-image: url(' + xGame.itemurl(data.information.id) + ');"></span>';
                    html += '<b class=' + (data.information.name.length == 5 ? '"indent"' : '""') + 'style="color: ' + helper.getItemColor(data.information.quality) + '">' + data.information.name + '</b>';
                    html += '</div>';
                } else if (gettype == 8) {
                    html += '<div class="reward reward' + i + ' rtype' + gettype + '">';
                    html += '<span class="avatar" style="background-image: url(' + xGame.avatar(data.information.image) + ');"></span>';
                    html += '<b style="color: ' + helper.getItemColor(data.information.quality) + '">' + data.information.name + '</b>';
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
        
        receiveGift: function(gid) {
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
                        
                        that.e.find('.newgift_reward_box').remove();
                        
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
                        
                        var i = gid - 1;
                        that.rewardDown(that.rewardData[i]);

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
                        
                        that.playAnim(that.rewardDom.find('#newgift_l' + gid));

                        that.clearAnim = setTimeout(function () {
                            that.hideAnim();
                        }, 3000);

                        if (res.popo == 0) {
                            $('#header_newgift_btn_wrap').children('.popo').hide();
                        }
                        break;
                    default:
                        xGame.toast.show(res.msg);
                        break;
                }
                xGame.unlock();
            };

            loadContents({
                'c': 'newgift',
                'm': 'get_reward',
                'id': gid
            },
            Func);
            Func = null;
        },
        
        rewardDown: function(data) {
            
            var html = '';
            var gettype = 0;
            html += '<div class="newgift_reward_box">';
            for (var i = 1;i < 5; i ++) {
                gettype = parseInt(data['gettype' + i]);
                if (gettype == 3) {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                    html += '<em class="item" style="background-image: url(' + xGame.itemurl(data.information.id) + ');"></em>';
                    html += '<b style="color: ' + helper.getItemColor(data.information.quality) + '">' + data.information.name + '</b>';
                    html += '</span>';
                } else if (gettype == 8) {
                    
                    if (data.renew == 0) {
                        html += '<span class="reward_down pos' + i + ' rtype' + gettype + '">';
                        html += '<em class="avatar" style="background-image: url(' + xGame.avatar(data.information.image) + ');"></em>';
                        html += '<b style="color: ' + helper.getItemColor(data.information.quality) + '">' + data.information.name + '</b>';
                        html += '</span>';
                    } else {

                        if (parseInt(data.information.id) == 13) {  // 如果是蔡文姬
                            setTimeout(function () {
                                xGame.toast.show(lang('newgiftpop_has', [data.information.name]));
                            }, 1000);
                        } else {
                            html += '<span class="reward_down pos' + i + ' rtype' + ((data.jade_type == 3) ? 6 : 5) + '"><em></em>x' + data.jade_price + '</span>';
                            
                            setTimeout(function () {
                                xGame.toast.show(lang('newgiftpop_renew', [data.jade_type, data.jade_price]));
                            }, 1000);
                        }
                    }

                } else if (gettype == 0) {
                    continue;
                } else {
                    html += '<span class="reward_down pos' + i + ' rtype' + gettype + '"><em></em>x' + data['getvalue' + i] + '</span>';
                }
            }
            html += '</div>';
            this.e.find('#newgiftpop_wrap').append(html);
            
            this.deleteThread = setTimeout(function() {
                if (newgiftpop.e) {
                    newgiftpop.e.find('.newgift_reward_box').remove();
                }
            }, 3000);
            
            html = null;
        },

        playHeadAnim: function(obj) {
            var that = this;

            var xy_config = [
                {left: 24, top: 25, scaleX: 1.5, scaleY: 1.5},
                {left: 152, top: 25, scaleX: 1.5, scaleY: 1.5},
                {left: 281, top: 25, scaleX: 1.5, scaleY: 1.5}
            ];

            that.headAnim = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 222, top: 95, width: 397, height: 140},
                "img": staticUrl + 'img/pop/firstpaypop_anim.png',
                'imgOffset': xy_config,
                "width": 93,
                "height": 90,
                "loop": true,
                "anim": [{
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
            that.headAnim.appendTo(obj);
            that.headAnim.play();
        },

        buildHeadTouchDom: function () {
            var html = '';

            for (var i = 0; i < 3; i++) {
                html += '<div class="head_reward_item" id="head_touch' + i + '"></div>';
            }
            this.headRewardDom.html(this.headRewardDom.html() + html);
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

        hideHeadAnim: function() {
            if (this.headAnim) {
                this.headAnim.destroy();
                this.headAnim = null;
            }
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.find('.mask').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.avatarDom) {
                this.avatarDom.unbind('touchend');
                this.avatarDom.remove();
                this.avatarDom = null;
            }
            if (this.avatarInfoDom) {
                this.avatarInfoDom.unbind('touchend');
                this.avatarInfoDom.remove();
                this.avatarInfoDom = null;
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
            if (this.rewardDom) {
                this.rewardDom.unbind('touchend');
                this.rewardDom.undelegate('.pop_btn_middle', 'click');
                this.rewardDom.undelegate('li .rtype8', 'click');
                this.rewardDom.undelegate('li .rtype3', 'click');
                this.rewardDom.remove();
                this.rewardDom = null;
            }
            if (this.headRewardDom) {
                this.headRewardDom.undelegate('.head_reward_item', 'click');
                this.headRewardDom.remove();
                this.headRewardDom = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            
            if (typeof CD['newgift'] != undefined) {
                xGame.updateCd('newgift', 0);
            }
            
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
            this.hideAnim();
            this.hideHeadAnim();
            this.unbindAll();
        },
    };
})();